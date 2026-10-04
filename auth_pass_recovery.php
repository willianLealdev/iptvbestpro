<?php
// Esqueci a senha / nova senha do painel: tela compartilhada (includes/account_pages.php), sem identificar que é o painel do admin.
include("includes/db_helper.php");
require_once("includes/account_pages.php");
accp_forgot_page($mysqli, array('type' => 'admin', 'scope' => 'adm', 'prefix' => '', 'badge' => '', 'icon' => '', 'login' => 'gerencia.php', 'forgot' => 'auth_pass_recovery.php', 'session_key' => 'id', 'flash_key' => 'auth_flash', 'after_login' => 'gerencia.php'));
