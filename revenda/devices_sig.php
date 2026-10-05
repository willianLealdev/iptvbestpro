<?php
// Assinatura dos dispositivos deste revendedor (a tela atualiza sozinha quando muda)
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/devices_live.php");
$reseller = rv_current($mysqli);
if (!$reseller) { http_response_code(403); exit; }
devices_live_output($mysqli, "owner = 'reseller' AND reseller_id = ".(int)$reseller['id']);
