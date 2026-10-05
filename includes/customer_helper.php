<?php
/**
 * Clientes finais: cadastro, planos de assinatura (R$ e US$), pedidos e códigos numéricos de ativação.
 * Cada assinatura paga gera 1 código que ativa 1 aparelho, uma única vez.
 * Tabelas criadas automaticamente.
 */

require_once(__DIR__."/reseller_helper.php");

define('SUB_CODE_LENGTH', 8);
define('CUSTOMER_MAX_PENDING', 3);

function customers_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    resellers_migrate($mysqli);

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_customers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL,
        `email` VARCHAR(191) NOT NULL,
        `phone` VARCHAR(30) NOT NULL DEFAULT '',
        `password` VARCHAR(255) NOT NULL,
        `status` INT(1) NOT NULL DEFAULT 1,
        `country_code` CHAR(2) NOT NULL DEFAULT '',
        `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
        `remark` TEXT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `last_login` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_customer_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach (array('currency_locked' => "TINYINT(1) NOT NULL DEFAULT 0", 'last_ip' => "VARCHAR(45) NOT NULL DEFAULT ''") as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_customers` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_customers` ADD `$name` $definition");
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_sub_plans` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(80) NOT NULL,
        `months` INT(11) NOT NULL DEFAULT 12,
        `price_brl` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `price_usd` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `description` VARCHAR(255) NOT NULL DEFAULT '',
        `status` INT(1) NOT NULL DEFAULT 1,
        `sort` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_subscriptions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `customer_id` INT(11) NOT NULL,
        `plan_id` INT(11) NOT NULL DEFAULT 0,
        `plan_name` VARCHAR(80) NOT NULL DEFAULT '',
        `months` INT(11) NOT NULL DEFAULT 12,
        `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',   -- pending | paid | cancelled
        `code` VARCHAR(20) NULL DEFAULT NULL,             -- código numérico (gerado ao confirmar o pagamento)
        `code_used_at` INT(11) NOT NULL DEFAULT 0,
        `device_pk` INT(11) NOT NULL DEFAULT 0,
        `activated_at` INT(11) NOT NULL DEFAULT 0,
        `expires_at` INT(11) NOT NULL DEFAULT 0,
        `method` VARCHAR(30) NOT NULL DEFAULT 'manual',
        `reference` VARCHAR(191) NOT NULL DEFAULT '',
        `admin_id` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `paid_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_sub_code` (`code`),
        KEY `idx_customer` (`customer_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_subscriptions` LIKE 'is_demo'");
    if ($col && mysqli_num_rows($col) == 0) mysqli_query($mysqli, "ALTER TABLE `tbl_subscriptions` ADD `is_demo` TINYINT(1) NOT NULL DEFAULT 0");

    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_devices` LIKE 'customer_id'");
    if ($col && mysqli_num_rows($col) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD `customer_id` INT(11) NOT NULL DEFAULT 0, ADD KEY `idx_customer` (`customer_id`)");
    }

    // Plano inicial para começar (o admin ajusta preços em Clientes → Planos)
    $has = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_sub_plans"));
    if ((int)$has['num'] == 0) {
        mysqli_query($mysqli, "INSERT INTO tbl_sub_plans (name, months, price_brl, price_usd, description, status, sort) VALUES ('Anual', 12, 0, 0, '1 aparelho por 12 meses', 1, 1)");
    }
}

/* ---------- Moeda pelo país do IP ---------- */

// Brasil = BRL; demais países = USD. Sem país identificado = BRL.
function customer_currency_for_country($country_code){
    $cc = strtoupper((string)$country_code);
    if ($cc == '' || $cc == 'BR') return 'BRL';
    return 'USD';
}

function customer_detect_country(){
    $c = devices_country(devices_client_ip());
    return $c['code'];
}

function money_format_currency($value, $currency){
    if ($currency == 'USD') return 'US$ '.number_format((float)$value, 2, '.', ',');
    return 'R$ '.number_format((float)$value, 2, ',', '.');
}

function plan_price($plan, $currency){
    return (float)($currency == 'USD' ? $plan['price_usd'] : $plan['price_brl']);
}

function sub_plans_list($mysqli, $only_active = true){
    customers_migrate($mysqli);
    $rows = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_sub_plans".($only_active ? " WHERE status = 1" : "")." ORDER BY sort ASC, id ASC");
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

/* ---------- Pedidos de assinatura ---------- */

function subscription_create($mysqli, $customer, $plan_id){
    customers_migrate($mysqli);
    $plan_id = (int)$plan_id;
    $plan = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_sub_plans WHERE id = $plan_id AND status = 1"));
    if (!$plan) return array('ok' => false, 'msg' => 'Plano não encontrado.');
    $currency = $customer['currency'] == 'USD' ? 'USD' : 'BRL';
    $amount = plan_price($plan, $currency);
    if ($amount <= 0) return array('ok' => false, 'msg' => 'Este plano ainda não tem preço definido. Fale com o suporte.');

    $cid = (int)$customer['id'];
    $pending = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_subscriptions WHERE customer_id = $cid AND status = 'pending'"));
    if ((int)$pending['num'] >= CUSTOMER_MAX_PENDING) return array('ok' => false, 'msg' => 'Você já tem pedidos aguardando pagamento. Pague ou cancele um deles antes de criar outro.');

    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_subscriptions (customer_id, plan_id, plan_name, months, currency, amount, status, created_at) VALUES (?,?,?,?,?,?,'pending',?)");
    $months = (int)$plan['months'];
    mysqli_stmt_bind_param($stmt, 'iisisdi', $cid, $plan_id, $plan['name'], $months, $currency, $amount, $now);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($mysqli);
    mysqli_stmt_close($stmt);
    return $ok ? array('ok' => true, 'msg' => 'Pedido #'.$id.' criado. Siga as instruções de pagamento.', 'id' => $id) : array('ok' => false, 'msg' => 'Não foi possível criar o pedido.');
}

// Código numérico único de 8 dígitos (não começa com 0)
function subscription_code_generate($mysqli){
    for ($i = 0; $i < 50; $i++) {
        $code = (string)random_int(1, 9);
        for ($d = 1; $d < SUB_CODE_LENGTH; $d++) $code .= (string)random_int(0, 9);
        $stmt = mysqli_prepare($mysqli, "SELECT id FROM tbl_subscriptions WHERE code = ?");
        mysqli_stmt_bind_param($stmt, 's', $code);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if (!$exists) return $code;
    }
    return false;
}

// Confirma o pagamento e libera o código de ativação (uma única vez)
function subscription_mark_paid($mysqli, $sub_id, $admin_id = 0, $reference = ''){
    $sub_id = (int)$sub_id;
    $code = subscription_code_generate($mysqli);
    if (!$code) return false;
    $now = time();
    $admin_id = (int)$admin_id;
    $reference = mb_substr((string)$reference, 0, 191);
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_subscriptions SET status = 'paid', code = ?, paid_at = ?, admin_id = ?, reference = IF(? <> '', ?, reference) WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'siissi', $code, $now, $admin_id, $reference, $reference, $sub_id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ok ? $code : false;
}

function subscription_cancel($mysqli, $sub_id, $customer_id = 0){
    $sub_id = (int)$sub_id;
    $extra = $customer_id > 0 ? " AND customer_id = ".(int)$customer_id : '';
    mysqli_query($mysqli, "UPDATE tbl_subscriptions SET status = 'cancelled' WHERE id = $sub_id AND status = 'pending'".$extra);
    return mysqli_affected_rows($mysqli) > 0;
}

// Situação de uma assinatura para exibir: pending | code (pago, código livre) | active | expired | cancelled
function subscription_state($s){
    if ($s['status'] == 'cancelled') return 'cancelled';
    if ($s['status'] == 'pending') return 'pending';
    if ((int)$s['code_used_at'] == 0) return 'code';
    return ((int)$s['expires_at'] > 0 && (int)$s['expires_at'] < time()) ? 'expired' : 'active';
}

function subscription_state_badge($state){
    $map = array(
        'pending'   => array('Aguardando pagamento', 'pk-trial'),
        'code'      => array('Código liberado', 'pk-year'),
        'active'    => array('Ativa', 'pk-year'),
        'expired'   => array('Vencida', 'pk-expired'),
        'cancelled' => array('Cancelada', 'pk-none'),
    );
    $s = isset($map[$state]) ? $map[$state] : array($state, 'pk-none');
    return '<span class="dv-badge '.$s[1].'">'.$s[0].'</span>';
}

/* ---------- Ativação no app pelo código numérico ---------- */

function subscription_code_normalize($code){
    return preg_replace('/\D/', '', (string)$code);
}

function subscription_code_is_numeric($code){
    $digits = subscription_code_normalize($code);
    return strlen($digits) == SUB_CODE_LENGTH && preg_replace('/[\s\-\.]/', '', (string)$code) === $digits;
}

/**
 * O cliente digita o código numérico no app: ativa o aparelho pelo período do plano.
 * O código só pode ser usado uma vez. Retorna array('ok', 'msg', 'device').
 */
/* ---------- Teste para lojas (Amazon / Google) ---------- */

// Coloca a lista de demonstração (conteúdo livre) no aparelho, se ele ainda não tiver
function demo_attach_playlist($mysqli, $device_id){
    require_once(__DIR__."/playlist_helper.php");
    require_once(__DIR__."/demo_catalog.php");
    require_once(__DIR__."/brand_helper.php");
    playlists_migrate($mysqli);
    foreach (playlist_list($mysqli, $device_id) as $p) if ($p['user_name'] === DEMO_USER) return true;
    $base = rtrim(brand_mail_base_url(), '/');
    return playlist_save($mysqli, $device_id, 'Demo (conteúdo livre)', DEMO_USER, DEMO_PASS, $base, 'auto: teste das lojas', 'url');
}

// Gera um código de teste: cliente "Teste das lojas", 1 mês, pago, vale em vários aparelhos
function demo_store_code_create($mysqli, $admin_id = 0){
    customers_migrate($mysqli);
    $email = 'teste-lojas@iptvbestpro.com';
    $c = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_customers WHERE email = '".mysqli_real_escape_string($mysqli, $email)."'"));
    if (!$c) {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $now = time();
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_customers (name, email, password, status, created_at) VALUES ('Teste das lojas', ?, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'ssi', $email, $hash, $now);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $cid = mysqli_insert_id($mysqli);
    } else {
        $cid = (int)$c['id'];
        mysqli_query($mysqli, "UPDATE tbl_customers SET status = 1 WHERE id = $cid");
    }
    $code = subscription_code_generate($mysqli);
    if (!$code || !$cid) return false;
    $now = time();
    $aid = (int)$admin_id;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_subscriptions (customer_id, plan_id, plan_name, months, currency, amount, status, created_at, code, paid_at, admin_id, reference, is_demo)
                                     VALUES (?, 0, 'Teste das lojas', 1, 'USD', 0, 'paid', ?, ?, ?, ?, 'Teste das lojas', 1)");
    mysqli_stmt_bind_param($stmt, 'iisii', $cid, $now, $code, $now, $aid);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? $code : false;
}

// Copia as listas dos outros aparelhos do cliente para um aparelho que ainda não tem nenhuma
function customer_copy_playlists($mysqli, $customer_id, $device_id, $device_pk){
    require_once(__DIR__."/playlist_helper.php");
    playlists_migrate($mysqli);
    if (playlist_list($mysqli, $device_id)) return 0;
    $cid = (int)$customer_id; $dpk = (int)$device_pk;
    $res = mysqli_query($mysqli, "SELECT u.* FROM tbl_users u JOIN tbl_devices d ON d.device_id = u.device_id
                                  WHERE d.owner = 'customer' AND d.customer_id = $cid AND d.id <> $dpk AND u.status = '1' ORDER BY u.id ASC");
    $seen = array(); $n = 0;
    while ($res && $u = mysqli_fetch_assoc($res)) {
        $key = strtolower($u['user_name'].'|'.$u['dns_base']);
        if (isset($seen[$key])) continue;
        $seen[$key] = 1;
        if (playlist_save($mysqli, $device_id, $u['playlist_name'], $u['user_name'], $u['user_password'], $u['dns_base'], 'auto: outros aparelhos do cliente', isset($u['source']) ? $u['source'] : 'account')) $n++;
    }
    return $n;
}

function subscription_activate_device($mysqli, $data){
    customers_migrate($mysqli);
    $ip = devices_client_ip();
    if (devices_link_rate_limited($mysqli, $ip)) return array('ok' => false, 'msg' => 'Muitas tentativas. Tente novamente em 1 hora.', 'device' => null);
    if (empty($data['device_id'])) return array('ok' => false, 'msg' => 'Dispositivo não identificado.', 'device' => null);

    $code = subscription_code_normalize($data['code']);
    $stmt = mysqli_prepare($mysqli, "SELECT s.*, c.name AS customer_name, c.email AS customer_email, c.status AS customer_status
                                     FROM tbl_subscriptions s JOIN tbl_customers c ON c.id = s.customer_id WHERE s.code = ?");
    mysqli_stmt_bind_param($stmt, 's', $code);
    mysqli_stmt_execute($stmt);
    $sub = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$sub || $sub['status'] != 'paid' || (int)$sub['customer_status'] != 1) {
        devices_link_fail($mysqli, $ip);
        return array('ok' => false, 'msg' => 'Código inválido.', 'device' => null);
    }

    devices_register($mysqli, $data);
    $device = devices_find($mysqli, devices_clean($data['device_id'], 191), devices_clean(isset($data['application_id']) ? $data['application_id'] : '', 191));
    if (!$device) return array('ok' => false, 'msg' => 'Erro ao registrar o dispositivo.', 'device' => null);

    // Código já usado: só vale para o mesmo aparelho (reabrir o app depois de ativado)
    $is_demo = !empty($sub['is_demo']);
    if ((int)$sub['code_used_at'] > 0 && !$is_demo) {
        if ((int)$sub['device_pk'] === (int)$device['id']) return array('ok' => true, 'msg' => 'Dispositivo já ativado.', 'device' => $device);
        return array('ok' => false, 'msg' => 'Este código já foi usado em outro aparelho. Para ativar outro aparelho, contrate uma nova assinatura.', 'device' => null);
    }
    if ($device['owner'] != '' && $device['owner'] != 'customer' && devices_activation_status($device) == 'active') {
        return array('ok' => false, 'msg' => 'Este aparelho já está ativo com outro revendedor.', 'device' => $device);
    }

    $now = time();
    $base = ((int)$device['customer_id'] === (int)$sub['customer_id'] && devices_activation_status($device) == 'active' && (int)$device['expires_at'] > $now) ? (int)$device['expires_at'] : $now;
    $expires = strtotime('+'.(int)$sub['months'].' months', $base);

    // Marca o código como usado (somente se ainda estiver livre)
    $sid = (int)$sub['id'];
    $dpk = (int)$device['id'];
    if ($is_demo) {
        mysqli_query($mysqli, "UPDATE tbl_subscriptions SET code_used_at = IF(code_used_at = 0, $now, code_used_at), device_pk = $dpk, activated_at = IF(activated_at = 0, $now, activated_at), expires_at = $expires WHERE id = $sid");
    } else {
        mysqli_query($mysqli, "UPDATE tbl_subscriptions SET code_used_at = $now, device_pk = $dpk, activated_at = $now, expires_at = $expires WHERE id = $sid AND code_used_at = 0");
        if (mysqli_affected_rows($mysqli) == 0) return array('ok' => false, 'msg' => 'Este código já foi usado.', 'device' => null);
    }

    $cid = (int)$sub['customer_id'];
    $by = 'cliente: '.$sub['customer_email'];
    $cname = mb_substr($sub['customer_name'], 0, 120);
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_devices SET owner = 'customer', reseller_id = 0, customer_id = ?, linked_at = ?, package = 'sub', activated_by = ?,
                                     activated_at = IF(activated_at > 0 AND expires_at > ?, activated_at, ?), expires_at = ?, customer_name = IF(customer_name = '', ?, customer_name) WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'iisiiisi', $cid, $now, $by, $now, $now, $expires, $cname, $dpk);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Aparelho novo sem lista: recebe as listas que o cliente já tem nos outros aparelhos dele
    customer_copy_playlists($mysqli, $cid, $device['device_id'], $dpk);
    // Código de teste das lojas: já vem com a lista de demonstração (conteúdo livre)
    if ($is_demo) demo_attach_playlist($mysqli, $device['device_id']);

    $device = devices_find($mysqli, $device['device_id'], $device['application_id']);
    return array('ok' => true, 'msg' => 'Assinatura ativada! Válida até '.date('d/m/Y', $expires).'.', 'device' => $device);
}
