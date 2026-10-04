<?php
/**
 * Pré-visualização fiel de cada modelo (tela inicial do app, em proporção de TV).
 * Cada campo da ficha do modelo (theme_models.php) vira a variável CSS --f-<chave>;
 * campos liga/desliga viram classes off-<chave>. O editor atualiza tudo ao vivo.
 */

function theme_preview_styles(){ ?>
<style>
    .tv { --r: 1cqw; --ov: .6; container-type: inline-size; position: relative; aspect-ratio: 16 / 9; border-radius: 16px; overflow: hidden;
          color: #fff; box-shadow: 0 0 0 8px #111827, 0 18px 40px rgba(0,0,0,.25); font-family: inherit; background: #0b0b14; }
    .tv * { box-sizing: border-box; }
    .tv i { line-height: 1; }
    .tv.tv--thumb { border-radius: 0; box-shadow: none; pointer-events: none; }
    .tv.hide-name .pv-name, .tv.hide-name .m8 .brand span { display: none; }
    .tv__bgimg { position: absolute; inset: 0; background-size: cover; background-position: center; }
    .tv__bgimg::after { content: ''; position: absolute; inset: 0; background: #000; opacity: var(--ov); }
    .tv__bgimg:not([style*="url"])::after { display: none; }
    .tv__layer { position: absolute; inset: 0; padding: 3.2cqw 3.4cqw; display: flex; flex-direction: column; gap: 2.4cqw; }

    /* Cabeçalho comum */
    .pv-head { display: flex; align-items: center; gap: 1.6cqw; position: relative; }
    .pv-head .pv-logo { height: 5.4cqw; width: 5.4cqw; border-radius: 1cqw; object-fit: cover; background: rgba(255,255,255,.06); }
    .pv-head .pv-name { font-size: 2.6cqw; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 30cqw; }
    .pv-head .pv-date { font-size: 1.8cqw; opacity: .95; margin-left: 4cqw; white-space: nowrap; }
    .pv-head .pv-icons { margin-left: auto; display: flex; gap: 2.2cqw; font-size: 2.6cqw; }
    .pv-foot { display: flex; justify-content: space-between; font-size: 2cqw; margin-top: auto; }
    .pv-refresh { display: inline-flex; align-items: center; justify-content: center; width: 5cqw; height: 2.8cqw; border-radius: 2cqw; background: #fff; color: #111; font-size: 1.8cqw; flex: 0 0 auto; }
    .pv-upd { font-size: 1.2cqw; opacity: .85; }
    .pv-count { position: absolute; top: .8cqw; right: .8cqw; font-size: 1.2cqw; font-weight: 700; padding: .2cqw .6cqw; border-radius: .5cqw; border: .12cqw solid rgba(255,255,255,.35); background: rgba(0,0,0,.25); }
    /* Desenhos ("rabiscos") por cima dos cartões */
    .pat::before { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .16;
                   background-image: radial-gradient(circle at 20% 30%, transparent .9cqw, rgba(255,255,255,.9) 1cqw, transparent 1.15cqw),
                                     radial-gradient(circle at 70% 60%, transparent .6cqw, rgba(255,255,255,.9) .7cqw, transparent .85cqw);
                   background-size: 6cqw 6cqw, 4.5cqw 4.5cqw; }
    .tv.off-show-pattern .pat::before { display: none; }

    /* 1 OneUI */
    .tv[data-m="1"] { background: linear-gradient(to bottom, var(--f-bg_top, #070923), var(--f-bg_bottom, #020309)); }
    .m1 .grid { flex: 1; display: grid; grid-template-columns: 1.05fr 1fr 1fr; grid-template-rows: 1.45fr 1fr; gap: 1.8cqw; min-height: 0; }
    .m1 .big { grid-row: span 2; }
    .m1 .card { position: relative; border-radius: var(--r); overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 800; }
    .m1 .card > i { font-size: 5.6cqw; position: relative; }
    .m1 .big > i { font-size: 8cqw; }
    .m1 .card b { font-size: 2.6cqw; margin-top: .4cqw; margin-bottom: 3.6cqw; position: relative; }
    .m1 .big b { font-size: 3.4cqw; }
    .m1 .card .bar { position: absolute; left: 0; right: 0; bottom: 0; padding: .8cqw 1.4cqw; background: rgba(0,0,0,.18); display: flex; align-items: center; justify-content: space-between; font-weight: 400; }
    .m1 .live { background: linear-gradient(to bottom, var(--f-live_top), var(--f-live_bottom)); }
    .m1 .mov { background: linear-gradient(to bottom, var(--f-movies_top), var(--f-movies_bottom)); }
    .m1 .ser { background: linear-gradient(to bottom, var(--f-series_top), var(--f-series_bottom)); }
    .m1 .btn3 { background: var(--f-buttons); border-radius: var(--r); display: flex; align-items: center; justify-content: center; gap: 1cqw; font-size: 1.9cqw; font-weight: 800; }
    .m1 .row2 { grid-column: 2 / span 2; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.8cqw; }

    /* 2 Glossy */
    .tv[data-m="2"] { background: var(--f-bg, #151B27); }
    .m2 .body { flex: 1; display: grid; grid-template-columns: 7cqw 1.05fr 2fr; gap: 2.4cqw; min-height: 0; }
    .m2 .circles { display: flex; flex-direction: column; gap: 1.4cqw; }
    .m2 .circ, .m2 .head-c { width: 6.6cqw; height: 6.6cqw; border-radius: 50%; background: linear-gradient(135deg, var(--f-accent_start), var(--f-accent_end)); display: inline-flex; align-items: center; justify-content: center; font-size: 3cqw; flex: 0 0 auto; }
    .m2 .list { background: linear-gradient(to bottom, var(--f-panel_top), var(--f-panel_bottom)); border-radius: 1.4cqw; padding: 1cqw; display: flex; flex-direction: column; gap: 1cqw; }
    .m2 .li { flex: 1; background: rgba(0,0,0,.22); border-radius: 1cqw; display: flex; align-items: center; gap: 1.4cqw; padding: 0 1.4cqw; font-size: 2cqw; }
    .m2 .li span { width: 5.6cqw; height: 5.6cqw; border-radius: .8cqw; background: rgba(255,255,255,.08); display: flex; align-items: center; justify-content: center; font-size: 2.8cqw; }
    .m2 .rows { display: flex; flex-direction: column; gap: 1.6cqw; }
    .m2 .rowc { flex: 1; border: .3cqw solid transparent; border-radius: 1.4cqw; display: flex; align-items: center; gap: 2cqw; padding: 0 2cqw;
                background: linear-gradient(to bottom, var(--f-panel_top), var(--f-panel_bottom)) padding-box, linear-gradient(90deg, var(--f-accent_start), var(--f-accent_end)) border-box; }
    .m2 .rowc b { font-size: 2.4cqw; font-weight: 800; width: 12.5cqw; white-space: nowrap; }
    .m2 .rowc > i { font-size: 4.6cqw; }
    .m2 .rowc .upd { margin-left: auto; background: rgba(0,0,0,.25); border-radius: 1cqw; padding: .8cqw 1.2cqw; display: flex; align-items: center; gap: 1cqw; }

    /* 3 Black Panther */
    .tv[data-m="3"] { background: var(--f-bg, #111111); }
    .m3 .row1 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3cqw; flex: 1.6; min-height: 0; }
    .m3 .card { position: relative; background: var(--f-card); border: .15cqw solid rgba(255,255,255,.5); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1cqw; }
    .m3 .card .ic { width: 9cqw; height: 9cqw; border-radius: 50%; background: var(--f-accent); display: flex; align-items: center; justify-content: center; font-size: 4.6cqw; }
    .m3 .card b { font-size: 2.4cqw; font-weight: 800; }
    .m3 .card .bar { width: 100%; margin-top: auto; padding: .8cqw 1.4cqw; background: rgba(0,0,0,.3); display: flex; align-items: center; justify-content: space-between; }
    .m3 .row2 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3cqw; flex: .55; }
    .m3 .btn { background: var(--f-card); border: .15cqw solid rgba(255,255,255,.5); box-shadow: -.5cqw .5cqw 0 var(--f-accent); display: flex; align-items: center; justify-content: center; gap: 1cqw; font-weight: 800; font-size: 2cqw; }

    /* 4 MovieUI */
    .m4-hero { position: absolute; inset: 0; background-size: cover; background-position: center top; }
    .m4 .pv-head { background: rgba(0,0,0,var(--f-topbar_dark, .62)); border-radius: 1.4cqw; padding: 1cqw 1.6cqw; }
    .m4 .tiles { position: relative; margin-top: auto; display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.8cqw; }
    .m4 .tile { background: #3A0A10; border-radius: 1.4cqw; aspect-ratio: 1.55; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .8cqw; font-weight: 800; font-size: 1.8cqw; }
    .tv.off-tile-auto .m4 .tile { background: var(--f-tile_color); }
    .m4 .tile i { font-size: 4cqw; }

    /* 5 VUI / 6 ChristmasUI / 7 HalloweenUI */
    .tv[data-m="5"], .tv[data-m="6"], .tv[data-m="7"] { background: linear-gradient(to bottom, var(--f-bg_top, #070923), var(--f-bg_bottom, #020309)); }
    .m5 .grid { grid-template-columns: repeat(3, 1fr) .85fr; }
    .m6 .grid { grid-template-columns: repeat(3, 1fr) .32fr; }
    .m7 .grid { grid-template-columns: 1fr .32fr 1fr 1fr; }
    .m5 .grid, .m6 .grid, .m7 .grid { flex: 1; display: grid; gap: 2cqw; min-height: 0; }
    .m5 .card, .m6 .card, .m7 .card { position: relative; border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5cqw; overflow: hidden; background: rgba(0,0,0,.25); }
    .c-live { background: color-mix(in srgb, var(--f-live_color) 10%, rgba(0,0,0,.25)) !important; }
    .c-mov { background: color-mix(in srgb, var(--f-movies_color) 10%, rgba(0,0,0,.25)) !important; }
    .c-ser { background: color-mix(in srgb, var(--f-series_color) 10%, rgba(0,0,0,.25)) !important; }
    .c-live > i { color: var(--f-live_color); } .c-mov > i { color: var(--f-movies_color); } .c-ser > i { color: var(--f-series_color); }
    .m5 .card > i, .m6 .card > i, .m7 .card > i { font-size: 6cqw; position: relative; }
    .m5 .card b, .m6 .card b, .m7 .card b { font-size: 2.6cqw; font-weight: 800; position: relative; }
    .m5 .card .pv-refresh { margin-top: .9cqw; }
    .rf { width: 5cqw; height: 2.8cqw; border-radius: 2cqw; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8cqw; color: #fff; margin-top: .9cqw; position: relative; }
    .c-live .rf { background: var(--f-live_color); } .c-mov .rf { background: var(--f-movies_color); } .c-ser .rf { background: var(--f-series_color); }
    .m5 .side, .m6 .side, .m7 .side { display: flex; flex-direction: column; gap: 2cqw; }
    .m5 .sbtn, .m6 .sbtn, .m7 .sbtn { flex: 1; background: rgba(0,0,0,.25); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); display: flex; align-items: center; justify-content: center; gap: 1cqw; font-weight: 800; font-size: 1.8cqw; }
    .m6 .sbtn, .m7 .sbtn { font-size: 3.4cqw; }
    .pv-head.pv-boxed { background: rgba(0,0,0,.25); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); padding: 1cqw 1.6cqw; }
    /* Decorações */
    .decor { position: absolute; font-size: 6cqw; line-height: 1; pointer-events: none; }
    .tv.off-show-decor .decor { display: none; }
    .m7 .card > i.alt { display: none; }
    .tv.off-show-decor .m7 .card > i.alt { display: block; }
    .snow { position: absolute; inset: 0; pointer-events: none;
            background-image: radial-gradient(circle, rgba(255,255,255,.85) .25cqw, transparent .3cqw), radial-gradient(circle, rgba(255,255,255,.6) .18cqw, transparent .22cqw);
            background-size: 11cqw 9cqw, 7cqw 6cqw; background-position: 2cqw 3cqw, 5cqw 1cqw; }
    .tv.off-show-snow .snow { display: none; }

    /* 8 BestPro */
    .tv[data-m="8"] { --p: var(--f-theme_primary, #3B6CF6); --a: var(--f-theme_accent, #22C55E); --s: var(--f-theme_surface, #131C2E); color: var(--f-theme_text, #fff); background: var(--f-theme_background, #0B1220); }
    .m8 { padding: 0 !important; flex-direction: row !important; gap: 0 !important; }
    .m8 .side { width: 22%; background: color-mix(in srgb, var(--s) 85%, transparent); padding: 3cqw 2cqw; display: flex; flex-direction: column; gap: 1.4cqw; }
    .m8 .pv-logo { height: 5cqw; width: 5cqw; border-radius: 1cqw; object-fit: cover; }
    .m8 .brand { display: flex; align-items: center; gap: 1cqw; font-weight: 700; font-size: 1.8cqw; margin-bottom: 1.4cqw; min-width: 0; }
    .m8 .brand span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .m8 .menu div { font-size: 1.5cqw; padding: 1.1cqw 1.4cqw; border-radius: calc(var(--r) * .6); opacity: .85; margin-bottom: .6cqw; }
    .m8 .menu div.on { background: var(--p); opacity: 1; font-weight: 600; }
    .m8 .main { flex: 1; padding: 3cqw; display: flex; flex-direction: column; gap: 2.2cqw; min-width: 0; }
    .m8 .banner { height: 40%; border-radius: var(--r); background: linear-gradient(120deg, var(--p), var(--a)); background-size: cover; background-position: center; display: flex; align-items: flex-end; padding: 1.6cqw; }
    .m8 .banner b { font-size: 2.2cqw; text-shadow: 0 .2cqw .8cqw rgba(0,0,0,.5); }
    .m8 .rt { font-size: 1.5cqw; font-weight: 600; margin-bottom: .8cqw; }
    .m8 .row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.4cqw; }
    .m8 .tile { aspect-ratio: 16 / 10; border-radius: var(--r); background: var(--s); position: relative; overflow: hidden; border: .2cqw solid transparent; }
    .m8 .tile.focus { border-color: var(--p); box-shadow: 0 0 0 .4cqw color-mix(in srgb, var(--p) 35%, transparent); }
    .m8 .tile .live { position: absolute; top: 8%; left: 8%; background: var(--a); color: #000; font-size: .9cqw; font-weight: 700; padding: .2cqw .6cqw; border-radius: .4cqw; }
    .m8 .tile .bar { position: absolute; left: 0; bottom: 0; height: 6%; width: 55%; background: var(--a); }
</style>
<?php }

/**
 * Abre o contêiner .tv com as variáveis do modelo e devolve o HTML da tela.
 * $hero: imagem do pôster (MovieUI). $ids = false remove ids (miniaturas da lista).
 */
function theme_preview_box($base, $settings, $logo_url, $brand, $img_base, $hero, $extra_class = '', $ids = true){
    $css = theme_model_css($base, $settings);
    $bg = (isset($settings['theme_bg_image']) && $settings['theme_bg_image'] != '') ? $img_base.$settings['theme_bg_image'] : '';
    $banner = (isset($settings['theme_banner']) && $settings['theme_banner'] != '') ? $img_base.$settings['theme_banner'] : '';
    ob_start();
    theme_preview_markup($base, $logo_url, $brand, $bg, $banner, $hero);
    $html = ob_get_clean();
    if (!$ids) $html = preg_replace('/ id="tv_[a-z]+"/', '', $html);
    $class = trim('tv '.$extra_class.' '.$css['class'].(isset($settings['theme_show_name']) && $settings['theme_show_name'] == '0' ? ' hide-name' : ''));
    return '<div class="'.$class.'"'.($ids ? ' id="tv"' : '').' data-m="'.htmlspecialchars($base).'" style="'.htmlspecialchars($css['style']).'">'.$html.'</div>';
}

// Miniatura de um tema salvo (cartões de "Meus temas")
function theme_mini_preview($settings, $base, $img_base, $fallback_logo, $default_name, $hero){
    $logo = (isset($settings['theme_logo']) && $settings['theme_logo'] != '') ? $img_base.$settings['theme_logo'] : $fallback_logo;
    $brand = (isset($settings['theme_brand_text']) && $settings['theme_brand_text'] != '') ? $settings['theme_brand_text'] : $default_name;
    echo theme_preview_box($base, $settings, $logo, $brand, $img_base, $hero, 'tv--thumb', false);
}

function theme_preview_markup($theme_id, $logo_url, $brand, $bg_url, $banner_url, $hero){
    $date = date('D, j M Y');
    $logo = '<img class="pv-logo" id="tv_logo" src="'.htmlspecialchars($logo_url).'" alt="" onerror="this.style.visibility=\'hidden\'">';
    $name = '<span class="pv-name" id="tv_name">'.htmlspecialchars($brand).'</span>';
    $icons_full = '<div class="pv-icons"><i class="ri-wifi-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div>';
    $icons_box = '<div class="pv-icons"><i class="ri-router-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div>';
    $foot = '<div class="pv-foot"><span>Expiration : Unlimited</span><span>Username: cliente</span></div>';
    $rf = '<span class="pv-refresh"><i class="ri-refresh-line"></i></span>';
    $bgdiv = '<div class="tv__bgimg" id="tv_bgimg" style="'.($bg_url != '' ? "background-image:url('".htmlspecialchars($bg_url)."')" : '').'"></div>';

    switch ((string)$theme_id) {
        case '1': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m1">
                <div class="pv-head"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><?php echo $icons_full; ?></div>
                <div class="grid">
                    <div class="card live big pat"><span class="pv-count">9</span><i class="ri-tv-2-line"></i><b>LIVE TV</b><div class="bar"><span class="pv-upd">Last updated: 0 sec ago</span><?php echo $rf; ?></div></div>
                    <div class="card mov pat"><span class="pv-count">5</span><i class="ri-film-line"></i><b>MOVIES</b><div class="bar"><span class="pv-upd">Last updated</span><?php echo $rf; ?></div></div>
                    <div class="card ser pat"><span class="pv-count">4</span><i class="ri-clapperboard-line"></i><b>SERIES</b><div class="bar"><span class="pv-upd">Last updated</span><?php echo $rf; ?></div></div>
                    <div class="row2">
                        <div class="btn3"><i class="ri-file-list-3-line"></i>LIVE WITH EPG</div>
                        <div class="btn3"><i class="ri-layout-grid-line"></i>MULTISCREEN</div>
                        <div class="btn3"><i class="ri-history-line"></i>CATCH UP</div>
                    </div>
                </div>
                <?php echo $foot; ?>
            </div>
        <?php break;
        case '2': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m2">
                <div class="pv-head"><?php echo $logo.$name; ?><span class="pv-date" style="margin-left:auto;"><?php echo $date; ?></span><span class="head-c"><i class="ri-notification-3-fill"></i></span><span class="head-c"><i class="ri-wifi-line"></i></span></div>
                <div class="body">
                    <div class="circles">
                        <span class="circ"><i class="ri-download-2-line"></i></span><span class="circ"><i class="ri-radio-line"></i></span><span class="circ"><i class="ri-user-line"></i></span><span class="circ"><i class="ri-group-line"></i></span><span class="circ"><i class="ri-settings-3-line"></i></span>
                    </div>
                    <div class="list">
                        <div class="li"><span><i class="ri-history-line"></i></span>CATCH UP</div>
                        <div class="li"><span><i class="ri-layout-grid-line"></i></span>MULTISCREEN</div>
                        <div class="li"><span><i class="ri-file-list-3-line"></i></span>LIVE WITH EPG</div>
                    </div>
                    <div class="rows">
                        <div class="rowc"><b>LIVE TV</b><i class="ri-tv-2-line"></i><div class="upd"><span class="pv-upd">Last updated: 3 mins ago</span><?php echo $rf; ?></div></div>
                        <div class="rowc"><b>MOVIES</b><i class="ri-play-circle-line"></i><div class="upd"><span class="pv-upd">Last updated: 3 mins ago</span><?php echo $rf; ?></div></div>
                        <div class="rowc"><b>SERIES</b><i class="ri-movie-line"></i><div class="upd"><span class="pv-upd">Last updated: 2 mins ago</span><?php echo $rf; ?></div></div>
                    </div>
                </div>
                <?php echo $foot; ?>
            </div>
        <?php break;
        case '3': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m3">
                <div class="pv-head"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><div class="pv-icons"><i class="ri-wifi-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-radio-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div></div>
                <div class="row1">
                    <div class="card"><span class="ic"><i class="ri-tv-2-line"></i></span><b>LIVE TV</b><div class="bar"><span class="pv-upd">Last updated: 4 mins ago</span><?php echo $rf; ?></div></div>
                    <div class="card"><span class="ic"><i class="ri-play-circle-line"></i></span><b>MOVIES</b><div class="bar"><span class="pv-upd">Last updated: 3 mins ago</span><?php echo $rf; ?></div></div>
                    <div class="card"><span class="ic"><i class="ri-movie-line"></i></span><b>SERIES</b><div class="bar"><span class="pv-upd">Last updated: 3 mins ago</span><?php echo $rf; ?></div></div>
                </div>
                <div class="row2">
                    <div class="btn"><i class="ri-file-list-3-line"></i>LIVE WITH EPG</div>
                    <div class="btn"><i class="ri-layout-grid-line"></i>MULTISCREEN</div>
                    <div class="btn"><i class="ri-history-line"></i>CATCH UP</div>
                </div>
                <?php echo $foot; ?>
            </div>
        <?php break;
        case '4': ?>
            <div class="m4-hero" style="background-image:url('<?php echo htmlspecialchars($hero); ?>');"></div>
            <div class="tv__layer m4">
                <div class="pv-head"><?php echo $logo.$name; ?><div class="pv-icons"><i class="ri-wifi-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-radio-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div></div>
                <div class="tiles">
                    <div class="tile"><i class="ri-tv-2-line"></i>LIVE TV</div>
                    <div class="tile"><i class="ri-play-circle-line"></i>MOVIES</div>
                    <div class="tile"><i class="ri-movie-line"></i>SERIES</div>
                    <div class="tile"><i class="ri-file-list-3-line"></i>LIVE WITH EPG</div>
                    <div class="tile"><i class="ri-layout-grid-line"></i>MULTISCREEN</div>
                </div>
            </div>
        <?php break;
        case '5': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m5">
                <div class="pv-head"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><?php echo $icons_box; ?></div>
                <div class="grid">
                    <div class="card c-live pat"><span class="pv-count">6</span><i class="ri-tv-2-line"></i><b>LIVE TV</b><span class="pv-upd">Last updated: 14 sec ago</span><?php echo $rf; ?></div>
                    <div class="card c-mov pat"><span class="pv-count">5</span><i class="ri-film-line"></i><b>MOVIES</b><span class="pv-upd">Last updated: 10 sec ago</span><?php echo $rf; ?></div>
                    <div class="card c-ser pat"><span class="pv-count">5</span><i class="ri-clapperboard-line"></i><b>SERIES</b><span class="pv-upd">Last updated: 7 sec ago</span><?php echo $rf; ?></div>
                    <div class="side">
                        <div class="sbtn"><i class="ri-file-list-3-line"></i>LIVE WITH EPG</div>
                        <div class="sbtn"><i class="ri-layout-grid-line"></i>MULTISCREEN</div>
                        <div class="sbtn"><i class="ri-history-line"></i>CATCH UP</div>
                    </div>
                </div>
                <?php echo $foot; ?>
            </div>
        <?php break;
        case '6': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m6">
                <div class="snow"></div>
                <div class="pv-head pv-boxed"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><?php echo $icons_box; ?></div>
                <div class="grid">
                    <div class="card c-live pat"><span class="decor" style="left:4%;bottom:3%;">🎅</span><span class="decor" style="left:10%;top:6%;font-size:4cqw;">🎄</span><span class="pv-count">6</span><i class="ri-tv-2-line"></i><b>LIVE TV</b><span class="pv-upd">Last updated: 6 mins ago</span><span class="rf"><i class="ri-refresh-line"></i></span></div>
                    <div class="card c-mov pat"><span class="decor" style="left:12%;top:6%;font-size:4.5cqw;">🌲</span><span class="pv-count">5</span><i class="ri-film-line"></i><b>MOVIES</b><span class="pv-upd">Last updated: 8 mins ago</span><span class="rf"><i class="ri-refresh-line"></i></span></div>
                    <div class="card c-ser pat"><span class="decor" style="right:4%;bottom:3%;">⛄</span><span class="decor" style="left:12%;top:6%;font-size:4cqw;">🎁</span><span class="pv-count">5</span><i class="ri-clapperboard-line"></i><b>SERIES</b><span class="pv-upd">Last updated: 10 mins ago</span><span class="rf"><i class="ri-refresh-line"></i></span></div>
                    <div class="side"><div class="sbtn"><i class="ri-file-list-3-line"></i></div><div class="sbtn"><i class="ri-layout-grid-line"></i></div><div class="sbtn"><i class="ri-history-line"></i></div></div>
                </div>
            </div>
        <?php break;
        case '7': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m7">
                <div class="pv-head pv-boxed"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><?php echo $icons_box; ?></div>
                <div class="grid">
                    <div class="card c-live pat"><span class="decor" style="left:5%;top:4%;font-size:5cqw;">🕸️</span><span class="pv-count">6</span><span class="decor" style="position:relative;font-size:8cqw;">🎃</span><i class="ri-tv-2-line alt"></i><b>LIVE TV</b><span class="pv-upd">Last updated: 6 mins ago</span><span class="rf"><i class="ri-refresh-line"></i></span></div>
                    <div class="side"><div class="sbtn"><i class="ri-ghost-line"></i></div><div class="sbtn"><i class="ri-cake-3-line"></i></div><div class="sbtn"><i class="ri-emotion-laugh-line"></i></div></div>
                    <div class="card c-mov pat"><span class="pv-count">5</span><span class="decor" style="position:relative;font-size:8cqw;">🦇</span><i class="ri-film-line alt"></i><b>MOVIES</b><span class="pv-upd">Last updated: 8 mins ago</span><span class="rf"><i class="ri-refresh-line"></i></span></div>
                    <div class="card c-ser pat"><span class="decor" style="right:5%;top:4%;font-size:5cqw;">🕸️</span><span class="pv-count">5</span><span class="decor" style="position:relative;font-size:8cqw;">🧪</span><i class="ri-clapperboard-line alt"></i><b>SERIES</b><span class="pv-upd">Last updated: 10 mins ago</span><span class="rf"><i class="ri-refresh-line"></i></span></div>
                </div>
            </div>
        <?php break;
        default: ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m8">
                <div class="side">
                    <div class="brand"><img class="pv-logo" id="tv_logo" src="<?php echo htmlspecialchars($logo_url); ?>" alt="" onerror="this.style.visibility='hidden'"><span id="tv_name"><?php echo htmlspecialchars($brand); ?></span></div>
                    <div class="menu"><div class="on">Início</div><div>TV ao Vivo</div><div>Filmes</div><div>Séries</div><div>Favoritos</div><div>Configurações</div></div>
                </div>
                <div class="main">
                    <div class="banner" id="tv_banner" style="<?php echo $banner_url != '' ? "background-image:url('".htmlspecialchars($banner_url)."')" : ''; ?>"><b>Destaques da semana</b></div>
                    <div><div class="rt">Canais ao vivo</div><div class="row"><div class="tile focus"><span class="live">AO VIVO</span></div><div class="tile"><span class="live">AO VIVO</span></div><div class="tile"></div><div class="tile"></div></div></div>
                    <div><div class="rt">Continuar assistindo</div><div class="row"><div class="tile"><span class="bar"></span></div><div class="tile"><span class="bar" style="width:30%"></span></div><div class="tile"></div><div class="tile"></div></div></div>
                </div>
            </div>
        <?php
    }
}
