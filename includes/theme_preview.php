<?php
/**
 * Pré-visualização fiel de cada modelo de tema (tela inicial do app, em proporção de TV).
 * Cores vêm de variáveis CSS (--p principal, --a destaque, --bg fundo, --s cartões, --t texto, --r cantos),
 * atualizadas ao vivo pelo editor. Com "manter cores originais", o JS aplica a paleta original do modelo.
 */

// Paleta original de cada modelo (aproximada a partir das telas do app)
function theme_model_originals(){
    return array(
        '1' => array('p' => '#4DB6AC', 'a' => '#E8505B', 'bg' => '#0A0A1C', 's' => '#16163A', 't' => '#FFFFFF'),
        '2' => array('p' => '#FF6B35', 'a' => '#FF8A3D', 'bg' => '#18202C', 's' => '#222B3A', 't' => '#FFFFFF'),
        '3' => array('p' => '#DC3545', 'a' => '#DC3545', 'bg' => '#141414', 's' => '#3F3F3F', 't' => '#FFFFFF'),
        '4' => array('p' => '#3A0A10', 'a' => '#C81E3A', 'bg' => '#1A0508', 's' => '#3A0A10', 't' => '#FFFFFF'),
        '5' => array('p' => '#E53935', 'a' => '#00E676', 'bg' => '#0B0B0B', 's' => '#141414', 't' => '#FFFFFF'),
        '6' => array('p' => '#E53935', 'a' => '#00E676', 'bg' => '#0B0E22', 's' => '#12152B', 't' => '#FFFFFF'),
        '7' => array('p' => '#FF8F1F', 'a' => '#7E57C2', 'bg' => '#0B0E22', 's' => '#12152B', 't' => '#FFFFFF'),
        '8' => array('p' => '#3B6CF6', 'a' => '#22C55E', 'bg' => '#0B1220', 's' => '#131C2E', 't' => '#FFFFFF'),
    );
}

function theme_preview_styles(){ ?>
<style>
    .tv { --p: #3B6CF6; --a: #22C55E; --bg: #0B1220; --s: #131C2E; --t: #FFFFFF; --r: 14px; --ov: .6;
          container-type: inline-size; position: relative; aspect-ratio: 16 / 9; border-radius: 16px; overflow: hidden;
          background: var(--bg); color: var(--t); box-shadow: 0 0 0 8px #111827, 0 18px 40px rgba(0,0,0,.25); font-family: inherit; }
    .tv * { box-sizing: border-box; }
    .tv.hide-name #tv_name, .tv.hide-name .pv-name, .tv.hide-name .m8 .brand span { display: none; }
    .tv.tv--thumb { border-radius: 0; box-shadow: none; pointer-events: none; }
    .tv__bgimg { position: absolute; inset: 0; background-size: cover; background-position: center; }
    .tv__bgimg::after { content: ''; position: absolute; inset: 0; background: var(--bg); opacity: var(--ov); }
    .tv__layer { position: absolute; inset: 0; padding: 3.2cqw 3.4cqw; display: flex; flex-direction: column; gap: 2.4cqw; }
    .tv i { line-height: 1; }

    /* Cabeçalho comum: logo, nome, data e ícones */
    .pv-head { display: flex; align-items: center; gap: 1.6cqw; }
    .pv-head .pv-logo { height: 5.4cqw; width: 5.4cqw; border-radius: 1cqw; object-fit: contain; background: rgba(255,255,255,.06); }
    .pv-head .pv-name { font-size: 2.6cqw; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 26cqw; }
    .pv-head .pv-date { font-size: 1.8cqw; opacity: .95; margin-left: 4cqw; white-space: nowrap; }
    .pv-head .pv-icons { margin-left: auto; display: flex; gap: 2.2cqw; font-size: 2.6cqw; }
    .pv-head.pv-boxed { background: color-mix(in srgb, var(--s) 80%, transparent); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); padding: 1cqw 1.6cqw; }
    .pv-foot { display: flex; justify-content: space-between; font-size: 2cqw; margin-top: auto; }
    .pv-refresh { display: inline-flex; align-items: center; justify-content: center; width: 5cqw; height: 2.8cqw; border-radius: 2cqw; background: #fff; color: #111; font-size: 1.8cqw; }
    .pv-upd { font-size: 1.2cqw; opacity: .85; }
    .pv-count { position: absolute; top: .8cqw; right: .8cqw; font-size: 1.2cqw; font-weight: 700; padding: .2cqw .6cqw; border-radius: .5cqw; border: .12cqw solid rgba(255,255,255,.35); background: rgba(0,0,0,.25); }

    /* 1 OneUI */
    .m1 .grid { flex: 1; display: grid; grid-template-columns: 1.05fr 1fr 1fr; grid-template-rows: 1.45fr 1fr; gap: 1.8cqw; min-height: 0; }
    .m1 .big { grid-row: span 2; }
    .m1 .card { position: relative; border-radius: var(--r); overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 800; }
    .m1 .card > i { font-size: 5.6cqw; }
    .m1 .big > i { font-size: 8cqw; }
    .m1 .card b { font-size: 2.6cqw; margin-top: .4cqw; margin-bottom: 3.6cqw; }
    .m1 .big b { font-size: 3.4cqw; }
    .m1 .card .bar { position: absolute; left: 0; right: 0; bottom: 0; padding: .8cqw 1.4cqw; background: rgba(0,0,0,.18); display: flex; align-items: center; justify-content: space-between; font-weight: 400; }
    .m1 .live { background: linear-gradient(170deg, #2EC987, #3557D6); }
    .m1 .mov { background: linear-gradient(170deg, #E8325B, #F08A3B); }
    .m1 .ser { background: linear-gradient(170deg, #9F3FD8, #4969E0); }
    .tv.custom .m1 .live { background: linear-gradient(170deg, var(--a), var(--p)); }
    .tv.custom .m1 .mov { background: linear-gradient(170deg, var(--p), color-mix(in srgb, var(--p) 55%, #000)); }
    .tv.custom .m1 .ser { background: linear-gradient(170deg, color-mix(in srgb, var(--a) 70%, var(--p)), var(--s)); }
    .m1 .btn3 { grid-column: span 1; background: var(--p); border-radius: var(--r); display: flex; align-items: center; justify-content: center; gap: 1cqw; font-size: 1.9cqw; font-weight: 800; }
    .m1 .row2 { grid-column: 2 / span 2; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.8cqw; }

    /* 2 Glossy */
    .m2 .body { flex: 1; display: grid; grid-template-columns: 7cqw 1.05fr 2fr; gap: 2.4cqw; min-height: 0; }
    .m2 .circles { display: flex; flex-direction: column; gap: 1.4cqw; }
    .m2 .circ { width: 6.6cqw; height: 6.6cqw; border-radius: 50%; background: var(--p); display: flex; align-items: center; justify-content: center; font-size: 3cqw; }
    .m2 .head-c { width: 6.6cqw; height: 6.6cqw; border-radius: 50%; background: var(--p); display: inline-flex; align-items: center; justify-content: center; font-size: 3cqw; }
    .m2 .list { background: var(--s); border-radius: var(--r); padding: 1cqw; display: flex; flex-direction: column; gap: 1cqw; }
    .m2 .li { flex: 1; background: color-mix(in srgb, var(--s) 70%, #000); border-radius: calc(var(--r) * .7); display: flex; align-items: center; gap: 1.4cqw; padding: 0 1.4cqw; font-size: 2cqw; }
    .m2 .li span { width: 5.6cqw; height: 5.6cqw; border-radius: calc(var(--r) * .5); background: color-mix(in srgb, var(--s) 70%, #fff 12%); display: flex; align-items: center; justify-content: center; font-size: 2.8cqw; }
    .m2 .rows { display: flex; flex-direction: column; gap: 1.6cqw; }
    .m2 .rowc { flex: 1; border: .3cqw solid var(--p); border-radius: var(--r); background: var(--s); display: flex; align-items: center; gap: 2cqw; padding: 0 2cqw; }
    .m2 .rowc b { font-size: 2.4cqw; font-weight: 800; width: 12.5cqw; white-space: nowrap; }
    .m2 .rowc > i { font-size: 4.6cqw; }
    .m2 .rowc .upd { margin-left: auto; background: rgba(0,0,0,.25); border-radius: calc(var(--r) * .6); padding: .8cqw 1.2cqw; display: flex; align-items: center; gap: 1cqw; }

    /* 3 Black Panther */
    .m3 .row1 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3cqw; flex: 1.6; min-height: 0; }
    .m3 .card { position: relative; background: var(--s); border: .15cqw solid rgba(255,255,255,.25); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1cqw; border-radius: calc(var(--r) * .3); }
    .m3 .card .ic { width: 9cqw; height: 9cqw; border-radius: 50%; background: var(--p); display: flex; align-items: center; justify-content: center; font-size: 4.6cqw; }
    .m3 .card b { font-size: 2.4cqw; font-weight: 800; }
    .m3 .card .bar { width: 100%; margin-top: auto; padding: .8cqw 1.4cqw; background: rgba(0,0,0,.25); display: flex; align-items: center; justify-content: space-between; }
    .m3 .row2 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3cqw; flex: .55; }
    .m3 .btn { background: var(--s); border: .15cqw solid rgba(255,255,255,.35); border-left: .5cqw solid var(--p); display: flex; align-items: center; justify-content: center; gap: 1cqw; font-weight: 800; font-size: 2cqw; border-radius: calc(var(--r) * .3); }

    /* 4 MovieUI */
    .m4 .hero { position: absolute; inset: 0; background-size: cover; background-position: center top; }
    .m4 .hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,.1) 40%, rgba(0,0,0,.65)); }
    .m4 .pv-head { position: relative; background: rgba(0,0,0,.35); border-radius: var(--r); padding: 1cqw 1.6cqw; }
    .m4 .tiles { position: relative; margin-top: auto; display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.8cqw; }
    .m4 .tile { background: var(--s); border-radius: var(--r); aspect-ratio: 1.55; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .8cqw; font-weight: 800; font-size: 1.8cqw; }
    .m4 .tile i { font-size: 4cqw; }

    /* 5 VUI */
    .m5 .grid { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr) .85fr; gap: 2cqw; min-height: 0; }
    .m5 .card { position: relative; background: var(--s); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5cqw; }
    .m5 .card .pv-refresh, .m6 .card .rf, .m7 .card .rf { margin-top: .9cqw; flex: 0 0 auto; }
    .m5 .card > i { font-size: 6.2cqw; }
    .m5 .card b { font-size: 2.7cqw; font-weight: 800; }
    .m5 .c-live > i { color: #E53935; } .m5 .c-mov > i { color: #00E676; } .m5 .c-ser > i { color: #FF9800; }
    .tv.custom .m5 .c-live > i { color: var(--p); } .tv.custom .m5 .c-mov > i { color: var(--a); } .tv.custom .m5 .c-ser > i { color: color-mix(in srgb, var(--p) 50%, var(--a)); }
    .m5 .side { display: flex; flex-direction: column; gap: 2cqw; }
    .m5 .sbtn { flex: 1; background: var(--s); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); display: flex; align-items: center; justify-content: center; gap: 1cqw; font-weight: 800; font-size: 1.8cqw; }

    /* 6 Christmas / 7 Halloween */
    .m6 .grid, .m7 .grid { flex: 1; display: grid; gap: 2cqw; min-height: 0; }
    .m6 .grid { grid-template-columns: repeat(3, 1fr) .32fr; }
    .m7 .grid { grid-template-columns: 1fr .32fr 1fr 1fr; }
    .m6 .card, .m7 .card { position: relative; background: var(--s); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5cqw; overflow: hidden; }
    .m6 .card > i, .m7 .card > i { font-size: 6cqw; }
    .m6 .card b, .m7 .card b { font-size: 2.6cqw; font-weight: 800; }
    .m6 .card .rf, .m7 .card .rf { width: 5cqw; height: 2.8cqw; border-radius: 2cqw; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8cqw; color: #fff; }
    .m6 .side, .m7 .side { display: flex; flex-direction: column; gap: 2cqw; }
    .m6 .sbtn, .m7 .sbtn { flex: 1; background: var(--s); border: .15cqw solid rgba(255,255,255,.08); border-radius: var(--r); display: flex; align-items: center; justify-content: center; font-size: 3.4cqw; }
    .m6 .k1 { color: #E53935; } .m6 .k2 { color: #00E676; } .m6 .k3 { color: #FF9800; }
    .m7 .k1 { color: #FF8F1F; } .m7 .k2 { color: #7E57C2; } .m7 .k3 { color: #00C853; }
    .tv.custom .m6 .k1, .tv.custom .m7 .k1 { color: var(--p); }
    .tv.custom .m6 .k2, .tv.custom .m7 .k2 { color: var(--a); }
    .tv.custom .m6 .k3, .tv.custom .m7 .k3 { color: color-mix(in srgb, var(--p) 50%, var(--a)); }
    .m6 .bk1 { background: #E53935; } .m6 .bk2 { background: #00E676; } .m6 .bk3 { background: #FF9800; }
    .m7 .bk1 { background: #FF8F1F; } .m7 .bk2 { background: #7E57C2; } .m7 .bk3 { background: #00C853; }
    .tv.custom .m6 .bk1, .tv.custom .m7 .bk1 { background: var(--p); }
    .tv.custom .m6 .bk2, .tv.custom .m7 .bk2 { background: var(--a); }
    .tv.custom .m6 .bk3, .tv.custom .m7 .bk3 { background: color-mix(in srgb, var(--p) 50%, var(--a)); }
    .m6 .snow { position: absolute; inset: 0; pointer-events: none;
                background-image: radial-gradient(circle, rgba(255,255,255,.85) .25cqw, transparent .3cqw), radial-gradient(circle, rgba(255,255,255,.6) .18cqw, transparent .22cqw);
                background-size: 11cqw 9cqw, 7cqw 6cqw; background-position: 2cqw 3cqw, 5cqw 1cqw; }

    /* 8 BestPro */
    .m8 { padding: 0 !important; flex-direction: row !important; gap: 0 !important; }
    .m8 .side { width: 22%; background: color-mix(in srgb, var(--s) 85%, transparent); padding: 3cqw 2cqw; display: flex; flex-direction: column; gap: 1.4cqw; }
    .m8 .side .pv-logo { height: 5cqw; width: 5cqw; border-radius: 1cqw; object-fit: contain; }
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
 * Mini pré-visualização de um tema salvo (cartões de "Meus temas"): mesma tela do editor, em tamanho pequeno.
 * Sem ids (para não conflitar com a pré-visualização grande do editor).
 */
function theme_mini_preview($settings, $base, $img_base, $fallback_logo, $default_name, $model_img_base){
    $orig = theme_model_originals();
    $o = isset($orig[(string)$base]) ? $orig[(string)$base] : $orig['8'];
    $keep = $settings['theme_keep_colors'] == '1' && (string)$base !== '8';
    $c = $keep ? $o : array('p' => $settings['theme_primary'], 'a' => $settings['theme_accent'], 'bg' => $settings['theme_background'], 's' => $settings['theme_surface'], 't' => $settings['theme_text']);
    $style = sprintf('--p:%s;--a:%s;--bg:%s;--s:%s;--t:%s;--r:%scqw;--ov:%s',
        $c['p'], $c['a'], $c['bg'], $c['s'], $c['t'], round((int)$settings['theme_corner'] * 0.07, 2), round((int)$settings['theme_bg_overlay'] / 100, 2));
    $catalog = theme_catalog();
    $model_img = (isset($catalog[(string)$base]) && $catalog[(string)$base]['image'] != '') ? $model_img_base.$catalog[(string)$base]['image'].'.png' : '';
    $logo = $settings['theme_logo'] != '' ? $img_base.$settings['theme_logo'] : $fallback_logo;
    $brand = $settings['theme_brand_text'] != '' ? $settings['theme_brand_text'] : $default_name;

    ob_start();
    theme_preview_markup($base, $logo, $brand,
        $settings['theme_bg_image'] != '' ? $img_base.$settings['theme_bg_image'] : '',
        $settings['theme_banner'] != '' ? $img_base.$settings['theme_banner'] : '',
        $model_img);
    $html = preg_replace('/ id="tv_[a-z]+"/', '', ob_get_clean());

    echo '<div class="tv tv--thumb'.($keep ? '' : ' custom').($settings['theme_show_name'] == '0' ? ' hide-name' : '').'" style="'.htmlspecialchars($style).'">'.$html.'</div>';
}

/**
 * $theme_id: modelo (1..8). $logo_url / $brand: logo e nome mostrados. $banner_url: banner (BestPro/MovieUI).
 * $model_img: tela original do modelo (usada como fundo do MovieUI quando não há imagem própria).
 */
function theme_preview_markup($theme_id, $logo_url, $brand, $bg_url, $banner_url, $model_img){
    $date = date('D, j M Y');
    $logo = '<img class="pv-logo" id="tv_logo" src="'.htmlspecialchars($logo_url).'" alt="" onerror="this.style.visibility=\'hidden\'">';
    $name = '<span class="pv-name" id="tv_name">'.htmlspecialchars($brand).'</span>';
    $icons_full = '<div class="pv-icons"><i class="ri-wifi-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div>';
    $foot = '<div class="pv-foot"><span>Expiration : Unlimited</span><span>Username: cliente</span></div>';
    $rf = '<span class="pv-refresh"><i class="ri-refresh-line"></i></span>';
    $bgdiv = '<div class="tv__bgimg" id="tv_bgimg" style="'.($bg_url != '' ? "background-image:url('".htmlspecialchars($bg_url)."')" : '').'"></div>';

    switch ((string)$theme_id) {
        case '1': ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer m1">
                <div class="pv-head"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><?php echo $icons_full; ?></div>
                <div class="grid">
                    <div class="card live big"><span class="pv-count">9</span><i class="ri-tv-2-line"></i><b>LIVE TV</b><div class="bar"><span class="pv-upd">Last updated: 0 sec ago</span><?php echo $rf; ?></div></div>
                    <div class="card mov"><span class="pv-count">5</span><i class="ri-film-line"></i><b>MOVIES</b><div class="bar"><span class="pv-upd">Last updated</span><?php echo $rf; ?></div></div>
                    <div class="card ser"><span class="pv-count">4</span><i class="ri-clapperboard-line"></i><b>SERIES</b><div class="bar"><span class="pv-upd">Last updated</span><?php echo $rf; ?></div></div>
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
        case '4':
            $hero = $banner_url != '' ? $banner_url : ($bg_url != '' ? $bg_url : $model_img); ?>
            <div class="tv__bgimg" id="tv_bgimg" style="display:none"></div>
            <div class="hero m4-hero" id="tv_banner" data-hero-fallback="<?php echo htmlspecialchars($model_img); ?>" data-bg="<?php echo htmlspecialchars($bg_url); ?>" data-banner="<?php echo htmlspecialchars($banner_url); ?>" style="position:absolute;inset:0;background-size:cover;background-position:center top;background-image:url('<?php echo htmlspecialchars($hero); ?>');"></div>
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
                <div class="pv-head"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><div class="pv-icons"><i class="ri-router-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div></div>
                <div class="grid">
                    <div class="card c-live"><span class="pv-count">6</span><i class="ri-tv-2-line"></i><b>LIVE TV</b><span class="pv-upd">Last updated: 14 sec ago</span><?php echo $rf; ?></div>
                    <div class="card c-mov"><span class="pv-count">5</span><i class="ri-film-line"></i><b>MOVIES</b><span class="pv-upd">Last updated: 10 sec ago</span><?php echo $rf; ?></div>
                    <div class="card c-ser"><span class="pv-count">5</span><i class="ri-clapperboard-line"></i><b>SERIES</b><span class="pv-upd">Last updated: 7 sec ago</span><?php echo $rf; ?></div>
                    <div class="side">
                        <div class="sbtn"><i class="ri-file-list-3-line"></i>LIVE WITH EPG</div>
                        <div class="sbtn"><i class="ri-layout-grid-line"></i>MULTISCREEN</div>
                        <div class="sbtn"><i class="ri-history-line"></i>CATCH UP</div>
                    </div>
                </div>
                <?php echo $foot; ?>
            </div>
        <?php break;
        case '6':
        case '7':
            $m = (string)$theme_id === '6' ? 'm6' : 'm7';
            $ic = $m == 'm6' ? array('ri-tv-2-line', 'ri-film-line', 'ri-clapperboard-line', 'ri-file-list-3-line', 'ri-layout-grid-line', 'ri-history-line')
                             : array('ri-ghost-smile-line', 'ri-bug-line', 'ri-flask-line', 'ri-ghost-line', 'ri-cake-3-line', 'ri-emotion-laugh-line'); ?>
            <?php echo $bgdiv; ?>
            <div class="tv__layer <?php echo $m; ?>">
                <?php if ($m == 'm6') { ?><div class="snow"></div><?php } ?>
                <div class="pv-head pv-boxed"><?php echo $logo.$name; ?><span class="pv-date"><?php echo $date; ?></span><div class="pv-icons"><i class="ri-router-line"></i><i class="ri-notification-3-fill"></i><i class="ri-download-2-line"></i><i class="ri-user-line"></i><i class="ri-group-line"></i><i class="ri-settings-3-line"></i></div></div>
                <div class="grid">
                    <div class="card"><span class="pv-count">6</span><i class="<?php echo $ic[0]; ?> k1"></i><b>LIVE TV</b><span class="pv-upd">Last updated: 6 mins ago</span><span class="rf bk1"><i class="ri-refresh-line"></i></span></div>
                    <?php if ($m == 'm7') { ?><div class="side"><div class="sbtn"><i class="<?php echo $ic[3]; ?>"></i></div><div class="sbtn"><i class="<?php echo $ic[4]; ?>"></i></div><div class="sbtn"><i class="<?php echo $ic[5]; ?>"></i></div></div><?php } ?>
                    <div class="card"><span class="pv-count">5</span><i class="<?php echo $ic[1]; ?> k2"></i><b>MOVIES</b><span class="pv-upd">Last updated: 8 mins ago</span><span class="rf bk2"><i class="ri-refresh-line"></i></span></div>
                    <div class="card"><span class="pv-count">5</span><i class="<?php echo $ic[2]; ?> k3"></i><b>SERIES</b><span class="pv-upd">Last updated: 10 mins ago</span><span class="rf bk3"><i class="ri-refresh-line"></i></span></div>
                    <?php if ($m == 'm6') { ?><div class="side"><div class="sbtn"><i class="<?php echo $ic[3]; ?>"></i></div><div class="sbtn"><i class="<?php echo $ic[4]; ?>"></i></div><div class="sbtn"><i class="<?php echo $ic[5]; ?>"></i></div></div><?php } ?>
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
