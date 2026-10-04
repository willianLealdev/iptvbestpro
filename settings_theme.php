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
    theme_saved_migrate($mysqli);
    $catalog = theme_catalog();
    $scope = 0;

    function th_flash($ok, $msg){
        $_SESSION['th_msg'] = $msg;
        $_SESSION['msg'] = 'th_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }

    $active = theme_active($mysqli, $scope);

    // O que está sendo editado: um tema salvo (?saved=) ou um novo tema a partir de um modelo (?base=)
    $editing_saved = null;
    if(isset($_GET['saved'])){ $editing_saved = theme_saved_get($mysqli, $scope, (int)$_GET['saved']); }
    if($editing_saved){
        $base = (string)$editing_saved['base_theme'];
        $current = $editing_saved['settings_arr'];
    } else {
        if(isset($_GET['base']) && isset($catalog[(string)$_GET['base']])){ $base = (string)$_GET['base']; }
        else if($active['saved_id'] > 0){
            $editing_saved = theme_saved_get($mysqli, $scope, $active['saved_id']);
            $base = $active['base'];
        } else { $base = $active['base']; }
        $current = $editing_saved ? $editing_saved['settings_arr'] : theme_defaults($base);
    }
    $self = 'settings_theme.php';
    $back = $editing_saved ? $self.'?saved='.$editing_saved['id'] : $self.'?base='.$base;

    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        if(isset($_POST['use_base']) && isset($catalog[(string)$_POST['use_base']])){
            theme_set_active($mysqli, $scope, $_POST['use_base'], 0);
            th_flash(true, 'Modelo "'.$catalog[(string)$_POST['use_base']]['name'].'" (original) em uso no app.');
            header("Location:".$self."?base=".$_POST['use_base']);
            exit;
        }
        if(isset($_POST['use_saved'])){
            $s = theme_saved_get($mysqli, $scope, (int)$_POST['use_saved']);
            if($s){ theme_set_active($mysqli, $scope, $s['base_theme'], $s['id']); }
            th_flash((bool)$s, $s ? 'Tema "'.$s['name'].'" em uso no app.' : 'Tema não encontrado.');
            header("Location:".$self.($s ? "?saved=".$s['id'] : ''));
            exit;
        }
        if(isset($_POST['delete_saved'])){
            $ok = theme_saved_delete($mysqli, $scope, (int)$_POST['delete_saved']);
            th_flash($ok, $ok ? 'Tema excluído.' : 'Tema não encontrado.');
            header("Location:".$self);
            exit;
        }
        if(isset($_POST['save_branding'])){
            panel_config_set($mysqli, 'reseller_branding', !empty($_POST['reseller_branding']) ? '1' : '0');
            th_flash(true, !empty($_POST['reseller_branding']) ? 'Revendedores podem usar a própria marca.' : 'Marca própria dos revendedores desativada.');
            header("Location:".$back);
            exit;
        }
        if(isset($_POST['save_theme']) || isset($_POST['save_as_new'])){
            $name = isset($_POST['saved_name']) ? trim($_POST['saved_name']) : '';
            $as_new = isset($_POST['save_as_new']) || !$editing_saved;
            if($name == ''){
                th_flash(false, 'Dê um nome ao tema para salvar.');
                header("Location:".$back);
                exit;
            }
            if($as_new && theme_saved_count($mysqli, $scope) >= THEME_MAX_SAVED){
                th_flash(false, 'Limite de '.THEME_MAX_SAVED.' temas salvos. Exclua algum para criar outro.');
                header("Location:".$back);
                exit;
            }
            $old_files = array($current['theme_logo'], $current['theme_bg_image'], $current['theme_banner']);
            $r = theme_process_form($current, $_POST, theme_file_prefix($scope), true, $base);
            $id = theme_saved_save($mysqli, $scope, $as_new ? 0 : $editing_saved['id'], $name, $base, $r['settings']);
            if($id && !$as_new){ theme_cleanup_files($mysqli, $old_files); }
            if($id && !empty($_POST['activate_theme'])){ theme_set_active($mysqli, $scope, $base, $id); }
            if(!$id){
                th_flash(false, 'Não foi possível salvar o tema.');
            } else {
                th_flash(!$r['errors'], ($r['errors'] ? implode(' ', $r['errors']).' ' : '').'Tema "'.$name.'" salvo em Meus temas.'.(!empty($_POST['activate_theme']) ? ' Ele já está em uso no app.' : ''));
            }
            header("Location:".$self.($id ? "?saved=".$id : ''));
            exit;
        }
    }

    if(isset($_SESSION['th_msg'])){
        $client_lang['th_custom'] = $_SESSION['th_msg'];
        unset($_SESSION['th_msg']);
    }

    $saved_list = theme_saved_list($mysqli, $scope);
    require_once(__DIR__.'/includes/poster_helper.php');
$movie_poster = posters_preview_image($mysqli, 0);
    $movie_hero = $movie_poster != '' ? 'images/'.$movie_poster : 'assets/images/themes/'.'MovieUI.png';
    
    $in_use = $editing_saved && (int)$active['saved_id'] === (int)$editing_saved['id'];
    $branding_on = theme_reseller_allowed($mysqli);
    $branded = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_reseller_theme WHERE enabled = 1"));

    ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Pré-visualização · <?php echo htmlspecialchars($catalog[$base]['name']); ?></h5>
            <span class="badge <?php echo $in_use ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $in_use ? 'Em uso no app' : 'Não está em uso'; ?></span>
        </div>
    <?php $side_html = ob_get_clean();

    ob_start(); ?>
        <hr class="my-4">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="activate_theme" id="activate_theme" value="1" <?php if($in_use){ echo 'checked'; } ?>>
            <label class="form-check-label" for="activate_theme">Usar este tema no app depois de salvar</label>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="save_theme" class="btn btn-primary" style="min-width:140px;"><i class="ri-save-3-line"></i> <?php echo $editing_saved ? 'Salvar alterações' : 'Salvar tema'; ?></button>
            <?php if($editing_saved){ ?>
                <button type="submit" name="save_as_new" class="btn btn-outline-primary"><i class="ri-file-copy-line"></i> Salvar como novo</button>
            <?php } ?>
            <a href="<?php echo $self; ?>?base=<?php echo $base; ?>#editor" class="btn btn-outline-secondary"><i class="ri-arrow-go-back-line"></i> Começar do zero</a>
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
                <?php theme_gallery_render($saved_list, $active, 'assets/images/themes/', $self, '', $editing_saved ? 's'.$editing_saved['id'] : 'b'.$base, array('img_base' => 'images/', 'fallback_logo' => brand_logo_url(), 'app_name' => APP_NAME, 'movie_hero' => $movie_hero)); ?>
            </div>
        </div>

        <form method="post" class="card th-card mb-4">
            <div class="card-body p-3 d-flex flex-wrap align-items-center gap-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="reseller_branding" id="reseller_branding" value="1" <?php if($branding_on){ echo 'checked'; } ?>>
                    <label class="form-check-label" for="reseller_branding">Permitir que revendedores usem a própria marca (Minha Marca) <small class="text-muted">· <?php echo (int)$branded['num']; ?> com marca ativa</small></label>
                </div>
                <button type="submit" name="save_branding" class="btn btn-sm btn-outline-primary ms-auto">Salvar</button>
            </div>
        </form>

        <div id="editor" class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h4 class="mb-0"><?php echo $editing_saved ? 'Editando: '.htmlspecialchars($editing_saved['name']) : 'Novo tema a partir de '.htmlspecialchars($catalog[$base]['name']); ?></h4>
        </div>

        <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($back); ?>">
            <?php theme_form_editor($current, array(
                'img_base' => 'images/',
                'app_name' => APP_NAME,
                'fallback_logo' => brand_logo_url(),
                'side_html' => $side_html,
                'actions_html' => $actions_html,
                'theme_id' => $base,
                'movie_hero' => $movie_hero,
                'posters_url' => 'manage_movie_ui.php',
                'model_name' => $catalog[$base]['name'],
                'saved_name' => $editing_saved ? $editing_saved['name'] : '',
            )); ?>
        </form>
    </div>
</main>

<?php theme_form_script(brand_logo_url()); ?>
<?php include("includes/footer.php");?>
