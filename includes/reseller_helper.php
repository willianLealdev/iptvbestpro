<?php
/**
 * Revenda: revendedores, códigos de ativação, créditos, pedidos de créditos
 * e ativação de dispositivos por período (1 crédito = 1 ano).
 * Tabelas criadas/atualizadas automaticamente.
 */

require_once(__DIR__."/device_tracker.php");

define('CREDITS_PER_YEAR', 1);
define('MAX_ACTIVATION_YEARS', 5);
define('ACTIVATION_CODE_LENGTH', 6);
define('ACTIVATION_CODE_CHARS', 'ABCDEFGHJKLMNPQRSTUVWXYZ'); // sem I e O para não confundir com 1 e 0
define('LINK_MAX_FAILS_PER_HOUR', 10);

function resellers_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;

    devices_migrate($mysqli);

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_resellers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL,
        `email` VARCHAR(191) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `credits` INT(11) NOT NULL DEFAULT 0,
        `status` INT(1) NOT NULL DEFAULT 1,
        `remark` TEXT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_reseller_email` (`email`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $extra = array(
        'code'       => "VARCHAR(10) NULL DEFAULT NULL",
        'phone'      => "VARCHAR(30) NOT NULL DEFAULT ''",
        'last_login' => "INT(11) NOT NULL DEFAULT 0",
        'currency'   => "CHAR(3) NOT NULL DEFAULT 'BRL'",
    );
    foreach ($extra as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_resellers` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) {
            mysqli_query($mysqli, "ALTER TABLE `tbl_resellers` ADD `$name` $definition");
        }
    }
    $idx = mysqli_query($mysqli, "SHOW INDEX FROM `tbl_resellers` WHERE Key_name = 'uq_reseller_code'");
    if ($idx && mysqli_num_rows($idx) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_resellers` ADD UNIQUE KEY `uq_reseller_code` (`code`)");
    }

    // Histórico de créditos (positivo = entrada, negativo = uso/remoção)
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_reseller_credits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `reseller_id` INT(11) NOT NULL,
        `amount` INT(11) NOT NULL,
        `balance_after` INT(11) NOT NULL DEFAULT 0,
        `type` VARCHAR(20) NOT NULL DEFAULT 'admin',
        `note` VARCHAR(255) NOT NULL DEFAULT '',
        `admin_id` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_reseller` (`reseller_id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Pedidos de compra de créditos
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_credit_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `reseller_id` INT(11) NOT NULL,
        `credits` INT(11) NOT NULL,
        `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `total` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `method` VARCHAR(30) NOT NULL DEFAULT 'manual',
        `reference` VARCHAR(191) NOT NULL DEFAULT '',
        `note` VARCHAR(255) NOT NULL DEFAULT '',
        `admin_id` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `paid_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_reseller` (`reseller_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Moeda do pedido (BRL ou USD), para separar os valores recebidos
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_credit_orders` LIKE 'currency'");
    if ($col && mysqli_num_rows($col) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_credit_orders` ADD `currency` CHAR(3) NOT NULL DEFAULT 'BRL' AFTER `total`");
    }

    // Configurações da revenda (código do admin, preço do crédito, instruções de pagamento...)
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_panel_config` (
        `name` VARCHAR(64) NOT NULL,
        `value` TEXT NULL,
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tentativas de vínculo com código errado (proteção contra adivinhação)
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_link_attempts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ip` VARCHAR(45) NOT NULL,
        `created_at` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_ip_time` (`ip`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Campos de vínculo e ativação no dispositivo
    $columns = array(
        'customer_name' => "VARCHAR(120) NOT NULL DEFAULT ''",    // nome do cliente (preenchido na ativação)
        'owner'         => "VARCHAR(10) NOT NULL DEFAULT ''",     // '' = sem dono | 'admin' | 'reseller'
        'reseller_id'   => "INT(11) NOT NULL DEFAULT 0",          // dono revendedor
        'linked_at'     => "INT(11) NOT NULL DEFAULT 0",
        'activated_by'  => "VARCHAR(191) NOT NULL DEFAULT ''",    // e-mail do revendedor ou 'admin: usuario'
        'package'       => "VARCHAR(20) NOT NULL DEFAULT ''",     // '' = não ativado | '1y'..'5y'
        'used_credits'  => "INT(11) NOT NULL DEFAULT 0",
        'activated_at'  => "INT(11) NOT NULL DEFAULT 0",
        'expires_at'    => "INT(11) NOT NULL DEFAULT 0",
    );
    foreach ($columns as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_devices` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) {
            mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD `$name` $definition");
        }
    }
    $idx = mysqli_query($mysqli, "SHOW INDEX FROM `tbl_devices` WHERE Key_name = 'idx_reseller'");
    if ($idx && mysqli_num_rows($idx) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD KEY `idx_reseller` (`reseller_id`), ADD KEY `idx_expires` (`expires_at`)");
    }
    $idx = mysqli_query($mysqli, "SHOW INDEX FROM `tbl_devices` WHERE Key_name = 'idx_owner'");
    if ($idx && mysqli_num_rows($idx) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD KEY `idx_owner` (`owner`, `reseller_id`)");
    }
    // Dispositivos antigos com revendedor e sem dono definido
    mysqli_query($mysqli, "UPDATE tbl_devices SET owner = 'reseller' WHERE owner = '' AND reseller_id > 0");

    // Garante códigos para revendedores antigos e para o admin
    $res = mysqli_query($mysqli, "SELECT id FROM tbl_resellers WHERE code IS NULL OR code = ''");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        mysqli_query($mysqli, "UPDATE tbl_resellers SET code = '".activation_code_generate($mysqli)."' WHERE id = ".(int)$r['id']);
    }
    if (panel_config_get($mysqli, 'admin_code', '') == '') {
        panel_config_set($mysqli, 'admin_code', activation_code_generate($mysqli));
    }

    dns_unify($mysqli);

    require_once(__DIR__.'/brand_helper.php');
    brand_apply_defaults($mysqli);
}

/**
 * Xtream Codes e 1-Stream usam o mesmo tipo de servidor: tudo fica em tbl_xui_dns.
 * Copia (uma única vez) os DNS do 1-Stream que ainda não existem na lista principal.
 */
function dns_unify($mysqli){
    if (panel_config_get($mysqli, 'dns_unified', '0') == '1') return;

    $existing = array();
    $res = mysqli_query($mysqli, "SELECT dns_base FROM tbl_xui_dns");
    while ($res && $r = mysqli_fetch_assoc($res)) $existing[] = strtolower(rtrim(trim($r['dns_base']), '/'));

    $res = mysqli_query($mysqli, "SELECT dns_title, dns_base, status FROM tbl_stream_dns");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $key = strtolower(rtrim(trim($r['dns_base']), '/'));
        if ($key == '' || in_array($key, $existing)) continue;
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_xui_dns (dns_title, dns_base, status) VALUES (?, ?, ?)");
        $status = (int)$r['status'];
        mysqli_stmt_bind_param($stmt, 'ssi', $r['dns_title'], $r['dns_base'], $status);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $existing[] = $key;
    }

    // Mantém as opções do app iguais para as duas formas de login
    mysqli_query($mysqli, "UPDATE tbl_settings SET is_stream_dns = is_xui_dns, is_stream_radio = is_xui_radio WHERE id = 1");
    panel_config_set($mysqli, 'dns_unified', '1');
}

/* ---------- Configurações ---------- */

function panel_config_get($mysqli, $name, $default = ''){
    $stmt = mysqli_prepare($mysqli, "SELECT value FROM tbl_panel_config WHERE name = ?");
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ($row && $row['value'] !== null) ? $row['value'] : $default;
}

function panel_config_set($mysqli, $name, $value){
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_panel_config (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $value = (string)$value;
    mysqli_stmt_bind_param($stmt, 'ss', $name, $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ---------- Códigos de ativação ---------- */

function activation_code_normalize($code){
    return strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$code));
}

function activation_code_exists($mysqli, $code){
    $stmt = mysqli_prepare($mysqli, "SELECT id FROM tbl_resellers WHERE code = ?");
    mysqli_stmt_bind_param($stmt, 's', $code);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if ($exists) return true;
    $res = mysqli_query($mysqli, "SHOW TABLES LIKE 'tbl_panel_config'");
    if ($res && mysqli_num_rows($res) > 0) {
        return panel_config_get($mysqli, 'admin_code', '') === $code;
    }
    return false;
}

// Gera um código único de 6 letras (não repete entre revendedores nem com o do admin)
function activation_code_generate($mysqli){
    $chars = ACTIVATION_CODE_CHARS;
    $max = strlen($chars) - 1;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $code = '';
        for ($i = 0; $i < ACTIVATION_CODE_LENGTH; $i++) $code .= $chars[random_int(0, $max)];
        if (!activation_code_exists($mysqli, $code)) return $code;
    }
    return false;
}

// Retorna array('owner' => 'admin'|'reseller', 'reseller' => row|null) ou null
function activation_code_owner($mysqli, $code){
    $code = activation_code_normalize($code);
    if (strlen($code) != ACTIVATION_CODE_LENGTH) return null;
    if (panel_config_get($mysqli, 'admin_code', '') === $code) return array('owner' => 'admin', 'reseller' => null);
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_resellers WHERE code = ? AND status = 1");
    mysqli_stmt_bind_param($stmt, 's', $code);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ? array('owner' => 'reseller', 'reseller' => $row) : null;
}

/* ---------- Créditos ---------- */

/**
 * Soma (ou subtrai, se negativo) créditos de forma atômica e registra no histórico.
 * Retorna o novo saldo, ou false se o saldo ficaria negativo / revendedor não existe.
 */
function resellers_add_credits($mysqli, $reseller_id, $amount, $note = '', $type = 'admin', $admin_id = 0){
    $reseller_id = (int)$reseller_id;
    $amount = (int)$amount;
    if ($reseller_id <= 0 || $amount == 0) return false;

    mysqli_begin_transaction($mysqli);
    $res = mysqli_query($mysqli, "SELECT credits FROM tbl_resellers WHERE id = $reseller_id FOR UPDATE");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || ((int)$row['credits'] + $amount) < 0) {
        mysqli_rollback($mysqli);
        return false;
    }
    $balance = (int)$row['credits'] + $amount;
    $now = time();
    mysqli_query($mysqli, "UPDATE tbl_resellers SET credits = $balance, updated_at = $now WHERE id = $reseller_id");

    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_reseller_credits (reseller_id, amount, balance_after, type, note, admin_id, created_at) VALUES (?,?,?,?,?,?,?)");
    $note = mb_substr(trim(strip_tags((string)$note)), 0, 255);
    $type = mb_substr((string)$type, 0, 20);
    $admin_id = (int)$admin_id;
    mysqli_stmt_bind_param($stmt, 'iiissii', $reseller_id, $amount, $balance, $type, $note, $admin_id, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($mysqli);
    return $balance;
}

function resellers_total_credit_share($mysqli){
    $res = mysqli_query($mysqli, "SELECT COALESCE(SUM(amount),0) AS num FROM tbl_reseller_credits WHERE amount > 0");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int)$row['num'] : 0;
}

function credit_type_label($type){
    $labels = array('admin' => 'Ajuste do admin', 'purchase' => 'Compra', 'activation' => 'Ativação', 'refund' => 'Estorno');
    return isset($labels[$type]) ? $labels[$type] : $type;
}

/* ---------- Pedidos de créditos ---------- */

// Preço do crédito na moeda do revendedor: BRL (credit_price) ou USD (credit_price_usd)
function credit_price($mysqli, $currency = 'BRL'){
    $key = $currency == 'USD' ? 'credit_price_usd' : 'credit_price';
    return (float)str_replace(',', '.', panel_config_get($mysqli, $key, '0'));
}

function reseller_currency($reseller){
    return (isset($reseller['currency']) && $reseller['currency'] == 'USD') ? 'USD' : 'BRL';
}

function money_currency($value, $currency){
    if ($currency == 'USD') return 'US$ '.number_format((float)$value, 2, '.', ',');
    return money_br($value);
}

function money_br($value){
    return 'R$ '.number_format((float)$value, 2, ',', '.');
}

function credit_orders_create($mysqli, $reseller_id, $credits){
    $credits = (int)$credits;
    $min = max(1, (int)panel_config_get($mysqli, 'credit_min', '1'));
    if ($credits < $min || $credits > 10000) return false;
    $reseller_id = (int)$reseller_id;
    $reseller = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT currency FROM tbl_resellers WHERE id = $reseller_id"));
    if (!$reseller) return false;
    $currency = reseller_currency($reseller);
    $unit = credit_price($mysqli, $currency);
    if ($unit <= 0) return false;
    $total = round($unit * $credits, 2);
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_credit_orders (reseller_id, credits, unit_price, total, currency, status, method, created_at) VALUES (?,?,?,?,?,'pending','manual',?)");
    mysqli_stmt_bind_param($stmt, 'iiddsi', $reseller_id, $credits, $unit, $total, $currency, $now);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($mysqli);
    mysqli_stmt_close($stmt);
    return $ok ? $id : false;
}

// Confirma o pagamento: só pedidos pendentes; os créditos entram uma única vez
function credit_orders_mark_paid($mysqli, $order_id, $admin_id = 0, $reference = ''){
    $order_id = (int)$order_id;
    $now = time();
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_credit_orders SET status = 'paid', paid_at = ?, admin_id = ?, reference = IF(? <> '', ?, reference) WHERE id = ? AND status = 'pending'");
    $admin_id = (int)$admin_id;
    $reference = mb_substr((string)$reference, 0, 191);
    mysqli_stmt_bind_param($stmt, 'iissi', $now, $admin_id, $reference, $reference, $order_id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$changed) return false;

    $order = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_credit_orders WHERE id = $order_id"));
    return resellers_add_credits($mysqli, $order['reseller_id'], (int)$order['credits'], 'Pedido #'.$order_id, 'purchase', $admin_id) !== false;
}

function credit_orders_cancel($mysqli, $order_id, $reseller_id = 0){
    $order_id = (int)$order_id;
    $extra = $reseller_id > 0 ? " AND reseller_id = ".(int)$reseller_id : '';
    mysqli_query($mysqli, "UPDATE tbl_credit_orders SET status = 'cancelled' WHERE id = $order_id AND status = 'pending'".$extra);
    return mysqli_affected_rows($mysqli) > 0;
}

function credit_order_status_badge($status){
    $map = array(
        'pending'   => array('Aguardando pagamento', 'pk-trial'),
        'paid'      => array('Pago', 'pk-year'),
        'cancelled' => array('Cancelado', 'pk-none'),
    );
    $s = isset($map[$status]) ? $map[$status] : array($status, 'pk-none');
    return '<span class="dv-badge '.$s[1].'">'.$s[0].'</span>';
}

/* ---------- Pacotes e ativação ---------- */

// 1 ano = 1 crédito, 2 anos = 2 créditos... até MAX_ACTIVATION_YEARS. Sem vitalício.
function devices_packages(){
    $packages = array();
    for ($y = 1; $y <= MAX_ACTIVATION_YEARS; $y++) {
        $packages[$y.'y'] = array(
            'label'   => $y == 1 ? '1 Ano' : $y.' Anos',
            'years'   => $y,
            'days'    => $y * 365,
            'credits' => $y * CREDITS_PER_YEAR,
            'class'   => 'pk-year',
        );
    }
    return $packages;
}

// Rótulo do pacote salvo no aparelho (inclui chaves antigas de testes)
function devices_package_info($key){
    $packages = devices_packages();
    if (isset($packages[$key])) return $packages[$key];
    $legacy = array(
        'sub'      => array('label' => 'Assinatura', 'class' => 'pk-year'),
        'year'     => array('label' => '1 Ano', 'class' => 'pk-year'),
        'trial'    => array('label' => 'Teste', 'class' => 'pk-trial'),
        'lifetime' => array('label' => 'Vitalício', 'class' => 'pk-lifetime'),
    );
    return isset($legacy[$key]) ? $legacy[$key] : null;
}

// 'active' | 'expired' | 'inactive'
function devices_activation_status($row){
    if ($row['package'] == '') return 'inactive';
    if ((int)$row['expires_at'] == 0) return 'active';
    return ((int)$row['expires_at'] >= time()) ? 'active' : 'expired';
}

// Status para o app: 'unlinked' | 'pending' | 'active' | 'expired'
function devices_app_status($row){
    if (!$row || $row['owner'] == '') return 'unlinked';
    $s = devices_activation_status($row);
    if ($s == 'inactive') return 'pending';
    return $s;
}

function devices_owner_label($mysqli, $row){
    if ($row['owner'] == 'admin') return 'Admin';
    if ($row['owner'] == 'customer' && !empty($row['customer_id'])) {
        $c = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT email FROM tbl_customers WHERE id = ".(int)$row['customer_id']));
        return $c ? 'Cliente: '.$c['email'] : 'Cliente removido';
    }
    if ($row['owner'] == 'reseller' && (int)$row['reseller_id'] > 0) {
        static $cache = array();
        $id = (int)$row['reseller_id'];
        if (!isset($cache[$id])) {
            $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT name, email FROM tbl_resellers WHERE id = $id"));
            $cache[$id] = $r ? $r : array('name' => 'Revendedor removido', 'email' => '');
        }
        return $cache[$id]['email'] != '' ? $cache[$id]['email'] : $cache[$id]['name'];
    }
    return '';
}

// Nome público do dono (mostrado no app do cliente; não expõe e-mail)
function devices_owner_name($mysqli, $row){
    if ($row['owner'] == 'admin' || $row['owner'] == 'customer') return defined('APP_NAME') ? APP_NAME : 'Admin';
    if ($row['owner'] == 'reseller' && (int)$row['reseller_id'] > 0) {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT name FROM tbl_resellers WHERE id = ".(int)$row['reseller_id']));
        return $r ? $r['name'] : '';
    }
    return '';
}

/**
 * Ativa ou renova um dispositivo por $years anos.
 * $charge_reseller_id > 0 desconta (anos × CREDITS_PER_YEAR) créditos desse revendedor; 0 = admin (sem custo).
 * Renovação de aparelho ainda válido soma os dias a partir do vencimento atual.
 * Retorna array('ok' => bool, 'msg' => string).
 */
function devices_activate($mysqli, $device_pk, $package_key, $charge_reseller_id, $actor_label = '', $admin_id = 0){
    $packages = devices_packages();
    if (!isset($packages[$package_key])) return array('ok' => false, 'msg' => 'Período inválido.');
    $pkg = $packages[$package_key];

    $device_pk = (int)$device_pk;
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $device_pk");
    $device = $res ? mysqli_fetch_assoc($res) : null;
    if (!$device) return array('ok' => false, 'msg' => 'Dispositivo não encontrado.');

    $charge_reseller_id = (int)$charge_reseller_id;
    $credits = 0;
    $activated_by = $actor_label;
    if ($charge_reseller_id > 0) {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, email, status FROM tbl_resellers WHERE id = $charge_reseller_id"));
        if (!$r) return array('ok' => false, 'msg' => 'Revendedor não encontrado.');
        if ((int)$r['status'] != 1) return array('ok' => false, 'msg' => 'Revendedor bloqueado.');
        $credits = (int)$pkg['credits'];
        $activated_by = $r['email'];
        $note = $pkg['label'].' · '.$device['device_id'];
        if (resellers_add_credits($mysqli, $charge_reseller_id, -$credits, $note, 'activation', $admin_id) === false) {
            return array('ok' => false, 'msg' => 'Créditos insuficientes (precisa de '.$credits.').');
        }
    }

    $now = time();
    $was_valid = devices_activation_status($device) == 'active' && (int)$device['expires_at'] > $now;
    $base = $was_valid ? (int)$device['expires_at'] : $now;
    $expires = $base + ($pkg['days'] * 86400);
    $activated_at = ($was_valid && (int)$device['activated_at'] > 0) ? (int)$device['activated_at'] : $now;

    // Aparelho sem dono passa a ser de quem ativou
    $owner = $device['owner'];
    $owner_reseller = (int)$device['reseller_id'];
    $linked_at = (int)$device['linked_at'];
    if ($owner == '') {
        $owner = $charge_reseller_id > 0 ? 'reseller' : 'admin';
        $owner_reseller = $charge_reseller_id;
        $linked_at = $now;
    }

    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_devices SET package = ?, owner = ?, reseller_id = ?, linked_at = ?, activated_by = ?, used_credits = used_credits + ?, activated_at = ?, expires_at = ? WHERE id = ?");
    $activated_by = mb_substr($activated_by, 0, 191);
    mysqli_stmt_bind_param($stmt, 'ssiisiiii', $package_key, $owner, $owner_reseller, $linked_at, $activated_by, $credits, $activated_at, $expires, $device_pk);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok && $charge_reseller_id > 0 && $credits > 0) {
        resellers_add_credits($mysqli, $charge_reseller_id, $credits, 'Estorno: falha na ativação', 'refund', $admin_id);
    }
    if (!$ok) return array('ok' => false, 'msg' => 'Erro ao salvar a ativação.');
    return array('ok' => true, 'msg' => 'Ativado por '.$pkg['label'].' — vence em '.date('d/m/Y', $expires).'.');
}

// Grava o nome do cliente do aparelho ($reseller_id > 0 restringe aos aparelhos desse revendedor)
function devices_set_customer($mysqli, $device_pk, $name, $reseller_id = 0){
    $name = mb_substr(trim(strip_tags((string)$name)), 0, 120);
    $pk = (int)$device_pk;
    $extra = $reseller_id > 0 ? " AND owner = 'reseller' AND reseller_id = ".(int)$reseller_id : '';
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_devices SET customer_name = ? WHERE id = ?".$extra);
    mysqli_stmt_bind_param($stmt, 'si', $name, $pk);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function devices_deactivate($mysqli, $device_pk, $reseller_id = 0){
    $device_pk = (int)$device_pk;
    $extra = $reseller_id > 0 ? " AND owner = 'reseller' AND reseller_id = ".(int)$reseller_id : '';
    return mysqli_query($mysqli, "UPDATE tbl_devices SET package = '', expires_at = 0 WHERE id = $device_pk".$extra);
}

/* ---------- Vínculo pelo app (código de 6 letras) ---------- */

function devices_link_rate_limited($mysqli, $ip){
    $since = time() - 3600;
    $stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS num FROM tbl_link_attempts WHERE ip = ? AND created_at >= ?");
    mysqli_stmt_bind_param($stmt, 'si', $ip, $since);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)$row['num'] >= LINK_MAX_FAILS_PER_HOUR;
}

function devices_link_fail($mysqli, $ip){
    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_link_attempts (ip, created_at) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'si', $ip, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (random_int(1, 50) == 1) mysqli_query($mysqli, "DELETE FROM tbl_link_attempts WHERE created_at < ".($now - 86400));
}

function devices_find($mysqli, $device_id, $application_id){
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_devices WHERE device_id = ? AND application_id = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $device_id, $application_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

/**
 * O cliente digita o código no app: o aparelho passa para a lista do dono do código.
 * Aparelho ativo e válido com outro dono não pode ser "roubado".
 * Retorna array('ok' => bool, 'msg' => string, 'device' => row|null)
 */
function devices_link($mysqli, $data){
    resellers_migrate($mysqli);
    // Código numérico (8 dígitos) = assinatura de cliente final
    require_once(__DIR__.'/customer_helper.php');
    if (subscription_code_is_numeric(isset($data['code']) ? $data['code'] : '')) {
        return subscription_activate_device($mysqli, $data);
    }
    $ip = devices_client_ip();
    if (devices_link_rate_limited($mysqli, $ip)) {
        return array('ok' => false, 'msg' => 'Muitas tentativas. Tente novamente em 1 hora.', 'device' => null);
    }
    if (empty($data['device_id'])) return array('ok' => false, 'msg' => 'Dispositivo não identificado.', 'device' => null);

    $owner = activation_code_owner($mysqli, isset($data['code']) ? $data['code'] : '');
    if (!$owner) {
        devices_link_fail($mysqli, $ip);
        return array('ok' => false, 'msg' => 'Código inválido.', 'device' => null);
    }

    devices_register($mysqli, $data);
    $device_id = devices_clean($data['device_id'], 191);
    $application_id = devices_clean(isset($data['application_id']) ? $data['application_id'] : '', 191);
    $device = devices_find($mysqli, $device_id, $application_id);
    if (!$device) return array('ok' => false, 'msg' => 'Erro ao registrar o dispositivo.', 'device' => null);

    $new_reseller = $owner['owner'] == 'reseller' ? (int)$owner['reseller']['id'] : 0;
    $same_owner = ($device['owner'] == $owner['owner'] && (int)$device['reseller_id'] == $new_reseller);
    if (!$same_owner && $device['owner'] != '' && devices_activation_status($device) == 'active') {
        return array('ok' => false, 'msg' => 'Este dispositivo já está ativo com outro revendedor.', 'device' => $device);
    }

    if (!$same_owner) {
        $now = time();
        // Troca de dono: zera ativação vencida/pendente do dono anterior
        mysqli_query($mysqli, "UPDATE tbl_devices SET owner = '".$owner['owner']."', reseller_id = $new_reseller, linked_at = $now,
                               package = IF(package <> '' AND expires_at > 0 AND expires_at < $now, '', package)
                               WHERE id = ".(int)$device['id']);
        $device = devices_find($mysqli, $device_id, $application_id);
    }
    return array('ok' => true, 'msg' => 'Dispositivo vinculado. Aguarde a ativação pelo seu revendedor.', 'device' => $device);
}
