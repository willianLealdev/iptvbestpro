<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/theme_helper.php");
require_once(__DIR__."/../includes/theme_form.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
theme_reseller_migrate($mysqli);
theme_custom_migrate($mysqli);

$allowed = theme_reseller_allowed($mysqli);
$catalog = theme_catalog();
$row = theme_reseller_row($mysqli, $rid);
$active_theme = isset($catalog[(string)$row['theme_id']]) ? (string)$row['theme_id'] : THEME_BESTPRO_ID;
$edit_id = (isset($_GET['theme']) && isset($catalog[(string)$_GET['theme']])) ? (string)$_GET['theme'] : $active_theme;
$is_model = $edit_id !== THEME_BESTPRO_ID;
$current = theme_get($mysqli, $rid, $edit_id);

// Configurações do BestPro do revendedor (guardadas junto da marca)
function rv_bestpro_settings($mysqli, $rid){ return theme_get($mysqli, $rid, THEME_BESTPRO_ID); }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $allowed) {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
        rv_redirect('theme.php?theme='.$edit_id);
    }
    if (isset($_POST['save_basic'])) {
        theme_reseller_save_row($mysqli, $rid, !empty($_POST['enabled']), $active_theme, isset($_POST['brand_name']) ? $_POST['brand_name'] : '', rv_bestpro_settings($mysqli, $rid));
        rv_flash('success', !empty($_POST['enabled']) ? 'Sua marca está ativa no app dos seus clientes.' : 'Marca desligada. Seus clientes veem o visual padrão.');
    } else if (isset($_POST['use_theme']) && isset($catalog[(string)$_POST['use_theme']])) {
        $use = (string)$_POST['use_theme'];
        theme_reseller_save_row($mysqli, $rid, (int)$row['enabled'] == 1, $use, $row['brand_name'], rv_bestpro_settings($mysqli, $rid));
        rv_flash('success', 'Modelo "'.$catalog[$use]['name'].'" selecionado.');
        rv_redirect('theme.php?theme='.$use);
    } else if (isset($_POST['save_model'])) {
        $r = theme_process_form($current, $_POST, theme_file_prefix($rid, $edit_id));
        if ($is_model) {
            theme_custom_save($mysqli, $rid, $edit_id, !empty($_POST['customize_enabled']), $r['settings']);
        } else {
            theme_reseller_save_row($mysqli, $rid, (int)$row['enabled'] == 1, $active_theme, $row['brand_name'], $r['settings']);
        }
        if (!empty($_POST['activate_theme'])) {
            theme_reseller_save_row($mysqli, $rid, (int)$row['enabled'] == 1, $edit_id, $row['brand_name'], rv_bestpro_settings($mysqli, $rid));
        }
        rv_flash($r['errors'] ? 'error' : 'success', $r['errors'] ? implode(' ', $r['errors']) : 'Personalização do modelo "'.$catalog[$edit_id]['name'].'" salva.');
    } else if (isset($_POST['reset_model'])) {
        foreach (array('theme_logo', 'theme_bg_image', 'theme_banner') as $key) theme_delete_file($current[$key]);
        if ($is_model) theme_custom_save($mysqli, $rid, $edit_id, false, theme_defaults($edit_id));
        else theme_reseller_save_row($mysqli, $rid, (int)$row['enabled'] == 1, $active_theme, $row['brand_name'], theme_defaults(THEME_BESTPRO_ID));
        rv_flash('success', 'Modelo "'.$catalog[$edit_id]['name'].'" voltou ao original.');
    }
    rv_redirect('theme.php?theme='.$edit_id);
}

$customized = theme_is_customized($mysqli, $rid, $edit_id);
$state = array();
foreach ($catalog as $id => $th) $state[$id] = array('active' => $active_theme === (string)$id, 'customized' => theme_is_customized($mysqli, $rid, $id));

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
            <p class="rv-muted mb-0">Os clientes vinculados ao seu código <b><?php echo rv_e($reseller['code']); ?></b> veem o app com a sua marca.</p>
        </div>
        <div class="col-lg-4">
            <label class="form-label fw-semibold">Nome da marca</label>
            <input type="text" name="brand_name" id="brand_name" class="form-control" maxlength="60" value="<?php echo rv_e($row['brand_name']); ?>" placeholder="<?php echo rv_e($reseller['name']); ?>">
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
    <h2 class="rv-title fs-5 mb-1">Modelos</h2>
    <p class="rv-muted small mb-3">Escolha um modelo para seus clientes e clique em <b>Personalizar</b> para colocar seu logo, cores, fundo e banner.</p>
    <?php theme_gallery_render($state, '../assets/images/themes/', 'theme.php?theme=', rv_csrf_field(), $edit_id); ?>
</div>

<div id="editor" class="d-flex align-items-center gap-2 mb-3">
    <h2 class="rv-title fs-4 mb-0">Personalizar: <?php echo rv_e($catalog[$edit_id]['name']); ?></h2>
    <?php if ($is_model) { ?><span class="badge <?php echo $customized ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo $customized ? 'Personalização ligada' : 'Original'; ?></span><?php } ?>
</div>

<form method="post" enctype="multipart/form-data" action="theme.php?theme=<?php echo rv_e($edit_id); ?>">
    <?php echo rv_csrf_field(); ?>
    <?php
        ob_start(); ?>
            <h5 class="mb-3">Pré-visualização</h5>
            <?php if ($is_model) { ?>
                <div class="d-flex align-items-center gap-3 mb-3 p-2 rounded" style="background:#f7f9fe;">
                    <img src="../assets/images/themes/<?php echo $catalog[$edit_id]['image']; ?>.png" alt="" style="width:120px;border-radius:8px;">
                    <small class="rv-muted">Modelo base: <b><?php echo rv_e($catalog[$edit_id]['name']); ?></b>. A pré-visualização mostra o que será aplicado por cima dele.</small>
                </div>
            <?php } ?>
        <?php $side_html = ob_get_clean();
        ob_start(); ?>
            <hr class="my-4">
            <?php if ($is_model) { ?>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="customize_enabled" id="customize_enabled" value="1" <?php if ($customized || !$state[$edit_id]['customized']) { echo 'checked'; } ?>>
                    <label class="form-check-label fw-semibold" for="customize_enabled">Aplicar esta personalização no modelo</label>
                </div>
            <?php } ?>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="activate_theme" id="activate_theme" value="1" <?php if ($active_theme === $edit_id) { echo 'checked disabled'; } ?>>
                <label class="form-check-label" for="activate_theme">Usar este modelo para meus clientes</label>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="save_model" class="btn rv-btn-green" style="min-width:150px;"><i class="ri-save-3-line"></i> Salvar</button>
                <button type="submit" name="reset_model" class="btn btn-outline-secondary" formnovalidate onclick="return confirm('Voltar este modelo ao original?');"><i class="ri-arrow-go-back-line"></i> Voltar ao original</button>
            </div>
        <?php $actions_html = ob_get_clean();

        theme_form_editor($current, array(
            'img_base' => '../images/',
            'app_name' => $row['brand_name'] != '' ? $row['brand_name'] : $reseller['name'],
            'fallback_logo' => '../images/'.APP_LOGO,
            'side_html' => $side_html,
            'actions_html' => $actions_html,
            'allow_keep_colors' => $is_model,
        ));
    ?>
</form>
<?php } ?>

<?php
ob_start();
if ($allowed) theme_form_script('../images/'.APP_LOGO);
rv_footer(ob_get_clean());
