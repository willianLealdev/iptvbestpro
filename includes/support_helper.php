<?php
/**
 * Suporte (IPTV Best Pro)
 *  - Chamados revenda <-> admin: a revenda abre e responde em revenda/support.php; o admin responde em manage_support.php.
 *  - Contato de suporte que aparece no app para o cliente: o da revenda dona do aparelho; sem revenda, o do admin.
 *  - Mensagens enviadas pelo cliente no app (Suporte/Report): ficam com o aparelho e a revenda dona dele.
 */
require_once(__DIR__."/brand_helper.php");
require_once(__DIR__."/reseller_helper.php");
require_once(__DIR__."/account_auth.php");

define('SUPPORT_TELEGRAM_DEFAULT', 'https://t.me/+v2RQZuJX84wyZTMx');

function support_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    resellers_migrate($mysqli);
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_support_tickets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `reseller_id` INT(11) NOT NULL,
        `subject` VARCHAR(160) NOT NULL,
        `status` VARCHAR(12) NOT NULL DEFAULT 'open',
        `last_from` VARCHAR(10) NOT NULL DEFAULT 'reseller',
        `unread_admin` TINYINT(1) NOT NULL DEFAULT 1,
        `unread_reseller` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_reseller` (`reseller_id`),
        KEY `idx_status` (`status`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_support_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ticket_id` INT(11) NOT NULL,
        `sender` VARCHAR(10) NOT NULL,
        `sender_name` VARCHAR(120) NOT NULL DEFAULT '',
        `body` TEXT NOT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_ticket` (`ticket_id`, `id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Contato de suporte da revenda (aparece no app para os clientes dela)
    foreach (array('support_whatsapp' => "VARCHAR(30) NOT NULL DEFAULT ''", 'support_note' => "VARCHAR(200) NOT NULL DEFAULT ''") as $name => $def) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_resellers` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_resellers` ADD `$name` $def");
    }

    // Mensagens do app: aparelho e revenda dona; a senha da lista não é mais guardada
    $cols = array('device_id' => "VARCHAR(191) NOT NULL DEFAULT ''", 'reseller_id' => "INT(11) NOT NULL DEFAULT 0",
                  'device_model' => "VARCHAR(100) NOT NULL DEFAULT ''", 'is_read' => "TINYINT(1) NOT NULL DEFAULT 0");
    foreach ($cols as $name => $def) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_reports` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_reports` ADD `$name` $def");
    }
    mysqli_query($mysqli, "UPDATE tbl_reports SET user_pass = '' WHERE user_pass <> ''");
}

function support_e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Só dígitos com DDI (ex.: 5511999998888) — usado no link do WhatsApp
function support_phone_clean($phone){
    $d = preg_replace('/\D+/', '', (string)$phone);
    return strlen($d) >= 10 && strlen($d) <= 15 ? $d : '';
}

function support_whatsapp_link($phone, $text = ''){
    $d = support_phone_clean($phone);
    if ($d === '') return '';
    return 'https://wa.me/'.$d.($text !== '' ? '?text='.rawurlencode($text) : '');
}

function support_telegram_url($mysqli){
    $u = trim(panel_config_get($mysqli, 'support_telegram', SUPPORT_TELEGRAM_DEFAULT));
    return preg_match('#^https://(t\.me|telegram\.me)/#i', $u) ? $u : '';
}

/**
 * Contato de suporte para o aparelho (app): revenda dona do aparelho; senão o do admin.
 * Revenda sem número cadastrado: o cliente vê o aviso para falar com a revenda (o número do admin não aparece).
 */
function support_contact_for_device($mysqli, $device){
    $out = array('owner' => 'admin', 'name' => brand_name(), 'whatsapp' => '', 'note' => '');
    if ($device && isset($device['owner']) && $device['owner'] == 'reseller' && (int)$device['reseller_id'] > 0) {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT name, support_whatsapp, support_note FROM tbl_resellers WHERE id = ".(int)$device['reseller_id']));
        $out['owner'] = 'reseller';
        if ($r) {
            $out['name'] = $r['name'];
            $out['whatsapp'] = support_phone_clean($r['support_whatsapp']);
            $out['note'] = (string)$r['support_note'];
        }
        return $out;
    }
    if ($device && isset($device['owner']) && $device['owner'] == 'customer') $out['owner'] = 'customer';
    $out['whatsapp'] = support_phone_clean(panel_config_get($mysqli, 'support_whatsapp', ''));
    $out['note'] = (string)panel_config_get($mysqli, 'support_note', '');
    return $out;
}

/* ---------- Chamados ---------- */

function support_ticket_create($mysqli, $reseller, $subject, $body){
    $subject = trim(mb_substr((string)$subject, 0, 160));
    $body = trim(mb_substr((string)$body, 0, 5000));
    if ($subject === '' || $body === '') return array('ok' => false, 'msg' => 'Preencha o assunto e a mensagem.');
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_support_tickets (reseller_id, subject, status, last_from, unread_admin, unread_reseller, created_at, updated_at) VALUES (?, ?, 'open', 'reseller', 1, 0, ?, ?)");
    $rid = (int)$reseller['id'];
    mysqli_stmt_bind_param($stmt, 'isii', $rid, $subject, $now, $now);
    mysqli_stmt_execute($stmt);
    $tid = mysqli_insert_id($mysqli);
    mysqli_stmt_close($stmt);
    support_message_add($mysqli, $tid, 'reseller', $reseller['name'], $body);
    support_notify_admin($mysqli, $tid, $reseller['name'], $subject, $body, true);
    return array('ok' => true, 'msg' => 'Chamado aberto. Você recebe a resposta aqui e por e-mail.', 'id' => $tid);
}

function support_message_add($mysqli, $tid, $sender, $sender_name, $body){
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_support_messages (ticket_id, sender, sender_name, body, created_at) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isssi', $tid, $sender, $sender_name, $body, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function support_ticket_get($mysqli, $tid, $reseller_id = null){
    $sql = "SELECT t.*, r.name AS reseller_name, r.email AS reseller_email FROM tbl_support_tickets t LEFT JOIN tbl_resellers r ON r.id = t.reseller_id WHERE t.id = ".(int)$tid;
    if ($reseller_id !== null) $sql .= " AND t.reseller_id = ".(int)$reseller_id;
    return mysqli_fetch_assoc(mysqli_query($mysqli, $sql));
}

function support_messages($mysqli, $tid){
    $out = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_support_messages WHERE ticket_id = ".(int)$tid." ORDER BY id ASC");
    while ($res && $m = mysqli_fetch_assoc($res)) $out[] = $m;
    return $out;
}

function support_reply_reseller($mysqli, $reseller, $tid, $body){
    $t = support_ticket_get($mysqli, $tid, (int)$reseller['id']);
    $body = trim(mb_substr((string)$body, 0, 5000));
    if (!$t) return array('ok' => false, 'msg' => 'Chamado não encontrado.');
    if ($body === '') return array('ok' => false, 'msg' => 'Escreva a mensagem.');
    support_message_add($mysqli, (int)$t['id'], 'reseller', $reseller['name'], $body);
    mysqli_query($mysqli, "UPDATE tbl_support_tickets SET status = 'open', last_from = 'reseller', unread_admin = 1, unread_reseller = 0, updated_at = ".time()." WHERE id = ".(int)$t['id']);
    support_notify_admin($mysqli, (int)$t['id'], $reseller['name'], $t['subject'], $body, false);
    return array('ok' => true, 'msg' => 'Mensagem enviada.');
}

function support_reply_admin($mysqli, $tid, $admin_name, $body){
    $t = support_ticket_get($mysqli, $tid);
    $body = trim(mb_substr((string)$body, 0, 5000));
    if (!$t) return array('ok' => false, 'msg' => 'Chamado não encontrado.');
    if ($body === '') return array('ok' => false, 'msg' => 'Escreva a resposta.');
    support_message_add($mysqli, (int)$t['id'], 'admin', $admin_name, $body);
    mysqli_query($mysqli, "UPDATE tbl_support_tickets SET status = 'answered', last_from = 'admin', unread_admin = 0, unread_reseller = 1, updated_at = ".time()." WHERE id = ".(int)$t['id']);
    support_notify_reseller($mysqli, $t, $body);
    return array('ok' => true, 'msg' => 'Resposta enviada para a revenda.');
}

function support_set_status($mysqli, $tid, $status, $reseller_id = null){
    if (!in_array($status, array('open', 'closed'), true)) return false;
    $sql = "UPDATE tbl_support_tickets SET status = '$status', updated_at = ".time()." WHERE id = ".(int)$tid;
    if ($reseller_id !== null) $sql .= " AND reseller_id = ".(int)$reseller_id;
    mysqli_query($mysqli, $sql);
    return mysqli_affected_rows($mysqli) > 0;
}

function support_mark_read($mysqli, $tid, $side){
    $col = $side == 'admin' ? 'unread_admin' : 'unread_reseller';
    mysqli_query($mysqli, "UPDATE tbl_support_tickets SET $col = 0 WHERE id = ".(int)$tid);
}

function support_unread_admin($mysqli){
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_support_tickets WHERE unread_admin = 1 AND status <> 'closed'"));
    return $r ? (int)$r['n'] : 0;
}

function support_unread_reseller($mysqli, $rid){
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_support_tickets WHERE unread_reseller = 1 AND reseller_id = ".(int)$rid));
    $a = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_reports WHERE is_read = 0 AND reseller_id = ".(int)$rid));
    return ($r ? (int)$r['n'] : 0) + ($a ? (int)$a['n'] : 0);
}

function support_status_badge($status){
    $map = array('open' => array('Aguardando resposta', 'warning'), 'answered' => array('Respondido', 'success'), 'closed' => array('Encerrado', 'secondary'));
    $s = isset($map[$status]) ? $map[$status] : $map['open'];
    return '<span class="badge bg-'.$s[1].'">'.$s[0].'</span>';
}

/* ---------- E-mails ---------- */

function support_notify_admin($mysqli, $tid, $reseller_name, $subject, $body, $is_new){
    $base = acc_base_url();
    $res = mysqli_query($mysqli, "SELECT email, username FROM tbl_admin WHERE admin_type = 3 AND status = 1 AND email <> ''");
    while ($res && $a = mysqli_fetch_assoc($res)) {
        if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) continue;
        $html = acc_email_html($is_new ? 'Novo chamado de suporte' : 'Nova mensagem no chamado',
            array('Revenda: <b>'.support_e($reseller_name).'</b>', 'Assunto: <b>'.support_e($subject).'</b>', nl2br(support_e(mb_substr($body, 0, 1500)))),
            'Responder no painel', $base.'/manage_support.php?id='.(int)$tid);
        acc_mail($a['email'], $a['username'], ($is_new ? 'Novo chamado: ' : 'Chamado #'.(int)$tid.': ').$subject.' - '.brand_name(), $html);
    }
}

function support_notify_reseller($mysqli, $t, $body){
    if (empty($t['reseller_email']) || !filter_var($t['reseller_email'], FILTER_VALIDATE_EMAIL)) return;
    $html = acc_email_html('Seu chamado foi respondido',
        array('Olá, '.support_e($t['reseller_name']).'.', 'Assunto: <b>'.support_e($t['subject']).'</b>', nl2br(support_e(mb_substr($body, 0, 1500)))),
        'Ver no painel', acc_base_url().'/revenda/support.php?id='.(int)$t['id']);
    acc_mail($t['reseller_email'], $t['reseller_name'], 'Resposta: '.$t['subject'].' - '.brand_name(), $html);
}
