<?php
require_once(__DIR__."/_init.php");

if (rv_current($mysqli)) rv_redirect('dashboard.php');
if (!rv_signup_enabled($mysqli)) {
    rv_flash('error', 'O cadastro de novos revendedores está fechado no momento.');
    rv_redirect('index.php');
}

$errors = array();
$form = array('name' => '', 'email' => '', 'phone' => '');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form['name'] = mb_substr(trim(strip_tags(isset($_POST['name']) ? $_POST['name'] : '')), 0, 120);
    $form['email'] = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $form['phone'] = mb_substr(preg_replace('/[^0-9+()\- ]/', '', isset($_POST['phone']) ? $_POST['phone'] : ''), 0, 30);
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
    $limit_key = 'R:'.devices_client_ip();

    if (!rv_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
    if (devices_link_rate_limited($mysqli, $limit_key)) $errors[] = 'Muitos cadastros a partir desta rede. Tente mais tarde.';
    if ($form['name'] == '') $errors[] = 'Informe seu nome.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (strlen($password) < 6) $errors[] = 'A senha precisa ter pelo menos 6 caracteres.';
    if ($password !== $confirm) $errors[] = 'As senhas não conferem.';

    if (!$errors) {
        $stmt = mysqli_prepare($mysqli, "SELECT id FROM tbl_resellers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $form['email']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = 'Este e-mail já está cadastrado. Faça login.';
        mysqli_stmt_close($stmt);
    }

    if (!$errors) {
        $code = activation_code_generate($mysqli);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = time();
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_resellers (name, email, phone, password, code, credits, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,0,1,'Cadastro pelo site',?,?)");
        mysqli_stmt_bind_param($stmt, 'sssssii', $form['name'], $form['email'], $form['phone'], $hash, $code, $now, $now);
        $ok = $code !== false && mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($mysqli);
        mysqli_stmt_close($stmt);

        if ($ok && $new_id) {
            devices_link_fail($mysqli, $limit_key); // conta como 1 cadastro para o limite por rede
            session_regenerate_id(true);
            $_SESSION['rv_id'] = (int)$new_id;
            rv_flash('success', 'Cadastro concluído! Seu código de ativação é '.$code.'.');
            rv_redirect('dashboard.php');
        }
        $errors[] = 'Não foi possível concluir o cadastro. Tente novamente.';
    }
}

rv_head('Cadastro'); ?>
<div class="rv-auth">
    <div class="rv-card p-4 p-sm-5" style="max-width:480px;">
        <div class="text-center mb-4">
            <h1 class="rv-title">Seja um Revendedor</h1>
            <p class="rv-muted mb-0">Crie sua conta e receba seu código exclusivo de ativação.</p>
        </div>
        <?php if ($errors) { ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) { echo '<div>'.rv_e($e).'</div>'; } ?></div>
        <?php } ?>
        <form method="post">
            <?php echo rv_csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Nome / Empresa</label>
                <input type="text" name="name" class="form-control" maxlength="120" value="<?php echo rv_e($form['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" maxlength="191" value="<?php echo rv_e($form['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">WhatsApp</label>
                <input type="tel" name="phone" class="form-control" maxlength="30" value="<?php echo rv_e($form['phone']); ?>" placeholder="+55 11 99999-9999">
            </div>
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label class="form-label">Senha</label>
                    <input type="password" name="password" class="form-control" minlength="6" required autocomplete="new-password">
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Confirmar senha</label>
                    <input type="password" name="password_confirm" class="form-control" minlength="6" required autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn rv-btn-green btn-lg w-100">Criar conta</button>
        </form>
        <p class="text-center mt-4 mb-0 rv-muted">Já tem conta? <a href="index.php">Entrar</a></p>
    </div>
</div>
</body>
</html>
