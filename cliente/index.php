<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_auth.php");
require_once(__DIR__."/../includes/auth_layout.php");

if (cl_current($mysqli)) cl_redirect('dashboard.php');
acc_migrate($mysqli);

$error = '';
$notice = '';
$unverified_id = 0;
$email = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $locked = acc_locked_until($mysqli, 'cl', $email);

    if (!cl_csrf_ok()) {
        $error = 'Sessão expirada. Tente novamente.';
    } else if ($locked) {
        $error = acc_lock_message($locked);
    } else if (isset($_POST['resend_verify'])) {
        $id = isset($_SESSION['cl_pending_verify']) ? (int)$_SESSION['cl_pending_verify'] : 0;
        $c = $id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, name, email, email_verified FROM tbl_customers WHERE id = $id")) : null;
        if ($c && !(int)$c['email_verified'] && acc_mail_rate_ok($mysqli, 'cl-resend', 3)) acc_send_verify($mysqli, 'customer', $c);
        $notice = 'Se o cadastro estiver pendente, enviamos um novo link de confirmação. Confira sua caixa de entrada e o spam.';
        $email = $c ? $c['email'] : '';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_customers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$c || !password_verify($password, $c['password'])) {
            acc_register_fail($mysqli, 'cl', $email);
            $error = acc_wrong_message($mysqli, 'cl', $email);
        } else if (isset($c['email_verified']) && !(int)$c['email_verified']) {
            $_SESSION['cl_pending_verify'] = (int)$c['id'];
            $unverified_id = (int)$c['id'];
        } else if ((int)$c['status'] != 1) {
            $error = 'Sua conta está bloqueada. Fale com o suporte.';
        } else {
            acc_clear_fails($mysqli, 'cl', $email);
            unset($_SESSION['cl_pending_verify']);
            session_regenerate_id(true);
            $_SESSION['cl_id'] = (int)$c['id'];
            mysqli_query($mysqli, "UPDATE tbl_customers SET last_login = ".time()." WHERE id = ".(int)$c['id']);
            cl_redirect('dashboard.php');
        }
    }
}

$f = cl_take_flash();
auth_start(array('prefix' => '../', 'title' => 'Entrar', 'badge' => 'Área do Cliente', 'icon' => 'ri-user-3-line', 'subtitle' => 'Entre para ver suas assinaturas, códigos e playlists.')); ?>
        <?php if ($f) auth_alert($f['type'] == 'success' ? 'ok' : 'err', $f['msg']); ?>
        <?php auth_alert('err', $error); ?>
        <?php auth_alert('ok', $notice); ?>
        <?php if ($unverified_id) { ?>
            <div class="au-alert warn"><i class="ri-mail-unread-line"></i><div>
                <b>Confirme seu e-mail para entrar.</b><br>Enviamos um link de confirmação quando você se cadastrou. Abra o e-mail e clique em <b>Confirmar e-mail</b>.
                <form method="post" style="margin-top:8px;">
                    <?php echo cl_csrf_field(); ?>
                    <button type="submit" name="resend_verify" class="au-link-btn"><i class="ri-refresh-line"></i> Reenviar link de confirmação</button>
                </form>
            </div></div>
        <?php } ?>
        <form method="post" autocomplete="on">
            <?php echo cl_csrf_field(); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $email, 'placeholder' => 'seu@email.com', 'attrs' => 'autofocus autocomplete="email"')); ?>
            <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'placeholder' => '••••••••', 'attrs' => 'autocomplete="current-password"')); ?>
            <div class="au-line"><span></span><a href="forgot.php">Esqueceu a senha?</a></div>
            <button type="submit" class="au-btn"><i class="ri-login-box-line"></i> Entrar</button>
        </form>
        <p class="au-alt">Ainda não tem conta? <a href="register.php">Cadastre-se</a></p>
<?php auth_end(); ?>
