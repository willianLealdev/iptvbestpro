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
        'source'        => "VARCHAR(10) NOT NULL DEFAULT 'account'", // 'url' = servidor fixo da URL | 'account' = DNS automático
        'dns_status'    => "VARCHAR(10) NOT NULL DEFAULT 'online'",  // 'online' | 'offline' (procurando servidor)
        'dns_last_ok'   => "INT(11) NOT NULL DEFAULT 0",
        'dns_server'    => "VARCHAR(191) NOT NULL DEFAULT ''",  // servidor de verdade informado pelo painel IPTV (o DNS pode ser só uma porta)
        'dns_full_at'   => "INT(11) NOT NULL DEFAULT 0",       // última busca completa (lista numa porta: procura o servidor de verdade a cada 6 h)
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

/**
 * DNS ativos usados na busca automática.
 * $scope = 0: só os DNS do admin. $scope = id do revendedor: DNS dele + DNS do admin.
 */
function dns_list_active($mysqli, $scope = 0){
    dns_health_migrate($mysqli);
    $list = array();
    $scope = (int)$scope;
    $where = $scope > 0 ? "(reseller_id = 0 OR reseller_id = $scope OR id IN (SELECT dns_id FROM tbl_dns_share WHERE reseller_id = $scope))" : "reseller_id = 0";
    $res = mysqli_query($mysqli, "SELECT id, dns_title, dns_base, catch_all FROM tbl_xui_dns WHERE status = '1' AND $where ORDER BY (reseller_id = 0) ASC, id ASC");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $base = rtrim(trim(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8')), '/'); // a página de DNS grava com htmlentities
        $norm = dns_normalize($base);
        if ($norm != '') $list[] = array('id' => $r['id'], 'title' => $r['dns_title'], 'base' => $base, 'norm' => $norm, 'catch_all' => (int)$r['catch_all'] == 1);
    }
    return $list;
}

/**
 * Alguns servidores respondem "login aceito" para QUALQUER usuário e senha (até inventados).
 * Eles não servem para descobrir de qual servidor é a lista: ficam por último na escolha.
 * Testa com um usuário inventado no máximo a cada 6 horas por servidor.
 */
function dns_catch_all_refresh($mysqli, $force = false){
    $now = time();
    $where = $force ? "" : " AND catch_checked < ".($now - 21600);
    $res = mysqli_query($mysqli, "SELECT id, dns_base FROM tbl_xui_dns WHERE status = '1'".$where);
    $tasks = array();
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $base = rtrim(trim(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8')), '/');
        if (dns_normalize($base) == '') continue;
        $tasks[(int)$r['id']] = array('base' => $base, 'username' => 'bpx'.bin2hex(random_bytes(5)), 'password' => bin2hex(random_bytes(6)));
    }
    if (!$tasks) return 0;
    $marked = 0;
    foreach (playlist_probe_tasks($tasks, 5, 20) as $id => $probe) {
        if ($probe['result'] == 'unreachable') continue; // fora do ar: testa de novo depois
        $ca = $probe['result'] == 'ok' ? 1 : 0;
        $marked += $ca;
        mysqli_query($mysqli, "UPDATE tbl_xui_dns SET catch_all = $ca, catch_checked = $now WHERE id = ".(int)$id);
    }
    return $marked;
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
 * Executa vários testes player_api ao mesmo tempo, no máximo $concurrency por vez.
 * $tasks: array de array('base' => url, 'username' => .., 'password' => ..). Retorna resultados com as mesmas chaves.
 */
function playlist_probe_tasks($tasks, $timeout = PLAYLIST_PROBE_TIMEOUT, $concurrency = 20){
    $results = array();
    foreach (array_chunk($tasks, max(1, $concurrency), true) as $chunk) {
        $mh = curl_multi_init();
        $handles = array();
        foreach ($chunk as $key => $t) {
            $url = rtrim($t['base'], '/').'/player_api.php?username='.rawurlencode($t['username']).'&password='.rawurlencode($t['password']);
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
            $handles[$key] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $status == CURLM_OK);

        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results[$key] = playlist_probe_result($body, $code);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $results;
}

// Testa um usuário em vários DNS ao mesmo tempo. Retorna resultados indexados como $list.
function playlist_probe_parallel($list, $username, $password, $timeout = PLAYLIST_PROBE_TIMEOUT){
    $tasks = array();
    foreach ($list as $i => $d) $tasks[$i] = array('base' => $d['base'], 'username' => $username, 'password' => $password);
    return playlist_probe_tasks($tasks, $timeout);
}

function playlist_probe_result($body, $code){
    if ($body === false || $body === null || $code == 0 || $code >= 500) return array('result' => 'unreachable', 'exp_date' => null, 'status' => '');
    $json = json_decode($body, true);
    if (is_array($json) && isset($json['user_info']['auth']) && (int)$json['user_info']['auth'] == 1) {
        $exp = isset($json['user_info']['exp_date']) && $json['user_info']['exp_date'] !== null ? (int)$json['user_info']['exp_date'] : null;
        $status = isset($json['user_info']['status']) ? (string)$json['user_info']['status'] : '';
        // servidor de verdade da lista (o que o próprio painel IPTV informa); um DNS "porta" aponta para outro endereço
        $server = '';
        $si = isset($json['server_info']) && is_array($json['server_info']) ? $json['server_info'] : array();
        if (!empty($si['url'])) {
            $proto = !empty($si['server_protocol']) ? strtolower($si['server_protocol']) : 'http';
            $port = $proto == 'https' ? (isset($si['https_port']) ? $si['https_port'] : '') : (isset($si['port']) ? $si['port'] : '');
            $server = dns_normalize($proto.'://'.$si['url'].($port !== '' ? ':'.$port : ''));
        }
        return array('result' => 'ok', 'exp_date' => $exp, 'status' => $status, 'server' => $server);
    }
    if (is_array($json) || $code == 401 || $code == 403 || $code == 200) return array('result' => 'auth_fail', 'exp_date' => null, 'status' => '');
    return array('result' => 'unreachable', 'exp_date' => null, 'status' => '');
}

/**
 * Descobre, entre TODOS os DNS cadastrados, qual aceita esse usuário agora.
 * $prefer_norm: servidor a testar primeiro (o da URL colada ou o último que funcionou).
 * Retorna array('ok' => bool, 'msg' => string, 'dns' => item|null, 'probe' => array|null)
 */
function playlist_find_dns($mysqli, $username, $password, $prefer_norm = '', $timeout = PLAYLIST_PROBE_TIMEOUT, $scope = 0){
    dns_health_migrate($mysqli);
    dns_catch_all_refresh($mysqli); // só testa os servidores que não foram verificados nas últimas 6 horas
    $list = dns_list_active($mysqli, $scope);
    if (!$list) return array('ok' => false, 'msg' => 'Nenhum servidor DNS ativo. Cadastre em Servidores DNS.', 'dns' => null, 'probe' => null);

    $results = playlist_probe_parallel($list, $username, $password, $timeout);
    $winner = null;
    // Ordem de escolha quando mais de um servidor aceita o mesmo usuário:
    //  1º o servidor preferido (o da URL colada / o último que funcionou), se ele for confiável;
    //  2º o servidor "de verdade": o endereço dele é o mesmo que o painel IPTV informa (os outros são só portas para ele);
    //  3º qualquer um que aceite; por último os que aceitam qualquer usuário.
    foreach (array(false, true) as $loose) {
        $pref = null; $native = null; $first = null;
        foreach ($list as $i => $d) {
            if ($results[$i]['result'] != 'ok' || !empty($d['catch_all']) != $loose) continue;
            if ($first === null) $first = $i;
            if ($d['norm'] == $prefer_norm) $pref = $i;
            $srv = isset($results[$i]['server']) ? $results[$i]['server'] : '';
            if ($native === null && $srv != '' && $srv == $d['norm']) $native = $i;
        }
        // o servidor de hoje é só uma porta e existe um cadastrado que é o de verdade: usa o de verdade
        if ($pref !== null && $native !== null && $pref != $native) {
            $sp = isset($results[$pref]['server']) ? $results[$pref]['server'] : '';
            if ($sp != '' && $sp != $list[$pref]['norm']) $pref = $native;
        }
        $winner = $pref !== null ? $pref : ($native !== null ? $native : $first);
        if ($winner !== null) break;
    }
    if ($winner !== null) {
        return array('ok' => true, 'msg' => 'Servidor encontrado: '.$list[$winner]['title'].'.', 'dns' => $list[$winner], 'probe' => $results[$winner]);
    }

    $unreachable = array();
    foreach ($list as $i => $d) { if ($results[$i]['result'] == 'unreachable') $unreachable[] = $d['title']; }
    if ($unreachable) {
        return array('ok' => false, 'msg' => 'Nenhum servidor aceitou este usuário agora (alguns servidores não responderam). Tente novamente em alguns minutos.', 'dns' => null, 'probe' => null);
    }
    return array('ok' => false, 'msg' => 'Usuário ou senha incorretos em todos os servidores DNS cadastrados.', 'dns' => null, 'probe' => null);
}

/**
 * Failover para o app: devolve o DNS que funciona AGORA para esta playlist.
 * Usa cache curto; se o último servidor parar de aceitar (queda ou migração), procura em todos e atualiza.
 */
function playlist_current_dns($mysqli, $row){
    // Playlist cadastrada por URL: o servidor é o da própria URL, sem troca automática
    if (isset($row['source']) && $row['source'] != 'account') return $row['dns_base'];
    // O monitor (a cada 5 min) mantém dns_base atualizado. Só testa aqui se a verificação estiver atrasada.
    $stale_after = 600;
    $now = time();
    if (!empty($row['dns_checked_at']) && ($now - (int)$row['dns_checked_at']) < $stale_after) return $row['dns_base'];

    $find = playlist_find_dns($mysqli, $row['user_name'], $row['user_password'], dns_normalize($row['dns_base']), 4, playlist_dns_scope($mysqli, $row['device_id']));
    $base = $find['ok'] ? $find['dns']['base'] : $row['dns_base'];
    playlist_set_dns_state($mysqli, $row['id'], $base, $find['ok'], $find['ok'] && isset($find['probe']['server']) ? $find['probe']['server'] : null);
    return $base;
}

/* ---------- Saúde dos servidores DNS (bolinha online/offline) ---------- */

function dns_health_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    $columns = array(
        'health'        => "VARCHAR(10) NOT NULL DEFAULT ''",   // '' = nunca verificado | 'online' | 'offline'
        'health_at'     => "INT(11) NOT NULL DEFAULT 0",
        'latency_ms'    => "INT(11) NOT NULL DEFAULT 0",
        'offline_since' => "INT(11) NOT NULL DEFAULT 0",        // desde quando está offline (0 = online)
        'reseller_id'   => "INT(11) NOT NULL DEFAULT 0",        // 0 = DNS do admin | id = DNS exclusivo do revendedor
        'created_at'    => "INT(11) NOT NULL DEFAULT 0",
        'catch_all'     => "TINYINT(1) NOT NULL DEFAULT 0",     // 1 = aceita qualquer usuário/senha (não serve para identificar o servidor da lista)
        'catch_checked' => "INT(11) NOT NULL DEFAULT 0",
    );
    foreach ($columns as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_xui_dns` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) {
            mysqli_query($mysqli, "ALTER TABLE `tbl_xui_dns` ADD `$name` $definition");
        }
    }
    $idx = mysqli_query($mysqli, "SHOW INDEX FROM `tbl_xui_dns` WHERE Key_name = 'idx_dns_reseller'");
    if ($idx && mysqli_num_rows($idx) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_xui_dns` ADD KEY `idx_dns_reseller` (`reseller_id`)");
    }
    // DNS compartilhado: o mesmo servidor existe uma vez só; as outras revendas que usam ficam ligadas a ele
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_dns_share` (
        `dns_id` INT(11) NOT NULL,
        `reseller_id` INT(11) NOT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`dns_id`, `reseller_id`),
        KEY `idx_share_reseller` (`reseller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dns_merge_duplicates($mysqli);
}

// Junta servidores repetidos (mesmo endereço): fica um só e quem tinha a cópia passa a usar o original.
function dns_merge_duplicates($mysqli){
    $res = mysqli_query($mysqli, "SELECT id, dns_base, reseller_id FROM tbl_xui_dns ORDER BY (reseller_id = 0) DESC, id ASC");
    $keep = array();
    $now = time();
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $norm = dns_normalize(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8'));
        if ($norm == '') continue;
        if (!isset($keep[$norm])) { $keep[$norm] = $r; continue; }
        $main = $keep[$norm];
        $dup_id = (int)$r['id'];
        $rid = (int)$r['reseller_id'];
        // revenda dona da cópia passa a usar o servidor que ficou (se ele não for do admin, que já vale para todos)
        if ($rid > 0 && (int)$main['reseller_id'] != $rid && (int)$main['reseller_id'] > 0) {
            mysqli_query($mysqli, "INSERT IGNORE INTO tbl_dns_share (dns_id, reseller_id, created_at) VALUES (".(int)$main['id'].", $rid, $now)");
        }
        // quem estava ligado na cópia passa para o que ficou
        $sh = mysqli_query($mysqli, "SELECT reseller_id FROM tbl_dns_share WHERE dns_id = $dup_id");
        while ($sh && $s = mysqli_fetch_assoc($sh)) {
            if ((int)$s['reseller_id'] != (int)$main['reseller_id'] && (int)$main['reseller_id'] > 0) {
                mysqli_query($mysqli, "INSERT IGNORE INTO tbl_dns_share (dns_id, reseller_id, created_at) VALUES (".(int)$main['id'].", ".(int)$s['reseller_id'].", $now)");
            }
        }
        mysqli_query($mysqli, "DELETE FROM tbl_dns_share WHERE dns_id = $dup_id");
        mysqli_query($mysqli, "DELETE FROM tbl_xui_dns WHERE id = $dup_id");
    }
}

// Procura um servidor já cadastrado com o mesmo endereço (de qualquer dono). $except = id para ignorar (edição).
function dns_find_by_base($mysqli, $base, $except = 0){
    $norm = dns_normalize(html_entity_decode((string)$base, ENT_QUOTES, 'UTF-8'));
    if ($norm == '') return null;
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_xui_dns WHERE id <> ".(int)$except);
    while ($res && $r = mysqli_fetch_assoc($res)) {
        if (dns_normalize(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8')) == $norm) return $r;
    }
    return null;
}

// Nome do servidor de uma playlist (só para mostrar no painel)
function dns_title_for_base($mysqli, $base){
    static $map = null;
    if ($map === null) {
        $map = array();
        $res = mysqli_query($mysqli, "SELECT dns_title, dns_base FROM tbl_xui_dns");
        while ($res && $r = mysqli_fetch_assoc($res)) {
            $n = dns_normalize(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8'));
            if ($n != '' && !isset($map[$n])) $map[$n] = html_entity_decode($r['dns_title'], ENT_QUOTES, 'UTF-8');
        }
    }
    $n = dns_normalize($base);
    if ($n == '') return '';
    if (isset($map[$n])) return $map[$n];
    $h = parse_url($n, PHP_URL_HOST);
    return $h ? $h : $n;
}

define('DNS_OFFLINE_DELETE_DAYS', 15);
define('DNS_MAX_PER_RESELLER', 30);

// Dias restantes até a exclusão automática de um DNS de revendedor offline (null = não está offline)
function dns_days_until_delete($row){
    if (empty($row['offline_since'])) return null;
    $left = DNS_OFFLINE_DELETE_DAYS - floor((time() - (int)$row['offline_since']) / 86400);
    return max(0, (int)$left);
}

/**
 * Exclui DNS de revendedores offline há mais de DNS_OFFLINE_DELETE_DAYS dias.
 * Os DNS do admin nunca são excluídos automaticamente. Retorna quantos foram removidos.
 */
function dns_auto_cleanup($mysqli){
    dns_health_migrate($mysqli);
    $limit = time() - DNS_OFFLINE_DELETE_DAYS * 86400;
    mysqli_query($mysqli, "DELETE FROM tbl_xui_dns WHERE reseller_id > 0 AND offline_since > 0 AND offline_since < $limit");
    return mysqli_affected_rows($mysqli);
}

/* ---------- DNS exclusivos do revendedor ---------- */

function dns_reseller_list($mysqli, $reseller_id){
    dns_health_migrate($mysqli);
    $rows = array();
    $rid = (int)$reseller_id;
    $res = mysqli_query($mysqli, "SELECT d.*, (d.reseller_id = $rid) AS is_owner,
                                     (SELECT COUNT(*) FROM tbl_dns_share s WHERE s.dns_id = d.id) + IF(d.reseller_id > 0, 1, 0) AS users
                                     FROM tbl_xui_dns d
                                     WHERE d.reseller_id = $rid OR d.id IN (SELECT dns_id FROM tbl_dns_share WHERE reseller_id = $rid)
                                     ORDER BY d.id DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

// Retorna array('ok' => bool, 'msg' => string)
function dns_reseller_add($mysqli, $reseller_id, $title, $base){
    dns_health_migrate($mysqli);
    $rid = (int)$reseller_id;
    $title = mb_substr(trim(strip_tags((string)$title)), 0, 120);
    $base = trim((string)$base);
    if ($title == '') return array('ok' => false, 'msg' => 'Informe o nome do servidor.');
    if (!preg_match('#^https?://#i', $base)) $base = 'http://'.$base;
    $norm = dns_normalize($base);
    if ($norm == '' || !filter_var($base, FILTER_VALIDATE_URL)) return array('ok' => false, 'msg' => 'Endereço inválido. Ex.: http://servidor.com:8080');

    foreach (dns_reseller_list($mysqli, $rid) as $d) {
        if (dns_normalize(html_entity_decode($d['dns_base'], ENT_QUOTES, 'UTF-8')) == $norm) return array('ok' => false, 'msg' => 'Este servidor já está na sua lista ("'.html_entity_decode($d['dns_title'], ENT_QUOTES, 'UTF-8').'").');
    }
    if (count(dns_reseller_list($mysqli, $rid)) >= DNS_MAX_PER_RESELLER) return array('ok' => false, 'msg' => 'Limite de '.DNS_MAX_PER_RESELLER.' servidores atingido.');
    // Já existe no sistema (de outra revenda ou do admin): não cria outro, usa o mesmo
    $exists = dns_find_by_base($mysqli, $norm);
    if ($exists) {
        $name = html_entity_decode($exists['dns_title'], ENT_QUOTES, 'UTF-8');
        if ((int)$exists['reseller_id'] == 0) {
            return array('ok' => true, 'msg' => 'Este servidor já está no sistema como "'.$name.'" e já vale para os seus clientes. Não precisa cadastrar de novo.');
        }
        $now = time();
        mysqli_query($mysqli, "INSERT IGNORE INTO tbl_dns_share (dns_id, reseller_id, created_at) VALUES (".(int)$exists['id'].", $rid, $now)");
        return array('ok' => true, 'msg' => 'Este servidor já existia no sistema como "'.$name.'". Ele foi adicionado à sua lista e é compartilhado com quem já usa.');
    }

    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_xui_dns (dns_title, dns_base, status, reseller_id, created_at) VALUES (?, ?, 1, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssii', $title, $norm, $rid, $now);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($ok) dns_health_check_all($mysqli, 4, $rid);
    return $ok ? array('ok' => true, 'msg' => 'Servidor "'.$title.'" cadastrado.') : array('ok' => false, 'msg' => 'Erro ao salvar o servidor.');
}

function dns_reseller_delete($mysqli, $reseller_id, $dns_id){
    $rid = (int)$reseller_id;
    $id = (int)$dns_id;
    if ($rid <= 0) return false;
    mysqli_query($mysqli, "DELETE FROM tbl_dns_share WHERE dns_id = $id AND reseller_id = $rid");
    if (mysqli_affected_rows($mysqli) > 0) return true;
    $next = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT reseller_id FROM tbl_dns_share WHERE dns_id = $id ORDER BY created_at ASC LIMIT 1"));
    if ($next) {
        $nr = (int)$next['reseller_id'];
        mysqli_query($mysqli, "UPDATE tbl_xui_dns SET reseller_id = $nr WHERE id = $id AND reseller_id = $rid");
        $moved = mysqli_affected_rows($mysqli) > 0;
        if ($moved) mysqli_query($mysqli, "DELETE FROM tbl_dns_share WHERE dns_id = $id AND reseller_id = $nr");
        return $moved;
    }
    mysqli_query($mysqli, "DELETE FROM tbl_xui_dns WHERE id = $id AND reseller_id = $rid");
    return mysqli_affected_rows($mysqli) > 0;
}

function dns_reseller_toggle($mysqli, $reseller_id, $dns_id){
    $rid = (int)$reseller_id;
    $id = (int)$dns_id;
    if ($rid <= 0) return false;
    mysqli_query($mysqli, "UPDATE tbl_xui_dns SET status = IF(status = 1, 0, 1) WHERE id = $id AND reseller_id = $rid");
    return mysqli_affected_rows($mysqli) > 0;
}

// Escopo de DNS do aparelho: id do revendedor dono (0 = admin / sem dono)
function playlist_dns_scope($mysqli, $device_id){
    $stmt = mysqli_prepare($mysqli, "SELECT reseller_id FROM tbl_devices WHERE device_id = ? AND owner = 'reseller' ORDER BY id DESC LIMIT 1");
    if (!$stmt) return 0;
    mysqli_stmt_bind_param($stmt, 's', $device_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ? (int)$row['reseller_id'] : 0;
}

/**
 * Testa se cada servidor DNS responde (player_api.php sem login: um servidor no ar devolve resposta,
 * mesmo recusando o acesso). Atualiza health/latency em tbl_xui_dns. Retorna array id => 'online'|'offline'.
 */
function dns_health_check_all($mysqli, $timeout = 5, $only_reseller = null){
    dns_health_migrate($mysqli);
    $rows = array();
    $where = $only_reseller === null ? '' : ' WHERE reseller_id = '.(int)$only_reseller;
    $res = mysqli_query($mysqli, "SELECT id, dns_base FROM tbl_xui_dns".$where);
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[$r['id']] = rtrim(trim(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8')), '/');
    if (!$rows) return array();

    $mh = curl_multi_init();
    $handles = array();
    foreach ($rows as $id => $base) {
        $ch = curl_init($base.'/player_api.php');
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
        $handles[$id] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $status == CURLM_OK);

    $now = time();
    $out = array();
    foreach ($handles as $id => $ch) {
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ms = (int)round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        $online = $code > 0 && $code < 500;
        $out[$id] = $online ? 'online' : 'offline';
        $health = $out[$id];
        $latency = $online ? $ms : 0;
        // offline_since: marca o início da queda; volta a 0 quando o servidor responde
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_xui_dns SET health = ?, health_at = ?, latency_ms = ?,
                                         offline_since = IF(? = 'online', 0, IF(offline_since = 0, ?, offline_since)) WHERE id = ?");
        $iid = (int)$id;
        mysqli_stmt_bind_param($stmt, 'siisii', $health, $now, $latency, $health, $now, $iid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

function playlist_set_dns_state($mysqli, $id, $base, $online, $server = null){
    $now = time();
    $status = $online ? 'online' : 'offline';
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_users SET dns_base = ?, dns_status = ?, dns_checked_at = ?, dns_last_ok = IF(? = 'online', ?, dns_last_ok) WHERE id = ?");
    $id = (int)$id;
    mysqli_stmt_bind_param($stmt, 'ssisii', $base, $status, $now, $status, $now, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($server !== null) {
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_users SET dns_server = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $server, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Monitor (roda a cada 5 minutos em segundo plano): para cada playlist cadastrada por usuário/senha,
 * confere se o servidor atual ainda aceita o login. Se caiu ou o usuário foi migrado,
 * procura entre todos os DNS cadastrados e troca. Sem servidor: fica 'offline' e tenta no próximo ciclo.
 * Retorna array com contadores.
 */
function playlist_monitor_run($mysqli){
    playlists_migrate($mysqli);
    $health = dns_health_check_all($mysqli);
    dns_catch_all_refresh($mysqli);
    $stats = array('checked' => 0, 'online' => 0, 'switched' => 0, 'offline' => 0,
                   'dns_online' => count(array_keys($health, 'online')), 'dns_offline' => count(array_keys($health, 'offline')),
                   'dns_deleted' => dns_auto_cleanup($mysqli));

    // Playlists por usuário/senha + revendedor dono do aparelho (define quais DNS valem para ela)
    $rows = array();
    $res = mysqli_query($mysqli, "SELECT u.id, u.user_name, u.user_password, u.dns_base, u.dns_server, u.dns_full_at, u.device_id,
                                         COALESCE((SELECT d.reseller_id FROM tbl_devices d WHERE d.device_id = u.device_id AND d.owner = 'reseller' ORDER BY d.id DESC LIMIT 1), 0) AS scope
                                  FROM tbl_users u WHERE u.status = '1' AND u.source = 'account'");
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[$r['id']] = $r;
    if (!$rows) return $stats;

    // DNS ativos por escopo (admin = 0, revendedor = id)
    $scopes = array();
    foreach ($rows as $r) {
        $s = (int)$r['scope'];
        if (!isset($scopes[$s])) {
            $scopes[$s] = array();
            foreach (dns_list_active($mysqli, $s) as $d) $scopes[$s][$d['norm']] = $d;
        }
    }

    // 1) Uma consulta por playlist no servidor atual (em grupos paralelos)
    $tasks = array();
    $failed = array();
    foreach ($rows as $id => $r) {
        $norm = dns_normalize($r['dns_base']);
        $allowed = $scopes[(int)$r['scope']];
        if (!isset($allowed[$norm])) { $failed[$id] = $r; continue; } // DNS removido, desligado ou de outro dono
        // servidor atual aceita qualquer usuário: procura de novo o servidor certo da lista
        if (!empty($allowed[$norm]['catch_all'])) { $failed[$id] = $r; continue; }
        $tasks[$id] = array('base' => $allowed[$norm]['base'], 'username' => $r['user_name'], 'password' => $r['user_password']);
    }
    $results = playlist_probe_tasks($tasks, 5, 20);
    foreach ($results as $id => $probe) {
        $stats['checked']++;
        if ($probe['result'] == 'ok') {
            $srv = isset($probe['server']) ? $probe['server'] : '';
            $allowed = $scopes[(int)$rows[$id]['scope']];
            // a lista está numa "porta" (o servidor de verdade é outro): procura o de verdade, no máximo a cada 6 horas
            if ($srv != '' && $srv != dns_normalize($rows[$id]['dns_base']) && (isset($allowed[$srv]) || time() - (int)$rows[$id]['dns_full_at'] > 21600)) {
                mysqli_query($mysqli, "UPDATE tbl_users SET dns_full_at = ".time()." WHERE id = ".(int)$id);
                $failed[$id] = $rows[$id];
                continue;
            }
            playlist_set_dns_state($mysqli, $id, $rows[$id]['dns_base'], true, $srv);
            $stats['online']++;
        } else {
            $failed[$id] = $rows[$id];
        }
    }

    // 2) Falhou: procura em todos os DNS cadastrados
    foreach ($failed as $id => $r) {
        if (!$scopes[(int)$r['scope']]) { playlist_set_dns_state($mysqli, $id, $r['dns_base'], false); $stats['offline']++; continue; }
        $find = playlist_find_dns($mysqli, $r['user_name'], $r['user_password'], dns_normalize($r['dns_base']), 5, (int)$r['scope']);
        if ($find['ok']) {
            $switched = dns_normalize($find['dns']['base']) != dns_normalize($r['dns_base']);
            playlist_set_dns_state($mysqli, $id, $find['dns']['base'], true, isset($find['probe']['server']) ? $find['probe']['server'] : '');
            $stats[$switched ? 'switched' : 'online']++;
        } else {
            playlist_set_dns_state($mysqli, $id, $r['dns_base'], false);
            $stats['offline']++;
        }
    }
    return $stats;
}

function playlist_save($mysqli, $device_id, $name, $username, $password, $dns_base, $created_by, $source = 'account'){
    $now = time();
    $type = 'xui';
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_users (user_type, user_name, user_password, dns_base, device_id, registered_on, status, playlist_name, created_by, dns_checked_at, source) VALUES (?,?,?,?,?,?,1,?,?,?,?)");
    $now_s = (string)$now;
    $name = mb_substr(trim(strip_tags((string)$name)), 0, 120);
    $created_by = mb_substr((string)$created_by, 0, 191);
    $source = in_array($source, array('url', 'manual')) ? $source : 'account';
    mysqli_stmt_bind_param($stmt, 'ssssssssis', $type, $username, $password, $dns_base, $device_id, $now_s, $name, $created_by, $now, $source);
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
    $mode = (isset($post['mode']) && in_array($post['mode'], array('url', 'account_dns'))) ? $post['mode'] : 'account';
    $name = isset($post['playlist_name']) ? trim($post['playlist_name']) : '';
    if ($name == '') return array('ok' => false, 'msg' => 'Informe o nome da lista.');

    if ($mode == 'url') {
        // URL: o servidor já vem na própria URL; salva direto, sem verificar DNS
        $parsed = playlist_parse_url(isset($post['playlist_url']) ? $post['playlist_url'] : '');
        if (!$parsed) return array('ok' => false, 'msg' => 'URL inválida. Use uma URL Xtream com username e password (ex.: http://servidor/get.php?username=...&password=...).');
        if (!playlist_save($mysqli, $device_id, $name, $parsed['username'], $parsed['password'], $parsed['server'], $created_by, 'url')) {
            return array('ok' => false, 'msg' => 'Erro ao salvar a playlist.');
        }
        return array('ok' => true, 'msg' => 'Playlist "'.$name.'" adicionada.');
    }

    // Account com DNS informado pelo cliente final: servidor fixo, sem busca automática
    if ($mode == 'account_dns' && strpos((string)$created_by, 'cliente:') !== 0) $mode = 'account'; // só o cliente final informa o DNS
    if ($mode == 'account_dns') {
        $dns = dns_normalize(isset($post['dns']) ? $post['dns'] : '');
        $username = trim(isset($post['username']) ? $post['username'] : '');
        $password = trim(isset($post['password']) ? $post['password'] : '');
        if ($dns == '') return array('ok' => false, 'msg' => 'Informe o DNS do servidor (ex.: http://servidor.com:8080).');
        if ($username == '' || $password == '') return array('ok' => false, 'msg' => 'Informe usuário e senha.');
        $probe = playlist_probe_parallel(array(array('base' => $dns)), $username, $password, 6);
        if ($probe[0]['result'] == 'auth_fail') return array('ok' => false, 'msg' => 'O servidor respondeu, mas recusou este usuário e senha. Confira os dados.');
        if (!playlist_save($mysqli, $device_id, $name, $username, $password, $dns, $created_by, 'manual')) {
            return array('ok' => false, 'msg' => 'Erro ao salvar a playlist.');
        }
        return array('ok' => true, 'msg' => 'Playlist "'.$name.'" adicionada.'.($probe[0]['result'] == 'unreachable' ? ' Atenção: o servidor não respondeu agora; confira o DNS se a lista não abrir.' : ''));
    }

    // Account: procura sozinho qual DNS cadastrado aceita este usuário
    $username = trim(isset($post['username']) ? $post['username'] : '');
    $password = trim(isset($post['password']) ? $post['password'] : '');
    if ($username == '' || $password == '') return array('ok' => false, 'msg' => 'Informe usuário e senha.');
    $find = playlist_find_dns($mysqli, $username, $password, '', PLAYLIST_PROBE_TIMEOUT, playlist_dns_scope($mysqli, $device_id));
    if (!$find['ok']) return array('ok' => false, 'msg' => $find['msg']);

    if (!playlist_save($mysqli, $device_id, $name, $username, $password, $find['dns']['base'], $created_by, 'account')) {
        return array('ok' => false, 'msg' => 'Erro ao salvar a playlist.');
    }
    // guarda o servidor de verdade informado pelo painel IPTV (aparece no painel embaixo do nome da playlist)
    if (!empty($find['probe']['server'])) {
        $srv = $find['probe']['server'];
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_users SET dns_server = ? WHERE device_id = ? AND user_name = ? ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'sss', $srv, $device_id, $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    return array('ok' => true, 'msg' => 'Playlist "'.$name.'" adicionada. Servidor encontrado automaticamente.');
}
