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

rv_head('Entrar'); ?>
<div class="rv-auth">
    <div class="rv-card p-4 p-sm-5">
        <?php brand_auth_header('../', 'Área do Revendedor'); ?>
        <?php $f = rv_take_flash(); if ($f) { ?><div class="alert alert-<?php echo $f['type'] == 'success' ? 'success' : 'danger'; ?>"><?php echo rv_e($f['msg']); ?></div><?php } ?>
        <?php if ($error) { ?><div class="alert alert-danger"><?php echo rv_e($error); ?></div><?php } ?>
        <form method="post" autocomplete="on">
            <?php echo rv_csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control form-control-lg" value="<?php echo rv_e($email); ?>" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Senha</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">Entrar</button>
        </form>
        <?php if (rv_signup_enabled($mysqli)) { ?>
            <p class="text-center mt-4 mb-0 rv-muted">Ainda não é revendedor? <a href="register.php">Cadastre-se</a></p>
        <?php } ?>
    </div>
</div>
<script src="../assets/vendors/bootstrap/bootstrap.min.js"></script>
</body>
</html>
