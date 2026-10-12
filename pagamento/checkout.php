<?php
/**
 * Inicia o pagamento de um pedido. Recebe só: quem paga (as), tipo e id do pedido e o método.
 * O VALOR é lido do banco (pay_load_order). Nada de valor vem do navegador.
 */
require_once(__DIR__."/../includes/db_helper.php");
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/payment_helper.php");
payments_migrate($mysqli);

function co_back($as){
    global $mysqli;
    if ($as != 'reseller') return '../cliente/dashboard.php';
    require_once(__DIR__.'/../includes/server_helper.php');
    return '../'.server_portal_dir($mysqli, isset($_SESSION['rv_id']) ? (int)$_SESSION['rv_id'] : 0).'/credits.php';
}
function co_fail($as, $msg){
    $key = $as == 'reseller' ? 'rv_flash' : 'cl_flash';
    $_SESSION[$key] = array('type' => 'error', 'msg' => $msg);
    header('Location: '.co_back($as));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') { header('Location: ../cliente/'); exit; }

$as = (isset($_POST['as']) && $_POST['as'] == 'reseller') ? 'reseller' : 'customer';
$type = (isset($_POST['type']) && $_POST['type'] == 'credit') ? 'credit' : 'sub';
$order_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$method = (isset($_POST['method']) && $_POST['method'] == 'card') ? 'card' : 'pix';

// Sessão e CSRF do portal correspondente
$owner_id = $as == 'reseller' ? (isset($_SESSION['rv_id']) ? (int)$_SESSION['rv_id'] : 0) : (isset($_SESSION['cl_id']) ? (int)$_SESSION['cl_id'] : 0);
$csrf_key = $as == 'reseller' ? 'rv_csrf' : 'cl_csrf';
if ($owner_id <= 0) { header('Location: '.($as == 'reseller' ? '../revenda/' : '../cliente/')); exit; }
if (!isset($_POST['csrf']) || empty($_SESSION[$csrf_key]) || !hash_equals($_SESSION[$csrf_key], (string)$_POST['csrf'])) co_fail($as, 'Sessão expirada. Tente novamente.');

// Tipo de pedido precisa combinar com quem paga
if (($as == 'reseller' && $type != 'credit') || ($as == 'customer' && $type != 'sub')) co_fail($as, 'Pedido inválido.');

$order = pay_load_order($mysqli, $type, $order_id, $as, $owner_id);
if (!$order) co_fail($as, 'Pedido não encontrado.');
if ($order['status'] != 'pending') co_fail($as, 'Este pedido não está aguardando pagamento.');
if ($order['amount'] <= 0) co_fail($as, 'Valor do pedido inválido.');

if ($method == 'pix') {
    $r = pay_mp_create_pix($mysqli, $order, $as, $owner_id);
    if (!$r['ok']) co_fail($as, $r['msg']);
    header('Location: pagar.php?p='.(int)$r['payment_id']);
    exit;
}

$r = pay_stripe_create_checkout($mysqli, $order, $as, $owner_id, pay_base_url().'/pagamento/pagar.php');
if (!$r['ok']) co_fail($as, $r['msg']);
header('Location: '.$r['url']);
exit;
