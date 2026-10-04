<?php
require_once(__DIR__."/_init.php");

if (rv_current($mysqli)) rv_redirect('dashboard.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $limit_key = 'L:'.devices_client_ip();

    if (!rv_csrf_ok()) {
        $error = 'Sessão expirada. Tente novamente.';
    } else if (devices_link_rate_limited($mysqli, $limit_key)) {
        $error = 'Muitas tentativas. Aguarde 1 hora e tente novamente.';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_resellers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$r || !password_verify($password, $r['password'])) {
            devices_link_fail($mysqli, $limit_key);
            $error = 'E-mail ou senha incorretos.';
        } else if ((int)$r['status'] != 1) {
            $error = 'Sua conta está bloqueada. Fale com o administrador.';
        } else {
            session_regenerate_id(true);
            $_SESSION['rv_id'] = (int)$r['id'];
            mysqli_query($mysqli, "UPDATE tbl_resellers SET last_login = ".time()." WHERE id = ".(int)$r['id']);
            rv_redirect('dashboard.php');
        }
    }
}

require_once(__DIR__."/../includes/auth_layout.php");
$f = rv_take_flash();
auth_start(array('prefix' => '../', 'title' => 'Entrar', 'badge' => 'Área do Revendedor', 'icon' => 'ri-store-2-line', 'subtitle' => 'Entre para gerenciar seus aparelhos, créditos e a sua marca.')); ?>
        <?php if ($f) auth_alert($f['type'] == 'success' ? 'ok' : 'err', $f['msg']); ?>
        <?php auth_alert('err', $error); ?>
        <form method="post" autocomplete="on">
            <?php echo rv_csrf_field(); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $email, 'placeholder' => 'seu@email.com', 'attrs' => 'autofocus autocomplete="email"')); ?>
            <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'placeholder' => '••••••••', 'attrs' => 'autocomplete="current-password"')); ?>
            <button type="submit" class="au-btn"><i class="ri-login-box-line"></i> Entrar</button>
        </form>
        <?php if (rv_signup_enabled($mysqli)) { ?>
            <p class="au-alt">Ainda não é revendedor? <a href="register.php">Cadastre-se</a></p>
        <?php } ?>
<?php auth_end(); ?>
