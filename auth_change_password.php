<?php
/**
 * Troca obrigatória da senha do painel quando a senha atual é a padrão ou fraca (ex.: admin/admin).
 * O admin_guard.php manda para cá até a senha ser trocada.
 */
include("includes/db_helper.php");
include("includes/session_check.php");
require_once("includes/account_pages.php");

$errors = array();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $p1 = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $p2 = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT username FROM tbl_admin WHERE id = ".(int)$_SESSION['id']));
    $weak = array('admin', 'admin123', 'password', '12345678', '123456789', 'senha123');
    if (!accp_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
    if (!acc_valid_password($p1)) $errors[] = __('A senha precisa ter pelo menos {0} caracteres e não pode ser uma senha óbvia (como 12345678).', ACC_MIN_PASSWORD);
    if (in_array(strtolower($p1), $weak, true) || ($row && strtolower($p1) === strtolower($row['username']))) $errors[] = 'Escolha uma senha mais forte (não use a senha padrão nem o nome de usuário).';
    if ($p1 !== $p2) $errors[] = 'As senhas não conferem.';
    if (!$errors) {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_admin SET password = ? WHERE id = ?");
        $id = (int)$_SESSION['id'];
        mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        unset($_SESSION['admin_must_change_pw']);
        session_regenerate_id(true);
        $_SESSION['class'] = 'success';
        $_SESSION['msg'] = '11';
        header("Location: dashboard.php");
        exit;
    }
}

auth_start(array('prefix' => '', 'title' => 'Criar nova senha', 'subtitle' => 'Por segurança, troque a senha antes de continuar. A senha atual é a padrão ou é muito fraca.'));
auth_alert('err', $errors); ?>
        <form method="post">
            <?php echo accp_csrf_field(); ?>
            <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Nova senha', 'icon' => 'ri-lock-2-line', 'hint' => 'Mínimo de 8 caracteres.', 'attrs' => 'autofocus minlength="8" autocomplete="new-password"')); ?>
            <?php auth_input(array('name' => 'password_confirm', 'type' => 'password', 'label' => 'Confirmar nova senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="8" autocomplete="new-password"')); ?>
            <button type="submit" class="au-btn"><i class="ri-check-line"></i> Salvar nova senha</button>
        </form>
        <p class="au-alt"><a href="logout.php">Sair</a></p>
<?php auth_end(); ?>
