<?php
/**
 * Imagens do serviço MSX geradas na hora (PNG, porque o MSX não mostra SVG): fundo, cartões da tela inicial
 * e quadrinho de canal sem logo. Não usa o banco: as cores vêm no endereço. Resultado guardado em cache.
 */
$FONT = __DIR__.'/fonts/DejaVuSans-Bold.ttf';
$ICONS = __DIR__.'/../assets/vendors/remixicon/remixicon.ttf';

function img_hex($v, $def){
    $v = '#'.ltrim((string)$v, '#');
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $def;
}
function img_rgb($hex){ return array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))); }
function img_mix($a, $b, $t){
    $x = img_rgb($a); $y = img_rgb($b);
    return array((int)round($x[0] + ($y[0] - $x[0]) * $t), (int)round($x[1] + ($y[1] - $x[1]) * $t), (int)round($x[2] + ($y[2] - $x[2]) * $t));
}
// Degradê na diagonal (canto de cima à esquerda -> canto de baixo à direita)
function img_gradient($im, $w, $h, $c1, $c2){
    $n = $w + $h;
    for ($k = 0; $k <= $n; $k++) {
        $c = img_mix($c1, $c2, $k / $n);
        $col = imagecolorallocate($im, $c[0], $c[1], $c[2]);
        imagesetthickness($im, 2);
        imageline($im, $k, 0, 0, $k, $col);
    }
}
function img_text_center($im, $size, $cx, $y, $color, $font, $text){
    $bb = imagettfbbox($size, 0, $font, $text);
    $tw = abs($bb[2] - $bb[0]);
    imagettftext($im, $size, 0, (int)round($cx - $tw / 2), (int)$y, $color, $font, $text);
}
function img_send($im, $cache){
    ob_start();
    imagepng($im, null, 6);
    $png = ob_get_clean();
    @file_put_contents($cache, $png, LOCK_EX);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    echo $png;
    exit;
}

$k = isset($_GET['k']) ? (string)$_GET['k'] : '';
$cache_dir = sys_get_temp_dir().'/msx_img';
if (!is_dir($cache_dir)) @mkdir($cache_dir, 0700, true);
$cache = $cache_dir.'/'.md5(http_build_query($_GET)).'.png';
if (is_file($cache)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($cache);
    exit;
}
if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) { http_response_code(501); exit; }

if ($k === 'bg') {
    $w = 1280; $h = 720;
    $a = img_hex(isset($_GET['a']) ? $_GET['a'] : '', '#0B1830'); $b = img_hex(isset($_GET['b']) ? $_GET['b'] : '', '#050A14'); $p = img_hex(isset($_GET['p']) ? $_GET['p'] : '', '#3B6CF6');
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $c = img_mix($a, $b, $y / $h);
        imagefilledrectangle($im, 0, $y, $w, $y, imagecolorallocate($im, $c[0], $c[1], $c[2]));
    }
    // brilho suave no canto de cima à direita (círculos translúcidos)
    imagealphablending($im, true);
    $pc = img_rgb($p);
    for ($i = 0; $i < 14; $i++) {
        $col = imagecolorallocatealpha($im, $pc[0], $pc[1], $pc[2], 122 + $i * 0.4);
        imagefilledellipse($im, (int)($w * 0.88), (int)($h * -0.05), (int)(900 - $i * 55), (int)(900 - $i * 55), $col);
    }
    img_send($im, $cache);
}

if ($k === 'card') {
    $c1 = img_hex(isset($_GET['c1']) ? $_GET['c1'] : '', '#3B6CF6'); $c2 = img_hex(isset($_GET['c2']) ? $_GET['c2'] : '', '#1D3A8A');
    $w = max(200, min(1000, isset($_GET['w']) ? (int)$_GET['w'] : 640));
    $h = max(120, min(1000, isset($_GET['h']) ? (int)$_GET['h'] : 540));
    $glyphs = array('live' => "\xEF\x88\xB4", 'movies' => "\xEE\xBD\xBE", 'series' => "\xEF\x85\x91", 'search' => "\xEF\x83\x91", 'lists' => "\xEF\x80\x90", 'account' => "\xEF\x89\x9F");
    $kind = isset($_GET['i']) && isset($glyphs[$_GET['i']]) ? $_GET['i'] : 'live';
    $text = mb_strtoupper(mb_substr(strip_tags(isset($_GET['t']) ? (string)$_GET['t'] : ''), 0, 24, 'UTF-8'), 'UTF-8');
    $im = imagecreatetruecolor($w, $h);
    img_gradient($im, $w, $h, $c1, $c2);
    imagealphablending($im, true);
    imagefilledellipse($im, (int)($w * 0.92), (int)($h * 0.08), (int)($h * 0.9), (int)($h * 0.9), imagecolorallocatealpha($im, 255, 255, 255, 116));
    imagefilledellipse($im, (int)($w * 0.04), (int)($h * 0.98), (int)($h * 0.68), (int)($h * 0.68), imagecolorallocatealpha($im, 255, 255, 255, 118));
    $white = imagecolorallocate($im, 255, 255, 255);
    // ícone grande no meio (fonte de ícones do painel) e o nome embaixo
    $isz = min($w, $h) * 0.34;
    img_text_center($im, $isz * 0.75, $w / 2, $h * 0.14 + $isz, $white, $ICONS, $glyphs[$kind]);
    $fs = max(14, min($h * 0.085, $w / 12));
    img_text_center($im, $fs * 0.75, $w / 2, $h * 0.88, $white, $FONT, $text);
    img_send($im, $cache);
}

if ($k === 'tile') {
    $s = img_hex(isset($_GET['s']) ? $_GET['s'] : '', '#131C2E'); $a = img_hex(isset($_GET['a']) ? $_GET['a'] : '', '#3B6CF6');
    $name = trim((string)(isset($_GET['t']) ? $_GET['t'] : ''));
    $words = preg_split('/[\s\-_|:]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach (array_slice($words, 0, 2) as $wd) $ini .= mb_strtoupper(mb_substr($wd, 0, 1, 'UTF-8'), 'UTF-8');
    if ($ini === '') $ini = 'TV';
    $w = 480; $h = 300;
    $im = imagecreatetruecolor($w, $h);
    $top = img_mix($s, '#FFFFFF', 0.12); $bot = img_mix($s, '#000000', 0.25);
    img_gradient($im, $w, $h, sprintf('#%02X%02X%02X', $top[0], $top[1], $top[2]), sprintf('#%02X%02X%02X', $bot[0], $bot[1], $bot[2]));
    imagealphablending($im, true);
    $ac = img_rgb($a);
    imagefilledellipse($im, (int)($w / 2), 118, 150, 150, imagecolorallocatealpha($im, $ac[0], $ac[1], $ac[2], 96));
    img_text_center($im, 38, $w / 2, 138, imagecolorallocate($im, $ac[0], $ac[1], $ac[2]), $FONT, $ini);
    $label = mb_substr($name, 0, 22, 'UTF-8');
    img_text_center($im, 15, $w / 2, 255, imagecolorallocate($im, 235, 240, 250), $FONT, $label);
    img_send($im, $cache);
}

http_response_code(404);
