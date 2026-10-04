<?php
/**
 * Webhook do Stripe (cartão).
 * 1) Valida a assinatura (Stripe-Signature) com o "Signing secret" do webhook.
 * 2) Consulta a sessão na API do Stripe e confere valor/moeda com o pedido do banco antes de liberar.
 */
require_once(__DIR__."/../includes/db_helper.php");
while (ob_get_level() > 0) ob_end_clean();
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/payment_helper.php");
payments_migrate($mysqli);

$payload = file_get_contents('php://input');
if (!pay_stripe_signature_ok($mysqli, $payload)) { http_response_code(400); echo 'invalid signature'; exit; }

$event = json_decode($payload, true);
$type = isset($event['type']) ? $event['type'] : '';
$obj = isset($event['data']['object']) ? $event['data']['object'] : array();

if (in_array($type, array('checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired', 'checkout.session.async_payment_failed'))
    && !empty($obj['id'])) {
    pay_stripe_sync($mysqli, $obj['id']);
}
http_response_code(200);
echo 'ok';
