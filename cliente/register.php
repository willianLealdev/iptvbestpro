<?php
require_once(__DIR__."/_init.php");

if (cl_current($mysqli)) cl_redirect('dashboard.php');

$errors = array();
$form = array('name' => '', 'email' => '', 'phone' => '');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form['name'] = mb_substr(trim(strip_tags(isset($_POST['name']) ? $_POST['name'] : '')), 0, 120);
    $form['email'] = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $form['phone'] = mb_substr(preg_replace('/[^0-9+()\- ]/', '', isset($_POST['phone']) ? $_POST['phone'] : ''), 0, 30);
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
    $limit_key = 'CR:'.devices_client_ip();

    if (!cl_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
    if (devices_link_rate_limited($mysqli, $limit_key)) $errors[] = 'Muitos cadastros a partir desta rede. Tente mais tarde.';
    if ($form['name'] == '') $errors[] = 'Informe seu nome.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (strlen($password) < 6) $errors[] = 'A senha precisa ter pelo menos 6 caracteres.';
    if ($password !== $confirm) $errors[] = 'As senhas não conferem.';

    if (!$errors) {
        $stmt = mysqli_prepare($mysqli, "SELECT id FROM tbl_customers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $form['email']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = 'Este e-mail já está cadastrado. Faça login.';
        mysqli_stmt_close($stmt);
    }

    if (!$errors) {
        // Geolocalização: país pelo IP define a moeda (Brasil = R$, demais = US$)
        $country = customer_detect_country();
        $currency = customer_currency_for_country($country);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = time();
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_customers (name, email, phone, password, status, country_code, currency, created_at) VALUES (?,?,?,?,1,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssssssi', $form['name'], $form['email'], $form['phone'], $hash, $country, $currency, $now);
        $ok = mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($mysqli);
        mysqli_stmt_close($stmt);

        if ($ok && $new_id) {
            devices_link_fail($mysqli, $limit_key); // conta como 1 cadastro para o limite por rede
            session_regenerate_id(true);
            $_SESSION['cl_id'] = (int)$new_id;
            cl_flash('success', 'Cadastro concluído! Escolha um plano para receber seu código de ativação.');
            cl_redirect('dashboard.php#planos');
        }
        $errors[] = 'Não foi possível concluir o cadastro. Tente novamente.';
    }
}

require_once(__DIR__."/../includes/auth_layout.php");
auth_start(array('prefix' => '../', 'title' => 'Cadastro', 'badge' => 'Crie sua conta', 'icon' => 'ri-user-add-line', 'wide' => true, 'subtitle' => 'Assine um plano, receba o código de ativação e cadastre as suas playlists.')); ?>
        <?php auth_alert('err', $errors); ?>
        <form method="post">
            <?php echo cl_csrf_field(); ?>
            <?php auth_input(array('name' => 'name', 'label' => 'Nome', 'icon' => 'ri-user-3-line', 'value' => $form['name'], 'attrs' => 'maxlength="120" autocomplete="name"')); ?>
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
