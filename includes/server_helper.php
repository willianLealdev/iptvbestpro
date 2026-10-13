<?php
/**
 * Servidores parceiros (provedores de IPTV).
 *
 * Um servidor é uma conta em tbl_resellers com kind = 'server' (mesmo login, créditos, pedidos, tema, pôsteres e DNS da revenda),
 * com estas diferenças:
 *  - Código de 8 números, o mesmo formato da assinatura de cliente (assim funciona em todos os apps já publicados,
 *    que aceitam 6 letras ou 8 números). O painel diferencia: se o número é de um servidor, ativa como servidor.
 *  - O código ativa sozinho: ao ser digitado no app, desconta 1 crédito (1 ano) na hora. Sem crédito, avisa e não ativa.
 *    Quando vence, o cliente digita o código de novo (não há renovação automática).
 *  - Os aparelhos usam só as DNS do servidor, e a lista é enviada pela página pública /lista
 *    (ID do aparelho + usuário + senha), validada só nas DNS do servidor. Nunca pelo app.
 *  - Créditos são comprados em pacotes criados pelo admin.
 */
require_once(__DIR__.'/reseller_helper.php');

define('SERVER_CODE_LENGTH', 8);

function server_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    resellers_migrate($mysqli);
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_resellers` LIKE 'kind'");
    if ($col && mysqli_num_rows($col) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_resellers` ADD `kind` VARCHAR(10) NOT NULL DEFAULT 'reseller', ADD KEY `idx_kind` (`kind`)");
    }
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_server_packages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL DEFAULT '',
        `credits` INT(11) NOT NULL DEFAULT 0,
        `price_brl` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `price_usd` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `status` TINYINT(1) NOT NULL DEFAULT 1,
        `sort` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_credit_orders` LIKE 'package_id'");
    if ($col && mysqli_num_rows($col) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_credit_orders` ADD `package_id` INT(11) NOT NULL DEFAULT 0, ADD `package_name` VARCHAR(120) NOT NULL DEFAULT ''");
    }
}

function server_is_numeric_code($code){
    return (bool)preg_match('/^\d{'.SERVER_CODE_LENGTH.'}$/', preg_replace('/[\s\-\.]/', '', (string)$code));
}

// Gera um código único de 8 números (não repete entre servidores nem com códigos de assinatura)
function server_code_generate($mysqli){
    server_migrate($mysqli);
    for ($i = 0; $i < 60; $i++) {
        $code = (string)random_int(10000000, 99999999);
        $res = mysqli_query($mysqli, "SELECT id FROM tbl_resellers WHERE code = '$code'");
        if (!$res || mysqli_num_rows($res) > 0) continue;
        $sub = mysqli_query($mysqli, "SHOW TABLES LIKE 'tbl_subscriptions'");
        if ($sub && mysqli_num_rows($sub) > 0) {
            $res = mysqli_query($mysqli, "SELECT id FROM tbl_subscriptions WHERE code = '$code'");
            if ($res && mysqli_num_rows($res) > 0) continue;
        }
        return $code;
    }
    return false;
}

// Código digitado no app pertence a um servidor? (8 números cadastrados como código de servidor)
function server_code_matches($mysqli, $code){
    return server_is_numeric_code($code) && server_by_code($mysqli, $code) !== null;
}

function server_by_code($mysqli, $code){
    server_migrate($mysqli);
    $code = preg_replace('/\D/', '', (string)$code);
    if (strlen($code) != SERVER_CODE_LENGTH) return null;
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_resellers WHERE code = ? AND kind = 'server' AND status = 1");
    mysqli_stmt_bind_param($stmt, 's', $code);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// Servidor dono do aparelho (ou null quando o aparelho não é de um servidor)
function server_of_device($mysqli, $device){
    if (!$device || $device['owner'] != 'reseller' || (int)$device['reseller_id'] <= 0) return null;
    server_migrate($mysqli);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_resellers WHERE id = ".(int)$device['reseller_id']." AND kind = 'server'"));
    return $row ?: null;
}

// Endereço da página de envio da lista, já com o ID do aparelho
function server_upload_url($device_id){
    require_once(__DIR__.'/brand_helper.php');
    return rtrim(brand_mail_base_url(), '/').'/lista?d='.rawurlencode((string)$device_id);
}

/**
 * O cliente digitou o código do servidor no app: ativa na hora (1 crédito = 1 ano).
 * Aparelho já ativo com o mesmo servidor: não cobra de novo.
 */
function server_link_device($mysqli, $data){
    server_migrate($mysqli);
    $ip = devices_client_ip();
    if (devices_link_rate_limited($mysqli, $ip)) {
        return array('ok' => false, 'msg' => 'Muitas tentativas. Tente novamente em 1 hora.', 'device' => null);
    }
    if (empty($data['device_id'])) return array('ok' => false, 'msg' => 'Dispositivo não identificado.', 'device' => null);
    $server = server_by_code($mysqli, isset($data['code']) ? $data['code'] : '');
    if (!$server) {
        devices_link_fail($mysqli, $ip);
        return array('ok' => false, 'msg' => 'Código inválido.', 'device' => null);
    }
    devices_register($mysqli, $data);
    $device_id = devices_clean($data['device_id'], 191);
    $application_id = devices_clean(isset($data['application_id']) ? $data['application_id'] : '', 191);
    $device = devices_find($mysqli, $device_id, $application_id);
    if (!$device) return array('ok' => false, 'msg' => 'Erro ao registrar o dispositivo.', 'device' => null);

    $sid = (int)$server['id'];
    $same = ($device['owner'] == 'reseller' && (int)$device['reseller_id'] == $sid);
    $active = devices_activation_status($device) == 'active';
    if (($active && !$same && $device['owner'] != '') || devices_active_elsewhere($mysqli, $device_id, 'reseller', $sid, 0, (int)$device['id'])) {
        return array('ok' => false, 'msg' => 'Este aparelho já está ativo com outra revenda. Para usar outro código, resete a licença no app (Configurações → Resetar licença).', 'device' => $device);
    }
    if ($active && $same) return array('ok' => true, 'msg' => 'Dispositivo ativo.', 'device' => $device);

    if ((int)$server['credits'] < 1) {
        return array('ok' => false, 'msg' => 'Não há créditos disponíveis. Verifique com o seu revendedor ou servidor, ou adquira uma nova licença.', 'device' => $device);
    }
    if (!$same) {
        // Troca de dono: chega zerado (sem listas, ativação, nome ou créditos do dono anterior)
        devices_take_over($mysqli, (int)$device['id'], 'reseller', $sid);
    }
    $r = devices_activate($mysqli, (int)$device['id'], '1y', $sid, '', 0);
    $device = devices_find($mysqli, $device_id, $application_id);
    if (!$r['ok']) {
        $msg = strpos($r['msg'], 'Créditos insuficientes') === 0 ? 'Não há créditos disponíveis. Verifique com o seu revendedor ou servidor, ou adquira uma nova licença.' : $r['msg'];
        return array('ok' => false, 'msg' => $msg, 'device' => $device);
    }
    return array('ok' => true, 'msg' => 'Dispositivo ativado. Agora envie a sua lista.', 'device' => $device);
}

/* ---------- Pacotes ---------- */

function server_packages($mysqli, $only_active = true){
    server_migrate($mysqli);
    $out = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_server_packages".($only_active ? " WHERE status = 1" : "")." ORDER BY sort ASC, credits ASC, id ASC");
    while ($res && ($r = mysqli_fetch_assoc($res))) $out[] = $r;
    return $out;
}

function server_package_price($pkg, $currency){
    return (float)($currency == 'USD' ? $pkg['price_usd'] : $pkg['price_brl']);
}

// Pedido de compra de um pacote (pago por Pix/cartão ou confirmado pelo admin, como os créditos da revenda)
function server_order_create($mysqli, $server_id, $package_id){
    server_migrate($mysqli);
    $server_id = (int)$server_id; $package_id = (int)$package_id;
    $pkg = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_server_packages WHERE id = $package_id AND status = 1"));
    $srv = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_resellers WHERE id = $server_id AND kind = 'server'"));
    if (!$pkg || !$srv) return false;
    $currency = reseller_currency($srv);
    $total = server_package_price($pkg, $currency);
    if ($total <= 0 || (int)$pkg['credits'] <= 0) return false;
    $credits = (int)$pkg['credits'];
    $unit = round($total / $credits, 2);
    $now = time();
    $name = mb_substr($pkg['name'], 0, 120);
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_credit_orders (reseller_id, credits, unit_price, total, currency, status, method, created_at, package_id, package_name) VALUES (?,?,?,?,?,'pending','manual',?,?,?)");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'iiddsiis', $server_id, $credits, $unit, $total, $currency, $now, $package_id, $name);
    $ok = mysqli_stmt_execute($stmt);
    $id = $ok ? mysqli_insert_id($mysqli) : 0;
    mysqli_stmt_close($stmt);
    return $id ?: false;
}

/* ---------- Página pública de envio da lista ---------- */

/**
 * Valida o aparelho (tem de ser de um servidor e estar ativo) e o usuário nas DNS desse servidor; adiciona a lista.
 * Retorna array('ok' => bool, 'msg' => string, 'step' => 'device'|'user'|'done')
 */
function server_upload_submit($mysqli, $device_id, $username, $password){
    server_migrate($mysqli);
    require_once(__DIR__.'/playlist_helper.php');
    playlists_migrate($mysqli);
    $ip = devices_client_ip();
    if (devices_link_rate_limited($mysqli, $ip)) return array('ok' => false, 'step' => 'device', 'msg' => 'Muitas tentativas. Tente novamente em 1 hora.');
    $device_id = devices_clean(trim((string)$device_id), 191);
    $username = trim((string)$username); $password = trim((string)$password);
    if ($device_id == '') return array('ok' => false, 'step' => 'device', 'msg' => 'Informe o ID do aparelho (aparece na tela do app).');

    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_devices WHERE device_id = ? ORDER BY last_seen DESC, id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $device_id);
    mysqli_stmt_execute($stmt);
    $device = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $server = $device ? server_of_device($mysqli, $device) : null;
    if (!$device || !$server) {
        devices_link_fail($mysqli, $ip);
        return array('ok' => false, 'step' => 'device', 'msg' => 'Aparelho não encontrado. Confira o ID na tela do app, e se ele foi ativado com o código do seu servidor.');
    }
    if (devices_activation_status($device) != 'active') {
        return array('ok' => false, 'step' => 'device', 'msg' => 'Este aparelho não está ativo. Digite o código do servidor no app para ativar.');
    }
    if ($username == '' || $password == '') return array('ok' => false, 'step' => 'user', 'msg' => 'Informe o usuário e a senha da sua lista.', 'server' => $server['name']);
    if (!dns_list_active($mysqli, (int)$server['id'])) {
        return array('ok' => false, 'step' => 'user', 'msg' => 'O servidor ainda não cadastrou os endereços (DNS). Fale com o seu servidor.', 'server' => $server['name']);
    }
    $r = playlist_handle_submit($mysqli, $device['device_id'], array('mode' => 'account', 'playlist_name' => mb_substr($server['name'], 0, 60), 'username' => $username, 'password' => $password), 'lista: '.$ip);
    if (!$r['ok']) devices_link_fail($mysqli, $ip);
    return array('ok' => $r['ok'], 'step' => $r['ok'] ? 'done' : 'user', 'msg' => $r['ok'] ? 'Lista adicionada com sucesso! Ela já vai aparecer no aplicativo.' : 'Usuário ou senha não encontrados nos servidores de '.$server['name'].'. Confira os dados.', 'server' => $server['name']);
}

// Pasta do portal da conta (revenda ou servidor parceiro), para voltar do pagamento para o lugar certo
function server_portal_dir($mysqli, $reseller_id){
    server_migrate($mysqli);
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kind FROM tbl_resellers WHERE id = ".(int)$reseller_id));
    return ($r && $r['kind'] == 'server') ? 'servidor' : 'revenda';
}
