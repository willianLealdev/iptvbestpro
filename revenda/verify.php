<?php
// Tela compartilhada: includes/account_pages.php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_pages.php");
accp_verify_page($mysqli, array('type' => 'reseller', 'scope' => 'rv', 'prefix' => '../', 'badge' => 'Área do Revendedor', 'icon' => 'ri-store-2-line', 'login' => 'index.php', 'forgot' => 'forgot.php', 'session_key' => 'rv_id', 'flash_key' => 'rv_flash', 'after_login' => 'dashboard.php'));
