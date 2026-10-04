<?php
/**
 * Tema personalizável "BestPro" (App UI = 8): cores e imagens definidas no painel
 * e enviadas ao app pela API (app_details -> theme_config).
 */

require_once(__DIR__."/reseller_helper.php"); // panel_config_get / panel_config_set

define('THEME_BESTPRO_ID', '8');
define('THEME_UPLOAD_MAX_BYTES', 5 * 1024 * 1024);

function theme_defaults(){
    return array(
        'theme_primary'    => '#3B6CF6', // botões, item selecionado, foco do controle remoto
        'theme_accent'     => '#22C55E', // destaques, selos "AO VIVO", progresso
        'theme_background' => '#0B1220', // fundo das telas
        'theme_surface'    => '#131C2E', // cartões, menus e barras
        'theme_text'       => '#FFFFFF', // texto principal
        'theme_bg_image'   => '',        // imagem de fundo (arquivo em images/)
        'theme_bg_overlay' => '60',      // escurecimento da imagem de fundo (0-90 %)
        'theme_logo'       => '',        // logo da tela inicial (vazio = logo do app)
        'theme_banner'     => '',        // banner/destaque da tela inicial
        'theme_corner'     => '14',      // arredondamento dos cartões (px)
    );
}

function theme_presets(){
    return array(
        'Azul BestPro'  => array('#3B6CF6', '#22C55E', '#0B1220', '#131C2E', '#FFFFFF'),
        'Verde Neon'    => array('#16A34A', '#A3E635', '#07130C', '#0F2117', '#FFFFFF'),
        'Vermelho Cine' => array('#E50914', '#F5C518', '#0A0A0A', '#181818', '#FFFFFF'),
        'Dourado Premium' => array('#D4A017', '#F59E0B', '#0F0D08', '#1C1810', '#FFF8E7'),
        'Roxo Galáxia'  => array('#8B5CF6', '#EC4899', '#0D0A1A', '#1A1530', '#FFFFFF'),
        'Ciano Tech'    => array('#06B6D4', '#3B82F6', '#06131A', '#0E2230', '#FFFFFF'),
    );
}

function theme_get($mysqli){
    $out = array();
    foreach (theme_defaults() as $key => $default) $out[$key] = panel_config_get($mysqli, $key, $default);
    return $out;
}

function theme_valid_color($value){
    $value = strtoupper(trim((string)$value));
    if (preg_match('/^#[0-9A-F]{6}$/', $value)) return $value;
    if (preg_match('/^#([0-9A-F])([0-9A-F])([0-9A-F])$/', $value, $m)) return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
    return null;
}

/**
 * Recebe um upload de imagem (png/jpg/webp, até 5 MB) e salva em images/.
 * Retorna array('ok' => bool, 'file' => nome|null, 'error' => string). Sem arquivo enviado: ok=true, file=null.
 */
function theme_upload($field, $prefix){
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] == UPLOAD_ERR_NO_FILE) return array('ok' => true, 'file' => null, 'error' => '');
    $f = $_FILES[$field];
    if ($f['error'] != UPLOAD_ERR_OK) return array('ok' => false, 'file' => null, 'error' => 'Falha no envio da imagem.');
    if ($f['size'] > THEME_UPLOAD_MAX_BYTES) return array('ok' => false, 'file' => null, 'error' => 'Imagem maior que 5 MB.');
    $info = @getimagesize($f['tmp_name']);
    $types = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp');
    if (!$info || !isset($types[$info[2]])) return array('ok' => false, 'file' => null, 'error' => 'Formato inválido. Use PNG, JPG ou WEBP.');
    $name = $prefix.'_'.bin2hex(random_bytes(6)).'.'.$types[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], __DIR__.'/../images/'.$name)) return array('ok' => false, 'file' => null, 'error' => 'Não foi possível salvar a imagem.');
    return array('ok' => true, 'file' => $name, 'error' => '');
}

function theme_delete_file($name){
    if ($name != '' && preg_match('/^theme_[a-z]+_[0-9a-f]{12}\.(png|jpg|webp)$/', $name)) {
        $path = __DIR__.'/../images/'.$name;
        if (is_file($path)) @unlink($path);
    }
}

// Dados enviados ao app (URLs completas das imagens)
function theme_api_payload($mysqli, $base_url){
    $t = theme_get($mysqli);
    $img = function ($file) use ($base_url) { return $file != '' ? rtrim($base_url, '/').'/images/'.$file : ''; };
    return array(
        'theme_id'         => THEME_BESTPRO_ID,
        'primary_color'    => $t['theme_primary'],
        'accent_color'     => $t['theme_accent'],
        'background_color' => $t['theme_background'],
        'surface_color'    => $t['theme_surface'],
        'text_color'       => $t['theme_text'],
        'background_image' => $img($t['theme_bg_image']),
        'background_overlay' => (string)max(0, min(90, (int)$t['theme_bg_overlay'])),
        'logo'             => $img($t['theme_logo']),
        'banner'           => $img($t['theme_banner']),
        'corner_radius'    => (string)max(0, min(32, (int)$t['theme_corner'])),
    );
}
