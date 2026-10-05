<?php
/**
 * Ponte da lista para o app de TV (LG webOS / Samsung Tizen).
 * Apps de TV feitos em HTML não conseguem ler a API dos servidores IPTV direto (CORS).
 * Aqui o painel busca SÓ a lista (categorias, canais, filmes, séries, guia) — nunca o vídeo —
 * e somente para uma lista que pertence ao próprio aparelho, que precisa estar ativo.
 * Respostas ficam em cache curto para pesar pouco no servidor.
 */
require_once("includes/db_helper.php");
require_once("includes/reseller_helper.php");
require_once("includes/playlist_helper.php");

while (ob_get_level() > 0) ob_end_clean();
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function tvx_out($code, $arr){ http_response_code($code); echo json_encode($arr); exit; }

// O app manda o pedido como texto JSON no campo "q" (assim a TV não precisa de pré-checagem CORS)
$in = isset($_POST['q']) ? json_decode((string)$_POST['q'], true) : json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
$device_id = isset($in['device_id']) ? substr(trim((string)$in['device_id']), 0, 191) : '';
$app_id = isset($in['application_id']) ? substr(trim((string)$in['application_id']), 0, 191) : 'com.iptvbestpro.app';
$pl_id = isset($in['playlist_id']) ? (int)$in['playlist_id'] : 0;
$action = isset($in['action']) ? (string)$in['action'] : '';
$params = isset($in['params']) && is_array($in['params']) ? $in['params'] : array();

$allowed = array('' => 60, 'get_live_categories' => 600, 'get_live_streams' => 600, 'get_vod_categories' => 600, 'get_vod_streams' => 600,
                 'get_vod_info' => 3600, 'get_series_categories' => 600, 'get_series' => 600, 'get_series_info' => 1800, 'get_short_epg' => 120);
if ($device_id === '' || $pl_id <= 0 || !array_key_exists($action, $allowed)) tvx_out(400, array('error' => 'bad_request'));

// Aparelho precisa existir e estar ativo
$device = devices_find($mysqli, $device_id, $app_id);
if (!$device || devices_app_status($device) !== 'active') tvx_out(403, array('error' => 'device_not_active'));

// A lista precisa ser deste aparelho
playlists_migrate($mysqli);
$stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_users WHERE id = ? AND device_id = ? AND status = '1'");
mysqli_stmt_bind_param($stmt, 'is', $pl_id, $device_id);
mysqli_stmt_execute($stmt);
$pl = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$pl) tvx_out(404, array('error' => 'playlist_not_found'));

$base = rtrim(playlist_current_dns($mysqli, $pl), '/');
if (!preg_match('#^https?://#i', $base)) $base = 'http://'.$base;

// Só parâmetros conhecidos da API Xtream
$q = array('username' => $pl['user_name'], 'password' => $pl['user_password']);
if ($action !== '') $q['action'] = $action;
foreach (array('category_id', 'vod_id', 'series_id', 'stream_id', 'limit') as $k) {
    if (isset($params[$k]) && preg_match('/^[0-9A-Za-z_-]{1,40}$/', (string)$params[$k])) $q[$k] = (string)$params[$k];
}
$url = $base.'/player_api.php?'.http_build_query($q);

// Cache curto (por lista + ação + parâmetros)
$cache_dir = sys_get_temp_dir().'/tvx_cache';
if (!is_dir($cache_dir)) @mkdir($cache_dir, 0700, true);
$cache_file = $cache_dir.'/'.md5($pl['id'].'|'.$base.'|'.$pl['user_name'].'|'.$url).'.json';
$ttl = $allowed[$action];
if (is_file($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
    $body = file_get_contents($cache_file);
} else {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_TIMEOUT => 45, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'IPTVSmartersPlayer',
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400 || $body === '') tvx_out(502, array('error' => 'server_unreachable'));
    if (json_decode($body) === null) tvx_out(502, array('error' => 'invalid_response'));
    @file_put_contents($cache_file, $body, LOCK_EX);
}

// Login: inclui o endereço da lista (o app usa para montar o link do vídeo se o servidor não informar)
if ($action === '') {
    $j = json_decode($body, true);
    if (is_array($j)) { $j['_dns'] = $base; $body = json_encode($j); }
}
echo $body;
