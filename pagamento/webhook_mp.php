<?php
/**
 * Webhook do Mercado Pago (Pix).
 * Nunca confia no conteúdo recebido: pega só o id do pagamento e CONSULTA a API do Mercado Pago.
 * Se a chave secreta do webhook estiver configurada, valida também a assinatura (x-signature).
 */
require_once(__DIR__."/../includes/db_helper.php");
while (ob_get_level() > 0) ob_end_clean();
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/payment_helper.php");
payments_migrate($mysqli);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

// Formatos aceitos: ?data.id=123&type=payment | ?id=123&topic=payment | corpo {"type":"payment","data":{"id":"123"}}
$type = isset($_GET['type']) ? $_GET['type'] : (isset($_GET['topic']) ? $_GET['topic'] : (isset($body['type']) ? $body['type'] : (isset($body['topic']) ? $body['topic'] : '')));
$id = isset($_GET['data_id']) ? $_GET['data_id'] : (isset($_GET['data.id']) ? $_GET['data.id'] : (isset($_GET['id']) ? $_GET['id'] : (isset($body['data']['id']) ? $body['data']['id'] : '')));
$id = preg_replace('/\D/', '', (string)$id);

if ($type != 'payment' || $id == '') { http_response_code(200); echo 'ignored'; exit; }
if (!pay_mp_signature_ok($mysqli, $id)) { http_response_code(401); echo 'invalid signature'; exit; }

pay_mp_sync($mysqli, $id);
http_response_code(200);
echo 'ok';
