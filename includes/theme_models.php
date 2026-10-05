<?php
/**
 * Ficha de cada modelo de tema: quais campos ele realmente usa no app, com os valores originais.
 * Tipos: color, toggle, range, image. Cada campo vira variável CSS na pré-visualização (--f-<chave>).
 * Os valores originais vêm dos desenhos do app (res/drawable e layouts de cada tema).
 */

function theme_model_schema($base){
    $bg_gradient = array(
        'bg_top'    => array('type' => 'color', 'group' => 'Fundo', 'label' => 'Cor de cima do fundo', 'default' => '#070923'),
        'bg_bottom' => array('type' => 'color', 'group' => 'Fundo', 'label' => 'Cor de baixo do fundo', 'default' => '#020309'),
    );
    $bg_image = array(
        'theme_bg_image'   => array('type' => 'image', 'group' => 'Fundo', 'label' => 'Imagem de fundo (opcional)',
            'help' => '<b>Tamanho ideal: 1920 × 1080 px</b> (Full HD, 16:9), JPG ou WEBP até 1 MB. Prefira imagens escuras: os cartões ficam por cima. Sem imagem, o app usa as cores do fundo.'),
        'theme_bg_overlay' => array('type' => 'range', 'group' => 'Fundo', 'label' => 'Escurecer imagem de fundo', 'min' => 0, 'max' => 90, 'unit' => '%', 'default' => '60'),
    );
    $pattern = array('show_pattern' => array('type' => 'toggle', 'group' => 'Cartões', 'label' => 'Mostrar os desenhos (rabiscos) por cima dos cartões', 'default' => '1'));

    switch ((string)$base) {
        case '1': // OneUI
            return array_merge($bg_gradient, $bg_image, array(
                'live_top'      => array('type' => 'color', 'group' => 'Cartão TV ao Vivo', 'label' => 'Cor de cima', 'default' => '#36D29B'),
                'live_bottom'   => array('type' => 'color', 'group' => 'Cartão TV ao Vivo', 'label' => 'Cor de baixo', 'default' => '#3C56DE'),
                'movies_top'    => array('type' => 'color', 'group' => 'Cartão Filmes', 'label' => 'Cor de cima', 'default' => '#DB2E54'),
                'movies_bottom' => array('type' => 'color', 'group' => 'Cartão Filmes', 'label' => 'Cor de baixo', 'default' => '#F69D3F'),
                'series_top'    => array('type' => 'color', 'group' => 'Cartão Séries', 'label' => 'Cor de cima', 'default' => '#9C3CB3'),
                'series_bottom' => array('type' => 'color', 'group' => 'Cartão Séries', 'label' => 'Cor de baixo', 'default' => '#5396DC'),
                'buttons'       => array('type' => 'color', 'group' => 'Botões', 'label' => 'Botões Live with EPG, Multiscreen e Catch Up', 'default' => '#4DB6AC'),
            ), $pattern);
        case '2': // Glossy
            return array_merge(array(
                'bg'           => array('type' => 'color', 'group' => 'Fundo', 'label' => 'Cor do fundo', 'default' => '#151B27'),
            ), $bg_image, array(
                'accent_start' => array('type' => 'color', 'group' => 'Botões redondos e bordas', 'label' => 'Cor inicial do degradê', 'default' => '#FD4C3A'),
                'accent_end'   => array('type' => 'color', 'group' => 'Botões redondos e bordas', 'label' => 'Cor final do degradê', 'default' => '#FF7439'),
                'panel_top'    => array('type' => 'color', 'group' => 'Lista e faixas', 'label' => 'Cor de cima', 'default' => '#233245'),
                'panel_bottom' => array('type' => 'color', 'group' => 'Lista e faixas', 'label' => 'Cor de baixo', 'default' => '#141A26'),
            ));
        case '3': // Black Panther
            return array_merge(array(
                'bg'     => array('type' => 'color', 'group' => 'Fundo', 'label' => 'Cor do fundo', 'default' => '#111111'),
            ), $bg_image, array(
                'accent' => array('type' => 'color', 'group' => 'Detalhes', 'label' => 'Círculos dos ícones e detalhe dos botões', 'default' => '#E22F4D'),
                'card'   => array('type' => 'color', 'group' => 'Cartões', 'label' => 'Cor dos cartões e botões', 'default' => '#404040'),
            ));
        case '4': // MovieUI
            return array(
                'posters_info' => array('type' => 'info', 'group' => 'Pôsteres', 'label' => 'Pôsteres em tela cheia',
                    'help' => 'No MovieUI, o fundo é o <b>pôster de filme</b> que fica trocando sozinho. Os pôsteres são escolhidos na página <b>Pôsteres</b>: você envia os seus e marca quais aparecem no app. Tamanho ideal do pôster: <b>1920 × 1080 px</b> (16:9), com o título na parte de cima e o rodapé mais escuro.'),
                'tile_auto'    => array('type' => 'toggle', 'group' => 'Cartões', 'label' => 'Cor dos cartões automática (tirada do pôster)', 'default' => '1'),
                'tile_color'   => array('type' => 'color', 'group' => 'Cartões', 'label' => 'Cor fixa dos cartões (quando não for automática)', 'default' => '#3A0A10'),
                'topbar_dark'  => array('type' => 'range', 'group' => 'Barra do topo', 'label' => 'Escurecer a barra do topo', 'min' => 0, 'max' => 90, 'unit' => '%', 'default' => '62'),
            );
        case '5': // VUI
        case '6': // ChristmasUI
        case '7': // HalloweenUI
            $c = (string)$base === '7'
                ? array('#FF811D', '#7761D5', '#00C853')
                : array('#E62E2F', '#00E676', '#FF9100');
            $fields = array_merge($bg_gradient, $bg_image, array(
                'live_color'   => array('type' => 'color', 'group' => 'Cores das seções', 'label' => 'TV ao Vivo (ícone e botão)', 'default' => $c[0]),
                'movies_color' => array('type' => 'color', 'group' => 'Cores das seções', 'label' => 'Filmes (ícone e botão)', 'default' => $c[1]),
                'series_color' => array('type' => 'color', 'group' => 'Cores das seções', 'label' => 'Séries (ícone e botão)', 'default' => $c[2]),
            ), $pattern);
            if ((string)$base === '6') {
                $fields['show_decor'] = array('type' => 'toggle', 'group' => 'Decoração', 'label' => 'Mostrar decorações de Natal (Papai Noel, boneco de neve, bolas)', 'default' => '1');
                $fields['show_snow']  = array('type' => 'toggle', 'group' => 'Decoração', 'label' => 'Mostrar neve caindo', 'default' => '1');
            }
            if ((string)$base === '7') {
                $fields['show_decor'] = array('type' => 'toggle', 'group' => 'Decoração', 'label' => 'Mostrar decorações de Halloween (abóbora, morcego, caldeirão, teias)', 'default' => '1');
            }
            return $fields;
        default: // 8 BestPro (layout próprio, totalmente personalizável)
            return array_merge(array(
                'theme_primary'    => array('type' => 'color', 'group' => 'Cores', 'label' => 'Cor principal (botões, item selecionado, foco)', 'default' => '#3B6CF6'),
                'theme_accent'     => array('type' => 'color', 'group' => 'Cores', 'label' => 'Cor de destaque (AO VIVO, progresso)', 'default' => '#22C55E'),
                'theme_background' => array('type' => 'color', 'group' => 'Cores', 'label' => 'Fundo', 'default' => '#0B1220'),
                'theme_surface'    => array('type' => 'color', 'group' => 'Cores', 'label' => 'Cartões e menus', 'default' => '#131C2E'),
                'theme_text'       => array('type' => 'color', 'group' => 'Cores', 'label' => 'Texto', 'default' => '#FFFFFF'),
                'theme_corner'     => array('type' => 'range', 'group' => 'Cores', 'label' => 'Arredondamento dos cartões', 'min' => 0, 'max' => 32, 'unit' => 'px', 'default' => '14'),
            ), $bg_image, array(
                'theme_banner'     => array('type' => 'image', 'group' => 'Banner', 'label' => 'Banner de destaque',
                    'help' => '<b>Tamanho ideal: 1280 × 480 px</b> (8:3), JPG ou WEBP. Mantenha textos e rostos no centro: as bordas podem ser cortadas em telas menores.'),
            ));
    }
}

// Campos comuns a todos os modelos (identificação e logo)
function theme_common_defaults(){
    return array(
        'theme_brand_text' => '',
        'theme_show_name'  => '1',
        'theme_logo'       => '',
    );
}

// Valores originais do modelo (comuns + campos do modelo)
function theme_model_defaults($base){
    $out = theme_common_defaults();
    foreach (theme_model_schema($base) as $key => $f) {
        if ($f['type'] == 'info') continue;
        $out[$key] = $f['type'] == 'image' ? '' : (string)(isset($f['default']) ? $f['default'] : '');
    }
    return $out;
}

// Variáveis CSS para a pré-visualização (--f-<chave>) e classes de liga/desliga
function theme_model_css($base, $settings){
    $vars = array();
    $classes = array();
    foreach (theme_model_schema($base) as $key => $f) {
        $v = isset($settings[$key]) ? $settings[$key] : (isset($f['default']) ? $f['default'] : '');
        if ($f['type'] == 'color' && preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) $vars[] = '--f-'.$key.':'.$v;
        if ($f['type'] == 'range') $vars[] = '--f-'.$key.':'.((int)$v / 100);
        if ($f['type'] == 'toggle' && $v != '1') $classes[] = 'off-'.str_replace('_', '-', $key);
    }
    $corner = isset($settings['theme_corner']) ? (int)$settings['theme_corner'] : 14;
    $vars[] = '--r:'.round($corner * 0.07, 2).'cqw';
    $ov = isset($settings['theme_bg_overlay']) ? (int)$settings['theme_bg_overlay'] : 60;
    $vars[] = '--ov:'.round($ov / 100, 2);
    return array('style' => implode(';', $vars), 'class' => implode(' ', $classes));
}

// Primeiro pôster ativo da galeria Movie UI (para a pré-visualização do MovieUI)
function theme_movie_poster($mysqli, $scope = 0){
    $t = mysqli_query($mysqli, "SHOW TABLES LIKE 'tbl_poster_gallery'");
    if (!$t || mysqli_num_rows($t) == 0) return '';
    // Cada dono vê só os próprios pôsteres (admin = 0, revenda = id)
    $c = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_poster_gallery` LIKE 'reseller_id'");
    $owner = ($c && mysqli_num_rows($c) > 0) ? " AND reseller_id = ".(int)$scope : "";
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT poster_image FROM tbl_poster_gallery WHERE status = '1'".$owner." ORDER BY id DESC LIMIT 1"));
    return ($r && $r['poster_image'] != '') ? $r['poster_image'] : '';
}
