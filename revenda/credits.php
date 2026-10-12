<?php
require_once(__DIR__."/_init.php");
$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];

$currency = reseller_currency($reseller);
$price = credit_price($mysqli, $currency);
$min = max(1, (int)panel_config_get($mysqli, 'credit_min', '1'));
require_once(__DIR__."/../includes/payment_helper.php");
$pix_on = pay_mp_enabled($mysqli); // Pix só em pedidos em reais (conferido em cada pedido)
$card_on = pay_stripe_enabled($mysqli);
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
    } else if (isset($_POST['buy_package']) && rv_is_server()) {
        // servidor parceiro: compra um pacote criado pelo admin
        $pending = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_credit_orders WHERE reseller_id = $rid AND status = 'pending'"));
        if ((int)$pending['num'] >= 3) {
            rv_flash('error', 'Você já tem 3 pedidos aguardando pagamento. Pague ou cancele um deles antes de criar outro.');
        } else {
            $id = server_order_create($mysqli, $rid, (int)$_POST['package_id']);
            rv_flash($id ? 'success' : 'error', $id ? 'Pedido #'.$id.' criado. Siga as instruções de pagamento abaixo.' : 'Este pacote não está disponível. Escolha outro.');
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
                <?php if (rv_is_server()) { ?>
                    Cada vez que um cliente digita o seu código no app, 1 crédito é usado e o aparelho ativa na hora. Quando vence, o cliente digita o código de novo.
                <?php } else { ?>
                    2 anos = 2 créditos, 3 anos = 3 créditos, e assim por diante (até <?php echo MAX_ACTIVATION_YEARS; ?> anos).
                <?php } ?>
            </div>
        </div>
<?php if (rv_is_server()) { $pkgs = server_packages($mysqli); ?>
        <div class="rv-card p-4">
            <h2 class="rv-title fs-5 mb-1">Comprar pacotes</h2>
            <p class="rv-muted small mb-3">Preços em <?php echo $currency == 'USD' ? 'dólar (US$) · pagamento com cartão' : 'real (R$) · Pix ou cartão'; ?>.</p>
            <?php if (!$pkgs) { ?>
                <div class="alert alert-info mb-0">Nenhum pacote disponível no momento. <?php echo $contact != '' ? 'Contato: '.rv_e($contact) : 'Fale com o administrador.'; ?></div>
            <?php } else { ?>
                <div class="d-grid gap-3">
                    <?php foreach ($pkgs as $p) { $pp = server_package_price($p, $currency); if ($pp <= 0) continue; ?>
                        <form method="post" class="d-flex align-items-center gap-3 p-3 rounded border">
                            <?php echo rv_csrf_field(); ?>
                            <input type="hidden" name="package_id" value="<?php echo (int)$p['id']; ?>">
                            <div class="flex-grow-1">
                                <b class="d-block"><?php echo rv_e($p['name']); ?></b>
                                <span class="rv-muted small"><?php echo (int)$p['credits']; ?> créditos · <?php echo money_currency($pp / max(1, (int)$p['credits']), $currency); ?> por aparelho</span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold fs-5"><?php echo money_currency($pp, $currency); ?></div>
                                <button type="submit" name="buy_package" value="1" class="btn btn-sm rv-btn-green mt-1"><i class="ri-shopping-cart-2-line"></i> Comprar</button>
                            </div>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
<?php } else { ?>

        <div class="rv-card p-4">
            <h2 class="rv-title fs-5 mb-1">Comprar créditos</h2>
            <p class="rv-muted small mb-3">Preços em <?php echo $currency == 'USD' ? 'dólar (US$) · pagamento com cartão' : 'real (R$) · Pix ou cartão'; ?>.</p>
            <?php if ($price <= 0) { ?>
                <div class="alert alert-info mb-0">A venda de créditos ainda não foi configurada. <?php echo $contact != '' ? 'Contato: '.rv_e($contact) : 'Fale com o administrador.'; ?></div>
            <?php } else { ?>
                <style>
                    .buy-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
                    .buy-chip { border: 1px solid #dbe3f4; background: #fff; color: #1f2a44; border-radius: 999px; padding: 6px 14px; font-weight: 600; font-size: 14px; cursor: pointer; transition: .15s; }
                    .buy-chip:hover, .buy-chip.on { border-color: #16a34a; background: #f0fdf4; color: #15803d; }
                    .buy-stepper { display: flex; align-items: stretch; border: 1px solid #dbe3f4; border-radius: 12px; overflow: hidden; transition: .15s; }
                    .buy-stepper:focus-within { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
                    .buy-stepper.bad { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245,158,11,.18); }
                    .buy-stepper button { width: 54px; border: 0; background: #f7f9fe; font-size: 22px; color: #3b4a6b; cursor: pointer; }
                    .buy-stepper button:hover { background: #eef2fb; }
                    .buy-stepper input { border: 0; text-align: center; font-size: 22px; font-weight: 700; flex: 1; min-width: 0; padding: 10px; outline: none; -moz-appearance: textfield; }
                    .buy-stepper input::-webkit-outer-spin-button, .buy-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
                    .buy-warn { display: none; align-items: flex-start; gap: 12px; margin-top: 12px; padding: 14px 16px; border-radius: 12px; background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1px solid #fcd34d; color: #78350f; animation: buyIn .25s ease; }
                    .buy-warn.show { display: flex; }
                    .buy-warn i { font-size: 24px; color: #d97706; line-height: 1; }
                    .buy-warn b { display: block; color: #92400e; }
                    .buy-warn button { margin-top: 8px; border: 0; border-radius: 8px; background: #f59e0b; color: #fff; font-weight: 600; font-size: 13px; padding: 6px 12px; cursor: pointer; }
                    .buy-warn button:hover { background: #d97706; }
                    @keyframes buyIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
                    #buy_btn:disabled { opacity: .55; cursor: not-allowed; }
                </style>
                <form method="post" id="buy_form" novalidate>
                    <?php echo rv_csrf_field(); ?>
                    <label class="form-label">Quantidade de créditos</label>
                    <div class="buy-chips">
                        <?php foreach (array_unique(array($min, max($min, 10), max($min, 20), max($min, 50), max($min, 100))) as $chip) { ?>
                            <button type="button" class="buy-chip" data-qty="<?php echo (int)$chip; ?>"><?php echo (int)$chip; ?></button>
                        <?php } ?>
                    </div>
                    <div class="buy-stepper" id="buy_box">
                        <button type="button" id="buy_minus" aria-label="Diminuir">−</button>
                        <input type="number" name="credits" id="buy_qty" inputmode="numeric" step="1" value="<?php echo max($min, 10); ?>" data-min="<?php echo $min; ?>">
                        <button type="button" id="buy_plus" aria-label="Aumentar">+</button>
                    </div>
                    <div class="buy-warn" id="buy_warn" role="alert">
                        <i class="ri-error-warning-fill"></i>
                        <div>
                            <b>Compra mínima de <?php echo $min; ?> créditos</b>
                            Cada crédito ativa 1 aparelho por 1 ano. Escolha <?php echo $min; ?> ou mais para gerar o pedido.
                            <div><button type="button" id="buy_fix">Usar <?php echo $min; ?> créditos</button></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <span class="rv-muted">Preço por crédito</span><b><?php echo money_currency($price, $currency); ?></b>
                    </div>
                    <div class="d-flex justify-content-between mt-1 fs-5">
                        <span>Total</span><b id="buy_total"></b>
                    </div>
                    <input type="hidden" name="buy_submit" value="1">
                    <button type="submit" id="buy_btn" class="btn rv-btn-green btn-lg w-100 mt-3"><i class="ri-shopping-cart-2-line"></i> Gerar pedido</button>
                    <?php if ($min > 1) { ?><p class="rv-muted small mt-2 mb-0">Compra mínima: <?php echo $min; ?> créditos.</p><?php } ?>
                </form>
            <?php } ?>
        </div>
<?php } ?>
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
                                <td><?php echo money_currency($o['total'], $o['currency']); ?></td>
                                <td><?php echo credit_order_status_badge($o['status']); ?></td>
                                <td>
                                    <?php if ($o['status'] == 'pending') { ?>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center mb-1">
                                            <?php if ($pix_on && $o['currency'] == 'BRL') { ?>
                                                <form method="post" action="../pagamento/checkout.php">
                                                    <?php echo rv_csrf_field(); ?>
                                                    <input type="hidden" name="as" value="reseller"><input type="hidden" name="type" value="credit">
                                                    <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>"><input type="hidden" name="method" value="pix">
                                                    <button type="submit" class="btn btn-sm rv-btn-green"><i class="ri-qr-code-line"></i> Pix</button>
                                                </form>
                                            <?php } ?>
                                            <?php if ($card_on) { ?>
                                                <form method="post" action="../pagamento/checkout.php">
                                                    <?php echo rv_csrf_field(); ?>
                                                    <input type="hidden" name="as" value="reseller"><input type="hidden" name="type" value="credit">
                                                    <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>"><input type="hidden" name="method" value="card">
                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="ri-bank-card-line"></i> Cartão</button>
                                                </form>
                                            <?php } ?>
                                        </div>
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
    var cur = <?php echo json_encode($currency); ?>;
    var min = parseInt(qty.getAttribute('data-min'), 10) || 1;
    var warn = document.getElementById('buy_warn'), box = document.getElementById('buy_box'), btn = document.getElementById('buy_btn');
    var chips = document.querySelectorAll('.buy-chip');
    function setQty(n) { qty.value = Math.max(0, n); upd(); }
    document.getElementById('buy_minus').addEventListener('click', function () { setQty((parseInt(qty.value || '0', 10) || 0) - 1); });
    document.getElementById('buy_plus').addEventListener('click', function () { setQty((parseInt(qty.value || '0', 10) || 0) + 1); });
    document.getElementById('buy_fix').addEventListener('click', function () { setQty(min); qty.focus(); });
    chips.forEach(function (c) { c.addEventListener('click', function () { setQty(parseInt(c.getAttribute('data-qty'), 10)); }); });
    document.getElementById('buy_form').addEventListener('submit', function (e) {
        if ((parseInt(qty.value || '0', 10) || 0) < min) { e.preventDefault(); upd(); warn.classList.remove('show'); void warn.offsetWidth; warn.classList.add('show'); }
    });
    function upd() {
        var n = Math.max(0, parseInt(qty.value || '0', 10) || 0);
        var bad = n < min;
        warn.classList.toggle('show', bad);
        box.classList.toggle('bad', bad);
        btn.disabled = bad;
        chips.forEach(function (c) { c.classList.toggle('on', parseInt(c.getAttribute('data-qty'), 10) === n); });
        document.getElementById('buy_total').textContent = cur === 'USD'
            ? 'US$ ' + (n * price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : (n * price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    qty.addEventListener('input', upd);
    upd();
})();
</script>
<?php rv_footer(ob_get_clean()); ?>
