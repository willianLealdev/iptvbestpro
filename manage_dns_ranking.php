<?php $page_title="Ranking dos Servidores";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/playlist_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    dns_health_migrate($mysqli);
    dns_stats_migrate($mysqli);

    $periods = array('1' => 'Últimas 24 horas', '7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias');
    $days = (isset($_GET['days']) && isset($periods[$_GET['days']])) ? (int)$_GET['days'] : 7;
    $owner = (isset($_GET['owner']) && in_array($_GET['owner'], array('admin', 'reseller'))) ? $_GET['owner'] : '';

    $all = dns_ranking($mysqli, $days);
    $rows = array();
    foreach ($all as $r) {
        if ($owner == 'admin' && $r['reseller_id'] > 0) continue;
        if ($owner == 'reseller' && $r['reseller_id'] == 0) continue;
        $rows[] = $r;
    }
    $with_data = array_values(array_filter($rows, function ($r) { return $r['uptime'] !== null; }));
    $worst = $with_data;
    usort($worst, function ($x, $y) {
        if ($x['drops'] != $y['drops']) return $y['drops'] - $x['drops'];
        return $y['downtime'] - $x['downtime'];
    });
    $worst = array_values(array_filter($worst, function ($r) { return $r['drops'] > 0; }));
    $drops = dns_recent_drops($mysqli, 30);
    $first = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT MIN(day) AS d FROM tbl_dns_daily"));
    $since_txt = !empty($first['d']) ? date('d/m/Y', strtotime($first['d'])) : '';

    $grade_info = array(
        'excellent' => array('Excelente', '#15803d', '#f0fdf4', '#bbf7d0'),
        'good'      => array('Bom', '#1d4ed8', '#eff6ff', '#bfdbfe'),
        'unstable'  => array('Instável', '#b45309', '#fffbeb', '#fde68a'),
        'bad'       => array('Ruim', '#b91c1c', '#fef2f2', '#fecaca'),
        'nodata'    => array('Sem dados', '#6b7280', '#f9fafb', '#e5e7eb'),
    );
    function rk_badge($g, $info){ $i = $info[$g]; return '<span class="rk-badge" style="color:'.$i[1].';background:'.$i[2].';border-color:'.$i[3].'">'.$i[0].'</span>'; }
    function rk_up($u){ return $u === null ? '—' : rtrim(rtrim(number_format($u, 2, ',', ''), '0'), ',').'%'; }
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_dns.php">DNS</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <style>
            .co-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .co-table thead th { background: #fafbfc; font-weight: 600; text-align: center; white-space: nowrap; padding: 12px 10px; }
            .co-table tbody td { text-align: center; vertical-align: middle; white-space: nowrap; padding: 11px 10px; }
            .rk-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
            .rk-pos { display: inline-flex; width: 34px; height: 34px; border-radius: 50%; align-items: center; justify-content: center; font-weight: 700; background: #f1f5f9; color: #334155; }
            .rk-pos.p1 { background: #fde68a; color: #78350f; } .rk-pos.p2 { background: #e5e7eb; color: #374151; } .rk-pos.p3 { background: #fed7aa; color: #7c2d12; }
            .rk-podium .card { text-align: center; }
            .rk-podium .medal { font-size: 34px; line-height: 1; }
            .rk-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
            .rk-bar { height: 6px; border-radius: 4px; background: #e5e7eb; overflow: hidden; margin-top: 4px; min-width: 90px; }
            .rk-bar > i { display: block; height: 100%; }
        </style>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-1">Ranking dos Servidores</h4>
                <div class="text-muted small">Do mais estável para o que mais caiu. O monitor confere cada servidor a cada 5 minutos.<?php if ($since_txt) { ?> Histórico desde <?php echo $since_txt; ?>.<?php } ?></div>
            </div>
            <form method="get" class="d-flex gap-2">
                <select name="days" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($periods as $k => $l) { ?><option value="<?php echo $k; ?>" <?php if ($days == (int)$k) echo 'selected'; ?>><?php echo $l; ?></option><?php } ?>
                </select>
                <select name="owner" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos os servidores</option>
                    <option value="admin" <?php if ($owner == 'admin') echo 'selected'; ?>>Só os meus (admin)</option>
                    <option value="reseller" <?php if ($owner == 'reseller') echo 'selected'; ?>>Só os das revendas</option>
                </select>
            </form>
        </div>

        <?php if (!$with_data) { ?>
            <div class="alert alert-info">O histórico começa a ser montado agora: a cada 5 minutos o monitor registra se cada servidor respondeu. Volte daqui a algumas horas para ver o ranking.</div>
        <?php } else { ?>
            <!-- Pódio: os 3 mais estáveis -->
            <div class="row g-3 mb-4 rk-podium">
                <?php $medals = array('🥇', '🥈', '🥉'); for ($i = 0; $i < min(3, count($with_data)); $i++) { $r = $with_data[$i]; ?>
                    <div class="col-md-4">
                        <div class="card co-card p-3 h-100">
                            <div class="medal"><?php echo $medals[$i]; ?></div>
                            <div class="fw-bold mt-2" translate="no"><?php echo htmlspecialchars($r['title']); ?></div>
                            <div class="text-muted small text-truncate" translate="no"><?php echo htmlspecialchars($r['base']); ?></div>
                            <div class="fs-3 fw-bold mt-2" style="color:#15803d;"><?php echo rk_up($r['uptime']); ?></div>
                            <div class="small text-muted">no ar</div>
                            <div class="mt-2"><?php echo $r['drops'] == 0 ? '<b style="color:#15803d;">Nunca caiu</b>' : '<b>'.$r['drops'].'</b> '.($r['drops'] == 1 ? 'queda' : 'quedas'); ?><?php echo $r['latency'] ? ' · '.$r['latency'].' ms' : ''; ?></div>
                            <?php if ($r['playlists_now'] > 0) { ?><div class="small text-muted mt-1"><?php echo $r['playlists_now']; ?> listas usando agora</div><?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <?php if ($worst) { ?>
            <div class="card co-card mb-4" style="border-left:4px solid #dc2626 !important;">
                <div class="card-body p-4">
                    <h5 class="mb-3" style="color:#b91c1c;"><i class="ri-error-warning-line"></i> Servidores que mais caíram</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (array_slice($worst, 0, 8) as $r) { ?>
                            <div class="p-2 px-3 rounded" style="background:#fef2f2;border:1px solid #fecaca;">
                                <b translate="no"><?php echo htmlspecialchars($r['title']); ?></b> — caiu <b><?php echo $r['drops']; ?></b> <?php echo $r['drops'] == 1 ? 'vez' : 'vezes'; ?>, <?php echo dns_fmt_duration($r['downtime']); ?> fora do ar
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        <?php } ?>

        <div class="card co-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Ranking completo · <?php echo htmlspecialchars($periods[(string)$days]); ?></h5>
                <div class="table-responsive">
                    <table class="table co-table mb-0">
                        <thead><tr><th>#</th><th>Servidor</th><th>Agora</th><th>Tempo no ar</th><th>Quedas</th><th>Tempo fora</th><th>Maior queda</th><th>Última queda</th><th>Latência</th><th>Revendas usando</th><th>Selo</th></tr></thead>
                        <tbody>
                        <?php if ($rows) { $pos = 0; foreach ($rows as $r) { $pos++; $g = dns_rank_grade($r);
                            $color = $r['uptime'] === null ? '#9ca3af' : ($r['uptime'] >= 99.9 ? '#16a34a' : ($r['uptime'] >= 99 ? '#2563eb' : ($r['uptime'] >= 95 ? '#d97706' : '#dc2626'))); ?>
                            <tr>
                                <td><span class="rk-pos <?php echo $r['uptime'] !== null && $pos <= 3 ? 'p'.$pos : ''; ?>"><?php echo $pos; ?></span></td>
                                <td class="text-start" translate="no">
                                    <b><?php echo htmlspecialchars($r['title']); ?></b><?php if (!$r['enabled']) { ?> <span class="badge bg-secondary">desligado</span><?php } ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($r['base']); ?><?php echo $r['reseller_id'] > 0 ? ' · Revenda: '.htmlspecialchars($r['reseller_name']) : ' · Admin'; ?></small>
                                </td>
                                <td><?php if ($r['health'] == 'online') { ?><span class="rk-dot" style="background:#16a34a"></span>Online<?php } elseif ($r['health'] == 'offline') { ?><span class="rk-dot" style="background:#dc2626"></span>Offline<?php } else { ?><span class="text-muted">—</span><?php } ?></td>
                                <td>
                                    <b style="color:<?php echo $color; ?>"><?php echo rk_up($r['uptime']); ?></b>
                                    <?php if ($r['uptime'] !== null) { ?><div class="rk-bar"><i style="width:<?php echo max(2, $r['uptime']); ?>%;background:<?php echo $color; ?>"></i></div><?php } ?>
                                </td>
                                <td><?php echo $r['uptime'] === null ? '—' : ($r['drops'] == 0 ? '<span style="color:#15803d;font-weight:600;">Nunca caiu</span>' : '<b>'.$r['drops'].'</b>'); ?></td>
                                <td><?php echo dns_fmt_duration($r['downtime']); ?></td>
                                <td><?php echo dns_fmt_duration($r['longest']); ?></td>
                                <td><?php echo $r['last_drop'] ? date('d/m H:i', $r['last_drop']) : '—'; ?></td>
                                <td><?php echo $r['latency'] ? $r['latency'].' ms' : '—'; ?></td>
                                <td><?php echo $r['resellers'] > 0 ? $r['resellers'] : '—'; ?><?php if ($r['playlists_now'] > 0) { ?><br><small class="text-muted"><?php echo $r['playlists_now']; ?> listas</small><?php } ?></td>
                                <td><?php echo rk_badge($g, $grade_info); ?></td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="11" class="text-muted py-5">Nenhum servidor cadastrado.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mt-3">
                    <b>Como o ranking é feito:</b> primeiro o maior tempo no ar; empatando, o que caiu menos vezes, depois o que ficou menos tempo fora e, por último, o mais rápido (latência).
                    Selos: <b>Excelente</b> = nunca caiu (99,9%+), <b>Bom</b> = 99%+ e até 2 quedas, <b>Instável</b> = 95% a 99%, <b>Ruim</b> = abaixo de 95%.
                </div>
            </div>
        </div>

        <div class="card co-card">
            <div class="card-body p-4">
                <h5 class="mb-3">Histórico de quedas</h5>
                <div class="table-responsive">
                    <table class="table co-table mb-0">
                        <thead><tr><th>Servidor</th><th>Caiu em</th><th>Voltou em</th><th>Ficou fora</th></tr></thead>
                        <tbody>
                        <?php if ($drops) { foreach ($drops as $e) { $open = (int)$e['up_at'] == 0; ?>
                            <tr>
                                <td class="text-start" translate="no"><b><?php echo htmlspecialchars(html_entity_decode($e['dns_title'], ENT_QUOTES, 'UTF-8')); ?></b><br><small class="text-muted"><?php echo htmlspecialchars(rtrim(html_entity_decode($e['dns_base'], ENT_QUOTES, 'UTF-8'), '/')); ?></small></td>
                                <td><?php echo date('d/m/Y H:i', $e['down_at']); ?></td>
                                <td><?php echo $open ? '<span style="color:#dc2626;font-weight:600;">Ainda fora do ar</span>' : date('d/m/Y H:i', $e['up_at']); ?></td>
                                <td><?php echo dns_fmt_duration(($open ? time() : (int)$e['up_at']) - (int)$e['down_at']); ?></td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="4" class="text-muted py-4">Nenhuma queda registrada.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
