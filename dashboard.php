<?php $page_title="Dashboard";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require_once("includes/reseller_helper.php");

    resellers_migrate($mysqli);

    function dash_count($mysqli, $sql){
        $row = mysqli_fetch_array(mysqli_query($mysqli, $sql));
        return $row ? (int)$row['num'] : 0;
    }

    $today_start = devices_today_start();

    $total_today     = dash_count($mysqli, "SELECT COUNT(*) as num FROM tbl_devices WHERE first_seen >= ".$today_start);
    $total_devices   = dash_count($mysqli, "SELECT COUNT(*) as num FROM tbl_devices");
    $total_resellers = dash_count($mysqli, "SELECT COUNT(*) as num FROM tbl_resellers");
    $total_credit_share = resellers_total_credit_share($mysqli);

    // Dispositivos por App
    $by_app = array();
    $res = mysqli_query($mysqli, "SELECT COALESCE(NULLIF(app_name,''), NULLIF(application_id,''), 'Desconhecido') AS app, COUNT(*) AS num
                                  FROM tbl_devices GROUP BY app ORDER BY num DESC");
    while($r = mysqli_fetch_assoc($res)){ $by_app[] = array('label' => $r['app'], 'value' => (int)$r['num']); }

    // Dispositivos por Plataforma (todas as plataformas aparecem, mesmo com 0)
    $platforms = devices_platform_labels();
    $by_platform = array_fill_keys(array_keys($platforms), 0);
    $res = mysqli_query($mysqli, "SELECT platform, COUNT(*) AS num FROM tbl_devices GROUP BY platform");
    while($r = mysqli_fetch_assoc($res)){
        $key = isset($platforms[$r['platform']]) ? $r['platform'] : 'android';
        $by_platform[$key] += (int)$r['num'];
    }
    arsort($by_platform);

    // Dispositivos por País
    $by_country = array();
    $res = mysqli_query($mysqli, "SELECT country_code, MAX(country_name) AS country_name, COUNT(*) AS num
                                  FROM tbl_devices WHERE country_code <> '' GROUP BY country_code ORDER BY num DESC");
    while($r = mysqli_fetch_assoc($res)){
        $by_country[] = array('code' => $r['country_code'], 'name' => $r['country_name'], 'value' => (int)$r['num']);
    }
    $total_unknown_country = dash_count($mysqli, "SELECT COUNT(*) as num FROM tbl_devices WHERE country_code = ''");

    // Últimas instalações
    $result_recent = mysqli_query($mysqli, "SELECT * FROM tbl_devices ORDER BY first_seen DESC LIMIT 8");

    $sql_reports="SELECT * FROM tbl_reports ORDER BY tbl_reports.`id` DESC LIMIT 10";
    $result_reports=mysqli_query($mysqli,$sql_reports);

    $qry="SELECT * FROM tbl_settings where id='1'";
    $result=mysqli_query($mysqli,$qry);
    $settings_data=mysqli_fetch_assoc($result);

    $app_colors = array('#16e85a','#ecbd45','#c93d77','#ee7b3a','#8a6f5c','#9b5de5','#52d39a','#f0435c','#d4d62a','#3b5bdb','#22c7e0','#b517d6');
?>

<link rel="stylesheet" href="assets/vendors/jsvectormap/jsvectormap.min.css" type="text/css">

<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo (isset($page_title)) ? $page_title : "" ?></li>
            </ol>
        </nav>

        <style>
            .dash-stat {
                border: 0;
                border-radius: 14px;
                border-bottom: 5px solid var(--dash-color);
                background: var(--dash-bg);
                box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
                height: 100%;
            }
            .dash-stat .card-body { display: flex; align-items: center; gap: 18px; padding: 18px 20px; }
            .dash-stat__icon {
                flex: 0 0 78px; width: 78px; height: 78px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                background: var(--dash-icon-bg); color: var(--dash-color); font-size: 34px;
            }
            .dash-stat__label { color: #6b7a99; font-size: 15px; margin-bottom: 4px; }
            .dash-stat__value { font-size: 22px; font-weight: 700; color: #1f2a44; line-height: 1.1; }
            .dash-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); height: 100%; }
            .dash-card__title { font-size: 20px; font-weight: 700; color: #1f2a44; margin-bottom: 20px; }
            .dash-legend { max-height: 300px; overflow-y: auto; padding-right: 6px; }
            .dash-legend__item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; margin-bottom: 8px; }
            .dash-legend__dot { width: 12px; height: 12px; border-radius: 50%; flex: 0 0 12px; }
            .dash-platform { display: flex; align-items: center; gap: 14px; padding: 11px 0; }
            .dash-platform__icon { width: 34px; text-align: center; font-size: 22px; }
            .dash-platform__name { flex: 1 1 auto; color: #4b5563; font-size: 14px; min-width: 0; }
            .dash-platform__bar { flex: 0 0 30%; height: 8px; border-radius: 6px; background: #eef1f6; overflow: hidden; }
            .dash-platform__bar span { display: block; height: 100%; border-radius: 6px; }
            .dash-platform__pct { flex: 0 0 44px; text-align: right; color: #4b5563; font-size: 14px; }
            #dash_map { width: 100%; height: 380px; }
            .dash-country { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f3f7; font-size: 14px; }
            .dash-country:last-child { border-bottom: 0; }
            .dash-country__flag { width: 26px; height: 18px; border-radius: 3px; object-fit: cover; box-shadow: 0 0 0 1px rgba(0,0,0,.06); }
            .dash-country__name { flex: 1 1 auto; color: #374151; }
            .dash-country__num { font-weight: 700; color: #1f2a44; }
            .dash-empty { color: #9ca3af; text-align: center; padding: 40px 10px; }
            .dash-chart-wrap { position: relative; height: 300px; }
        </style>

        <!-- Cards -->
        <div class="row g-4">
            <div class="col-xxl-3 col-md-6">
                <div class="card dash-stat" style="--dash-color:#f5b82e; --dash-bg:#fffcf3; --dash-icon-bg:#fff1cc;">
                    <div class="card-body">
                        <div class="dash-stat__icon"><i class="ri-tv-2-fill"></i></div>
                        <div>
                            <div class="dash-stat__label">Ativados Hoje</div>
                            <div class="dash-stat__value"><?php echo thousandsNumberFormat($total_today); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card dash-stat" style="--dash-color:#3b6cf6; --dash-bg:#f7f9fe; --dash-icon-bg:#e3eafd;">
                    <div class="card-body">
                        <div class="dash-stat__icon"><i class="ri-device-fill"></i></div>
                        <div>
                            <div class="dash-stat__label">Total de Dispositivos</div>
                            <div class="dash-stat__value"><?php echo thousandsNumberFormat($total_devices); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card dash-stat" style="--dash-color:#f0769a; --dash-bg:#fff7f9; --dash-icon-bg:#ffe0e8;">
                    <div class="card-body">
                        <div class="dash-stat__icon"><i class="ri-user-add-fill"></i></div>
                        <div>
                            <div class="dash-stat__label">Resellers</div>
                            <div class="dash-stat__value"><?php echo thousandsNumberFormat($total_resellers); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card dash-stat" style="--dash-color:#16c784; --dash-bg:#f3fdf7; --dash-icon-bg:#ccf7dd;">
                    <div class="card-body">
                        <div class="dash-stat__icon"><i class="ri-share-forward-box-fill"></i></div>
                        <div>
                            <div class="dash-stat__label">Total Credit Share</div>
                            <div class="dash-stat__value"><?php echo thousandsNumberFormat($total_credit_share); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por App / Por Plataforma -->
        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="dash-card__title">Dispositivos por App</div>
                        <?php if (count($by_app) > 0) { ?>
                            <div class="row align-items-center">
                                <div class="col-sm-7">
                                    <div class="dash-chart-wrap"><canvas id="dash_app_chart"></canvas></div>
                                </div>
                                <div class="col-sm-5 mt-3 mt-sm-0">
                                    <div class="dash-legend">
                                        <?php foreach ($by_app as $i => $app) { ?>
                                            <div class="dash-legend__item">
                                                <span class="dash-legend__dot" style="background: <?php echo $app_colors[$i % count($app_colors)]; ?>"></span>
                                                <span><?php echo htmlspecialchars($app['label']); ?> <?php echo $app['value']; ?></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="dash-empty"><i class="ri-apps-2-line" style="font-size:40px;"></i><br>Nenhum dispositivo registrado ainda.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="dash-card__title">Dispositivos por Plataforma</div>
                        <?php foreach ($by_platform as $key => $num) {
                            $p = $platforms[$key];
                            $pct = $total_devices > 0 ? round(($num / $total_devices) * 100) : 0; ?>
                            <div class="dash-platform">
                                <div class="dash-platform__icon" style="color: <?php echo $p['color']; ?>"><i class="<?php echo $p['icon']; ?>"></i></div>
                                <div class="dash-platform__name"><?php echo $p['label']; ?> <?php echo thousandsNumberFormat($num); ?></div>
                                <div class="dash-platform__bar"><span style="width: <?php echo $pct; ?>%; background: <?php echo $p['color']; ?>"></span></div>
                                <div class="dash-platform__pct"><?php echo $pct; ?>%</div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mapa por País -->
        <div class="row g-4 mt-1">
            <div class="col-lg-8">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="dash-card__title">Instalações por País</div>
                        <div id="dash_map"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="dash-card__title">Top Países</div>
                        <?php if (count($by_country) > 0) { ?>
                            <div style="max-height: 380px; overflow-y: auto;">
                                <?php foreach ($by_country as $c) { ?>
                                    <div class="dash-country">
                                        <img class="dash-country__flag" src="https://flagcdn.com/w40/<?php echo strtolower($c['code']); ?>.png" alt="" loading="lazy">
                                        <span class="dash-country__name" data-country="<?php echo htmlspecialchars($c['code']); ?>"><?php echo htmlspecialchars($c['name'] != '' ? $c['name'] : $c['code']); ?></span>
                                        <span class="dash-country__num"><?php echo thousandsNumberFormat($c['value']); ?></span>
                                    </div>
                                <?php } ?>
                                <?php if ($total_unknown_country > 0) { ?>
                                    <div class="dash-country">
                                        <i class="ri-question-line" style="width:26px;text-align:center;color:#9ca3af;"></i>
                                        <span class="dash-country__name">Não identificado</span>
                                        <span class="dash-country__num"><?php echo thousandsNumberFormat($total_unknown_country); ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="dash-empty"><i class="ri-earth-line" style="font-size:40px;"></i><br>Os países aparecem aqui quando os apps começarem a se registrar.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas instalações / Reports -->
        <div class="row g-4 mt-1">
            <div class="col-lg-7">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="dash-card__title">Últimas Instalações</div>
                        <?php if (mysqli_num_rows($result_recent) > 0) { ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr><th>Dispositivo</th><th>Plataforma</th><th>País</th><th>Instalado</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($d = mysqli_fetch_assoc($result_recent)) {
                                        $p = isset($platforms[$d['platform']]) ? $platforms[$d['platform']] : $platforms['android']; ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($d['device_model'] != '' ? $d['device_model'] : $d['device_id']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($d['app_name'] != '' ? $d['app_name'] : $d['application_id']); ?><?php echo $d['app_version'] != '' ? ' · v'.htmlspecialchars($d['app_version']) : ''; ?></small>
                                            </td>
                                            <td><i class="<?php echo $p['icon']; ?>" style="color: <?php echo $p['color']; ?>"></i> <?php echo $p['label']; ?></td>
                                            <td>
                                                <?php if ($d['country_code'] != '') { ?>
                                                    <img class="dash-country__flag" src="https://flagcdn.com/w40/<?php echo strtolower($d['country_code']); ?>.png" alt="" loading="lazy">
                                                    <span data-country="<?php echo htmlspecialchars($d['country_code']); ?>"><?php echo htmlspecialchars($d['country_code']); ?></span>
                                                <?php } else { ?>
                                                    <span class="text-muted">—</span>
                                                <?php } ?>
                                            </td>
                                            <td><?php echo calculate_time_span($d['first_seen']); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="dash-empty">Nenhuma instalação registrada ainda.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="dash-card__title mb-0">Novos Reports</div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="text-decoration-none text-dark" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ri-more-2-fill"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-sm">
                                    <li><a class="dropdown-item" href="manage_report.php">Gerenciar</a></li>
                                </ul>
                            </div>
                        </div>
                        <?php if(mysqli_num_rows($result_reports) > 0){ ?>
                            <?php while($row=mysqli_fetch_array($result_reports)) { ?>
                                <div class="d-flex align-items-center justify-content-between mt-4">
                                    <span class="d-block fw-semibold"><?php echo htmlspecialchars((string)$row['report_title'], ENT_QUOTES, 'UTF-8', false);?></span>
                                    <span class="text-muted"><?php echo calculate_time_span($row['report_on']);?></span>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="dash-empty">Nenhum report.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tema do App -->
        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="card dash-card">
                    <div class="card-body p-4">
                        <div class="dash-card__title">Tema do App</div>
                        <?php
                            $themes = array('2' => 'Glossy', '3' => 'BlackPanther', '4' => 'MovieUI', '5' => 'VUI', '6' => 'ChristmasUI', '7' => 'HalloweenUI');
                            $theme_img = isset($themes[$settings_data['is_theme']]) ? $themes[$settings_data['is_theme']] : 'OneUI';
                        ?>
                        <?php
                            require_once("includes/theme_helper.php");
                            $dash_active = theme_active($mysqli, 0);
                            $dash_saved = $dash_active['saved_id'] > 0 ? theme_saved_get($mysqli, 0, $dash_active['saved_id']) : null;
                            $dash_catalog = theme_catalog();
                        ?>
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <div>
                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($dash_saved ? $dash_saved['name'] : $dash_catalog[$dash_active['base']]['name'].' (original)'); ?></div>
                                <div class="text-muted small">Modelo: <?php echo htmlspecialchars($dash_catalog[$dash_active['base']]['name']); ?></div>
                            </div>
                            <a href="settings_theme.php" class="btn btn-outline-primary ms-auto"><i class="ri-palette-line"></i> Temas do App</a>
                        </div>
                        <?php if ($dash_saved) {
                            // Tema personalizado em uso: mostra como ele aparece no app
                            require_once("includes/theme_preview.php");
                            theme_preview_styles();
                            $dash_poster = theme_movie_poster($mysqli);
                            echo '<div style="max-width:900px;border-radius:10px;overflow:hidden;">';
                            theme_mini_preview($dash_saved['settings_arr'], $dash_active['base'], 'images/', brand_logo_url(), APP_NAME,
                                               $dash_poster != '' ? 'images/'.$dash_poster : 'assets/images/themes/MovieUI.png');
                            echo '</div>';
                        } else if ($dash_active['base'] != '8') { ?>
                            <img src="assets/images/themes/<?php echo $theme_img; ?>.png" alt="" style="width: 100%; max-width: 900px; height: auto; border-radius: 10px;">
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->

<script src="assets/vendors/jsvectormap/jsvectormap.min.js"></script>
<script src="assets/vendors/jsvectormap/world.js"></script>
<script>
(function () {
    var byApp = <?php echo json_encode($by_app, JSON_UNESCAPED_UNICODE); ?>;
    var byCountry = <?php echo json_encode($by_country, JSON_UNESCAPED_UNICODE); ?>;
    var appColors = <?php echo json_encode($app_colors); ?>;

    var countryNames = null;
    try { countryNames = new Intl.DisplayNames(['pt-BR'], { type: 'region' }); } catch (e) {}
    function countryName(code, fallback) {
        try { return (countryNames && countryNames.of(code)) || fallback || code; } catch (e) { return fallback || code; }
    }

    // Nomes de países em português
    document.querySelectorAll('[data-country]').forEach(function (el) {
        el.textContent = countryName(el.getAttribute('data-country'), el.textContent);
    });

    // Mapa
    var values = {};
    byCountry.forEach(function (c) { values[c.code] = c.value; });
    var mapOptions = {
        selector: '#dash_map',
        map: 'world',
        zoomButtons: true,
        regionStyle: {
            initial: { fill: '#e4e9f2', stroke: '#ffffff', strokeWidth: 0.4 },
            hover: { fillOpacity: 0.8, cursor: 'pointer' }
        },
        onRegionTooltipShow: function (event, tooltip, code) {
            var n = values[code] || 0;
            tooltip.text(countryName(code, tooltip.text()) + ': ' + n + (n === 1 ? ' dispositivo' : ' dispositivos'));
        }
    };
    if (byCountry.length > 0) {
        mapOptions.visualizeData = { scale: ['#bfd3ff', '#2f5bea'], values: values };
    }
    new jsVectorMap(mapOptions);

    // Gráfico por App
    window.addEventListener('load', function () {
        var canvas = document.getElementById('dash_app_chart');
        if (!canvas || typeof Chart === 'undefined') return;
        var total = byApp.reduce(function (s, a) { return s + a.value; }, 0);
        new Chart(canvas, {
            type: 'pie',
            data: {
                labels: byApp.map(function (a) { return a.label; }),
                datasets: [{
                    data: byApp.map(function (a) { return a.value; }),
                    backgroundColor: byApp.map(function (a, i) { return appColors[i % appColors.length]; }),
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var pct = total ? (ctx.parsed * 100 / total).toFixed(1) : 0;
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    });
})();
</script>
<?php include("includes/footer.php");?>
