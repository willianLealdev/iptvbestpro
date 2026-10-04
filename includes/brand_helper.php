<?php
/**
 * Marca padrão IPTV Best Pro: substitui o nome e o logo "StreamBox"/nemosofts que vieram com o script.
 * Roda uma única vez (flag em tbl_panel_config). Depois disso, o admin altera em Settings normalmente.
 */

define('BRAND_NAME', 'IPTV Best Pro');
define('BRAND_LOGO', 'iptvbestpro_logo.png');
define('BRAND_ICON', 'iptvbestpro_icon.png');
define('BRAND_FULL', 'iptvbestpro_full.jpg');

/**
 * Logo oficial usado em TODAS as telas (login do admin, painel, revenda e futuro portal do cliente).
 * Vem de Settings (APP_LOGO); se o arquivo não existir, usa o logo IPTV Best Pro que acompanha o sistema.
 * $prefix: caminho até a raiz do painel ('' no painel, '../' em /revenda).
 */
function brand_logo_url($prefix = ''){
    $root = __DIR__.'/../';
    if (defined('APP_LOGO') && APP_LOGO != '' && APP_LOGO != 'errors' && is_file($root.'images/'.APP_LOGO)) return $prefix.'images/'.APP_LOGO;
    return $prefix.'assets/images/brand/iptvbestpro_logo.png';
}

function brand_name(){
    return (defined('APP_NAME') && APP_NAME != '' && APP_NAME != 'Errors extension') ? APP_NAME : BRAND_NAME;
}

// Bloco padrão de marca das telas de entrada (logo + nome), igual em todas
function brand_auth_header($prefix = '', $subtitle = ''){ ?>
    <div class="text-center mb-4">
        <img src="<?php echo htmlspecialchars(brand_logo_url($prefix)); ?>" alt="<?php echo htmlspecialchars(brand_name()); ?>"
             style="width:84px;height:84px;border-radius:18px;object-fit:cover;box-shadow:0 6px 18px rgba(15,23,42,.18);">
        <div class="fs-4 fw-bold mt-3" style="color:#1f2a44;"><?php echo htmlspecialchars(brand_name()); ?></div>
        <?php if ($subtitle != '') { ?><div class="text-muted"><?php echo htmlspecialchars($subtitle); ?></div><?php } ?>
    </div>
<?php }

// Endereço público do site (https://dominio): guardado nas visitas ao painel para os e-mails enviados
// em segundo plano (ex.: alertas de DNS), que não têm navegador para saber o domínio.
function brand_remember_base_url($mysqli){
    if (empty($_SERVER['HTTP_HOST']) || !empty($_SESSION['brand_base_saved'])) return;
    $proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http');
    $host = !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
    $url = strtolower(trim(explode(',', $proto)[0])).'://'.trim(explode(',', $host)[0]);
    if (!preg_match('#^https?://[A-Za-z0-9.\-:]+$#', $url) || strpos($url, 'localhost') !== false) return;
    $stmt = @mysqli_prepare($mysqli, "INSERT INTO tbl_panel_config (name, value) VALUES ('site_base_url', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, 's', $url); @mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
    $_SESSION['brand_base_saved'] = 1;
}

// Endereço do site para links e imagens dos e-mails (com navegador: domínio atual; em segundo plano: o salvo)
function brand_mail_base_url(){
    if (!empty($_SERVER['HTTP_HOST'])) {
        $proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http');
        $host = !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
        return strtolower(trim(explode(',', $proto)[0])).'://'.trim(explode(',', $host)[0]);
    }
    return brand_saved_base_url();
}

// Logo oficial para e-mails (versão leve, 144 px, fundo escuro da marca)
function brand_email_logo_url(){
    return rtrim(brand_mail_base_url(), '/').'/assets/images/brand/email_logo.png';
}

function brand_saved_base_url(){
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return '';
    $res = @mysqli_query($mysqli, "SELECT value FROM tbl_panel_config WHERE name = 'site_base_url'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? rtrim($row['value'], '/') : '';
}

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
