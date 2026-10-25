<?php $page_title="Temas das revendas";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");
    require_once("includes/theme_helper.php");
    require_once("includes/theme_form.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    theme_reseller_migrate($mysqli);
    theme_saved_migrate($mysqli);
    $catalog = theme_catalog();
    $self = 'manage_reseller_themes.php';

    function rt_flash($ok, $msg){ $_SESSION['rt_msg'] = array($ok, $msg); }

    // Liga/desliga a marca própria para TODAS as revendas (desligado: todas usam o tema do app)
    if(isset($_POST['toggle_branding'])){
        $new = theme_reseller_allowed($mysqli) ? '0' : '1';
        panel_config_set($mysqli, 'reseller_branding', $new);
        rt_flash(true, $new == '1' ? 'Marca própria das revendas LIGADA.' : 'Marca própria das revendas DESLIGADA: todas usam o tema do app.');
        header("Location:".$self);
        exit;
    }
    // Volta UMA revenda para o tema do app (desliga a marca própria dela)
    if(isset($_POST['reset_reseller'])){
        $rid = (int)$_POST['reseller_id'];
        $row = theme_reseller_row($mysqli, $rid);
        theme_reseller_set_basic($mysqli, $rid, 0, (string)$row['brand_name']);
        rt_flash(true, 'Revenda voltou para o tema do app.');
        header("Location:".$self.(isset($_GET['f']) ? '?f='.urlencode((string)$_GET['f']) : ''));
        exit;
    }

    $allowed = theme_reseller_allowed($mysqli);
    $filter = (isset($_GET['f']) && in_array($_GET['f'], array('own', 'app'), true)) ? $_GET['f'] : '';

    $list = array();
    $res = mysqli_query($mysqli, "SELECT r.id, r.name, r.email, r.status, r.kind, t.enabled, t.theme_id, t.saved_id, t.brand_name, t.updated_at
                                  FROM tbl_resellers r LEFT JOIN tbl_reseller_theme t ON t.reseller_id = r.id
                                  ORDER BY (t.enabled = 1) DESC, t.updated_at DESC, r.name ASC");
    while($res && $r = mysqli_fetch_assoc($res)){
        $own = $allowed && (int)$r['enabled'] == 1;
        $r['own'] = $own;
        $list[] = $r;
    }
    $count_own = 0; $count_app = 0;
    foreach($list as $r){ if($r['own']) $count_own++; else $count_app++; }

    function rt_e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
    function rt_img($file){
        $file = basename((string)$file);
        return ($file != '' && is_file(__DIR__.'/images/'.$file)) ? 'images/'.$file : '';
    }
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <?php theme_form_styles(); ?>
        <style>
            .rt-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .rt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 18px; }
            .rt-item { border: 1px solid #eef0f4; border-radius: 12px; padding: 14px; background: #fff; }
            .rt-item.own { border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245, 158, 11, .15); }
            .rt-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
            .rt-name { font-weight: 700; color: #1f2a44; }
            .rt-mail { color: #6b7280; font-size: 12px; word-break: break-all; }
            .rt-badge { font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }
            .rt-badge.own { background: #fef3c7; color: #92400e; }
            .rt-badge.app { background: #e0f2fe; color: #075985; }
            .rt-meta { font-size: 13px; color: #374151; margin: 6px 0; }
            .rt-meta b { color: #111827; }
            .rt-prev { margin: 10px 0; border-radius: 10px; overflow: hidden; background: #0b1220; }
            .rt-prev .tv { width: 100%; }
            .rt-imgs { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
            .rt-imgs a { display: block; text-align: center; font-size: 11px; color: #6b7280; text-decoration: none; }
            .rt-imgs img { display: block; height: 64px; max-width: 120px; object-fit: contain; background: #111827; border-radius: 8px; border: 1px solid #1f2937; margin-bottom: 3px; }
            .rt-filters a { margin-right: 6px; }
        </style>

        <div class="card rt-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Qual tema cada revenda está usando</h5>
                        <div class="text-muted" style="font-size:13px">
                            <b>Tema próprio:</b> a revenda personalizou nome, logo ou imagens (aparece nos aparelhos dela).
                            <b>Tema do app:</b> usa o seu visual padrão. Confira as imagens antes de aprovar o que vai para as lojas.
                        </div>
                    </div>
                    <form method="post" class="m-0">
                        <button type="submit" name="toggle_branding" class="btn <?php echo $allowed ? 'btn-outline-danger' : 'btn-success'; ?>"
                                onclick="return confirm('<?php echo $allowed ? 'Desligar a marca própria de TODAS as revendas? Todas passam a usar o tema do app.' : 'Ligar a marca própria das revendas?'; ?>')">
                            <?php echo $allowed ? 'Desligar marca própria de todas' : 'Ligar marca própria das revendas'; ?>
                        </button>
                    </form>
                </div>
                <div class="mt-3" style="font-size:13px">
                    Marca própria das revendas: <b class="<?php echo $allowed ? 'text-success' : 'text-danger'; ?>"><?php echo $allowed ? 'LIGADA' : 'DESLIGADA'; ?></b>
                    &nbsp;·&nbsp; Usando tema próprio: <b><?php echo $count_own; ?></b>
                    &nbsp;·&nbsp; Usando tema do app: <b><?php echo $count_app; ?></b>
                </div>
                <?php if(!empty($_SESSION['rt_msg'])){ list($ok, $msg) = $_SESSION['rt_msg']; unset($_SESSION['rt_msg']); ?>
                    <div class="alert alert-<?php echo $ok ? 'success' : 'danger'; ?> mt-3 mb-0"><?php echo rt_e($msg); ?></div>
                <?php } ?>
                <div class="rt-filters mt-3">
                    <a href="<?php echo $self; ?>" class="btn btn-sm <?php echo $filter == '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todas (<?php echo count($list); ?>)</a>
                    <a href="<?php echo $self; ?>?f=own" class="btn btn-sm <?php echo $filter == 'own' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Tema próprio (<?php echo $count_own; ?>)</a>
                    <a href="<?php echo $self; ?>?f=app" class="btn btn-sm <?php echo $filter == 'app' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Tema do app (<?php echo $count_app; ?>)</a>
                </div>
            </div>
        </div>

        <div class="rt-grid">
        <?php
        $shown = 0;
        foreach($list as $r){
            if($filter == 'own' && !$r['own']) continue;
            if($filter == 'app' && $r['own']) continue;
            $shown++;
            $rid = (int)$r['id'];
            $active = theme_active($mysqli, $rid);
            $base = $active['base'];
            $saved = $active['saved_id'] > 0 ? theme_saved_get($mysqli, $rid, $active['saved_id']) : null;
            $settings = $saved ? $saved['settings_arr'] : theme_defaults($base);
            $model_name = isset($catalog[$base]) ? $catalog[$base]['name'] : $base;
            $brand = ($saved && $settings['theme_brand_text'] != '') ? $settings['theme_brand_text'] : ($r['brand_name'] != '' ? $r['brand_name'] : '');
            $poster = theme_movie_poster($mysqli, $rid);
            $hero = $poster != '' ? 'images/'.$poster : 'assets/images/themes/MovieUI.png';
            $imgs = array();
            if($saved){
                foreach(array('theme_logo' => 'Logo', 'theme_bg_image' => 'Fundo', 'theme_banner' => 'Banner') as $k => $lab){
                    if(!empty($settings[$k])){ $u = rt_img($settings[$k]); if($u != '') $imgs[] = array($lab, $u); }
                }
            }
        ?>
            <div class="rt-item <?php echo $r['own'] ? 'own' : ''; ?>">
                <div class="rt-head">
                    <div>
                        <div class="rt-name"><?php echo rt_e($r['name']); ?><?php if($r['kind'] == 'server'){ ?> <small class="text-muted">(servidor)</small><?php } ?></div>
                        <div class="rt-mail"><?php echo rt_e($r['email']); ?></div>
                    </div>
                    <span class="rt-badge <?php echo $r['own'] ? 'own' : 'app'; ?>"><?php echo $r['own'] ? 'TEMA PRÓPRIO' : 'TEMA DO APP'; ?></span>
                </div>

                <?php if($r['own']){ ?>
                    <div class="rt-meta">Modelo: <b><?php echo rt_e($model_name); ?></b>
                        · <?php echo $saved ? 'Personalizado: <b>'.rt_e($saved['name']).'</b>' : 'Modelo original, sem personalização'; ?></div>
                    <div class="rt-meta">Nome da marca: <b><?php echo $brand != '' ? rt_e($brand) : '—'; ?></b>
                        <?php if((int)$r['updated_at'] > 0){ ?> · alterado em <?php echo date('d/m/Y H:i', (int)$r['updated_at']); ?><?php } ?></div>

                    <div class="rt-prev">
                        <?php theme_mini_preview($settings, $base, 'images/', brand_logo_url(), $brand != '' ? $brand : APP_NAME, $hero); ?>
                    </div>

                    <?php if($imgs){ ?>
                        <div class="rt-imgs">
                            <?php foreach($imgs as $im){ ?>
                                <a href="<?php echo rt_e($im[1]); ?>" target="_blank" rel="noopener"><img src="<?php echo rt_e($im[1]); ?>" alt=""><?php echo rt_e($im[0]); ?></a>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="rt-meta text-muted">Sem imagens próprias enviadas (usa as do modelo).</div>
                    <?php } ?>

                    <form method="post" class="mt-3 mb-0">
                        <input type="hidden" name="reseller_id" value="<?php echo $rid; ?>">
                        <button type="submit" name="reset_reseller" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Voltar esta revenda para o tema do app?')">Voltar para o tema do app</button>
                    </form>
                <?php } else { ?>
                    <div class="rt-meta text-muted">
                        <?php echo (!$allowed && (int)$r['enabled'] == 1) ? 'Tem tema próprio salvo, mas a marca própria está desligada para todas.' : 'Esta revenda usa o visual padrão do seu app.'; ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        </div>
        <?php if($shown == 0){ ?>
            <div class="text-center text-muted py-5">Nenhuma revenda nesta lista.</div>
        <?php } ?>
    </div>
</main>

<?php include("includes/footer.php");?>
