<?php
/**
 * Serviço para o Media Station X (MSX), o app que roda em TVs Samsung e LG.
 * O MSX monta a tela dele a partir de arquivos JSON que este servidor entrega.
 * O vídeo NÃO passa pelo painel: o MSX toca direto do servidor IPTV. O painel só entrega as listas (como o app de TV).
 */
require_once(__DIR__."/../includes/db_helper.php");
require_once(__DIR__."/../includes/reseller_helper.php");
require_once(__DIR__."/../includes/playlist_helper.php");
require_once(__DIR__."/../includes/net_guard.php");
require_once(__DIR__."/../includes/device_tracker.php");
require_once(__DIR__."/../includes/brand_helper.php");

define('MSX_APP_ID', 'com.iptvbestpro.app');   // mesmo aplicativo dos outros apps de TV (ativação e listas valem igual)
define('MSX_VERSION', '1.0.0');
define('MSX_PAGE', 60);                         // itens por página nas listas grandes
define('MSX_INPUT_PLUGIN', 'http://msx.benzac.de/interaction/input.html');

while (ob_get_level() > 0) ob_end_clean();
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function msx_out($data){
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Resposta de ação de servidor (execute:...): sempre dentro de "response"
function msx_exec($action){
    msx_out(array('response' => array('status' => 200, 'data' => array('action' => $action, 'data' => new stdClass()))));
}

function msx_base(){
    $https = !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = isset($_SERVER['HTTP_HOST']) && preg_match('/^[A-Za-z0-9.\-:]+$/', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'tv.iptvbestpro.com';
    return ($https ? 'https' : 'http').'://'.$host;
}

function msx_logo(){
    return msx_base().'/'.ltrim(brand_logo_url(''), '/');
}

// Identificação do aparelho: o MSX troca {ID} por um código próprio da instalação dele
function msx_key(){
    $id = isset($_GET['id']) ? (string)$_GET['id'] : '';
    return preg_match('/^[A-Za-z0-9_\-]{6,100}$/', $id) ? $id : '';
}

// Link para outra tela do serviço, já com o código do aparelho
function msx_u($route, $key, $extra = array(), $raw = ''){
    $q = array_merge(array('r' => $route, 'id' => $key), $extra);
    return msx_base().'/msx/app.json?'.http_build_query($q).$raw;
}

function msx_state_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_msx_state` (
        `device_id` VARCHAR(191) NOT NULL,
        `pl_id` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`device_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function msx_device_id($key){ return 'msx-'.$key; }

function msx_register($mysqli, $key){
    return devices_register($mysqli, array(
        'device_id' => msx_device_id($key), 'application_id' => MSX_APP_ID, 'app_name' => 'IPTV Best Pro (MSX)',
        'app_version' => 'MSX '.MSX_VERSION, 'platform' => 'msx', 'device_model' => 'Media Station X', 'os_version' => '',
    ));
}

function msx_device($mysqli, $key){
    return devices_find($mysqli, msx_device_id($key), MSX_APP_ID);
}

function msx_playlists($mysqli, $key){
    playlists_migrate($mysqli);
    $did = msx_device_id($key);
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_users WHERE status = '1' AND device_id = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, 's', $did);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = array();
    while ($res && ($r = mysqli_fetch_assoc($res))) if ($r['user_name'] != '') $out[] = $r;
    mysqli_stmt_close($stmt);
    return $out;
}

// Lista em uso neste aparelho: a que o cliente escolheu, ou a mais nova
function msx_current_pl($mysqli, $key){
    msx_state_migrate($mysqli);
    $pls = msx_playlists($mysqli, $key);
    if (!$pls) return null;
    $did = msx_device_id($key);
    $stmt = mysqli_prepare($mysqli, "SELECT pl_id FROM tbl_msx_state WHERE device_id = ?");
    mysqli_stmt_bind_param($stmt, 's', $did);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) foreach ($pls as $p) if ((int)$p['id'] === (int)$row['pl_id']) return $p;
    return $pls[0];
}

function msx_set_pl($mysqli, $key, $pl_id){
    msx_state_migrate($mysqli);
    $did = msx_device_id($key);
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_msx_state (device_id, pl_id, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE pl_id = VALUES(pl_id), updated_at = VALUES(updated_at)");
    mysqli_stmt_bind_param($stmt, 'sii', $did, $pl_id, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function msx_pl_name($p){ return $p['playlist_name'] != '' ? $p['playlist_name'] : $p['user_name']; }

function msx_pl_base($pl){
    $base = rtrim((string)$pl['dns_base'], '/');
    if ($base !== '' && !preg_match('#^https?://#i', $base)) $base = 'http://'.$base;
    return $base;
}

// Pedido à API Xtream do servidor da lista (só a lista: nunca o vídeo). Cache curto, como o app de TV.
function msx_xtream($pl, $action, $params = array(), $ttl = 600){
    $base = msx_pl_base($pl);
    if ($base === '' || !net_url_is_public($base)) return null;
    $q = array('username' => $pl['user_name'], 'password' => $pl['user_password']);
    if ($action !== '') $q['action'] = $action;
    foreach (array('category_id', 'vod_id', 'series_id', 'stream_id') as $k) {
        if (isset($params[$k]) && preg_match('/^[0-9A-Za-z_-]{1,40}$/', (string)$params[$k])) $q[$k] = (string)$params[$k];
    }
    $url = $base.'/player_api.php?'.http_build_query($q);
    $cache_dir = sys_get_temp_dir().'/tvx_cache';
    if (!is_dir($cache_dir)) @mkdir($cache_dir, 0700, true);
    $cache_file = $cache_dir.'/'.md5($pl['id'].'|'.$base.'|'.$pl['user_name'].'|'.$url).'.json';
    if (is_file($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
        $body = file_get_contents($cache_file);
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_TIMEOUT => 45, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'IPTVSmartersPlayer',
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0));
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $pip = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);
        if ($pip !== '' && !net_ip_is_public($pip)) return null;
        if ($body === false || $code >= 400 || $body === '') return null;
        if (json_decode($body) === null) return null;
        @file_put_contents($cache_file, $body, LOCK_EX);
    }
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

// Links de vídeo (tocam direto do servidor IPTV)
function msx_stream_url($pl, $kind, $id, $ext){
    return msx_pl_base($pl).'/'.$kind.'/'.rawurlencode($pl['user_name']).'/'.rawurlencode($pl['user_password']).'/'.rawurlencode((string)$id).'.'.rawurlencode($ext);
}

// Texto sem acento e em minúsculas (busca "acao" acha "Ação")
function msx_norm($x){
    $x = mb_strtolower((string)$x, 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $x);
    return $t !== false ? $t : $x;
}

/* ---------- peças de tela ---------- */

function msx_msg_page($title, $text){
    return array('type' => 'list', 'headline' => $title,
        'template' => array('type' => 'default', 'layout' => '0,0,12,2', 'color' => 'msx-glass'),
        'items' => array(array('title' => $title, 'titleFooter' => $text)));
}

// Lista vertical (categorias, canais, listas...). $items: array de array('label'=>, 'action'=>, 'icon'=>, 'image'=>)
function msx_list_page($headline, $items, $icon = 'chevron-right'){
    $out = array();
    foreach ($items as $i) {
        $it = array('label' => $i['label']);
        if (!empty($i['action'])) $it['action'] = $i['action'];
        $it['icon'] = !empty($i['icon']) ? $i['icon'] : $icon;
        if (!empty($i['image'])) $it['image'] = $i['image'];
        if (!empty($i['title'])) $it['title'] = $i['title'];
        $out[] = $it;
    }
    return array('type' => 'list', 'headline' => $headline,
        'template' => array('type' => 'control', 'layout' => '0,0,12,1', 'color' => 'msx-glass'), 'items' => $out);
}

// Grade de capas (filmes e séries)
function msx_poster_page($headline, $items){
    $out = array();
    foreach ($items as $i) {
        $it = array('title' => $i['title'], 'action' => $i['action']);
        if (!empty($i['image'])) $it['image'] = $i['image'];
        $out[] = $it;
    }
    return array('type' => 'pages', 'headline' => $headline,
        'template' => array('type' => 'separate', 'layout' => '0,0,2,3', 'imageFiller' => 'smart', 'color' => 'msx-glass'), 'items' => $out);
}

// Ação que abre o teclado do MSX e manda o texto digitado para $url (que tem {INPUT})
function msx_input_action($url_with_input, $type, $headline, $hint){
    return 'content:request:interaction:'.$url_with_input.'|'.$type.'|pt|'.str_replace(array('|', '@'), ' ', $headline).'|||'.str_replace(array('|', '@'), ' ', $hint).'@'.MSX_INPUT_PLUGIN;
}


/* ---------- visual: usa o tema do app (marca da revenda, logo, nome, cores, fundo, banner) ---------- */

function msx_hex($v, $def){
    $v = trim((string)$v);
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? strtoupper($v) : $def;
}
function msx_rgb($hex){ return array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))); }
function msx_from_rgb($r, $g, $b){
    return sprintf('#%02X%02X%02X', max(0, min(255, (int)round($r))), max(0, min(255, (int)round($g))), max(0, min(255, (int)round($b))));
}
function msx_mix($a, $b, $t = 0.5){
    $x = msx_rgb($a); $y = msx_rgb($b);
    return msx_from_rgb($x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t);
}
function msx_shade($hex, $f){ return $f < 0 ? msx_mix($hex, '#000000', -$f) : msx_mix($hex, '#FFFFFF', $f); }
function msx_rgba($hex, $a){ $c = msx_rgb($hex); return 'rgba('.$c[0].','.$c[1].','.$c[2].','.$a.')'; }

/**
 * Paleta do app para este aparelho: marca da revenda (se tiver e o admin permitir) ou a marca do admin.
 * Cada modelo de tema (OneUI, Glossy, BestPro...) entrega as cores dele.
 */
function msx_theme($mysqli, $key){
    require_once(__DIR__.'/../includes/theme_helper.php');
    $base_url = msx_base();
    $brand = theme_for_device($mysqli, msx_device_id($key), MSX_APP_ID, $base_url);
    $cfg = $brand ? $brand['theme_config'] : theme_api_payload($mysqli, $base_url);
    $tid = isset($cfg['theme_id']) ? (string)$cfg['theme_id'] : THEME_BESTPRO_ID;
    $m = array_merge(isset($cfg['model_defaults']) && is_array($cfg['model_defaults']) ? $cfg['model_defaults'] : array(),
                     isset($cfg['model_settings']) && is_array($cfg['model_settings']) ? $cfg['model_settings'] : array());
    $g = function ($k, $d) use ($m) { return msx_hex(isset($m[$k]) ? $m[$k] : '', $d); };

    switch ($tid) {
    case '1': // OneUI
        $top = $g('bg_top', '#070923'); $bottom = $g('bg_bottom', '#020309');
        $sec = array('live' => array($g('live_top', '#36D29B'), $g('live_bottom', '#3C56DE')),
                     'movies' => array($g('movies_top', '#DB2E54'), $g('movies_bottom', '#F69D3F')),
                     'series' => array($g('series_top', '#9C3CB3'), $g('series_bottom', '#5396DC')));
        $primary = $g('buttons', '#4DB6AC'); $accent = $sec['movies'][0]; $surface = msx_mix($bottom, '#FFFFFF', 0.10);
        break;
    case '2': // Glossy
        $bottom = $g('bg', '#151B27'); $top = $g('panel_top', '#233245');
        $a = $g('accent_start', '#FD4C3A'); $b = $g('accent_end', '#FF7439');
        $sec = array('live' => array($a, $b), 'movies' => array($b, msx_shade($b, -0.3)), 'series' => array(msx_shade($a, -0.25), $a));
        $primary = $a; $accent = $b; $surface = $g('panel_bottom', '#141A26');
        break;
    case '3': // Black Panther
        $bottom = $g('bg', '#111111'); $top = msx_shade($bottom, 0.08); $acc = $g('accent', '#E22F4D');
        $sec = array('live' => array($acc, msx_shade($acc, -0.35)), 'movies' => array(msx_shade($acc, 0.15), msx_shade($acc, -0.2)), 'series' => array(msx_shade($acc, -0.1), msx_shade($acc, -0.45)));
        $primary = $acc; $accent = $acc; $surface = $g('card', '#404040');
        break;
    case '4': // MovieUI
        $tile = $g('tile_color', '#3A0A10'); $bottom = msx_shade($tile, -0.7); $top = msx_shade($tile, -0.35);
        $sec = array('live' => array('#E50914', '#7A0710'), 'movies' => array('#F59E0B', '#92400E'), 'series' => array('#8B5CF6', '#4C1D95'));
        $primary = '#E50914'; $accent = '#F59E0B'; $surface = $tile;
        break;
    case '5': case '6': case '7':
        $top = $g('bg_top', '#070923'); $bottom = $g('bg_bottom', '#020309');
        $c = array($g('live_color', '#E62E2F'), $g('movies_color', '#00E676'), $g('series_color', '#FF9100'));
        $sec = array('live' => array($c[0], msx_shade($c[0], -0.4)), 'movies' => array($c[1], msx_shade($c[1], -0.4)), 'series' => array($c[2], msx_shade($c[2], -0.4)));
        $primary = $c[0]; $accent = $c[1]; $surface = msx_mix($bottom, '#FFFFFF', 0.10);
        break;
    default: // 8 BestPro
        $bottom = $g('theme_background', '#0B1220'); $top = msx_shade($bottom, 0.10);
        $primary = $g('theme_primary', '#3B6CF6'); $accent = $g('theme_accent', '#22C55E'); $surface = $g('theme_surface', '#131C2E');
        $mid = msx_mix($primary, $accent, 0.5);
        $sec = array('live' => array($primary, msx_shade($primary, -0.4)), 'movies' => array($accent, msx_shade($accent, -0.45)),
                     'series' => array($mid, msx_shade($mid, -0.45)));
    }
    $name = isset($cfg['brand_name']) && $cfg['brand_name'] != '' ? (string)$cfg['brand_name'] : (defined('APP_NAME') ? APP_NAME : 'IPTV Best Pro');
    return array(
        'name' => $name,
        'show_name' => !isset($cfg['show_brand_name']) || $cfg['show_brand_name'] != '0',
        'logo' => !empty($cfg['logo']) ? (string)$cfg['logo'] : msx_logo(),
        'bgimg' => !empty($cfg['background_image']) ? (string)$cfg['background_image'] : '',
        'banner' => !empty($cfg['banner']) ? (string)$cfg['banner'] : '',
        'top' => $top, 'bottom' => $bottom, 'surface' => $surface, 'primary' => $primary, 'accent' => $accent,
        'text' => msx_hex(isset($m['theme_text']) ? $m['theme_text'] : '', '#FFFFFF'), 'sec' => $sec,
    );
}

// Imagens geradas (SVG): fundo, cartões da tela inicial e quadrinho de canal sem logo
function msx_img_url($kind, $params){
    return msx_base().'/msx/img.png?'.http_build_query(array_merge(array('k' => $kind), $params));
}
function msx_bg_url($th){
    return $th['bgimg'] !== '' ? $th['bgimg'] : msx_img_url('bg', array('a' => ltrim($th['top'], '#'), 'b' => ltrim($th['bottom'], '#'), 'p' => ltrim($th['primary'], '#')));
}
