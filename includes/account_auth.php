<?php
/**
 * Segurança das contas (revendedor, cliente e admin):
 *  - Confirmação de e-mail no cadastro: o link chega por e-mail; sem confirmar, não entra.
 *    Cadastros não confirmados em 5 dias são excluídos automaticamente.
 *  - Redefinição de senha por link (vale 1 hora, uso único).
 *  - Nunca revela se um e-mail está cadastrado (login, cadastro e "esqueci a senha" usam mensagens neutras).
 *  - Bloqueio de login: 3 erros em 1 hora (pelo e-mail ou pelo IP) = 1 hora sem poder entrar.
 *
 * Tokens: só o hash SHA-256 fica no banco; o link leva o token aleatório de 64 caracteres.
 */

define('ACC_MAX_FAILS', 3);
define('ACC_LOCK_SECONDS', 3600);
define('ACC_VERIFY_TTL', 5 * 86400);   // link de confirmação: 5 dias (mesmo prazo da exclusão)
define('ACC_RESET_TTL', 3600);          // link de nova senha: 1 hora
define('ACC_UNVERIFIED_DAYS', 5);
define('ACC_MIN_PASSWORD', 8);

function acc_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    // email_verified: 1 = pode entrar. Contas antigas e criadas pelo admin já nascem confirmadas (padrão 1);
    // o cadastro pelo site grava 0 até a pessoa clicar no link.
    foreach (array('tbl_resellers', 'tbl_customers') as $t) {
        $exists = mysqli_query($mysqli, "SHOW TABLES LIKE '$t'");
        if (!$exists || mysqli_num_rows($exists) == 0) continue;
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `$t` LIKE 'email_verified'");
        if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `$t` ADD `email_verified` TINYINT(1) NOT NULL DEFAULT 1");
    }
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_auth_tokens` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_type` VARCHAR(10) NOT NULL,
        `account_id` INT(11) NOT NULL,
        `purpose` VARCHAR(10) NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `expires_at` INT(11) NOT NULL,
        `used_at` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_token` (`token_hash`),
        KEY `idx_account` (`account_type`, `account_id`, `purpose`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_auth_fails` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `k` VARCHAR(191) NOT NULL,
        `created_at` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_k_time` (`k`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function acc_table($type){
    $map = array('reseller' => 'tbl_resellers', 'server' => 'tbl_resellers', 'customer' => 'tbl_customers', 'admin' => 'tbl_admin');
    return isset($map[$type]) ? $map[$type] : '';
}

function acc_ip(){
    if (function_exists('devices_client_ip')) return devices_client_ip();
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

function acc_base_url(){
    // Sem navegador (monitor em segundo plano): usa o endereço do site salvo na última visita ao painel
    if (empty($_SERVER['HTTP_HOST']) && empty($_SERVER['HTTP_X_FORWARDED_HOST'])) return brand_saved_base_url();
    $proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http');
    $host = !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    return strtolower(trim(explode(',', $proto)[0])).'://'.trim(explode(',', $host)[0]);
}

/* ---------- Bloqueio por tentativas ---------- */

function acc_fail_keys($scope, $email){
    $keys = array($scope.':ip:'.acc_ip());
    $email = mb_strtolower(trim((string)$email));
    if ($email !== '') $keys[] = $scope.':em:'.mb_substr($email, 0, 160);
    return $keys;
}

// Até quando está bloqueado (timestamp) ou 0 se pode tentar
function acc_locked_until($mysqli, $scope, $email){
    acc_migrate($mysqli);
    $since = time() - ACC_LOCK_SECONDS;
    $until = 0;
    foreach (acc_fail_keys($scope, $email) as $k) {
        $stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS n, MAX(created_at) AS last FROM tbl_auth_fails WHERE k = ? AND created_at >= ?");
        mysqli_stmt_bind_param($stmt, 'si', $k, $since);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ((int)$r['n'] >= ACC_MAX_FAILS) $until = max($until, (int)$r['last'] + ACC_LOCK_SECONDS);
    }
    return $until > time() ? $until : 0;
}

function acc_register_fail($mysqli, $scope, $email){
    acc_migrate($mysqli);
    $now = time();
    foreach (acc_fail_keys($scope, $email) as $k) {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_auth_fails (k, created_at) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'si', $k, $now);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    if (random_int(1, 40) == 1) mysqli_query($mysqli, "DELETE FROM tbl_auth_fails WHERE created_at < ".($now - 86400));
}

function acc_clear_fails($mysqli, $scope, $email){
    acc_migrate($mysqli);
    foreach (acc_fail_keys($scope, $email) as $k) {
        $stmt = mysqli_prepare($mysqli, "DELETE FROM tbl_auth_fails WHERE k = ?");
        mysqli_stmt_bind_param($stmt, 's', $k);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Quantas tentativas ainda restam (para avisar "restam 2 tentativas")
function acc_attempts_left($mysqli, $scope, $email){
    $since = time() - ACC_LOCK_SECONDS;
    $max = 0;
    foreach (acc_fail_keys($scope, $email) as $k) {
        $stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS n FROM tbl_auth_fails WHERE k = ? AND created_at >= ?");
        mysqli_stmt_bind_param($stmt, 'si', $k, $since);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $max = max($max, (int)$r['n']);
    }
    return max(0, ACC_MAX_FAILS - $max);
}

function acc_lock_message($until){
    $min = max(1, (int)ceil(($until - time()) / 60));
    return __('Por segurança, o acesso foi bloqueado após 3 tentativas incorretas. Tente novamente em {0} minuto(s).', $min);
}

// Mensagem padrão de erro de login (nunca diz se o e-mail existe)
function acc_wrong_message($mysqli, $scope, $email){
    $left = acc_attempts_left($mysqli, $scope, $email);
    if ($left <= 0) return acc_lock_message(acc_locked_until($mysqli, $scope, $email));
    return __('E-mail ou senha incorretos.').' '.__('Restam {0} tentativa(s) antes do bloqueio de 1 hora.', $left);
}

/* ---------- Tokens (confirmação e nova senha) ---------- */

function acc_token_create($mysqli, $type, $id, $purpose, $ttl){
    acc_migrate($mysqli);
    $id = (int)$id;
    // um link válido por vez: invalida os anteriores do mesmo tipo
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_auth_tokens SET used_at = ? WHERE account_type = ? AND account_id = ? AND purpose = ? AND used_at = 0");
    $now = time();
    mysqli_stmt_bind_param($stmt, 'isis', $now, $type, $id, $purpose);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $exp = $now + (int)$ttl;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_auth_tokens (account_type, account_id, purpose, token_hash, expires_at, created_at) VALUES (?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'sissii', $type, $id, $purpose, $hash, $exp, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $token;
}

// Confere o token. Retorna o id da conta (0 se inválido/expirado). $consume = marcar como usado.
function acc_token_check($mysqli, $type, $purpose, $token, $consume = false){
    acc_migrate($mysqli);
    if (!preg_match('/^[0-9a-f]{64}$/', (string)$token)) return 0;
    $hash = hash('sha256', $token);
    $now = time();
    $stmt = mysqli_prepare($mysqli, "SELECT id, account_id FROM tbl_auth_tokens WHERE token_hash = ? AND account_type = ? AND purpose = ? AND used_at = 0 AND expires_at >= ?");
    mysqli_stmt_bind_param($stmt, 'sssi', $hash, $type, $purpose, $now);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$r) return 0;
    if ($consume) mysqli_query($mysqli, "UPDATE tbl_auth_tokens SET used_at = $now WHERE id = ".(int)$r['id']." AND used_at = 0");
    return (int)$r['account_id'];
}

/* ---------- E-mails ---------- */

function acc_mail($to, $name, $subject, $html){
    global $mysqli;
    if (!function_exists('send_email')) require_once(__DIR__.'/../smtp_email.php');
    try { return (bool)send_email($to, $name, $subject, $html); }
    catch (\Throwable $e) { error_log('[conta] e-mail não enviado: '.$e->getMessage()); return false; }
}

function acc_email_html($title, $paragraphs, $button = '', $url = '', $note = ''){
    $brand = htmlspecialchars(brand_name());
    $logo = htmlspecialchars(function_exists('brand_email_logo_url') && brand_mail_base_url() != '' ? brand_email_logo_url() : acc_base_url().'/assets/images/brand/email_logo.png');
    $p = '';
    foreach ((array)$paragraphs as $t) $p .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#3b4560;">'.$t.'</p>';
    $btn = $button != '' ? '<p style="margin:26px 0;text-align:center;"><a href="'.htmlspecialchars($url).'" style="display:inline-block;background:#f5b82e;color:#1b1300;font-weight:700;font-size:16px;text-decoration:none;padding:14px 30px;border-radius:10px;">'.htmlspecialchars($button).'</a></p>'
        .'<p style="margin:0 0 14px;font-size:12.5px;line-height:1.6;color:#8a94ab;">'.htmlspecialchars(__('Se o botão não funcionar, copie e cole este endereço no navegador:')).'<br><span style="word-break:break-all;color:#2f6bff;">'.htmlspecialchars($url).'</span></p>' : '';
    $n = $note != '' ? '<p style="margin:18px 0 0;font-size:12.5px;line-height:1.6;color:#8a94ab;">'.$note.'</p>' : '';
    return '<div data-brand-mail="1" style="background:#f2f5fb;padding:30px 12px;font-family:Arial,Helvetica,sans-serif;">'
        .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">'
        .'<tr><td style="background:#070b18;padding:22px;text-align:center;"><img src="'.$logo.'" alt="'.$brand.'" width="64" height="64" style="display:inline-block;border-radius:14px;border:0;"><div style="color:#ffffff;font-size:18px;font-weight:700;margin-top:8px;">'.$brand.'</div></td></tr>'
        .'<tr><td style="padding:30px 28px;"><h1 style="margin:0 0 16px;font-size:21px;color:#1f2a44;">'.htmlspecialchars($title).'</h1>'.$p.$btn.$n.'</td></tr>'
        .'<tr><td style="padding:16px 28px;background:#f8fafc;font-size:12px;color:#8a94ab;text-align:center;">© '.date('Y').' '.$brand.'</td></tr>'
        .'</table></div>';
}

// $path: caminho da área (revenda / cliente / '' para admin)
function acc_area_url($type, $page){
    $dir = $type == 'reseller' ? '/revenda/' : ($type == 'server' ? '/servidor/' : ($type == 'customer' ? '/cliente/' : '/'));
    return acc_base_url().$dir.$page;
}

function acc_send_verify($mysqli, $type, $row){
    $token = acc_token_create($mysqli, $type, $row['id'], 'verify', ACC_VERIFY_TTL);
    $url = acc_area_url($type, 'verify.php?t='.$token);
    $name = htmlspecialchars($row['name']);
    $html = acc_email_html(__('Confirme seu e-mail'), array(
        __('Olá, {0}!', $name),
        __('Recebemos o seu cadastro no {0}. Para ativar o seu acesso, confirme o seu e-mail clicando no botão abaixo.', htmlspecialchars(brand_name())),
    ), __('Confirmar e-mail'), $url,
        __('O link vale por {0} dias. Se o e-mail não for confirmado nesse prazo, o cadastro é excluído automaticamente. Se você não fez este cadastro, ignore esta mensagem.', ACC_UNVERIFIED_DAYS));
    return acc_mail($row['email'], $row['name'], __('Confirme seu e-mail').' - '.brand_name(), $html);
}

function acc_send_reset($mysqli, $type, $row){
    $token = acc_token_create($mysqli, $type, $row['id'], 'reset', ACC_RESET_TTL);
    $url = acc_area_url($type, ($type == 'admin' ? 'auth_reset.php' : 'reset.php').'?t='.$token);
    $name = htmlspecialchars(isset($row['name']) ? $row['name'] : $row['username']);
    $html = acc_email_html(__('Redefinir senha'), array(
        __('Olá, {0}!', $name),
        __('Recebemos um pedido para redefinir a senha da sua conta. Clique no botão abaixo para criar uma nova senha.'),
    ), __('Criar nova senha'), $url,
        __('O link vale por 1 hora e só pode ser usado uma vez. Se você não pediu a troca de senha, ignore esta mensagem: sua senha continua a mesma.'));
    return acc_mail($row['email'], $name, __('Redefinir senha').' - '.brand_name(), $html);
}

// Alguém tentou cadastrar um e-mail que já tem conta: avisa o dono, sem revelar nada na tela
function acc_send_exists_notice($type, $row){
    $html = acc_email_html(__('Tentativa de cadastro'), array(
        __('Olá, {0}!', htmlspecialchars($row['name'])),
        __('Alguém tentou criar uma nova conta com o seu e-mail, mas você já tem cadastro. Se foi você, basta entrar com a sua senha ou pedir uma nova senha.'),
    ), __('Entrar'), acc_area_url($type, 'index.php'),
        __('Se não foi você, pode ignorar esta mensagem: nada foi alterado na sua conta.'));
    return acc_mail($row['email'], $row['name'], __('Tentativa de cadastro').' - '.brand_name(), $html);
}

/* ---------- Limpeza ---------- */

// Exclui cadastros pelo site que não confirmaram o e-mail em 5 dias
function acc_cleanup_unverified($mysqli){
    acc_migrate($mysqli);
    $limit = time() - ACC_UNVERIFIED_DAYS * 86400;
    $n = 0;
    mysqli_query($mysqli, "DELETE r FROM tbl_resellers r WHERE r.email_verified = 0 AND r.created_at < $limit AND r.credits = 0
                           AND NOT EXISTS (SELECT 1 FROM tbl_devices d WHERE d.reseller_id = r.id)");
    $n += max(0, mysqli_affected_rows($mysqli));
    $sub = mysqli_query($mysqli, "SHOW TABLES LIKE 'tbl_subscriptions'");
    $guard = ($sub && mysqli_num_rows($sub) > 0) ? " AND NOT EXISTS (SELECT 1 FROM tbl_subscriptions s WHERE s.customer_id = c.id)" : '';
    mysqli_query($mysqli, "DELETE c FROM tbl_customers c WHERE c.email_verified = 0 AND c.created_at < $limit".$guard);
    $n += max(0, mysqli_affected_rows($mysqli));
    mysqli_query($mysqli, "DELETE FROM tbl_auth_tokens WHERE expires_at < ".(time() - 86400));
    return $n;
}

// Limite simples de envio de e-mails (cadastro / esqueci a senha / reenviar): por IP, por hora
function acc_mail_rate_ok($mysqli, $scope, $max = 5){
    acc_migrate($mysqli);
    $k = $scope.':mail:'.acc_ip();
    $since = time() - 3600;
    $stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS n FROM tbl_auth_fails WHERE k = ? AND created_at >= ?");
    mysqli_stmt_bind_param($stmt, 'si', $k, $since);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ((int)$r['n'] >= $max) return false;
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_auth_fails (k, created_at) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'si', $k, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return true;
}

function acc_valid_password($p){
    if (!is_string($p) || strlen($p) < ACC_MIN_PASSWORD || strlen($p) > 200) return false;
    // senhas óbvias (as primeiras que um robô testa) não são aceitas, mesmo com 8+ caracteres
    $l = strtolower($p);
    if (in_array($l, array('12345678', '123456789', '1234567890', '87654321', '11111111', '00000000', 'password', 'password1', 'senha123', 'senha1234', 'admin123', 'admin1234', 'qwerty123', 'iptvbestpro', 'abc12345'), true)) return false;
    if (preg_match('/^(.)\1+$/', $p)) return false; // aaaaaaaa, 11111111...
    return true;
}
