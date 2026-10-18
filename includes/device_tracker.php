<?php
/**
 * Registro de dispositivos (instalações do app) + país por IP.
 * Tabela criada automaticamente na primeira execução.
 */

define('DEVICES_TZ', 'America/Sao_Paulo');
define('DEVICES_GEO_DB', '/opt/geo/country.mmdb');
define('DEVICES_GEO_AUTOLOAD', '/opt/geo/vendor/autoload.php');
define('DEVICES_ASN_DB', '/opt/geo/asn.mmdb');

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

    // Provedor de internet (empresa dona do IP), origem provável e em quantos dias diferentes o app foi aberto
    foreach (array('asn_org' => "VARCHAR(120) NOT NULL DEFAULT ''", 'origin' => "VARCHAR(20) NOT NULL DEFAULT ''", 'days_seen' => "INT(11) NOT NULL DEFAULT 1") as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_devices` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD `$name` $definition");
    }
}

// Empresa dona do IP (ex.: "Claro S.A.", "Google LLC", "Amazon.com, Inc."). Vazio quando a base não existe.
function devices_asn_org($ip){
    if ($ip == '' || !file_exists(DEVICES_ASN_DB) || !file_exists(DEVICES_GEO_AUTOLOAD)) return '';
    try {
        require_once DEVICES_GEO_AUTOLOAD;
        static $reader = null;
        if ($reader === null) $reader = new \MaxMind\Db\Reader(DEVICES_ASN_DB);
        $r = $reader->get($ip);
        if (!empty($r['autonomous_system_organization'])) return mb_substr((string)$r['autonomous_system_organization'], 0, 120);
    } catch (\Throwable $e) {
        error_log('device_tracker asn: '.$e->getMessage());
    }
    return '';
}

/**
 * Origem provável do aparelho, pela empresa dona do IP e pelo modelo:
 *  emulator | store_google | store_amazon | store_roku | store_apple | datacenter (servidor/robô) | real (internet de casa/celular) | '' (sem dados)
 * As lojas testam os apps em aparelhos dentro da rede delas (Google, Amazon, Roku, Apple); cliente de verdade usa internet de casa ou do celular.
 */
function devices_origin_guess($model, $asn_org){
    $m = strtolower((string)$model);
    $o = strtolower((string)$asn_org);
    if (preg_match('/sdk_gphone|sdk_google|android sdk built|emulator|emulador|generic_x86|genymotion|headlesschrome|simulator/', $m)) return 'emulator';
    if ($o == '') return '';
    if (strpos($o, 'google') !== false) return 'store_google';
    if (strpos($o, 'amazon') !== false || preg_match('/\baws\b/', $o)) return 'store_amazon';
    if (strpos($o, 'roku') !== false) return 'store_roku';
    if (preg_match('/\bapple\b/', $o)) return 'store_apple';
    if (preg_match('/microsoft|azure|digitalocean|ovh|hetzner|linode|akamai|oracle|alibaba|tencent|huawei cloud|vultr|choopa|m247|datacamp|leaseweb|contabo|scaleway|cloudflare|hostinger|zenlayer|psychz|quadranet|colocrossing|godaddy|ionos|hosting|datacenter|data center|servers?\b|cloud/', $o)) return 'datacenter';
    return 'real';
}

/**
 * Uso do app pelo último acesso (o app não avisa quando é removido; quem some por muitos dias provavelmente removeu):
 * using = abriu nas últimas 24 horas | idle = 1 a 15 dias sem abrir | gone = mais de 15 dias sem abrir
 */
define('DEVICES_USING_DAYS', 1);
define('DEVICES_GONE_DAYS', 15);
function devices_usage_sql($key, $alias = 'd'){
    $now = time();
    $a = $now - DEVICES_USING_DAYS * 86400;
    $b = $now - DEVICES_GONE_DAYS * 86400;
    $has_list = "EXISTS (SELECT 1 FROM tbl_users u WHERE u.device_id = $alias.device_id AND u.status = '1')";
    if ($key == 'using') return "($alias.last_seen >= $a AND $has_list)";
    if ($key == 'nolist') return "($alias.last_seen >= $a AND NOT $has_list)";
    if ($key == 'idle') return "($alias.last_seen < $a AND $alias.last_seen >= $b)";
    if ($key == 'gone') return "$alias.last_seen < $b";
    return '1=1';
}
function devices_usage_info($last_seen){
    $days = $last_seen > 0 ? (int)floor((time() - (int)$last_seen) / 86400) : 999;
    if ($days < DEVICES_USING_DAYS) return array('using', 'Usando', 'us-using', 'ri-checkbox-circle-line');
    if ($days < DEVICES_GONE_DAYS) return array('idle', $days == 1 ? 'Parado há 1 dia' : 'Parado há '.$days.' dias', 'us-idle', 'ri-pause-circle-line');
    return array('gone', 'Provavelmente removeu', 'us-gone', 'ri-delete-bin-6-line');
}

function devices_origin_info($origin){
    $map = array(
        'real'         => array('Provável cliente real', 'or-real', 'ri-user-heart-line'),
        'store_google' => array('Teste do Google (loja)', 'or-store', 'ri-google-play-line'),
        'store_amazon' => array('Teste da Amazon (loja)', 'or-store', 'ri-amazon-line'),
        'store_roku'   => array('Teste da Roku (loja)', 'or-store', 'ri-tv-line'),
        'store_apple'  => array('Teste da Apple (loja)', 'or-store', 'ri-apple-line'),
        'datacenter'   => array('Servidor / robô', 'or-bot', 'ri-robot-2-line'),
        'emulator'     => array('Emulador', 'or-bot', 'ri-bug-line'),
    );
    return isset($map[$origin]) ? $map[$origin] : array('Sem dados', 'or-none', 'ri-question-line');
}

/**
 * IP real do cliente, sem confiar em cabeçalho que o próprio cliente pode inventar.
 * Caminho: cliente -> Cloudflare -> Traefik (Easypanel) -> Apache.
 *  - "quem conectou" = REMOTE_ADDR; se for o proxy interno (IP privado), é o último IP do X-Forwarded-For,
 *    que é o IP que o Traefik viu (ele acrescenta no fim; o que vem antes pode ter sido inventado).
 *  - se quem conectou é um servidor da Cloudflare, vale o CF-Connecting-IP (a Cloudflare sobrescreve esse cabeçalho);
 *    se não é (alguém acessando o servidor direto), vale o IP de quem conectou — e o CF-Connecting-IP é ignorado.
 * Usado no bloqueio de tentativas de login, no limite de códigos de ativação e no país do aparelho.
 */
function devices_client_ip(){
    $pub = function ($ip) { return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE); };
    $peer = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
    if (!$pub($peer) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_values(array_filter(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])), function ($x) { return filter_var($x, FILTER_VALIDATE_IP); }));
        // do fim para o começo: o primeiro IP público é quem conectou no proxy
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if ($pub($chain[$i])) { $peer = $chain[$i]; break; }
        }
    }
    if ($peer !== '' && devices_is_cloudflare($peer) && !empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var(trim($_SERVER['HTTP_CF_CONNECTING_IP']), FILTER_VALIDATE_IP)) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    return $peer;
}

// Faixas de IP da Cloudflare (https://www.cloudflare.com/ips/)
function devices_is_cloudflare($ip){
    static $ranges = array(
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    );
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    foreach ($ranges as $r) {
        list($net, $bits) = explode('/', $r);
        $nb = @inet_pton($net);
        if ($nb === false || strlen($nb) != strlen($bin)) continue;
        $bits = (int)$bits;
        $bytes = intdiv($bits, 8);
        if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) continue;
        $rem = $bits % 8;
        if ($rem == 0) return true;
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        if ((ord($bin[$bytes]) & $mask) === (ord($nb[$bytes]) & $mask)) return true;
    }
    return false;
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
        'titan_os' => 'titan_os', 'titan' => 'titan_os', 'titanos' => 'titan_os',
        'iphone' => 'iphone', 'ios' => 'iphone',
        'ipad' => 'ipad', 'ipados' => 'ipad',
        'apple_tv' => 'apple_tv', 'tvos' => 'apple_tv', 'appletv' => 'apple_tv',
        'windows' => 'windows', 'win' => 'windows', 'pc' => 'windows',
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
        'titan_os'     => array('label' => 'TV Titan OS (Philips, JVC)', 'icon' => 'ri-tv-line', 'color' => '#e11d48'),
        'iphone'       => array('label' => 'iPhone',          'icon' => 'ri-apple-line',      'color' => '#111827'),
        'ipad'         => array('label' => 'iPad',            'icon' => 'ri-tablet-line',     'color' => '#374151'),
        'apple_tv'     => array('label' => 'Apple TV',        'icon' => 'ri-tv-2-line',       'color' => '#6b7280'),
        'windows'      => array('label' => 'Windows (PC)',    'icon' => 'ri-windows-line',    'color' => '#0078d4'),
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
    $asn = devices_asn_org($ip);
    $origin = devices_origin_guess($fields['device_model'], $asn);

    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_devices
        (device_id, application_id, app_name, app_version, platform, device_model, os_version, ip_address, country_code, country_name, first_seen, last_seen, asn_org, origin)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            app_name = IF(VALUES(app_name) <> '', VALUES(app_name), app_name),
            app_version = IF(VALUES(app_version) <> '', VALUES(app_version), app_version),
            platform = VALUES(platform),
            device_model = IF(VALUES(device_model) <> '', VALUES(device_model), device_model),
            os_version = IF(VALUES(os_version) <> '', VALUES(os_version), os_version),
            ip_address = VALUES(ip_address),
            country_code = IF(VALUES(country_code) <> '', VALUES(country_code), country_code),
            country_name = IF(VALUES(country_name) <> '', VALUES(country_name), country_name),
            asn_org = IF(VALUES(asn_org) <> '', VALUES(asn_org), asn_org),
            origin = IF(VALUES(origin) <> '', VALUES(origin), origin),
            days_seen = days_seen + IF(FLOOR((last_seen - 10800) / 86400) <> FLOOR((VALUES(last_seen) - 10800) / 86400), 1, 0),
            last_seen = VALUES(last_seen)");
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'ssssssssssiiss',
        $fields['device_id'], $fields['application_id'], $fields['app_name'], $fields['app_version'],
        $fields['platform'], $fields['device_model'], $fields['os_version'], $fields['ip_address'],
        $fields['country_code'], $fields['country_name'], $now, $now, $asn, $origin);
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

// Bandeira e sigla do país de onde o aparelho se registrou (pelo IP). Vazio quando o país não é conhecido.
function devices_flag($code, $name = ''){
    $code = strtoupper(trim((string)$code));
    if (!preg_match('/^[A-Z]{2}$/', $code) || $code == 'XX') return '';
    $title = htmlspecialchars($name != '' ? $name.' ('.$code.')' : $code, ENT_QUOTES, 'UTF-8');
    return '<span class="dv-flag" title="'.$title.'" translate="no" style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;font-size:11px;font-weight:600;color:#6b7a99;vertical-align:middle">'
        .'<img src="https://flagcdn.com/w20/'.strtolower($code).'.png" alt="" width="20" height="15" style="border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.08)" loading="lazy">'.$code.'</span>';
}
