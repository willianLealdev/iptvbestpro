<?php
require_once(__DIR__."/_init.php");
$customer = cl_require_login($mysqli);
$cid = (int)$customer['id'];
$currency = $customer['currency'] == 'USD' ? 'USD' : 'BRL';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!cl_csrf_ok()) {
        cl_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['subscribe'])) {
        $r = subscription_create($mysqli, $customer, (int)$_POST['plan_id']);
        cl_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } else if (isset($_POST['start_trial'])) {
        $r = trial_create($mysqli, $customer);
        cl_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } else if (isset($_POST['cancel_sub'])) {
        $ok = subscription_cancel($mysqli, (int)$_POST['sub_id'], $cid);
        cl_flash($ok ? 'success' : 'error', $ok ? 'Pedido cancelado.' : 'Não foi possível cancelar este pedido.');
    }
    cl_redirect('dashboard.php');
}

$plans = sub_plans_list($mysqli);
$subs = array();
$res = mysqli_query($mysqli, "SELECT s.*, d.device_id AS device_mac, d.device_model FROM tbl_subscriptions s
                              LEFT JOIN tbl_devices d ON d.id = s.device_pk
                              WHERE s.customer_id = $cid AND s.status <> 'cancelled' ORDER BY s.id DESC");
while ($res && $r = mysqli_fetch_assoc($res)) $subs[] = $r;
$has_pending = false;
foreach ($subs as $s) { if ($s['status'] == 'pending') $has_pending = true; }
require_once(__DIR__."/../includes/payment_helper.php");
$pix_on = pay_mp_enabled($mysqli);
$card_on = pay_stripe_enabled($mysqli);
$instructions = trim(panel_config_get($mysqli, 'payment_instructions', ''));
$contact = trim(panel_config_get($mysqli, 'payment_contact', ''));
$trial_days = trial_days($mysqli);
$trial_offer = trial_enabled($mysqli) && !trial_used_by_customer($mysqli, $cid);

cl_header($customer, 'Minhas assinaturas', 'dashboard'); ?>

<?php if ($trial_offer) { ?>
<div class="cl-card p-4 mb-4" style="border:2px solid #16a34a;background:linear-gradient(135deg,#f0fdf4,#ffffff);">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="cl-title fs-4 mb-1"><i class="ri-gift-line" style="color:#16a34a;"></i> Teste grátis de <?php echo $trial_days; ?> dias</h2>
            <p class="cl-muted mb-0">Experimente o app antes de comprar. Gere o seu código, digite no aparelho e use por <b><?php echo $trial_days; ?> dias</b> sem pagar nada. Depois disso, para continuar usando, é só escolher um plano.</p>
            <p class="small cl-muted mb-0 mt-1">1 teste por cliente, em 1 aparelho que nunca foi ativado.</p>
        </div>
        <form method="post">
            <?php echo cl_csrf_field(); ?>
            <button type="submit" name="start_trial" value="1" class="btn cl-btn-green btn-lg"><i class="ri-play-circle-line"></i> Começar teste grátis</button>
        </form>
    </div>
</div>
<?php } ?>

<div class="cl-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="cl-title">Minhas assinaturas</h1>
        <a href="#planos" class="btn cl-btn-green"><i class="ri-add-line"></i> Nova assinatura</a>
    </div>
    <p class="cl-muted">Cada assinatura ativa <b>1 aparelho</b>. Depois do pagamento você recebe um <b>código de ativação</b>: abra o app no aparelho, digite o código e pronto. O código só pode ser usado <b>uma vez</b>.</p>

    <?php if ($subs) { ?>
        <div class="row g-3">
            <?php foreach ($subs as $s) { $st = subscription_state($s); ?>
                <div class="col-lg-6">
                    <div class="p-3 rounded h-100" style="border:1px solid #eef0f4;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-bold"><?php echo cl_e($s['plan_name']); ?> <span class="cl-muted fw-normal">· pedido #<?php echo (int)$s['id']; ?></span></div>
                                <div class="small cl-muted"><?php echo !empty($s['is_trial']) ? 'Grátis' : money_format_currency($s['amount'], $s['currency']); ?> · <?php echo date('d/m/Y', $s['created_at']); ?></div>
                            </div>
                            <?php echo subscription_state_badge($st); ?>
                        </div>

                        <?php if ($st == 'pending') {
                            $can_pix = $pix_on && $s['currency'] == 'BRL'; ?>
                            <?php if ($can_pix || $card_on) { ?>
                                <div class="d-flex flex-wrap gap-2 mt-3 mb-2">
                                    <?php if ($can_pix) { ?>
                                        <form method="post" action="../pagamento/checkout.php" style="flex:1;">
                                            <?php echo cl_csrf_field(); ?>
                                            <input type="hidden" name="as" value="customer"><input type="hidden" name="type" value="sub">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><input type="hidden" name="method" value="pix">
                                            <button type="submit" class="btn cl-btn-green w-100"><i class="ri-qr-code-line"></i> Pagar com Pix</button>
                                        </form>
                                    <?php } ?>
                                    <?php if ($card_on) { ?>
                                        <form method="post" action="../pagamento/checkout.php" style="flex:1;">
                                            <?php echo cl_csrf_field(); ?>
                                            <input type="hidden" name="as" value="customer"><input type="hidden" name="type" value="sub">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><input type="hidden" name="method" value="card">
                                            <button type="submit" class="btn btn-primary w-100"><i class="ri-bank-card-line"></i> Pagar com cartão</button>
                                        </form>
                                    <?php } ?>
                                </div>
                                <div class="small cl-muted mb-2">Assim que o pagamento for aprovado, o código de ativação aparece aqui automaticamente.</div>
                            <?php } else { ?>
                                <div class="alert alert-warning small mt-3 mb-2">
                                    <b>Aguardando pagamento.</b><br>
                                    <?php echo $instructions != '' ? nl2br(cl_e($instructions)) : 'Fale com o suporte para efetuar o pagamento.'; ?>
                                    <?php if ($contact != '') { ?><br>Envie o comprovante com o número do pedido para: <b><?php echo cl_e($contact); ?></b><?php } ?>
                                </div>
                            <?php } ?>
                            <form method="post" onsubmit="return confirm('Cancelar este pedido?');">
                                <?php echo cl_csrf_field(); ?>
                                <input type="hidden" name="sub_id" value="<?php echo (int)$s['id']; ?>">
                                <button type="submit" name="cancel_sub" class="btn btn-sm btn-outline-danger">Cancelar pedido</button>
                            </form>
                        <?php } else if ($st == 'code') { ?>
                            <div class="mt-3 p-3 rounded text-center" style="background:#f7f9fe;">
                                <div class="cl-muted small">Seu código de ativação</div>
                                <div class="cl-code"><?php echo cl_e(trim(chunk_split($s['code'], 4, ' '))); ?></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-copy="<?php echo cl_e($s['code']); ?>"><i class="ri-file-copy-line"></i> Copiar código</button>
                                <div class="small cl-muted mt-2">Digite este código no app do aparelho que você quer ativar. Vale para <b>1 aparelho</b>, uma única vez.</div>
                                <?php if (!empty($s['is_trial'])) { ?><div class="small fw-bold mt-1" style="color:#16a34a;">Teste grátis de <?php echo (int)$s['days']; ?> dias. Conta a partir da ativação.</div><?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="mt-3 small">
                                <div><span class="cl-muted">Aparelho:</span> <b><?php echo cl_e($s['device_model'] != '' ? $s['device_model'] : $s['device_mac']); ?></b></div>
                                <div><span class="cl-muted">Ativado em:</span> <?php echo date('d/m/Y', $s['activated_at']); ?></div>
                                <div><span class="cl-muted">Vence em:</span> <b class="<?php echo $st == 'expired' ? 'text-danger' : ''; ?>"><?php echo date('d/m/Y', $s['expires_at']); ?></b></div>
                            </div>
                            <?php if (!empty($s['is_trial'])) { ?>
                                <div class="alert <?php echo $st == 'expired' ? 'alert-danger' : 'alert-success'; ?> small mt-3 mb-0">
                                    <?php echo $st == 'expired' ? '<b>Seu teste grátis terminou.</b> Para continuar usando, escolha um plano abaixo e ative o mesmo aparelho com o novo código.' : '<b>Teste grátis em andamento.</b> Gostou? Escolha um plano abaixo antes de vencer para não ficar sem acesso.'; ?>
                                </div>
                            <?php } ?>
                            <?php if ($s['device_pk']) { ?>
                                <a href="playlist.php?sub=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary mt-3"><i class="ri-play-list-line"></i> Playlists deste aparelho</a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="cl-empty"><i class="ri-vip-crown-line"></i>Você ainda não tem assinaturas. Escolha um plano abaixo.</div>
    <?php } ?>
</div>

<div class="cl-card p-4" id="planos">
    <h2 class="cl-title fs-4 mb-1">Planos</h2>
    <p class="cl-muted mb-4">Preços em <?php echo $currency == 'USD' ? 'dólar (US$)' : 'real (R$)'; ?>. Cada plano ativa 1 aparelho.</p>
    <?php if ($plans) { ?>
        <div class="row g-3">
            <?php foreach ($plans as $p) { $price = plan_price($p, $currency); ?>
                <div class="col-md-6 col-lg-4">
                    <div class="cl-plan">
                        <div class="fw-bold fs-5"><?php echo cl_e($p['name']); ?></div>
                        <div class="cl-muted small mb-2"><?php echo (int)$p['months']; ?> <?php echo (int)$p['months'] == 1 ? 'mês' : 'meses'; ?><?php echo $p['description'] != '' ? ' · '.cl_e($p['description']) : ''; ?></div>
                        <div class="cl-price mb-3"><?php echo $price > 0 ? money_format_currency($price, $currency) : '<span class="fs-6 cl-muted">Preço em breve</span>'; ?></div>
                        <form method="post" class="mt-auto">
                            <?php echo cl_csrf_field(); ?>
                            <input type="hidden" name="plan_id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" name="subscribe" class="btn cl-btn-green w-100" <?php if ($price <= 0) { echo 'disabled'; } ?>><i class="ri-shopping-cart-2-line"></i> Assinar</button>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="cl-empty"><i class="ri-price-tag-3-line"></i>Nenhum plano disponível no momento.</div>
    <?php } ?>
</div>

<?php cl_footer(); ?>
