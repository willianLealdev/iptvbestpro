<?php
/**
 * Monitor de DNS das playlists (usuário/senha).
 * Executado a cada 5 minutos pelo docker-entrypoint.sh. Não pode ser chamado pelo navegador.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

// Evita dois ciclos ao mesmo tempo (se um ciclo demorar mais que o intervalo)
$lock = fopen(sys_get_temp_dir().'/dns_monitor.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('Y-m-d H:i:s')." [dns-monitor] ciclo anterior ainda em execução, pulando\n";
    exit;
}

chdir(__DIR__.'/..');
require_once(__DIR__.'/../includes/db_helper.php');
while (ob_get_level() > 0) ob_end_flush();
require_once(__DIR__.'/../includes/lb_helper.php');
require_once(__DIR__.'/../includes/reseller_helper.php');
require_once(__DIR__.'/../includes/playlist_helper.php');

$start = microtime(true);
$stats = playlist_monitor_run($mysqli);
printf("%s [dns-monitor] servidores online=%d offline=%d | playlists verificadas=%d online=%d trocadas=%d sem_servidor=%d (%.1fs)\n",
    date('Y-m-d H:i:s'), $stats['dns_online'], $stats['dns_offline'], $stats['checked'], $stats['online'], $stats['switched'], $stats['offline'], microtime(true) - $start);

flock($lock, LOCK_UN);
fclose($lock);
