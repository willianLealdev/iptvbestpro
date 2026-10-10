<?php
/**
 * Compra pela Apple (App Store) no app de iPhone / iPad / Apple TV.
 * Só para cliente direto: aparelho de revenda nunca compra pela Apple (renova com a revenda).
 *
 * O app manda a transação assinada pela Apple (JWS do StoreKit 2). Aqui o painel confere:
 *  - a corrente de certificados termina no certificado raiz da Apple (Apple Root CA - G3, guardado abaixo);
 *  - a assinatura ES256 da transação;
 *  - app (bundle), produto, vencimento e se não foi reembolsada.
 * Depois ativa o aparelho como cliente direto até o vencimento da assinatura.
 * Renovações, cancelamentos e reembolsos chegam pelos avisos da Apple (apple_notify.php).
 */
require_once(__DIR__."/customer_helper.php");
require_once(__DIR__."/reseller_helper.php");

define('APPLE_BUNDLE_ID', 'com.iptvbestpro.app');
define('APPLE_PRODUCT_YEAR', 'com.iptvbestpro.app.year');

function apple_root_g3_pem(){
    // Apple Root CA - G3 · SHA-256 63:34:3A:BF:B8:9A:6A:03:EB:B5:7E:9B:3F:5F:A7:BE:7C:4F:5C:75:6F:30:17:B3:A8:C4:88:C3:65:3E:91:79
    return "-----BEGIN CERTIFICATE-----\n".
"MIICQzCCAcmgAwIBAgIILcX8iNLFS5UwCgYIKoZIzj0EAwMwZzEbMBkGA1UEAwwS\n".
"QXBwbGUgUm9vdCBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9u\n".
"IEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwHhcN\n".
"MTQwNDMwMTgxOTA2WhcNMzkwNDMwMTgxOTA2WjBnMRswGQYDVQQDDBJBcHBsZSBS\n".
"b290IENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9y\n".
"aXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzB2MBAGByqGSM49\n".
"AgEGBSuBBAAiA2IABJjpLz1AcqTtkyJygRMc3RCV8cWjTnHcFBbZDuWmBSp3ZHtf\n".
"TjjTuxxEtX/1H7YyYl3J6YRbTzBPEVoA/VhYDKX1DyxNB0cTddqXl5dvMVztK517\n".
"IDvYuVTZXpmkOlEKMaNCMEAwHQYDVR0OBBYEFLuw3qFYM4iapIqZ3r6966/ayySr\n".
"MA8GA1UdEwEB/wQFMAMBAf8wDgYDVR0PAQH/BAQDAgEGMAoGCCqGSM49BAMDA2gA\n".
"MGUCMQCD6cHEFl4aXTQY2e3v9GwOAEZLuN+yRhHFD/3meoyhpmvOwgPUnPWTxnS4\n".
"at+qIxUCMG1mihDK1A3UT82NQz60imOlM27jbdoXt2QfyFMm+YhidDkLF1vLUagM\n".
"6BgD56KyKA==\n".
"-----END CERTIFICATE-----\n";
}

function apple_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    customers_migrate($mysqli);
    resellers_migrate($mysqli);
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_apple_subs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `original_tx` VARCHAR(64) NOT NULL,
        `last_tx` VARCHAR(64) NOT NULL DEFAULT '',
        `device_pk` INT(11) NOT NULL DEFAULT 0,
        `customer_id` INT(11) NOT NULL DEFAULT 0,
        `product_id` VARCHAR(120) NOT NULL DEFAULT '',
        `environment` VARCHAR(20) NOT NULL DEFAULT '',
        `expires_at` INT(11) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active',   -- active | expired | refunded
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_apple_original` (`original_tx`),
        KEY `idx_device` (`device_pk`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function apple_b64url($s){
    $s = strtr((string)$s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s, true);
}

function apple_der_pem($der_b64){
    return "-----BEGIN CERTIFICATE-----\n".chunk_split(preg_replace('/\s+/', '', (string)$der_b64), 64, "\n")."-----END CERTIFICATE-----\n";
}

// Assinatura JOSE (r||s, 64 bytes) -> DER, que é o formato do openssl_verify
function apple_sig_der($raw){
    if (strlen($raw) != 64) return false;
    $enc = function ($int) {
        $int = ltrim($int, "\x00");
        if ($int === '' ) $int = "\x00";
        if (ord($int[0]) > 0x7f) $int = "\x00".$int;
        return "\x02".chr(strlen($int)).$int;
    };
    $seq = $enc(substr($raw, 0, 32)).$enc(substr($raw, 32, 32));
    return "\x30".chr(strlen($seq)).$seq;
}

/**
 * Confere um JWS assinado pela Apple (transação ou aviso). Devolve o conteúdo (array) ou false.
 */
function apple_jws_verify($jws, $root_pem = null){
    if ($root_pem === null) $root_pem = apple_root_g3_pem(); // outro certificado raiz só nos testes automáticos
    $parts = explode('.', (string)$jws);
    if (count($parts) != 3) return false;
    $header = json_decode(apple_b64url($parts[0]), true);
    $payload = json_decode(apple_b64url($parts[1]), true);
    $sig = apple_b64url($parts[2]);
    if (!is_array($header) || !is_array($payload) || $sig === false) return false;
    if (!isset($header['alg']) || $header['alg'] !== 'ES256' || empty($header['x5c']) || !is_array($header['x5c']) || count($header['x5c']) < 3) return false;

    $leaf = apple_der_pem($header['x5c'][0]);
    $inter = apple_der_pem($header['x5c'][1]);
    $root = apple_der_pem($header['x5c'][2]);
    // o certificado raiz da corrente tem que ser exatamente o da Apple
    $ours = openssl_x509_fingerprint($root_pem, 'sha256');
    $theirs = openssl_x509_fingerprint($root, 'sha256');
    if (!$ours || !$theirs || !hash_equals(strtolower($ours), strtolower($theirs))) return false;
    // intermediário assinado pela raiz; folha assinada pelo intermediário
    if (openssl_x509_verify($inter, openssl_pkey_get_public($root_pem)) !== 1) return false;
    if (openssl_x509_verify($leaf, openssl_pkey_get_public($inter)) !== 1) return false;
    // validade dos certificados
    $now = time();
    foreach (array($leaf, $inter) as $c) {
        $info = openssl_x509_parse($c);
        if (!$info || $now < (int)$info['validFrom_time_t'] - 86400 || $now > (int)$info['validTo_time_t'] + 86400) return false;
    }
    $der = apple_sig_der($sig);
    if ($der === false) return false;
    $ok = openssl_verify($parts[0].'.'.$parts[1], $der, openssl_pkey_get_public($leaf), OPENSSL_ALGO_SHA256);
    return $ok === 1 ? $payload : false;
}

// Cliente que representa a compra pela Apple (um por assinatura; a Apple não informa o e-mail do comprador)
function apple_customer_for($mysqli, $original_tx, $currency){
    $email = 'apple-'.preg_replace('/[^0-9A-Za-z]/', '', $original_tx).'@iptvbestpro.com';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_customers WHERE email = '".mysqli_real_escape_string($mysqli, $email)."'"));
    if ($row) return (int)$row['id'];
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $now = time();
    $name = 'Cliente Apple';
    $cur = $currency == 'BRL' ? 'BRL' : 'USD';
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_customers (name, email, password, status, currency, created_at) VALUES (?, ?, ?, 1, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $hash, $cur, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (int)mysqli_insert_id($mysqli);
}

// Registra o pagamento no Histórico de Pagamentos (uma linha por transação da Apple)
function apple_record_payment($mysqli, $customer_id, $device_pk, $tx){
    $txid = (string)$tx['transactionId'];
    $ref = 'Apple '.$txid;
    $exists = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_subscriptions WHERE method = 'apple' AND reference = '".mysqli_real_escape_string($mysqli, $ref)."'"));
    if ($exists) return;
    $now = time();
    $paid = isset($tx['purchaseDate']) ? (int)floor($tx['purchaseDate'] / 1000) : $now;
    $exp = isset($tx['expiresDate']) ? (int)floor($tx['expiresDate'] / 1000) : 0;
    $currency = isset($tx['currency']) ? strtoupper(substr((string)$tx['currency'], 0, 3)) : 'USD';
    $amount = isset($tx['price']) ? round(((float)$tx['price']) / 1000, 2) : 0; // a Apple manda o preço em milésimos
    $plan = 'Assinatura anual (Apple)';
    $cid = (int)$customer_id; $dpk = (int)$device_pk;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_subscriptions (customer_id, plan_id, plan_name, months, currency, amount, status, created_at, paid_at, device_pk, activated_at, expires_at, method, reference)
                                     VALUES (?, 0, ?, 12, ?, ?, 'paid', ?, ?, ?, ?, ?, 'apple', ?)");
    mysqli_stmt_bind_param($stmt, 'issdiiiiis', $cid, $plan, $currency, $amount, $now, $paid, $dpk, $paid, $exp, $ref);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Aplica uma transação válida no aparelho ligado à assinatura
function apple_apply($mysqli, $tx, $device = null){
    apple_migrate($mysqli);
    if (!is_array($tx) || ($tx['bundleId'] ?? '') !== APPLE_BUNDLE_ID) return array('ok' => false, 'msg' => 'Compra de outro app.');
    if (($tx['productId'] ?? '') !== APPLE_PRODUCT_YEAR) return array('ok' => false, 'msg' => 'Produto desconhecido.');
    $otx = substr((string)($tx['originalTransactionId'] ?? ''), 0, 64);
    if ($otx === '') return array('ok' => false, 'msg' => 'Compra inválida.');
    $now = time();
    $exp = isset($tx['expiresDate']) ? (int)floor($tx['expiresDate'] / 1000) : 0;
    $revoked = !empty($tx['revocationDate']);
    $env = substr((string)($tx['environment'] ?? ''), 0, 20);

    $sub = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_apple_subs WHERE original_tx = '".mysqli_real_escape_string($mysqli, $otx)."'"));
    if ($device) {
        // a mesma assinatura da Apple vale para um aparelho só (o primeiro que a usou)
        if ($sub && (int)$sub['device_pk'] > 0 && (int)$sub['device_pk'] !== (int)$device['id']) {
            $old = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_devices WHERE id = ".(int)$sub['device_pk']));
            if ($old) return array('ok' => false, 'msg' => 'Esta assinatura já está em uso em outro aparelho.');
        }
        if ($device['owner'] == 'reseller') return array('ok' => false, 'msg' => 'Este aparelho é de uma revenda. Renove com a sua revenda.');
        $dpk = (int)$device['id'];
    } else {
        if (!$sub) return array('ok' => false, 'msg' => 'Assinatura não encontrada.');
        $dpk = (int)$sub['device_pk'];
        $device = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $dpk"));
        if (!$device) return array('ok' => false, 'msg' => 'Aparelho não encontrado.');
        if ($device['owner'] == 'reseller') return array('ok' => false, 'msg' => 'Aparelho passou para uma revenda.');
    }

    $currency = isset($tx['currency']) ? strtoupper((string)$tx['currency']) : 'USD';
    $cid = $sub && (int)$sub['customer_id'] > 0 ? (int)$sub['customer_id'] : apple_customer_for($mysqli, $otx, $currency);
    $status = $revoked ? 'refunded' : ($exp > $now ? 'active' : 'expired');
    $last = substr((string)($tx['transactionId'] ?? ''), 0, 64);
    $pid = APPLE_PRODUCT_YEAR;
    if ($sub) {
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_apple_subs SET last_tx = ?, device_pk = ?, customer_id = ?, environment = ?, expires_at = ?, status = ?, updated_at = ? WHERE id = ?");
        $sid = (int)$sub['id'];
        mysqli_stmt_bind_param($stmt, 'siisisii', $last, $dpk, $cid, $env, $exp, $status, $now, $sid);
    } else {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_apple_subs (original_tx, last_tx, device_pk, customer_id, product_id, environment, expires_at, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssiissisii', $otx, $last, $dpk, $cid, $pid, $env, $exp, $status, $now, $now);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($status == 'active') {
        $by = 'cliente: Apple';
        $cname = 'Cliente Apple';
        $was_other = $device['owner'] != 'customer' || (int)$device['customer_id'] !== $cid;
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_devices SET owner = 'customer', reseller_id = 0, customer_id = ?, linked_at = IF(linked_at > 0, linked_at, ?), package = 'sub', activated_by = ?,
                                         activated_at = IF(activated_at > 0 AND expires_at > ?, activated_at, ?), expires_at = ?, customer_name = IF(customer_name = '', ?, customer_name) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'iisiiisi', $cid, $now, $by, $now, $now, $exp, $cname, $dpk);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($was_other && $device['owner'] != '' ) devices_wipe_playlists($mysqli, $device['device_id']);
        apple_record_payment($mysqli, $cid, $dpk, $tx);
    } else if ($device['owner'] == 'customer' && (int)$device['customer_id'] === $cid) {
        // venceu ou foi reembolsada: o aparelho deixa de assistir
        $until = $revoked ? $now - 1 : min((int)$device['expires_at'], $exp > 0 ? $exp : $now - 1);
        mysqli_query($mysqli, "UPDATE tbl_devices SET expires_at = ".(int)$until." WHERE id = $dpk");
        if ($revoked) mysqli_query($mysqli, "UPDATE tbl_subscriptions SET status = 'cancelled' WHERE method = 'apple' AND reference = 'Apple ".mysqli_real_escape_string($mysqli, $last)."'");
    }
    $device = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $dpk"));
    return array('ok' => $status == 'active', 'msg' => $status == 'active' ? 'Assinatura ativada! Válida até '.date('d/m/Y', $exp).'.' : 'Esta assinatura não está mais ativa.', 'device' => $device);
}

// Chamado pelo app (helper_name = apple_iap): ativa o aparelho com a compra feita agora (ou "Restaurar compras")
function apple_claim($mysqli, $data){
    apple_migrate($mysqli);
    if (empty($data['device_id'])) return array('ok' => false, 'msg' => 'Dispositivo não identificado.', 'device' => null);
    $jws = isset($data['signed_transaction']) ? (string)$data['signed_transaction'] : '';
    if (strlen($jws) < 100 || strlen($jws) > 20000) return array('ok' => false, 'msg' => 'Compra inválida.', 'device' => null);
    $tx = apple_jws_verify($jws);
    if (!$tx) return array('ok' => false, 'msg' => 'Não foi possível confirmar a compra com a Apple.', 'device' => null);
    devices_register($mysqli, $data);
    $device = devices_find($mysqli, devices_clean($data['device_id'], 191), devices_clean(isset($data['application_id']) ? $data['application_id'] : '', 191));
    if (!$device) return array('ok' => false, 'msg' => 'Erro ao registrar o dispositivo.', 'device' => null);
    $r = apple_apply($mysqli, $tx, $device);
    if (!isset($r['device'])) $r['device'] = $device;
    return $r;
}

// Avisos da Apple (App Store Server Notifications V2): renovação, cancelamento, reembolso
function apple_notification($mysqli, $signed_payload){
    apple_migrate($mysqli);
    $n = apple_jws_verify($signed_payload);
    if (!$n || empty($n['data']['signedTransactionInfo'])) return false;
    if (($n['data']['bundleId'] ?? '') !== APPLE_BUNDLE_ID) return false;
    $tx = apple_jws_verify($n['data']['signedTransactionInfo']);
    if (!$tx) return false;
    $type = (string)($n['notificationType'] ?? '');
    if (in_array($type, array('REFUND', 'REVOKE'), true) && empty($tx['revocationDate'])) $tx['revocationDate'] = time() * 1000;
    apple_apply($mysqli, $tx, null);
    return true;
}
