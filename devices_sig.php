<?php
// Assinatura da lista de dispositivos (tela do admin atualiza sozinha quando muda)
require_once("includes/db_helper.php");
require_once("includes/devices_live.php");
if (empty($_SESSION['id']) || empty($_SESSION['admin_type'])) { http_response_code(403); exit; }
devices_live_output($mysqli);
