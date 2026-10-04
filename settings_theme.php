<?php $page_title="Personalizar Tema";
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

    function th_flash($ok, $msg){
        $_SESSION['th_msg'] = $msg;
        $_SESSION['msg'] = 'th_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }

    $current = theme_get($mysqli);

    if(isset($_POST['save_theme'])){
        $r = theme_process_form($current, $_POST, 'theme_');
        theme_save_admin($mysqli, $r['settings']);
        if(!empty($_POST['activate_theme'])){
            mysqli_query($mysqli, "UPDATE tbl_settings SET is_theme = '".THEME_BESTPRO_ID."' WHERE id = 1");
        }
        panel_config_set($mysqli, 'reseller_branding', !empty($_POST['reseller_branding']) ? '1' : '0');
        th_flash(!$r['errors'], $r['errors'] ? implode(' ', $r['errors']) : 'Tema salvo. Os aparelhos recebem o novo visual na próxima abertura do app.');
        header("Location:settings_theme.php");
        exit;
    }

    if(isset($_POST['reset_theme'])){
        foreach(array('theme_logo', 'theme_bg_image', 'theme_banner') as $key){ theme_delete_file($current[$key]); }
        theme_save_admin($mysqli, theme_defaults());
        th_flash(true, 'Tema restaurado para o padrão.');
        header("Location:settings_theme.php");
        exit;
    }

    if(isset($_SESSION['th_msg'])){
        $client_lang['th_custom'] = $_SESSION['th_msg'];
        unset($_SESSION['th_msg']);
    }

    $t = theme_get($mysqli);
    $is_active = ($settings_details['is_theme'] == THEME_BESTPRO_ID);
    $branding_on = theme_reseller_allowed($mysqli);
    theme_reseller_migrate($mysqli);
    $branded = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_reseller_theme WHERE enabled = 1"));

    ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Pré-visualização</h5>
            <span class="badge <?php echo $is_active ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $is_active ? 'Tema ativo no app' : 'Tema não ativo'; ?></span>
        </div>
    <?php $side_html = ob_get_clean();

    ob_start(); ?>
        <hr class="my-4">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="activate_theme" id="activate_theme" value="1" <?php if($is_active){ echo 'checked disabled'; } ?>>
            <label class="form-check-label" for="activate_theme">Usar este tema no app (BestPro)</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="reseller_branding" id="reseller_branding" value="1" <?php if($branding_on){ echo 'checked'; } ?>>
            <label class="form-check-label" for="reseller_branding">
                Permitir que revendedores usem a própria marca (Minha Marca)
                <small class="text-muted d-block"><?php echo (int)$branded['num']; ?> revendedor(es) com marca própria ativa.</small>
            </label>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="save_theme" class="btn btn-primary" style="min-width:140px;"><i class="ri-save-3-line"></i> Salvar tema</button>
            <button type="submit" name="reset_theme" class="btn btn-outline-secondary" formnovalidate onclick="return confirm('Restaurar as cores e remover as imagens do tema?');"><i class="ri-arrow-go-back-line"></i> Restaurar padrão</button>
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

        <?php if(!$is_active){ ?>
            <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
                <i class="ri-information-line fs-5"></i>
                <span>O tema <b>BestPro</b> ainda não é o tema ativo do app. Marque <b>"Usar este tema no app"</b> ao salvar, ou escolha <b>BestPro</b> em Settings → App UI.</span>
            </div>
        <?php } ?>

        <form method="post" enctype="multipart/form-data">
            <?php theme_form_editor($t, array(
                'img_base' => 'images/',
                'app_name' => APP_NAME,
                'fallback_logo' => 'images/'.APP_LOGO,
                'side_html' => $side_html,
                'actions_html' => $actions_html,
            )); ?>
        </form>
    </div>
</main>

<?php theme_form_script('images/'.APP_LOGO); ?>
<?php include("includes/footer.php");?>
