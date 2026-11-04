<?php
/**
 * Histórico de cada pedido (assinatura de cliente ou créditos de revenda): o que o cliente fez e o que o gateway respondeu.
 * Pedido criado, clicou em Pix/cartão, Pix gerado, erro no gateway, voltou do cartão, pago, cancelado...
 * Gravar o histórico NUNCA pode atrapalhar um pagamento: qualquer erro aqui é ignorado.
 */

function pay_log_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_payment_events` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_type` VARCHAR(10) NOT NULL,             -- sub | credit
        `order_id` INT(11) NOT NULL,
        `event` VARCHAR(40) NOT NULL,
        `method` VARCHAR(12) NOT NULL DEFAULT '',      -- pix | card | manual | ''
        `level` VARCHAR(8) NOT NULL DEFAULT 'info',    -- ok | error | info
        `detail` VARCHAR(600) NOT NULL DEFAULT '',
        `ref` VARCHAR(191) NOT NULL DEFAULT '',
        `ip` VARCHAR(45) NOT NULL DEFAULT '',
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_order` (`order_type`, `order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Grava um passo do pedido. $once = true: não repete se o último registro igual (mesmo evento, referência e texto) já está lá
 * (o gateway é consultado várias vezes e o mesmo aviso não pode virar dezenas de linhas).
 */
function pay_log($mysqli, $order_type, $order_id, $event, $method = '', $level = 'info', $detail = '', $ref = '', $once = false){
    try {
        $order_type = $order_type == 'credit' ? 'credit' : 'sub';
        $order_id = (int)$order_id;
        if ($order_id <= 0) return;
        pay_log_migrate($mysqli);
        $event = mb_substr((string)$event, 0, 40);
        $detail = mb_substr(trim((string)$detail), 0, 600);
        $ref = mb_substr((string)$ref, 0, 191);
        $method = mb_substr((string)$method, 0, 12);
        $level = in_array($level, array('ok', 'error', 'info'), true) ? $level : 'info';
        if ($once) {
            $stmt = mysqli_prepare($mysqli, "SELECT detail FROM tbl_payment_events WHERE order_type = ? AND order_id = ? AND event = ? AND ref = ? ORDER BY id DESC LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'siss', $order_type, $order_id, $event, $ref);
            mysqli_stmt_execute($stmt);
            $last = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($last && $last['detail'] === $detail) return;
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? mb_substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $ip = mb_substr((string)$_SERVER['HTTP_CF_CONNECTING_IP'], 0, 45);
        $now = time();
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_payment_events (order_type, order_id, event, method, level, detail, ref, ip, created_at) VALUES (?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sissssssi', $order_type, $order_id, $event, $method, $level, $detail, $ref, $ip, $now);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (\Throwable $e) {
        error_log('[pay_log] '.$e->getMessage());
    }
}

function pay_event_labels(){
    return array(
        'order_created'       => 'Pedido criado',
        'click_pix'           => 'Cliente clicou em Pagar com Pix',
        'click_card'          => 'Cliente clicou em Pagar com cartão',
        'checkout_refused'    => 'Pagamento recusado antes de chamar o gateway',
        'pix_unavailable'     => 'Pix indisponível',
        'pix_generated'       => 'Pix gerado (QR Code criado)',
        'pix_reused'          => 'Pix já existente reaproveitado',
        'pix_error'           => 'Erro ao gerar o Pix',
        'pix_page_viewed'     => 'Cliente abriu a tela do QR Code',
        'card_unavailable'    => 'Cartão indisponível',
        'card_session_created'=> 'Tela do cartão (Stripe) criada',
        'card_error'          => 'Erro ao abrir o cartão (Stripe)',
        'card_return_ok'      => 'Cliente voltou do Stripe (disse que pagou)',
        'card_return_cancel'  => 'Cliente voltou do Stripe cancelando',
        'gateway_status'      => 'Resposta do gateway',
        'amount_mismatch'     => 'Valor pago diferente do pedido',
        'paid'                => 'Pagamento confirmado',
        'manual_paid'         => 'Confirmado manualmente no painel',
        'cancelled'           => 'Pedido cancelado',
    );
}

// Eventos do pedido (do mais antigo ao mais novo). $ids = lista de ids de pedidos. Retorna array id => lista de eventos.
function pay_log_load($mysqli, $order_type, $ids){
    $out = array();
    $clean = array();
    foreach ((array)$ids as $i) { $i = (int)$i; if ($i > 0) $clean[$i] = $i; }
    if (!$clean) return $out;
    try {
        pay_log_migrate($mysqli);
        $t = $order_type == 'credit' ? 'credit' : 'sub';
        $res = mysqli_query($mysqli, "SELECT * FROM tbl_payment_events WHERE order_type = '$t' AND order_id IN (".implode(',', $clean).") ORDER BY id ASC");
        while ($res && $r = mysqli_fetch_assoc($res)) $out[(int)$r['order_id']][] = $r;
    } catch (\Throwable $e) {
        error_log('[pay_log_load] '.$e->getMessage());
    }
    return $out;
}

// Pagamentos do gateway (tbl_payments) dos pedidos: usados no histórico de pedidos antigos, de antes do registro de passos existir
function pay_log_payments($mysqli, $order_type, $ids){
    $out = array();
    $clean = array();
    foreach ((array)$ids as $i) { $i = (int)$i; if ($i > 0) $clean[$i] = $i; }
    if (!$clean) return $out;
    try {
        $t = $order_type == 'credit' ? 'credit' : 'sub';
        $res = mysqli_query($mysqli, "SELECT id, provider, order_id, status, provider_id, qr_code, checkout_url, expires_at, created_at, paid_at FROM tbl_payments
                                      WHERE order_type = '$t' AND order_id IN (".implode(',', $clean).") ORDER BY id ASC");
        while ($res && $r = mysqli_fetch_assoc($res)) $out[(int)$r['order_id']][] = $r;
    } catch (\Throwable $e) {
        error_log('[pay_log_payments] '.$e->getMessage());
    }
    return $out;
}

/**
 * Lista final de passos para mostrar. Pedido sem passos gravados (antigo) usa o que existe em tbl_payments.
 * Cada passo: array(t, label, level, detail, method, ip)
 */
function pay_log_steps($events, $payments, $created_at, $status){
    $labels = pay_event_labels();
    $steps = array();
    if ($events) {
        foreach ($events as $e) {
            $steps[] = array('t' => (int)$e['created_at'], 'label' => isset($labels[$e['event']]) ? $labels[$e['event']] : $e['event'], 'level' => $e['level'],
                             'detail' => $e['detail'], 'method' => $e['method'], 'ip' => $e['ip'], 'event' => $e['event']);
        }
        return $steps;
    }
    // pedido antigo: reconstrói o que dá com os registros do gateway
    $steps[] = array('t' => (int)$created_at, 'label' => 'Pedido criado', 'level' => 'ok', 'detail' => '', 'method' => '', 'ip' => '', 'event' => 'order_created');
    foreach ((array)$payments as $p) {
        if ($p['provider'] == 'mercadopago') {
            $steps[] = array('t' => (int)$p['created_at'], 'label' => 'Pix gerado (QR Code criado)', 'level' => 'ok', 'detail' => 'Pagamento Mercado Pago '.$p['provider_id'], 'method' => 'pix', 'ip' => '', 'event' => 'pix_generated');
        } else {
            $steps[] = array('t' => (int)$p['created_at'], 'label' => $p['provider_id'] != '' ? 'Tela do cartão (Stripe) criada' : 'Erro ao abrir o cartão (Stripe)',
                             'level' => $p['provider_id'] != '' ? 'ok' : 'error', 'detail' => '', 'method' => 'card', 'ip' => '', 'event' => $p['provider_id'] != '' ? 'card_session_created' : 'card_error');
        }
        if ($p['status'] == 'paid') $steps[] = array('t' => (int)$p['paid_at'], 'label' => 'Pagamento confirmado', 'level' => 'ok', 'detail' => '', 'method' => '', 'ip' => '', 'event' => 'paid');
        elseif ($p['status'] == 'failed') $steps[] = array('t' => (int)$p['created_at'], 'label' => 'Pagamento recusado ou com erro', 'level' => 'error', 'detail' => '', 'method' => '', 'ip' => '', 'event' => 'failed');
        elseif ($p['status'] == 'expired' || ($p['status'] == 'pending' && (int)$p['expires_at'] > 0 && (int)$p['expires_at'] < time())) $steps[] = array('t' => (int)$p['expires_at'], 'label' => 'Expirou sem pagar', 'level' => 'info', 'detail' => '', 'method' => '', 'ip' => '', 'event' => 'expired');
    }
    if ($status == 'cancelled') $steps[] = array('t' => 0, 'label' => 'Pedido cancelado', 'level' => 'info', 'detail' => '', 'method' => '', 'ip' => '', 'event' => 'cancelled');
    return $steps;
}

/**
 * Resumo de uma linha: onde o cliente parou. Retorna array(texto, classe de cor).
 * Cores: ok (verde), wait (amarelo), bad (vermelho), none (cinza).
 */
function pay_log_summary($steps, $status){
    if ($status == 'paid') return array('Pago', 'ok');
    if ($status == 'cancelled') return array('Cancelado', 'none');
    $has = function ($ev) use ($steps) { foreach ($steps as $s) if ($s['event'] == $ev) return true; return false; };
    $last_error = null;
    foreach ($steps as $s) if ($s['level'] == 'error') $last_error = $s;
    $last = $steps ? $steps[count($steps) - 1] : null;
    // o último passo importante decide
    if ($last && $last['level'] == 'error') {
        $txt = $last['label'].($last['detail'] != '' ? ': '.mb_substr($last['detail'], 0, 90) : '');
        return array($txt, 'bad');
    }
    if ($has('card_return_cancel') && (!$last || in_array($last['event'], array('card_return_cancel', 'gateway_status')))) return array('Cancelou na tela do cartão', 'wait');
    if ($has('card_session_created')) return array($has('card_return_ok') ? 'Voltou do cartão, aguardando confirmação' : 'Abriu o cartão (Stripe) e não concluiu', 'wait');
    if ($has('pix_generated') || $has('pix_reused')) return array($has('pix_page_viewed') ? 'Pix gerado e QR Code aberto, não pagou' : 'Pix gerado, não pagou', 'wait');
    if ($has('click_pix') || $has('click_card')) return array('Clicou em pagar, mas nada foi gerado', 'bad');
    return array('Só criou o pedido (não escolheu Pix nem cartão)', 'none');
}

// Dados prontos para o navegador (JSON): passos de um pedido
function pay_log_for_js($steps){
    $out = array();
    foreach ($steps as $s) {
        $out[] = array('t' => $s['t'] > 0 ? date('d/m/Y H:i:s', $s['t']) : '', 'label' => $s['label'], 'level' => $s['level'],
                       'detail' => $s['detail'], 'method' => $s['method'], 'ip' => $s['ip']);
    }
    return $out;
}
