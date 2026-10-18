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
        'currency_locked' => "TINYINT(1) NOT NULL DEFAULT 0",
        'country_code'    => "CHAR(2) NOT NULL DEFAULT ''",
        'last_ip'         => "VARCHAR(45) NOT NULL DEFAULT ''",
        'training_seen'   => "TINYINT(1) NOT NULL DEFAULT 0", // já abriu o Treinamento (some a janela de boas-vindas)
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
        'is_dev'        => "TINYINT(1) NOT NULL DEFAULT 0",       // 0 = ainda não classificado | 1 = aparelho de teste (aba Dispositivos dev) | 2 = real (marcado à mão)
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

/**
 * Geolocalização por IP para contas (revendedor e cliente): a cada acesso confere o país do IP atual
 * e ajusta a moeda (Brasil = BRL, outros países = USD). Se o admin fixou a moeda manualmente
 * (currency_locked = 1), só registra país/IP. IP sem país identificado não altera nada.
 * $table: 'tbl_resellers' | 'tbl_customers'. Retorna a linha atualizada.
 */
function geo_sync_account($mysqli, $table, $row){
    if (!in_array($table, array('tbl_resellers', 'tbl_customers')) || empty($row['id'])) return $row;
    $ip = devices_client_ip();
    $key = 'geo_'.$table.'_'.(int)$row['id'];
    if (isset($_SESSION[$key]) && $_SESSION[$key] === $ip) return $row; // já conferido neste IP nesta sessão
    $_SESSION[$key] = $ip;

    $country = devices_country($ip);
    $cc = strtoupper((string)$country['code']);
    if ($cc == '') return $row;
    $currency = ($cc == 'BR') ? 'BRL' : 'USD';
    $locked = !empty($row['currency_locked']);
    $new_currency = $locked ? $row['currency'] : $currency;

    if ($cc != (string)$row['country_code'] || $new_currency != $row['currency'] || $ip != (string)$row['last_ip']) {
        $id = (int)$row['id'];
        $stmt = mysqli_prepare($mysqli, "UPDATE $table SET country_code = ?, currency = ?, last_ip = ? WHERE id = ?");
        $ip_db = mb_substr($ip, 0, 45);
        mysqli_stmt_bind_param($stmt, 'sssi', $cc, $new_currency, $ip_db, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $row['country_code'] = $cc;
        $row['currency'] = $new_currency;
        $row['last_ip'] = $ip_db;
    }
    return $row;
}

// Select de moeda usado no admin: Automático (pelo IP) / fixo em R$ / fixo em US$
function currency_mode_select($row, $show_country = true){
    $locked = !empty($row['currency_locked']);
    $cur = (isset($row['currency']) && $row['currency'] == 'USD') ? 'USD' : 'BRL';
    $cc = isset($row['country_code']) ? $row['country_code'] : '';
    $o = '<select name="currency" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.submit()">';
    $o .= '<option value="AUTO"'.(!$locked ? ' selected' : '').'>Auto pelo IP ('.($cur == 'USD' ? 'US$' : 'R$').')</option>';
    $o .= '<option value="BRL"'.($locked && $cur == 'BRL' ? ' selected' : '').'>Fixo em R$</option>';
    $o .= '<option value="USD"'.($locked && $cur == 'USD' ? ' selected' : '').'>Fixo em US$</option>';
    $o .= '</select>';
    if ($show_country && $cc != '') $o .= '<br><small class="text-muted">País: '.htmlspecialchars($cc).'</small>';
    return $o;
}

// Grava a escolha do admin: AUTO libera a moeda para seguir o IP; BRL/USD fixa
function currency_mode_save($mysqli, $table, $id, $value){
    if (!in_array($table, array('tbl_resellers', 'tbl_customers'))) return;
    $id = (int)$id;
    if ($value == 'AUTO') {
        mysqli_query($mysqli, "UPDATE $table SET currency_locked = 0 WHERE id = $id");
    } else {
        $cur = $value == 'USD' ? 'USD' : 'BRL';
        mysqli_query($mysqli, "UPDATE $table SET currency = '$cur', currency_locked = 1 WHERE id = $id");
    }
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
    // Teste grátis de 7 dias (sem crédito): só uma vez por aparelho, em aparelho que nunca foi ativado
    $packages['trial'] = array('label' => 'Teste grátis 7 dias', 'years' => 0, 'days' => 7, 'credits' => 0, 'class' => 'pk-trial');
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
    // O aparelho fica sempre com o dono do código que foi digitado no app (revenda, servidor, admin ou cliente).
    // No painel ele só é ativado/renovado por esse dono; para ir para outro, o cliente digita outro código ou reseta a licença.
    if ($device['owner'] != '' && $charge_reseller_id > 0 && !($device['owner'] == 'reseller' && (int)$device['reseller_id'] == $charge_reseller_id)) {
        return array('ok' => false, 'msg' => 'Este aparelho está ligado ao código de '.devices_owner_name($mysqli, $device).'. Ele só pode ser ativado por esse dono. Para trocar, o cliente digita outro código no app ou reseta a licença.');
    }
    // Aparelho sem dono (ativação manual): quem ativa vira o dono; não pode estar ativo com outro dono em outro registro
    if ($device['owner'] == '' && devices_active_elsewhere($mysqli, $device['device_id'], $charge_reseller_id > 0 ? 'reseller' : 'admin', $charge_reseller_id, 0, $device_pk)) {
        return array('ok' => false, 'msg' => 'Este aparelho já está ativo com outro dono. Ele só pode ser ativado de novo depois que o cliente resetar a licença no app ou quando a ativação vencer.');
    }
    // Teste grátis de 7 dias: só em aparelho sem ativação e que nunca usou teste (mesma regra do teste do site)
    $is_trial = $package_key == 'trial';
    if ($is_trial) {
        require_once(__DIR__.'/customer_helper.php');
        customers_migrate($mysqli);
        if (devices_activation_status($device) == 'active') return array('ok' => false, 'msg' => 'O teste grátis é só para aparelho sem ativação. Este aparelho já está ativo.');
        if (trial_used_by_device($mysqli, $device)) return array('ok' => false, 'msg' => 'Este aparelho já usou o teste grátis ou já foi ativado antes. Ative com um pacote de 1 ano.');
    }
    $credits = 0;
    $activated_by = $actor_label;
    if ($charge_reseller_id > 0) {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, email, status FROM tbl_resellers WHERE id = $charge_reseller_id"));
        if (!$r) return array('ok' => false, 'msg' => 'Revendedor não encontrado.');
        if ((int)$r['status'] != 1) return array('ok' => false, 'msg' => 'Revendedor bloqueado.');
        $credits = (int)$pkg['credits'];
        $activated_by = $r['email'];
        $note = $pkg['label'].' · '.$device['device_id'];
        if ($credits > 0 && resellers_add_credits($mysqli, $charge_reseller_id, -$credits, $note, 'activation', $admin_id) === false) {
            return array('ok' => false, 'msg' => 'Créditos insuficientes (precisa de '.$credits.').');
        }
    }

    $now = time();
    $was_valid = devices_activation_status($device) == 'active' && (int)$device['expires_at'] > $now;
    $base = ($was_valid && !$is_trial) ? (int)$device['expires_at'] : $now;
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
    if ($is_trial) {
        // fica registrado de vez: o aparelho não ganha outro teste (nem resetando a licença)
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_trial_devices (device_id, platform, device_model, ip_address, customer_id, used_at) VALUES (?,?,?,?,0,?)");
        mysqli_stmt_bind_param($stmt, 'ssssi', $device['device_id'], $device['platform'], $device['device_model'], $device['ip_address'], $now);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    return array('ok' => true, 'msg' => 'Ativado por '.$pkg['label'].' — vence em '.date('d/m/Y', $expires).'.');
}

// Grava o nome do cliente do aparelho ($reseller_id > 0 restringe aos aparelhos desse revendedor)
// MAC digitado sem separador (ex.: d93089bb51d6 ou D9-30-89-BB-51-D6) vira D9:30:89:BB:51:D6.
// Outros formatos de Device ID ficam como foram digitados.
function devices_format_mac($value){
    $v = trim((string)$value);
    $hex = preg_replace('/[\s:\-\.]/', '', $v);
    if (preg_match('/^[0-9A-Fa-f]{12}$/', $hex)) return strtoupper(implode(':', str_split($hex, 2)));
    return $v;
}

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

// Desativar = o aparelho fica livre (sem dono, sem ativação e sem as listas do dono), igual ao Resetar licença no app:
// depois pode ser ativado com o código de qualquer revenda, servidor ou assinatura.
// activated_at fica guardado só como histórico (aparelho que já foi ativado não ganha teste grátis).
function devices_deactivate($mysqli, $device_pk, $reseller_id = 0){
    $device_pk = (int)$device_pk;
    $extra = $reseller_id > 0 ? " AND owner = 'reseller' AND reseller_id = ".(int)$reseller_id : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT device_id FROM tbl_devices WHERE id = $device_pk".$extra));
    if (!$row) return false;
    devices_wipe_playlists($mysqli, $row['device_id']);
    $did = mysqli_real_escape_string($mysqli, $row['device_id']);
    // todos os registros desse aparelho (um por app) ficam livres
    return mysqli_query($mysqli, "UPDATE tbl_devices SET owner = '', reseller_id = 0, customer_id = 0, linked_at = 0, package = '', expires_at = 0,
                                  activated_by = '', used_credits = 0, customer_name = '' WHERE device_id = '$did'");
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
// Apaga todas as listas (playlists) de um aparelho
function devices_wipe_playlists($mysqli, $device_id){
    $device_id = (string)$device_id;
    if ($device_id === '') return;
    $stmt = mysqli_prepare($mysqli, "DELETE FROM tbl_users WHERE device_id = ?");
    mysqli_stmt_bind_param($stmt, 's', $device_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Regra do dono: o aparelho é de quem o ativou. Enquanto estiver ativo, nenhum outro dono (revenda, servidor,
 * assinatura ou admin cobrando outra revenda) ativa, renova ou "pega" o aparelho. Outro dono só entra depois
 * que o cliente reseta a licença no app ou quando a ativação venceu.
 * Um mesmo aparelho (device_id) pode ter mais de um registro (um por app): a regra vale para todos.
 */
function devices_active_elsewhere($mysqli, $device_id, $owner, $reseller_id, $customer_id = 0, $exclude_pk = 0){
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_devices WHERE device_id = ? AND id <> ? AND owner <> ''");
    $ex = (int)$exclude_pk;
    mysqli_stmt_bind_param($stmt, 'si', $device_id, $ex);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $found = null;
    while ($res && $row = mysqli_fetch_assoc($res)) {
        if (devices_activation_status($row) != 'active') continue;
        $same = $row['owner'] == $owner && (int)$row['reseller_id'] == (int)$reseller_id && ($owner != 'customer' || (int)$row['customer_id'] == (int)$customer_id);
        if (!$same) { $found = $row; break; }
    }
    mysqli_stmt_close($stmt);
    return $found;
}

// Troca de dono: o aparelho chega zerado para o novo dono (sem ativação, nome, créditos ou listas do anterior)
// e os outros registros antigos do mesmo aparelho (de outros apps, sem ativação válida) são apagados.
function devices_take_over($mysqli, $device_pk, $owner, $reseller_id, $customer_id = 0){
    $pk = (int)$device_pk;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT device_id FROM tbl_devices WHERE id = $pk"));
    if (!$row) return false;
    $now = time();
    $owner = in_array($owner, array('reseller', 'admin', 'customer'), true) ? $owner : '';
    devices_wipe_playlists($mysqli, $row['device_id']);
    mysqli_query($mysqli, "UPDATE tbl_devices SET owner = '$owner', reseller_id = ".(int)$reseller_id.", customer_id = ".(int)$customer_id.", linked_at = $now,
                           package = '', activated_at = 0, expires_at = 0, activated_by = '', used_credits = 0, customer_name = '' WHERE id = $pk");
    $did = mysqli_real_escape_string($mysqli, $row['device_id']);
    mysqli_query($mysqli, "DELETE FROM tbl_devices WHERE device_id = '$did' AND id <> $pk");
    return true;
}

// Excluir aparelho = apagar tudo dele (listas incluídas). Ao voltar, ele chega zerado.
function devices_purge($mysqli, $ids){
    $ids = array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string)$ids)));
    if (!$ids) return 0;
    $in = implode(',', $ids);
    $res = mysqli_query($mysqli, "SELECT device_id FROM tbl_devices WHERE id IN ($in)");
    while ($res && $d = mysqli_fetch_assoc($res)) devices_wipe_playlists($mysqli, $d['device_id']);
    mysqli_query($mysqli, "DELETE FROM tbl_devices WHERE id IN ($in)");
    return mysqli_affected_rows($mysqli);
}

function devices_link($mysqli, $data){
    resellers_migrate($mysqli);
    // Código de 8 números de um servidor parceiro: ativa na hora, descontando 1 crédito do servidor
    // (os outros códigos de 8 números são assinaturas de cliente)
    require_once(__DIR__.'/server_helper.php');
    if (server_code_matches($mysqli, isset($data['code']) ? $data['code'] : '')) {
        return server_link_device($mysqli, $data);
    }
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
    if ((!$same_owner && $device['owner'] != '' && devices_activation_status($device) == 'active')
        || devices_active_elsewhere($mysqli, $device_id, $owner['owner'], $new_reseller, 0, (int)$device['id'])) {
        return array('ok' => false, 'msg' => 'Este aparelho já está ativo com outra revenda. Para usar outro código, resete a licença no app (Configurações → Resetar licença).', 'device' => $device);
    }

    if (!$same_owner) {
        // Troca de dono: chega zerado (sem listas, ativação, nome ou créditos do dono anterior)
        devices_take_over($mysqli, (int)$device['id'], $owner['owner'], $new_reseller);
        $device = devices_find($mysqli, $device_id, $application_id);
    }
    return array('ok' => true, 'msg' => 'Dispositivo vinculado. Aguarde a ativação pelo seu revendedor.', 'device' => $device);
}
