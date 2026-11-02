<?php
/**
 * Rotas do serviço MSX (/msx/app.json?r=...). Cada rota devolve o JSON de uma tela.
 */
require_once(__DIR__."/msx_lib.php");

resellers_migrate($mysqli);
$route = isset($_GET['r']) ? (string)$_GET['r'] : 'menu';
$key = msx_key();

// O MSX troca {ID} por um código da instalação dele. Sem isso (MSX antigo), não dá para saber qual é o aparelho.
if ($key === '') {
    msx_out(array('name' => 'IPTV Best Pro', 'version' => MSX_VERSION, 'headline' => 'IPTV Best Pro', 'menu' => array(array(
        'id' => 'old', 'icon' => 'error', 'label' => 'Atualize o Media Station X',
        'data' => msx_msg_page('Atualize o Media Station X', 'Esta versão do MSX é antiga. Atualize o aplicativo na loja da sua TV e abra de novo.')))));
}
msx_register($mysqli, $key);
$device = msx_device($mysqli, $key);
$status = devices_app_status($device);
$active = $status === 'active';

function msx_need_active($active){
    if (!$active) msx_out(msx_msg_page('Aparelho não ativo', 'Ative o aparelho com o código do seu revendedor para usar esta área.'));
}
function msx_clean_text($t){ return str_replace(array('|', '[', ']', '@'), ' ', (string)$t); }
function msx_need_list($mysqli, $key){
    $pl = msx_current_pl($mysqli, $key);
    if (!$pl) msx_out(msx_msg_page('Nenhuma lista', 'Seu revendedor ainda não colocou uma lista neste aparelho.'));
    return $pl;
}
// vencimento legível
function msx_expiry($device){
    return ($device && (int)$device['expires_at'] > 0) ? date('d/m/Y', (int)$device['expires_at']) : '';
}

switch ($route) {

case 'menu':
    $items = array();
    if ($active) {
        $items[] = array('id' => 'live', 'icon' => 'live-tv', 'label' => 'TV ao vivo', 'data' => msx_u('live_cats', $key));
        $items[] = array('id' => 'movies', 'icon' => 'movie', 'label' => 'Filmes', 'data' => msx_u('vod_cats', $key));
        $items[] = array('id' => 'series', 'icon' => 'video-library', 'label' => 'Séries', 'data' => msx_u('ser_cats', $key));
        $items[] = array('id' => 'search', 'icon' => 'search', 'label' => 'Buscar', 'data' => msx_u('search_menu', $key));
        $items[] = array('id' => 'pls', 'icon' => 'playlist-play', 'label' => 'Minhas listas', 'data' => msx_u('pls', $key));
    } else {
        $items[] = array('id' => 'activate', 'icon' => 'vpn-key', 'label' => 'Ativar', 'data' => msx_u('activate', $key));
    }
    $items[] = array('id' => 'account', 'icon' => 'account-circle', 'label' => 'Conta', 'data' => msx_u('account', $key));
    msx_out(array('name' => 'IPTV Best Pro', 'version' => MSX_VERSION, 'logo' => msx_logo(), 'logoSize' => 'small',
        'cache' => false, 'reuse' => false, 'style' => 'default', 'menu' => $items));

case 'activate':
    $msg = $status === 'pending' ? 'Aparelho ligado. Aguarde a ativação pelo seu revendedor.'
        : ($status === 'expired' ? 'Sua ativação venceu. Digite o código do seu revendedor de novo para renovar.' : 'Digite o código que o seu revendedor passou.');
    msx_out(msx_list_page($msg, array(array('label' => 'Digitar o código', 'icon' => 'vpn-key',
        'action' => msx_input_action(msx_u('link', $key, array(), '&input={INPUT}'), 'default', 'Código de ativação', 'Ex.: ABCDEF')))));

case 'link':
    $code = activation_code_normalize(isset($_GET['input']) ? (string)$_GET['input'] : '');
    if ($code === '') msx_out(array('action' => 'error:Digite o código.'));
    $link = devices_link($mysqli, array('device_id' => msx_device_id($key), 'application_id' => MSX_APP_ID, 'code' => $code,
        'platform' => 'msx', 'device_model' => 'Media Station X', 'app_name' => 'IPTV Best Pro (MSX)', 'app_version' => 'MSX '.MSX_VERSION));
    if ($link['ok']) msx_out(array('action' => '[success:'.msx_clean_text($link['msg']).'|reload:menu]'));
    msx_out(array('action' => 'error:'.msx_clean_text($link['msg'])));

case 'live_cats':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $cats = msx_xtream($pl, 'get_live_categories');
    if (!is_array($cats) || !$cats) msx_out(msx_msg_page('Sem categorias', 'Não foi possível carregar as categorias agora. Tente de novo em instantes.'));
    $items = array(array('label' => 'Todos os canais', 'icon' => 'live-tv', 'action' => 'content:'.msx_u('live', $key, array('c' => 'all', 'p' => 0))));
    foreach ($cats as $c) {
        if (!isset($c['category_id'])) continue;
        $items[] = array('label' => (string)$c['category_name'], 'action' => 'content:'.msx_u('live', $key, array('c' => (string)$c['category_id'], 'p' => 0)));
    }
    msx_out(msx_list_page('TV ao vivo', $items));

case 'live':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $cat = isset($_GET['c']) ? (string)$_GET['c'] : 'all';
    $p = max(0, isset($_GET['p']) ? (int)$_GET['p'] : 0);
    $list = msx_xtream($pl, 'get_live_streams', $cat === 'all' ? array() : array('category_id' => $cat), 600);
    if (!is_array($list)) msx_out(msx_msg_page('Sem canais', 'Não foi possível carregar os canais agora. Tente de novo em instantes.'));
    $total = count($list);
    $slice = array_slice($list, $p * MSX_PAGE, MSX_PAGE);
    $items = array();
    if ($p > 0) $items[] = array('label' => '« Página anterior', 'icon' => 'arrow-back', 'action' => 'content:'.msx_u('live', $key, array('c' => $cat, 'p' => $p - 1)));
    foreach ($slice as $s) {
        if (!isset($s['stream_id'])) continue;
        $items[] = array('label' => (string)$s['name'], 'icon' => 'live-tv', 'image' => !empty($s['stream_icon']) ? (string)$s['stream_icon'] : null,
            'action' => 'video:'.msx_stream_url($pl, 'live', $s['stream_id'], 'm3u8'));
    }
    if (($p + 1) * MSX_PAGE < $total) $items[] = array('label' => 'Próxima página »', 'icon' => 'arrow-forward', 'action' => 'content:'.msx_u('live', $key, array('c' => $cat, 'p' => $p + 1)));
    if (!$items) msx_out(msx_msg_page('Sem canais', 'Esta categoria está vazia.'));
    msx_out(msx_list_page('TV ao vivo ('.$total.')', $items));

case 'vod_cats':
case 'ser_cats':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $is_vod = $route === 'vod_cats';
    $cats = msx_xtream($pl, $is_vod ? 'get_vod_categories' : 'get_series_categories');
    if (!is_array($cats) || !$cats) msx_out(msx_msg_page('Sem categorias', 'Não foi possível carregar as categorias agora. Tente de novo em instantes.'));
    $items = array();
    foreach ($cats as $c) {
        if (!isset($c['category_id'])) continue;
        $items[] = array('label' => (string)$c['category_name'], 'action' => 'content:'.msx_u($is_vod ? 'vod' : 'ser', $key, array('c' => (string)$c['category_id'], 'p' => 0)));
    }
    msx_out(msx_list_page($is_vod ? 'Filmes' : 'Séries', $items));

case 'vod':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $cat = isset($_GET['c']) ? (string)$_GET['c'] : '';
    $p = max(0, isset($_GET['p']) ? (int)$_GET['p'] : 0);
    $list = msx_xtream($pl, 'get_vod_streams', array('category_id' => $cat), 600);
    if (!is_array($list)) msx_out(msx_msg_page('Sem filmes', 'Não foi possível carregar os filmes agora. Tente de novo em instantes.'));
    $total = count($list);
    $items = array();
    if ($p > 0) $items[] = array('title' => '« Página anterior', 'action' => 'content:'.msx_u('vod', $key, array('c' => $cat, 'p' => $p - 1)));
    foreach (array_slice($list, $p * MSX_PAGE, MSX_PAGE) as $m) {
        if (!isset($m['stream_id'])) continue;
        $ext = !empty($m['container_extension']) && preg_match('/^[A-Za-z0-9]{2,5}$/', $m['container_extension']) ? $m['container_extension'] : 'mp4';
        $items[] = array('title' => (string)$m['name'], 'image' => !empty($m['stream_icon']) ? (string)$m['stream_icon'] : null,
            'action' => 'video:'.msx_stream_url($pl, 'movie', $m['stream_id'], $ext));
    }
    if (($p + 1) * MSX_PAGE < $total) $items[] = array('title' => 'Próxima página »', 'action' => 'content:'.msx_u('vod', $key, array('c' => $cat, 'p' => $p + 1)));
    if (!$items) msx_out(msx_msg_page('Sem filmes', 'Esta categoria está vazia.'));
    msx_out(msx_poster_page('Filmes ('.$total.')', $items));

case 'ser':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $cat = isset($_GET['c']) ? (string)$_GET['c'] : '';
    $p = max(0, isset($_GET['p']) ? (int)$_GET['p'] : 0);
    $list = msx_xtream($pl, 'get_series', array('category_id' => $cat), 600);
    if (!is_array($list)) msx_out(msx_msg_page('Sem séries', 'Não foi possível carregar as séries agora. Tente de novo em instantes.'));
    $total = count($list);
    $items = array();
    if ($p > 0) $items[] = array('title' => '« Página anterior', 'action' => 'content:'.msx_u('ser', $key, array('c' => $cat, 'p' => $p - 1)));
    foreach (array_slice($list, $p * MSX_PAGE, MSX_PAGE) as $s) {
        if (!isset($s['series_id'])) continue;
        $items[] = array('title' => (string)$s['name'], 'image' => !empty($s['cover']) ? (string)$s['cover'] : null,
            'action' => 'content:'.msx_u('serie', $key, array('s' => (string)$s['series_id'])));
    }
    if (($p + 1) * MSX_PAGE < $total) $items[] = array('title' => 'Próxima página »', 'action' => 'content:'.msx_u('ser', $key, array('c' => $cat, 'p' => $p + 1)));
    if (!$items) msx_out(msx_msg_page('Sem séries', 'Esta categoria está vazia.'));
    msx_out(msx_poster_page('Séries ('.$total.')', $items));

case 'serie':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $sid = isset($_GET['s']) ? (string)$_GET['s'] : '';
    $info = msx_xtream($pl, 'get_series_info', array('series_id' => $sid), 1800);
    $eps = is_array($info) && isset($info['episodes']) && is_array($info['episodes']) ? $info['episodes'] : array();
    $items = array();
    foreach ($eps as $season => $list) {
        if (!is_array($list)) continue;
        foreach ($list as $e) {
            if (!is_array($e) || !isset($e['id'])) continue;
            $ext = !empty($e['container_extension']) && preg_match('/^[A-Za-z0-9]{2,5}$/', $e['container_extension']) ? $e['container_extension'] : 'mp4';
            $num = isset($e['episode_num']) ? (string)$e['episode_num'] : '';
            $sn = isset($e['season']) ? (string)$e['season'] : (string)$season;
            $title = isset($e['title']) ? (string)$e['title'] : '';
            $items[] = array('label' => 'T'.$sn.' E'.$num.($title !== '' ? ' - '.$title : ''), 'icon' => 'play-circle-outline',
                'action' => 'video:'.msx_stream_url($pl, 'series', $e['id'], $ext));
        }
    }
    if (!$items) msx_out(msx_msg_page('Sem episódios', 'Não encontramos episódios para esta série.'));
    $name = is_array($info) && isset($info['info']['name']) ? (string)$info['info']['name'] : 'Série';
    msx_out(msx_list_page($name, $items));

case 'search_menu':
    msx_need_active($active);
    $mk = function ($type, $label, $icon) use ($key) {
        return array('label' => $label, 'icon' => $icon,
            'action' => msx_input_action(msx_u('search', $key, array('t' => $type), '&input={INPUT}'), 'search:2', 'Buscar '.mb_strtolower($label, 'UTF-8'), 'Digite pelo menos 2 letras'));
    };
    msx_out(msx_list_page('Buscar', array($mk('live', 'Canais', 'live-tv'), $mk('movie', 'Filmes', 'movie'), $mk('series', 'Séries', 'video-library'))));

case 'search':
    msx_need_active($active);
    $pl = msx_need_list($mysqli, $key);
    $type = isset($_GET['t']) && in_array($_GET['t'], array('live', 'movie', 'series'), true) ? $_GET['t'] : 'movie';
    $text = trim(mb_substr(strip_tags(isset($_GET['input']) ? (string)$_GET['input'] : ''), 0, 60));
    if (mb_strlen($text) < 2) msx_out(array('template' => array('type' => 'control', 'layout' => '0,0,12,1', 'color' => 'msx-glass'), 'items' => array()));
    $act = $type === 'live' ? 'get_live_streams' : ($type === 'movie' ? 'get_vod_streams' : 'get_series');
    $all = msx_xtream($pl, $act, array(), 1800);
    $needle = msx_norm($text);
    $adult = '/adult|adulto|xxx|\+18|18\+|porn|hot\b/i';
    $items = array();
    if (is_array($all)) {
        foreach ($all as $s) {
            if (!is_array($s) || !isset($s['name'])) continue;
            if (preg_match($adult, (string)$s['name'])) continue;
            if (strpos(msx_norm($s['name']), $needle) === false) continue;
            if ($type === 'live') {
                $items[] = array('label' => (string)$s['name'], 'icon' => 'live-tv', 'action' => 'video:'.msx_stream_url($pl, 'live', $s['stream_id'], 'm3u8'));
            } else if ($type === 'movie') {
                $ext = !empty($s['container_extension']) && preg_match('/^[A-Za-z0-9]{2,5}$/', $s['container_extension']) ? $s['container_extension'] : 'mp4';
                $items[] = array('label' => (string)$s['name'], 'icon' => 'movie', 'action' => 'video:'.msx_stream_url($pl, 'movie', $s['stream_id'], $ext));
            } else {
                $items[] = array('label' => (string)$s['name'], 'icon' => 'video-library', 'action' => 'content:'.msx_u('serie', $key, array('s' => (string)$s['series_id'])));
            }
            if (count($items) >= 60) break;
        }
    }
    msx_out(array('template' => array('type' => 'control', 'layout' => '0,0,12,1', 'color' => 'msx-glass'), 'items' => $items));

case 'pls':
    msx_need_active($active);
    $pls = msx_playlists($mysqli, $key);
    $cur = msx_current_pl($mysqli, $key);
    if (!$pls) msx_out(msx_msg_page('Nenhuma lista', 'Seu revendedor ainda não colocou uma lista neste aparelho.'));
    $items = array();
    foreach ($pls as $p) {
        $on = $cur && (int)$cur['id'] === (int)$p['id'];
        $items[] = array('label' => msx_pl_name($p).($on ? '  (em uso)' : ''), 'icon' => $on ? 'check-circle' : 'radio-button-unchecked',
            'action' => 'execute:'.msx_u('setpl', $key, array('l' => (int)$p['id'])));
    }
    msx_out(msx_list_page('Minhas listas', $items));

case 'setpl':
    msx_need_active($active);
    $want = isset($_GET['l']) ? (int)$_GET['l'] : 0;
    foreach (msx_playlists($mysqli, $key) as $p) {
        if ((int)$p['id'] === $want) {
            msx_set_pl($mysqli, $key, $want);
            msx_exec('[success:'.msx_clean_text('Lista em uso: '.msx_pl_name($p)).'|reload:content]');
        }
    }
    msx_exec('error:Lista não encontrada.');

case 'account':
    require_once(__DIR__."/../includes/support_helper.php");
    support_migrate($mysqli);
    $sp = support_contact_for_device($mysqli, $device);
    $labels = array('active' => 'Ativo', 'expired' => 'Vencido', 'pending' => 'Aguardando ativação pelo revendedor', 'unlinked' => 'Não ativado');
    $items = array(array('title' => 'Situação', 'titleFooter' => isset($labels[$status]) ? $labels[$status] : $status));
    if ($active && msx_expiry($device) !== '') $items[] = array('title' => 'Válido até', 'titleFooter' => msx_expiry($device));
    if ($device && $device['owner'] != '') $items[] = array('title' => 'Revenda', 'titleFooter' => devices_owner_name($mysqli, $device));
    if (!empty($sp['whatsapp'])) $items[] = array('title' => 'Suporte (WhatsApp)', 'titleFooter' => (string)$sp['whatsapp']);
    $items[] = array('title' => 'ID deste aparelho', 'titleFooter' => substr($key, 0, 24));
    $page = array('type' => 'list', 'headline' => 'Conta',
        'template' => array('type' => 'default', 'layout' => '0,0,12,1', 'color' => 'msx-glass'), 'items' => $items);
    if ($device && $device['owner'] != '') {
        $page['items'][] = array('type' => 'control', 'label' => 'Resetar licença (usar outro código)', 'icon' => 'restore', 'action' => 'execute:'.msx_u('reset', $key));
    }
    msx_out($page);

case 'reset':
    if ($device) {
        devices_wipe_playlists($mysqli, msx_device_id($key));
        $ids = array();
        $st = mysqli_prepare($mysqli, "SELECT id FROM tbl_devices WHERE device_id = ?");
        $did = msx_device_id($key);
        mysqli_stmt_bind_param($st, 's', $did);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && $x = mysqli_fetch_assoc($rs)) $ids[] = (int)$x['id'];
        mysqli_stmt_close($st);
        if ($ids) devices_purge($mysqli, $ids);
    }
    msx_exec('[success:Licença resetada. Digite o novo código.|reload:menu]');

default:
    msx_out(msx_msg_page('Tela não encontrada', 'Volte e tente de novo.'));
}
