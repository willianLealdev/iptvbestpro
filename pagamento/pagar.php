<?php
/**
 * Tela do pagamento: QR Code Pix (Mercado Pago) ou retorno do cartão (Stripe).
 * A confirmação chega em tempo real (status.php, Server-Sent Events) assim que o gateway avisar o servidor.
 */
require_once(__DIR__."/../includes/db_helper.php");
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/payment_helper.php");
payments_migrate($mysqli);

$pid = isset($_GET['p']) ? (int)$_GET['p'] : 0;
$p = $pid ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_payments WHERE id = $pid")) : null;

// Só o dono do pedido vê esta tela
$owner_id = 0;
if ($p) $owner_id = $p['owner_type'] == 'reseller' ? (isset($_SESSION['rv_id']) ? (int)$_SESSION['rv_id'] : 0) : (isset($_SESSION['cl_id']) ? (int)$_SESSION['cl_id'] : 0);
if (!$p || $owner_id !== (int)$p['owner_id']) {
    header('Location: ../cliente/');
    exit;
}
$back = $p['owner_type'] == 'reseller' ? '../revenda/credits.php' : '../cliente/dashboard.php';

// Retorno do Stripe: confirma na hora consultando a sessão (o webhook também confirma)
if ($p['provider'] == 'stripe' && isset($_GET['s']) && $_GET['s'] == 'ok' && $p['provider_id'] != '') pay_stripe_sync($mysqli, $p['provider_id']);
$status = pay_status_for_owner($mysqli, $pid, $p['owner_type'], $p['owner_id']);
$cancelled = $p['provider'] == 'stripe' && isset($_GET['s']) && $_GET['s'] == 'cancel' && $status != 'paid';
$amount_txt = money_format_currency($p['amount'], $p['currency']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Pagamento | <?php echo htmlspecialchars(brand_name()); ?></title>
    <link href="<?php echo htmlspecialchars(brand_logo_url('../')); ?>" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/remixicon/remixicon.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; color: #1f2a44; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .pay-card { background: #fff; border-radius: 16px; box-shadow: 0 6px 24px rgba(15,23,42,.08); width: 100%; max-width: 460px; padding: 32px; }
        .pay-amount { font-size: 34px; font-weight: 800; }
        .pay-qr { width: 240px; height: 240px; margin: 0 auto; display: block; border-radius: 12px; border: 1px solid #eef0f4; }
        .pay-code { font-family: monospace; font-size: 12px; word-break: break-all; background: #f7f9fe; border-radius: 8px; padding: 10px; max-height: 90px; overflow: auto; }
        .pay-state { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; border-radius: 10px; font-weight: 600; }
        .pay-wait { background: #fff7e6; color: #b45309; }
        .pay-ok { background: #f0fdf4; color: #15803d; }
        .pay-bad { background: #fff1f0; color: #cf1322; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: currentColor; animation: pulse 1.2s infinite; }
        @keyframes pulse { 0%,100% { opacity: .3; } 50% { opacity: 1; } }
    </style>
</head>
<body>
<?php echo i18n_switcher_fixed(); ?>
<div class="pay-card">
    <?php brand_auth_header('../', $p['provider'] == 'mercadopago' ? 'Pagamento via Pix' : 'Pagamento com cartão'); ?>
    <div class="text-center mb-3"><div class="text-muted small">Valor</div><div class="pay-amount"><?php echo $amount_txt; ?></div></div>

    <div id="state_paid" class="pay-state pay-ok mb-3" style="<?php echo $status == 'paid' ? '' : 'display:none'; ?>">
        <i class="ri-checkbox-circle-fill fs-4"></i> Pagamento confirmado!
    </div>
    <div id="state_bad" class="pay-state pay-bad mb-3" style="<?php echo ($status == 'failed' || $status == 'expired' || $cancelled) ? '' : 'display:none'; ?>">
        <i class="ri-close-circle-fill fs-4"></i> <span id="bad_text"><?php echo $cancelled ? 'Pagamento cancelado.' : ($status == 'expired' ? 'Este Pix expirou. Gere um novo.' : 'Pagamento não aprovado.'); ?></span>
    </div>
    <div id="state_wait" class="pay-state pay-wait mb-3" style="<?php echo ($status == 'pending' && !$cancelled) ? '' : 'display:none'; ?>">
        <span class="dot"></span> <?php echo $p['provider'] == 'mercadopago' ? 'Aguardando o pagamento do Pix...' : 'Confirmando o pagamento...'; ?>
    </div>

    <?php if ($p['provider'] == 'mercadopago' && $status == 'pending') { ?>
        <div id="pix_box">
            <?php if ($p['qr_base64'] != '') { ?><img class="pay-qr mb-3" src="data:image/png;base64,<?php echo htmlspecialchars($p['qr_base64']); ?>" alt="QR Code Pix"><?php } ?>
            <div class="small text-muted mb-1">Pix copia e cola</div>
            <div class="pay-code mb-2" id="pix_code"><?php echo htmlspecialchars($p['qr_code']); ?></div>
            <button type="button" class="btn btn-primary w-100 mb-2" id="copy_pix"><i class="ri-file-copy-line"></i> Copiar código Pix</button>
            <div class="small text-muted text-center">Abra o app do seu banco, escolha <b>Pix → Pagar com QR Code</b> ou <b>Copia e cola</b>. A confirmação aparece aqui automaticamente.
                <?php if ($p['expires_at'] > 0) { ?><br>Válido até <?php echo date('H:i', $p['expires_at']); ?>.<?php } ?></div>
        </div>
    <?php } ?>

    <a href="<?php echo $back; ?>" class="btn btn-outline-secondary w-100 mt-3" id="back_btn"><?php echo $status == 'paid' ? 'Continuar' : 'Voltar'; ?></a>
</div>

<script>
(function () {
    var copy = document.getElementById('copy_pix');
    if (copy) copy.addEventListener('click', function () {
        var text = document.getElementById('pix_code').textContent.trim();
        var done = function () { copy.innerHTML = '<i class="ri-check-line"></i> Código copiado'; setTimeout(function () { copy.innerHTML = '<i class="ri-file-copy-line"></i> Copiar código Pix'; }, 2000); };
        if (navigator.clipboard) navigator.clipboard.writeText(text).then(done);
    });

    var status = <?php echo json_encode($cancelled ? 'cancelled' : $status); ?>;
    if (status !== 'pending') return;

    function show(st) {
        document.getElementById('state_wait').style.display = 'none';
        if (st === 'paid') {
            document.getElementById('state_paid').style.display = '';
            var box = document.getElementById('pix_box'); if (box) box.style.display = 'none';
            var b = document.getElementById('back_btn'); b.textContent = 'Continuar'; b.className = 'btn btn-success w-100 mt-3';
            setTimeout(function () { window.location.href = <?php echo json_encode($back); ?>; }, 4000);
        } else {
            document.getElementById('bad_text').textContent = st === 'expired' ? 'Este Pix expirou. Gere um novo.' : 'Pagamento não aprovado.';
            document.getElementById('state_bad').style.display = '';
        }
    }

    // Tempo real: o servidor avisa assim que o gateway confirmar (Server-Sent Events, reconecta sozinho)
    if (window.EventSource) {
        var es = new EventSource('status.php?p=<?php echo (int)$pid; ?>');
        es.addEventListener('status', function (e) {
            if (e.data !== 'pending') { es.close(); show(e.data); }
        });
    } else {
        // Navegadores antigos: consulta a cada 5 segundos
        setInterval(function () {
            fetch('status.php?p=<?php echo (int)$pid; ?>&once=1').then(function (r) { return r.text(); }).then(function (st) { if (st !== 'pending') show(st); });
        }, 5000);
    }
})();
</script>
</body>
</html>
