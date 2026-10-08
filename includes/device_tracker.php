<?php
/**
 * Registro de dispositivos (instalações do app) + país por IP.
 * Tabela criada automaticamente na primeira execução.
 */

define('DEVICES_TZ', 'America/Sao_Paulo');
define('DEVICES_GEO_DB', '/opt/geo/country.mmdb');
define('DEVICES_GEO_AUTOLOAD', '/opt/geo/vendor/autoload.php');

function devices_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;

    $sql = "CREATE TABLE IF NOT EXISTS `tbl_devices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `device_id` VARCHAR(191) NOT NULL,
        `application_id` VARCHAR(191) NOT NULL DEFAULT '',
        `app_name` VARCHAR(100) NOT NULL DEFAULT '',
        `app_version` VARCHAR(30) NOT NULL DEFAULT '',
        `platform` VARCHAR(30) NOT NULL DEFAULT 'android',
        `device_model` VARCHAR(100) NOT NULL DEFAULT '',
        `os_version` VARCHAR(30) NOT NULL DEFAULT '',
        `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
        `country_code` CHAR(2) NOT NULL DEFAULT '',
        `country_name` VARCHAR(80) NOT NULL DEFAULT '',
        `first_seen` INT(11) NOT NULL DEFAULT 0,
        `last_seen` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_device_app` (`device_id`, `application_id`),
        KEY `idx_first_seen` (`first_seen`),
        KEY `idx_last_seen` (`last_seen`),
        KEY `idx_country` (`country_code`),
        KEY `idx_platform` (`platform`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    mysqli_query($mysqli, $sql);
}

// IP real do cliente (o Easypanel/Traefik repassa em X-Forwarded-For)
function devices_client_ip(){
    $candidates = array();
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) $candidates[] = trim($ip);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[] = $_SERVER['HTTP_X_REAL_IP'];
    if (!empty($_SERVER['REMOTE_ADDR'])) $candidates[] = $_SERVER['REMOTE_ADDR'];

    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    return isset($candidates[0]) ? $candidates[0] : '';
}

// Retorna array('code' => 'BR', 'name' => 'Brazil') ou códigos vazios
function devices_country($ip){
    $empty = array('code' => '', 'name' => '');

    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && preg_match('/^[A-Z]{2}$/', $_SERVER['HTTP_CF_IPCOUNTRY']) && $_SERVER['HTTP_CF_IPCOUNTRY'] != 'XX') {
        return array('code' => $_SERVER['HTTP_CF_IPCOUNTRY'], 'name' => '');
    }
    if ($ip == '' || !file_exists(DEVICES_GEO_DB) || !file_exists(DEVICES_GEO_AUTOLOAD)) return $empty;

    try {
        require_once DEVICES_GEO_AUTOLOAD;
        static $reader = null;
        if ($reader === null) $reader = new \MaxMind\Db\Reader(DEVICES_GEO_DB);
        $record = $reader->get($ip);
        if (!empty($record['country']['iso_code'])) {
            return array(
                'code' => strtoupper($record['country']['iso_code']),
                'name' => isset($record['country']['names']['en']) ? $record['country']['names']['en'] : ''
            );
        }
    } catch (\Throwable $e) {
        error_log('device_tracker geo: '.$e->getMessage());
    }
    return $empty;
}

function devices_normalize_platform($platform){
    $p = strtolower(trim((string)$platform));
    $map = array(
        'android' => 'android', 'mobile' => 'android', 'phone' => 'android',
        'tablet' => 'tablet',
        'tv' => 'android_tv', 'androidtv' => 'android_tv', 'android_tv' => 'android_tv', 'tvbox' => 'android_tv', 'box' => 'android_tv',
        'firestick' => 'firestick', 'firetv' => 'firestick', 'fire_tv' => 'firestick', 'amazon' => 'firestick',
        'chromebook' => 'chromebook',
        'auto' => 'android_auto', 'android_auto' => 'android_auto',
        'lg_tv' => 'lg_tv', 'webos' => 'lg_tv', 'lg' => 'lg_tv',
        'samsung_tv' => 'samsung_tv', 'tizen' => 'samsung_tv', 'samsung' => 'samsung_tv',
        'roku' => 'roku', 'roku_tv' => 'roku',
        'whale_tv' => 'whale_tv', 'whale' => 'whale_tv',
    );
    return isset($map[$p]) ? $map[$p] : 'android';
}

function devices_platform_labels(){
    return array(
        'android'      => array('label' => 'Android Celular', 'icon' => 'ri-smartphone-line', 'color' => '#22c55e'),
        'tablet'       => array('label' => 'Tablet',          'icon' => 'ri-tablet-line',     'color' => '#3b82f6'),
        'android_tv'   => array('label' => 'Android TV / Box','icon' => 'ri-tv-2-line',       'color' => '#a855f7'),
        'firestick'    => array('label' => 'Fire TV Stick',   'icon' => 'ri-fire-line',       'color' => '#f97316'),
        'chromebook'   => array('label' => 'Chromebook',      'icon' => 'ri-macbook-line',    'color' => '#0ea5e9'),
        'android_auto' => array('label' => 'Android Auto',    'icon' => 'ri-car-line',        'color' => '#64748b'),
        'lg_tv'        => array('label' => 'TV LG (webOS)',   'icon' => 'ri-tv-line',         'color' => '#a50034'),
        'samsung_tv'   => array('label' => 'TV Samsung',      'icon' => 'ri-tv-line',         'color' => '#1428a0'),
        'roku'         => array('label' => 'Roku',            'icon' => 'ri-tv-line',         'color' => '#6c3c97'),
        'whale_tv'     => array('label' => 'TV Whale (Philips, Sharp, Toshiba...)', 'icon' => 'ri-tv-line', 'color' => '#0284c7'),
    );
}

function devices_clean($value, $max){
    return mb_substr(trim(strip_tags((string)$value)), 0, $max);
}

/**
 * Registra/atualiza o aparelho. $data vem do JSON enviado pelo app.
 * Campos aceitos: device_id (obrigatório), application_id, app_name, app_version, platform, device_model, os_version
 */
function devices_register($mysqli, $data){
    if (empty($data['device_id'])) return false;
    devices_migrate($mysqli);

    $ip = devices_client_ip();
    $country = devices_country($ip);
    $now = time();

    $fields = array(
        'device_id'      => devices_clean($data['device_id'], 191),
        'application_id' => devices_clean(isset($data['application_id']) ? $data['application_id'] : '', 191),
        'app_name'       => devices_clean(isset($data['app_name']) ? $data['app_name'] : '', 100),
        'app_version'    => devices_clean(isset($data['app_version']) ? $data['app_version'] : '', 30),
        'platform'       => devices_normalize_platform(isset($data['platform']) ? $data['platform'] : ''),
        'device_model'   => devices_clean(isset($data['device_model']) ? $data['device_model'] : '', 100),
        'os_version'     => devices_clean(isset($data['os_version']) ? $data['os_version'] : '', 30),
        'ip_address'     => devices_clean($ip, 45),
        'country_code'   => $country['code'],
        'country_name'   => devices_clean($country['name'], 80),
    );

    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_devices
        (device_id, application_id, app_name, app_version, platform, device_model, os_version, ip_address, country_code, country_name, first_seen, last_seen)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            app_name = IF(VALUES(app_name) <> '', VALUES(app_name), app_name),
            app_version = IF(VALUES(app_version) <> '', VALUES(app_version), app_version),
            platform = VALUES(platform),
            device_model = IF(VALUES(device_model) <> '', VALUES(device_model), device_model),
            os_version = IF(VALUES(os_version) <> '', VALUES(os_version), os_version),
            ip_address = VALUES(ip_address),
            country_code = IF(VALUES(country_code) <> '', VALUES(country_code), country_code),
            country_name = IF(VALUES(country_name) <> '', VALUES(country_name), country_name),
            last_seen = VALUES(last_seen)");
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'ssssssssssii',
        $fields['device_id'], $fields['application_id'], $fields['app_name'], $fields['app_version'],
        $fields['platform'], $fields['device_model'], $fields['os_version'], $fields['ip_address'],
        $fields['country_code'], $fields['country_name'], $now, $now);
    $ok = mysqli_stmt_execute($stmt);
    $is_new = $ok && mysqli_stmt_affected_rows($stmt) == 1; // 1 = aparelho novo no painel (2 = já existia)
    mysqli_stmt_close($stmt);
    // Aparelho novo (ou que foi excluído do painel): nenhuma lista antiga com este ID pode voltar
    if ($is_new) {
        $del = mysqli_prepare($mysqli, "DELETE FROM tbl_users WHERE device_id = ?");
        if ($del) {
            mysqli_stmt_bind_param($del, 's', $fields['device_id']);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
        }
    }
    return $ok;
}

// Início do dia de hoje (timestamp) no fuso do painel
function devices_today_start(){
    $d = new DateTime('today', new DateTimeZone(DEVICES_TZ));
    return $d->getTimestamp();
}
