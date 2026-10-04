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
        // Moeda pelo país do IP: Brasil = R$, demais países = US$
        $country = devices_country(devices_client_ip());
        $currency = ($country['code'] == '' || strtoupper($country['code']) == 'BR') ? 'BRL' : 'USD';
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_resellers (name, email, phone, password, code, credits, status, remark, currency, created_at, updated_at) VALUES (?,?,?,?,?,0,1,'Cadastro pelo site',?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssssssii', $form['name'], $form['email'], $form['phone'], $hash, $code, $currency, $now, $now);
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

require_once(__DIR__."/../includes/auth_layout.php");
auth_start(array('prefix' => '../', 'title' => 'Cadastro', 'badge' => 'Seja um Revendedor', 'icon' => 'ri-store-2-line', 'wide' => true, 'subtitle' => 'Crie sua conta e receba seu código exclusivo de ativação.')); ?>
        <?php auth_alert('err', $errors); ?>
        <form method="post">
            <?php echo rv_csrf_field(); ?>
            <?php auth_input(array('name' => 'name', 'label' => 'Nome / Empresa', 'icon' => 'ri-user-3-line', 'value' => $form['name'], 'attrs' => 'maxlength="120" autocomplete="name"')); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $form['email'], 'placeholder' => 'seu@email.com', 'attrs' => 'maxlength="191" autocomplete="email"')); ?>
            <?php auth_input(array('name' => 'phone', 'type' => 'tel', 'label' => 'WhatsApp', 'icon' => 'ri-whatsapp-line', 'value' => $form['phone'], 'placeholder' => '+55 11 99999-9999', 'required' => false, 'attrs' => 'maxlength="30" autocomplete="tel"')); ?>
            <div class="au-row">
                <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="6" autocomplete="new-password"')); ?>
                <?php auth_input(array('name' => 'password_confirm', 'type' => 'password', 'label' => 'Confirmar senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="6" autocomplete="new-password"')); ?>
            </div>
            <button type="submit" class="au-btn"><i class="ri-user-add-line"></i> Criar conta</button>
        </form>
        <p class="au-alt">Já tem conta? <a href="index.php">Entrar</a></p>
<?php auth_end(); ?>
