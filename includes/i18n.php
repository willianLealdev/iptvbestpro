<?php
/**
 * Idiomas do sistema: Português (pt), English (en), Español (es).
 *
 * Como o idioma é escolhido (nesta ordem):
 *  1. Escolha feita agora pelo seletor (vale para a sessão daquela conta).
 *  2. Idioma padrão salvo na conta (admin, revendedor ou cliente) — "Definir como padrão".
 *  3. Idioma padrão salvo neste navegador (visitante do site).
 *  4. País do IP: Brasil e países de língua portuguesa = pt; países de língua espanhola = es; demais = en.
 *
 * Tradução: as páginas são escritas em português (as novas) ou inglês (as originais). Antes de enviar a página,
 * um filtro troca cada texto visível pela versão do idioma atual usando o dicionário (includes/lang/dict.php)
 * e as traduções feitas pelo admin em "Traduções" (tabela tbl_i18n). Textos com partes variáveis usam {} no
 * dicionário (ex.: "Pedido #{} criado."). Elementos com translate="no" não são traduzidos.
 */

define('I18N_LANGS', 'pt,en,es');

function i18n_languages(){
    return array(
        'pt' => array('name' => 'Português', 'short' => 'PT', 'flag' => 'br', 'html' => 'pt-BR'),
        'en' => array('name' => 'English',   'short' => 'EN', 'flag' => 'us', 'html' => 'en'),
        'es' => array('name' => 'Español',   'short' => 'ES', 'flag' => 'es', 'html' => 'es'),
    );
}

function i18n_valid($lang){ return in_array($lang, explode(',', I18N_LANGS), true); }

// Idioma pelo país do IP
function i18n_lang_for_country($cc){
    $cc = strtoupper((string)$cc);
    if ($cc == '') return 'pt';
    $pt = array('BR','PT','AO','MZ','CV','GW','ST','TL');
    $es = array('ES','MX','AR','CO','CL','PE','VE','EC','GT','CU','BO','DO','HN','PY','SV','NI','CR','PA','UY','PR','GQ');
    if (in_array($cc, $pt)) return 'pt';
    if (in_array($cc, $es)) return 'es';
    return 'en';
}

function i18n_country_name($cc, $lang){
    $names = array(
        'BR' => array('Brasil','Brazil','Brasil'), 'US' => array('Estados Unidos','United States','Estados Unidos'),
        'PT' => array('Portugal','Portugal','Portugal'), 'ES' => array('Espanha','Spain','España'),
        'MX' => array('México','Mexico','México'), 'AR' => array('Argentina','Argentina','Argentina'),
        'CO' => array('Colômbia','Colombia','Colombia'), 'CL' => array('Chile','Chile','Chile'),
        'PE' => array('Peru','Peru','Perú'), 'PY' => array('Paraguai','Paraguay','Paraguay'),
        'UY' => array('Uruguai','Uruguay','Uruguay'), 'BO' => array('Bolívia','Bolivia','Bolivia'),
        'VE' => array('Venezuela','Venezuela','Venezuela'), 'EC' => array('Equador','Ecuador','Ecuador'),
        'CA' => array('Canadá','Canada','Canadá'), 'GB' => array('Reino Unido','United Kingdom','Reino Unido'),
        'FR' => array('França','France','Francia'), 'DE' => array('Alemanha','Germany','Alemania'),
        'IT' => array('Itália','Italy','Italia'), 'JP' => array('Japão','Japan','Japón'),
        'AO' => array('Angola','Angola','Angola'), 'MZ' => array('Moçambique','Mozambique','Mozambique'),
    );
    $cc = strtoupper((string)$cc);
    $i = array_search($lang, array('pt','en','es'));
    return isset($names[$cc]) ? $names[$cc][$i === false ? 0 : $i] : $cc;
}

/* ---------- Contexto: quem está usando a página ---------- */

// Retorna array('type' => admin|reseller|customer|guest, 'id' => int)
function i18n_context(){
    static $ctx = null;
    if ($ctx !== null) return $ctx;
    $script = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    $base = basename($script);
    $ctx = array('type' => 'guest', 'id' => 0);
    if (strpos($script, '/revenda/') !== false) {
        if (!empty($_SESSION['rv_id'])) $ctx = array('type' => 'reseller', 'id' => (int)$_SESSION['rv_id']);
    } else if (strpos($script, '/cliente/') !== false) {
        if (!empty($_SESSION['cl_id'])) $ctx = array('type' => 'customer', 'id' => (int)$_SESSION['cl_id']);
    } else if (strpos($script, '/pagamento/') !== false) {
        $as = isset($_REQUEST['as']) ? $_REQUEST['as'] : '';
        if ($as == 'reseller' && !empty($_SESSION['rv_id'])) $ctx = array('type' => 'reseller', 'id' => (int)$_SESSION['rv_id']);
        else if (!empty($_SESSION['cl_id'])) $ctx = array('type' => 'customer', 'id' => (int)$_SESSION['cl_id']);
        else if (!empty($_SESSION['rv_id'])) $ctx = array('type' => 'reseller', 'id' => (int)$_SESSION['rv_id']);
    } else if (!in_array($base, array('index.php', 'privacy_policy.php', 'terms.php', '')) && !empty($_SESSION['id']) && !empty($_SESSION['admin_name'])) {
        $ctx = array('type' => 'admin', 'id' => (int)$_SESSION['id']);
    }
    return $ctx;
}

function i18n_ctx_key(){ $c = i18n_context(); return $c['type'].':'.$c['id']; }

function i18n_table_for($type){
    $map = array('admin' => 'tbl_admin', 'reseller' => 'tbl_resellers', 'customer' => 'tbl_customers');
    return isset($map[$type]) ? $map[$type] : '';
}

function i18n_migrate($mysqli){
    static $done = false;
    if ($done || !$mysqli) return;
    $done = true;
    $flag = sys_get_temp_dir().'/iptvbp_i18n_migrated_v1';
    if (@file_exists($flag)) return; // já migrado neste servidor
    foreach (array('tbl_admin', 'tbl_resellers', 'tbl_customers') as $t) {
        $exists = @mysqli_query($mysqli, "SHOW TABLES LIKE '$t'");
        if (!$exists || mysqli_num_rows($exists) == 0) continue;
        $col = @mysqli_query($mysqli, "SHOW COLUMNS FROM `$t` LIKE 'lang'");
        if ($col && mysqli_num_rows($col) == 0) @mysqli_query($mysqli, "ALTER TABLE `$t` ADD `lang` VARCHAR(5) NOT NULL DEFAULT ''");
    }
    @mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_i18n` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `src` VARCHAR(600) NOT NULL,
        `src_hash` CHAR(40) NOT NULL,
        `pt` TEXT NULL, `en` TEXT NULL, `es` TEXT NULL,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_src` (`src_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    @mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_i18n_missing` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `src` VARCHAR(600) NOT NULL,
        `src_hash` CHAR(40) NOT NULL,
        `page` VARCHAR(120) NOT NULL DEFAULT '',
        `hits` INT(11) NOT NULL DEFAULT 1,
        `ignored` TINYINT(1) NOT NULL DEFAULT 0,
        `last_seen` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_src` (`src_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    @file_put_contents($flag, (string)time());
}

// Idioma padrão salvo na conta (cache na sessão)
function i18n_account_lang($mysqli){
    $c = i18n_context();
    if ($c['type'] == 'guest' || !$mysqli) return '';
    $key = i18n_ctx_key();
    if (isset($_SESSION['i18n_acc'][$key])) return $_SESSION['i18n_acc'][$key];
    i18n_migrate($mysqli);
    $t = i18n_table_for($c['type']);
    $row = $t ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lang FROM `$t` WHERE id = ".(int)$c['id'])) : null;
    $lang = ($row && i18n_valid($row['lang'])) ? $row['lang'] : '';
    $_SESSION['i18n_acc'][$key] = $lang;
    return $lang;
}

function i18n_detected_country(){
    if (!function_exists('devices_country') && file_exists(__DIR__.'/device_tracker.php')) require_once(__DIR__.'/device_tracker.php');
    if (isset($_SESSION['i18n_cc_ip']) && $_SESSION['i18n_cc_ip'] === i18n_client_ip()) return $_SESSION['i18n_cc'];
    $cc = '';
    if (function_exists('devices_country')) {
        $c = devices_country(i18n_client_ip());
        $cc = strtoupper((string)$c['code']);
    } else if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && preg_match('/^[A-Z]{2}$/', $_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $cc = $_SERVER['HTTP_CF_IPCOUNTRY'];
    }
    $_SESSION['i18n_cc_ip'] = i18n_client_ip();
    $_SESSION['i18n_cc'] = $cc;
    return $cc;
}

function i18n_client_ip(){
    if (function_exists('devices_client_ip')) return devices_client_ip();
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

/**
 * Trata ?i18n=xx (trocar idioma) e ?i18n=xx&i18n_default=1 (trocar e salvar como padrão).
 * Depois redireciona para a mesma página sem esses parâmetros.
 */
function i18n_handle_request($mysqli){
    if (!isset($_GET['i18n']) || !i18n_valid($_GET['i18n'])) return;
    $lang = $_GET['i18n'];
    $_SESSION['i18n_sel'][i18n_ctx_key()] = $lang;
    if (!empty($_GET['i18n_default'])) {
        $c = i18n_context();
        $t = i18n_table_for($c['type']);
        if ($t && $mysqli) {
            i18n_migrate($mysqli);
            mysqli_query($mysqli, "UPDATE `$t` SET lang = '".$lang."' WHERE id = ".(int)$c['id']);
            $_SESSION['i18n_acc'][i18n_ctx_key()] = $lang;
        }
        setcookie('i18n_default', $lang, time() + 86400 * 365, '/');
        $_COOKIE['i18n_default'] = $lang;
        $_SESSION['i18n_saved_flash'] = 1;
    }
    $q = $_GET;
    unset($q['i18n'], $q['i18n_default']);
    $path = strtok(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', '?');
    header('Location: '.$path.($q ? '?'.http_build_query($q) : ''));
    exit;
}

function i18n_lang(){
    static $lang = null;
    if ($lang !== null) return $lang;
    global $mysqli;
    $key = i18n_ctx_key();
    if (!empty($_SESSION['i18n_sel'][$key]) && i18n_valid($_SESSION['i18n_sel'][$key])) return $lang = $_SESSION['i18n_sel'][$key];
    $acc = i18n_account_lang(isset($mysqli) ? $mysqli : null);
    if ($acc != '') return $lang = $acc;
    // escolha feita antes de entrar (ex.: no site ou na tela de login) continua valendo
    if (!empty($_SESSION['i18n_sel']['guest:0']) && i18n_valid($_SESSION['i18n_sel']['guest:0'])) return $lang = $_SESSION['i18n_sel']['guest:0'];
    if (!empty($_COOKIE['i18n_default']) && i18n_valid($_COOKIE['i18n_default'])) return $lang = $_COOKIE['i18n_default'];
    return $lang = i18n_lang_for_country(i18n_detected_country());
}

// Idioma padrão já salvo (conta ou navegador), para marcar no seletor
function i18n_saved_default(){
    global $mysqli;
    $acc = i18n_account_lang(isset($mysqli) ? $mysqli : null);
    if ($acc != '') return $acc;
    return (!empty($_COOKIE['i18n_default']) && i18n_valid($_COOKIE['i18n_default'])) ? $_COOKIE['i18n_default'] : '';
}

/* ---------- Dicionário ---------- */

function i18n_norm($s){ return trim(preg_replace('/\s+/u', ' ', (string)$s)); }

function i18n_dict(){
    static $d = null;
    if ($d !== null) return $d;
    $d = array('exact' => array(), 'patterns' => array());
    $file = __DIR__.'/lang/dict.php';
    try { $raw = file_exists($file) ? include($file) : array(); } catch (\Throwable $e) { $raw = array(); error_log('i18n dict: '.$e->getMessage()); }
    if (!is_array($raw)) $raw = array();
    // Traduções feitas no painel (Traduções) têm prioridade sobre o arquivo
    global $mysqli;
    if (isset($mysqli) && $mysqli) {
        i18n_migrate($mysqli);
        $res = @mysqli_query($mysqli, "SELECT src, pt, en, es FROM tbl_i18n");
        while ($res && $r = mysqli_fetch_assoc($res)) {
            $cur = isset($raw[$r['src']]) ? $raw[$r['src']] : array();
            foreach (array('pt', 'en', 'es') as $l) { if ($r[$l] !== null && $r[$l] !== '') $cur[$l] = $r[$l]; }
            $raw[$r['src']] = $cur;
        }
    }
    foreach ($raw as $src => $tr) {
        $src = i18n_norm($src);
        if (strpos($src, '{}') === false) { $d['exact'][$src] = $tr; continue; }
        $pieces = explode('{}', $src);
        $re = '/^'.implode('(.+?)', array_map(function ($p) { return preg_quote($p, '/'); }, $pieces)).'$/su';
        $bucket = $pieces[0] === '' ? '*' : mb_strtolower(mb_substr($pieces[0], 0, 3));
        $d['patterns'][$bucket][] = array($re, $tr, mb_strlen(str_replace('{}', '', $src)));
    }
    foreach ($d['patterns'] as $b => $list) {
        usort($list, function ($a, $b) { return $b[2] - $a[2]; });
        $d['patterns'][$b] = $list;
    }
    return $d;
}

/**
 * Traduz um texto (sem HTML) para o idioma atual. Retorna null quando não existe no dicionário.
 */
function i18n_lookup($text, $lang, $depth = 0){
    $d = i18n_dict();
    $key = i18n_norm($text);
    if ($key === '') return null;
    if (isset($d['exact'][$key])) {
        return (isset($d['exact'][$key][$lang]) && $d['exact'][$key][$lang] !== '') ? $d['exact'][$key][$lang] : $key;
    }
    if ($depth > 1) return null;
    // grupos pelo começo do texto (1 a 3 letras) + padrões que começam com parte variável
    $cands = array();
    for ($n = 3; $n >= 1; $n--) {
        $b = mb_strtolower(mb_substr($key, 0, $n));
        if (isset($d['patterns'][$b])) $cands = array_merge($cands, $d['patterns'][$b]);
    }
    if (isset($d['patterns']['*'])) $cands = array_merge($cands, $d['patterns']['*']);
    foreach ($cands as $p) {
        if (!preg_match($p[0], $key, $m)) continue;
        $out = (isset($p[1][$lang]) && $p[1][$lang] !== '') ? $p[1][$lang] : null;
        if ($out === null) return $key;
        $rep = array();
        for ($i = 1; $i < count($m); $i++) {
            $sub = i18n_lookup($m[$i], $lang, $depth + 1);
            $rep['{'.($i - 1).'}'] = $sub !== null ? $sub : $m[$i];
        }
        return strtr($out, $rep);
    }
    return null;
}

// Tradução para uso no PHP/JS: __('Texto {0}', $valor)
function __($text){
    $args = func_get_args();
    array_shift($args);
    $out = i18n_lookup($text, i18n_lang());
    if ($out === null) { $out = $text; i18n_note_missing($text); }
    if ($args) {
        $rep = array();
        foreach ($args as $i => $a) $rep['{'.$i.'}'] = $a;
        $out = strtr($out, $rep);
    }
    return $out;
}

/* ---------- Captura de textos sem tradução (admin → Traduções) ---------- */

function i18n_capture_on(){
    global $mysqli;
    static $on = null;
    if ($on !== null) return $on;
    if (!isset($mysqli) || !$mysqli || !function_exists('panel_config_get')) return $on = false;
    return $on = ((int)@panel_config_get($mysqli, 'i18n_capture_until', '0') > time());
}

function i18n_note_missing($text){
    $t = i18n_norm($text);
    if (mb_strlen($t) < 3 || mb_strlen($t) > 500) return;
    if (!preg_match('/\p{L}{3,}/u', $t)) return;                         // precisa ter uma palavra
    if (preg_match('/^[\w.+-]+@[\w-]+\.[\w.]+$|^https?:\/\/|^[0-9A-F:]{11,}$/i', $t)) return; // e-mail, URL, MAC
    $GLOBALS['i18n_missing'][$t] = 1;
}

function i18n_flush_missing(){
    global $mysqli;
    if (empty($GLOBALS['i18n_missing']) || !i18n_capture_on()) return;
    try {
        i18n_migrate($mysqli);
        $page = mb_substr(basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''), 0, 120);
        $dir = basename(dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));
        if (in_array($dir, array('revenda', 'cliente', 'pagamento'))) $page = $dir.'/'.$page;
        $now = time();
        $n = 0;
        foreach (array_keys($GLOBALS['i18n_missing']) as $t) {
            if (++$n > 80) break;
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_i18n_missing (src, src_hash, page, hits, last_seen) VALUES (?,?,?,1,?) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen)");
            $h = sha1($t);
            mysqli_stmt_bind_param($stmt, 'sssi', $t, $h, $page, $now);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch (\Throwable $e) { }
    $GLOBALS['i18n_missing'] = array();
}

/* ---------- Filtro da página ---------- */

function i18n_should_filter($buffer){
    if (PHP_SAPI === 'cli') return false;
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false) return false;
        if (stripos($h, 'Location:') === 0) return false;
    }
    $start = ltrim(substr($buffer, 0, 200));
    if ($start === '' || $start[0] === '{' || $start[0] === '[') return false;
    return strpos($buffer, '<') !== false;
}

function i18n_translate_text_node($raw, $lang){
    if (trim($raw) === '' || !preg_match('/\p{L}/u', $raw)) return $raw;
    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $tr = i18n_lookup($decoded, $lang);
    if ($tr === null) { i18n_note_missing($decoded); return $raw; }
    if ($tr === i18n_norm($decoded)) return $raw;
    preg_match('/^\s*/u', $raw, $lead);
    preg_match('/\s*$/u', $raw, $trail);
    return $lead[0].htmlspecialchars($tr, ENT_NOQUOTES, 'UTF-8', false).$trail[0];
}

function i18n_translate_js_strings($code, $lang){
    $code = i18n_translate_js_literals($code, $lang);
    return preg_replace_callback("/\\b(confirm|alert)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/u", function ($m) use ($lang) {
        $plain = str_replace("\\'", "'", $m[2]);
        $tr = i18n_lookup(html_entity_decode($plain, ENT_QUOTES, 'UTF-8'), $lang);
        if ($tr === null) return $m[0];
        return $m[1]."('".str_replace("'", "\\'", $tr)."'";
    }, $code);
}

// Literais '...' dentro de <script> que são exatamente um texto do dicionário (ex.: 'Copiado', '<i ...></i> Copiado')
function i18n_translate_js_literals($code, $lang){
    $d = i18n_dict();
    return preg_replace_callback('/\'((?:[^\'\\\\\n]|\\\\.)*)\'/u', function ($m) use ($d, $lang) {
        $lit = $m[1];
        if (!preg_match('/\p{L}{3,}/u', $lit)) return $m[0];
        $segs = preg_split('/(<[^>]*>)/', $lit, -1, PREG_SPLIT_DELIM_CAPTURE);
        $changed = false;
        foreach ($segs as $i => $seg) {
            if ($i % 2 == 1 || trim($seg) === '') continue;
            $plain = str_replace("\'", "'", $seg);
            $key = i18n_norm($plain);
            if (!isset($d['exact'][$key]) || empty($d['exact'][$key][$lang]) || $d['exact'][$key][$lang] === $key) continue;
            preg_match('/^\s*/u', $plain, $lead);
            preg_match('/\s*$/u', $plain, $trail);
            $segs[$i] = $lead[0].str_replace("'", "\'", $d['exact'][$key][$lang]).$trail[0];
            $changed = true;
        }
        return $changed ? "'".implode('', $segs)."'" : $m[0];
    }, $code);
}

function i18n_translate_tag($tag, $lang){
    // atributos visíveis
    $tag = preg_replace_callback('/\b(placeholder|title|alt|aria-label|data-bs-title|data-confirm)="([^"]*)"/u', function ($m) use ($lang) {
        if (!preg_match('/\p{L}{2,}/u', $m[2])) return $m[0];
        $tr = i18n_lookup(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $lang);
        return $tr === null ? $m[0] : $m[1].'="'.htmlspecialchars($tr, ENT_QUOTES, 'UTF-8', false).'"';
    }, $tag);
    // value de botões (input submit/button)
    if (preg_match('/^<input\b/i', $tag) && preg_match('/\btype="(submit|button|reset)"/i', $tag)) {
        $tag = preg_replace_callback('/\bvalue="([^"]*)"/u', function ($m) use ($lang) {
            $tr = i18n_lookup(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $lang);
            return $tr === null ? $m[0] : 'value="'.htmlspecialchars($tr, ENT_QUOTES, 'UTF-8', false).'"';
        }, $tag);
    }
    // confirm('...') em onclick/onsubmit
    if (stripos($tag, 'confirm(') !== false || stripos($tag, 'alert(') !== false) {
        $tag = preg_replace_callback('/\b(on\w+)="([^"]*)"/u', function ($m) use ($lang) {
            $js = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $m[1].'="'.htmlspecialchars(i18n_translate_js_strings($js, $lang), ENT_QUOTES, 'UTF-8', false).'"';
        }, $tag);
    }
    return $tag;
}

function i18n_filter($buffer){
    try {
        if (!i18n_should_filter($buffer)) return $buffer;
        $lang = i18n_lang();
        $langs = i18n_languages();
        $buffer = preg_replace('/<html([^>]*)\blang="[^"]*"/i', '<html$1lang="'.$langs[$lang]['html'].'"', $buffer, 1);

        // blocos inteiros de script/style/textarea primeiro (o conteúdo deles não é texto da página)
        $parts = preg_split('/(<script\b[^>]*>.*?<\/script\s*>|<style\b[^>]*>.*?<\/style\s*>|<textarea\b[^>]*>.*?<\/textarea\s*>|<!--.*?-->|<[^>]+>)/is', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) return $buffer;
        $skip_tag = ''; $skip_depth = 0; // translate="no"
        $out = '';
        foreach ($parts as $i => $p) {
            if ($p === '') continue;
            if ($i % 2 == 0) { // texto
                $out .= $skip_tag !== '' ? $p : i18n_translate_text_node($p, $lang);
                continue;
            }
            if (strncmp($p, '<!--', 4) === 0) { $out .= $p; continue; }
            if (preg_match('/^<(script|style|textarea)\b/i', $p, $bm)) {
                $gt = strpos($p, '>');
                $open = substr($p, 0, $gt + 1);
                $rest = substr($p, $gt + 1);
                $kind = strtolower($bm[1]);
                if ($skip_tag !== '' || $kind == 'style') { $out .= $p; continue; }
                if ($kind == 'script') { $out .= $open.i18n_translate_js_strings($rest, $lang); continue; }
                $out .= i18n_translate_tag($open, $lang).$rest; // textarea: só o placeholder
                continue;
            }
            if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9\-]+)/', $p, $tm)) { $out .= $p; continue; }
            $closing = $tm[1] === '/';
            $name = strtolower($tm[2]);
            if ($skip_tag !== '') {
                if ($name === $skip_tag) $skip_depth += $closing ? -1 : (substr($p, -2) === '/>' ? 0 : 1);
                if ($skip_depth <= 0) $skip_tag = '';
                $out .= $p;
                continue;
            }
            if (!$closing && preg_match('/\btranslate="no"|\bclass="[^"]*\bnotranslate\b/i', $p)) {
                if (!in_array($name, array('input', 'img', 'br', 'hr', 'meta', 'link'))) { $skip_tag = $name; $skip_depth = 1; }
                $out .= $p;
                continue;
            }
            $out .= $closing ? $p : i18n_translate_tag($p, $lang);
        }
        i18n_flush_missing();
        return $out;
    } catch (\Throwable $e) {
        error_log('i18n: '.$e->getMessage());
        return $buffer;
    }
}

/* ---------- Seletor de idioma ---------- */

/**
 * Seletor (sem dependências): bandeira + sigla; abre a lista de idiomas, o país detectado pelo IP
 * e o botão "Definir como padrão". $theme: 'light' | 'dark'.
 */
function i18n_switcher($theme = 'light', $prefix = ''){
    try { return i18n_switcher_html($theme); } catch (\Throwable $e) { error_log('i18n switcher: '.$e->getMessage()); return ''; }
}

function i18n_switcher_html($theme = 'light'){
    $langs = i18n_languages();
    $cur = i18n_lang();
    $saved = i18n_saved_default();
    $cc = i18n_detected_country();
    $c = i18n_context();
    $q = $_GET; unset($q['i18n'], $q['i18n_default']);
    $base = strtok(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', '?');
    $link = function ($code, $def) use ($q, $base) {
        $qq = $q; $qq['i18n'] = $code; if ($def) $qq['i18n_default'] = 1;
        return htmlspecialchars($base.'?'.http_build_query($qq), ENT_QUOTES, 'UTF-8');
    };
    $txt = array(
        'pt' => array('Idioma', 'Detectado pelo IP', 'Definir %s como padrão', 'Seu idioma padrão', 'Idioma padrão salvo.', 'Sempre que entrar, o sistema abre neste idioma.'),
        'en' => array('Language', 'Detected by IP', 'Set %s as default', 'Your default language', 'Default language saved.', 'Every time you sign in, the system opens in this language.'),
        'es' => array('Idioma', 'Detectado por IP', 'Definir %s como predeterminado', 'Tu idioma predeterminado', 'Idioma predeterminado guardado.', 'Cada vez que entres, el sistema se abrirá en este idioma.'),
    );
    $t = $txt[$cur];
    $flash = !empty($_SESSION['i18n_saved_flash']);
    unset($_SESSION['i18n_saved_flash']);
    static $css = false;
    ob_start();
    if (!$css) { $css = true; ?>
<style>
.i18n-sw{position:relative;display:inline-block;font-family:inherit;z-index:60}
.i18n-sw>summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border-radius:999px;font-weight:600;font-size:13.5px;line-height:1;user-select:none;border:1px solid rgba(0,0,0,.12);background:#fff;color:#1f2a44}
.i18n-sw>summary::-webkit-details-marker{display:none}
.i18n-sw.dark>summary{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.18);color:#e9edf7}
.i18n-sw>summary img{width:20px;height:14px;border-radius:3px;object-fit:cover}
.i18n-sw>summary i{font-size:16px;opacity:.7}
.i18n-sw__menu{position:absolute;right:0;top:calc(100% + 8px);min-width:250px;background:#fff;color:#1f2a44;border-radius:14px;box-shadow:0 18px 40px rgba(15,23,42,.18);border:1px solid #e8ecf4;padding:8px;text-align:left}
.i18n-sw__head{font-size:11.5px;text-transform:uppercase;letter-spacing:1px;color:#8a94ab;padding:6px 10px 4px;font-weight:700}
.i18n-sw__item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;color:#1f2a44 !important;text-decoration:none !important;font-size:14.5px;font-weight:500}
.i18n-sw__item:hover{background:#f3f6fc}
.i18n-sw__item.on{background:#eef4ff;font-weight:700}
.i18n-sw__item img{width:22px;height:15px;border-radius:3px;object-fit:cover}
.i18n-sw__item .tag{margin-left:auto;font-size:11px;font-weight:700;color:#16a34a;background:#f0fdf4;border-radius:999px;padding:2px 8px}
.i18n-sw__geo{display:flex;align-items:center;gap:8px;margin:6px 4px 4px;padding:8px 10px;border-radius:9px;background:#f7f9fe;font-size:13px;color:#4b5875}
.i18n-sw__geo img{width:20px;height:14px;border-radius:3px}
.i18n-sw__def{display:flex;align-items:center;justify-content:center;gap:6px;margin:6px 4px 2px;padding:9px 10px;border-radius:9px;background:#16a34a;color:#fff !important;text-decoration:none !important;font-weight:600;font-size:13.5px}
.i18n-sw__def:hover{background:#15803d}
.i18n-sw__ok{margin:6px 4px 2px;padding:8px 10px;border-radius:9px;background:#f0fdf4;color:#15803d;font-size:13px;display:flex;gap:6px;align-items:center}
.i18n-sw__hint{font-size:11.5px;color:#8a94ab;padding:4px 10px 4px}
.i18n-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#0f172a;color:#fff;padding:12px 18px;border-radius:12px;font-size:14px;box-shadow:0 12px 30px rgba(0,0,0,.25);z-index:9999;animation:i18nT 3.2s ease forwards}
@keyframes i18nT{0%{opacity:0;transform:translate(-50%,10px)}10%,85%{opacity:1;transform:translate(-50%,0)}100%{opacity:0;transform:translate(-50%,10px)}}
</style>
<script>document.addEventListener('click',function(e){document.querySelectorAll('details.i18n-sw[open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open');});});</script>
<?php } ?>
<details class="i18n-sw <?php echo $theme == 'dark' ? 'dark' : ''; ?>" translate="no">
    <summary title="<?php echo $t[0]; ?>"><img src="https://flagcdn.com/w40/<?php echo $langs[$cur]['flag']; ?>.png" alt=""><?php echo $langs[$cur]['short']; ?><i class="ri-arrow-down-s-line"></i></summary>
    <div class="i18n-sw__menu">
        <div class="i18n-sw__head"><?php echo $t[0]; ?></div>
        <?php foreach ($langs as $code => $l) { ?>
            <a class="i18n-sw__item <?php echo $code == $cur ? 'on' : ''; ?>" href="<?php echo $link($code, false); ?>">
                <img src="https://flagcdn.com/w40/<?php echo $l['flag']; ?>.png" alt=""> <?php echo $l['name']; ?>
                <?php if ($code == $saved) { ?><span class="tag">★</span><?php } ?>
            </a>
        <?php } ?>
        <?php if ($cc != '') { ?>
            <div class="i18n-sw__geo"><img src="https://flagcdn.com/w40/<?php echo strtolower(htmlspecialchars($cc)); ?>.png" alt=""> <?php echo $t[1]; ?>: <b><?php echo htmlspecialchars(i18n_country_name($cc, $cur)); ?></b></div>
        <?php } ?>
        <?php if ($saved == $cur) { ?>
            <div class="i18n-sw__ok"><i class="ri-star-fill"></i> <?php echo $t[3]; ?>: <b><?php echo $langs[$cur]['name']; ?></b></div>
        <?php } else { ?>
            <a class="i18n-sw__def" href="<?php echo $link($cur, true); ?>"><i class="ri-star-line"></i> <?php echo sprintf($t[2], $langs[$cur]['name']); ?></a>
            <div class="i18n-sw__hint"><?php echo $t[5]; ?></div>
        <?php } ?>
    </div>
</details>
<?php if ($flash) { ?><div class="i18n-toast" translate="no"><i class="ri-check-line"></i> <?php echo $t[4]; ?></div><?php } ?>
<?php
    return ob_get_clean();
}

// Seletor fixo no canto superior direito (telas sem barra de navegação)
function i18n_switcher_fixed($theme = 'light'){
    return '<div style="position:fixed;top:14px;right:16px;z-index:1050">'.i18n_switcher($theme).'</div>';
}

/* ---------- Inicialização ---------- */
if (PHP_SAPI !== 'cli') {
    if (session_status() == PHP_SESSION_NONE) @session_start();
    i18n_handle_request(isset($mysqli) ? $mysqli : null);
    ob_start('i18n_filter');
}
