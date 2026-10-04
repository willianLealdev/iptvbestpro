<?php
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
 * Galeria de modelos: cada cartão tem "Usar este" e "Personalizar".
 * $state[id] = array('active' => bool, 'customized' => bool). $edit_url / $use_name: link de edição e nome do botão de ativar.
 */
function theme_gallery_render($state, $img_prefix, $edit_url_base, $hidden_html, $editing_id){ ?>
<style>
    .tg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
    .tg-card { border: 2px solid #eef0f4; border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
    .tg-card.editing { border-color: #3b6cf6; box-shadow: 0 0 0 3px rgba(59,108,246,.15); }
    .tg-thumb { position: relative; aspect-ratio: 16 / 9; background: #f3f4f6; }
    .tg-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tg-thumb .tg-custom { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #fff; font-weight: 700; background: linear-gradient(135deg, #3B6CF6, #22C55E); }
    .tg-badges { position: absolute; top: 8px; left: 8px; display: flex; gap: 4px; flex-wrap: wrap; }
    .tg-badges span { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
    .tg-on { background: #16a34a; color: #fff; }
    .tg-cus { background: #3b6cf6; color: #fff; }
    .tg-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .tg-name { font-weight: 600; font-size: 14px; }
    .tg-actions { display: flex; gap: 6px; margin-top: auto; }
    .tg-actions .btn { flex: 1; font-size: 13px; padding: 5px 6px; }
</style>
<div class="tg-grid">
    <?php foreach (theme_catalog() as $id => $th) { $s = isset($state[$id]) ? $state[$id] : array('active' => false, 'customized' => false); ?>
        <div class="tg-card <?php echo (string)$editing_id === (string)$id ? 'editing' : ''; ?>">
            <div class="tg-thumb">
                <?php if ($th['image'] != '') { ?>
                    <img src="<?php echo htmlspecialchars($img_prefix.$th['image']); ?>.png" alt="" loading="lazy">
                <?php } else { ?>
                    <div class="tg-custom"><i class="ri-palette-line fs-3"></i>BestPro</div>
                <?php } ?>
                <div class="tg-badges">
                    <?php if ($s['active']) { ?><span class="tg-on">Em uso</span><?php } ?>
                    <?php if ($s['customized'] && $th['image'] != '') { ?><span class="tg-cus">Personalizado</span><?php } ?>
                </div>
            </div>
            <div class="tg-body">
                <div class="tg-name"><?php echo htmlspecialchars($th['name']); ?></div>
                <div class="tg-actions">
                    <?php if (!$s['active']) { ?>
                        <form method="post" style="flex:1;display:flex;">
                            <?php echo $hidden_html; ?>
                            <input type="hidden" name="use_theme" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-outline-success w-100">Usar este</button>
                        </form>
                    <?php } else { ?>
                        <button type="button" class="btn btn-success" disabled>Em uso</button>
                    <?php } ?>
                    <a href="<?php echo htmlspecialchars($edit_url_base.$id); ?>#editor" class="btn btn-primary"><i class="ri-palette-line"></i> Personalizar</a>
                </div>
            </div>
        </div>
    <?php } ?>
</div>
<?php }

function theme_form_styles(){ ?>
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

    /* Pré-visualização (tela de TV) */
    .tv { --p: #3B6CF6; --a: #22C55E; --bg: #0B1220; --s: #131C2E; --t: #FFFFFF; --r: 14px; --ov: .6;
          position: relative; aspect-ratio: 16 / 9; border-radius: 16px; overflow: hidden; background: var(--bg); color: var(--t);
          box-shadow: 0 0 0 8px #111827, 0 18px 40px rgba(0,0,0,.25); font-family: inherit; }
    .tv__bgimg { position: absolute; inset: 0; background-size: cover; background-position: center; }
    .tv__bgimg::after { content: ''; position: absolute; inset: 0; background: var(--bg); opacity: var(--ov); }
    .tv__inner { position: relative; display: flex; height: 100%; }
    .tv__side { width: 22%; background: color-mix(in srgb, var(--s) 85%, transparent); padding: 5% 3%; display: flex; flex-direction: column; gap: 6%; }
    .tv__logo { height: 9%; display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: clamp(8px, 1.2vw, 14px); min-width: 0; }
    .tv__logo img { height: 100%; border-radius: 6px; object-fit: contain; }
    .tv__logo span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tv__menu div { font-size: clamp(7px, .95vw, 12px); padding: 6% 8%; border-radius: calc(var(--r) * .6); opacity: .8; margin-bottom: 4%; }
    .tv__menu div.on { background: var(--p); opacity: 1; font-weight: 600; }
    .tv__main { flex: 1; padding: 4% 4%; display: flex; flex-direction: column; gap: 5%; min-width: 0; }
    .tv__banner { height: 40%; border-radius: var(--r); background: linear-gradient(120deg, var(--p), var(--a)); background-size: cover; background-position: center;
                  position: relative; overflow: hidden; display: flex; align-items: flex-end; padding: 3%; }
    .tv__banner b { font-size: clamp(9px, 1.4vw, 18px); text-shadow: 0 2px 8px rgba(0,0,0,.5); }
    .tv__row-title { font-size: clamp(7px, .95vw, 12px); font-weight: 600; opacity: .9; margin-bottom: 2%; }
    .tv__row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3%; }
    .tv__tile { aspect-ratio: 16 / 10; border-radius: var(--r); background: var(--s); position: relative; overflow: hidden; border: 2px solid transparent; }
    .tv__tile.focus { border-color: var(--p); box-shadow: 0 0 0 3px color-mix(in srgb, var(--p) 35%, transparent); }
    .tv__tile .live { position: absolute; top: 8%; left: 8%; background: var(--a); color: #000; font-size: clamp(5px, .6vw, 9px); font-weight: 700; padding: 2% 6%; border-radius: 4px; }
    .tv__tile .bar { position: absolute; left: 0; bottom: 0; height: 6%; width: 55%; background: var(--a); }
</style>
<?php }

function theme_form_editor($t, $opts){
    $img_base = $opts['img_base'];
    $logo_url = $t['theme_logo'] != '' ? $img_base.$t['theme_logo'] : $opts['fallback_logo'];
    ?>
<div class="row g-4">
    <div class="col-xl-5">
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
                    $images = array(
                        'theme_logo'     => array('Logo da tela inicial', 'PNG com fundo transparente. Vazio = logo padrão.'),
                        'theme_bg_image' => array('Imagem de fundo', 'Recomendado 1920×1080.'),
                        'theme_banner'   => array('Banner de destaque', 'Recomendado 1280×480.'),
                    );
                    foreach($images as $key => $info){ ?>
                    <div class="th-img">
                        <div class="fw-semibold"><?php echo $info[0]; ?></div>
                        <small class="text-muted d-block mb-2"><?php echo $info[1]; ?> PNG, JPG ou WEBP até 5 MB.</small>
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
                <div class="tv" id="tv">
                    <div class="tv__bgimg" id="tv_bgimg" style="<?php echo $t['theme_bg_image'] != '' ? "background-image:url('".htmlspecialchars($img_base.$t['theme_bg_image'])."')" : ''; ?>"></div>
                    <div class="tv__inner">
                        <div class="tv__side">
                            <div class="tv__logo"><img id="tv_logo" src="<?php echo htmlspecialchars($logo_url); ?>" alt="" onerror="this.style.display='none'"><span id="tv_name"><?php echo htmlspecialchars($opts['app_name']); ?></span></div>
                            <div class="tv__menu">
                                <div class="on">Início</div>
                                <div>TV ao Vivo</div>
                                <div>Filmes</div>
                                <div>Séries</div>
                                <div>Favoritos</div>
                                <div>Configurações</div>
                            </div>
                        </div>
                        <div class="tv__main">
                            <div class="tv__banner" id="tv_banner" style="<?php echo $t['theme_banner'] != '' ? "background-image:url('".htmlspecialchars($img_base.$t['theme_banner'])."')" : ''; ?>"><b>Destaques da semana</b></div>
                            <div>
                                <div class="tv__row-title">Canais ao vivo</div>
                                <div class="tv__row">
                                    <div class="tv__tile focus"><span class="live">AO VIVO</span></div>
                                    <div class="tv__tile"><span class="live">AO VIVO</span></div>
                                    <div class="tv__tile"></div>
                                    <div class="tv__tile"></div>
                                </div>
                            </div>
                            <div>
                                <div class="tv__row-title">Continuar assistindo</div>
                                <div class="tv__row">
                                    <div class="tv__tile"><span class="bar"></span></div>
                                    <div class="tv__tile"><span class="bar" style="width:30%"></span></div>
                                    <div class="tv__tile"></div>
                                    <div class="tv__tile"></div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    var vars = { theme_primary: '--p', theme_accent: '--a', theme_background: '--bg', theme_surface: '--s', theme_text: '--t' };

    function setColor(key, value) {
        if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
        tv.style.setProperty(vars[key], value);
        document.getElementById(key).value = value.toUpperCase();
        var picker = document.querySelector('[data-color-for="' + key + '"]');
        if (picker) picker.value = value;
    }
    Object.keys(vars).forEach(function (key) { setColor(key, document.getElementById(key).value); });

    document.querySelectorAll('[data-color-for]').forEach(function (p) {
        p.addEventListener('input', function () { setColor(p.getAttribute('data-color-for'), p.value); });
    });
    Object.keys(vars).forEach(function (key) {
        document.getElementById(key).addEventListener('input', function () { setColor(key, this.value.trim()); });
    });

    document.querySelectorAll('[data-preset]').forEach(function (b) {
        b.addEventListener('click', function () {
            var c = JSON.parse(b.getAttribute('data-preset'));
            ['theme_primary', 'theme_accent', 'theme_background', 'theme_surface', 'theme_text'].forEach(function (key, i) { setColor(key, c[i]); });
        });
    });

    var corner = document.getElementById('theme_corner');
    function upCorner() { tv.style.setProperty('--r', corner.value + 'px'); document.getElementById('corner_val').textContent = corner.value + 'px'; }
    corner.addEventListener('input', upCorner); upCorner();

    var overlay = document.getElementById('theme_bg_overlay');
    function upOverlay() { tv.style.setProperty('--ov', overlay.value / 100); document.getElementById('overlay_val').textContent = overlay.value + '%'; }
    overlay.addEventListener('input', upOverlay); upOverlay();

    // "Manter cores originais": deixa as cores esmaecidas (continuam salvas para uso futuro)
    var keep = document.getElementById('theme_keep_colors');
    function upKeep() { var box = document.getElementById('th_colors_box'); if (box && keep) { box.style.opacity = keep.checked ? '.45' : '1'; } }
    if (keep) { keep.addEventListener('change', upKeep); upKeep(); }

    // Nome da marca (portal do revendedor)
    var brand = document.getElementById('brand_name');
    if (brand) brand.addEventListener('input', function () { document.getElementById('tv_name').textContent = brand.value || brand.getAttribute('placeholder'); });

    // Pré-visualização das imagens escolhidas antes de salvar
    var targets = {
        theme_logo: function (url) { var i = document.getElementById('tv_logo'); i.style.display = ''; i.src = url; },
        theme_bg_image: function (url) { document.getElementById('tv_bgimg').style.backgroundImage = url ? "url('" + url + "')" : 'none'; },
        theme_banner: function (url) { document.getElementById('tv_banner').style.backgroundImage = url ? "url('" + url + "')" : ''; }
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
