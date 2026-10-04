<?php $page_title="Temas do App";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/theme_helper.php");
    require_once("includes/theme_form.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    theme_custom_migrate($mysqli);
    $catalog = theme_catalog();

    function th_flash($ok, $msg){
        $_SESSION['th_msg'] = $msg;
        $_SESSION['msg'] = 'th_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }

    $active_theme = (string)$settings_details['is_theme'];
    if(!isset($catalog[$active_theme])){ $active_theme = '1'; }
    $edit_id = (isset($_GET['theme']) && isset($catalog[(string)$_GET['theme']])) ? (string)$_GET['theme'] : $active_theme;
    $is_model = $edit_id !== THEME_BESTPRO_ID;

    // Ativar um modelo no app
    if(isset($_POST['use_theme']) && isset($catalog[(string)$_POST['use_theme']])){
        $use = (string)$_POST['use_theme'];
        mysqli_query($mysqli, "UPDATE tbl_settings SET is_theme = '".mysqli_real_escape_string($mysqli, $use)."' WHERE id = 1");
        th_flash(true, 'Tema "'.$catalog[$use]['name'].'" ativado no app.');
        header("Location:settings_theme.php?theme=".$use);
        exit;
    }

    $current = theme_get($mysqli, 0, $edit_id);

    if(isset($_POST['save_theme'])){
        $r = theme_process_form($current, $_POST, theme_file_prefix(0, $edit_id));
        if($is_model){
            theme_custom_save($mysqli, 0, $edit_id, !empty($_POST['customize_enabled']), $r['settings']);
        } else {
            theme_save_admin($mysqli, $r['settings']);
        }
        if(!empty($_POST['activate_theme'])){
            mysqli_query($mysqli, "UPDATE tbl_settings SET is_theme = '".mysqli_real_escape_string($mysqli, $edit_id)."' WHERE id = 1");
        }
        panel_config_set($mysqli, 'reseller_branding', !empty($_POST['reseller_branding']) ? '1' : '0');
        th_flash(!$r['errors'], $r['errors'] ? implode(' ', $r['errors']) : 'Personalização do modelo "'.$catalog[$edit_id]['name'].'" salva. Os aparelhos recebem na próxima abertura do app.');
        header("Location:settings_theme.php?theme=".$edit_id);
        exit;
    }

    if(isset($_POST['reset_theme'])){
        foreach(array('theme_logo', 'theme_bg_image', 'theme_banner') as $key){ theme_delete_file($current[$key]); }
        if($is_model){ theme_custom_save($mysqli, 0, $edit_id, false, theme_defaults($edit_id)); }
        else { theme_save_admin($mysqli, theme_defaults($edit_id)); }
        th_flash(true, 'Modelo "'.$catalog[$edit_id]['name'].'" voltou ao original.');
        header("Location:settings_theme.php?theme=".$edit_id);
        exit;
    }

    if(isset($_SESSION['th_msg'])){
        $client_lang['th_custom'] = $_SESSION['th_msg'];
        unset($_SESSION['th_msg']);
    }

    $t = theme_get($mysqli, 0, $edit_id);
    $customized = theme_is_customized($mysqli, 0, $edit_id);
    $is_active = ($active_theme === $edit_id);
    $branding_on = theme_reseller_allowed($mysqli);
    theme_reseller_migrate($mysqli);
    $branded = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_reseller_theme WHERE enabled = 1"));

    $state = array();
    foreach($catalog as $id => $th){
        $state[$id] = array('active' => ($active_theme === (string)$id), 'customized' => theme_is_customized($mysqli, 0, $id));
    }

    ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Pré-visualização</h5>
            <span class="badge <?php echo $is_active ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $is_active ? 'Em uso no app' : 'Não está em uso'; ?></span>
        </div>
        <?php if($is_model){ ?>
            <div class="d-flex align-items-center gap-3 mb-3 p-2 rounded" style="background:#f7f9fe;">
                <img src="assets/images/themes/<?php echo $catalog[$edit_id]['image']; ?>.png" alt="" style="width:120px;border-radius:8px;">
                <small class="text-muted">Modelo base: <b><?php echo htmlspecialchars($catalog[$edit_id]['name']); ?></b>. A pré-visualização abaixo mostra as cores e imagens que serão aplicadas por cima dele.</small>
            </div>
        <?php } ?>
    <?php $side_html = ob_get_clean();

    ob_start(); ?>
        <hr class="my-4">
        <?php if($is_model){ ?>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="customize_enabled" id="customize_enabled" value="1" <?php if($customized){ echo 'checked'; } ?>>
                <label class="form-check-label fw-semibold" for="customize_enabled">Aplicar esta personalização no modelo <?php echo htmlspecialchars($catalog[$edit_id]['name']); ?></label>
            </div>
        <?php } ?>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="activate_theme" id="activate_theme" value="1" <?php if($is_active){ echo 'checked disabled'; } ?>>
            <label class="form-check-label" for="activate_theme">Usar este modelo no app</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="reseller_branding" id="reseller_branding" value="1" <?php if($branding_on){ echo 'checked'; } ?>>
            <label class="form-check-label" for="reseller_branding">
                Permitir que revendedores usem a própria marca (Minha Marca)
                <small class="text-muted d-block"><?php echo (int)$branded['num']; ?> revendedor(es) com marca própria ativa.</small>
            </label>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="save_theme" class="btn btn-primary" style="min-width:140px;"><i class="ri-save-3-line"></i> Salvar</button>
            <button type="submit" name="reset_theme" class="btn btn-outline-secondary" formnovalidate onclick="return confirm('Voltar este modelo ao original (remove cores e imagens personalizadas)?');"><i class="ri-arrow-go-back-line"></i> Voltar ao original</button>
        </div>
    <?php $actions_html = ob_get_clean();
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

        <div class="card th-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-1">Modelos</h5>
                <p class="text-muted small mb-3">Escolha um modelo para usar no app e clique em <b>Personalizar</b> para trocar logo, cores, fundo e banner. Cada modelo guarda a sua própria personalização.</p>
                <?php theme_gallery_render($state, 'assets/images/themes/', 'settings_theme.php?theme=', '', $edit_id); ?>
            </div>
        </div>

        <div id="editor" class="d-flex align-items-center gap-2 mb-3">
            <h4 class="mb-0">Personalizar: <?php echo htmlspecialchars($catalog[$edit_id]['name']); ?></h4>
            <?php if($is_model){ ?><span class="badge <?php echo $customized ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo $customized ? 'Personalização ligada' : 'Original'; ?></span><?php } ?>
        </div>

        <form method="post" enctype="multipart/form-data" action="settings_theme.php?theme=<?php echo htmlspecialchars($edit_id); ?>">
            <?php theme_form_editor($t, array(
                'img_base' => 'images/',
                'app_name' => APP_NAME,
                'fallback_logo' => 'images/'.APP_LOGO,
                'side_html' => $side_html,
                'actions_html' => $actions_html,
                'allow_keep_colors' => $is_model,
            )); ?>
        </form>
    </div>
</main>

<?php theme_form_script('images/'.APP_LOGO); ?>
<?php include("includes/footer.php");?>
