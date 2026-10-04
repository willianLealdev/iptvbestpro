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

cl_head('Entrar'); ?>
<div class="cl-auth">
    <div class="cl-card p-4 p-sm-5">
        <?php brand_auth_header('../', 'Área do Cliente'); ?>
        <?php $f = cl_take_flash(); if ($f) { ?><div class="alert alert-<?php echo $f['type'] == 'success' ? 'success' : 'danger'; ?>"><?php echo cl_e($f['msg']); ?></div><?php } ?>
        <?php if ($error) { ?><div class="alert alert-danger"><?php echo cl_e($error); ?></div><?php } ?>
        <form method="post" autocomplete="on">
            <?php echo cl_csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control form-control-lg" value="<?php echo cl_e($email); ?>" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Senha</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">Entrar</button>
        </form>
        <p class="text-center mt-4 mb-0 cl-muted">Ainda não tem conta? <a href="register.php">Cadastre-se</a></p>
    </div>
</div>
<script src="../assets/vendors/bootstrap/bootstrap.min.js"></script>
</body>
</html>
