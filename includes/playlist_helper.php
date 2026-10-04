<?php
/**
 * Playlists por dispositivo (tbl_users, lida pelo app via get_device_user).
 * O servidor (DNS) é descoberto automaticamente entre os cadastrados em Servidores DNS.
 */

define('PLAYLIST_PROBE_TIMEOUT', 8);

function playlists_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    $columns = array(
        'playlist_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'created_by'    => "VARCHAR(191) NOT NULL DEFAULT ''",
        'dns_checked_at'=> "INT(11) NOT NULL DEFAULT 0",   // última verificação do servidor (failover)
    );
    foreach ($columns as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_users` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) {
            mysqli_query($mysqli, "ALTER TABLE `tbl_users` ADD `$name` $definition");
        }
    }
}

// scheme://host[:porta] em minúsculas, sem barra final
function dns_normalize($url){
    $url = trim((string)$url);
    if ($url == '') return '';
    if (!preg_match('#^https?://#i', $url)) $url = 'http://'.$url;
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return '';
    $scheme = strtolower(isset($p['scheme']) ? $p['scheme'] : 'http');
    $port = isset($p['port']) ? (int)$p['port'] : 0;
    if (($scheme == 'http' && $port == 80) || ($scheme == 'https' && $port == 443)) $port = 0;
    return $scheme.'://'.strtolower($p['host']).($port ? ':'.$port : '');
}

function dns_list_active($mysqli){
    $list = array();
    $res = mysqli_query($mysqli, "SELECT id, dns_title, dns_base FROM tbl_xui_dns WHERE status = '1' ORDER BY id ASC");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $norm = dns_normalize($r['dns_base']);
        if ($norm != '') $list[] = array('id' => $r['id'], 'title' => $r['dns_title'], 'base' => rtrim(trim($r['dns_base']), '/'), 'norm' => $norm);
    }
    return $list;
}

/**
 * Extrai servidor, usuário e senha de uma URL Xtream
 * (get.php / player_api.php?username=..&password=.. ou http://host/live|movie|series/usuario/senha/...).
 */
function playlist_parse_url($url){
    $url = trim((string)$url);
    if (!preg_match('#^https?://#i', $url)) return null;
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return null;
    $user = ''; $pass = '';
    if (!empty($p['query'])) {
        parse_str($p['query'], $q);
        if (!empty($q['username'])) $user = (string)$q['username'];
        if (!empty($q['password'])) $pass = (string)$q['password'];
    }
    if ($user == '' && !empty($p['path']) && preg_match('#/(?:live|movie|series)/([^/]+)/([^/]+)/#i', $p['path'], $m)) {
        $user = urldecode($m[1]); $pass = urldecode($m[2]);
    }
    if ($user == '' || $pass == '') return null;
    return array('server' => dns_normalize($url), 'username' => $user, 'password' => $pass);
}

/**
 * Testa vários DNS ao mesmo tempo (curl_multi). Retorna array indexado como $list com o resultado de cada um.
 */
function playlist_probe_parallel($list, $username, $password, $timeout = PLAYLIST_PROBE_TIMEOUT){
    $mh = curl_multi_init();
    $handles = array();
    foreach ($list as $i => $d) {
        $url = rtrim($d['base'], '/').'/player_api.php?username='.rawurlencode($username).'&password='.rawurlencode($password);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout + 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'IPTVSmartersPlayer',
        ));
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $status == CURLM_OK);

    $results = array();
    foreach ($handles as $i => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $results[$i] = playlist_probe_result($body, $code);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

function playlist_probe_result($body, $code){
    if ($body === false || $body === null || $code == 0 || $code >= 500) return array('result' => 'unreachable', 'exp_date' => null, 'status' => '');
    $json = json_decode($body, true);
    if (is_array($json) && isset($json['user_info']['auth']) && (int)$json['user_info']['auth'] == 1) {
        $exp = isset($json['user_info']['exp_date']) && $json['user_info']['exp_date'] !== null ? (int)$json['user_info']['exp_date'] : null;
        $status = isset($json['user_info']['status']) ? (string)$json['user_info']['status'] : '';
        return array('result' => 'ok', 'exp_date' => $exp, 'status' => $status);
    }
    if (is_array($json) || $code == 401 || $code == 403 || $code == 200) return array('result' => 'auth_fail', 'exp_date' => null, 'status' => '');
    return array('result' => 'unreachable', 'exp_date' => null, 'status' => '');
}

/**
 * Descobre, entre TODOS os DNS cadastrados, qual aceita esse usuário agora.
 * $prefer_norm: servidor a testar primeiro (o da URL colada ou o último que funcionou).
 * Retorna array('ok' => bool, 'msg' => string, 'dns' => item|null, 'probe' => array|null)
 */
function playlist_find_dns($mysqli, $username, $password, $prefer_norm = '', $timeout = PLAYLIST_PROBE_TIMEOUT){
    $list = dns_list_active($mysqli);
    if (!$list) return array('ok' => false, 'msg' => 'Nenhum servidor DNS ativo. Cadastre em Servidores DNS.', 'dns' => null, 'probe' => null);

    $results = playlist_probe_parallel($list, $username, $password, $timeout);
    $winner = null;
    foreach ($list as $i => $d) {
        if ($results[$i]['result'] != 'ok') continue;
        if ($winner === null || $d['norm'] == $prefer_norm) $winner = $i;
        if ($d['norm'] == $prefer_norm) break;
    }
    if ($winner !== null) {
        return array('ok' => true, 'msg' => 'Servidor encontrado: '.$list[$winner]['title'].'.', 'dns' => $list[$winner], 'probe' => $results[$winner]);
    }

    $unreachable = array();
    foreach ($list as $i => $d) { if ($results[$i]['result'] == 'unreachable') $unreachable[] = $d['title']; }
    if ($unreachable) {
        return array('ok' => false, 'msg' => 'Nenhum servidor aceitou este usuário. Sem resposta de: '.implode(', ', $unreachable).'.', 'dns' => null, 'probe' => null);
    }
    return array('ok' => false, 'msg' => 'Usuário ou senha incorretos em todos os servidores DNS cadastrados.', 'dns' => null, 'probe' => null);
}

/**
 * Failover para o app: devolve o DNS que funciona AGORA para esta playlist.
 * Usa cache curto; se o último servidor parar de aceitar (queda ou migração), procura em todos e atualiza.
 */
function playlist_current_dns($mysqli, $row){
    $cache_seconds = 300;
    $now = time();
    if (!empty($row['dns_checked_at']) && ($now - (int)$row['dns_checked_at']) < $cache_seconds) return $row['dns_base'];

    $find = playlist_find_dns($mysqli, $row['user_name'], $row['user_password'], dns_normalize($row['dns_base']), 4);
    $base = $find['ok'] ? $find['dns']['base'] : $row['dns_base'];
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_users SET dns_base = ?, dns_checked_at = ? WHERE id = ?");
    $id = (int)$row['id'];
    // Sem servidor disponível: mantém o último e tenta de novo em 1 minuto
    $checked = $find['ok'] ? $now : $now - $cache_seconds + 60;
    mysqli_stmt_bind_param($stmt, 'sii', $base, $checked, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $base;
}

function playlist_save($mysqli, $device_id, $name, $username, $password, $dns_base, $created_by){
    $now = time();
    $type = 'xui';
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_users (user_type, user_name, user_password, dns_base, device_id, registered_on, status, playlist_name, created_by, dns_checked_at) VALUES (?,?,?,?,?,?,1,?,?,?)");
    $now_s = (string)$now;
    $name = mb_substr(trim(strip_tags((string)$name)), 0, 120);
    $created_by = mb_substr((string)$created_by, 0, 191);
    mysqli_stmt_bind_param($stmt, 'ssssssssi', $type, $username, $password, $dns_base, $device_id, $now_s, $name, $created_by, $now);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function playlist_list($mysqli, $device_id){
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_users WHERE device_id = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, 's', $device_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = array();
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function playlist_delete($mysqli, $playlist_id, $device_id){
    $stmt = mysqli_prepare($mysqli, "DELETE FROM tbl_users WHERE id = ? AND device_id = ?");
    $playlist_id = (int)$playlist_id;
    mysqli_stmt_bind_param($stmt, 'is', $playlist_id, $device_id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Trata o envio do formulário (aba URL ou Account). Usado pelo admin e pelo portal da revenda.
 * Retorna array('ok' => bool, 'msg' => string)
 */
function playlist_handle_submit($mysqli, $device_id, $post, $created_by){
    playlists_migrate($mysqli);
    $mode = (isset($post['mode']) && $post['mode'] == 'url') ? 'url' : 'account';
    $name = isset($post['playlist_name']) ? trim($post['playlist_name']) : '';
    if ($name == '') return array('ok' => false, 'msg' => 'Informe o nome da lista.');

    if ($mode == 'url') {
        $parsed = playlist_parse_url(isset($post['playlist_url']) ? $post['playlist_url'] : '');
        if (!$parsed) return array('ok' => false, 'msg' => 'URL inválida. Use uma URL Xtream com username e password (ex.: http://servidor/get.php?username=...&password=...).');
        // A URL serve só para extrair usuário e senha; o servidor dela é testado primeiro, mas qualquer DNS cadastrado vale
        $username = $parsed['username']; $password = $parsed['password'];
        $find = playlist_find_dns($mysqli, $username, $password, $parsed['server']);
    } else {
        $username = trim(isset($post['username']) ? $post['username'] : '');
        $password = trim(isset($post['password']) ? $post['password'] : '');
        if ($username == '' || $password == '') return array('ok' => false, 'msg' => 'Informe usuário e senha.');
        $find = playlist_find_dns($mysqli, $username, $password);
    }
    if (!$find['ok']) return array('ok' => false, 'msg' => $find['msg']);

    if (!playlist_save($mysqli, $device_id, $name, $username, $password, $find['dns']['base'], $created_by)) {
        return array('ok' => false, 'msg' => 'Erro ao salvar a playlist.');
    }
    $msg = 'Playlist "'.$name.'" adicionada. '.$find['msg'];
    if (!empty($find['probe']['exp_date'])) $msg .= ' Vencimento no servidor: '.date('d/m/Y', $find['probe']['exp_date']).'.';
    return array('ok' => true, 'msg' => $msg);
}
