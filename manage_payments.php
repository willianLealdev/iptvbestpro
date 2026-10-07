<?php $page_title="Histórico de Pagamentos";
    // Exportar CSV antes de qualquer saída da página
    $is_csv = isset($_GET['csv']);
    if ($is_csv) ob_start();
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");
    require_once("includes/payment_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    payments_migrate($mysqli);

    /* ---------- Filtros ---------- */
    $kind = (isset($_GET['kind']) && in_array($_GET['kind'], array('credit', 'sub'))) ? $_GET['kind'] : '';
    $method = (isset($_GET['method']) && in_array($_GET['method'], array('mercadopago', 'stripe', 'manual'))) ? $_GET['method'] : '';
    $period = (isset($_GET['period']) && in_array($_GET['period'], array('today', '7d', 'month', 'last_month', '30d', 'year', 'all', 'custom'))) ? $_GET['period'] : 'month';
    $q = isset($_GET['q']) ? mb_substr(trim(strip_tags($_GET['q'])), 0, 100) : '';
    $date_from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
    $date_to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';

    $t_from = 0; $t_to = 0;
    switch ($period) {
        case 'today':      $t_from = strtotime('today'); break;
        case '7d':         $t_from = strtotime('-7 days'); break;
        case '30d':        $t_from = strtotime('-30 days'); break;
        case 'month':      $t_from = strtotime(date('Y-m-01 00:00:00')); break;
        case 'last_month': $t_from = strtotime(date('Y-m-01 00:00:00', strtotime('first day of last month'))); $t_to = strtotime(date('Y-m-01 00:00:00')); break;
        case 'year':       $t_from = strtotime(date('Y-01-01 00:00:00')); break;
        case 'custom':
            if ($date_from != '') $t_from = strtotime($date_from.' 00:00:00');
            if ($date_to != '') $t_to = strtotime($date_to.' 23:59:59') + 1;
            break;
    }

    // Pedidos pagos: créditos de revendedor + assinaturas de cliente (pagos pelo gateway ou confirmados à mão)
    $w = array();
    if ($t_from > 0) $w[] = "x.paid_at >= ".(int)$t_from;
    if ($t_to > 0) $w[] = "x.paid_at < ".(int)$t_to;
    if ($kind != '') $w[] = "x.kind = '$kind'";
    if ($method == 'manual') $w[] = "x.method NOT IN ('mercadopago', 'stripe')";
    elseif ($method != '') $w[] = "x.method = '$method'";
    if ($q != '') {
        $qe = mysqli_real_escape_string($mysqli, $q);
        $w[] = "(x.who_name LIKE '%$qe%' OR x.who_email LIKE '%$qe%' OR x.reference LIKE '%$qe%' OR x.order_id = '".(int)$q."')";
    }
    $where_sql = $w ? 'WHERE '.implode(' AND ', $w) : '';

    $union = "(
        SELECT 'credit' AS kind, o.id AS order_id, o.paid_at, o.created_at, o.total AS amount, o.currency, o.credits AS qty, '' AS plan_name,
               o.method, o.reference, o.reseller_id AS who_id, COALESCE(r.name, '') AS who_name, COALESCE(r.email, '') AS who_email, COALESCE(r.phone, '') AS who_phone
        FROM tbl_credit_orders o LEFT JOIN tbl_resellers r ON r.id = o.reseller_id
        WHERE o.status = 'paid'
        UNION ALL
        SELECT 'sub' AS kind, s.id AS order_id, s.paid_at, s.created_at, s.amount, s.currency, s.months AS qty, s.plan_name,
               s.method, s.reference, s.customer_id AS who_id, COALESCE(c.name, '') AS who_name, COALESCE(c.email, '') AS who_email, COALESCE(c.phone, '') AS who_phone
        FROM tbl_subscriptions s LEFT JOIN tbl_customers c ON c.id = s.customer_id
        WHERE s.status = 'paid'
    ) x";

    function pm_method_label($m){
        if ($m == 'mercadopago') return 'Pix (Mercado Pago)';
        if ($m == 'stripe') return 'Cartão (Stripe)';
        return 'Manual';
    }

    /* ---------- CSV ---------- */
    if ($is_csv) {
        while (ob_get_level() > 0) ob_end_clean(); // sem o HTML do topo e sem o filtro de tradução
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pagamentos_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Data', 'Tipo', 'Pedido', 'Nome', 'E-mail', 'Telefone', 'Créditos/Plano', 'Valor', 'Moeda', 'Forma', 'Referência'), ';');
        $res = mysqli_query($mysqli, "SELECT * FROM $union $where_sql ORDER BY x.paid_at DESC");
        while ($res && $r = mysqli_fetch_assoc($res)) {
            fputcsv($out, array(
                $r['paid_at'] ? date('d/m/Y H:i', $r['paid_at']) : '',
                $r['kind'] == 'credit' ? 'Créditos (revenda)' : 'Assinatura (cliente)',
                $r['order_id'], $r['who_name'], $r['who_email'], $r['who_phone'],
                $r['kind'] == 'credit' ? (int)$r['qty'].' créditos' : $r['plan_name'].' ('.(int)$r['qty'].' meses)',
                number_format((float)$r['amount'], 2, ',', ''), $r['currency'], pm_method_label($r['method']), $r['reference'],
            ), ';');
        }
        fclose($out);
        exit;
    }

    /* ---------- Totais do filtro ---------- */
    $tot = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n,
        COALESCE(SUM(IF(x.currency = 'BRL', x.amount, 0)), 0) AS brl,
        COALESCE(SUM(IF(x.currency = 'USD', x.amount, 0)), 0) AS usd,
        COALESCE(SUM(IF(x.kind = 'credit', x.qty, 0)), 0) AS credits,
        COALESCE(SUM(IF(x.kind = 'sub', 1, 0)), 0) AS subs,
        COALESCE(SUM(IF(x.method = 'mercadopago', 1, 0)), 0) AS n_pix,
        COALESCE(SUM(IF(x.method = 'stripe', 1, 0)), 0) AS n_card
        FROM $union $where_sql"));

    $total_rows = (int)$tot['n'];
    $limit = 30;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $last_page = max(1, (int)ceil($total_rows / $limit));
    if ($page > $last_page) $page = $last_page;
    $start = ($page - 1) * $limit;
    $result = mysqli_query($mysqli, "SELECT * FROM $union $where_sql ORDER BY x.paid_at DESC, x.order_id DESC LIMIT $start, $limit");

    $period_labels = array('today' => 'Hoje', '7d' => 'Últimos 7 dias', 'month' => 'Este mês', 'last_month' => 'Mês passado',
                           '30d' => 'Últimos 30 dias', 'year' => 'Este ano', 'all' => 'Tudo', 'custom' => 'Escolher datas');
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <style>
            .co-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .co-table thead th { background: #fafbfc; font-weight: 600; text-align: center; white-space: nowrap; padding: 14px 10px; }
            .co-table tbody td { text-align: center; vertical-align: middle; white-space: nowrap; padding: 12px 10px; }
            .dv-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
            .pm-credit { color: #1d4ed8; background: #eff6ff; border-color: #dbeafe; }
            .pm-sub { color: #7c3aed; background: #f5f3ff; border-color: #ede9fe; }
            .pm-pix { color: #0f766e; background: #f0fdfa; border-color: #ccfbf1; }
            .pm-card { color: #4338ca; background: #eef2ff; border-color: #e0e7ff; }
            .pm-manual { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
        </style>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg"><div class="card co-card p-3 h-100"><div class="text-muted small">Recebido em R$</div><div class="fs-4 fw-bold" style="color:#15803d;"><?php echo money_currency($tot['brl'], 'BRL'); ?></div><small class="text-muted"><?php echo htmlspecialchars($period_labels[$period]); ?></small></div></div>
            <div class="col-6 col-lg"><div class="card co-card p-3 h-100"><div class="text-muted small">Recebido em US$</div><div class="fs-4 fw-bold" style="color:#1d4ed8;"><?php echo money_currency($tot['usd'], 'USD'); ?></div><small class="text-muted"><?php echo htmlspecialchars($period_labels[$period]); ?></small></div></div>
            <div class="col-6 col-lg"><div class="card co-card p-3 h-100"><div class="text-muted small">Créditos vendidos</div><div class="fs-3 fw-bold"><?php echo (int)$tot['credits']; ?></div><small class="text-muted">para revendas</small></div></div>
            <div class="col-6 col-lg"><div class="card co-card p-3 h-100"><div class="text-muted small">Assinaturas pagas</div><div class="fs-3 fw-bold"><?php echo (int)$tot['subs']; ?></div><small class="text-muted">de clientes diretos</small></div></div>
            <div class="col-6 col-lg"><div class="card co-card p-3 h-100"><div class="text-muted small">Pagamentos</div><div class="fs-3 fw-bold"><?php echo $total_rows; ?></div><small class="text-muted"><?php echo (int)$tot['n_pix']; ?> Pix · <?php echo (int)$tot['n_card']; ?> cartão · <?php echo $total_rows - (int)$tot['n_pix'] - (int)$tot['n_card']; ?> manual</small></div></div>
        </div>

        <div class="card co-card">
            <div class="card-body p-4">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted mb-1">Buscar</label>
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Nome, e-mail, nº do pedido ou referência">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Período</label>
                        <select name="period" class="form-select" id="pm_period">
                            <?php foreach ($period_labels as $k => $l) { ?><option value="<?php echo $k; ?>" <?php if ($period == $k) echo 'selected'; ?>><?php echo $l; ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 pm-dates" <?php if ($period != 'custom') echo 'style="display:none"'; ?>>
                        <label class="form-label small text-muted mb-1">De</label>
                        <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-6 col-md-2 pm-dates" <?php if ($period != 'custom') echo 'style="display:none"'; ?>>
                        <label class="form-label small text-muted mb-1">Até</label>
                        <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Tipo</label>
                        <select name="kind" class="form-select">
                            <option value="">Todos</option>
                            <option value="credit" <?php if ($kind == 'credit') echo 'selected'; ?>>Créditos (revendas)</option>
                            <option value="sub" <?php if ($kind == 'sub') echo 'selected'; ?>>Assinaturas (clientes)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Forma</label>
                        <select name="method" class="form-select">
                            <option value="">Todas</option>
                            <option value="mercadopago" <?php if ($method == 'mercadopago') echo 'selected'; ?>>Pix (Mercado Pago)</option>
                            <option value="stripe" <?php if ($method == 'stripe') echo 'selected'; ?>>Cartão (Stripe)</option>
                            <option value="manual" <?php if ($method == 'manual') echo 'selected'; ?>>Manual</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line"></i> Filtrar</button>
                        <a href="manage_payments.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, array('csv' => 1, 'page' => null)))); ?>" class="btn btn-outline-secondary" title="Baixar planilha (CSV)"><i class="ri-file-excel-2-line"></i> Exportar</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table co-table mb-0">
                        <thead><tr><th>Data do pagamento</th><th>Tipo</th><th>Quem comprou</th><th>Comprou</th><th>Valor</th><th>Forma</th><th>Pedido / Referência</th></tr></thead>
                        <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0) { while ($r = mysqli_fetch_assoc($result)) {
                            $m = $r['method'];
                            $m_cls = $m == 'mercadopago' ? 'pm-pix' : ($m == 'stripe' ? 'pm-card' : 'pm-manual');
                            $who_link = $r['kind'] == 'credit' ? 'manage_resellers.php?keyword='.urlencode($r['who_email']) : 'manage_customers.php?keyword='.urlencode($r['who_email']); ?>
                            <tr>
                                <td><?php echo $r['paid_at'] ? date('d/m/Y H:i', $r['paid_at']) : '—'; ?></td>
                                <td>
                                    <?php if ($r['kind'] == 'credit') { ?><span class="dv-badge pm-credit">Revenda</span>
                                    <?php } else { ?><span class="dv-badge pm-sub">Cliente</span><?php } ?>
                                </td>
                                <td class="text-start" translate="no">
                                    <b><a href="<?php echo htmlspecialchars($who_link); ?>" class="text-reset"><?php echo htmlspecialchars($r['who_name'] != '' ? $r['who_name'] : '(excluído)'); ?></a></b><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($r['who_email']); ?><?php echo $r['who_phone'] != '' ? ' · '.htmlspecialchars($r['who_phone']) : ''; ?></small>
                                </td>
                                <td>
                                    <?php if ($r['kind'] == 'credit') { ?><b><?php echo (int)$r['qty']; ?></b> <?php echo (int)$r['qty'] == 1 ? 'crédito' : 'créditos'; ?>
                                    <?php } else { ?><span translate="no"><?php echo htmlspecialchars($r['plan_name']); ?></span><br><small class="text-muted"><?php echo (int)$r['qty']; ?> meses</small><?php } ?>
                                </td>
                                <td class="fw-semibold"><?php echo money_currency($r['amount'], $r['currency']); ?></td>
                                <td><span class="dv-badge <?php echo $m_cls; ?>"><?php echo pm_method_label($m); ?></span></td>
                                <td>
                                    #<?php echo (int)$r['order_id']; ?>
                                    <?php if ($r['reference'] != '') { ?><br><small class="text-muted" translate="no" style="white-space:normal;word-break:break-all;"><?php echo htmlspecialchars($r['reference']); ?></small><?php } ?>
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="7" class="text-muted py-5">Nenhum pagamento neste período.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($last_page > 1) { ?>
                    <ul class="pagination pagination-sm justify-content-end mt-3 mb-0 flex-wrap">
                        <?php for ($p = 1; $p <= $last_page; $p++) { ?>
                            <li class="page-item <?php if ($p == $page) echo 'active'; ?>"><a class="page-link" href="manage_payments.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, array('page' => $p)))); ?>"><?php echo $p; ?></a></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('pm_period').addEventListener('change', function () {
    var custom = this.value === 'custom';
    document.querySelectorAll('.pm-dates').forEach(function (el) { el.style.display = custom ? '' : 'none'; });
    if (!custom) this.form.submit();
});
</script>

<?php include("includes/footer.php");?>
