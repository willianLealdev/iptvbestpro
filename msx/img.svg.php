<?php
/**
 * Imagens do serviço MSX geradas na hora (SVG): fundo, cartões da tela inicial e quadrinho de canal sem logo.
 * Não usa o banco: as cores vêm no endereço (o app.json já monta o endereço com as cores do tema).
 */
require_once(__DIR__."/msx_lib.php");

$k = isset($_GET['k']) ? (string)$_GET['k'] : '';
$col = function ($name, $def) { return msx_hex('#'.(isset($_GET[$name]) ? (string)$_GET[$name] : ''), $def); };
$FONT = 'Roboto,Arial,Helvetica,sans-serif';

if ($k === 'bg') {
    $a = $col('a', '#0B1830'); $b = $col('b', '#050A14'); $p = $col('p', '#3B6CF6');
    msx_svg_out('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1920 1080" width="1920" height="1080">'
        .'<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></linearGradient>'
        .'<radialGradient id="r" cx="0.85" cy="0.05" r="0.7"><stop offset="0" stop-color="'.$p.'" stop-opacity="0.35"/><stop offset="1" stop-color="'.$p.'" stop-opacity="0"/></radialGradient></defs>'
        .'<rect width="1920" height="1080" fill="url(#g)"/><rect width="1920" height="1080" fill="url(#r)"/></svg>');
}

if ($k === 'card') {
    $c1 = $col('c1', '#3B6CF6'); $c2 = $col('c2', '#1D3A8A');
    $w = max(200, min(1280, isset($_GET['w']) ? (int)$_GET['w'] : 640));
    $h = max(120, min(1280, isset($_GET['h']) ? (int)$_GET['h'] : 540));
    $kind = isset($_GET['i']) ? (string)$_GET['i'] : 'live';
    $text = mb_strtoupper(msx_svg_text(isset($_GET['t']) ? $_GET['t'] : '', 24), 'UTF-8');
    $size = min($w, $h) * 0.36;
    $scale = $size / 24;
    $x = ($w - $size) / 2;
    $y = $h * 0.12;
    $fs = max(18, min($h * 0.10, $w / 11));
    msx_svg_out('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">'
        .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$c1.'"/><stop offset="1" stop-color="'.$c2.'"/></linearGradient></defs>'
        .'<rect width="'.$w.'" height="'.$h.'" rx="'.round(min($w, $h) * 0.06).'" fill="url(#g)"/>'
        .'<circle cx="'.round($w * 0.92).'" cy="'.round($h * 0.08).'" r="'.round($h * 0.45).'" fill="#FFFFFF" fill-opacity="0.08"/>'
        .'<circle cx="'.round($w * 0.04).'" cy="'.round($h * 0.98).'" r="'.round($h * 0.34).'" fill="#FFFFFF" fill-opacity="0.07"/>'
        .'<g transform="translate('.round($x, 1).' '.round($y, 1).') scale('.round($scale, 3).')" fill="#FFFFFF" fill-opacity="0.96"><path d="'.msx_svg_icon($kind).'"/></g>'
        .'<text x="'.round($w / 2).'" y="'.round($h * 0.86).'" text-anchor="middle" font-family="'.$FONT.'" font-weight="700" font-size="'.round($fs).'" fill="#FFFFFF">'.$text.'</text>'
        .'</svg>');
}

if ($k === 'tile') {
    $s = $col('s', '#131C2E'); $a = $col('a', '#3B6CF6');
    $name = trim((string)(isset($_GET['t']) ? $_GET['t'] : ''));
    $words = preg_split('/[\s\-_|:]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach (array_slice($words, 0, 2) as $wd) $ini .= mb_strtoupper(mb_substr($wd, 0, 1, 'UTF-8'), 'UTF-8');
    if ($ini === '') $ini = 'TV';
    $label = msx_svg_text($name, 22);
    msx_svg_out('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 300" width="480" height="300">'
        .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.msx_shade($s, 0.12).'"/><stop offset="1" stop-color="'.msx_shade($s, -0.25).'"/></linearGradient></defs>'
        .'<rect width="480" height="300" rx="22" fill="url(#g)"/>'
        .'<circle cx="240" cy="118" r="62" fill="'.$a.'" fill-opacity="0.22"/>'
        .'<text x="240" y="140" text-anchor="middle" font-family="'.$FONT.'" font-weight="700" font-size="64" fill="'.$a.'">'.msx_svg_text($ini, 3).'</text>'
        .'<text x="240" y="250" text-anchor="middle" font-family="'.$FONT.'" font-weight="500" font-size="26" fill="#FFFFFF" fill-opacity="0.85">'.$label.'</text>'
        .'</svg>');
}

http_response_code(404);
echo '';
