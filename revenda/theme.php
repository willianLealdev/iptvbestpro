<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/theme_helper.php");
require_once(__DIR__."/../includes/theme_form.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
theme_reseller_migrate($mysqli);

$allowed = theme_reseller_allowed($mysqli);
$row = theme_reseller_row($mysqli, $rid);
$current = theme_get($mysqli, $rid);
$catalog = theme_catalog();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $allowed) {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['save_brand'])) {
        $theme_id = isset($_POST['theme_id']) ? (string)$_POST['theme_id'] : THEME_BESTPRO_ID;
        $r = theme_process_form($current, $_POST, 'theme_r'.$rid.'_');
        theme_reseller_save_row($mysqli, $rid, !empty($_POST['enabled']), $theme_id, isset($_POST['brand_name']) ? $_POST['brand_name'] : '', $r['settings']);
        rv_flash($r['errors'] ? 'error' : 'success', $r['errors'] ? implode(' ', $r['errors']) : 'Marca salva. Seus clientes veem o novo visual na próxima abertura do app.');
    } else if (isset($_POST['reset_brand'])) {
        foreach (array('theme_logo', 'theme_bg_image', 'theme_banner') as $key) theme_delete_file($current[$key]);
        theme_reseller_save_row($mysqli, $rid, false, THEME_BESTPRO_ID, '', theme_defaults());
        rv_flash('success', 'Marca removida. Seus clientes voltam a ver o visual padrão.');
    }
    rv_redirect('theme.php');
}

rv_header($mysqli, $reseller, 'Minha Marca', 'theme');
theme_form_styles(); ?>

<style>
    .bt-themes { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 14px; }
    .bt-theme { position: relative; border: 2px solid #eef0f4; border-radius: 12px; overflow: hidden; cursor: pointer; background: #fff; display: block; margin: 0; }
    .bt-theme input { position: absolute; opacity: 0; }
    .bt-theme img, .bt-theme .bt-custom { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; background: #f3f4f6; }
    .bt-theme .bt-custom { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 4px; color: #fff; font-weight: 700;
                           background: linear-gradient(135deg, <?php echo rv_e($current['theme_primary']); ?>, <?php echo rv_e($current['theme_accent']); ?>); }
    .bt-theme span.bt-name { display: block; padding: 8px 10px; font-size: 13px; font-weight: 600; }
    .bt-theme:has(input:checked) { border-color: #3b6cf6; box-shadow: 0 0 0 3px rgba(59,108,246,.18); }
    .bt-theme:has(input:checked)::after { content: '\2713'; position: absolute; top: 8px; right: 8px; width: 24px; height: 24px; border-radius: 50%; background: #3b6cf6; color: #fff; font-size: 14px; display: flex; align-items: center; justify-content: center; }
</style>

<?php if (!$allowed) { ?>
    <div class="rv-card p-4">
        <div class="rv-empty"><i class="ri-palette-line"></i>A personalização de marca não está disponível no momento. Fale com o administrador.</div>
    </div>
<?php } else { ?>
<form method="post" enctype="multipart/form-data">
    <?php echo rv_csrf_field(); ?>

    <div class="rv-card p-4 mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <h1 class="rv-title mb-1">Minha Marca</h1>
                <p class="rv-muted mb-0">Deixe o app com a cara da sua marca. Os clientes vinculados ao seu código <b><?php echo rv_e($reseller['code']); ?></b> veem este visual.</p>
            </div>
            <div class="col-lg-5">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" name="enabled" id="enabled" value="1" <?php if ((int)$row['enabled'] == 1) { echo 'checked'; } ?>>
                    <label class="form-check-label fs-6 fw-semibold" for="enabled">Usar minha marca no app dos meus clientes</label>
                </div>
            </div>
        </div>
        <hr>
        <label class="form-label fw-semibold">Nome da marca</label>
        <input type="text" name="brand_name" id="brand_name" class="form-control" maxlength="60" value="<?php echo rv_e($row['brand_name']); ?>" placeholder="<?php echo rv_e($reseller['name']); ?>">
        <small class="rv-muted">Aparece no app ao lado do logo. O ícone e o nome do app na tela inicial do aparelho não mudam.</small>
    </div>

    <div class="rv-card p-4 mb-4">
        <h2 class="rv-title fs-5 mb-3">Escolha o tema</h2>
        <div class="bt-themes">
            <?php foreach ($catalog as $id => $th) { ?>
                <label class="bt-theme">
                    <input type="radio" name="theme_id" value="<?php echo $id; ?>" <?php if ((string)$row['theme_id'] === (string)$id) { echo 'checked'; } ?>>
                    <?php if ($th['image'] != '') { ?>
                        <img src="../assets/images/themes/<?php echo $th['image']; ?>.png" alt="" loading="lazy">
                    <?php } else { ?>
                        <div class="bt-custom"><i class="ri-palette-line fs-3"></i>Personalizado</div>
                    <?php } ?>
                    <span class="bt-name"><?php echo rv_e($th['name']); ?></span>
                </label>
            <?php } ?>
        </div>
        <p class="rv-muted small mt-3 mb-0">Nos temas prontos, o app usa o seu <b>nome de marca</b> e o seu <b>logo</b>. No <b>Personalizado</b>, você também escolhe cores, fundo e banner.</p>
    </div>

    <?php
        ob_start(); ?>
            <h5 class="mb-3">Pré-visualização</h5>
        <?php $side_html = ob_get_clean();
        ob_start(); ?>
            <hr class="my-4">
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="save_brand" class="btn rv-btn-green" style="min-width:150px;"><i class="ri-save-3-line"></i> Salvar minha marca</button>
                <button type="submit" name="reset_brand" class="btn btn-outline-secondary" formnovalidate onclick="return confirm('Remover sua marca e voltar ao visual padrão?');"><i class="ri-arrow-go-back-line"></i> Remover minha marca</button>
            </div>
        <?php $actions_html = ob_get_clean();

        $brand_preview = $row['brand_name'] != '' ? $row['brand_name'] : $reseller['name'];
        theme_form_editor($current, array(
            'img_base' => '../images/',
            'app_name' => $brand_preview,
            'fallback_logo' => '../images/'.APP_LOGO,
            'side_html' => $side_html,
            'actions_html' => $actions_html,
        ));
    ?>
</form>
<?php } ?>

<?php
ob_start();
if ($allowed) theme_form_script('../images/'.APP_LOGO);
rv_footer(ob_get_clean());
