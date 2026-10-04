<?php
require_once(__DIR__.'/theme_preview.php');

/**
 * Editor visual do tema BestPro (paletas, cores, imagens e pré-visualização de TV).
 * Usado em settings_theme.php (admin) e revenda/theme.php (revendedor).
 *
 * $opts:
 *   img_base     => caminho até a pasta images/ ('images/' ou '../images/')
 *   app_name     => nome mostrado na pré-visualização
 *   fallback_logo=> URL do logo usado quando não há logo próprio
 *   side_html    => HTML extra no topo da coluna da pré-visualização (avisos, status)
 *   actions_html => HTML dos botões abaixo da pré-visualização
 *   allow_keep_colors => true nos modelos prontos (mostra "Manter as cores originais")
 */

/**
 * Galeria: "Modelos" (originais) e "Meus temas" (personalizações salvas com nome).
 * $active = array('base' => id, 'saved_id' => id). $self_url: página atual (sem query).
 */
function theme_gallery_render($saved, $active, $img_prefix, $self_url, $hidden_html, $editing){ ?>
<style>
    .tg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
    .tg-card { border: 2px solid #eef0f4; border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
    .tg-card.editing { border-color: #3b6cf6; box-shadow: 0 0 0 3px rgba(59,108,246,.15); }
    .tg-card.in-use { border-color: #16a34a; }
    .tg-thumb { position: relative; aspect-ratio: 16 / 9; background: #f3f4f6; }
    .tg-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tg-thumb .tg-custom { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #fff; font-weight: 700; background: linear-gradient(135deg, #3B6CF6, #22C55E); }
    .tg-badges { position: absolute; top: 8px; left: 8px; display: flex; gap: 4px; flex-wrap: wrap; }
    .tg-badges span { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
    .tg-on { background: #16a34a; color: #fff; }
    .tg-swatches { position: absolute; right: 8px; bottom: 8px; display: flex; }
    .tg-swatches i { width: 16px; height: 16px; border-radius: 50%; border: 2px solid #fff; margin-left: -5px; display: inline-block; }
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
<?php if ($saved) { ?>
    <div class="tg-grid mb-4">
        <?php foreach ($saved as $s) {
            $cat = theme_catalog();
            $base = isset($cat[$s['base_theme']]) ? $cat[$s['base_theme']] : $cat['1'];
            $in_use = (int)$active['saved_id'] === (int)$s['id'];
            $st = $s['settings_arr']; ?>
            <div class="tg-card <?php echo $in_use ? 'in-use' : ''; ?> <?php echo (string)$editing === 's'.$s['id'] ? 'editing' : ''; ?>">
                <div class="tg-thumb">
                    <?php if ($base['image'] != '') { ?><img src="<?php echo htmlspecialchars($img_prefix.$base['image']); ?>.png" alt="" loading="lazy">
                    <?php } else { ?><div class="tg-custom" style="background:linear-gradient(135deg, <?php echo htmlspecialchars($st['theme_primary']); ?>, <?php echo htmlspecialchars($st['theme_accent']); ?>)"><i class="ri-palette-line fs-3"></i>BestPro</div><?php } ?>
                    <div class="tg-badges"><?php if ($in_use) { ?><span class="tg-on">Em uso</span><?php } ?></div>
                    <?php if ($st['theme_keep_colors'] != '1') { ?>
                        <div class="tg-swatches"><?php foreach (array('theme_primary', 'theme_accent', 'theme_surface') as $k) { ?><i style="background:<?php echo htmlspecialchars($st[$k]); ?>"></i><?php } ?></div>
                    <?php } ?>
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
                <?php if ($th['image'] != '') { ?><img src="<?php echo htmlspecialchars($img_prefix.$th['image']); ?>.png" alt="" loading="lazy">
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
    .th-color { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
    .th-color input[type=color] { width: 46px; height: 38px; border: 1px solid #dfe3ea; border-radius: 8px; padding: 2px; background: #fff; cursor: pointer; flex: 0 0 46px; }
    .th-color input[type=text] { width: 110px; font-family: monospace; text-transform: uppercase; }
    .th-color label { flex: 1 1 auto; margin: 0; font-weight: 500; }
    .th-color small { display: block; color: #6b7a99; font-weight: 400; }
    .th-presets { display: flex; flex-wrap: wrap; gap: 8px; }
    .th-preset { border: 1px solid #e5e7eb; background: #fff; border-radius: 20px; padding: 5px 12px 5px 6px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
    .th-preset:hover { border-color: #3b6cf6; }
    .th-preset span.sw { display: inline-flex; }
    .th-preset span.sw i { width: 14px; height: 14px; border-radius: 50%; display: inline-block; margin-right: -4px; border: 2px solid #fff; }
    .th-img { border: 1px dashed #d6dbe4; border-radius: 10px; padding: 12px; margin-bottom: 14px; }
    .th-img img { max-height: 70px; max-width: 100%; border-radius: 6px; display: block; margin-bottom: 8px; background: #f3f4f6; }

</style>
<?php }

function theme_form_editor($t, $opts){
    $img_base = $opts['img_base'];
    $logo_url = $t['theme_logo'] != '' ? $img_base.$t['theme_logo'] : $opts['fallback_logo'];
    $tid = isset($opts['theme_id']) ? (string)$opts['theme_id'] : THEME_BESTPRO_ID;
    $default_name = $opts['app_name'];
    $shown_name = $t['theme_brand_text'] != '' ? $t['theme_brand_text'] : $default_name;
    ?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card th-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Identificação</h5>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome do tema <span class="text-danger">*</span></label>
                    <input type="text" name="saved_name" class="form-control" maxlength="60" required value="<?php echo htmlspecialchars(isset($opts['saved_name']) ? $opts['saved_name'] : ''); ?>" placeholder="Ex.: <?php echo htmlspecialchars($opts['model_name']); ?> Dourado">
                    <small class="text-muted">Nome para encontrar este tema na lista "Meus temas". Não aparece no app.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Nome exibido no app</label>
                    <input type="text" name="theme_brand_text" id="theme_brand_text" class="form-control" maxlength="40" value="<?php echo htmlspecialchars($t['theme_brand_text']); ?>" placeholder="<?php echo htmlspecialchars($default_name); ?>">
                    <small class="text-muted">Aparece ao lado do logo no topo do app. Vazio = "<?php echo htmlspecialchars($default_name); ?>".</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="theme_show_name" id="theme_show_name" value="1" <?php if ($t['theme_show_name'] == '1') { echo 'checked'; } ?>>
                    <label class="form-check-label" for="theme_show_name">Mostrar o nome ao lado do logo <small class="text-muted">(desligue para mostrar só o logo)</small></label>
                </div>
            </div>
        </div>

        <div class="card th-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Paletas prontas</h5>
                <div class="th-presets">
                    <?php foreach(theme_presets() as $name => $c){ ?>
                        <button type="button" class="th-preset" data-preset='<?php echo json_encode($c); ?>'>
                            <span class="sw"><?php foreach(array_slice($c, 0, 3) as $hex){ ?><i style="background:<?php echo $hex; ?>"></i><?php } ?></span>
                            <?php echo htmlspecialchars($name); ?>
                        </button>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="card th-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Cores</h5>
                <?php if (!empty($opts['allow_keep_colors'])) { ?>
                    <div class="form-check form-switch mb-3 p-3 rounded" style="background:#f7f9fe;padding-left:3.2rem !important;">
                        <input class="form-check-input" type="checkbox" name="theme_keep_colors" id="theme_keep_colors" value="1" <?php if ($t['theme_keep_colors'] == '1') { echo 'checked'; } ?>>
                        <label class="form-check-label" for="theme_keep_colors">
                            <b>Manter as cores originais do modelo</b>
                            <small class="text-muted d-block">Troca só logo, fundo, banner e nome. Desligue para usar as cores abaixo.</small>
                        </label>
                    </div>
                <?php } ?>
                <div id="th_colors_box">
                <?php
                    $colors = array(
                        'theme_primary'    => array('Cor principal', 'Botões, item selecionado e foco do controle'),
                        'theme_accent'     => array('Cor de destaque', 'Selo "AO VIVO", progresso e detalhes'),
                        'theme_background' => array('Fundo', 'Cor de fundo das telas'),
                        'theme_surface'    => array('Cartões e menus', 'Cartões, barra lateral e janelas'),
                        'theme_text'       => array('Texto', 'Cor principal dos textos'),
                    );
                    foreach($colors as $key => $info){ ?>
                    <div class="th-color">
                        <input type="color" value="<?php echo htmlspecialchars($t[$key]); ?>" data-color-for="<?php echo $key; ?>">
                        <label><?php echo $info[0]; ?><small><?php echo $info[1]; ?></small></label>
                        <input type="text" name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($t[$key]); ?>" maxlength="7" required>
                    </div>
                <?php } ?>
                <label class="form-label mt-2">Arredondamento dos cartões: <b id="corner_val"><?php echo (int)$t['theme_corner']; ?>px</b></label>
                <input type="range" class="form-range" name="theme_corner" id="theme_corner" min="0" max="32" value="<?php echo (int)$t['theme_corner']; ?>">
                </div>
            </div>
        </div>

        <div class="card th-card">
            <div class="card-body p-4">
                <h5 class="mb-3">Imagens</h5>
                <?php
                    $is_movie = $tid === '4';
                    $images = array(
                        'theme_logo'     => array('Logo da tela inicial',
                            '<b>Tamanho ideal: 512 × 512 px</b> (quadrado), PNG com <b>fundo transparente</b>. Mínimo 256 × 256 px. Deixe uma pequena margem em volta do desenho. Vazio = logo padrão do app.'),
                        'theme_bg_image' => array('Imagem de fundo',
                            '<b>Tamanho ideal: 1920 × 1080 px</b> (Full HD, formato 16:9 da TV), JPG ou WEBP de até 1 MB. Prefira imagens escuras ou com pouco detalhe: os cartões e textos ficam por cima. Use o controle "Escurecer" abaixo para dar contraste.'),
                        'theme_banner'   => $is_movie
                            ? array('Pôster de destaque (tela cheia)', '<b>Tamanho ideal: 1920 × 1080 px</b> (16:9). No MovieUI o banner ocupa a <b>tela inteira</b> atrás do menu: deixe o título/rosto na parte de cima e o rodapé mais escuro, onde ficam os botões.')
                            : array('Banner de destaque', '<b>Tamanho ideal: 1280 × 480 px</b> (formato 8:3), JPG ou WEBP. Mantenha textos e rostos no centro: as bordas podem ser cortadas em telas menores.'.($tid !== THEME_BESTPRO_ID ? ' <i>Neste modelo o banner é usado como destaque quando a tela tiver espaço para ele.</i>' : '')),
                    );
                    foreach($images as $key => $info){ ?>
                    <div class="th-img">
                        <div class="fw-semibold"><?php echo $info[0]; ?></div>
                        <small class="text-muted d-block mb-2"><?php echo $info[1]; ?> Aceita PNG, JPG ou WEBP até 5 MB.</small>
                        <?php if($t[$key] != ''){ ?>
                            <img src="<?php echo htmlspecialchars($img_base.$t[$key]); ?>" alt="">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="remove_<?php echo $key; ?>" id="remove_<?php echo $key; ?>" value="1" data-remove-for="<?php echo $key; ?>">
                                <label class="form-check-label" for="remove_<?php echo $key; ?>">Remover imagem</label>
                            </div>
                        <?php } ?>
                        <input type="file" name="<?php echo $key; ?>" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" data-image-for="<?php echo $key; ?>">
                    </div>
                <?php } ?>
                <label class="form-label">Escurecer imagem de fundo: <b id="overlay_val"><?php echo (int)$t['theme_bg_overlay']; ?>%</b></label>
                <input type="range" class="form-range" name="theme_bg_overlay" id="theme_bg_overlay" min="0" max="90" value="<?php echo (int)$t['theme_bg_overlay']; ?>">
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card th-card" style="position: sticky; top: 90px;">
            <div class="card-body p-4">
                <?php echo isset($opts['side_html']) ? $opts['side_html'] : ''; ?>
                <?php
                    $orig = theme_model_originals();
                    $catalog = theme_catalog();
                    $model_img = (isset($catalog[$tid]) && $catalog[$tid]['image'] != '' && isset($opts['model_img_base'])) ? $opts['model_img_base'].$catalog[$tid]['image'].'.png' : '';
                ?>
                <div class="tv <?php echo $t['theme_show_name'] == '0' ? 'hide-name' : ''; ?>" id="tv" data-theme="<?php echo htmlspecialchars($tid); ?>" data-default-name="<?php echo htmlspecialchars($default_name); ?>" data-original='<?php echo json_encode(isset($orig[$tid]) ? $orig[$tid] : $orig[THEME_BESTPRO_ID]); ?>'>
                    <?php theme_preview_markup($tid, $logo_url, $shown_name,
                        $t['theme_bg_image'] != '' ? $img_base.$t['theme_bg_image'] : '',
                        $t['theme_banner'] != '' ? $img_base.$t['theme_banner'] : '',
                        $model_img); ?>
                </div>
                <p class="text-muted small mt-3 mb-0">A pré-visualização é aproximada. No app, o tema aplica estas cores e imagens nas telas de TV, celular e tablet.</p>
                <?php echo isset($opts['actions_html']) ? $opts['actions_html'] : ''; ?>
            </div>
        </div>
    </div>
</div>
<?php }

function theme_form_script($fallback_logo){ ?>
<script>
(function () {
    var tv = document.getElementById('tv');
    if (!tv) return;
    var themeId = tv.getAttribute('data-theme');
    var original = JSON.parse(tv.getAttribute('data-original') || '{}');
    var vars = { theme_primary: '--p', theme_accent: '--a', theme_background: '--bg', theme_surface: '--s', theme_text: '--t' };
    var origKey = { theme_primary: 'p', theme_accent: 'a', theme_background: 'bg', theme_surface: 's', theme_text: 't' };
    var keep = document.getElementById('theme_keep_colors');

    function keeping() { return !!(keep && keep.checked); }

    // Aplica na pré-visualização: cores escolhidas, ou as originais do modelo quando "manter cores" está ligado
    function paint() {
        Object.keys(vars).forEach(function (key) {
            var v = keeping() ? original[origKey[key]] : document.getElementById(key).value;
            if (/^#[0-9a-fA-F]{6}$/.test(v || '')) tv.style.setProperty(vars[key], v);
        });
        tv.classList.toggle('custom', !keeping());
        var box = document.getElementById('th_colors_box');
        if (box) box.style.opacity = keeping() ? '.45' : '1';
    }

    function setColor(key, value) {
        if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
        document.getElementById(key).value = value.toUpperCase();
        var picker = document.querySelector('[data-color-for="' + key + '"]');
        if (picker) picker.value = value;
        paint();
    }

    document.querySelectorAll('[data-color-for]').forEach(function (p) {
        p.addEventListener('input', function () { if (keep && keep.checked) { keep.checked = false; } setColor(p.getAttribute('data-color-for'), p.value); });
    });
    Object.keys(vars).forEach(function (key) {
        document.getElementById(key).addEventListener('input', function () { setColor(key, this.value.trim()); });
    });
    document.querySelectorAll('[data-preset]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (keep) keep.checked = false;
            var c = JSON.parse(b.getAttribute('data-preset'));
            ['theme_primary', 'theme_accent', 'theme_background', 'theme_surface', 'theme_text'].forEach(function (key, i) { setColor(key, c[i]); });
        });
    });
    if (keep) keep.addEventListener('change', paint);
    paint();

    var corner = document.getElementById('theme_corner');
    function upCorner() { tv.style.setProperty('--r', (corner.value * 0.07) + 'cqw'); document.getElementById('corner_val').textContent = corner.value + 'px'; }
    corner.addEventListener('input', upCorner); upCorner();

    var overlay = document.getElementById('theme_bg_overlay');
    function upOverlay() { tv.style.setProperty('--ov', overlay.value / 100); document.getElementById('overlay_val').textContent = overlay.value + '%'; }
    overlay.addEventListener('input', upOverlay); upOverlay();

    // Nome exibido no app e "mostrar nome"
    var brandText = document.getElementById('theme_brand_text');
    if (brandText) brandText.addEventListener('input', function () {
        var n = document.getElementById('tv_name');
        if (n) n.textContent = brandText.value || tv.getAttribute('data-default-name');
    });
    var showName = document.getElementById('theme_show_name');
    if (showName) showName.addEventListener('change', function () { tv.classList.toggle('hide-name', !showName.checked); });

    // MovieUI: o pôster de destaque usa o banner, senão a imagem de fundo, senão a tela original
    var current = { bg: '', banner: '' };
    var heroEl = document.getElementById('tv_banner');
    var heroFallback = heroEl ? heroEl.getAttribute('data-hero-fallback') : null;
    if (heroEl && themeId === '4') { current.bg = heroEl.getAttribute('data-bg') || ''; current.banner = heroEl.getAttribute('data-banner') || ''; }
    function updateHero() {
        if (themeId !== '4' || !heroEl) return;
        var url = current.banner || current.bg || heroFallback;
        heroEl.style.backgroundImage = url ? "url('" + url + "')" : 'none';
    }

    var targets = {
        theme_logo: function (url) { var i = document.getElementById('tv_logo'); if (i) { i.style.visibility = ''; i.src = url; } },
        theme_bg_image: function (url) {
            current.bg = url;
            var b = document.getElementById('tv_bgimg');
            if (b) b.style.backgroundImage = url ? "url('" + url + "')" : 'none';
            updateHero();
        },
        theme_banner: function (url) {
            current.banner = url;
            if (themeId === '4') { updateHero(); return; }
            var el = document.getElementById('tv_banner');
            if (el) el.style.backgroundImage = url ? "url('" + url + "')" : '';
        }
    };
    document.querySelectorAll('[data-image-for]').forEach(function (input) {
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) return;
            var reader = new FileReader();
            reader.onload = function (e) { targets[input.getAttribute('data-image-for')](e.target.result); };
            reader.readAsDataURL(f);
        });
    });
    document.querySelectorAll('[data-remove-for]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var key = cb.getAttribute('data-remove-for');
            if (cb.checked) targets[key](key === 'theme_logo' ? <?php echo json_encode($fallback_logo); ?> : '');
        });
    });
})();
</script>
<?php }