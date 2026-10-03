<?php $page_title="Pedidos de Créditos";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    $admin_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    function co_flash($ok, $msg){
        $_SESSION['co_msg'] = $msg;
        $_SESSION['msg'] = 'co_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    $back_url = "manage_credit_orders.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    if(isset($_POST['mark_paid'])){
        $ok = credit_orders_mark_paid($mysqli, (int)$_POST['order_id'], $admin_id, trim(strip_tags(isset($_POST['reference']) ? $_POST['reference'] : '')));
        co_flash($ok, $ok ? 'Pagamento confirmado. Créditos adicionados ao revendedor.' : 'Este pedido não está mais pendente.');
        header("Location:".$back_url);
        exit;
    }
    if(isset($_POST['cancel_order'])){
        $ok = credit_orders_cancel($mysqli, (int)$_POST['order_id']);
        co_flash($ok, $ok ? 'Pedido cancelado.' : 'Este pedido não está mais pendente.');
        header("Location:".$back_url);
        exit;
    }
    if(isset($_POST['save_settings'])){
        $price = str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $_POST['credit_price']));
        panel_config_set($mysqli, 'credit_price', number_format((float)$price, 2, '.', ''));
        panel_config_set($mysqli, 'credit_min', max(1, (int)$_POST['credit_min']));
        panel_config_set($mysqli, 'payment_instructions', mb_substr(trim(strip_tags($_POST['payment_instructions'])), 0, 2000));
        panel_config_set($mysqli, 'payment_contact', mb_substr(trim(strip_tags($_POST['payment_contact'])), 0, 191));
        panel_config_set($mysqli, 'reseller_signup', isset($_POST['reseller_signup']) ? '1' : '0');
        co_flash(true, 'Configurações da revenda salvas.');
        header("Location:manage_credit_orders.php");
        exit;
    }

    if(isset($_SESSION['co_msg'])){
        $client_lang['co_custom'] = $_SESSION['co_msg'];
        unset($_SESSION['co_msg']);
    }

    $status_filter = (isset($_GET['status']) && in_array($_GET['status'], array('pending','paid','cancelled'))) ? $_GET['status'] : '';
    $where_sql = $status_filter != '' ? "WHERE o.status = '$status_filter'" : '';

    $total_rows = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_credit_orders o $where_sql"));
    $total_rows = (int)$total_rows['num'];
    $limit = 20;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $last_page = max(1, (int)ceil($total_rows / $limit));
    if($page > $last_page){ $page = $last_page; }
    $start = ($page - 1) * $limit;

    $result = mysqli_query($mysqli, "SELECT o.*, r.name AS reseller_name, r.email AS reseller_email, r.phone AS reseller_phone
                                     FROM tbl_credit_orders o LEFT JOIN tbl_resellers r ON r.id = o.reseller_id
                                     $where_sql ORDER BY (o.status = 'pending') DESC, o.id DESC LIMIT $start, $limit");

    $pending_count = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_credit_orders WHERE status = 'pending'"));
    $paid_month = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(total),0) AS total, COALESCE(SUM(credits),0) AS credits FROM tbl_credit_orders WHERE status = 'paid' AND paid_at >= ".strtotime(date('Y-m-01 00:00:00'))));

    $cfg_price = panel_config_get($mysqli, 'credit_price', '0');
    $cfg_min = panel_config_get($mysqli, 'credit_min', '1');
    $cfg_instructions = panel_config_get($mysqli, 'payment_instructions', '');
    $cfg_contact = panel_config_get($mysqli, 'payment_contact', '');
    $cfg_signup = panel_config_get($mysqli, 'reseller_signup', '1');
    $portal_url = rtrim(getBaseUrl(), '/').'/revenda/';
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_resellers.php">Resellers</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <style>
            .co-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .co-table thead th { background: #fafbfc; font-weight: 600; text-align: center; white-space: nowrap; padding: 14px 10px; }
            .co-table tbody td { text-align: center; vertical-align: middle; white-space: nowrap; padding: 12px 10px; }
            .dv-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
            .pk-year { color: #389e0d; background: #f6ffed; border-color: #d9f7be; }
            .pk-trial { color: #d46b08; background: #fff7e6; border-color: #ffe7ba; }
            .pk-none { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
        </style>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="row g-3 mb-4">
                    <div class="col-sm-4"><div class="card co-card p-3"><div class="text-muted small">Pedidos pendentes</div><div class="fs-3 fw-bold" style="color:#d46b08;"><?php echo (int)$pending_count['num']; ?></div></div></div>
                    <div class="col-sm-4"><div class="card co-card p-3"><div class="text-muted small">Recebido este mês</div><div class="fs-3 fw-bold" style="color:#15803d;"><?php echo money_br($paid_month['total']); ?></div></div></div>
                    <div class="col-sm-4"><div class="card co-card p-3"><div class="text-muted small">Créditos vendidos no mês</div><div class="fs-3 fw-bold"><?php echo (int)$paid_month['credits']; ?></div></div></div>
                </div>

                <div class="card co-card">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0">Pedidos</h5>
                            <form method="get">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <option value="pending" <?php if($status_filter=='pending'){echo 'selected';} ?>>Aguardando pagamento</option>
                                    <option value="paid" <?php if($status_filter=='paid'){echo 'selected';} ?>>Pagos</option>
                                    <option value="cancelled" <?php if($status_filter=='cancelled'){echo 'selected';} ?>>Cancelados</option>
                                </select>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table co-table mb-0">
                                <thead><tr><th>#</th><th>Data</th><th>Revendedor</th><th>Créditos</th><th>Total</th><th>Status</th><th>Ações</th></tr></thead>
                                <tbody>
                                <?php if(mysqli_num_rows($result) > 0){ while($o = mysqli_fetch_assoc($result)){ ?>
                                    <tr>
                                        <td><?php echo (int)$o['id']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', $o['created_at']); ?></td>
                                        <td class="text-start">
                                            <b><?php echo htmlspecialchars((string)$o['reseller_name']); ?></b><br>
                                            <small class="text-muted"><?php echo htmlspecialchars((string)$o['reseller_email']); ?><?php echo $o['reseller_phone'] != '' ? ' · '.htmlspecialchars($o['reseller_phone']) : ''; ?></small>
                                        </td>
                                        <td><?php echo (int)$o['credits']; ?></td>
                                        <td><?php echo money_br($o['total']); ?></td>
                                        <td>
                                            <?php echo credit_order_status_badge($o['status']); ?>
                                            <?php if($o['status'] == 'paid' && $o['paid_at']){ ?><br><small class="text-muted"><?php echo date('d/m/Y H:i', $o['paid_at']); ?><?php echo $o['reference'] != '' ? ' · '.htmlspecialchars($o['reference']) : ''; ?></small><?php } ?>
                                        </td>
                                        <td>
                                            <?php if($o['status'] == 'pending'){ ?>
                                                <button type="button" class="btn btn-sm btn-success btn_pay" data-id="<?php echo (int)$o['id']; ?>" data-info="<?php echo htmlspecialchars('#'.$o['id'].' · '.$o['credits'].' créditos · '.money_br($o['total']).' · '.$o['reseller_name']); ?>"><i class="ri-check-line"></i> Confirmar pagamento</button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Cancelar este pedido?');">
                                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                                    <button type="submit" name="cancel_order" class="btn btn-sm btn-outline-danger"><i class="ri-close-line"></i></button>
                                                </form>
                                            <?php } else { ?><span class="text-muted">—</span><?php } ?>
                                        </td>
                                    </tr>
                                <?php } } else { ?>
                                    <tr><td colspan="7" class="text-muted py-5">Nenhum pedido.</td></tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if($last_page > 1){ ?>
                            <ul class="pagination pagination-sm justify-content-end mt-3 mb-0">
                                <?php for($p = 1; $p <= $last_page; $p++){ ?>
                                    <li class="page-item <?php if($p == $page){ echo 'active'; } ?>"><a class="page-link" href="manage_credit_orders.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, array('page' => $p)))); ?>"><?php echo $p; ?></a></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card co-card">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Configurações da Revenda</h5>
                        <div class="mb-3 p-3 rounded" style="background:#f7f9fe;">
                            <div class="small text-muted">Portal do revendedor</div>
                            <a href="<?php echo htmlspecialchars($portal_url); ?>" target="_blank"><?php echo htmlspecialchars($portal_url); ?></a>
                            <i class="ri-file-copy-line" style="cursor:pointer;color:#3b6cf6;" onclick="navigator.clipboard && navigator.clipboard.writeText('<?php echo htmlspecialchars($portal_url); ?>')" title="Copiar"></i>
                        </div>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Preço por crédito (R$)</label>
                                <input type="text" name="credit_price" class="form-control" value="<?php echo htmlspecialchars(number_format((float)$cfg_price, 2, ',', '')); ?>" placeholder="Ex.: 15,00">
                                <small class="text-muted">1 crédito = 1 ano de ativação. Com preço 0 a compra fica desativada.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Compra mínima (créditos)</label>
                                <input type="number" name="credit_min" min="1" class="form-control" value="<?php echo (int)$cfg_min; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Instruções de pagamento</label>
                                <textarea name="payment_instructions" rows="4" class="form-control" placeholder="Ex.: Pix (chave e-mail): pagamentos@seudominio.com&#10;Favorecido: Fulano de Tal"><?php echo htmlspecialchars($cfg_instructions); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contato para comprovante</label>
                                <input type="text" name="payment_contact" class="form-control" value="<?php echo htmlspecialchars($cfg_contact); ?>" placeholder="Ex.: WhatsApp +55 11 99999-9999">
                            </div>
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="reseller_signup" id="reseller_signup" <?php if($cfg_signup == '1'){ echo 'checked'; } ?>>
                                <label class="form-check-label" for="reseller_signup">Permitir cadastro de novos revendedores pelo site</label>
                            </div>
                            <button type="submit" name="save_settings" class="btn btn-primary w-100">Salvar</button>
                        </form>
                        <p class="small text-muted mt-3 mb-0">Pagamento automático (Stripe e Mercado Pago) será adicionado na próxima etapa.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal: confirmar pagamento -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="order_id" id="pay_order_id">
                <p id="pay_info" class="fw-semibold"></p>
                <label class="form-label">Referência (opcional)</label>
                <input type="text" name="reference" class="form-control" maxlength="191" placeholder="Ex.: ID da transação Pix">
                <p class="small text-muted mt-2 mb-0">Os créditos são adicionados ao revendedor na hora.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="mark_paid" class="btn btn-success">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('.btn_pay');
    if (!b) return;
    document.getElementById('pay_order_id').value = b.getAttribute('data-id');
    document.getElementById('pay_info').textContent = b.getAttribute('data-info');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal')).show();
});
</script>

<?php include("includes/footer.php");?>
