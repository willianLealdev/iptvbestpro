<?php
/**
 * Telas compartilhadas de conta: confirmar e-mail, esqueci a senha e criar nova senha.
 * Usadas pela revenda, pelo cliente e (esqueci/nova senha) pelo admin.
 *
 * $cfg: type (reseller|customer|admin), scope (rv|cl|adm), prefix ('../' ou ''), badge, icon,
 *       login ('index.php' / 'gerencia.php'), session_key ('rv_id' / 'cl_id'), after_login ('dashboard.php')
 */
require_once(__DIR__."/account_auth.php");
require_once(__DIR__."/auth_layout.php");

function accp_csrf_token(){
    if (empty($_SESSION['acc_csrf'])) $_SESSION['acc_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['acc_csrf'];
}
function accp_csrf_field(){ return '<input type="hidden" name="acc_csrf" value="'.accp_csrf_token().'">'; }
function accp_csrf_ok(){ return isset($_POST['acc_csrf'], $_SESSION['acc_csrf']) && hash_equals($_SESSION['acc_csrf'], (string)$_POST['acc_csrf']); }

function accp_start($cfg, $title, $subtitle = ''){
    auth_start(array('prefix' => $cfg['prefix'], 'title' => $title, 'badge' => $cfg['badge'], 'icon' => $cfg['icon'], 'subtitle' => $subtitle));
}

function accp_result($cfg, $ok, $title, $text, $button, $url){
    accp_start($cfg, $title); ?>
        <div class="au-done">
            <div class="au-done__ico <?php echo $ok ? 'ok' : 'err'; ?>"><i class="<?php echo $ok ? 'ri-checkbox-circle-line' : 'ri-close-circle-line'; ?>"></i></div>
            <h2><?php echo auth_e($title); ?></h2>
            <p><?php echo auth_e($text); ?></p>
        </div>
        <a href="<?php echo auth_e($url); ?>" class="au-btn"><?php echo auth_e($button); ?></a>
<?php auth_end();
    exit;
}

/* ---------- Confirmar e-mail (link do cadastro) ---------- */
function accp_verify_page($mysqli, $cfg){
    acc_migrate($mysqli);
    $token = isset($_GET['t']) ? (string)$_GET['t'] : '';
    $id = acc_token_check($mysqli, $cfg['type'], 'verify', $token, true);
    $table = acc_table($cfg['type']);
    if (!$id || !($row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM `$table` WHERE id = ".(int)$id)))) {
        accp_result($cfg, false, 'Link inválido ou expirado',
            'Este link de confirmação não é mais válido. Entre com seu e-mail e senha para receber um novo link. Cadastros não confirmados em 5 dias são excluídos.',
            'Ir para o login', $cfg['login']);
    }
    mysqli_query($mysqli, "UPDATE `$table` SET email_verified = 1 WHERE id = ".(int)$id);
    acc_clear_fails($mysqli, $cfg['scope'], $row['email']);
    // já entra direto na conta
    session_regenerate_id(true);
    $_SESSION[$cfg['session_key']] = (int)$id;
    $msg = 'E-mail confirmado! Seu acesso está ativo.';
    if (($cfg['type'] == 'reseller' || $cfg['type'] == 'server') && !empty($row['code'])) $msg .= ' '.__('Seu código de ativação é {0}.', $row['code']);
    $_SESSION[$cfg['flash_key']] = array('type' => 'success', 'msg' => $msg);
    header('Location: '.$cfg['after_login']);
    exit;
}

/* ---------- Esqueci a senha ---------- */
function accp_forgot_page($mysqli, $cfg){
    acc_migrate($mysqli);
    $done = false;
    $error = '';
    $email = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = mb_strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
        if (!accp_csrf_ok()) {
            $error = 'Sessão expirada. Tente novamente.';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Informe um e-mail válido.';
        } else {
            // Sempre a mesma resposta, exista ou não a conta
            if (acc_mail_rate_ok($mysqli, $cfg['scope'].'-forgot', 5)) {
                $table = acc_table($cfg['type']);
                $cols = $cfg['type'] == 'admin' ? 'id, username, email, status' : 'id, name, email, status';
                $stmt = mysqli_prepare($mysqli, "SELECT $cols FROM `$table` WHERE email = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                // segurança: e-mail do fornecedor antigo (nemosofts) nunca recebe link de troca de senha
                if ($row && preg_match('/nemosoft/i', (string)$row['email'])) $row = null;
                if ($row && ($cfg['type'] == 'admin' ? (int)$row['id'] > 0 : true)) acc_send_reset($mysqli, $cfg['type'], $row);
            }
            $done = true;
        }
    }
    if ($done) {
        accp_start($cfg, 'Verifique seu e-mail'); ?>
        <div class="au-done">
            <div class="au-done__ico"><i class="ri-mail-send-line"></i></div>
            <h2>Verifique seu e-mail</h2>
            <p>Se o e-mail <b translate="no"><?php echo auth_e($email); ?></b> estiver cadastrado, você vai receber um link para criar uma nova senha.</p>
            <p class="au-small">O link vale por 1 hora. Não chegou? Confira a caixa de spam.</p>
        </div>
        <a href="<?php echo auth_e($cfg['login']); ?>" class="au-btn"><i class="ri-arrow-left-line"></i> Voltar para o login</a>
<?php   auth_end();
        exit;
    }
    accp_start($cfg, 'Esqueceu a senha?', 'Informe o seu e-mail. Se ele estiver cadastrado, enviaremos um link para você criar uma nova senha.'); ?>
        <?php auth_alert('err', $error); ?>
        <form method="post">
            <?php echo accp_csrf_field(); ?>
            <?php auth_input(array('name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'value' => $email, 'placeholder' => 'seu@email.com', 'attrs' => 'autofocus autocomplete="email"')); ?>
            <button type="submit" class="au-btn"><i class="ri-mail-send-line"></i> Enviar link</button>
        </form>
        <p class="au-alt"><a href="<?php echo auth_e($cfg['login']); ?>"><i class="ri-arrow-left-line"></i> Voltar para o login</a></p>
<?php auth_end();
    exit;
}

/* ---------- Criar nova senha (link do e-mail) ---------- */
function accp_reset_page($mysqli, $cfg){
    acc_migrate($mysqli);
    $token = isset($_GET['t']) ? (string)$_GET['t'] : (isset($_POST['t']) ? (string)$_POST['t'] : '');
    $id = acc_token_check($mysqli, $cfg['type'], 'reset', $token, false);
    if (!$id) {
        accp_result($cfg, false, 'Link inválido ou expirado',
            'Este link para criar uma nova senha não é mais válido (vale por 1 hora e só pode ser usado uma vez). Peça um novo link.',
            'Pedir novo link', $cfg['forgot']);
    }
    $errors = array();
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $p1 = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $p2 = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
        if (!accp_csrf_ok()) $errors[] = 'Sessão expirada. Tente novamente.';
        if (!acc_valid_password($p1)) $errors[] = __('A senha precisa ter pelo menos {0} caracteres e não pode ser uma senha óbvia (como 12345678).', ACC_MIN_PASSWORD);
        if ($p1 !== $p2) $errors[] = 'As senhas não conferem.';
        if (!$errors && acc_token_check($mysqli, $cfg['type'], 'reset', $token, true) === $id) {
            $table = acc_table($cfg['type']);
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($mysqli, "UPDATE `$table` SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            // quem redefiniu pelo e-mail provou que o e-mail é válido
            if ($cfg['type'] != 'admin') mysqli_query($mysqli, "UPDATE `$table` SET email_verified = 1 WHERE id = ".(int)$id);
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT email FROM `$table` WHERE id = ".(int)$id));
            if ($row) acc_clear_fails($mysqli, $cfg['scope'], $row['email']);
            accp_result($cfg, true, 'Senha alterada', 'Sua nova senha foi salva. Agora é só entrar com ela.', 'Entrar', $cfg['login']);
        } else if (!$errors) {
            $errors[] = 'Este link não é mais válido. Peça um novo link.';
        }
    }
    accp_start($cfg, 'Criar nova senha', 'Escolha uma nova senha para a sua conta.'); ?>
        <?php auth_alert('err', $errors); ?>
        <form method="post">
            <?php echo accp_csrf_field(); ?>
            <input type="hidden" name="t" value="<?php echo auth_e($token); ?>">
            <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Nova senha', 'icon' => 'ri-lock-2-line', 'hint' => 'Mínimo de 8 caracteres.', 'attrs' => 'autofocus minlength="'.ACC_MIN_PASSWORD.'" autocomplete="new-password"')); ?>
            <?php auth_input(array('name' => 'password_confirm', 'type' => 'password', 'label' => 'Confirmar nova senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'minlength="'.ACC_MIN_PASSWORD.'" autocomplete="new-password"')); ?>
            <button type="submit" class="au-btn"><i class="ri-check-line"></i> Salvar nova senha</button>
        </form>
<?php auth_end();
    exit;
}
