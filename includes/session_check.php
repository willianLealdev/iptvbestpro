<?php
// Página do painel sem login: volta para a tela de login.
// Envios (POST) repetidos sem login vindos do mesmo IP (robô testando senha) recebem 429 por 1 hora.
if(empty($_SESSION['id']) || !isset($_SESSION['admin_name']) || !isset($_SESSION['admin_type'])){
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($mysqli) && $mysqli) {
		require_once(__DIR__.'/account_auth.php');
		acc_register_fail($mysqli, 'anon', '');
		$__until = acc_locked_until($mysqli, 'anon', '');
		if ($__until) {
			http_response_code(429);
			header('Retry-After: '.max(60, $__until - time()));
			header('Cache-Control: no-store');
			echo 'Too many requests.';
			exit;
		}
	}
	session_destroy();
	header( "Location: gerencia.php?login=required");
	exit;
}
?>
