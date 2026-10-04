<?php
/**
 * Status do pagamento em tempo real (Server-Sent Events).
 * A tela fica conectada; quando o webhook confirmar, o evento "status: paid" é enviado na hora.
 * Como garantia, a cada ~10 s também consulta o gateway diretamente (caso o webhook atrase).
 * ?once=1 devolve só o status atual (para navegadores sem EventSource).
 */
require_once(__DIR__."/../includes/db_helper.php");
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/payment_helper.php");
payments_migrate($mysqli);

$pid = isset($_GET['p']) ? (int)$_GET['p'] : 0;
$p = $pid ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_payments WHERE id = $pid")) : null;
$owner_id = 0;
if ($p) $owner_id = $p['owner_type'] == 'reseller' ? (isset($_SESSION['rv_id']) ? (int)$_SESSION['rv_id'] : 0) : (isset($_SESSION['cl_id']) ? (int)$_SESSION['cl_id'] : 0);
session_write_close(); // libera a sessão: outras páginas do usuário não ficam travadas

if (!$p || $owner_id !== (int)$p['owner_id']) { http_response_code(403); exit; }

function st_sync($mysqli, $p){
    if ($p['provider'] == 'mercadopago' && $p['provider_id'] != '') pay_mp_sync($mysqli, $p['provider_id']);
    if ($p['provider'] == 'stripe' && $p['provider_id'] != '') pay_stripe_sync($mysqli, $p['provider_id']);
}

if (isset($_GET['once'])) {
    while (ob_get_level() > 0) ob_end_clean();
    st_sync($mysqli, $p);
    header('Content-Type: text/plain; charset=utf-8');
    echo pay_status_for_owner($mysqli, $pid, $p['owner_type'], $p['owner_id']);
    exit;
}

while (ob_get_level() > 0) ob_end_clean();
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
set_time_limit(40);

echo "retry: 3000\n\n";
flush();

$start = time();
$last_sync = 0;
$last = '';
while (time() - $start < 30) {
    if (time() - $last_sync >= 10) { st_sync($mysqli, $p); $last_sync = time(); }
    $st = pay_status_for_owner($mysqli, $pid, $p['owner_type'], $p['owner_id']);
    if ($st !== $last) {
        echo "event: status\ndata: ".$st."\n\n";
        $last = $st;
    } else {
        echo ": ping\n\n"; // mantém a conexão viva atrás do proxy
    }
    @flush();
    if ($st != 'pending' || connection_aborted()) break;
    sleep(2);
}
// Fim do ciclo: o navegador reconecta sozinho (retry) e continua acompanhando
