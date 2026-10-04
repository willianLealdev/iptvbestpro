<?php
/**
 * Gateways de pagamento: Mercado Pago (Pix) e Stripe (cartão, via Checkout).
 *
 * Segurança:
 * - O valor cobrado SEMPRE vem do pedido no banco (tbl_subscriptions / tbl_credit_orders); nada vem do navegador.
 * - A confirmação só acontece no backend: o webhook é validado e o pagamento é consultado na API do gateway;
 *   valor e moeda precisam bater com o pedido. Só então o pedido é liberado (código ou créditos).
 * - Liberação idempotente: pagar duas vezes ou receber o webhook repetido não duplica nada.
 */

require_once(__DIR__."/customer_helper.php");

define('MP_API', 'https://api.mercadopago.com');
define('STRIPE_API', 'https://api.stripe.com');

function payments_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    customers_migrate($mysqli);
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(20) NOT NULL,              -- mercadopago | stripe
        `order_type` VARCHAR(20) NOT NULL,            -- sub | credit
        `order_id` INT(11) NOT NULL,
        `owner_type` VARCHAR(20) NOT NULL,            -- customer | reseller
        `owner_id` INT(11) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending | paid | failed | expired
        `provider_id` VARCHAR(191) NOT NULL DEFAULT '',  -- id do pagamento (MP) ou da sessão (Stripe)
        `qr_code` TEXT NULL,
        `qr_base64` MEDIUMTEXT NULL,
        `checkout_url` TEXT NULL,
        `expires_at` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `paid_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_order` (`order_type`, `order_id`),
        KEY `idx_provider` (`provider`, `provider_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ---------- Configuração (chaves ficam no banco, nunca no navegador) ---------- */

function pay_cfg($mysqli, $key, $default = ''){ return panel_config_get($mysqli, 'pay_'.$key, $default); }
function pay_set($mysqli, $key, $value){ return panel_config_set($mysqli, 'pay_'.$key, $value); }

function pay_mp_enabled($mysqli){ return pay_cfg($mysqli, 'mp_enabled', '0') == '1' && pay_cfg($mysqli, 'mp_token') != ''; }
function pay_stripe_enabled($mysqli){ return pay_cfg($mysqli, 'stripe_enabled', '0') == '1' && pay_cfg($mysqli, 'stripe_secret') != ''; }

function pay_mask($secret){
    $s = (string)$secret;
    return $s == '' ? '' : str_repeat('•', 8).substr($s, -4);
}

// URL pública do painel (raiz), usada nos webhooks e retornos
function pay_base_url(){
    $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http'));
    $host = !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    return strtolower(trim(explode(',', $proto)[0])).'://'.trim(explode(',', $host)[0]);
}

/* ---------- HTTP ---------- */

function pay_http($method, $url, $headers, $body = null){
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return array('code' => $code, 'body' => $res === false ? '' : $res, 'json' => $res ? json_decode($res, true) : null, 'error' => $err);
}

/* ---------- Pedidos (o valor sempre vem daqui) ---------- */

/**
 * Carrega o pedido do banco e confere o dono. Retorna array com amount/currency/description ou null.
 * $order_type: 'sub' (assinatura de cliente) | 'credit' (créditos de revendedor)
 */
function pay_load_order($mysqli, $order_type, $order_id, $owner_type, $owner_id){
    payments_migrate($mysqli);
    $order_id = (int)$order_id;
    $owner_id = (int)$owner_id;
    if ($order_type == 'sub' && $owner_type == 'customer') {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT s.*, c.email, c.name FROM tbl_subscriptions s JOIN tbl_customers c ON c.id = s.customer_id
                                                       WHERE s.id = $order_id AND s.customer_id = $owner_id"));
        if (!$r) return null;
        return array('type' => 'sub', 'id' => (int)$r['id'], 'status' => $r['status'], 'amount' => (float)$r['amount'], 'currency' => $r['currency'],
                     'description' => brand_name().' - Assinatura '.$r['plan_name'].' (pedido #'.$r['id'].')', 'email' => $r['email'], 'name' => $r['name']);
    }
    if ($order_type == 'credit' && $owner_type == 'reseller') {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT o.*, r.email, r.name FROM tbl_credit_orders o JOIN tbl_resellers r ON r.id = o.reseller_id
                                                       WHERE o.id = $order_id AND o.reseller_id = $owner_id"));
        if (!$r) return null;
        return array('type' => 'credit', 'id' => (int)$r['id'], 'status' => $r['status'], 'amount' => (float)$r['total'], 'currency' => ($r['currency'] == 'USD' ? 'USD' : 'BRL'),
                     'description' => brand_name().' - '.(int)$r['credits'].' créditos (pedido #'.$r['id'].')', 'email' => $r['email'], 'name' => $r['name']);
    }
    return null;
}

// Pedido sem checagem de dono (usado pelo webhook, depois de validar o gateway)
function pay_load_order_any($mysqli, $order_type, $order_id){
    $order_id = (int)$order_id;
    if ($order_type == 'sub') {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, status, amount, currency FROM tbl_subscriptions WHERE id = $order_id"));
        return $r ? array('status' => $r['status'], 'amount' => (float)$r['amount'], 'currency' => $r['currency']) : null;
    }
    if ($order_type == 'credit') {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, status, total, currency FROM tbl_credit_orders WHERE id = $order_id"));
        return $r ? array('status' => $r['status'], 'amount' => (float)$r['total'], 'currency' => ($r['currency'] == 'USD' ? 'USD' : 'BRL')) : null;
    }
    return null;
}

/* ---------- Mercado Pago (Pix) ---------- */

function pay_mp_create_pix($mysqli, $order, $owner_type, $owner_id){
    if (!pay_mp_enabled($mysqli)) return array('ok' => false, 'msg' => 'Pix indisponível no momento.');
    if ($order['currency'] != 'BRL') return array('ok' => false, 'msg' => 'Pix aceita apenas pagamentos em reais.');

    // Reaproveita um Pix pendente e ainda válido do mesmo pedido (evita gerar vários QR Codes)
    $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_payments WHERE provider = 'mercadopago' AND order_type = '".$order['type']."' AND order_id = ".(int)$order['id']."
                                                          AND status = 'pending' AND expires_at > ".(time() + 120)." ORDER BY id DESC LIMIT 1"));
    if ($existing) return array('ok' => true, 'payment_id' => (int)$existing['id']);

    $ref = $order['type'].':'.$order['id'];
    $expires = time() + 30 * 60;
    $body = json_encode(array(
        'transaction_amount' => round($order['amount'], 2),
        'description'        => mb_substr($order['description'], 0, 250),
        'payment_method_id'  => 'pix',
        'external_reference' => $ref,
        'notification_url'   => pay_base_url().'/pagamento/webhook_mp.php',
        'date_of_expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $expires),
        'payer'              => array('email' => $order['email'], 'first_name' => mb_substr($order['name'], 0, 60)),
    ));
    $r = pay_http('POST', MP_API.'/v1/payments', array(
        'Authorization: Bearer '.pay_cfg($mysqli, 'mp_token'),
        'Content-Type: application/json',
        'X-Idempotency-Key: '.$ref.'-'.bin2hex(random_bytes(6)),
    ), $body);

    if ($r['code'] < 200 || $r['code'] >= 300 || empty($r['json']['id'])) {
        error_log('[mercadopago] '.$r['code'].' '.$r['body']);
        $msg = !empty($r['json']['message']) ? $r['json']['message'] : 'Não foi possível gerar o Pix.';
        return array('ok' => false, 'msg' => 'Mercado Pago: '.$msg);
    }
    $tx = isset($r['json']['point_of_interaction']['transaction_data']) ? $r['json']['point_of_interaction']['transaction_data'] : array();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_payments (provider, order_type, order_id, owner_type, owner_id, amount, currency, status, provider_id, qr_code, qr_base64, expires_at, created_at)
                                     VALUES ('mercadopago', ?, ?, ?, ?, ?, 'BRL', 'pending', ?, ?, ?, ?, ?)");
    $pid = (string)$r['json']['id'];
    $qr = isset($tx['qr_code']) ? $tx['qr_code'] : '';
    $qr64 = isset($tx['qr_code_base64']) ? $tx['qr_code_base64'] : '';
    $now = time();
    $amount = $order['amount'];
    mysqli_stmt_bind_param($stmt, 'sisidsssii', $order['type'], $order['id'], $owner_type, $owner_id, $amount, $pid, $qr, $qr64, $expires, $now);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($mysqli);
    mysqli_stmt_close($stmt);
    return array('ok' => true, 'payment_id' => $id);
}

// Consulta o pagamento no Mercado Pago (fonte da verdade) e libera o pedido se aprovado e o valor bater
function pay_mp_sync($mysqli, $mp_payment_id){
    payments_migrate($mysqli);
    $mp_payment_id = preg_replace('/\D/', '', (string)$mp_payment_id);
    if ($mp_payment_id == '' || pay_cfg($mysqli, 'mp_token') == '') return false;
    $r = pay_http('GET', MP_API.'/v1/payments/'.$mp_payment_id, array('Authorization: Bearer '.pay_cfg($mysqli, 'mp_token')));
    if ($r['code'] != 200 || empty($r['json']['id'])) return false;
    $p = $r['json'];

    $ref = isset($p['external_reference']) ? $p['external_reference'] : '';
    if (!preg_match('/^(sub|credit):(\d+)$/', $ref, $m)) return false;
    $status = isset($p['status']) ? $p['status'] : '';
    $paid_amount = isset($p['transaction_amount']) ? (float)$p['transaction_amount'] : 0;
    $currency = isset($p['currency_id']) ? strtoupper($p['currency_id']) : 'BRL';

    if ($status == 'approved') {
        return pay_release($mysqli, 'mercadopago', (string)$p['id'], $m[1], (int)$m[2], $paid_amount, $currency);
    }
    if (in_array($status, array('rejected', 'cancelled', 'refunded', 'charged_back'))) {
        $st = $status == 'cancelled' ? 'expired' : 'failed';
        $pid = mysqli_real_escape_string($mysqli, (string)$p['id']);
        mysqli_query($mysqli, "UPDATE tbl_payments SET status = '$st' WHERE provider = 'mercadopago' AND provider_id = '$pid' AND status = 'pending'");
    }
    return false;
}

// Validação opcional da assinatura do webhook do Mercado Pago (x-signature), se a chave secreta estiver configurada
function pay_mp_signature_ok($mysqli, $data_id){
    $secret = pay_cfg($mysqli, 'mp_webhook_secret');
    if ($secret == '') return true; // sem chave configurada: a segurança vem da consulta à API
    $sig = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
    $req = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : '';
    $ts = ''; $v1 = '';
    foreach (explode(',', $sig) as $part) {
        $kv = array_map('trim', explode('=', $part, 2));
        if (count($kv) == 2 && $kv[0] == 'ts') $ts = $kv[1];
        if (count($kv) == 2 && $kv[0] == 'v1') $v1 = $kv[1];
    }
    if ($ts == '' || $v1 == '') return false;
    $manifest = 'id:'.strtolower($data_id).';request-id:'.$req.';ts:'.$ts.';';
    return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
}

/* ---------- Stripe (cartão) ---------- */

function pay_stripe_create_checkout($mysqli, $order, $owner_type, $owner_id, $return_url){
    if (!pay_stripe_enabled($mysqli)) return array('ok' => false, 'msg' => 'Pagamento com cartão indisponível no momento.');
    $cents = (int)round($order['amount'] * 100);
    if ($cents <= 0) return array('ok' => false, 'msg' => 'Valor inválido.');

    $now = time();
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_payments (provider, order_type, order_id, owner_type, owner_id, amount, currency, status, created_at) VALUES ('stripe', ?, ?, ?, ?, ?, ?, 'pending', ?)");
    $amount = $order['amount'];
    mysqli_stmt_bind_param($stmt, 'sisidsi', $order['type'], $order['id'], $owner_type, $owner_id, $amount, $order['currency'], $now);
    mysqli_stmt_execute($stmt);
    $local_id = mysqli_insert_id($mysqli);
    mysqli_stmt_close($stmt);

    $ref = $order['type'].':'.$order['id'];
    $sep = strpos($return_url, '?') === false ? '?' : '&';
    $params = array(
        'mode' => 'payment',
        'client_reference_id' => $ref,
        'customer_email' => $order['email'],
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]' => strtolower($order['currency']),
        'line_items[0][price_data][unit_amount]' => $cents,
        'line_items[0][price_data][product_data][name]' => mb_substr($order['description'], 0, 250),
        'metadata[ref]' => $ref,
        'metadata[payment_id]' => $local_id,
        'payment_intent_data[metadata][ref]' => $ref,
        'success_url' => $return_url.$sep.'p='.$local_id.'&s=ok',
        'cancel_url' => $return_url.$sep.'p='.$local_id.'&s=cancel',
        'expires_at' => $now + 60 * 60,
    );
    $r = pay_http('POST', STRIPE_API.'/v1/checkout/sessions', array(
        'Authorization: Bearer '.pay_cfg($mysqli, 'stripe_secret'),
        'Content-Type: application/x-www-form-urlencoded',
        'Idempotency-Key: pay-'.$local_id,
    ), http_build_query($params));

    if ($r['code'] < 200 || $r['code'] >= 300 || empty($r['json']['id'])) {
        error_log('[stripe] '.$r['code'].' '.$r['body']);
        mysqli_query($mysqli, "UPDATE tbl_payments SET status = 'failed' WHERE id = ".(int)$local_id);
        $msg = !empty($r['json']['error']['message']) ? $r['json']['error']['message'] : 'Não foi possível iniciar o pagamento.';
        return array('ok' => false, 'msg' => 'Stripe: '.$msg);
    }
    $sid = mysqli_real_escape_string($mysqli, $r['json']['id']);
    $url = mysqli_real_escape_string($mysqli, $r['json']['url']);
    mysqli_query($mysqli, "UPDATE tbl_payments SET provider_id = '$sid', checkout_url = '$url', expires_at = ".($now + 3600)." WHERE id = ".(int)$local_id);
    return array('ok' => true, 'payment_id' => $local_id, 'url' => $r['json']['url']);
}

// Verifica a assinatura do webhook do Stripe (Stripe-Signature: t=...,v1=...)
function pay_stripe_signature_ok($mysqli, $payload){
    $secret = pay_cfg($mysqli, 'stripe_webhook_secret');
    $header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
    if ($secret == '' || $header == '') return false;
    $ts = ''; $sigs = array();
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) != 2) continue;
        if ($kv[0] == 't') $ts = $kv[1];
        if ($kv[0] == 'v1') $sigs[] = $kv[1];
    }
    if ($ts == '' || !$sigs || abs(time() - (int)$ts) > 600) return false;
    $expected = hash_hmac('sha256', $ts.'.'.$payload, $secret);
    foreach ($sigs as $s) { if (hash_equals($expected, $s)) return true; }
    return false;
}

// Consulta a sessão no Stripe (fonte da verdade) e libera se paga e o valor bater
function pay_stripe_sync($mysqli, $session_id){
    payments_migrate($mysqli);
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', (string)$session_id) || pay_cfg($mysqli, 'stripe_secret') == '') return false;
    $r = pay_http('GET', STRIPE_API.'/v1/checkout/sessions/'.$session_id, array('Authorization: Bearer '.pay_cfg($mysqli, 'stripe_secret')));
    if ($r['code'] != 200 || empty($r['json']['id'])) return false;
    $s = $r['json'];
    $ref = isset($s['client_reference_id']) ? $s['client_reference_id'] : '';
    if (!preg_match('/^(sub|credit):(\d+)$/', $ref, $m)) return false;
    if (isset($s['payment_status']) && $s['payment_status'] == 'paid') {
        return pay_release($mysqli, 'stripe', $s['id'], $m[1], (int)$m[2], ((int)$s['amount_total']) / 100, strtoupper($s['currency']));
    }
    if (isset($s['status']) && $s['status'] == 'expired') {
        $sid = mysqli_real_escape_string($mysqli, $s['id']);
        mysqli_query($mysqli, "UPDATE tbl_payments SET status = 'expired' WHERE provider = 'stripe' AND provider_id = '$sid' AND status = 'pending'");
    }
    return false;
}

/* ---------- Liberação do pedido (comum aos dois gateways) ---------- */

/**
 * Confere valor e moeda com o pedido do banco e libera (gera código / adiciona créditos). Idempotente.
 */
function pay_release($mysqli, $provider, $provider_id, $order_type, $order_id, $paid_amount, $currency){
    $order = pay_load_order_any($mysqli, $order_type, $order_id);
    if (!$order) return false;
    if (abs($order['amount'] - (float)$paid_amount) > 0.009 || strtoupper($order['currency']) != strtoupper($currency)) {
        error_log("[pagamento] valor/moeda divergente em $order_type:$order_id ($paid_amount $currency x {$order['amount']} {$order['currency']})");
        return false;
    }
    $now = time();
    $pid = mysqli_real_escape_string($mysqli, $provider_id);
    $prov = mysqli_real_escape_string($mysqli, $provider);
    $ref_note = ($provider == 'mercadopago' ? 'Mercado Pago Pix ' : 'Stripe ').$provider_id;

    $released = false;
    if ($order['status'] == 'pending') {
        if ($order_type == 'sub') {
            mysqli_query($mysqli, "UPDATE tbl_subscriptions SET method = '$prov' WHERE id = ".(int)$order_id);
            $released = (bool)subscription_mark_paid($mysqli, $order_id, 0, $ref_note);
        } else {
            mysqli_query($mysqli, "UPDATE tbl_credit_orders SET method = '$prov' WHERE id = ".(int)$order_id);
            $released = credit_orders_mark_paid($mysqli, $order_id, 0, $ref_note);
        }
    }
    // Marca o registro do pagamento como pago (mesmo se o pedido já tinha sido liberado antes)
    mysqli_query($mysqli, "UPDATE tbl_payments SET status = 'paid', paid_at = $now WHERE provider = '$prov' AND provider_id = '$pid'");
    if ($provider == 'stripe' && !$released) {
        // Sessão pode ter sido criada sem provider_id gravado ainda: amarra pelo pedido
        mysqli_query($mysqli, "UPDATE tbl_payments SET status = 'paid', paid_at = $now WHERE provider = 'stripe' AND order_type = '".mysqli_real_escape_string($mysqli, $order_type)."' AND order_id = ".(int)$order_id." AND status = 'pending' AND provider_id = '$pid'");
    }
    return true;
}

/* ---------- Status para a tela (SSE) ---------- */

// Situação de um pagamento do dono logado: pending | paid | failed | expired
function pay_status_for_owner($mysqli, $payment_id, $owner_type, $owner_id){
    $p = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_payments WHERE id = ".(int)$payment_id." AND owner_type = '".mysqli_real_escape_string($mysqli, $owner_type)."' AND owner_id = ".(int)$owner_id));
    if (!$p) return null;
    // O pedido pode ter sido liberado por outro pagamento/admin
    $order = pay_load_order_any($mysqli, $p['order_type'], $p['order_id']);
    if ($order && $order['status'] == 'paid') return 'paid';
    if ($p['status'] == 'pending' && $p['expires_at'] > 0 && $p['expires_at'] < time()) return 'expired';
    return $p['status'];
}

// Dono logado na sessão (cliente ou revendedor). Retorna array(type, id) ou null.
function pay_session_owner(){
    if (!empty($_SESSION['cl_id'])) return array('customer', (int)$_SESSION['cl_id']);
    if (!empty($_SESSION['rv_id'])) return array('reseller', (int)$_SESSION['rv_id']);
    return null;
}
