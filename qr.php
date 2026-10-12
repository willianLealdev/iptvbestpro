<?php
/**
 * Imagem PNG do QR code da página de envio da lista (/lista?d=ID) de um aparelho de servidor parceiro.
 * Usada pelos apps que não geram QR code sozinhos (Roku). Só gera o endereço do próprio painel.
 */
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/includes/db_helper.php');
require_once(__DIR__.'/includes/server_helper.php');

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;

$d = isset($_GET['d']) ? preg_replace('/[^A-Za-z0-9:\-_.]/', '', (string)$_GET['d']) : '';
if ($d === '' || strlen($d) > 191) { http_response_code(400); exit; }

$o = new QROptions();
$o->outputType = QROutputInterface::GDIMAGE_PNG;
$o->outputBase64 = false;
$o->scale = 10;
$o->quietzoneSize = 2;
$png = (new QRCode($o))->render(server_upload_url($d));

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
echo $png;
