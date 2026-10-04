<?php
/**
 * Tema personalizável "BestPro" (App UI = 8) e marca por revendedor.
 * - Admin: configura em Settings → Personalizar Tema (tbl_panel_config).
 * - Revendedor: configura em /revenda → Minha Marca (tbl_reseller_theme), se o admin permitir.
 * O app recebe tudo em app_details -> theme_config (e is_theme do dono do aparelho).
 */

require_once(__DIR__."/reseller_helper.php"); // panel_config_get / panel_config_set

define('THEME_BESTPRO_ID', '8');
define('THEME_UPLOAD_MAX_BYTES', 5 * 1024 * 1024);

function theme_defaults($theme_id = THEME_BESTPRO_ID){
    return array(
        // Nos modelos prontos, começa mantendo as cores originais (troca só logo/fundo/banner)
        'theme_keep_colors'=> ((string)$theme_id === THEME_BESTPRO_ID) ? '0' : '1',
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

// Temas prontos do app (App UI)
function theme_catalog(){
    return array(
        '1' => array('name' => 'OneUI',         'image' => 'OneUI'),
        '2' => array('name' => 'Glossy',        'image' => 'Glossy'),
        '3' => array('name' => 'Black Panther', 'image' => 'BlackPanther'),
        '4' => array('name' => 'MovieUI',       'image' => 'MovieUI'),
        '5' => array('name' => 'VUI',           'image' => 'VUI'),
        '6' => array('name' => 'ChristmasUI',   'image' => 'ChristmasUI'),
        '7' => array('name' => 'HalloweenUI',   'image' => 'HalloweenUI'),
        '8' => array('name' => 'BestPro (personalizável)', 'image' => ''),
    );
}

function theme_presets(){
    return array(
        'Azul BestPro'    => array('#3B6CF6', '#22C55E', '#0B1220', '#131C2E', '#FFFFFF'),
        'Verde Neon'      => array('#16A34A', '#A3E635', '#07130C', '#0F2117', '#FFFFFF'),
        'Vermelho Cine'   => array('#E50914', '#F5C518', '#0A0A0A', '#181818', '#FFFFFF'),
        'Dourado Premium' => array('#D4A017', '#F59E0B', '#0F0D08', '#1C1810', '#FFF8E7'),
        'Roxo Galáxia'    => array('#8B5CF6', '#EC4899', '#0D0A1A', '#1A1530', '#FFFFFF'),
        'Ciano Tech'      => array('#06B6D4', '#3B82F6', '#06131A', '#0E2230', '#FFFFFF'),
    );
}

/* ---------- Marca do revendedor ---------- */

function theme_reseller_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_reseller_theme` (
        `reseller_id` INT(11) NOT NULL,
        `enabled` INT(1) NOT NULL DEFAULT 0,
        `theme_id` VARCHAR(5) NOT NULL DEFAULT '8',
        `brand_name` VARCHAR(60) NOT NULL DEFAULT '',
        `settings` TEXT NULL,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`reseller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// O admin permite que revendedores usem a própria marca?
function theme_reseller_allowed($mysqli){
    return panel_config_get($mysqli, 'reseller_branding', '1') == '1';
}

function theme_reseller_row($mysqli, $reseller_id){
    theme_reseller_migrate($mysqli);
    $rid = (int)$reseller_id;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_reseller_theme WHERE reseller_id = $rid"));
    if (!$row) $row = array('reseller_id' => $rid, 'enabled' => 0, 'theme_id' => THEME_BESTPRO_ID, 'brand_name' => '', 'settings' => '', 'updated_at' => 0);
    return $row;
}

function theme_reseller_save_row($mysqli, $reseller_id, $enabled, $theme_id, $brand_name, $settings){
    theme_reseller_migrate($mysqli);
    $rid = (int)$reseller_id;
    $enabled = $enabled ? 1 : 0;
    $catalog = theme_catalog();
    $theme_id = isset($catalog[$theme_id]) ? (string)$theme_id : THEME_BESTPRO_ID;
    $brand_name = mb_substr(trim(strip_tags((string)$brand_name)), 0, 60);
    $json = json_encode($settings);
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_reseller_theme (reseller_id, enabled, theme_id, brand_name, settings, updated_at) VALUES (?,?,?,?,?,?)
                                     ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), theme_id = VALUES(theme_id), brand_name = VALUES(brand_name), settings = VALUES(settings), updated_at = VALUES(updated_at)");
    mysqli_stmt_bind_param($stmt, 'iisssi', $rid, $enabled, $theme_id, $brand_name, $json, $now);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ---------- Personalização por modelo (temas 1 a 7) ---------- */

function theme_custom_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_theme_custom` (
        `scope` INT(11) NOT NULL,              -- 0 = admin | id do revendedor
        `theme_id` VARCHAR(5) NOT NULL,
        `enabled` INT(1) NOT NULL DEFAULT 0,   -- personalização ligada neste modelo
        `settings` TEXT NULL,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`scope`, `theme_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function theme_custom_row($mysqli, $scope, $theme_id){
    theme_custom_migrate($mysqli);
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_theme_custom WHERE scope = ? AND theme_id = ?");
    $scope = (int)$scope;
    $theme_id = (string)$theme_id;
    mysqli_stmt_bind_param($stmt, 'is', $scope, $theme_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ? $row : array('scope' => $scope, 'theme_id' => $theme_id, 'enabled' => 0, 'settings' => '', 'updated_at' => 0);
}

function theme_custom_save($mysqli, $scope, $theme_id, $enabled, $settings){
    theme_custom_migrate($mysqli);
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_theme_custom (scope, theme_id, enabled, settings, updated_at) VALUES (?,?,?,?,?)
                                     ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), settings = VALUES(settings), updated_at = VALUES(updated_at)");
    $scope = (int)$scope;
    $theme_id = (string)$theme_id;
    $enabled = $enabled ? 1 : 0;
    $json = json_encode($settings);
    $now = time();
    mysqli_stmt_bind_param($stmt, 'isisi', $scope, $theme_id, $enabled, $json, $now);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// A personalização está ligada neste modelo? (BestPro é sempre personalizado)
function theme_is_customized($mysqli, $scope, $theme_id){
    if ((string)$theme_id === THEME_BESTPRO_ID) return true;
    $row = theme_custom_row($mysqli, $scope, $theme_id);
    return (int)$row['enabled'] == 1;
}

/* ---------- Leitura / gravação (escopo 0 = admin, id = revendedor) ---------- */

function theme_merge_defaults($saved, $theme_id){
    if (!is_array($saved)) $saved = array();
    $out = array();
    foreach (theme_defaults($theme_id) as $key => $default) $out[$key] = isset($saved[$key]) ? (string)$saved[$key] : $default;
    return $out;
}

function theme_get($mysqli, $scope = 0, $theme_id = THEME_BESTPRO_ID){
    $theme_id = (string)$theme_id;
    // Modelos prontos: personalização própria de cada modelo
    if ($theme_id !== THEME_BESTPRO_ID) {
        $row = theme_custom_row($mysqli, $scope, $theme_id);
        return theme_merge_defaults($row['settings'] != '' ? json_decode($row['settings'], true) : array(), $theme_id);
    }
    // BestPro
    if ((int)$scope > 0) {
        $row = theme_reseller_row($mysqli, $scope);
        return theme_merge_defaults($row['settings'] != '' ? json_decode($row['settings'], true) : array(), $theme_id);
    }
    $out = array();
    foreach (theme_defaults($theme_id) as $key => $default) $out[$key] = panel_config_get($mysqli, $key, $default);
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
    if ($name != '' && preg_match('/^theme_([rt][0-9]+_)*[a-z]+_[0-9a-f]{12}\.(png|jpg|webp)$/', $name)) {
        $path = __DIR__.'/../images/'.$name;
        if (is_file($path)) @unlink($path);
    }
}

/**
 * Processa o formulário de personalização (cores, sliders, imagens).
 * Retorna array('settings' => array, 'errors' => array). Não grava: quem chama decide onde salvar.
 */
function theme_process_form($current, $post, $file_prefix){
    $errors = array();
    $out = $current;
    foreach (array('theme_primary', 'theme_accent', 'theme_background', 'theme_surface', 'theme_text') as $key) {
        $c = theme_valid_color(isset($post[$key]) ? $post[$key] : '');
        if ($c === null) { $errors[] = 'Cor inválida.'; continue; }
        $out[$key] = $c;
    }
    $out['theme_keep_colors'] = !empty($post['theme_keep_colors']) ? '1' : '0';
    $out['theme_bg_overlay'] = (string)max(0, min(90, (int)(isset($post['theme_bg_overlay']) ? $post['theme_bg_overlay'] : 60)));
    $out['theme_corner'] = (string)max(0, min(32, (int)(isset($post['theme_corner']) ? $post['theme_corner'] : 14)));

    foreach (array('theme_logo' => 'logo', 'theme_bg_image' => 'bg', 'theme_banner' => 'banner') as $key => $kind) {
        if (!empty($post['remove_'.$key])) {
            theme_delete_file($current[$key]);
            $out[$key] = '';
            continue;
        }
        $up = theme_upload($key, $file_prefix.$kind);
        if (!$up['ok']) { $errors[] = $up['error']; continue; }
        if ($up['file'] !== null) {
            theme_delete_file($current[$key]);
            $out[$key] = $up['file'];
        }
    }
    return array('settings' => $out, 'errors' => array_values(array_unique($errors)));
}

function theme_save_admin($mysqli, $settings){
    foreach (theme_defaults() as $key => $default) panel_config_set($mysqli, $key, isset($settings[$key]) ? $settings[$key] : $default);
}

/* ---------- API ---------- */

// Prefixo dos arquivos de imagem por escopo/modelo (ex.: theme_ / theme_t3_ / theme_r12_ / theme_r12_t3_)
function theme_file_prefix($scope, $theme_id){
    $p = 'theme_';
    if ((int)$scope > 0) $p .= 'r'.(int)$scope.'_';
    if ((string)$theme_id !== THEME_BESTPRO_ID) $p .= 't'.(int)$theme_id.'_';
    return $p;
}

// Monta o theme_config enviado ao app (URLs completas das imagens)
// customized = 0: o app usa o modelo original. keep_original_colors = 1: troca só logo/fundo/banner/nome.
function theme_payload_from($t, $base_url, $brand_name, $owner, $theme_id = THEME_BESTPRO_ID, $customized = true){
    $img = function ($file) use ($base_url) { return $file != '' ? rtrim($base_url, '/').'/images/'.$file : ''; };
    return array(
        'theme_id'           => (string)$theme_id,
        'customized'         => $customized ? '1' : '0',
        'keep_original_colors' => ($customized && $t['theme_keep_colors'] == '1') ? '1' : '0',
        'brand_owner'        => $owner,            // 'admin' | 'reseller'
        'brand_name'         => $brand_name,
        'primary_color'      => $t['theme_primary'],
        'accent_color'       => $t['theme_accent'],
        'background_color'   => $t['theme_background'],
        'surface_color'      => $t['theme_surface'],
        'text_color'         => $t['theme_text'],
        'background_image'   => $img($t['theme_bg_image']),
        'background_overlay' => (string)max(0, min(90, (int)$t['theme_bg_overlay'])),
        'logo'               => $img($t['theme_logo']),
        'banner'             => $img($t['theme_banner']),
        'corner_radius'      => (string)max(0, min(32, (int)$t['theme_corner'])),
    );
}

// Visual do admin para o tema ativo do app (Settings → App UI)
function theme_api_payload($mysqli, $base_url, $active_theme = THEME_BESTPRO_ID){
    $active_theme = (string)$active_theme;
    $catalog = theme_catalog();
    if (!isset($catalog[$active_theme])) $active_theme = '1';
    return theme_payload_from(theme_get($mysqli, 0, $active_theme), $base_url, defined('APP_NAME') ? APP_NAME : '', 'admin',
                              $active_theme, theme_is_customized($mysqli, 0, $active_theme));
}

/**
 * Marca do revendedor para o aparelho: se o aparelho é de um revendedor com marca própria ativa
 * (e o admin permite), devolve array('is_theme' => id, 'theme_config' => payload). Senão null.
 */
function theme_for_device($mysqli, $device_id, $application_id, $base_url){
    if ($device_id == '' || !theme_reseller_allowed($mysqli)) return null;
    $stmt = mysqli_prepare($mysqli, "SELECT reseller_id FROM tbl_devices WHERE device_id = ? AND application_id = ? AND owner = 'reseller' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'ss', $device_id, $application_id);
    mysqli_stmt_execute($stmt);
    $dev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$dev || (int)$dev['reseller_id'] <= 0) return null;

    $row = theme_reseller_row($mysqli, $dev['reseller_id']);
    if ((int)$row['enabled'] != 1) return null;
    $brand = $row['brand_name'] != '' ? $row['brand_name'] : (defined('APP_NAME') ? APP_NAME : '');
    $rid = (int)$dev['reseller_id'];
    return array(
        'is_theme'     => $row['theme_id'],
        'theme_config' => theme_payload_from(theme_get($mysqli, $rid, $row['theme_id']), $base_url, $brand, 'reseller',
                                             $row['theme_id'], theme_is_customized($mysqli, $rid, $row['theme_id'])),
    );
}
