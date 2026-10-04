<?php
/**
 * Entrega uma imagem de images/ ou uploads/ que não está na pasta, usando a cópia do banco (tbl_media).
 * O Apache só chama este arquivo quando o arquivo pedido não existe (ver Dockerfile: media-fallback.conf).
 * Também recoloca o arquivo na pasta para as próximas vezes.
 */
require_once(__DIR__."/includes/db_helper.php");
require_once(__DIR__."/includes/media_helper.php");
while (ob_get_level() > 0) ob_end_clean();

$rel = media_clean_path(isset($_GET['p']) ? $_GET['p'] : '');
if ($rel === '') { http_response_code(404); exit; }

media_migrate($mysqli);
$stmt = mysqli_prepare($mysqli, "SELECT mime, data, hash FROM tbl_media WHERE path = ?");
mysqli_stmt_bind_param($stmt, 's', $rel);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) { http_response_code(404); exit; }

media_restore($mysqli, $rel);

header('Content-Type: '.$row['mime']);
header('Content-Length: '.strlen($row['data']));
header('Cache-Control: public, max-age=86400');
header('ETag: "'.$row['hash'].'"');
header('X-Content-Type-Options: nosniff');
if ($row['mime'] == 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
echo $row['data'];
exit;
