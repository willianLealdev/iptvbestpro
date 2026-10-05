<?php
/**
 * Servidor de demonstração no formato Xtream (usado pela conta de teste das lojas).
 * Só responde ao usuário de demonstração; o conteúdo é livre (ver includes/demo_catalog.php).
 * Não usa banco de dados.
 */
require_once(__DIR__."/includes/demo_catalog.php");

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = isset($_REQUEST['username']) ? (string)$_REQUEST['username'] : '';
$pass = isset($_REQUEST['password']) ? (string)$_REQUEST['password'] : '';
$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

if (!hash_equals(DEMO_USER, $user) || !hash_equals(DEMO_PASS, $pass)) {
    echo json_encode(array('user_info' => array('auth' => 0)));
    exit;
}

$proto = 'https';
$host = !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'iptvbestpro.com');
$host = preg_replace('/[^a-z0-9.\-]/i', '', explode(',', $host)[0]);
$base = $proto.'://'.$host;
$cat = demo_catalog($base);
$now = time();

function demo_out($v){ echo json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

switch ($action) {
    case '':
        demo_out(array(
            'user_info' => array('username' => DEMO_USER, 'password' => DEMO_PASS, 'message' => 'Demo account', 'auth' => 1, 'status' => 'Active',
                'exp_date' => (string)($now + 365 * 86400), 'is_trial' => '1', 'active_cons' => '0', 'created_at' => (string)($now - 86400),
                'max_connections' => '5', 'allowed_output_formats' => array('m3u8')),
            'server_info' => array('xui' => false, 'version' => '1.0', 'revision' => 1, 'url' => $host, 'port' => '443', 'https_port' => '443',
                'server_protocol' => 'https', 'rtmp_port' => '0', 'timezone' => 'UTC', 'timestamp_now' => $now, 'time_now' => gmdate('Y-m-d H:i:s', $now)),
        ));
    case 'get_live_categories': demo_out($cat['live_cats']);
    case 'get_vod_categories': demo_out($cat['vod_cats']);
    case 'get_series_categories': demo_out($cat['series_cats']);
    case 'get_live_streams':
        $out = array();
        foreach ($cat['live'] as $i => $c) $out[] = array('num' => $i + 1, 'name' => $c['name'], 'stream_type' => 'live', 'stream_id' => $c['id'],
            'stream_icon' => demo_img($base, $c['logo']), 'epg_channel_id' => 'demo'.$c['id'], 'added' => (string)($now - 86400), 'category_id' => '1',
            'custom_sid' => '', 'tv_archive' => 0, 'direct_source' => '', 'tv_archive_duration' => 0);
        demo_out($out);
    case 'get_vod_streams':
        $out = array();
        foreach ($cat['vod'] as $i => $m) $out[] = array('num' => $i + 1, 'name' => $m['name'], 'stream_type' => 'movie', 'stream_id' => $m['id'],
            'stream_icon' => demo_img($base, $m['img']), 'rating' => '8', 'rating_5based' => 4, 'added' => (string)($now - 86400 * ($i + 1)),
            'category_id' => '11', 'container_extension' => 'm3u8', 'custom_sid' => '', 'direct_source' => '');
        demo_out($out);
    case 'get_vod_info':
        $id = isset($_REQUEST['vod_id']) ? (int)$_REQUEST['vod_id'] : 0;
        foreach ($cat['vod'] as $m) if ($m['id'] == $id) {
            demo_out(array(
                'info' => array('tmdb_id' => '', 'name' => $m['name'], 'movie_image' => demo_img($base, $m['img']), 'cover_big' => demo_img($base, $m['img']),
                    'release_date' => $m['year'].'-01-01', 'releasedate' => $m['year'].'-01-01', 'episode_run_time' => (string)$m['mins'], 'duration' => sprintf('00:%02d:00', $m['mins']),
                    'duration_secs' => $m['mins'] * 60, 'youtube_trailer' => '', 'director' => $m['dir'], 'cast' => 'Blender Foundation', 'actors' => 'Blender Foundation',
                    'plot' => $m['plot'], 'description' => $m['plot'], 'genre' => $m['genre'], 'rating' => '8', 'backdrop_path' => array()),
                'movie_data' => array('stream_id' => $m['id'], 'name' => $m['name'], 'added' => (string)$now, 'category_id' => '11', 'container_extension' => 'm3u8',
                    'custom_sid' => '', 'direct_source' => ''),
            ));
        }
        demo_out(array('info' => array(), 'movie_data' => array()));
    case 'get_series':
        $out = array();
        foreach ($cat['series'] as $i => $se) $out[] = array('num' => $i + 1, 'name' => $se['name'], 'series_id' => $se['id'], 'cover' => demo_img($base, $se['img']),
            'plot' => $se['plot'], 'cast' => 'Blender Foundation', 'director' => '', 'genre' => $se['genre'], 'releaseDate' => '2024-01-01', 'release_date' => '2024-01-01',
            'last_modified' => (string)$now, 'rating' => '8', 'rating_5based' => 4, 'backdrop_path' => array(), 'youtube_trailer' => '', 'episode_run_time' => '10', 'category_id' => '21');
        demo_out($out);
    case 'get_series_info':
        $id = isset($_REQUEST['series_id']) ? (int)$_REQUEST['series_id'] : 0;
        foreach ($cat['series'] as $se) if ($se['id'] == $id) {
            $eps = array();
            foreach ($se['episodes'] as $n => $e) $eps[] = array('id' => (string)$e['id'], 'episode_num' => $n + 1, 'title' => $e['title'], 'container_extension' => 'm3u8',
                'season' => '1', 'custom_sid' => '', 'added' => (string)$now, 'direct_source' => '',
                'info' => array('movie_image' => demo_img($base, $se['img']), 'plot' => $se['plot'], 'duration' => '00:10:00', 'duration_secs' => 600, 'rating' => '8'));
            demo_out(array('seasons' => array(array('season_number' => 1, 'name' => 'Season 1', 'episode_count' => count($eps))),
                'info' => array('name' => $se['name'], 'cover' => demo_img($base, $se['img']), 'plot' => $se['plot'], 'director' => '', 'genre' => $se['genre'],
                    'releaseDate' => '2024-01-01', 'rating' => '8', 'rating_5based' => '4', 'youtube_trailer' => '', 'cast' => 'Blender Foundation'),
                'episodes' => array('1' => $eps)));
        }
        demo_out(array('info' => array(), 'episodes' => new stdClass()));
    case 'get_short_epg':
    case 'get_simple_data_table':
        // Guia de demonstração: blocos de 30 minutos a partir de agora
        $start = $now - ($now % 1800);
        $list = array();
        for ($i = 0; $i < 6; $i++) {
            $a = $start + $i * 1800; $b = $a + 1800;
            $list[] = array('id' => (string)($i + 1), 'epg_id' => '1', 'title' => base64_encode('Demo program '.($i + 1)), 'lang' => 'en',
                'start' => gmdate('Y-m-d H:i:s', $a), 'end' => gmdate('Y-m-d H:i:s', $b), 'description' => base64_encode('Sample program for testing.'),
                'channel_id' => 'demo', 'start_timestamp' => (string)$a, 'stop_timestamp' => (string)$b, 'now_playing' => $i == 0 ? 1 : 0, 'has_archive' => 0);
        }
        demo_out(array('epg_listings' => $list));
    default:
        demo_out(array());
}
