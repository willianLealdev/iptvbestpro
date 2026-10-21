<?php
/**
 * Filmes e episódios para o app de TV que roda em página https (navegador, Windows, Titan, Whale).
 * O servidor IPTV redireciona o vídeo para um endereço http que o navegador bloqueia (conteúdo misto):
 * aqui o painel busca o vídeo (seguindo os redirecionamentos) e entrega por https, com suporte a Range (avançar/voltar).
 * Só para lista do próprio aparelho, que precisa estar ativo. O endereço é montado aqui (DNS + usuário + senha guardados),
 * o app nunca informa o destino.
 */
require_once("includes/db_helper.php");
require_once("includes/reseller_helper.php");
require_once("includes/playlist_helper.php");

while (ob_get_level() > 0) ob_end_clean();
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

function tvv_fail($code, $msg){ http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); echo $msg; exit; }

$device_id = isset($_GET['d']) ? substr(trim((string)$_GET['d']), 0, 191) : '';
$app_id = isset($_GET['a']) ? substr(trim((string)$_GET['a']), 0, 191) : 'com.iptvbestpro.app';
$pl_id = isset($_GET['p']) ? (int)$_GET['p'] : 0;
$type = isset($_GET['t']) ? (string)$_GET['t'] : '';
$vid = isset($_GET['i']) ? (string)$_GET['i'] : '';
$ext = isset($_GET['e']) ? strtolower((string)$_GET['e']) : 'mp4';
if ($device_id === '' || $pl_id <= 0 || !in_array($type, array('movie', 'series'), true) || !preg_match('/^[0-9A-Za-z_-]{1,40}$/', $vid) || !preg_match('/^[a-z0-9]{2,5}$/', $ext)) tvv_fail(400, 'bad_request');

$device = devices_find($mysqli, $device_id, $app_id);
if (!$device || devices_app_status($device) !== 'active') tvv_fail(403, 'device_not_active');

playlists_migrate($mysqli);
$stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_users WHERE id = ? AND device_id = ? AND status = '1'");
mysqli_stmt_bind_param($stmt, 'is', $pl_id, $device_id);
mysqli_stmt_execute($stmt);
$pl = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$pl) tvv_fail(404, 'playlist_not_found');
mysqli_close($mysqli);

$base = rtrim((string)$pl['dns_base'], '/');
if (!preg_match('#^https?://#i', $base)) $base = 'http://'.$base;
$url = $base.'/'.$type.'/'.rawurlencode($pl['user_name']).'/'.rawurlencode($pl['user_password']).'/'.$vid.'.'.$ext;

@set_time_limit(0);
ignore_user_abort(false);

$hdrs = array();
$sent = false;
$req = array();
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=\d*-\d*(,\s*\d*-\d*)*$/', $_SERVER['HTTP_RANGE'])) $req[] = 'Range: '.$_SERVER['HTTP_RANGE'];

$ch = curl_init($url);
curl_setopt_array($ch, array(
    CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 6,
    CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_TIMEOUT => 0,
    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => 'IPTVSmartersPlayer',
    CURLOPT_HTTPHEADER => $req,
    CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$hdrs) {
        if (stripos($line, 'HTTP/') === 0) $hdrs = array(); // novo bloco (redirecionamento): guarda só o último
        else if (strpos($line, ':') !== false) { list($k, $v) = explode(':', $line, 2); $hdrs[strtolower(trim($k))] = trim($v); }
        return strlen($line);
    },
    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$hdrs, &$sent) {
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code >= 300 && $code < 400) return strlen($data); // corpo do redirecionamento: ignora
        if (!$sent) {
            $sent = true;
            http_response_code($code);
            foreach (array('content-type', 'content-length', 'content-range', 'accept-ranges', 'last-modified') as $h) if (isset($hdrs[$h])) header($h.': '.$hdrs[$h]);
            if (!isset($hdrs['accept-ranges']) && $code == 200) header('Accept-Ranges: none');
        }
        echo $data;
        flush();
        return connection_aborted() ? 0 : strlen($data);
    },
));
curl_exec($ch);
$err = curl_errno($ch);
curl_close($ch);
if (!$sent) tvv_fail($err ? 502 : 404, $err ? 'upstream_error' : 'no_content');
