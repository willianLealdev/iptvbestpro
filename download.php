<?php
/**
 * Página de download do app (APK) para celular, TV Box, Android TV e Fire Stick.
 * O link curto /apk entrega o arquivo direto (bom para digitar no app Downloader da TV).
 */
require_once("includes/brand_helper.php");
require_once("includes/db_helper.php");
require_once("includes/app_release.php");

function dl_e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$name = brand_name();
$rel = app_release();
$file = __DIR__.'/'.$rel['file'];
$size = is_file($file) ? round(filesize($file) / 1048576, 1) : 0;
$base = function_exists('brand_mail_base_url') ? brand_mail_base_url() : '';
$short = preg_replace('#^https?://#', '', rtrim($base, '/')).'/apk';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Baixar o app | <?php echo dl_e($name); ?></title>
<meta name="description" content="Baixe o <?php echo dl_e($name); ?> para Android: celular, TV Box, Android TV e Fire Stick.">
<link href="<?php echo brand_logo_url(); ?>" rel="icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css">
<style>
:root{--bg:#070b18;--card:#111a33;--line:rgba(255,255,255,.08);--text:#e9edf7;--muted:#9aa6c2;--gold:#f5b82e;--gold2:#ffd56b;--blue:#2f6bff}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh}
a{color:inherit;text-decoration:none}
.bg{position:fixed;inset:0;background:url(assets/images/brand/hero-bg.jpg) center/cover;opacity:.45;z-index:0}
.bg:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,11,24,.2),var(--bg) 85%)}
.top{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;max-width:1080px;margin:0 auto;padding:16px 20px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px}
.brand img{width:40px;height:40px;border-radius:10px}
.wrap{position:relative;z-index:1;max-width:1080px;margin:0 auto;padding:20px 20px 60px}
.hero{text-align:center;padding:30px 0 10px}
.hero img.app{width:110px;height:110px;border-radius:26px;box-shadow:0 20px 50px rgba(0,0,0,.5)}
.hero h1{font-size:clamp(30px,5vw,46px);font-weight:800;letter-spacing:-1px;margin-top:18px}
.hero p{color:var(--muted);font-size:17px;max-width:620px;margin:10px auto 0}
.btn-dl{display:inline-flex;align-items:center;gap:10px;margin-top:26px;padding:17px 34px;border-radius:999px;background:linear-gradient(180deg,var(--gold2),var(--gold));color:#1b1300;font-weight:800;font-size:18px;box-shadow:0 14px 34px rgba(245,184,46,.35)}
.btn-dl:hover{transform:translateY(-1px)}
.btn-dl i{font-size:24px}
.meta{color:var(--muted);font-size:14px;margin-top:12px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:46px}
.card{background:rgba(17,26,51,.92);border:1px solid var(--line);border-radius:18px;padding:24px}
.card h3{display:flex;align-items:center;gap:10px;font-size:18px;margin-bottom:12px}
.card h3 i{font-size:26px;color:var(--gold)}
.card ol{padding-left:20px;color:#cdd5ea;font-size:14.5px;display:grid;gap:8px}
.code{display:inline-block;font-family:monospace;font-weight:700;color:var(--gold2);background:rgba(245,184,46,.12);border:1px solid rgba(245,184,46,.35);padding:2px 8px;border-radius:8px;word-break:break-all}
.note{margin-top:30px;text-align:center;color:var(--muted);font-size:13.5px}
.note a{color:var(--gold2);text-decoration:underline}
.warn{margin:24px auto 0;max-width:640px;background:rgba(245,184,46,.1);border:1px solid rgba(245,184,46,.35);border-radius:14px;padding:14px 18px;color:#f7e2a8;font-size:14px;text-align:center}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="bg"></div>
<div class="top">
    <a href="./" class="brand"><img src="<?php echo brand_logo_url(); ?>" alt=""><span><?php echo dl_e($name); ?></span></a>
    <?php echo i18n_switcher('dark'); ?>
</div>
<main class="wrap">
    <section class="hero">
        <img class="app" src="<?php echo brand_logo_url(); ?>" alt="">
        <h1>Baixe o <?php echo dl_e($name); ?></h1>
        <p>Para celular Android, TV Box, Android TV, Google TV e Fire TV Stick.</p>
        <?php if ($size > 0) { ?>
            <a class="btn-dl" href="<?php echo dl_e($rel['file']).'?v='.(int)$rel['version_code']; ?>" download><i class="ri-download-2-fill"></i> Baixar o app (APK)</a>
            <div class="meta">Versão <?php echo dl_e($rel['version']); ?> · <?php echo dl_e(str_replace('.', ',', (string)$size)); ?> MB · Android <?php echo dl_e($rel['min_android']); ?> ou superior</div>
        <?php } else { ?>
            <div class="warn">O arquivo do app ainda não foi publicado. Volte em instantes.</div>
        <?php } ?>
    </section>

    <section class="grid">
        <div class="card">
            <h3><i class="ri-smartphone-line"></i> Celular ou tablet</h3>
            <ol>
                <li>Toque em <b>Baixar o app (APK)</b>.</li>
                <li>Abra o arquivo baixado. Se o Android pedir, permita <b>instalar apps desta fonte</b>.</li>
                <li>Toque em <b>Instalar</b> e abra o app.</li>
                <li>Digite o <b>código de ativação</b> da sua revenda ou da sua assinatura.</li>
            </ol>
        </div>
        <div class="card">
            <h3><i class="ri-fire-line"></i> Fire TV Stick</h3>
            <ol>
                <li>Instale o app <b>Downloader</b> pela loja da Amazon.</li>
                <li>Em <b>Configurações → Minha Fire TV → Opções do desenvolvedor</b>, permita instalar apps do Downloader.</li>
                <li>No Downloader, digite <span class="code" translate="no"><?php echo dl_e($short); ?></span> e confirme.</li>
                <li>Instale, abra e digite o seu código de ativação.</li>
            </ol>
        </div>
        <div class="card">
            <h3><i class="ri-tv-2-line"></i> TV Box e Android TV</h3>
            <ol>
                <li>Instale o <b>Downloader</b> pela Play Store da TV (ou use o navegador do aparelho).</li>
                <li>Permita instalar apps de <b>fontes desconhecidas</b> quando o aparelho pedir.</li>
                <li>Digite <span class="code" translate="no"><?php echo dl_e($short); ?></span> e instale.</li>
                <li>Abra o app e digite o seu código de ativação.</li>
            </ol>
        </div>
    </section>

    <p class="note">Ainda não tem código? Assine na <a href="cliente/">área do cliente</a> ou fale com a sua revenda. O <?php echo dl_e($name); ?> não fornece canais, filmes ou playlists.</p>
</main>
</body>
</html>
