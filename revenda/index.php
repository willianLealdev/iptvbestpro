<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_auth.php");
require_once(__DIR__."/../includes/auth_layout.php");

if (rv_current($mysqli)) rv_redirect('dashboard.php');
acc_migrate($mysqli);

$error = '';
$notice = '';
$unverified_id = 0;
$email = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $locked = acc_locked_until($mysqli, 'rv', $email);

    if (!rv_csrf_ok()) {
        $error = 'Sessão expirada. Tente novamente.';
    } else if ($locked) {
        $error = acc_lock_message($locked);
    } else if (isset($_POST['resend_verify'])) {
        // Reenviar o link de confirmação (só depois de acertar a senha, ver abaixo)
        $id = isset($_SESSION['rv_pending_verify']) ? (int)$_SESSION['rv_pending_verify'] : 0;
        $r = $id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, name, email, email_verified FROM tbl_resellers WHERE id = $id")) : null;
        if ($r && !(int)$r['email_verified'] && acc_mail_rate_ok($mysqli, 'rv-resend', 3)) acc_send_verify($mysqli, 'reseller', $r);
        $notice = 'Se o cadastro estiver pendente, enviamos um novo link de confirmação. Confira sua caixa de entrada e o spam.';
        $email = $r ? $r['email'] : '';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_resellers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$r || !password_verify($password, $r['password'])) {
            acc_register_fail($mysqli, 'rv', $email);
            $error = acc_wrong_message($mysqli, 'rv', $email);
        } else if (isset($r['email_verified']) && !(int)$r['email_verified']) {
            $_SESSION['rv_pending_verify'] = (int)$r['id'];
            $unverified_id = (int)$r['id'];
        } else if ((int)$r['status'] != 1) {
            $error = 'Sua conta está bloqueada. Fale com o administrador.';
        } else if ((isset($r['kind']) && $r['kind'] != '' ? $r['kind'] : 'reseller') != RV_KIND) {
            // conta de servidor tentando entrar na revenda (ou o contrário)
            $error = rv_is_server() ? 'Esta conta é de revendedor. Entre pela Área do Revendedor.' : 'Esta conta é de servidor parceiro. Entre pela Área do Servidor.';
        } else {
            acc_clear_fails($mysqli, 'rv', $email);
            unset($_SESSION['rv_pending_verify']);
            session_regenerate_id(true);
            $_SESSION['rv_id'] = (int)$r['id'];
            mysqli_query($mysqli, "UPDATE tbl_resellers SET last_login = ".time()." WHERE id = ".(int)$r['id']);
            rv_redirect('dashboard.php');
        }
    }
}

$f = rv_take_flash();
auth_start(rv_is_server()
    ? array('prefix' => '../', 'title' => 'Entrar', 'badge' => 'Área do Servidor', 'icon' => 'ri-server-line', 'subtitle' => 'Entre para gerenciar seus créditos, DNS, aparelhos e a sua marca.')
    : array('prefix' => '../', 'title' => 'Entrar', 'badge' => 'Área do Revendedor', 'icon' => 'ri-store-2-line', 'subtitle' => 'Entre para gerenciar seus aparelhos, créditos e a sua marca.')); ?>
        <?php if ($f) auth_alert($f['type'] == 'success' ? 'ok' : 'err', $f['msg']); ?>
        <?php auth_alert('err', $error); ?>
        <?php auth_alert('ok', $notice); ?>
        <?php if ($unverified_id) { ?>
            <div class="au-alert warn"><i class="ri-mail-unread-line"></i><div>
                <b>Confirme seu e-mail para entrar.</b><br>Enviamos um link de confirmação quando você se cadastrou. Abra o e-mail e clique em <b>Confirmar e-mail</b>.
                <form method="post" style="margin-top:8px;">
                    <?php echo rv_csrf_field(); ?>
                    <button type="submit" name="resend_verify" class="au-link-btn"><i class="ri-refresh-line"></i> Reenviar link de confirmação</button>
                </form>
            </div></div>
        <?php } ?>
        <form method="post" autocomplete="on">
            <?php echo rv_csrf_field(); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $email, 'placeholder' => 'seu@email.com', 'attrs' => 'autofocus autocomplete="email"')); ?>
            <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'placeholder' => '••••••••', 'attrs' => 'autocomplete="current-password"')); ?>
            <div class="au-line"><span></span><a href="forgot.php">Esqueceu a senha?</a></div>
            <button type="submit" class="au-btn"><i class="ri-login-box-line"></i> Entrar</button>
        </form>
        <?php if (rv_signup_enabled($mysqli)) { ?>
            <p class="au-alt"><?php echo rv_is_server() ? 'Ainda não é parceiro?' : 'Ainda não é revendedor?'; ?> <a href="register.php">Cadastre-se</a></p>
        <?php } ?>
<?php auth_end(); ?>
