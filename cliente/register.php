<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_auth.php");
require_once(__DIR__."/../includes/auth_layout.php");

if (cl_current($mysqli)) cl_redirect('dashboard.php');
acc_migrate($mysqli);
acc_cleanup_unverified($mysqli);

$errors = array();
$sent_to = '';
$form = array('name' => '', 'email' => '', 'phone' => '');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form['name'] = mb_substr(trim(strip_tags(isset($_POST['name']) ? $_POST['name'] : '')), 0, 120);
    $form['email'] = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $form['phone'] = mb_substr(preg_replace('/[^0-9+()\- ]/', '', isset($_POST['phone']) ? $_POST['phone'] : ''), 0, 30);
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';

    if (!cl_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
    if ($form['name'] == '') $errors[] = 'Informe seu nome.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (!acc_valid_password($password)) $errors[] = __('A senha precisa ter pelo menos {0} caracteres e não pode ser uma senha óbvia (como 12345678).', ACC_MIN_PASSWORD);
    if ($password !== $confirm) $errors[] = 'As senhas não conferem.';
    if (!$errors && !acc_mail_rate_ok($mysqli, 'cl-signup', 5)) $errors[] = 'Muitos cadastros a partir desta rede. Tente mais tarde.';

    if (!$errors) {
        $stmt = mysqli_prepare($mysqli, "SELECT id, name, email FROM tbl_customers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $form['email']);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existing) {
            // Não revela que o e-mail já existe: mostra a mesma tela e avisa o dono por e-mail
            acc_send_exists_notice('customer', $existing);
            $sent_to = $form['email'];
        } else {
            // Geolocalização: país pelo IP define a moeda (Brasil = R$, demais = US$)
            $country = customer_detect_country();
            $currency = customer_currency_for_country($country);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = time();
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_customers (name, email, phone, password, status, country_code, currency, email_verified, created_at) VALUES (?,?,?,?,1,?,?,0,?)");
            mysqli_stmt_bind_param($stmt, 'ssssssi', $form['name'], $form['email'], $form['phone'], $hash, $country, $currency, $now);
            $ok = mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($mysqli);
            mysqli_stmt_close($stmt);
            if ($ok && $new_id) {
                acc_send_verify($mysqli, 'customer', array('id' => $new_id, 'name' => $form['name'], 'email' => $form['email']));
                $sent_to = $form['email'];
            } else {
                $errors[] = 'Não foi possível concluir o cadastro. Tente novamente.';
            }
        }
    }
}

if ($sent_to !== '') {
    auth_start(array('prefix' => '../', 'title' => 'Confirme seu e-mail', 'badge' => 'Crie sua conta', 'icon' => 'ri-user-add-line')); ?>
        <div class="au-done">
            <div class="au-done__ico"><i class="ri-mail-send-line"></i></div>
            <h2>Confirme seu e-mail</h2>
            <p>Enviamos um link de confirmação para <b translate="no"><?php echo cl_e($sent_to); ?></b>. Abra o e-mail e clique em <b>Confirmar e-mail</b> para ativar o seu acesso.</p>
            <p class="au-small">Não chegou? Confira a caixa de spam. O link vale por 5 dias; depois disso o cadastro é excluído automaticamente.</p>
        </div>
        <a href="index.php" class="au-btn"><i class="ri-login-box-line"></i> Ir para o login</a>
<?php auth_end();
    exit;
}

auth_start(array('prefix' => '../', 'title' => 'Cadastro', 'badge' => 'Crie sua conta', 'icon' => 'ri-user-add-line', 'wide' => true, 'subtitle' => 'Assine um plano, receba o código de ativação e cadastre as suas playlists.')); ?>
        <?php auth_alert('err', $errors); ?>
        <form method="post">
            <?php echo cl_csrf_field(); ?>
            <?php auth_input(array('name' => 'name', 'label' => 'Nome', 'icon' => 'ri-user-3-line', 'value' => $form['name'], 'attrs' => 'maxlength="120" autocomplete="name"')); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $form['email'], 'placeholder' => 'seu@email.com', 'hint' => 'Use um e-mail válido: você vai receber um link para confirmar.', 'attrs' => 'maxlength="191" autocomplete="email"')); ?>
            <?php auth_input(array('name' => 'phone', 'type' => 'tel', 'label' => 'WhatsApp', 'icon' => 'ri-whatsapp-line', 'value' => $form['phone'], 'placeholder' => '+55 11 99999-9999', 'required' => false, 'attrs' => 'maxlength="30" autocomplete="tel"')); ?>
            <div class="au-row">
                <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="'.ACC_MIN_PASSWORD.'" autocomplete="new-password"')); ?>
                <?php auth_input(array('name' => 'password_confirm', 'type' => 'password', 'label' => 'Confirmar senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="'.ACC_MIN_PASSWORD.'" autocomplete="new-password"')); ?>
            </div>
            <div class="au-hint" style="margin:-6px 0 16px;">Mínimo de 8 caracteres.</div>
            <button type="submit" class="au-btn"><i class="ri-user-add-line"></i> Criar conta</button>
        </form>
        <p class="au-alt">Já tem conta? <a href="index.php">Entrar</a></p>
<?php auth_end(); ?>
