<?php $page_title="Assinaturas";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/customer_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    customers_migrate($mysqli);
    $admin_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    function sb_flash($ok, $msg){
        $_SESSION['sb_msg'] = $msg;
        $_SESSION['msg'] = 'sb_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    $back = "manage_subscriptions.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    if(isset($_POST['mark_paid'])){
        $code = subscription_mark_paid($mysqli, (int)$_POST['sub_id'], $admin_id, trim(strip_tags(isset($_POST['reference']) ? $_POST['reference'] : '')));
        sb_flash((bool)$code, $code ? 'Pagamento confirmado. Código de ativação liberado para o cliente: '.$code : 'Este pedido não está mais pendente.');
        header("Location:".$back);
        exit;
    }
    if(isset($_POST['cancel_sub'])){
        $ok = subscription_cancel($mysqli, (int)$_POST['sub_id']);
        sb_flash($ok, $ok ? 'Pedido cancelado.' : 'Este pedido não está mais pendente.');
        header("Location:".$back);
        exit;
    }

    if(isset($_SESSION['sb_msg'])){
        $client_lang['sb_custom'] = $_SESSION['sb_msg'];
        unset($_SESSION['sb_msg']);
    }

    // Filtros
    $state = (isset($_GET['state']) && in_array($_GET['state'], array('pending','code','active','expired','cancelled'))) ? $_GET['state'] : '';
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $customer_filter = isset($_GET['customer']) ? (int)$_GET['customer'] : 0;
    $now = time();
    $where = array();
    if($state == 'pending') $where[] = "s.status = 'pending'";
    if($state == 'cancelled') $where[] = "s.status = 'cancelled'";
    if($state == 'code') $where[] = "s.status = 'paid' AND s.code_used_at = 0";
    if($state == 'active') $where[] = "s.status = 'paid' AND s.code_used_at > 0 AND s.expires_at >= $now";
    if($state == 'expired') $where[] = "s.status = 'paid' AND s.code_used_at > 0 AND s.expires_at < $now";
    if($customer_filter > 0) $where[] = "s.customer_id = $customer_filter";
    if($keyword != ''){
        $kw = mysqli_real_escape_string($mysqli, $keyword);
        $where[] = "(c.name LIKE '%$kw%' OR c.email LIKE '%$kw%' OR s.code LIKE '%$kw%' OR d.device_id LIKE '%$kw%')";
    }
    $where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $result = mysqli_query($mysqli, "SELECT s.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, d.device_id AS device_mac, d.device_model
                                     FROM tbl_subscriptions s
                                     LEFT JOIN tbl_customers c ON c.id = s.customer_id
                                     LEFT JOIN tbl_devices d ON d.id = s.device_pk
                                     $where_sql ORDER BY (s.status = 'pending') DESC, s.id DESC LIMIT 300");

    $tot = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'paid' AND code_used_at > 0 AND expires_at >= $now) AS active,
        SUM(status = 'paid' AND code_used_at = 0) AS codes,
        COALESCE(SUM(IF(status = 'paid' AND currency = 'BRL' AND paid_at >= ".strtotime(date('Y-m-01'))." , amount, 0)), 0) AS month_brl,
        COALESCE(SUM(IF(status = 'paid' AND currency = 'USD' AND paid_at >= ".strtotime(date('Y-m-01'))." , amount, 0)), 0) AS month_usd
        FROM tbl_subscriptions"));
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_customers.php">Clientes</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <style>
            .sb-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .dv-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
            .pk-year { color: #389e0d; background: #f6ffed; border-color: #d9f7be; }
            .pk-trial { color: #d46b08; background: #fff7e6; border-color: #ffe7ba; }
            .pk-none { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
            .pk-expired { color: #cf1322; background: #fff1f0; border-color: #ffccc7; }
            .sb-code { font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: 2px; color: #3b6cf6; }
        </style>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg"><div class="card sb-card p-3"><div class="text-muted small">Aguardando pagamento</div><div class="fs-3 fw-bold" style="color:#d46b08;"><?php echo (int)$tot['pending']; ?></div></div></div>
            <div class="col-6 col-lg"><div class="card sb-card p-3"><div class="text-muted small">Códigos não usados</div><div class="fs-3 fw-bold" style="color:#3b6cf6;"><?php echo (int)$tot['codes']; ?></div></div></div>
            <div class="col-6 col-lg"><div class="card sb-card p-3"><div class="text-muted small">Assinaturas ativas</div><div class="fs-3 fw-bold" style="color:#16a34a;"><?php echo (int)$tot['active']; ?></div></div></div>
            <div class="col-6 col-lg"><div class="card sb-card p-3"><div class="text-muted small">Recebido no mês</div><div class="fw-bold"><?php echo money_format_currency($tot['month_brl'], 'BRL'); ?><br><?php echo money_format_currency($tot['month_usd'], 'USD'); ?></div></div></div>
        </div>

        <div class="card sb-card">
            <div class="card-body p-4">
                <form method="get" class="d-flex flex-wrap gap-2 mb-3">
                    <input type="text" name="keyword" class="form-control" style="width:260px;max-width:100%;" placeholder="Cliente, e-mail, código ou MAC" value="<?php echo htmlspecialchars($keyword); ?>">
                    <select name="state" class="form-select" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        <option value="pending" <?php if($state=='pending'){echo 'selected';} ?>>Aguardando pagamento</option>
                        <option value="code" <?php if($state=='code'){echo 'selected';} ?>>Código liberado (não usado)</option>
                        <option value="active" <?php if($state=='active'){echo 'selected';} ?>>Ativas</option>
                        <option value="expired" <?php if($state=='expired'){echo 'selected';} ?>>Vencidas</option>
                        <option value="cancelled" <?php if($state=='cancelled'){echo 'selected';} ?>>Canceladas</option>
                    </select>
                    <?php if($customer_filter){ ?><input type="hidden" name="customer" value="<?php echo $customer_filter; ?>"><?php } ?>
                    <button class="btn btn-outline-secondary" type="submit"><i class="ri-search-line"></i></button>
                    <?php if($state != '' || $keyword != '' || $customer_filter){ ?><a href="manage_subscriptions.php" class="btn btn-outline-secondary">Limpar</a><?php } ?>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>#</th><th>Cliente</th><th>Plano</th><th class="text-end">Valor</th><th>Status</th><th>Código</th><th>Aparelho</th><th>Vencimento</th><th class="text-end">Ações</th></tr></thead>
                        <tbody>
                        <?php if(mysqli_num_rows($result) > 0){ while($s = mysqli_fetch_assoc($result)){ $st = subscription_state($s); ?>
                            <tr>
                                <td><?php echo (int)$s['id']; ?><br><small class="text-muted"><?php echo date('d/m/Y', $s['created_at']); ?></small></td>
                                <td><b><?php echo htmlspecialchars((string)$s['customer_name']); ?></b><br><small class="text-muted"><?php echo htmlspecialchars((string)$s['customer_email']); ?><?php echo $s['customer_phone'] != '' ? ' · '.htmlspecialchars($s['customer_phone']) : ''; ?></small></td>
                                <td><?php echo htmlspecialchars($s['plan_name']); ?><br><small class="text-muted"><?php echo (int)$s['months']; ?> meses</small></td>
                                <td class="text-end text-nowrap"><?php echo money_format_currency($s['amount'], $s['currency']); ?></td>
                                <td><?php echo subscription_state_badge($st); ?></td>
                                <td><?php echo $s['code'] ? '<span class="sb-code">'.htmlspecialchars($s['code']).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                                <td><?php echo $s['device_pk'] ? htmlspecialchars($s['device_model'] != '' ? $s['device_model'] : $s['device_mac']).'<br><small class="text-muted">'.htmlspecialchars((string)$s['device_mac']).'</small>' : '<span class="text-muted">—</span>'; ?></td>
                                <td class="text-nowrap"><?php echo (int)$s['expires_at'] > 0 ? date('d/m/Y', $s['expires_at']) : '<span class="text-muted">—</span>'; ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if($s['status'] == 'pending'){ ?>
                                        <button type="button" class="btn btn-sm btn-success btn_pay" data-id="<?php echo (int)$s['id']; ?>" data-info="<?php echo htmlspecialchars('#'.$s['id'].' · '.$s['plan_name'].' · '.money_format_currency($s['amount'], $s['currency']).' · '.$s['customer_name']); ?>"><i class="ri-check-line"></i> Confirmar pagamento</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Cancelar este pedido?');">
                                            <input type="hidden" name="sub_id" value="<?php echo (int)$s['id']; ?>">
                                            <button type="submit" name="cancel_sub" class="btn btn-sm btn-outline-danger"><i class="ri-close-line"></i></button>
                                        </form>
                                    <?php } else if($s['device_pk']){ ?>
                                        <a href="device_playlist.php?device=<?php echo (int)$s['device_pk']; ?>" class="btn btn-sm btn-outline-primary"><i class="ri-play-list-line"></i></a>
                                    <?php } else { ?><span class="text-muted">—</span><?php } ?>
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="9" class="text-center text-muted py-5">Nenhuma assinatura.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="sub_id" id="pay_sub_id">
                <p id="pay_info" class="fw-semibold"></p>
                <label class="form-label">Referência (opcional)</label>
                <input type="text" name="reference" class="form-control" maxlength="191" placeholder="Ex.: ID da transação Pix">
                <p class="small text-muted mt-2 mb-0">Ao confirmar, o sistema gera o <b>código de ativação</b> e ele aparece na área do cliente.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="mark_paid" class="btn btn-success">Confirmar e gerar código</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('.btn_pay');
    if (!b) return;
    document.getElementById('pay_sub_id').value = b.getAttribute('data-id');
    document.getElementById('pay_info').textContent = b.getAttribute('data-info');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal')).show();
});
</script>

<?php include("includes/footer.php");?>
