<?php
require_once(__DIR__.'/theme_preview.php');

/**
 * Galeria ("Meus temas" e "Modelos") e editor de tema por modelo.
 * Usado em settings_theme.php (admin) e revenda/theme.php (revendedor).
 */

/**
 * $mini = array('img_base', 'fallback_logo', 'app_name', 'movie_hero') para as miniaturas dos temas salvos.
 */
function theme_gallery_render($saved, $active, $img_prefix, $self_url, $hidden_html, $editing, $mini = array()){ ?>
<style>
    .tg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .tg-card { border: 2px solid #eef0f4; border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
    .tg-card.editing { border-color: #3b6cf6; box-shadow: 0 0 0 3px rgba(59,108,246,.15); }
    .tg-card.in-use { border-color: #16a34a; }
    .tg-thumb { position: relative; aspect-ratio: 16 / 9; background: #f3f4f6; overflow: hidden; }
    .tg-thumb img.shot { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tg-thumb .tg-custom { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #fff; font-weight: 700; background: linear-gradient(135deg, #3B6CF6, #22C55E); }
    .tg-badges { position: absolute; top: 8px; left: 8px; display: flex; gap: 4px; flex-wrap: wrap; z-index: 2; }
    .tg-badges span { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
    .tg-on { background: #16a34a; color: #fff; }
    .tg-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .tg-name { font-weight: 600; font-size: 14px; }
    .tg-sub { font-size: 12px; color: #6b7a99; }
    .tg-actions { display: flex; gap: 6px; margin-top: auto; flex-wrap: wrap; }
    .tg-actions form { display: flex; flex: 1; }
    .tg-actions .btn { flex: 1; font-size: 13px; padding: 5px 6px; white-space: nowrap; }
    .tg-title { font-size: 16px; font-weight: 700; margin: 0 0 4px; }
</style>

<div class="tg-title">Meus temas</div>
<p class="text-muted small mb-3">Personalizações salvas com nome. Clique em <b>Usar</b> para aplicar no app.</p>
<?php if ($saved) { $cat = theme_catalog(); ?>
    <div class="tg-grid mb-4">
        <?php foreach ($saved as $s) {
            $base = isset($cat[$s['base_theme']]) ? $cat[$s['base_theme']] : $cat['1'];
            $in_use = (int)$active['saved_id'] === (int)$s['id']; ?>
            <div class="tg-card <?php echo $in_use ? 'in-use' : ''; ?> <?php echo (string)$editing === 's'.$s['id'] ? 'editing' : ''; ?>">
                <div class="tg-thumb">
                    <?php theme_mini_preview($s['settings_arr'], $s['base_theme'], $mini['img_base'], $mini['fallback_logo'], $mini['app_name'], $mini['movie_hero']); ?>
                    <div class="tg-badges"><?php if ($in_use) { ?><span class="tg-on">Em uso</span><?php } ?></div>
                </div>
                <div class="tg-body">
                    <div class="tg-name"><?php echo htmlspecialchars($s['name']); ?></div>
                    <div class="tg-sub">Modelo: <?php echo htmlspecialchars($base['name']); ?> · <?php echo date('d/m/Y', $s['updated_at']); ?></div>
                    <div class="tg-actions">
                        <?php if (!$in_use) { ?>
                            <form method="post"><?php echo $hidden_html; ?><input type="hidden" name="use_saved" value="<?php echo (int)$s['id']; ?>"><button type="submit" class="btn btn-success">Usar</button></form>
                        <?php } ?>
                        <a href="<?php echo htmlspecialchars($self_url.'?saved='.$s['id']); ?>#editor" class="btn btn-primary"><i class="ri-pencil-line"></i> Editar</a>
                        <form method="post" onsubmit="return confirm('Excluir o tema salvo? Esta ação não pode ser desfeita.');" style="flex:0 0 auto;"><?php echo $hidden_html; ?><input type="hidden" name="delete_saved" value="<?php echo (int)$s['id']; ?>"><button type="submit" class="btn btn-outline-danger" title="Excluir"><i class="ri-delete-bin-line"></i></button></form>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } else { ?>
    <div class="p-3 mb-4 rounded text-muted small" style="background:#f7f9fe;">Você ainda não salvou nenhum tema. Escolha um modelo abaixo e clique em <b>Personalizar</b>.</div>
<?php } ?>

<div class="tg-title">Modelos</div>
<p class="text-muted small mb-3">Use um modelo como ele é, ou clique em <b>Personalizar</b> para criar um tema seu a partir dele.</p>
<div class="tg-grid">
    <?php foreach (theme_catalog() as $id => $th) {
        $in_use = (string)$active['base'] === (string)$id && (int)$active['saved_id'] === 0; ?>
        <div class="tg-card <?php echo $in_use ? 'in-use' : ''; ?> <?php echo (string)$editing === 'b'.$id ? 'editing' : ''; ?>">
            <div class="tg-thumb">
                <?php if ($th['image'] != '') { ?><img class="shot" src="<?php echo htmlspecialchars($img_prefix.$th['image']); ?>.png" alt="" loading="lazy">
                <?php } else { ?><div class="tg-custom"><i class="ri-palette-line fs-3"></i>BestPro</div><?php } ?>
                <div class="tg-badges"><?php if ($in_use) { ?><span class="tg-on">Em uso (original)</span><?php } ?></div>
            </div>
            <div class="tg-body">
                <div class="tg-name"><?php echo htmlspecialchars($th['name']); ?></div>
                <div class="tg-actions">
                    <?php if (!$in_use) { ?>
                        <form method="post"><?php echo $hidden_html; ?><input type="hidden" name="use_base" value="<?php echo $id; ?>"><button type="submit" class="btn btn-outline-success">Usar original</button></form>
                    <?php } ?>
                    <a href="<?php echo htmlspecialchars($self_url.'?base='.$id); ?>#editor" class="btn btn-primary"><i class="ri-palette-line"></i> Personalizar</a>
                </div>
            </div>
        </div>
    <?php } ?>
</div>
<?php }

function theme_form_styles(){ theme_preview_styles(); ?>
<style>
    .th-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); background: #fff; }
    .th-color { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .th-color input[type=color] { width: 46px; height: 38px; border: 1px solid #dfe3ea; border-radius: 8px; padding: 2px; background: #fff; cursor: pointer; flex: 0 0 46px; }
    .th-color input[type=text] { width: 110px; font-family: monospace; text-transform: uppercase; }
    .th-color label { flex: 1 1 auto; margin: 0; font-weight: 500; font-size: 14px; }
    .th-presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .th-preset { border: 1px solid #e5e7eb; background: #fff; border-radius: 20px; padding: 5px 12px 5px 6px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
    .th-preset:hover { border-color: #3b6cf6; }
    .th-preset span.sw { display: inline-flex; }
    .th-preset span.sw i { width: 14px; height: 14px; border-radius: 50%; display: inline-block; margin-right: -4px; border: 2px solid #fff; }
    .th-img { border: 1px dashed #d6dbe4; border-radius: 10px; padding: 12px; margin-bottom: 12px; }
    .th-img img { max-height: 70px; max-width: 100%; border-radius: 6px; display: block; margin-bottom: 8px; background: #f3f4f6; }
    .th-group-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; }
</style>
<?php }

/**
 * $opts: img_base, app_name, fallback_logo, side_html, actions_html, theme_id, model_name, saved_name,
 *        movie_hero (pôster do MovieUI), posters_url (link para gerenciar pôsteres, vazio para revendedor).
 */
function theme_form_editor($t, $opts){
    $img_base = $opts['img_base'];
    $tid = (string)$opts['theme_id'];
    $schema = theme_model_schema($tid);
    $t = array_merge(theme_defaults($tid), (array)$t);
    $logo_url = $t['theme_logo'] != '' ? $img_base.$t['theme_logo'] : $opts['fallback_logo'];
    $default_name = $opts['app_name'];
    $shown_name = $t['theme_brand_text'] != '' ? $t['theme_brand_text'] : $default_name;

    // Agrupa os campos da ficha
    $groups = array();
    foreach ($schema as $key => $f) { $groups[$f['group']][$key] = $f; }
    ?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card th-card mb-4">
            <div class="card-body p-4">
                <div class="th-group-title">Identificação</div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome do tema <span class="text-danger">*</span></label>
                    <input type="text" name="saved_name" class="form-control" maxlength="60" required value="<?php echo htmlspecialchars($opts['saved_name']); ?>" placeholder="Ex.: <?php echo htmlspecialchars($opts['model_name']); ?> da minha marca">
                    <small class="text-muted">Nome para encontrar este tema em "Meus temas". Não aparece no app.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Nome exibido no app</label>
                    <input type="text" name="theme_brand_text" id="theme_brand_text" class="form-control" maxlength="40" value="<?php echo htmlspecialchars($t['theme_brand_text']); ?>" placeholder="<?php echo htmlspecialchars($default_name); ?>">
                    <small class="text-muted">Aparece ao lado do logo no topo. Vazio = "<?php echo htmlspecialchars($default_name); ?>".</small>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="theme_show_name" id="theme_show_name" value="1" <?php if ($t['theme_show_name'] == '1') { echo 'checked'; } ?>>
                    <label class="form-check-label" for="theme_show_name">Mostrar o nome ao lado do logo <small class="text-muted">(desligue para mostrar só o logo)</small></label>
                </div>
                <?php theme_form_image_field('theme_logo', 'Logo', '<b>Tamanho ideal: 512 × 512 px</b> (quadrado), PNG. Mínimo 256 × 256 px. Deixe uma pequena margem em volta do desenho. Vazio = logo padrão.', $t, $img_base); ?>
            </div>
        </div>

        <?php foreach ($groups as $group => $fields) { ?>
            <div class="card th-card mb-4">
                <div class="card-body p-4">
                    <div class="th-group-title"><?php echo htmlspecialchars($group); ?></div>
                    <?php if ($tid === THEME_BESTPRO_ID && $group == 'Cores') { ?>
                        <div class="th-presets">
                            <?php foreach (theme_presets() as $name => $c) { ?>
                                <button type="button" class="th-preset" data-preset='<?php echo json_encode($c); ?>'>
                                    <span class="sw"><?php foreach (array_slice($c, 0, 3) as $hex) { ?><i style="background:<?php echo $hex; ?>"></i><?php } ?></span>
                                    <?php echo htmlspecialchars($name); ?>
                                </button>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php foreach ($fields as $key => $f) {
                        if ($f['type'] == 'color') { ?>
                            <div class="th-color">
                                <input type="color" value="<?php echo htmlspecialchars($t[$key]); ?>" data-picker-for="<?php echo $key; ?>">
                                <label for="<?php echo $key; ?>"><?php echo htmlspecialchars($f['label']); ?></label>
                                <input type="text" name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($t[$key]); ?>" maxlength="7" required data-field="<?php echo $key; ?>" data-default="<?php echo htmlspecialchars($f['default']); ?>">
                            </div>
                        <?php } else if ($f['type'] == 'toggle') { ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="<?php echo $key; ?>" id="<?php echo $key; ?>" value="1" data-toggle="<?php echo $key; ?>" <?php if ($t[$key] == '1') { echo 'checked'; } ?>>
                                <label class="form-check-label" for="<?php echo $key; ?>"><?php echo htmlspecialchars($f['label']); ?></label>
                            </div>
                        <?php } else if ($f['type'] == 'range') { ?>
                            <label class="form-label" for="<?php echo $key; ?>"><?php echo htmlspecialchars($f['label']); ?>: <b data-range-val="<?php echo $key; ?>"><?php echo (int)$t[$key].$f['unit']; ?></b></label>
                            <input type="range" class="form-range mb-2" name="<?php echo $key; ?>" id="<?php echo $key; ?>" min="<?php echo (int)$f['min']; ?>" max="<?php echo (int)$f['max']; ?>" value="<?php echo (int)$t[$key]; ?>" data-range="<?php echo $key; ?>" data-unit="<?php echo htmlspecialchars($f['unit']); ?>">
                        <?php } else if ($f['type'] == 'image') {
                            theme_form_image_field($key, $f['label'], isset($f['help']) ? $f['help'] : '', $t, $img_base);
                        } else if ($f['type'] == 'info') { ?>
                            <div class="p-3 rounded small mb-2" style="background:#f7f9fe;">
                                <?php echo $f['help']; ?>
                                <?php if (!empty($opts['posters_url'])) { ?>
                                    <div class="mt-2"><a href="<?php echo htmlspecialchars($opts['posters_url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="ri-image-line"></i> Gerenciar pôsteres</a></div>
                                <?php } else { ?>
                                    <div class="mt-2 text-muted">Os pôsteres são definidos pelo administrador.</div>
                                <?php } ?>
                            </div>
                        <?php }
                    } ?>
                </div>
            </div>
        <?php } ?>

        <button type="button" class="btn btn-outline-secondary w-100" id="th_restore"><i class="ri-arrow-go-back-line"></i> Restaurar as cores originais do modelo</button>
    </div>

    <div class="col-xl-7">
        <div class="card th-card" style="position: sticky; top: 90px;">
            <div class="card-body p-4">
                <?php echo isset($opts['side_html']) ? $opts['side_html'] : ''; ?>
                <div data-default-name="<?php echo htmlspecialchars($default_name); ?>" id="tv_wrap">
                    <?php echo theme_preview_box($tid, $t, $logo_url, $shown_name, $img_base, $opts['movie_hero']); ?>
                </div>
                <p class="text-muted small mt-3 mb-0">A pré-visualização reproduz a tela inicial deste modelo no app. Pequenas diferenças de tamanho podem ocorrer conforme o aparelho.</p>
                <?php echo isset($opts['actions_html']) ? $opts['actions_html'] : ''; ?>
            </div>
        </div>
    </div>
</div>
<?php }

function theme_form_image_field($key, $label, $help, $t, $img_base){ ?>
    <div class="th-img">
        <div class="fw-semibold"><?php echo htmlspecialchars($label); ?></div>
        <small class="text-muted d-block mb-2"><?php echo $help; ?> Aceita PNG, JPG ou WEBP até 5 MB.</small>
        <?php if (!empty($t[$key])) { ?>
            <img src="<?php echo htmlspecialchars($img_base.$t[$key]); ?>" alt="">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="remove_<?php echo $key; ?>" id="remove_<?php echo $key; ?>" value="1" data-remove-for="<?php echo $key; ?>">
                <label class="form-check-label" for="remove_<?php echo $key; ?>">Remover imagem</label>
            </div>
        <?php } ?>
        <input type="file" name="<?php echo $key; ?>" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" data-image-for="<?php echo $key; ?>">
    </div>
<?php }

function theme_form_script($fallback_logo){ ?>
<script>
(function () {
    var tv = document.getElementById('tv');
    if (!tv) return;
    var wrap = document.getElementById('tv_wrap');
    var isColor = function (v) { return /^#[0-9a-fA-F]{6}$/.test(v || ''); };

    // Cores: variável CSS --f-<campo>
    function setColor(key, value) {
        if (!isColor(value)) return;
        var input = document.getElementById(key);
        input.value = value.toUpperCase();
        var picker = document.querySelector('[data-picker-for="' + key + '"]');
        if (picker) picker.value = value;
        tv.style.setProperty('--f-' + key, value);
    }
    document.querySelectorAll('[data-field]').forEach(function (input) {
        input.addEventListener('input', function () { setColor(input.getAttribute('data-field'), input.value.trim()); });
    });
    document.querySelectorAll('[data-picker-for]').forEach(function (p) {
        p.addEventListener('input', function () { setColor(p.getAttribute('data-picker-for'), p.value); });
    });

    // Liga/desliga: classe off-<campo>
    document.querySelectorAll('[data-toggle]').forEach(function (cb) {
        cb.addEventListener('change', function () { tv.classList.toggle('off-' + cb.getAttribute('data-toggle').replace(/_/g, '-'), !cb.checked); });
    });

    // Níveis (sliders)
    document.querySelectorAll('[data-range]').forEach(function (r) {
        var key = r.getAttribute('data-range'), unit = r.getAttribute('data-unit');
        function up() {
            var v = parseInt(r.value, 10);
            if (key === 'theme_corner') tv.style.setProperty('--r', (v * 0.07) + 'cqw');
            else if (key === 'theme_bg_overlay') tv.style.setProperty('--ov', v / 100);
            else tv.style.setProperty('--f-' + key, v / 100);
            var lbl = document.querySelector('[data-range-val="' + key + '"]');
            if (lbl) lbl.textContent = v + unit;
        }
        r.addEventListener('input', up);
    });

    // Paletas prontas (BestPro)
    document.querySelectorAll('[data-preset]').forEach(function (b) {
        b.addEventListener('click', function () {
            var c = JSON.parse(b.getAttribute('data-preset'));
            ['theme_primary', 'theme_accent', 'theme_background', 'theme_surface', 'theme_text'].forEach(function (key, i) { if (document.getElementById(key)) setColor(key, c[i]); });
        });
    });

    // Restaurar cores originais do modelo
    var restore = document.getElementById('th_restore');
    if (restore) restore.addEventListener('click', function () {
        document.querySelectorAll('[data-field]').forEach(function (input) { setColor(input.getAttribute('data-field'), input.getAttribute('data-default')); });
    });

    // Nome exibido e "mostrar nome"
    var brandText = document.getElementById('theme_brand_text');
    if (brandText) brandText.addEventListener('input', function () {
        var n = document.getElementById('tv_name');
        if (n) n.textContent = brandText.value || wrap.getAttribute('data-default-name');
    });
    var showName = document.getElementById('theme_show_name');
    if (showName) showName.addEventListener('change', function () { tv.classList.toggle('hide-name', !showName.checked); });

    // Imagens: mostra na pré-visualização antes de salvar
    var targets = {
        theme_logo: function (url) { var i = document.getElementById('tv_logo'); if (i) { i.style.visibility = ''; i.src = url; } },
        theme_bg_image: function (url) { var b = document.getElementById('tv_bgimg'); if (b) b.style.backgroundImage = url ? "url('" + url + "')" : ''; },
        theme_banner: function (url) { var el = document.getElementById('tv_banner'); if (el) el.style.backgroundImage = url ? "url('" + url + "')" : ''; }
    };
    document.querySelectorAll('[data-image-for]').forEach(function (input) {
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            var fn = targets[input.getAttribute('data-image-for')];
            if (!f || !fn) return;
            var reader = new FileReader();
            reader.onload = function (e) { fn(e.target.result); };
            reader.readAsDataURL(f);
        });
    });
    document.querySelectorAll('[data-remove-for]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var key = cb.getAttribute('data-remove-for'), fn = targets[key];
            if (cb.checked && fn) fn(key === 'theme_logo' ? <?php echo json_encode($fallback_logo); ?> : '');
        });
    });
})();
</script>
<?php }
