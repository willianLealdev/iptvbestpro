<?php
/**
 * Compra pela Amazon (Amazon Appstore In-App Purchasing) no app da Amazon (Fire TV / tablets Fire).
 * Igual à compra pela Apple: só para aparelho sem revenda ativa (cliente direto).
 *
 * O app manda o recibo da Amazon (userId + receiptId). Aqui o painel confere o recibo direto com a Amazon
 * (Receipt Verification Service, com a "Shared Key" da conta de desenvolvedor, guardada em Gateways de Pagamento)
 * e ativa o aparelho como cliente direto até a próxima renovação da assinatura.
 * Renovação e cancelamento: o painel confere o recibo de novo quando a assinatura está perto de vencer.
 */
require_once(__DIR__."/customer_helper.php");
require_once(__DIR__."/reseller_helper.php");
require_once(__DIR__."/payment_helper.php");

define('AMAZON_IAP_SKU', 'com.iptvbestpro.app.year');   // SKU do período (term) de 1 ano da assinatura
define('AMAZON_RVS', 'https://appstore-sdk.amazon.com');

function amazon_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    customers_migrate($mysqli);
    resellers_migrate($mysqli);
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_amazon_subs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_id` VARCHAR(191) NOT NULL,
        `user_id` VARCHAR(191) NOT NULL DEFAULT '',
        `device_pk` INT(11) NOT NULL DEFAULT 0,
        `customer_id` INT(11) NOT NULL DEFAULT 0,
        `sku` VARCHAR(120) NOT NULL DEFAULT '',
        `sandbox` TINYINT(1) NOT NULL DEFAULT 0,
        `test` TINYINT(1) NOT NULL DEFAULT 0,
        `expires_at` INT(11) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active',   -- active | expired | cancelled
        `checked_at` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_amazon_receipt` (`receipt_id`),
        KEY `idx_device` (`device_pk`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Registro das últimas tentativas de compra (aparece em Gateways de Pagamento para ver o motivo de um erro)
function amazon_log($mysqli, $msg, $extra = array()){
    $log = json_decode(pay_cfg($mysqli, 'amazon_log', '[]'), true);
    if (!is_array($log)) $log = array();
    array_unshift($log, array_merge(array('t' => time(), 'msg' => mb_substr((string)$msg, 0, 200)), $extra));
    pay_set($mysqli, 'amazon_log', json_encode(array_slice($log, 0, 15), JSON_UNESCAPED_UNICODE));
}

// SKU da assinatura: a Amazon pode devolver o SKU do período (term) ou o SKU "pai" da assinatura
function amazon_sku_ok($sku){
    $sku = (string)$sku;
    return $sku === AMAZON_IAP_SKU || $sku === 'com.iptvbestpro.app.sub' || strpos($sku, 'com.iptvbestpro.app') === 0;
}

function amazon_enabled($mysqli){ return pay_cfg($mysqli, 'amazon_secret') != ''; }

/**
 * Confere o recibo com a Amazon. $sandbox = compra de teste do App Tester (endpoint de teste da Amazon).
 * Retorna array('ok' => bool, 'code' => http, 'json' => resposta)
 */
function amazon_rvs($mysqli, $user_id, $receipt_id, $sandbox = false){
    $secret = pay_cfg($mysqli, 'amazon_secret');
    if ($secret == '') return array('ok' => false, 'code' => 0, 'json' => null);
    // ':' e '=' fazem parte da Shared Key e dos recibos da Amazon e são válidos no caminho da URL: ficam como estão
    $seg = function ($x) { return str_replace(array('%3A', '%3D'), array(':', '='), rawurlencode(trim((string)$x))); };
    $url = AMAZON_RVS.($sandbox ? '/sandbox' : '').'/version/1.0/verifyReceiptId/developer/'.$seg($secret)
         .'/user/'.$seg($user_id).'/receiptId/'.$seg($receipt_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    return array('ok' => $code == 200 && is_array($json), 'code' => $code, 'json' => $json);
}

// Cliente que representa a compra pela Amazon (um por conta Amazon; a Amazon não informa o e-mail do comprador)
function amazon_customer_for($mysqli, $user_id){
    $email = 'amazon-'.substr(preg_replace('/[^0-9A-Za-z]/', '', $user_id), 0, 60).'@iptvbestpro.com';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_customers WHERE email = '".mysqli_real_escape_string($mysqli, $email)."'"));
    if ($row) return (int)$row['id'];
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $now = time();
    $name = 'Cliente Amazon';
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_customers (name, email, password, status, currency, created_at) VALUES (?, ?, ?, 1, 'USD', ?)");
    mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $hash, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (int)mysqli_insert_id($mysqli);
}

// Registra o período pago no Histórico de Pagamentos (uma linha por período da assinatura)
function amazon_record_payment($mysqli, $customer_id, $device_pk, $receipt_id, $paid, $exp, $test){
    $ref = 'Amazon '.substr($receipt_id, 0, 120).' '.date('Y-m-d', $exp);
    $exists = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_subscriptions WHERE method = 'amazon' AND reference = '".mysqli_real_escape_string($mysqli, $ref)."'"));
    if ($exists) return;
    $now = time();
    $plan = 'Assinatura anual (Amazon)'.($test ? ' · teste' : '');
    $cid = (int)$customer_id; $dpk = (int)$device_pk;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_subscriptions (customer_id, plan_id, plan_name, months, currency, amount, status, created_at, paid_at, device_pk, activated_at, expires_at, method, reference)
                                     VALUES (?, 0, ?, 12, 'USD', 0, 'paid', ?, ?, ?, ?, ?, 'amazon', ?)");
    mysqli_stmt_bind_param($stmt, 'isiiiiis', $cid, $plan, $now, $paid, $dpk, $paid, $exp, $ref);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Aplica um recibo conferido no aparelho. $rcpt = resposta da Amazon.
 * O vencimento é a próxima renovação (renewalDate); cancelada = vale até a data do cancelamento.
 */
function amazon_apply($mysqli, $user_id, $receipt_id, $rcpt, $sandbox, $device){
    amazon_migrate($mysqli);
    $pid = (string)($rcpt['productId'] ?? '');
    $term = (string)($rcpt['termSku'] ?? '');
    if (!amazon_sku_ok($pid) && !amazon_sku_ok($term)) {
        amazon_log($mysqli, 'Produto desconhecido', array('sku' => $pid, 'term' => $term));
        return array('ok' => false, 'msg' => 'Produto desconhecido.', 'device' => $device);
    }
    $now = time();
    $purchase = isset($rcpt['purchaseDate']) ? (int)floor($rcpt['purchaseDate'] / 1000) : $now;
    $cancel = !empty($rcpt['cancelDate']) ? (int)floor($rcpt['cancelDate'] / 1000) : 0;
    $renew = !empty($rcpt['renewalDate']) ? (int)floor($rcpt['renewalDate'] / 1000) : 0;
    $exp = $cancel > 0 ? $cancel : ($renew > 0 ? $renew : strtotime('+1 year', $purchase));
    // compra de teste (App Tester) sem o teste ligado no painel: vale só 24 horas (a Amazon consegue revisar; não serve para usar de graça)
    if ($sandbox && pay_cfg($mysqli, 'amazon_sandbox_ok', '0') != '1') $exp = min($exp, $now + 86400);
    $test = !empty($rcpt['testTransaction']) ? 1 : 0;
    $status = $exp > $now ? 'active' : ($cancel > 0 ? 'cancelled' : 'expired');

    $rid = mysqli_real_escape_string($mysqli, $receipt_id);
    $sub = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_amazon_subs WHERE receipt_id = '$rid'"));
    // a mesma assinatura da Amazon vale para um aparelho só (o primeiro que a usou)
    if ($sub && (int)$sub['device_pk'] > 0 && (int)$sub['device_pk'] !== (int)$device['id']) {
        $old = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_devices WHERE id = ".(int)$sub['device_pk']));
        if ($old) return array('ok' => false, 'msg' => 'Esta assinatura já está em uso em outro aparelho.', 'device' => $device);
    }
    $cid = $sub && (int)$sub['customer_id'] > 0 ? (int)$sub['customer_id'] : amazon_customer_for($mysqli, $user_id);
    // Regra do dono: aparelho ativo com revenda/servidor/outro cliente não é pego pela compra da Amazon
    $mine = $device['owner'] == 'customer' && (int)$device['customer_id'] === $cid;
    if (!$mine && (($device['owner'] != '' && devices_activation_status($device) == 'active')
        || devices_active_elsewhere($mysqli, $device['device_id'], 'customer', 0, $cid, (int)$device['id']))) {
        return array('ok' => false, 'msg' => 'Este aparelho já está ativo com outra revenda. Para assinar pela Amazon, resete a licença no app (Configurações → Resetar licença).', 'device' => $device);
    }
    $dpk = (int)$device['id'];
    $sku = AMAZON_IAP_SKU;
    $sb = $sandbox ? 1 : 0;
    $uid = substr($user_id, 0, 191);
    if ($sub) {
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_amazon_subs SET user_id = ?, device_pk = ?, customer_id = ?, sandbox = ?, test = ?, expires_at = ?, status = ?, checked_at = ?, updated_at = ? WHERE id = ?");
        $sid = (int)$sub['id'];
        mysqli_stmt_bind_param($stmt, 'siiiiisiii', $uid, $dpk, $cid, $sb, $test, $exp, $status, $now, $now, $sid);
    } else {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_amazon_subs (receipt_id, user_id, device_pk, customer_id, sku, sandbox, test, expires_at, status, checked_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $r191 = substr($receipt_id, 0, 191);
        mysqli_stmt_bind_param($stmt, 'ssiisiiisiii', $r191, $uid, $dpk, $cid, $sku, $sb, $test, $exp, $status, $now, $now, $now);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($status == 'active') {
        if (!$mine && $device['owner'] != '') {
            devices_take_over($mysqli, $dpk, 'customer', 0, $cid);
            $device = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $dpk"));
        }
        $by = 'cliente: Amazon';
        $cname = 'Cliente Amazon';
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_devices SET owner = 'customer', reseller_id = 0, customer_id = ?, linked_at = IF(linked_at > 0, linked_at, ?), package = 'sub', activated_by = ?,
                                         activated_at = IF(activated_at > 0 AND expires_at > ?, activated_at, ?), expires_at = ?, customer_name = IF(customer_name = '', ?, customer_name) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'iisiiisi', $cid, $now, $by, $now, $now, $exp, $cname, $dpk);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        amazon_record_payment($mysqli, $cid, $dpk, $receipt_id, $purchase, $exp, $test);
    } else if ($mine) {
        // venceu ou foi cancelada: o aparelho deixa de assistir
        $until = min((int)$device['expires_at'], $exp > 0 ? $exp : $now - 1);
        mysqli_query($mysqli, "UPDATE tbl_devices SET expires_at = ".(int)$until." WHERE id = $dpk");
    }
    $device = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $dpk"));
    return array('ok' => $status == 'active', 'msg' => $status == 'active' ? 'Assinatura ativada! Válida até '.date('d/m/Y', $exp).'.' : 'Esta assinatura não está mais ativa.', 'device' => $device);
}

// Chamado pelo app (helper_name = amazon_iap): compra feita agora ou "Restaurar compra"
function amazon_claim($mysqli, $data){
    amazon_migrate($mysqli);
    if (empty($data['device_id'])) return array('ok' => false, 'msg' => 'Dispositivo não identificado.', 'device' => null);
    // o app avisou um erro devolvido pela Amazon: só registra
    if (!empty($data['amazon_error'])) {
        amazon_log($mysqli, 'App: '.mb_substr((string)$data['amazon_error'], 0, 120), array('device' => devices_clean($data['device_id'], 60)));
        return array('ok' => false, 'msg' => '', 'device' => null);
    }
    if (!amazon_enabled($mysqli)) return array('ok' => false, 'msg' => 'A compra pela Amazon ainda não está disponível. Tente mais tarde.', 'device' => null);
    $uid = trim((string)($data['amazon_user'] ?? ''));
    $rid = trim((string)($data['amazon_receipt'] ?? ''));
    if ($uid === '' || $rid === '' || strlen($uid) > 191 || strlen($rid) > 191) return array('ok' => false, 'msg' => 'Compra inválida.', 'device' => null);
    $sandbox = !empty($data['amazon_sandbox']);
    // compras do App Tester (teste local) só valem quando o admin liga o teste em Gateways de Pagamento
    // confere no ambiente informado pelo app; se não achar, no outro (a revisão da Amazon pode usar qualquer um dos dois)
    $v = amazon_rvs($mysqli, $uid, $rid, $sandbox);
    if (!$v['ok']) {
        $v2 = amazon_rvs($mysqli, $uid, $rid, !$sandbox);
        if ($v2['ok']) { $v = $v2; $sandbox = !$sandbox; }
    }
    if (!$v['ok']) {
        amazon_log($mysqli, 'Amazon recusou o recibo', array('http' => $v['code'], 'sandbox' => $sandbox ? 1 : 0));
        $why = $v['code'] == 496 ? ' (Shared Key inválida)' : '';
        return array('ok' => false, 'msg' => ($v['code'] == 400 ? 'Compra inválida.' : 'Não foi possível confirmar a compra com a Amazon. Tente de novo.').$why, 'device' => null);
    }
    devices_register($mysqli, $data);
    $device = devices_find($mysqli, devices_clean($data['device_id'], 191), devices_clean(isset($data['application_id']) ? $data['application_id'] : '', 191));
    if (!$device) return array('ok' => false, 'msg' => 'Erro ao registrar o dispositivo.', 'device' => null);
    $r = amazon_apply($mysqli, $uid, $rid, $v['json'], $sandbox, $device);
    amazon_log($mysqli, $r['ok'] ? 'Ativado' : $r['msg'], array('device' => $device['device_id'], 'sandbox' => $sandbox ? 1 : 0, 'sku' => (string)($v['json']['productId'] ?? '')));
    return $r;
}

// Teste da Shared Key: a Amazon responde 496 quando a chave está errada; com a chave certa, um recibo inventado dá 400/497
function amazon_test_key($mysqli){
    $v = amazon_rvs($mysqli, 'teste-iptvbestpro', 'teste-iptvbestpro', false);
    if ($v['code'] == 496) return array(false, 'A Amazon recusou a Shared Key (HTTP 496). Copie de novo em My Settings → Identity → Shared Key.');
    if (in_array($v['code'], array(400, 497, 410))) return array(true, 'Shared Key aceita pela Amazon (HTTP '.$v['code'].' para um recibo de teste, como esperado).');
    return array(false, 'Resposta inesperada da Amazon (HTTP '.$v['code'].'). Tente de novo em alguns minutos.');
}

// Na abertura do app: assinatura da Amazon perto de vencer (ou vencida há pouco) é conferida de novo (renovação / cancelamento)
function amazon_refresh_device($mysqli, $device){
    if (!$device || $device['owner'] != 'customer' || $device['activated_by'] != 'cliente: Amazon' || !amazon_enabled($mysqli)) return $device;
    $now = time();
    $exp = (int)$device['expires_at'];
    if ($exp > $now + 2 * 86400 || $exp < $now - 60 * 86400) return $device;
    amazon_migrate($mysqli);
    $sub = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_amazon_subs WHERE device_pk = ".(int)$device['id']." ORDER BY updated_at DESC LIMIT 1"));
    if (!$sub || (int)$sub['checked_at'] > $now - 6 * 3600) return $device;
    mysqli_query($mysqli, "UPDATE tbl_amazon_subs SET checked_at = $now WHERE id = ".(int)$sub['id']);
    $v = amazon_rvs($mysqli, $sub['user_id'], $sub['receipt_id'], (int)$sub['sandbox'] == 1);
    if (!$v['ok']) return $device;
    $r = amazon_apply($mysqli, $sub['user_id'], $sub['receipt_id'], $v['json'], (int)$sub['sandbox'] == 1, $device);
    return $r['device'] ? $r['device'] : $device;
}
