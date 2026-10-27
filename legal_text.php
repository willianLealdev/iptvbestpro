<?php
/**
 * Textos legais em texto simples para o app iPhone / iPad / Apple TV (a Apple TV não abre links):
 * política de privacidade e termos de uso, no idioma pedido (?lang=pt|en|es). Públicos, só leitura.
 */
require_once("includes/db_helper.php");

while (ob_get_level() > 0) ob_end_clean();
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$lang = isset($_GET['lang']) && in_array($_GET['lang'], array('pt', 'en', 'es'), true) ? $_GET['lang'] : 'en';

function lg_text($html){
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(br\s*/?|/p|/li|/h[1-6]|/ul|/section)>#i', "\n", $html);
    $html = preg_replace('#<li[^>]*>#i', '• ', $html);
    $html = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $html = preg_replace("/[ \t]+/", ' ', $html);
    return trim(preg_replace("/\n{3,}/", "\n\n", $html));
}
function lg_section($html, $lang){
    if (preg_match('#<section id="'.$lang.'">(.*?)</section>#is', $html, $m)) return $m[1];
    return $html;
}

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT app_privacy_policy, app_terms FROM tbl_settings WHERE id = 1"));
$pp = stripslashes((string)(isset($row['app_privacy_policy']) ? $row['app_privacy_policy'] : ''));
$tt = stripslashes((string)(isset($row['app_terms']) ? $row['app_terms'] : ''));
if (trim(strip_tags($pp)) === '') { require_once("includes/privacy_default.php"); $pp = lg_section(privacy_default_html(APP_NAME, 'https://iptvbestpro.com'), $lang); }
if (trim(strip_tags($tt)) === '') { require_once("includes/terms_default.php"); $tt = lg_section(terms_default_html(APP_NAME, 'https://iptvbestpro.com'), $lang); }

echo json_encode(array('lang' => $lang, 'privacy' => lg_text($pp), 'terms' => lg_text($tt)), JSON_UNESCAPED_UNICODE);
