<?php
/**
 * Primeiro acesso de uma sub-revenda: troca a senha provisória (enviada por e-mail) por uma senha própria.
 */
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_auth.php");
require_once(__DIR__."/../includes/auth_layout.php");

$reseller = rv_current($mysqli);
if (!$reseller) rv_redirect('index.php');
if (empty($reseller['must_change'])) rv_redirect('dashboard.php');
$rid = (int)$reseller['id'];

$errors = array();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current = isset($_POST['current']) ? (string)$_POST['current'] : '';
    $p1 = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $p2 = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
    if (!rv_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
    if (!password_verify($current, $reseller['password'])) $errors[] = 'A senha provisória não confere. Copie e cole a senha que veio no e-mail.';
    if (!acc_valid_password($p1)) $errors[] = __('A senha precisa ter pelo menos {0} caracteres e não pode ser uma senha óbvia (como 12345678).', ACC_MIN_PASSWORD);
    if ($p1 === $current) $errors[] = 'A nova senha precisa ser diferente da provisória.';
    if ($p1 !== $p2) $errors[] = 'As senhas não conferem.';
    if (!$errors) {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $now = time();
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_resellers SET password = ?, must_change = 0, updated_at = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sii', $hash, $now, $rid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        trust_revoke_all($mysqli, 'rv', $rid);
        rv_flash('success', 'Senha alterada. Bem-vindo ao seu painel!');
        rv_redirect('dashboard.php');
    }
}

auth_start(array('prefix' => '../', 'title' => 'Crie sua senha', 'badge' => 'Primeiro acesso', 'icon' => 'ri-lock-password-line',
    'subtitle' => 'Para a sua segurança, troque a senha provisória que veio no e-mail por uma senha só sua.')); ?>
        <?php auth_alert('err', $errors); ?>
        <form method="post" autocomplete="off">
            <?php echo rv_csrf_field(); ?>
            <?php auth_input(array('name' => 'current', 'type' => 'password', 'label' => 'Senha provisória (do e-mail)', 'icon' => 'ri-key-2-line', 'attrs' => 'autocomplete="off" autofocus')); ?>
            <div class="au-row">
                <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Nova senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="'.ACC_MIN_PASSWORD.'" autocomplete="new-password"')); ?>
                <?php auth_input(array('name' => 'password_confirm', 'type' => 'password', 'label' => 'Confirmar nova senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="'.ACC_MIN_PASSWORD.'" autocomplete="new-password"')); ?>
            </div>
            <div class="au-hint" style="margin:-6px 0 16px;">Mínimo de 8 caracteres.</div>
            <button type="submit" class="au-btn"><i class="ri-check-line"></i> Salvar e entrar</button>
        </form>
        <p class="au-alt"><a href="logout.php">Sair</a></p>
<?php auth_end(); ?>
