<?php
require_once(__DIR__."/_init.php");
$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];

$price = credit_price($mysqli);
$min = max(1, (int)panel_config_get($mysqli, 'credit_min', '1'));
$instructions = trim(panel_config_get($mysqli, 'payment_instructions', ''));
$contact = trim(panel_config_get($mysqli, 'payment_contact', ''));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['buy_submit'])) {
        $qty = (int)$_POST['credits'];
        $pending = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_credit_orders WHERE reseller_id = $rid AND status = 'pending'"));
        if ((int)$pending['num'] >= 3) {
            rv_flash('error', 'Você já tem 3 pedidos aguardando pagamento. Pague ou cancele um deles antes de criar outro.');
        } else if ($qty < $min) {
            rv_flash('error', 'A compra mínima é de '.$min.' crédito(s).');
        } else {
            $id = credit_orders_create($mysqli, $rid, $qty);
            rv_flash($id ? 'success' : 'error', $id ? 'Pedido #'.$id.' criado. Siga as instruções de pagamento abaixo.' : 'Não foi possível criar o pedido.');
        }
    } else if (isset($_POST['cancel_order'])) {
        $ok = credit_orders_cancel($mysqli, (int)$_POST['order_id'], $rid);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Pedido cancelado.' : 'Não foi possível cancelar este pedido.');
    }
    rv_redirect('credits.php');
}

$orders = mysqli_query($mysqli, "SELECT * FROM tbl_credit_orders WHERE reseller_id = $rid ORDER BY id DESC LIMIT 20");
$history = mysqli_query($mysqli, "SELECT * FROM tbl_reseller_credits WHERE reseller_id = $rid ORDER BY id DESC LIMIT 30");

rv_header($mysqli, $reseller, 'Créditos', 'credits'); ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="rv-card p-4 mb-4">
            <div class="rv-muted">Saldo atual</div>
            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span style="font-size:42px;font-weight:700;color:#15803d;"><?php echo (int)$reseller['credits']; ?></span>
                <span class="rv-muted">créditos</span>
            </div>
            <div class="p-3 rounded small" style="background:#f7f9fe;">
                <b>1 crédito = 1 ano</b> de ativação para 1 aparelho.<br>
                2 anos = 2 créditos, 3 anos = 3 créditos, e assim por diante (até <?php echo MAX_ACTIVATION_YEARS; ?> anos).
            </div>
        </div>

        <div class="rv-card p-4">
            <h2 class="rv-title fs-5 mb-3">Comprar créditos</h2>
            <?php if ($price <= 0) { ?>
                <div class="alert alert-info mb-0">A venda de créditos ainda não foi configurada. <?php echo $contact != '' ? 'Contato: '.rv_e($contact) : 'Fale com o administrador.'; ?></div>
            <?php } else { ?>
                <form method="post">
                    <?php echo rv_csrf_field(); ?>
                    <label class="form-label">Quantidade de créditos</label>
                    <input type="number" name="credits" id="buy_qty" class="form-control form-control-lg" min="<?php echo $min; ?>" step="1" value="<?php echo max($min, 10); ?>" required>
                    <div class="d-flex justify-content-between mt-3">
                        <span class="rv-muted">Preço por crédito</span><b><?php echo money_br($price); ?></b>
                    </div>
                    <div class="d-flex justify-content-between mt-1 fs-5">
                        <span>Total</span><b id="buy_total"></b>
                    </div>
                    <button type="submit" name="buy_submit" class="btn rv-btn-green btn-lg w-100 mt-3"><i class="ri-shopping-cart-2-line"></i> Gerar pedido</button>
                    <?php if ($min > 1) { ?><p class="rv-muted small mt-2 mb-0">Compra mínima: <?php echo $min; ?> créditos.</p><?php } ?>
                </form>
            <?php } ?>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="rv-card p-4 mb-4">
            <h2 class="rv-title fs-5 mb-3">Meus pedidos</h2>
            <?php if (mysqli_num_rows($orders) > 0) { ?>
                <div class="table-responsive">
                    <table class="table rv-table mb-0">
                        <thead><tr><th>#</th><th>Data</th><th>Créditos</th><th>Total</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php $has_pending = false; while ($o = mysqli_fetch_assoc($orders)) { if ($o['status'] == 'pending') $has_pending = true; ?>
                            <tr>
                                <td><?php echo (int)$o['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', $o['created_at']); ?></td>
                                <td><?php echo (int)$o['credits']; ?></td>
                                <td><?php echo money_br($o['total']); ?></td>
                                <td><?php echo credit_order_status_badge($o['status']); ?></td>
                                <td>
                                    <?php if ($o['status'] == 'pending') { ?>
                                        <form method="post" onsubmit="return confirm('Cancelar o pedido #<?php echo (int)$o['id']; ?>?');">
                                            <?php echo rv_csrf_field(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                            <button type="submit" name="cancel_order" class="btn btn-sm btn-outline-danger">Cancelar</button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($has_pending) { ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <b>Como pagar:</b><br>
                        <?php echo $instructions != '' ? nl2br(rv_e($instructions)) : 'Fale com o administrador para efetuar o pagamento.'; ?>
                        <?php if ($contact != '') { ?><br><br>Envie o comprovante informando o número do pedido para: <b><?php echo rv_e($contact); ?></b><?php } ?>
                        <br><small>Os créditos entram assim que o pagamento for confirmado.</small>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="rv-empty"><i class="ri-shopping-bag-line"></i>Nenhum pedido ainda.</div>
            <?php } ?>
        </div>

        <div class="rv-card p-4">
            <h2 class="rv-title fs-5 mb-3">Extrato de créditos</h2>
            <?php if (mysqli_num_rows($history) > 0) { ?>
                <div class="table-responsive">
                    <table class="table rv-table mb-0">
                        <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th><th>Saldo</th></tr></thead>
                        <tbody>
                        <?php while ($h = mysqli_fetch_assoc($history)) { ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', $h['created_at']); ?></td>
                                <td><?php echo rv_e(credit_type_label($h['type'])); ?></td>
                                <td class="text-start"><?php echo rv_e($h['note']); ?></td>
                                <td class="fw-bold" style="color:<?php echo $h['amount'] > 0 ? '#16a34a' : '#dc2626'; ?>"><?php echo $h['amount'] > 0 ? '+'.$h['amount'] : $h['amount']; ?></td>
                                <td><?php echo (int)$h['balance_after']; ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="rv-empty"><i class="ri-file-list-3-line"></i>Sem movimentações.</div>
            <?php } ?>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
(function () {
    var qty = document.getElementById('buy_qty');
    if (!qty) return;
    var price = <?php echo json_encode($price); ?>;
    function upd() {
        var n = Math.max(0, parseInt(qty.value || '0', 10));
        document.getElementById('buy_total').textContent = (n * price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    qty.addEventListener('input', upd);
    upd();
})();
</script>
<?php rv_footer(ob_get_clean()); ?>
