<?php
// Conta de demonstração: /live|movie|series/demo/bestpro/ID.m3u8 -> endereço do vídeo livre de teste
require_once(__DIR__."/includes/demo_catalog.php");
$type = isset($_GET['t']) ? (string)$_GET['t'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$src = in_array($type, array('live', 'movie', 'series'), true) ? demo_source('', $type, $id) : '';
header('Cache-Control: no-store');
if ($src === '') { http_response_code(404); exit; }
header('Location: '.$src, true, 302);
exit;
