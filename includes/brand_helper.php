<?php
/**
 * Marca padrão IPTV Best Pro: substitui o nome e o logo "StreamBox"/nemosofts que vieram com o script.
 * Roda uma única vez (flag em tbl_panel_config). Depois disso, o admin altera em Settings normalmente.
 */

define('BRAND_NAME', 'IPTV Best Pro');
define('BRAND_LOGO', 'iptvbestpro_logo.png');
define('BRAND_ICON', 'iptvbestpro_icon.png');
define('BRAND_FULL', 'iptvbestpro_full.jpg');

function brand_apply_defaults($mysqli){
    if (panel_config_get($mysqli, 'brand_applied_v1', '0') == '1') return;

    // Copia as imagens da marca para a pasta images/ (volume persistente)
    $src = __DIR__.'/../assets/images/brand/';
    $dst = __DIR__.'/../images/';
    $files = array('iptvbestpro_logo.png' => BRAND_LOGO, 'iptvbestpro_icon_192.png' => BRAND_ICON, 'iptvbestpro_full.jpg' => BRAND_FULL);
    foreach ($files as $from => $to) {
        if (is_file($src.$from) && !is_file($dst.$to)) @copy($src.$from, $dst.$to);
    }
    if (!is_file($dst.BRAND_LOGO)) return; // tenta de novo na próxima vez

    $name = BRAND_NAME;
    $year = date('Y');

    // Configurações do app
    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_settings SET app_name = ?, app_logo = ?, app_author = ?, app_developed_by = ?,
        app_email = IF(app_email LIKE '%nemosofts%', '', app_email),
        app_website = IF(app_website LIKE '%nemosofts%', '', app_website),
        app_contact = IF(app_contact = '0356525684', '', app_contact),
        app_description = REPLACE(REPLACE(app_description, 'StreamBox', ?), 'nemosofts', ?)
        WHERE id = 1");
    $logo = BRAND_LOGO;
    mysqli_stmt_bind_param($stmt, 'ssssss', $name, $logo, $name, $name, $name, $name);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Configurações do site (nome, textos, logos e favicon)
    $t = mysqli_query($mysqli, "SHOW TABLES LIKE 'tbl_web_settings'");
    if ($t && mysqli_num_rows($t) > 0) {
        $desc = $name.' é o player de IPTV para assistir TV ao vivo, filmes e séries no celular, TV Box, Fire Stick e Smart TV.';
        $keywords = 'iptv, iptv best pro, tv ao vivo, filmes, séries';
        $copyright = 'Copyright © '.$year.' '.$name.'. Todos os direitos reservados.';
        $about = '<div>'.$name.' é um player de IPTV. Este serviço é oferecido para uso conforme disponibilizado.</div>';
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_web_settings SET site_name = ?, site_description = ?, site_keywords = ?, copyright_text = ?,
            web_logo_1 = ?, web_logo_2 = ?, web_favicon = ?,
            contact_email = IF(contact_email LIKE '%nemosofts%', '', contact_email),
            about_content = IF(about_content LIKE '%nemosofts%' OR about_content LIKE '%tamilaudiopro%', ?, about_content)
            WHERE id = 1");
        $l1 = BRAND_LOGO; $l2 = BRAND_FULL; $fav = BRAND_ICON;
        mysqli_stmt_bind_param($stmt, 'ssssssss', $name, $desc, $keywords, $copyright, $l1, $l2, $fav, $about);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    panel_config_set($mysqli, 'brand_applied_v1', '1');
}
