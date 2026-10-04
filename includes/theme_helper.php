<?php
/**
 * Temas do app: 8 modelos (App UI) + "Meus temas" (personalizações salvas com nome).
 * - Admin: Settings → Temas do App. Revendedor: /revenda → Minha Marca (se o admin permitir).
 * - Tema ativo = modelo base + tema salvo (0 = modelo original).
 * O app recebe em app_details: is_theme (modelo base) e theme_config (personalização).
 */

require_once(__DIR__."/reseller_helper.php"); // panel_config_get / panel_config_set
require_once(__DIR__."/theme_models.php");    // campos de cada modelo
require_once(__DIR__."/media_helper.php");     // cópia das imagens no banco (não somem no deploy)

define('THEME_BESTPRO_ID', '8');
define('THEME_UPLOAD_MAX_BYTES', 5 * 1024 * 1024);
define('THEME_MAX_SAVED', 30);

// Valores originais do modelo (ficha em theme_models.php)
function theme_defaults($theme_id = THEME_BESTPRO_ID){
    return theme_model_defaults($theme_id);
}

// Modelos prontos do app (App UI)
function theme_catalog(){
    return array(
        '1' => array('name' => 'OneUI',         'image' => 'OneUI'),
        '2' => array('name' => 'Glossy',        'image' => 'Glossy'),
        '3' => array('name' => 'Black Panther', 'image' => 'BlackPanther'),
        '4' => array('name' => 'MovieUI',       'image' => 'MovieUI'),
        '5' => array('name' => 'VUI',           'image' => 'VUI'),
        '6' => array('name' => 'ChristmasUI',   'image' => 'ChristmasUI'),
        '7' => array('name' => 'HalloweenUI',   'image' => 'HalloweenUI'),
        '8' => array('name' => 'BestPro',       'image' => ''),
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

function theme_merge_defaults($saved, $theme_id){
    if (!is_array($saved)) $saved = array();
    $out = array();
    foreach (theme_defaults($theme_id) as $key => $default) $out[$key] = isset($saved[$key]) ? (string)$saved[$key] : $default;
    return $out;
}

/* ---------- Marca do revendedor (liga/desliga, nome) ---------- */

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
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_reseller_theme` LIKE 'saved_id'");
    if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_reseller_theme` ADD `saved_id` INT(11) NOT NULL DEFAULT 0");
}

// O admin permite que revendedores usem a própria marca?
function theme_reseller_allowed($mysqli){
    return panel_config_get($mysqli, 'reseller_branding', '1') == '1';
}

function theme_reseller_row($mysqli, $reseller_id){
    theme_reseller_migrate($mysqli);
    $rid = (int)$reseller_id;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_reseller_theme WHERE reseller_id = $rid"));
    if (!$row) $row = array('reseller_id' => $rid, 'enabled' => 0, 'theme_id' => '1', 'saved_id' => 0, 'brand_name' => '', 'settings' => '', 'updated_at' => 0);
    return $row;
}

function theme_reseller_set_basic($mysqli, $reseller_id, $enabled, $brand_name){
    $row = theme_reseller_row($mysqli, $reseller_id);
    $rid = (int)$reseller_id;
    $enabled = $enabled ? 1 : 0;
    $brand_name = mb_substr(trim(strip_tags((string)$brand_name)), 0, 60);
    $theme_id = (string)$row['theme_id'];
    $saved_id = (int)$row['saved_id'];
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_reseller_theme (reseller_id, enabled, theme_id, saved_id, brand_name, updated_at) VALUES (?,?,?,?,?,?)
                                     ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), brand_name = VALUES(brand_name), updated_at = VALUES(updated_at)");
    mysqli_stmt_bind_param($stmt, 'iisisi', $rid, $enabled, $theme_id, $saved_id, $brand_name, $now);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ---------- Meus temas (personalizações salvas com nome) ---------- */

function theme_saved_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    theme_reseller_migrate($mysqli);
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_theme_saved` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `scope` INT(11) NOT NULL DEFAULT 0,      -- 0 = admin | id do revendedor
        `name` VARCHAR(60) NOT NULL,
        `base_theme` VARCHAR(5) NOT NULL,       -- modelo usado como base (1..8)
        `settings` TEXT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_scope` (`scope`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Uma única vez: transforma personalizações antigas em temas salvos
    if (panel_config_get($mysqli, 'theme_saved_migrated', '0') == '1') return;
    $catalog = theme_catalog();
    $now = time();

    // BestPro do admin (configurações antigas em tbl_panel_config)
    $legacy = array(); $has = false;
    foreach (theme_defaults(THEME_BESTPRO_ID) as $key => $default) {
        $v = panel_config_get($mysqli, $key, null);
        if ($v !== null) { $legacy[$key] = $v; $has = true; }
    }
    if ($has) {
        $id = theme_saved_save($mysqli, 0, 0, 'BestPro personalizado', THEME_BESTPRO_ID, theme_merge_defaults($legacy, THEME_BESTPRO_ID));
        if ($id && panel_config_get($mysqli, 'active_saved_theme', '0') == '0') {
            $active = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT is_theme FROM tbl_settings WHERE id = 1"));
            if ($active && (string)$active['is_theme'] === THEME_BESTPRO_ID) panel_config_set($mysqli, 'active_saved_theme', $id);
        }
    }

    // Personalizações por modelo (tbl_theme_custom, se existir)
    $t = mysqli_query($mysqli, "SHOW TABLES LIKE 'tbl_theme_custom'");
    if ($t && mysqli_num_rows($t) > 0) {
        $res = mysqli_query($mysqli, "SELECT * FROM tbl_theme_custom WHERE enabled = 1");
        while ($res && $r = mysqli_fetch_assoc($res)) {
            if (!isset($catalog[$r['theme_id']])) continue;
            theme_saved_save($mysqli, (int)$r['scope'], 0, $catalog[$r['theme_id']]['name'].' personalizado', $r['theme_id'],
                             theme_merge_defaults(json_decode($r['settings'], true), $r['theme_id']));
        }
    }

    // BestPro dos revendedores (tbl_reseller_theme.settings)
    $res = mysqli_query($mysqli, "SELECT reseller_id, theme_id, settings FROM tbl_reseller_theme WHERE settings IS NOT NULL AND settings <> ''");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $id = theme_saved_save($mysqli, (int)$r['reseller_id'], 0, 'Minha marca', THEME_BESTPRO_ID, theme_merge_defaults(json_decode($r['settings'], true), THEME_BESTPRO_ID));
        if ($id && (string)$r['theme_id'] === THEME_BESTPRO_ID) {
            mysqli_query($mysqli, "UPDATE tbl_reseller_theme SET saved_id = ".(int)$id." WHERE reseller_id = ".(int)$r['reseller_id']);
        }
    }
    panel_config_set($mysqli, 'theme_saved_migrated', '1');
}

function theme_saved_list($mysqli, $scope){
    theme_saved_migrate($mysqli);
    $rows = array();
    $scope = (int)$scope;
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_theme_saved WHERE scope = $scope ORDER BY updated_at DESC, id DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $r['settings_arr'] = theme_merge_defaults(json_decode((string)$r['settings'], true), $r['base_theme']);
        $rows[] = $r;
    }
    return $rows;
}

function theme_saved_get($mysqli, $scope, $id){
    theme_saved_migrate($mysqli);
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_theme_saved WHERE id = ? AND scope = ?");
    $id = (int)$id; $scope = (int)$scope;
    mysqli_stmt_bind_param($stmt, 'ii', $id, $scope);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return null;
    $row['settings_arr'] = theme_merge_defaults(json_decode((string)$row['settings'], true), $row['base_theme']);
    return $row;
}

// Cria ($id = 0) ou atualiza um tema salvo. Retorna o id, ou false.
function theme_saved_save($mysqli, $scope, $id, $name, $base_theme, $settings){
    $catalog = theme_catalog();
    $base_theme = isset($catalog[(string)$base_theme]) ? (string)$base_theme : '1';
    $name = mb_substr(trim(strip_tags((string)$name)), 0, 60);
    if ($name == '') return false;
    $json = json_encode($settings);
    $now = time();
    $scope = (int)$scope; $id = (int)$id;
    if ($id > 0) {
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_theme_saved SET name = ?, base_theme = ?, settings = ?, updated_at = ? WHERE id = ? AND scope = ?");
        mysqli_stmt_bind_param($stmt, 'sssiii', $name, $base_theme, $json, $now, $id, $scope);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok ? $id : false;
    }
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_theme_saved (scope, name, base_theme, settings, created_at, updated_at) VALUES (?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'isssii', $scope, $name, $base_theme, $json, $now, $now);
    $ok = mysqli_stmt_execute($stmt);
    $new_id = mysqli_insert_id($mysqli);
    mysqli_stmt_close($stmt);
    return $ok ? $new_id : false;
}

// Apaga arquivos de imagem que não são mais usados por nenhum tema salvo
function theme_cleanup_files($mysqli, $files){
    foreach ($files as $file) {
        if ($file == '') continue;
        $like = '%'.mysqli_real_escape_string($mysqli, $file).'%';
        $used = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_theme_saved WHERE settings LIKE '$like'"));
        if ((int)$used['num'] == 0) theme_delete_file($file);
    }
}

function theme_saved_count($mysqli, $scope){
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_theme_saved WHERE scope = ".(int)$scope));
    return $r ? (int)$r['num'] : 0;
}

// Exclui o tema salvo e as imagens dele. Se estava em uso, volta ao modelo original.
function theme_saved_delete($mysqli, $scope, $id){
    $row = theme_saved_get($mysqli, $scope, $id);
    if (!$row) return false;
    mysqli_query($mysqli, "DELETE FROM tbl_theme_saved WHERE id = ".(int)$id." AND scope = ".(int)$scope);
    // Só apaga a imagem se nenhum outro tema salvo (ex.: criado com "Salvar como novo") ainda usa o mesmo arquivo
    foreach (array('theme_logo', 'theme_bg_image', 'theme_banner') as $key) {
        $file = $row['settings_arr'][$key];
        if ($file == '') continue;
        $like = '%'.mysqli_real_escape_string($mysqli, $file).'%';
        $used = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_theme_saved WHERE settings LIKE '$like'"));
        if ((int)$used['num'] == 0) theme_delete_file($file);
    }
    $active = theme_active($mysqli, $scope);
    if ((int)$active['saved_id'] == (int)$id) theme_set_active($mysqli, $scope, $row['base_theme'], 0);
    return true;
}

/* ---------- Tema ativo (modelo + tema salvo) ---------- */

function theme_active($mysqli, $scope){
    theme_saved_migrate($mysqli);
    $catalog = theme_catalog();
    if ((int)$scope > 0) {
        $row = theme_reseller_row($mysqli, $scope);
        $base = isset($catalog[(string)$row['theme_id']]) ? (string)$row['theme_id'] : '1';
        $saved = (int)$row['saved_id'];
    } else {
        $s = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT is_theme FROM tbl_settings WHERE id = 1"));
        $base = ($s && isset($catalog[(string)$s['is_theme']])) ? (string)$s['is_theme'] : '1';
        $saved = (int)panel_config_get($mysqli, 'active_saved_theme', '0');
    }
    // Tema salvo apagado ou de outro modelo: considera o original
    if ($saved > 0) {
        $t = theme_saved_get($mysqli, $scope, $saved);
        if (!$t || (string)$t['base_theme'] !== $base) $saved = 0;
    }
    return array('base' => $base, 'saved_id' => $saved);
}

function theme_set_active($mysqli, $scope, $base, $saved_id){
    $catalog = theme_catalog();
    $base = isset($catalog[(string)$base]) ? (string)$base : '1';
    $saved_id = (int)$saved_id;
    if ((int)$scope > 0) {
        $row = theme_reseller_row($mysqli, $scope);
        $rid = (int)$scope;
        $now = time();
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_reseller_theme (reseller_id, enabled, theme_id, saved_id, brand_name, updated_at) VALUES (?,?,?,?,?,?)
                                         ON DUPLICATE KEY UPDATE theme_id = VALUES(theme_id), saved_id = VALUES(saved_id), updated_at = VALUES(updated_at)");
        $enabled = (int)$row['enabled'];
        $brand = (string)$row['brand_name'];
        mysqli_stmt_bind_param($stmt, 'iisisi', $rid, $enabled, $base, $saved_id, $brand, $now);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return;
    }
    mysqli_query($mysqli, "UPDATE tbl_settings SET is_theme = '".mysqli_real_escape_string($mysqli, $base)."' WHERE id = 1");
    panel_config_set($mysqli, 'active_saved_theme', $saved_id);
}

/* ---------- Imagens e formulário ---------- */

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
    global $mysqli;
    if (isset($mysqli) && $mysqli) media_backup($mysqli, 'images/'.$name);
    return array('ok' => true, 'file' => $name, 'error' => '');
}

function theme_delete_file($name){
    if ($name != '' && preg_match('/^theme_([rt][0-9]+_)*[a-z]+_[0-9a-f]{12}\.(png|jpg|webp)$/', $name)) {
        $path = __DIR__.'/../images/'.$name;
        if (is_file($path)) @unlink($path);
        global $mysqli;
        if (isset($mysqli) && $mysqli) media_forget($mysqli, 'images/'.$name);
    }
}

// Prefixo dos arquivos de imagem (ex.: theme_ / theme_r12_)
function theme_file_prefix($scope, $theme_id = THEME_BESTPRO_ID){
    $p = 'theme_';
    if ((int)$scope > 0) $p .= 'r'.(int)$scope.'_';
    if ((string)$theme_id !== THEME_BESTPRO_ID) $p .= 't'.(int)$theme_id.'_';
    return $p;
}

/**
 * Processa o formulário do modelo $base (campos da ficha + nome exibido + logo).
 * Retorna array('settings' => array, 'errors' => array). Não grava: quem chama decide onde salvar.
 * $copy_images = true: imagens antigas não são apagadas aqui (podem estar em uso por outro tema salvo).
 */
function theme_process_form($current, $post, $file_prefix, $copy_images, $base){
    $errors = array();
    $out = array_merge(theme_defaults($base), $current);
    $out['theme_show_name'] = !empty($post['theme_show_name']) ? '1' : '0';
    $out['theme_brand_text'] = mb_substr(trim(strip_tags(isset($post['theme_brand_text']) ? $post['theme_brand_text'] : '')), 0, 40);

    $fields = theme_model_schema($base);
    $fields['theme_logo'] = array('type' => 'image');
    foreach ($fields as $key => $f) {
        if ($f['type'] == 'color') {
            $c = theme_valid_color(isset($post[$key]) ? $post[$key] : '');
            if ($c === null) { $errors[] = 'Cor inválida.'; continue; }
            $out[$key] = $c;
        } else if ($f['type'] == 'toggle') {
            $out[$key] = !empty($post[$key]) ? '1' : '0';
        } else if ($f['type'] == 'range') {
            $out[$key] = (string)max((int)$f['min'], min((int)$f['max'], (int)(isset($post[$key]) ? $post[$key] : $f['default'])));
        } else if ($f['type'] == 'image') {
            if (!empty($post['remove_'.$key])) {
                if (!$copy_images) theme_delete_file($current[$key]);
                $out[$key] = '';
                continue;
            }
            $kind = preg_replace('/[^a-z]/', '', str_replace('theme_', '', $key));
            $up = theme_upload($key, $file_prefix.$kind);
            if (!$up['ok']) { $errors[] = $up['error']; continue; }
            if ($up['file'] !== null) {
                if (!$copy_images && isset($current[$key])) theme_delete_file($current[$key]);
                $out[$key] = $up['file'];
            }
        }
    }
    // Mantém só as chaves da ficha do modelo
    $clean = array();
    foreach (theme_defaults($base) as $key => $default) $clean[$key] = isset($out[$key]) ? $out[$key] : $default;
    return array('settings' => $clean, 'errors' => array_values(array_unique($errors)));
}

/* ---------- API ---------- */

// Monta o theme_config enviado ao app (URLs completas das imagens).
// customized = 0: o app usa o modelo original. model_settings: campos da ficha do modelo (cores, liga/desliga, níveis).
function theme_payload_from($t, $base_url, $brand_name, $owner, $theme_id, $customized){
    $img = function ($file) use ($base_url) { return $file != '' ? rtrim($base_url, '/').'/images/'.$file : ''; };
    $t = array_merge(theme_defaults($theme_id), (array)$t);
    $brand = ($customized && $t['theme_brand_text'] != '') ? $t['theme_brand_text'] : $brand_name;
    $model = array();
    foreach (theme_model_schema($theme_id) as $key => $f) {
        if ($f['type'] == 'image' || $f['type'] == 'info') continue;
        $model[$key] = (string)$t[$key];
    }
    return array(
        'theme_id'           => (string)$theme_id,
        'customized'         => $customized ? '1' : '0',
        'brand_owner'        => $owner,            // 'admin' | 'reseller'
        'brand_name'         => $brand,
        'show_brand_name'    => ($customized && $t['theme_show_name'] == '0') ? '0' : '1',
        'logo'               => $customized ? $img($t['theme_logo']) : '',
        'background_image'   => ($customized && isset($t['theme_bg_image'])) ? $img($t['theme_bg_image']) : '',
        'background_overlay' => isset($t['theme_bg_overlay']) ? (string)$t['theme_bg_overlay'] : '60',
        'banner'             => ($customized && isset($t['theme_banner'])) ? $img($t['theme_banner']) : '',
        'model_settings'     => $customized ? $model : new stdClass(),
    );
}

// Payload do tema ativo de um escopo (0 = admin, id = revendedor)
function theme_payload_for_scope($mysqli, $scope, $base_url, $brand_name, $owner){
    $a = theme_active($mysqli, $scope);
    if ($a['saved_id'] > 0) {
        $t = theme_saved_get($mysqli, $scope, $a['saved_id']);
        return theme_payload_from($t['settings_arr'], $base_url, $brand_name, $owner, $a['base'], true);
    }
    return theme_payload_from(theme_defaults($a['base']), $base_url, $brand_name, $owner, $a['base'], false);
}

function theme_api_payload($mysqli, $base_url, $active_theme = null){
    return theme_payload_for_scope($mysqli, 0, $base_url, defined('APP_NAME') ? APP_NAME : '', 'admin');
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

    $rid = (int)$dev['reseller_id'];
    $row = theme_reseller_row($mysqli, $rid);
    if ((int)$row['enabled'] != 1) return null;
    $brand = $row['brand_name'] != '' ? $row['brand_name'] : (defined('APP_NAME') ? APP_NAME : '');
    $a = theme_active($mysqli, $rid);
    return array(
        'is_theme'     => $a['base'],
        'theme_config' => theme_payload_for_scope($mysqli, $rid, $base_url, $brand, 'reseller'),
    );
}
