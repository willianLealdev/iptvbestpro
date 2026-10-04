<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/theme_helper.php");
require_once(__DIR__."/../includes/theme_form.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
$scope = $rid;
theme_saved_migrate($mysqli);

$allowed = theme_reseller_allowed($mysqli);
$catalog = theme_catalog();
$row = theme_reseller_row($mysqli, $rid);
$active = theme_active($mysqli, $scope);
$default_name = $row['brand_name'] != '' ? $row['brand_name'] : $reseller['name'];

// O que está sendo editado: tema salvo (?saved=) ou novo a partir de um modelo (?base=)
$editing_saved = null;
if (isset($_GET['saved'])) $editing_saved = theme_saved_get($mysqli, $scope, (int)$_GET['saved']);
if ($editing_saved) {
    $base = (string)$editing_saved['base_theme'];
} else if (isset($_GET['base']) && isset($catalog[(string)$_GET['base']])) {
    $base = (string)$_GET['base'];
} else if ($active['saved_id'] > 0) {
    $editing_saved = theme_saved_get($mysqli, $scope, $active['saved_id']);
    $base = $active['base'];
} else {
    $base = $active['base'];
}
$current = $editing_saved ? $editing_saved['settings_arr'] : theme_defaults($base);
$self = 'theme.php';
$back = $editing_saved ? $self.'?saved='.$editing_saved['id'] : $self.'?base='.$base;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $allowed) {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
        rv_redirect($back);
    }
    if (isset($_POST['save_basic'])) {
        theme_reseller_set_basic($mysqli, $rid, !empty($_POST['enabled']), isset($_POST['brand_name']) ? $_POST['brand_name'] : '');
        rv_flash('success', !empty($_POST['enabled']) ? 'Sua marca está ativa no app dos seus clientes.' : 'Marca desligada. Seus clientes veem o visual padrão.');
        rv_redirect($back);
    }
    if (isset($_POST['use_base']) && isset($catalog[(string)$_POST['use_base']])) {
        theme_set_active($mysqli, $scope, $_POST['use_base'], 0);
        rv_flash('success', 'Modelo "'.$catalog[(string)$_POST['use_base']]['name'].'" (original) em uso para seus clientes.');
        rv_redirect($self.'?base='.$_POST['use_base']);
    }
    if (isset($_POST['use_saved'])) {
        $s = theme_saved_get($mysqli, $scope, (int)$_POST['use_saved']);
        if ($s) theme_set_active($mysqli, $scope, $s['base_theme'], $s['id']);
        rv_flash($s ? 'success' : 'error', $s ? 'Tema "'.$s['name'].'" em uso para seus clientes.' : 'Tema não encontrado.');
        rv_redirect($self.($s ? '?saved='.$s['id'] : ''));
    }
    if (isset($_POST['delete_saved'])) {
        $ok = theme_saved_delete($mysqli, $scope, (int)$_POST['delete_saved']);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Tema excluído.' : 'Tema não encontrado.');
        rv_redirect($self);
    }
    if (isset($_POST['save_theme']) || isset($_POST['save_as_new'])) {
        $name = isset($_POST['saved_name']) ? trim($_POST['saved_name']) : '';
        $as_new = isset($_POST['save_as_new']) || !$editing_saved;
        if ($name == '') { rv_flash('error', 'Dê um nome ao tema para salvar.'); rv_redirect($back); }
        if ($as_new && theme_saved_count($mysqli, $scope) >= THEME_MAX_SAVED) { rv_flash('error', 'Limite de '.THEME_MAX_SAVED.' temas salvos.'); rv_redirect($back); }
        $old_files = array($current['theme_logo'], $current['theme_bg_image'], $current['theme_banner']);
        $r = theme_process_form($current, $_POST, theme_file_prefix($scope), true);
        $id = theme_saved_save($mysqli, $scope, $as_new ? 0 : $editing_saved['id'], $name, $base, $r['settings']);
        if ($id && !$as_new) theme_cleanup_files($mysqli, $old_files);
        if ($id && !empty($_POST['activate_theme'])) theme_set_active($mysqli, $scope, $base, $id);
        if (!$id) rv_flash('error', 'Não foi possível salvar o tema.');
        else rv_flash($r['errors'] ? 'error' : 'success', ($r['errors'] ? implode(' ', $r['errors']).' ' : '').'Tema "'.$name.'" salvo em Meus temas.'.(!empty($_POST['activate_theme']) ? ' Ele já está em uso para seus clientes.' : ''));
        rv_redirect($self.($id ? '?saved='.$id : ''));
    }
}

$saved_list = theme_saved_list($mysqli, $scope);
$in_use = $editing_saved && (int)$active['saved_id'] === (int)$editing_saved['id'];

rv_header($mysqli, $reseller, 'Minha Marca', 'theme');
theme_form_styles(); ?>

<?php if (!$allowed) { ?>
    <div class="rv-card p-4">
        <div class="rv-empty"><i class="ri-palette-line"></i>A personalização de marca não está disponível no momento. Fale com o administrador.</div>
    </div>
<?php } else { ?>

<form method="post" class="rv-card p-4 mb-4">
    <?php echo rv_csrf_field(); ?>
    <div class="row g-3 align-items-end">
        <div class="col-lg-5">
            <h1 class="rv-title mb-1">Minha Marca</h1>
            <p class="rv-muted mb-0">Os clientes vinculados ao seu código <b><?php echo rv_e($reseller['code']); ?></b> veem o app com o tema que você escolher abaixo.</p>
        </div>
        <div class="col-lg-4">
            <label class="form-label fw-semibold">Nome da sua marca</label>
            <input type="text" name="brand_name" class="form-control" maxlength="60" value="<?php echo rv_e($row['brand_name']); ?>" placeholder="<?php echo rv_e($reseller['name']); ?>">
        </div>
        <div class="col-lg-3">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="enabled" id="enabled" value="1" <?php if ((int)$row['enabled'] == 1) { echo 'checked'; } ?>>
                <label class="form-check-label fw-semibold" for="enabled">Usar minha marca</label>
            </div>
            <button type="submit" name="save_basic" class="btn rv-btn-green w-100">Salvar</button>
        </div>
    </div>
    <small class="rv-muted d-block mt-2">O ícone e o nome do app na tela inicial do aparelho não mudam; tudo dentro do app fica com a sua marca.</small>
</form>

<div class="rv-card p-4 mb-4">
    <?php theme_gallery_render($saved_list, $active, '../assets/images/themes/', $self, rv_csrf_field(), $editing_saved ? 's'.$editing_saved['id'] : 'b'.$base, array('img_base' => '../images/', 'fallback_logo' => '../images/'.APP_LOGO, 'app_name' => $default_name)); ?>
</div>

<div id="editor" class="d-flex align-items-center gap-2 mb-3">
    <h2 class="rv-title fs-4 mb-0"><?php echo $editing_saved ? 'Editando: '.rv_e($editing_saved['name']) : 'Novo tema a partir de '.rv_e($catalog[$base]['name']); ?></h2>
</div>

<form method="post" enctype="multipart/form-data" action="<?php echo rv_e($back); ?>">
    <?php echo rv_csrf_field(); ?>
    <?php
        ob_start(); ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Pré-visualização · <?php echo rv_e($catalog[$base]['name']); ?></h5>
                <span class="badge <?php echo $in_use ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $in_use ? 'Em uso' : 'Não está em uso'; ?></span>
            </div>
        <?php $side_html = ob_get_clean();
        ob_start(); ?>
            <hr class="my-4">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="activate_theme" id="activate_theme" value="1" <?php if ($in_use || !$editing_saved) { echo 'checked'; } ?>>
                <label class="form-check-label" for="activate_theme">Usar este tema para meus clientes depois de salvar</label>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="save_theme" class="btn rv-btn-green" style="min-width:150px;"><i class="ri-save-3-line"></i> <?php echo $editing_saved ? 'Salvar alterações' : 'Salvar tema'; ?></button>
                <?php if ($editing_saved) { ?><button type="submit" name="save_as_new" class="btn btn-outline-primary"><i class="ri-file-copy-line"></i> Salvar como novo</button><?php } ?>
                <a href="<?php echo $self; ?>?base=<?php echo $base; ?>#editor" class="btn btn-outline-secondary"><i class="ri-arrow-go-back-line"></i> Começar do zero</a>
            </div>
        <?php $actions_html = ob_get_clean();

        theme_form_editor($current, array(
            'img_base' => '../images/',
            'app_name' => $default_name,
            'fallback_logo' => '../images/'.APP_LOGO,
            'side_html' => $side_html,
            'actions_html' => $actions_html,
            'allow_keep_colors' => $base !== THEME_BESTPRO_ID,
            'theme_id' => $base,
            'model_img_base' => '../assets/images/themes/',
            'model_name' => $catalog[$base]['name'],
            'saved_name' => $editing_saved ? $editing_saved['name'] : '',
        ));
    ?>
</form>
<?php } ?>

<?php
ob_start();
if ($allowed) theme_form_script('../images/'.APP_LOGO);
rv_footer(ob_get_clean());
