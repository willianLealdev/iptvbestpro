<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_auth.php");
require_once(__DIR__."/../includes/auth_layout.php");

if (rv_current($mysqli)) rv_redirect('dashboard.php');
if (!rv_signup_enabled($mysqli)) {
    rv_flash('error', 'O cadastro de novos revendedores está fechado no momento.');
    rv_redirect('index.php');
}
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

    if (!rv_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
    if ($form['name'] == '') $errors[] = 'Informe seu nome.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (!acc_valid_password($password)) $errors[] = __('A senha precisa ter pelo menos {0} caracteres e não pode ser uma senha óbvia (como 12345678).', ACC_MIN_PASSWORD);
    if ($password !== $confirm) $errors[] = 'As senhas não conferem.';
    if (!$errors && !acc_mail_rate_ok($mysqli, 'rv-signup', 5)) $errors[] = 'Muitos cadastros a partir desta rede. Tente mais tarde.';

    if (!$errors) {
        $stmt = mysqli_prepare($mysqli, "SELECT id, name, email FROM tbl_resellers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $form['email']);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existing) {
            // Não revela que o e-mail já existe: mostra a mesma tela e avisa o dono por e-mail
            acc_send_exists_notice('reseller', $existing);
            $sent_to = $form['email'];
        } else {
            $code = activation_code_generate($mysqli);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = time();
            $country = devices_country(devices_client_ip());
            $currency = ($country['code'] == '' || strtoupper($country['code']) == 'BR') ? 'BRL' : 'USD';
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_resellers (name, email, phone, password, code, credits, status, remark, currency, email_verified, created_at, updated_at) VALUES (?,?,?,?,?,0,1,'Cadastro pelo site',?,0,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssssssii', $form['name'], $form['email'], $form['phone'], $hash, $code, $currency, $now, $now);
            $ok = $code !== false && mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($mysqli);
            mysqli_stmt_close($stmt);
            if ($ok && $new_id) {
                acc_send_verify($mysqli, 'reseller', array('id' => $new_id, 'name' => $form['name'], 'email' => $form['email']));
                $sent_to = $form['email'];
            } else {
                $errors[] = 'Não foi possível concluir o cadastro. Tente novamente.';
            }
        }
    }
}

if ($sent_to !== '') {
    auth_start(array('prefix' => '../', 'title' => 'Confirme seu e-mail', 'badge' => 'Seja um Revendedor', 'icon' => 'ri-store-2-line')); ?>
        <div class="au-done">
            <div class="au-done__ico"><i class="ri-mail-send-line"></i></div>
            <h2>Confirme seu e-mail</h2>
            <p>Enviamos um link de confirmação para <b translate="no"><?php echo rv_e($sent_to); ?></b>. Abra o e-mail e clique em <b>Confirmar e-mail</b> para ativar o seu acesso.</p>
            <p class="au-small">Não chegou? Confira a caixa de spam. O link vale por 5 dias; depois disso o cadastro é excluído automaticamente.</p>
        </div>
        <a href="index.php" class="au-btn"><i class="ri-login-box-line"></i> Ir para o login</a>
<?php auth_end();
    exit;
}

auth_start(array('prefix' => '../', 'title' => 'Cadastro', 'badge' => 'Seja um Revendedor', 'icon' => 'ri-store-2-line', 'wide' => true, 'subtitle' => 'Crie sua conta e receba seu código exclusivo de ativação.')); ?>
        <?php auth_alert('err', $errors); ?>
        <form method="post">
            <?php echo rv_csrf_field(); ?>
            <?php auth_input(array('name' => 'name', 'label' => 'Nome / Empresa', 'icon' => 'ri-user-3-line', 'value' => $form['name'], 'attrs' => 'maxlength="120" autocomplete="name"')); ?>
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
