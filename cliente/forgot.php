<?php
// Tela compartilhada: includes/account_pages.php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_pages.php");
accp_forgot_page($mysqli, array('type' => 'customer', 'scope' => 'cl', 'prefix' => '../', 'badge' => 'Área do Cliente', 'icon' => 'ri-user-3-line', 'login' => 'index.php', 'forgot' => 'forgot.php', 'session_key' => 'cl_id', 'flash_key' => 'cl_flash', 'after_login' => 'dashboard.php'));
