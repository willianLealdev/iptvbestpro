<?php
/**
 * Imagens ↔ banco: restaura as imagens que sumiram da pasta (ex.: depois de um deploy) e guarda no banco
 * as que ainda não têm cópia. Executado pelo docker-entrypoint.sh ao iniciar e a cada 5 minutos.
 * Não pode ser chamado pelo navegador.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

$lock = fopen(sys_get_temp_dir().'/media_sync.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;

chdir(__DIR__.'/..');
require_once(__DIR__.'/../includes/db_helper.php');
while (ob_get_level() > 0) ob_end_flush();
require_once(__DIR__.'/../includes/media_helper.php');

$start = microtime(true);
try {
    $r = media_sync($mysqli);
    if ($r['saved'] || $r['restored']) {
        printf("%s [media-sync] restauradas=%d guardadas=%d (%.1fs)\n", date('Y-m-d H:i:s'), $r['restored'], $r['saved'], microtime(true) - $start);
    }
} catch (\Throwable $e) {
    echo date('Y-m-d H:i:s').' [media-sync] erro: '.$e->getMessage()."\n";
}

flock($lock, LOCK_UN);
fclose($lock);
