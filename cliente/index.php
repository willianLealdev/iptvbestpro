<?php
require_once(__DIR__."/_init.php");

if (cl_current($mysqli)) cl_redirect('dashboard.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $limit_key = 'C:'.devices_client_ip();

    if (!cl_csrf_ok()) {
        $error = 'Sessão expirada. Tente novamente.';
    } else if (devices_link_rate_limited($mysqli, $limit_key)) {
        $error = 'Muitas tentativas. Aguarde 1 hora e tente novamente.';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_customers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$c || !password_verify($password, $c['password'])) {
            devices_link_fail($mysqli, $limit_key);
            $error = 'E-mail ou senha incorretos.';
        } else if ((int)$c['status'] != 1) {
            $error = 'Sua conta está bloqueada. Fale com o suporte.';
        } else {
            session_regenerate_id(true);
            $_SESSION['cl_id'] = (int)$c['id'];
            mysqli_query($mysqli, "UPDATE tbl_customers SET last_login = ".time()." WHERE id = ".(int)$c['id']);
            cl_redirect('dashboard.php');
        }
    }
}

require_once(__DIR__."/../includes/auth_layout.php");
$f = cl_take_flash();
auth_start(array('prefix' => '../', 'title' => 'Entrar', 'badge' => 'Área do Cliente', 'icon' => 'ri-user-3-line', 'subtitle' => 'Entre para ver suas assinaturas, códigos e playlists.')); ?>
        <?php if ($f) auth_alert($f['type'] == 'success' ? 'ok' : 'err', $f['msg']); ?>
        <?php auth_alert('err', $error); ?>
        <form method="post" autocomplete="on">
            <?php echo cl_csrf_field(); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $email, 'placeholder' => 'seu@email.com', 'attrs' => 'autofocus autocomplete="email"')); ?>
            <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'placeholder' => '••••••••', 'attrs' => 'autocomplete="current-password"')); ?>
            <button type="submit" class="au-btn"><i class="ri-login-box-line"></i> Entrar</button>
        </form>
        <p class="au-alt">Ainda não tem conta? <a href="register.php">Cadastre-se</a></p>
<?php auth_end(); ?>
