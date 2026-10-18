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
printf("%s [dns-monitor] servidores online=%d offline=%d excluidos=%d | playlists verificadas=%d online=%d trocadas=%d sem_servidor=%d (%.1fs)\n",
    date('Y-m-d H:i:s'), $stats['dns_online'], $stats['dns_offline'], $stats['dns_deleted'], $stats['checked'], $stats['online'], $stats['switched'], $stats['offline'], microtime(true) - $start);

// Limpeza automática dos aparelhos (uma vez por hora): não ativados parados há 15 dias e testes grátis vencidos há 30 dias
try {
    if (time() - (int)panel_config_get($mysqli, 'devices_cleanup_at', '0') >= 3600) {
        panel_config_set($mysqli, 'devices_cleanup_at', (string)time());
        $cl = devices_auto_cleanup($mysqli);
        if ($cl['waiting'] || $cl['trial']) echo date('Y-m-d H:i:s')." [devices-cleanup] nao_ativados=".$cl['waiting']." testes_vencidos=".$cl['trial']."
";
    }
} catch (\Throwable $e) {
    echo date('Y-m-d H:i:s').' [devices-cleanup] erro: '.$e->getMessage()."
";
}

// Alertas por e-mail: DNS que caiu ou voltou (admin e revendedor dono)
try {
    require_once(__DIR__.'/../includes/dns_alert_helper.php');
    $sent = dns_alerts_run($mysqli);
    if ($sent) echo date('Y-m-d H:i:s')." [dns-alert] e-mails enviados=$sent\n";
} catch (\Throwable $e) {
    echo date('Y-m-d H:i:s').' [dns-alert] erro: '.$e->getMessage()."\n";
}

flock($lock, LOCK_UN);
fclose($lock);
