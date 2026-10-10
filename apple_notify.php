<?php
/**
 * Avisos da Apple (App Store Server Notifications V2) para a assinatura do app iPhone / iPad / Apple TV.
 * Endereço para colocar no App Store Connect (App → App Information → App Store Server Notifications):
 *   https://iptvbestpro.com/apple_notify.php   (Production e Sandbox, versão 2)
 * A Apple manda {"signedPayload": "..."}; o conteúdo só vale se a assinatura da Apple conferir.
 */
require_once("includes/db_helper.php");
require_once("includes/apple_iap_helper.php");

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo '{"error":"method_not_allowed"}'; exit; }
$body = json_decode((string)file_get_contents('php://input'), true);
$sp = is_array($body) && isset($body['signedPayload']) ? (string)$body['signedPayload'] : '';
if ($sp === '' || strlen($sp) > 60000) { http_response_code(400); echo '{"error":"invalid_request"}'; exit; }
try {
    $ok = apple_notification($mysqli, $sp);
} catch (\Throwable $e) {
    error_log('[apple-notify] '.$e->getMessage());
    $ok = false;
}
// 200 = recebido (a Apple tenta de novo se não for 200); assinatura inválida também responde 200 para não ficar repetindo lixo
http_response_code(200);
echo json_encode(array('ok' => (bool)$ok));
