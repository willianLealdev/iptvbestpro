<?php
/**
 * "Confiar neste dispositivo": quem marca a caixa no login continua conectado (admin, revenda/servidor e cliente)
 * até clicar em Sair, mesmo que a sessão do servidor se perca (Deploy, reinício, 24 min parado).
 *
 * Como funciona: no login criamos um código aleatório, guardamos só o HASH dele no banco e o código vai num cookie
 * (HttpOnly, Secure, 1 ano). Quando a sessão some, o código do cookie é conferido no banco e a sessão é refeita.
 * Sair apaga o código. Trocar a senha apaga os códigos de todos os aparelhos. Conta bloqueada não é refeita.
 */

define('TRUST_DAYS', 365);

function trust_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_trusted_devices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `portal` VARCHAR(4) NOT NULL,                 -- adm | rv | cl
        `user_id` INT(11) NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `last_used` INT(11) NOT NULL DEFAULT 0,
        `expires_at` INT(11) NOT NULL DEFAULT 0,
        `ip` VARCHAR(45) NOT NULL DEFAULT '',
        `user_agent` VARCHAR(190) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        KEY `idx_user` (`portal`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function trust_cookie_name($portal){ return 'bp_trust_'.$portal; }

function trust_https(){
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);
}

function trust_set_cookie($portal, $value, $expires){
    if (headers_sent()) return;
    setcookie(trust_cookie_name($portal), $value, array('expires' => $expires, 'path' => '/', 'secure' => trust_https(), 'httponly' => true, 'samesite' => 'Lax'));
}

function trust_client_ip(){
    $ip = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    return mb_substr((string)$ip, 0, 45);
}

// Chamar logo depois do login certo, só se a caixa "Confiar neste dispositivo" estava marcada
function trust_issue($mysqli, $portal, $user_id){
    try {
        trust_migrate($mysqli);
        $portal = in_array($portal, array('adm', 'rv', 'cl'), true) ? $portal : 'cl';
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = time();
        $exp = $now + TRUST_DAYS * 86400;
        $ip = trust_client_ip();
        $ua = mb_substr(isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '', 0, 190);
        $uid = (int)$user_id;
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_trusted_devices (portal, user_id, token_hash, created_at, last_used, expires_at, ip, user_agent) VALUES (?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sisiiiss', $portal, $uid, $hash, $now, $now, $exp, $ip, $ua);
        mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($mysqli);
        mysqli_stmt_close($stmt);
        trust_set_cookie($portal, $id.'.'.$token, $exp);
    } catch (\Throwable $e) {
        error_log('[trust] '.$e->getMessage());
    }
}

// Refaz a sessão de quem tem o cookie válido (chamado no começo de toda página, depois de abrir o banco)
function trust_restore($mysqli){
    $map = array('adm' => 'id', 'rv' => 'rv_id', 'cl' => 'cl_id');
    foreach ($map as $portal => $sess_key) {
        $cname = trust_cookie_name($portal);
        if (empty($_COOKIE[$cname]) || !empty($_SESSION[$sess_key])) continue;
        if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', (string)$_COOKIE[$cname], $m)) { trust_set_cookie($portal, '', time() - 3600); continue; }
        try {
            trust_migrate($mysqli);
            $id = (int)$m[1];
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_trusted_devices WHERE id = $id AND portal = '$portal'"));
            $now = time();
            if (!$row || !hash_equals($row['token_hash'], hash('sha256', $m[2])) || (int)$row['expires_at'] < $now) {
                trust_set_cookie($portal, '', $now - 3600);
                if ($row && (int)$row['expires_at'] < $now) mysqli_query($mysqli, "DELETE FROM tbl_trusted_devices WHERE id = $id");
                continue;
            }
            $uid = (int)$row['user_id'];
            $ok = false;
            if ($portal == 'adm') {
                $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_admin WHERE id = $uid AND id <> 0"));
                if ($u && (int)$u['status'] == 1) {
                    session_regenerate_id(true);
                    $_SESSION['id'] = $u['id'];
                    $_SESSION['admin_name'] = $u['username'];
                    $_SESSION['admin_type'] = $u['admin_type'];
                    unset($_SESSION['admin_must_change_pw']);
                    $ok = true;
                }
            } else if ($portal == 'rv') {
                $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, status, email_verified FROM tbl_resellers WHERE id = $uid"));
                if ($u && (int)$u['status'] == 1 && (!isset($u['email_verified']) || (int)$u['email_verified'])) { session_regenerate_id(true); $_SESSION['rv_id'] = $uid; $ok = true; }
            } else {
                $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, status, email_verified FROM tbl_customers WHERE id = $uid"));
                if ($u && (int)$u['status'] == 1 && (!isset($u['email_verified']) || (int)$u['email_verified'])) { session_regenerate_id(true); $_SESSION['cl_id'] = $uid; $ok = true; }
            }
            if (!$ok) {
                // conta bloqueada ou apagada: o código deixa de valer
                mysqli_query($mysqli, "DELETE FROM tbl_trusted_devices WHERE id = $id");
                trust_set_cookie($portal, '', $now - 3600);
                continue;
            }
            // uso recente: renova a validade e o cookie (uma vez por dia basta)
            if ($now - (int)$row['last_used'] > 86400) {
                $exp = $now + TRUST_DAYS * 86400;
                mysqli_query($mysqli, "UPDATE tbl_trusted_devices SET last_used = $now, expires_at = $exp WHERE id = $id");
                trust_set_cookie($portal, $_COOKIE[$cname], $exp);
            }
        } catch (\Throwable $e) {
            error_log('[trust] '.$e->getMessage());
        }
    }
}

// Sair: apaga o código deste aparelho
function trust_revoke_current($mysqli, $portal){
    $cname = trust_cookie_name($portal);
    try {
        if (!empty($_COOKIE[$cname]) && preg_match('/^(\d+)\./', (string)$_COOKIE[$cname], $m)) {
            trust_migrate($mysqli);
            mysqli_query($mysqli, "DELETE FROM tbl_trusted_devices WHERE id = ".(int)$m[1]." AND portal = '".(in_array($portal, array('adm', 'rv', 'cl'), true) ? $portal : 'cl')."'");
        }
    } catch (\Throwable $e) {
        error_log('[trust] '.$e->getMessage());
    }
    trust_set_cookie($portal, '', time() - 3600);
    unset($_COOKIE[$cname]);
}

// Trocou ou redefiniu a senha: todos os aparelhos "confiados" dessa conta precisam entrar de novo
function trust_revoke_all($mysqli, $portal, $user_id){
    try {
        trust_migrate($mysqli);
        $portal = in_array($portal, array('adm', 'rv', 'cl'), true) ? $portal : 'cl';
        mysqli_query($mysqli, "DELETE FROM tbl_trusted_devices WHERE portal = '$portal' AND user_id = ".(int)$user_id);
    } catch (\Throwable $e) {
        error_log('[trust] '.$e->getMessage());
    }
}

// Caixa do formulário de login (marcada ou não por padrão)
function trust_checkbox($checked = false){
    return '<label class="au-check"><input type="checkbox" name="trust" value="1"'.($checked ? ' checked' : '').'> Confiar neste dispositivo</label>';
}
