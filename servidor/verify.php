<?php
// Tela compartilhada: includes/account_pages.php (conta de servidor parceiro)
define('RV_KIND', 'server');
require_once(__DIR__."/../revenda/_init.php");
require_once(__DIR__."/../includes/account_pages.php");
accp_verify_page($mysqli, array('type' => 'server', 'scope' => 'sv', 'prefix' => '../', 'badge' => 'Área do Servidor', 'icon' => 'ri-server-line', 'login' => 'index.php', 'forgot' => 'forgot.php', 'session_key' => 'rv_id', 'flash_key' => 'rv_flash', 'after_login' => 'dashboard.php'));
