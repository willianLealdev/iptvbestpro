<?php
/**
 * Histórico dos servidores DNS: quedas, tempo no ar e quem usa cada servidor.
 *  - tbl_dns_events: cada queda (início e fim). Uma queda aberta tem up_at = 0.
 *  - tbl_dns_daily:  por dia, quantas verificações e quantas responderam (tempo no ar em %) e a latência.
 *  - tbl_dns_users:  revendas que têm clientes no servidor (visto nos últimos dias). Guardado ANTES da troca
 *                    automática, para o aviso de queda chegar a todas as revendas que estavam usando o servidor.
 * O monitor verifica a cada 5 minutos.
 */

define('DNS_USERS_KEEP_DAYS', 7);

function dns_stats_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_dns_events` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `dns_id` INT(11) NOT NULL,
        `down_at` INT(11) NOT NULL,
        `up_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_dns_down` (`dns_id`, `down_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_dns_daily` (
        `dns_id` INT(11) NOT NULL,
        `day` DATE NOT NULL,
        `checks` INT(11) NOT NULL DEFAULT 0,
        `ok` INT(11) NOT NULL DEFAULT 0,
        `latency_sum` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`dns_id`, `day`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_dns_users` (
        `dns_id` INT(11) NOT NULL,
        `reseller_id` INT(11) NOT NULL,
        `playlists` INT(11) NOT NULL DEFAULT 0,
        `last_seen` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`dns_id`, `reseller_id`),
        KEY `idx_users_reseller` (`reseller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Chamado depois de cada verificação dos servidores.
 * $checks: id => array('online' => bool, 'ms' => int)
 */
function dns_stats_record($mysqli, $checks){
    if (!$checks) return;
    dns_stats_migrate($mysqli);
    $now = time();
    $day = date('Y-m-d', $now);
    foreach ($checks as $id => $c) {
        $id = (int)$id;
        $ok = !empty($c['online']) ? 1 : 0;
        $lat = $ok ? max(0, (int)$c['ms']) : 0;
        mysqli_query($mysqli, "INSERT INTO tbl_dns_daily (dns_id, day, checks, ok, latency_sum) VALUES ($id, '$day', 1, $ok, $lat)
                               ON DUPLICATE KEY UPDATE checks = checks + 1, ok = ok + $ok, latency_sum = latency_sum + $lat");
        $open = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_dns_events WHERE dns_id = $id AND up_at = 0 ORDER BY id DESC LIMIT 1"));
        if ($ok && $open) {
            mysqli_query($mysqli, "UPDATE tbl_dns_events SET up_at = $now WHERE id = ".(int)$open['id']);
        } else if (!$ok && !$open) {
            mysqli_query($mysqli, "INSERT INTO tbl_dns_events (dns_id, down_at) VALUES ($id, $now)");
        }
    }
    // limpeza de vez em quando (mais de 1 ano)
    if (random_int(1, 200) == 1) {
        mysqli_query($mysqli, "DELETE FROM tbl_dns_events WHERE down_at < ".($now - 400 * 86400));
        mysqli_query($mysqli, "DELETE FROM tbl_dns_daily WHERE day < '".date('Y-m-d', $now - 400 * 86400)."'");
        mysqli_query($mysqli, "DELETE FROM tbl_dns_users WHERE last_seen < ".($now - 30 * 86400));
    }
}

/**
 * Guarda quais revendas têm clientes em cada servidor agora (antes da troca automática).
 * Chamado no começo de cada ciclo do monitor.
 */
function dns_usage_snapshot($mysqli){
    dns_stats_migrate($mysqli);
    $by_norm = array();
    $res = mysqli_query($mysqli, "SELECT id, dns_base FROM tbl_xui_dns");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $n = dns_normalize(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8'));
        if ($n != '') $by_norm[$n] = (int)$r['id'];
    }
    if (!$by_norm) return 0;
    $count = array(); // dns_id => reseller_id => playlists
    $res = mysqli_query($mysqli, "SELECT u.dns_base, u.dns_pin, d.reseller_id
                                  FROM tbl_users u JOIN tbl_devices d ON d.device_id = u.device_id AND d.owner = 'reseller'
                                  WHERE u.status = '1' AND d.reseller_id > 0");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $rid = (int)$r['reseller_id'];
        foreach (array_unique(array(dns_normalize($r['dns_base']), (string)$r['dns_pin'])) as $n) {
            if ($n == '' || !isset($by_norm[$n])) continue;
            $did = $by_norm[$n];
            $count[$did][$rid] = (isset($count[$did][$rid]) ? $count[$did][$rid] : 0) + 1;
        }
    }
    $now = time();
    $n = 0;
    foreach ($count as $did => $list) {
        foreach ($list as $rid => $pl) {
            mysqli_query($mysqli, "INSERT INTO tbl_dns_users (dns_id, reseller_id, playlists, last_seen) VALUES ($did, $rid, $pl, $now)
                                   ON DUPLICATE KEY UPDATE playlists = $pl, last_seen = $now");
            $n++;
        }
    }
    return $n;
}

// Revendas que usaram o servidor nos últimos DNS_USERS_KEEP_DAYS dias (para o aviso de queda)
function dns_users_resellers($mysqli, $dns_id){
    dns_stats_migrate($mysqli);
    $since = time() - DNS_USERS_KEEP_DAYS * 86400;
    $out = array();
    $res = mysqli_query($mysqli, "SELECT reseller_id FROM tbl_dns_users WHERE dns_id = ".(int)$dns_id." AND last_seen >= $since");
    while ($res && $r = mysqli_fetch_assoc($res)) $out[] = (int)$r['reseller_id'];
    return $out;
}

/**
 * Ranking dos servidores no período (últimos $days dias). Mais estável primeiro.
 * Retorna lista com: id, title, base, health, reseller_id, reseller_name, checks, uptime (0-100 ou null),
 * drops, downtime (s), longest (s), last_drop, latency (ms), resellers, playlists_now.
 */
function dns_ranking($mysqli, $days, $scope = null){
    dns_stats_migrate($mysqli);
    $now = time();
    $start = $now - (int)$days * 86400;
    $day_start = date('Y-m-d', $start);
    $where = $scope === null ? '' : " WHERE (d.reseller_id = ".(int)$scope." OR d.id IN (SELECT dns_id FROM tbl_dns_share WHERE reseller_id = ".(int)$scope."))";
    $rows = array();
    $res = mysqli_query($mysqli, "SELECT d.id, d.dns_title, d.dns_base, d.health, d.status, d.reseller_id, d.latency_ms, d.created_at, COALESCE(r.name, '') AS reseller_name
                                  FROM tbl_xui_dns d LEFT JOIN tbl_resellers r ON r.id = d.reseller_id".$where);
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $rows[(int)$r['id']] = array(
            'id' => (int)$r['id'], 'title' => html_entity_decode($r['dns_title'], ENT_QUOTES, 'UTF-8'),
            'base' => rtrim(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8'), '/'), 'health' => $r['health'],
            'enabled' => (string)$r['status'] == '1', 'reseller_id' => (int)$r['reseller_id'], 'reseller_name' => $r['reseller_name'],
            'checks' => 0, 'ok' => 0, 'uptime' => null, 'latency' => 0, 'drops' => 0, 'downtime' => 0, 'longest' => 0, 'last_drop' => 0,
            'resellers' => 0, 'playlists_now' => 0,
        );
    }
    if (!$rows) return array();
    $ids = implode(',', array_keys($rows));

    $res = mysqli_query($mysqli, "SELECT dns_id, SUM(checks) AS c, SUM(ok) AS o, SUM(latency_sum) AS l FROM tbl_dns_daily
                                  WHERE dns_id IN ($ids) AND day >= '$day_start' GROUP BY dns_id");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $id = (int)$r['dns_id'];
        $rows[$id]['checks'] = (int)$r['c'];
        $rows[$id]['ok'] = (int)$r['o'];
        $rows[$id]['uptime'] = (int)$r['c'] > 0 ? round((int)$r['o'] * 100 / (int)$r['c'], 2) : null;
        $rows[$id]['latency'] = (int)$r['o'] > 0 ? (int)round((int)$r['l'] / (int)$r['o']) : 0;
    }
    // quedas que tocam o período (inclusive a que ainda está aberta)
    $res = mysqli_query($mysqli, "SELECT dns_id, down_at, up_at FROM tbl_dns_events
                                  WHERE dns_id IN ($ids) AND (up_at = 0 OR up_at >= $start) ORDER BY down_at ASC");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $id = (int)$r['dns_id'];
        $a = max((int)$r['down_at'], $start);
        $b = (int)$r['up_at'] > 0 ? (int)$r['up_at'] : $now;
        $dur = max(0, $b - $a);
        if ((int)$r['down_at'] >= $start) $rows[$id]['drops']++;
        $rows[$id]['downtime'] += $dur;
        $rows[$id]['longest'] = max($rows[$id]['longest'], $b - (int)$r['down_at']);
        $rows[$id]['last_drop'] = max($rows[$id]['last_drop'], (int)$r['down_at']);
    }
    $since = $now - DNS_USERS_KEEP_DAYS * 86400;
    $res = mysqli_query($mysqli, "SELECT dns_id, COUNT(*) AS n, SUM(IF(last_seen >= ".($now - 900).", playlists, 0)) AS p FROM tbl_dns_users
                                  WHERE dns_id IN ($ids) AND last_seen >= $since GROUP BY dns_id");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $rows[(int)$r['dns_id']]['resellers'] = (int)$r['n'];
        $rows[(int)$r['dns_id']]['playlists_now'] = (int)$r['p'];
    }

    // ordem: com dados primeiro; maior tempo no ar; menos quedas; menos tempo fora; menor latência
    usort($rows, function ($x, $y) {
        $hx = $x['uptime'] === null ? 1 : 0; $hy = $y['uptime'] === null ? 1 : 0;
        if ($hx != $hy) return $hx - $hy;
        if ($x['uptime'] != $y['uptime']) return $x['uptime'] < $y['uptime'] ? 1 : -1;
        if ($x['drops'] != $y['drops']) return $x['drops'] - $y['drops'];
        if ($x['downtime'] != $y['downtime']) return $x['downtime'] - $y['downtime'];
        $lx = $x['latency'] > 0 ? $x['latency'] : PHP_INT_MAX; $ly = $y['latency'] > 0 ? $y['latency'] : PHP_INT_MAX;
        return $lx == $ly ? 0 : ($lx < $ly ? -1 : 1);
    });
    return $rows;
}

// Selo do servidor no ranking: excellent | good | unstable | bad | nodata
function dns_rank_grade($r){
    if ($r['uptime'] === null) return 'nodata';
    if ($r['drops'] == 0 && $r['uptime'] >= 99.9) return 'excellent';
    if ($r['uptime'] >= 99 && $r['drops'] <= 2) return 'good';
    if ($r['uptime'] >= 95) return 'unstable';
    return 'bad';
}

// Últimas quedas (para a lista "Histórico de quedas")
function dns_recent_drops($mysqli, $limit = 30, $scope = null){
    dns_stats_migrate($mysqli);
    $where = $scope === null ? '' : " AND (d.reseller_id = ".(int)$scope." OR d.id IN (SELECT dns_id FROM tbl_dns_share WHERE reseller_id = ".(int)$scope."))";
    $out = array();
    $res = mysqli_query($mysqli, "SELECT e.down_at, e.up_at, d.dns_title, d.dns_base FROM tbl_dns_events e JOIN tbl_xui_dns d ON d.id = e.dns_id
                                  WHERE 1 $where ORDER BY e.down_at DESC LIMIT ".(int)$limit);
    while ($res && $r = mysqli_fetch_assoc($res)) $out[] = $r;
    return $out;
}

function dns_fmt_duration($sec){
    $sec = (int)$sec;
    if ($sec <= 0) return '—';
    if ($sec < 3600) return max(1, (int)floor($sec / 60)).' min';
    if ($sec < 86400) return floor($sec / 3600).'h '.str_pad((string)floor(($sec % 3600) / 60), 2, '0', STR_PAD_LEFT).'min';
    return floor($sec / 86400).'d '.floor(($sec % 86400) / 3600).'h';
}
