<?php
/**
 * Alertas por e-mail quando um servidor DNS cai (e quando volta).
 *  - DNS do admin (reseller_id = 0): e-mail para os e-mails cadastrados no alerta (lista vazia = Super Admins).
 *  - DNS de revendedor: e-mail para o revendedor dono e uma cópia para o admin (se ligado).
 *  - Todas as revendas que usam o servidor também recebem: as que compartilham o DNS e as que tinham
 *    clientes nele nos últimos dias (inclusive em DNS do admin) — ver dns_users_resellers().
 *  - Clientes finais não recebem.
 * Para evitar alarme falso, só avisa depois de DNS_ALERT_MINUTES offline seguidos (o monitor roda a cada 5 min).
 * Cada queda gera 1 e-mail de "offline" e 1 de "voltou"; os e-mails de um ciclo são agrupados por destinatário.
 */
require_once(__DIR__."/reseller_helper.php");
require_once(__DIR__."/playlist_helper.php");
require_once(__DIR__."/account_auth.php");

function dns_alert_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    dns_health_migrate($mysqli);
    foreach (array('alert_state' => "VARCHAR(10) NOT NULL DEFAULT ''", 'alert_at' => "INT(11) NOT NULL DEFAULT 0") as $name => $def) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_xui_dns` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_xui_dns` ADD `$name` $def");
    }
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_resellers` LIKE 'dns_alerts'");
    if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_resellers` ADD `dns_alerts` TINYINT(1) NOT NULL DEFAULT 1");
}

function dns_alert_cfg($mysqli){
    return array(
        'enabled'  => panel_config_get($mysqli, 'dns_alert_enabled', '1') == '1',
        'copy'     => panel_config_get($mysqli, 'dns_alert_copy_resellers', '1') == '1',
        'emails'   => trim(panel_config_get($mysqli, 'dns_alert_emails', '')),
        'minutes'  => max(5, (int)panel_config_get($mysqli, 'dns_alert_minutes', '10')),
    );
}

// Textos do e-mail no idioma de quem recebe
function dns_alert_t($lang, $key){
    static $t = array(
        'down_subject_1' => array('pt' => 'Servidor DNS offline: {0}', 'en' => 'DNS server offline: {0}', 'es' => 'Servidor DNS offline: {0}'),
        'down_subject_n' => array('pt' => '{0} servidores DNS offline', 'en' => '{0} DNS servers offline', 'es' => '{0} servidores DNS offline'),
        'down_title'     => array('pt' => 'Servidor DNS fora do ar', 'en' => 'DNS server is down', 'es' => 'Servidor DNS fuera de línea'),
        'down_intro'     => array('pt' => 'Os servidores abaixo pararam de responder:', 'en' => 'The servers below stopped responding:', 'es' => 'Los servidores de abajo dejaron de responder:'),
        'down_tip'       => array('pt' => 'As playlists cadastradas por usuário e senha tentam outro servidor ativo automaticamente. Verifique o servidor com o seu provedor.', 'en' => 'Playlists added by username and password automatically try another active server. Check the server with your provider.', 'es' => 'Las playlists registradas por usuario y contraseña prueban automáticamente otro servidor activo. Revisa el servidor con tu proveedor.'),
        'down_delete'    => array('pt' => 'Servidores de revendedor que ficarem offline por mais de {0} dias seguidos são excluídos automaticamente.', 'en' => 'Reseller servers offline for more than {0} days in a row are deleted automatically.', 'es' => 'Los servidores de revendedor offline por más de {0} días seguidos se eliminan automáticamente.'),
        'up_subject_1'   => array('pt' => 'Servidor DNS voltou: {0}', 'en' => 'DNS server is back: {0}', 'es' => 'Servidor DNS volvió: {0}'),
        'up_subject_n'   => array('pt' => '{0} servidores DNS voltaram', 'en' => '{0} DNS servers are back', 'es' => '{0} servidores DNS volvieron'),
        'up_title'       => array('pt' => 'Servidor DNS voltou a funcionar', 'en' => 'DNS server is working again', 'es' => 'Servidor DNS volvió a funcionar'),
        'up_intro'       => array('pt' => 'Os servidores abaixo voltaram a responder:', 'en' => 'The servers below are responding again:', 'es' => 'Los servidores de abajo volvieron a responder:'),
        'since'          => array('pt' => 'offline desde {0}', 'en' => 'offline since {0}', 'es' => 'offline desde {0}'),
        'was_off'        => array('pt' => 'ficou offline por {0}', 'en' => 'was offline for {0}', 'es' => 'estuvo offline por {0}'),
        'reseller'       => array('pt' => 'Revendedor: {0}', 'en' => 'Reseller: {0}', 'es' => 'Revendedor: {0}'),
        'admin_copy'     => array('pt' => 'Cópia para o administrador (servidores de revendedores).', 'en' => 'Copy for the administrator (reseller servers).', 'es' => 'Copia para el administrador (servidores de revendedores).'),
        'button'         => array('pt' => 'Ver servidores', 'en' => 'View servers', 'es' => 'Ver servidores'),
        'hello'          => array('pt' => 'Olá, {0}!', 'en' => 'Hi, {0}!', 'es' => '¡Hola, {0}!'),
        'footer'         => array('pt' => 'Você recebe este aviso porque os alertas de DNS estão ligados. Para desligar, acesse a página de servidores DNS no painel.', 'en' => 'You get this notice because DNS alerts are on. To turn them off, open the DNS servers page in the panel.', 'es' => 'Recibes este aviso porque las alertas de DNS están activas. Para desactivarlas, abre la página de servidores DNS en el panel.'),
    );
    if (!in_array($lang, array('pt', 'en', 'es'))) $lang = 'pt';
    return $t[$key][$lang];
}

function dns_alert_fmt($str, $args){
    foreach ((array)$args as $i => $a) $str = str_replace('{'.$i.'}', $a, $str);
    return $str;
}

function dns_alert_duration($sec, $lang){
    $sec = max(60, (int)$sec);
    $u = array('pt' => array('min', 'h', 'dia(s)'), 'en' => array('min', 'h', 'day(s)'), 'es' => array('min', 'h', 'día(s)'));
    $u = isset($u[$lang]) ? $u[$lang] : $u['pt'];
    if ($sec < 3600) return floor($sec / 60).' '.$u[0];
    if ($sec < 86400) return floor($sec / 3600).' '.$u[1].' '.floor(($sec % 3600) / 60).' '.$u[0];
    return floor($sec / 86400).' '.$u[2];
}

/**
 * $to: array(email, name, lang, link)
 * $down / $up: listas de servidores (title, base, offline_since, reseller)
 */
function dns_alert_send($to, $down, $up, $is_admin_copy = false){
    $lang = $to['lang'];
    $sent = 0;
    foreach (array('down' => $down, 'up' => $up) as $kind => $list) {
        if (!$list) continue;
        $items = '';
        foreach ($list as $d) {
            $info = $kind == 'down'
                ? dns_alert_fmt(dns_alert_t($lang, 'since'), array(date('d/m/Y H:i', $d['offline_since'])))
                : dns_alert_fmt(dns_alert_t($lang, 'was_off'), array(dns_alert_duration(time() - $d['offline_since'], $lang)));
            $color = $kind == 'down' ? '#dc2626' : '#16a34a';
            $items .= '<div style="border:1px solid #e5e9f2;border-left:4px solid '.$color.';border-radius:10px;padding:12px 14px;margin:0 0 10px;">'
                .'<div style="font-weight:700;color:#1f2a44;font-size:15px;">'.htmlspecialchars($d['title']).'</div>'
                .'<div style="color:#5b6680;font-size:13px;word-break:break-all;">'.htmlspecialchars($d['base']).'</div>'
                .($d['reseller'] !== '' ? '<div style="color:#5b6680;font-size:13px;">'.htmlspecialchars(dns_alert_fmt(dns_alert_t($lang, 'reseller'), array($d['reseller']))).'</div>' : '')
                .'<div style="color:'.$color.';font-size:13px;font-weight:600;margin-top:4px;">'.htmlspecialchars($info).'</div></div>';
        }
        $n = count($list);
        $subject = $n == 1 ? dns_alert_fmt(dns_alert_t($lang, $kind.'_subject_1'), array($list[0]['title'])) : dns_alert_fmt(dns_alert_t($lang, $kind.'_subject_n'), array($n));
        $paras = array(htmlspecialchars(dns_alert_fmt(dns_alert_t($lang, 'hello'), array($to['name']))), htmlspecialchars(dns_alert_t($lang, $kind.'_intro')), $items);
        if ($kind == 'down') {
            $paras[] = htmlspecialchars(dns_alert_t($lang, 'down_tip'));
            // aviso de exclusão automática: só vale para servidores de revenda (os do admin nunca são excluídos)
            $has_reseller_dns = false;
            foreach ($list as $it) if (!empty($it['owner'])) $has_reseller_dns = true;
            if ($has_reseller_dns && ($is_admin_copy || $to['type'] == 'reseller')) $paras[] = htmlspecialchars(dns_alert_fmt(dns_alert_t($lang, 'down_delete'), array(DNS_OFFLINE_DELETE_DAYS)));
        }
        $note = ($is_admin_copy ? htmlspecialchars(dns_alert_t($lang, 'admin_copy')).'<br>' : '').htmlspecialchars(dns_alert_t($lang, 'footer'));
        $html = acc_email_html(dns_alert_t($lang, $kind.'_title'), $paras, dns_alert_t($lang, 'button'), $to['link'], $note);
        if (acc_mail($to['email'], $to['name'], ($kind == 'down' ? '⚠️ ' : '✅ ').$subject.' - '.brand_name(), $html)) $sent++;
    }
    return $sent;
}

// Destinatários do admin: os e-mails cadastrados no alerta; só quando a lista está vazia vai para os Super Admins
function dns_alert_admin_recipients($mysqli, $cfg){
    $list = array();
    $base = acc_base_url();
    foreach (preg_split('/[\s,;]+/', $cfg['emails']) as $e) {
        $e = trim($e);
        if ($e != '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $list[strtolower($e)] = array('type' => 'admin', 'email' => $e, 'name' => brand_name(), 'lang' => 'pt', 'link' => $base.'/manage_dns.php');
        }
    }
    if ($list) return array_values($list);
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_admin WHERE admin_type = 3 AND status = 1 AND email <> ''");
    while ($res && $a = mysqli_fetch_assoc($res)) {
        if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) continue;
        $list[strtolower($a['email'])] = array('type' => 'admin', 'email' => $a['email'], 'name' => $a['username'], 'lang' => isset($a['lang']) && $a['lang'] != '' ? $a['lang'] : 'pt', 'link' => $base.'/manage_dns.php');
    }
    return array_values($list);
}

/**
 * Chamado pelo monitor (a cada 5 min) depois de verificar os servidores. Retorna quantos e-mails saíram.
 */
function dns_alerts_run($mysqli){
    dns_alert_migrate($mysqli);
    if (function_exists('dns_health_migrate')) dns_health_migrate($mysqli); // garante a tabela de DNS compartilhado
    acc_migrate($mysqli);
    $cfg = dns_alert_cfg($mysqli);
    $now = time();
    $wait = $cfg['minutes'] * 60;

    $down = array(); $up = array(); $ids_down = array(); $ids_up = array();
    $res = mysqli_query($mysqli, "SELECT d.*, r.name AS reseller_name FROM tbl_xui_dns d LEFT JOIN tbl_resellers r ON r.id = d.reseller_id WHERE d.status = '1'");
    while ($res && $d = mysqli_fetch_assoc($res)) {
        $item = array('id' => (int)$d['id'], 'owner' => (int)$d['reseller_id'], 'title' => $d['dns_title'], 'base' => rtrim(html_entity_decode($d['dns_base'], ENT_QUOTES, 'UTF-8'), '/'),
                      'offline_since' => (int)$d['offline_since'], 'reseller' => (int)$d['reseller_id'] > 0 ? (string)$d['reseller_name'] : '');
        $scope = (int)$d['reseller_id'];
        // Todos que usam o servidor recebem o aviso (1 e-mail por pessoa, mesmo que use de vários jeitos):
        //  - o dono (admin ou revenda) e as revendas que compartilham o mesmo DNS;
        //  - as revendas com clientes nesse servidor nos últimos dias (inclusive em DNS do admin).
        //    A lista é guardada antes da troca automática, então quem foi trocado para outro servidor também recebe.
        $scopes_of = array($scope);
        $sh = mysqli_query($mysqli, "SELECT reseller_id FROM tbl_dns_share WHERE dns_id = ".(int)$d['id']);
        while ($sh && $x = mysqli_fetch_assoc($sh)) $scopes_of[] = (int)$x['reseller_id'];
        if (function_exists('dns_users_resellers')) $scopes_of = array_merge($scopes_of, dns_users_resellers($mysqli, $d['id']));
        $scopes_of = array_values(array_unique(array_map('intval', $scopes_of)));
        if ($d['health'] == 'offline' && (int)$d['offline_since'] > 0 && $now - (int)$d['offline_since'] >= $wait && $d['alert_state'] != 'offline') {
            foreach ($scopes_of as $sc) $down[$sc][] = $item;
            $ids_down[] = (int)$d['id'];
        } else if ($d['health'] == 'online' && $d['alert_state'] == 'offline') {
            $item['offline_since'] = (int)$d['alert_at'] > 0 ? (int)$d['alert_at'] : $now - $wait; // alert_at guarda o início da queda
            foreach ($scopes_of as $sc) $up[$sc][] = $item;
            $ids_up[] = (int)$d['id'];
        }
    }
    // marca primeiro (evita repetir o mesmo aviso se o envio demorar ou falhar)
    if ($ids_down) mysqli_query($mysqli, "UPDATE tbl_xui_dns SET alert_state = 'offline', alert_at = offline_since WHERE id IN (".implode(',', $ids_down).")");
    if ($ids_up) mysqli_query($mysqli, "UPDATE tbl_xui_dns SET alert_state = '', alert_at = $now WHERE id IN (".implode(',', $ids_up).")");
    if (!$cfg['enabled'] || (!$down && !$up)) return 0;

    $sent = 0;
    $admins = dns_alert_admin_recipients($mysqli, $cfg);
    $base = acc_base_url();
    $scopes = array_unique(array_merge(array_keys($down), array_keys($up)));
    $copy_down = array(); $copy_up = array();
    foreach ($scopes as $scope) {
        $d = isset($down[$scope]) ? $down[$scope] : array();
        $u = isset($up[$scope]) ? $up[$scope] : array();
        if ($scope == 0) {
            foreach ($admins as $a) $sent += dns_alert_send($a, $d, $u);
            continue;
        }
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_resellers WHERE id = ".(int)$scope));
        if ($r && (int)$r['status'] == 1 && (!isset($r['email_verified']) || (int)$r['email_verified'] == 1) && (int)$r['dns_alerts'] == 1 && filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
            $sent += dns_alert_send(array('type' => 'reseller', 'email' => $r['email'], 'name' => $r['name'], 'lang' => isset($r['lang']) && $r['lang'] != '' ? $r['lang'] : 'pt', 'link' => $base.'/revenda/dns.php'), $d, $u);
        }
        // cópia para o admin: só servidores das revendas (os do admin ele já recebeu direto), sem repetir
        foreach ($d as $it) if ($it['owner'] > 0) $copy_down[$it['id']] = $it;
        foreach ($u as $it) if ($it['owner'] > 0) $copy_up[$it['id']] = $it;
    }
    $copy_down = array_values($copy_down);
    $copy_up = array_values($copy_up);
    if ($cfg['copy'] && ($copy_down || $copy_up)) {
        foreach ($admins as $a) {
            $a['link'] = $base.'/manage_dns_resellers.php';
            $sent += dns_alert_send($a, $copy_down, $copy_up, true);
        }
    }
    return $sent;
}
