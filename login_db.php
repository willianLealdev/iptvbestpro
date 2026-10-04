<?php
/**
 * Login do painel (gerencia.php). Consulta com prepared statement, bloqueio de 1 hora após 3 erros
 * e mensagem neutra (nunca diz se o usuário/e-mail existe).
 */
include("includes/db_helper.php");
require_once("includes/account_pages.php");

function adm_back($msg, $type = 'err'){
    $_SESSION['auth_msg'] = $msg;
    $_SESSION['auth_type'] = $type;
    header("Location:gerencia.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') { header("Location:gerencia.php"); exit; }

$login = mb_substr(trim(isset($_POST['user_login']) ? (string)$_POST['user_login'] : ''), 0, 191);
$password = isset($_POST['nsofts_password_input']) ? (string)$_POST['nsofts_password_input'] : '';
$key = mb_strtolower($login);

if (!accp_csrf_ok()) adm_back(__('Sessão expirada. Tente novamente.'));
$locked = acc_locked_until($mysqli, 'adm', $key);
if ($locked) adm_back(acc_lock_message($locked));
if ($login === '' || $password === '') adm_back(__('Informe usuário e senha.'));

$stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_admin WHERE (username = ? OR email = ?) AND id <> 0 LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ss', $login, $login);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$ok = false;
if ($row) {
    $stored = (string)$row['password'];
    // senhas novas (redefinidas pelo link) usam password_hash; as antigas do sistema original, md5
    $ok = (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon') === 0) ? password_verify($password, $stored) : hash_equals($stored, md5($password));
}

if (!$ok) {
    acc_register_fail($mysqli, 'adm', $key);
    $left = acc_attempts_left($mysqli, 'adm', $key);
    if ($left <= 0) adm_back(acc_lock_message(acc_locked_until($mysqli, 'adm', $key)));
    adm_back(__('Usuário ou senha incorretos.').' '.__('Restam {0} tentativa(s) antes do bloqueio de 1 hora.', $left));
}

if ((int)$row['status'] == 0) adm_back(__('Sua conta está bloqueada. Fale com o administrador.'));

acc_clear_fails($mysqli, 'adm', $key);
session_regenerate_id(true);
$_SESSION['id'] = $row['id'];
$_SESSION['admin_name'] = $row['username'];
$_SESSION['admin_type'] = $row['admin_type'];
$_SESSION['class'] = "success";
$_SESSION['msg'] = "17";
header("Location:dashboard.php");
exit;
