<?php
/**
 * Envio de e-mail pela API da Resend (https://resend.com).
 * Configurado em Settings → Admin Settings → SMTP Settings (opção "Resend API").
 */

define('RESEND_API_URL', 'https://api.resend.com/emails');

function resend_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    $columns = array(
        'resend_api_key'   => "TEXT NULL",
        'resend_from'      => "VARCHAR(191) NOT NULL DEFAULT ''",
        'resend_from_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
    );
    foreach ($columns as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_smtp_settings` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) {
            mysqli_query($mysqli, "ALTER TABLE `tbl_smtp_settings` ADD `$name` $definition");
        }
    }
}

// Guarda os últimos 15 envios (data, destino, provedor, resultado, erro) em tbl_panel_config
function mail_log_result($mysqli, $provider, $to, $subject, $ok, $error = '', $id = ''){
    if (!$mysqli) return;
    $res = @mysqli_query($mysqli, "SELECT value FROM tbl_panel_config WHERE name = 'mail_log'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $log = $row ? json_decode($row['value'], true) : array();
    if (!is_array($log)) $log = array();
    array_unshift($log, array('t' => time(), 'p' => $provider, 'to' => mb_substr((string)$to, 0, 120), 's' => mb_substr((string)$subject, 0, 120),
                              'ok' => $ok ? 1 : 0, 'e' => mb_substr((string)$error, 0, 300), 'id' => mb_substr((string)$id, 0, 80)));
    $json = json_encode(array_slice($log, 0, 15), JSON_UNESCAPED_UNICODE);
    $stmt = @mysqli_prepare($mysqli, "INSERT INTO tbl_panel_config (name, value) VALUES ('mail_log', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, 's', $json); @mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
}

function mail_log_list($mysqli){
    $res = @mysqli_query($mysqli, "SELECT value FROM tbl_panel_config WHERE name = 'mail_log'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $log = $row ? json_decode($row['value'], true) : array();
    return is_array($log) ? $log : array();
}

// Mostra só o final da chave (nunca a chave inteira na tela)
function resend_mask_key($key){
    $key = (string)$key;
    if ($key == '') return '';
    return str_repeat('•', 8).substr($key, -4);
}

/**
 * Envia um e-mail HTML. Retorna array('ok' => bool, 'id' => string, 'error' => string)
 */
function resend_send($api_key, $from_email, $from_name, $to_email, $to_name, $subject, $html){
    if ($api_key == '' || $from_email == '') {
        return array('ok' => false, 'id' => '', 'error' => 'Resend não configurada (API Key ou e-mail remetente vazio).');
    }
    $from = $from_name != '' ? sprintf('%s <%s>', str_replace(array('<', '>', '"'), '', $from_name), $from_email) : $from_email;
    $to = $to_name != '' ? sprintf('%s <%s>', str_replace(array('<', '>', '"'), '', $to_name), $to_email) : $to_email;

    $payload = json_encode(array(
        'from'     => $from,
        'to'       => array($to),
        'subject'  => (string)$subject,
        'html'     => (string)$html,
        'reply_to' => $from_email,
    ), JSON_UNESCAPED_UNICODE);

    $ch = curl_init(RESEND_API_URL);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$api_key,
            'Content-Type: application/json',
        ),
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($body === false) return array('ok' => false, 'id' => '', 'error' => 'Falha de conexão com a Resend: '.$curl_error);
    $json = json_decode($body, true);
    if ($code >= 200 && $code < 300 && !empty($json['id'])) return array('ok' => true, 'id' => $json['id'], 'error' => '');

    $msg = is_array($json) && !empty($json['message']) ? $json['message'] : 'HTTP '.$code;
    error_log('[resend] '.$code.' '.$body);
    return array('ok' => false, 'id' => '', 'error' => 'Resend recusou o envio: '.$msg);
}
