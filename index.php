<?php
/**
 * Página inicial pública (site institucional).
 * O login do painel administrativo fica em gerencia.php.
 */
require_once("includes/brand_helper.php");
if (!file_exists("includes/.lic")) {
    header("Location:install/index.php");
    exit;
}
require_once("includes/db_helper.php");
require_once("includes/customer_helper.php");

function lp_e($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$name = brand_name();
$currency = customer_currency_for_country(customer_detect_country());
$plans = sub_plans_list($mysqli);
// Menor preço entre os planos (para o "a partir de") e preço do crédito da revenda na mesma moeda
$from_price = 0;
foreach ($plans as $p) { $v = plan_price($p, $currency); if ($v > 0 && ($from_price == 0 || $v < $from_price)) $from_price = $v; }
$credit_unit = credit_price($mysqli, $currency);
$year = date('Y');

$platforms = array(
    array('ri-android-line', 'Android TV'),
    array('ri-router-line', 'TV Box'),
    array('ri-fire-line', 'Fire TV Stick'),
    array('ri-google-line', 'Google TV'),
    array('ri-smartphone-line', 'Celular Android'),
    array('ri-tablet-line', 'Tablet Android'),
);

$features = array(
    array('ri-play-list-2-line', 'Várias playlists', 'Adicione mais de uma lista no mesmo aparelho e troque entre elas quando quiser.'),
    array('ri-heart-3-line', 'Favoritos', 'Marque canais, filmes e séries para encontrar tudo em um toque.'),
    array('ri-history-line', 'Continue assistindo', 'O app lembra onde você parou e retoma do mesmo ponto.'),
    array('ri-lock-2-line', 'Controle dos pais', 'Bloqueie categorias com PIN e deixe o conteúdo adequado para a família.'),
    array('ri-calendar-event-line', 'Guia de programação', 'Veja o que está passando agora e o que vem a seguir, quando a lista oferece EPG.'),
    array('ri-closed-captioning-line', 'Legendas e áudio', 'Escolha legenda e faixa de áudio e ajuste o visual da legenda.'),
    array('ri-refresh-line', 'Reconexão automática', 'Se o servidor mudar de endereço, o app reconecta sozinho, sem você fazer nada.'),
    array('ri-palette-line', 'Temas personalizados', 'Visual moderno com temas que deixam a tela da TV do seu jeito.'),
);

$faq = array(
    array('O '.$name.' fornece canais ou conteúdo?', 'Não. O '.$name.' é apenas um reprodutor de mídia. Nós não vendemos, hospedamos nem distribuímos canais, filmes ou listas. Você usa a sua própria playlist, contratada com quem você preferir.'),
    array('Como adiciono a minha playlist?', 'Entre na área do cliente, abra o aparelho ativado e escolha "Playlist". Você pode colar a URL da lista (M3U) ou informar servidor, usuário e senha da sua conta. A lista aparece no app em instantes.'),
    array('Como ativo o meu aparelho?', 'Instale o app, abra e anote o código exibido na tela. Depois assine um plano na área do cliente: você recebe um código de ativação para digitar no app. Se você comprou com um revendedor, ele ativa o aparelho para você.'),
    array('Em quais aparelhos funciona?', 'Em aparelhos Android: Android TV, Google TV, TV Box, Fire TV Stick, celulares e tablets.'),
    array('Posso usar em mais de um aparelho?', 'Cada assinatura ativa 1 aparelho. Para usar em outro aparelho, faça uma nova assinatura.'),
    array('Como faço para ser revendedor?', 'Clique em "Revenda", crie a sua conta e compre créditos. Cada crédito ativa 1 aparelho por 1 ano. Você ganha um painel próprio para gerenciar seus clientes, servidores e a sua marca.'),
    array('Com quem falo sobre problemas na playlist?', 'Problemas com canais, quedas ou conteúdo da lista devem ser tratados com o fornecedor da sua playlist. Dúvidas sobre o app e a ativação são com a gente.'),
);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo lp_e($name); ?> | Reprodutor de mídia para TV, TV Box e celular</title>
<meta name="description" content="<?php echo lp_e($name); ?> é um reprodutor de mídia rápido e estável para Android TV, TV Box, Fire Stick e celular. Use a sua própria playlist por URL ou conta.">
<meta property="og:title" content="<?php echo lp_e($name); ?>">
<meta property="og:description" content="Sua playlist, na sua TV. Rápido, estável e sem anúncios.">
<meta property="og:image" content="<?php echo brand_logo_url(); ?>">
<meta name="theme-color" content="#070b18">
<link href="<?php echo brand_logo_url(); ?>" rel="icon">
<link rel="apple-touch-icon" href="<?php echo brand_logo_url(); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css">
<style>
:root{
    --bg:#070b18; --bg2:#0b1124; --card:#111a33; --line:rgba(255,255,255,.08);
    --text:#e9edf7; --muted:#9aa6c2; --gold:#f5b82e; --gold2:#ffd56b; --blue:#2f6bff; --blue2:#4f8cff;
    --radius:18px;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
.wrap{width:100%;max-width:1180px;margin:0 auto;padding:0 20px}
.grad{background:linear-gradient(90deg,var(--gold2),var(--gold) 45%,var(--blue2));-webkit-background-clip:text;background-clip:text;color:transparent}

/* barra superior */
.topbar{background:linear-gradient(90deg,#1a2a6b,#2f6bff);font-size:13.5px;text-align:center;padding:8px 16px;font-weight:500}
.topbar a{text-decoration:underline;font-weight:700}

/* navegação */
.nav{position:sticky;top:0;z-index:50;background:rgba(7,11,24,.78);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
.nav .wrap{display:flex;align-items:center;gap:24px;height:72px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;letter-spacing:-.2px;white-space:nowrap}
.brand img{width:42px;height:42px;border-radius:11px;object-fit:cover}
.menu{display:flex;gap:26px;margin-left:12px;font-size:14.5px;font-weight:500;color:var(--muted)}
.menu a:hover{color:var(--text)}
.nav-cta{margin-left:auto;display:flex;gap:10px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 20px;border-radius:999px;font-weight:600;font-size:14.5px;border:1px solid transparent;transition:.2s;cursor:pointer;white-space:nowrap}
.btn i{font-size:18px}
.btn-gold{background:linear-gradient(180deg,var(--gold2),var(--gold));color:#1b1300;box-shadow:0 8px 24px rgba(245,184,46,.25)}
.btn-gold:hover{transform:translateY(-1px);box-shadow:0 12px 30px rgba(245,184,46,.35)}
.btn-blue{background:var(--blue);color:#fff;box-shadow:0 8px 24px rgba(47,107,255,.3)}
.btn-blue:hover{background:var(--blue2);transform:translateY(-1px)}
.btn-ghost{border-color:rgba(255,255,255,.18);color:var(--text)}
.btn-ghost:hover{border-color:rgba(255,255,255,.4);background:rgba(255,255,255,.04)}
.btn-lg{padding:15px 28px;font-size:16px}
.lang-m{display:none;margin-left:auto}
.nav-cta{align-items:center}
.burger{display:none;margin-left:auto;background:none;border:0;color:var(--text);font-size:28px;cursor:pointer}
.mobile{display:none;border-top:1px solid var(--line);padding:12px 20px 20px}
.mobile a{display:block;padding:12px 4px;color:var(--muted);font-weight:500;border-bottom:1px solid var(--line)}
.mobile .btns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}
.mobile .btns a{border:0;padding:12px;color:inherit}
.mobile.open{display:block}

/* hero */
.hero{position:relative;padding:96px 0 70px;text-align:center;overflow:hidden}
.hero:before{content:"";position:absolute;inset:0;background:url(assets/images/brand/hero-bg.jpg) center/cover no-repeat;opacity:.55}
.hero:after{content:"";position:absolute;inset:0;background:radial-gradient(60% 50% at 50% 0%,rgba(47,107,255,.25),transparent 70%),linear-gradient(180deg,rgba(7,11,24,.2),var(--bg) 92%)}
.hero .wrap{position:relative;z-index:1}
.pill{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;background:rgba(245,184,46,.1);border:1px solid rgba(245,184,46,.3);color:var(--gold2);font-size:13px;font-weight:600;margin-bottom:22px}
.hero h1{font-size:clamp(36px,6vw,66px);line-height:1.06;font-weight:800;letter-spacing:-1.5px;max-width:900px;margin:0 auto}
.hero p.lead{font-size:clamp(16px,2vw,19px);color:var(--muted);max-width:660px;margin:22px auto 34px}
.hero .ctas{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.platforms{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin:56px auto 0;max-width:980px}
.plat{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;background:rgba(17,26,51,.75);border:1px solid var(--line);text-align:left}
.plat i{font-size:24px;color:var(--gold)}
.plat small{display:block;font-size:10.5px;color:var(--muted);line-height:1.2}
.plat b{display:block;font-size:13.5px;font-weight:600;line-height:1.25}
.disclaimer{margin-top:26px;font-size:13px;color:var(--muted)}
.disclaimer i{color:var(--gold)}

/* seções */
section{padding:90px 0}
.eyebrow{display:inline-block;color:var(--gold);font-weight:700;font-size:13px;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:12px}
.h2{font-size:clamp(28px,4vw,42px);line-height:1.15;font-weight:800;letter-spacing:-.8px}
.sub{color:var(--muted);font-size:17px;max-width:640px;margin-top:14px}
.center{text-align:center}
.center .sub{margin-left:auto;margin-right:auto}

/* apresentação */
.about{display:grid;grid-template-columns:1.05fr 1fr;gap:60px;align-items:center}
.tv{position:relative;border-radius:22px;padding:12px;background:linear-gradient(145deg,#1d2850,#0c1229);border:1px solid rgba(255,255,255,.1);box-shadow:0 40px 80px rgba(0,0,0,.5),0 0 0 1px rgba(47,107,255,.15)}
.tv-screen{border-radius:14px;overflow:hidden;aspect-ratio:16/9;background:#050814;position:relative}
.tv-screen img.bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.9}
.tv-ui{position:absolute;inset:0;display:grid;grid-template-columns:repeat(3,1fr);gap:4%;padding:12% 6% 6%;align-items:center}
.tv-ui .logo{position:absolute;top:5%;left:5%;display:flex;align-items:center;gap:6px;font-weight:700;font-size:clamp(9px,1.2vw,13px)}
.tv-ui .logo img{width:clamp(16px,2.2vw,26px);height:clamp(16px,2.2vw,26px);border-radius:6px}
.tile{aspect-ratio:1/1;border-radius:12%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6%;font-weight:700;font-size:clamp(9px,1.25vw,14px);box-shadow:0 10px 20px rgba(0,0,0,.35)}
.tile i{font-size:clamp(18px,3.2vw,38px)}
.t1{background:linear-gradient(145deg,#3f7bff,#1c3fb5)}
.t2{background:linear-gradient(145deg,#ffcf5a,#e09a12);color:#251800}
.t3{background:linear-gradient(145deg,#a35cff,#5b25c9)}
.tv-foot{width:34%;height:10px;margin:10px auto 0;border-radius:0 0 12px 12px;background:#141c38}
.checks{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px;margin-top:28px}
.checks div{display:flex;gap:10px;align-items:center;font-weight:500}
.checks i{color:#1b1300;background:var(--gold);border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;flex:none}

/* recursos */
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:48px}
.card{background:linear-gradient(180deg,rgba(17,26,51,.9),rgba(11,17,36,.9));border:1px solid var(--line);border-radius:var(--radius);padding:26px;transition:.25s}
.card:hover{transform:translateY(-4px);border-color:rgba(245,184,46,.35)}
.ico{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;background:rgba(47,107,255,.14);color:var(--blue2);margin-bottom:18px}
.card:nth-child(even) .ico{background:rgba(245,184,46,.12);color:var(--gold)}
.card h3{font-size:17px;font-weight:700;margin-bottom:8px}
.card p{color:var(--muted);font-size:14.5px}

/* como funciona */
.alt{background:linear-gradient(180deg,var(--bg),var(--bg2) 20%,var(--bg2) 80%,var(--bg))}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-top:52px;counter-reset:s}
.step{position:relative;padding:32px 28px;border-radius:var(--radius);background:var(--card);border:1px solid var(--line)}
.step .n{position:absolute;top:-18px;left:28px;width:38px;height:38px;border-radius:12px;background:linear-gradient(180deg,var(--gold2),var(--gold));color:#1b1300;font-weight:800;display:flex;align-items:center;justify-content:center}
.step i.big{font-size:34px;color:var(--blue2);display:block;margin:6px 0 14px}
.step h3{font-size:19px;margin-bottom:8px}
.step p{color:var(--muted);font-size:15px}
.ways{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.ways span{font-size:12.5px;font-weight:600;padding:5px 11px;border-radius:999px;background:rgba(47,107,255,.14);color:#bcd0ff}

/* planos */
.plans{display:flex;flex-wrap:wrap;justify-content:center;gap:22px;margin-top:48px}
.plan{flex:1 1 280px;max-width:360px;padding:34px 30px;border-radius:22px;background:var(--card);border:1px solid var(--line);display:flex;flex-direction:column}
.plan.hot{border-color:rgba(245,184,46,.55);background:linear-gradient(180deg,rgba(245,184,46,.08),var(--card) 40%);box-shadow:0 30px 60px rgba(0,0,0,.35)}
.plan .tag{align-self:flex-start;font-size:12px;font-weight:700;padding:5px 11px;border-radius:999px;background:var(--gold);color:#1b1300;margin-bottom:14px}
.plan h3{font-size:21px}
.plan .per{color:var(--muted);font-size:14px}
.plan .price{font-size:40px;font-weight:800;letter-spacing:-1px;margin:18px 0 6px}
.plan ul{list-style:none;margin:18px 0 28px;display:grid;gap:10px}
.plan li{display:flex;gap:10px;font-size:15px;color:#cdd5ea}
.plan li i{color:var(--gold)}
.plan .btn{margin-top:auto}
.note{margin-top:22px;color:var(--muted);font-size:14px}

.from{margin-top:12px;font-size:17px;color:var(--muted)}
.from b{font-size:30px;font-weight:800;color:var(--gold2);margin-left:4px;letter-spacing:-.5px}
.rv-callout{display:flex;align-items:center;gap:20px;max-width:900px;margin:40px auto 0;padding:22px 26px;border-radius:20px;background:linear-gradient(90deg,rgba(245,184,46,.16),rgba(47,107,255,.14));border:1px solid rgba(245,184,46,.45);transition:.2s}
.rv-callout:hover{transform:translateY(-2px);border-color:var(--gold)}
.rv-callout__ico{flex:none;width:54px;height:54px;border-radius:16px;background:linear-gradient(180deg,var(--gold2),var(--gold));color:#1b1300;display:flex;align-items:center;justify-content:center;font-size:28px}
.rv-callout__txt{flex:1}
.rv-callout__txt b{display:block;font-size:18px}
.rv-callout__txt span{color:#cdd5ea;font-size:15px}
.rv-callout__txt span b{display:inline;font-size:inherit;color:var(--gold2)}
.excl{display:flex;width:fit-content;align-items:center;gap:8px;margin:0 0 16px;padding:8px 14px;border-radius:12px;background:rgba(245,184,46,.14);border:1px solid rgba(245,184,46,.4);color:var(--gold2);font-weight:600;font-size:14.5px}
.excl b{color:#fff}

/* revenda */
.reseller{border-radius:28px;padding:60px;background:radial-gradient(70% 90% at 100% 0%,rgba(47,107,255,.35),transparent 60%),radial-gradient(60% 80% at 0% 100%,rgba(245,184,46,.18),transparent 60%),var(--card);border:1px solid rgba(255,255,255,.1);display:grid;grid-template-columns:1.1fr 1fr;gap:50px;align-items:center}
.rv-list{display:grid;gap:14px}
.rv-item{display:flex;gap:14px;padding:16px 18px;border-radius:16px;background:rgba(7,11,24,.55);border:1px solid var(--line)}
.rv-item i{font-size:24px;color:var(--gold)}
.rv-item b{display:block}
.rv-item span{color:var(--muted);font-size:14px}

/* portais */
.portals{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:48px}
.portal{padding:34px;border-radius:22px;background:var(--card);border:1px solid var(--line);display:flex;flex-direction:column;gap:12px}
.portal i.big{font-size:38px}
.portal.c i.big{color:var(--blue2)}
.portal.r i.big{color:var(--gold)}
.portal h3{font-size:22px}
.portal p{color:var(--muted)}
.portal .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}

/* faq */
.faq{max-width:820px;margin:44px auto 0;display:grid;gap:12px}
.faq details{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:0 22px}
.faq summary{list-style:none;cursor:pointer;padding:20px 0;font-weight:600;font-size:16px;display:flex;justify-content:space-between;align-items:center;gap:16px}
.faq summary::-webkit-details-marker{display:none}
.faq summary i{transition:.2s;color:var(--gold);font-size:22px;flex:none}
.faq details[open] summary i{transform:rotate(45deg)}
.faq details p{color:var(--muted);padding:0 0 20px}

/* rodapé */
footer{border-top:1px solid var(--line);padding:56px 0 30px;background:#050814}
.foot{display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:40px}
.foot h4{font-size:14px;text-transform:uppercase;letter-spacing:1.2px;color:var(--muted);margin-bottom:14px}
.foot a{display:block;color:#c8d0e4;padding:4px 0;font-size:15px}
.foot a:hover{color:var(--gold2)}
.foot p{color:var(--muted);font-size:14px;margin-top:14px;max-width:380px}
.copy{margin-top:40px;padding-top:22px;border-top:1px solid var(--line);color:var(--muted);font-size:13px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}

/* animação de entrada */
.reveal{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease}
.reveal.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none}html{scroll-behavior:auto}}

@media (max-width:1020px){
    .menu,.nav-cta{display:none}
    .burger{display:block;margin-left:10px}
    .lang-m{display:block}
    .platforms{grid-template-columns:repeat(3,1fr)}
    .grid4{grid-template-columns:repeat(2,1fr)}
    .about,.reseller{grid-template-columns:1fr}
    .reseller{padding:40px 28px}
    .steps{grid-template-columns:1fr;gap:34px}
}
@media (max-width:640px){
    .wrap{padding:0 16px}
    section{padding:64px 0}
    .hero{padding:64px 0 50px}
    .platforms{grid-template-columns:repeat(2,1fr)}
    .grid4,.portals,.checks{grid-template-columns:1fr}
    .foot{grid-template-columns:1fr}
    .hero .ctas .btn{width:100%}
    .rv-callout{flex-direction:column;text-align:center}
    .brand span{font-size:16px}
}
</style>
</head>
<body>

<div class="topbar"><i class="ri-vip-crown-fill"></i> Revendedores têm <b>preço exclusivo</b> e painel próprio. <a href="revenda/register.php">Quero revender</a></div>

<header class="nav">
    <div class="wrap">
        <a href="./" class="brand"><img src="<?php echo brand_logo_url(); ?>" alt=""><span><?php echo lp_e($name); ?></span></a>
        <nav class="menu">
            <a href="#recursos">Recursos</a>
            <a href="#como-funciona">Como funciona</a>
            <a href="#planos">Planos</a>
            <a href="#revenda">Revenda</a>
            <a href="#faq">Dúvidas</a>
        </nav>
        <div class="nav-cta">
            <?php echo i18n_switcher('dark'); ?>
            <a href="revenda/" class="btn btn-ghost"><i class="ri-store-2-line"></i> Revenda</a>
            <a href="cliente/" class="btn btn-gold"><i class="ri-user-3-line"></i> Cliente</a>
        </div>
        <div class="lang-m"><?php echo i18n_switcher('dark'); ?></div>
        <button class="burger" type="button" aria-label="Abrir menu" aria-expanded="false" id="burger"><i class="ri-menu-line"></i></button>
    </div>
    <div class="mobile" id="mobile">
        <a href="#recursos">Recursos</a>
        <a href="#como-funciona">Como funciona</a>
        <a href="#planos">Planos</a>
        <a href="#revenda">Revenda</a>
        <a href="#faq">Dúvidas</a>
        <div class="btns">
            <a href="revenda/" class="btn btn-ghost"><i class="ri-store-2-line"></i> Revenda</a>
            <a href="cliente/" class="btn btn-gold"><i class="ri-user-3-line"></i> Cliente</a>
        </div>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            <div class="pill"><i class="ri-vip-crown-fill"></i> O player premium para a sua TV</div>
            <h1>Sua playlist, na sua TV.<br><span class="grad">Simples, rápido e estável.</span></h1>
            <p class="lead">Você já tem o conteúdo. Faltava um player que não trava, não mostra anúncios e funciona em qualquer aparelho Android. O <?php echo lp_e($name); ?> foi feito para isso.</p>
            <div class="ctas">
                <a href="cliente/register.php" class="btn btn-gold btn-lg"><i class="ri-flashlight-fill"></i> Ativar meu aparelho</a>
                <a href="#como-funciona" class="btn btn-ghost btn-lg"><i class="ri-play-circle-line"></i> Como funciona</a>
            </div>
            <div class="platforms">
                <?php foreach ($platforms as $p) { ?>
                    <div class="plat"><i class="<?php echo $p[0]; ?>"></i><div><small>Disponível para</small><b><?php echo $p[1]; ?></b></div></div>
                <?php } ?>
            </div>
            <p class="disclaimer"><i class="ri-information-line"></i> O <?php echo lp_e($name); ?> não fornece canais, filmes ou playlists. Use a sua própria lista.</p>
        </div>
    </div>

    <section>
        <div class="wrap about">
            <div class="reveal">
                <span class="eyebrow">Conheça o <?php echo lp_e($name); ?></span>
                <h2 class="h2">Tudo o que você assiste, organizado em uma tela só.</h2>
                <p class="sub">Adicione a sua lista por URL ou pelos dados da sua conta e pronto: canais ao vivo, filmes e séries separados, com busca, favoritos e guia de programação.</p>
                <div class="checks">
                    <div><i class="ri-check-line"></i> Sem anúncios</div>
                    <div><i class="ri-check-line"></i> Várias playlists</div>
                    <div><i class="ri-check-line"></i> Controle dos pais</div>
                    <div><i class="ri-check-line"></i> Reconexão automática</div>
                    <div><i class="ri-check-line"></i> Continue de onde parou</div>
                    <div><i class="ri-check-line"></i> Suporte em português</div>
                </div>
            </div>
            <div class="reveal">
                <div class="tv">
                    <div class="tv-screen">
                        <img class="bg" src="assets/images/brand/hero-bg.jpg" alt="">
                        <div class="tv-ui">
                            <div class="logo"><img src="<?php echo brand_logo_url(); ?>" alt=""><?php echo lp_e($name); ?></div>
                            <div class="tile t1"><i class="ri-live-line"></i>Ao vivo</div>
                            <div class="tile t2"><i class="ri-film-line"></i>Filmes</div>
                            <div class="tile t3"><i class="ri-movie-2-line"></i>Séries</div>
                        </div>
                    </div>
                </div>
                <div class="tv-foot"></div>
            </div>
        </div>
    </section>

    <section id="recursos" class="alt">
        <div class="wrap">
            <div class="center reveal">
                <span class="eyebrow">Recursos</span>
                <h2 class="h2">Feito para quem quer assistir sem dor de cabeça</h2>
                <p class="sub">Ferramentas pensadas para deixar a experiência na TV fácil para qualquer pessoa, sem precisar entender de tecnologia.</p>
            </div>
            <div class="grid4">
                <?php foreach ($features as $f) { ?>
                    <div class="card reveal"><div class="ico"><i class="<?php echo $f[0]; ?>"></i></div><h3><?php echo $f[1]; ?></h3><p><?php echo $f[2]; ?></p></div>
                <?php } ?>
            </div>
        </div>
    </section>

    <section id="como-funciona">
        <div class="wrap">
            <div class="center reveal">
                <span class="eyebrow">Como funciona</span>
                <h2 class="h2">Pronto para usar em 3 passos</h2>
                <p class="sub">Da instalação à primeira transmissão em poucos minutos.</p>
            </div>
            <div class="steps">
                <div class="step reveal"><span class="n">1</span><i class="ri-download-cloud-2-line big"></i><h3>Instale o app</h3><p>Baixe o <?php echo lp_e($name); ?> no seu aparelho Android e abra. O app mostra o código do seu aparelho na tela.</p></div>
                <div class="step reveal"><span class="n">2</span><i class="ri-key-2-line big"></i><h3>Ative o aparelho</h3><p>Assine um plano na área do cliente e receba o código de ativação. Comprou com um revendedor? Ele ativa para você.</p></div>
                <div class="step reveal"><span class="n">3</span><i class="ri-play-list-add-line big"></i><h3>Adicione a sua playlist</h3><p>Na área do cliente, cadastre a sua lista e ela aparece no app automaticamente.</p><div class="ways"><span>Por URL (M3U)</span><span>Por conta (usuário e senha)</span></div></div>
            </div>
        </div>
    </section>

    <section id="planos" class="alt">
        <div class="wrap">
            <div class="center reveal">
                <span class="eyebrow">Planos</span>
                <h2 class="h2">Escolha o seu plano</h2>
                <?php if ($from_price > 0) { ?><div class="from">a partir de <b><?php echo money_format_currency($from_price, $currency); ?></b></div><?php } ?>
                <p class="sub">Pagamento seguro<?php echo $currency == 'BRL' ? ' por Pix ou cartão' : ' com cartão'; ?>. Cada plano ativa 1 aparelho.</p>
            </div>
            <div class="plans">
                <?php if ($plans) { $hot = count($plans) > 1 ? (int)floor(count($plans) / 2) : 0;
                    foreach ($plans as $i => $p) { $price = plan_price($p, $currency); $m = (int)$p['months']; ?>
                    <div class="plan reveal<?php echo $i == $hot ? ' hot' : ''; ?>">
                        <?php if ($i == $hot && count($plans) > 1) { ?><span class="tag">Mais escolhido</span><?php } ?>
                        <h3><?php echo lp_e($p['name']); ?></h3>
                        <div class="per"><?php echo $m; ?> <?php echo $m == 1 ? 'mês' : 'meses'; ?> de acesso</div>
                        <div class="price"><?php echo $price > 0 ? money_format_currency($price, $currency) : '<span style="font-size:24px">Em breve</span>'; ?></div>
                        <ul>
                            <li><i class="ri-check-line"></i> 1 aparelho ativado</li>
                            <li><i class="ri-check-line"></i> Playlists ilimitadas</li>
                            <li><i class="ri-check-line"></i> Todas as atualizações do app</li>
                            <?php if ($p['description'] != '') { ?><li><i class="ri-check-line"></i> <?php echo lp_e($p['description']); ?></li><?php } ?>
                        </ul>
                        <a href="cliente/register.php" class="btn <?php echo $i == $hot ? 'btn-gold' : 'btn-blue'; ?>">Assinar agora</a>
                    </div>
                <?php } } else { ?>
                    <div class="plan reveal"><h3>Planos em breve</h3><p class="per">Crie a sua conta para ser avisado.</p><a href="cliente/register.php" class="btn btn-gold" style="margin-top:22px">Criar conta</a></div>
                <?php } ?>
            </div>
            <a href="#revenda" class="rv-callout reveal">
                <span class="rv-callout__ico"><i class="ri-vip-crown-fill"></i></span>
                <span class="rv-callout__txt">
                    <b>É revendedor? Você tem preço exclusivo.</b>
                    <span><?php if ($credit_unit > 0) { ?>Ative aparelhos dos seus clientes por <b><?php echo money_format_currency($credit_unit, $currency); ?></b> por ano, com créditos. Venda pelo preço que quiser.<?php } else { ?>Compre créditos com desconto de revenda e venda pelo preço que quiser.<?php } ?></span>
                </span>
                <span class="btn btn-gold">Ver condições <i class="ri-arrow-right-line"></i></span>
            </a>
            <p class="note center">Preços em <?php echo $currency == 'USD' ? 'dólar (US$)' : 'real (R$)'; ?>. Não é cobrado nenhum valor pelo conteúdo da sua playlist.</p>
        </div>
    </section>

    <section id="revenda">
        <div class="wrap">
            <div class="reseller reveal">
                <div>
                    <span class="eyebrow">Programa de revenda</span>
                    <div class="excl"><i class="ri-price-tag-3-fill"></i> Preço exclusivo para revendedores<?php if ($credit_unit > 0) { ?>: <b><?php echo money_format_currency($credit_unit, $currency); ?></b> por aparelho/ano<?php } ?></div>
                    <h2 class="h2">Revenda o <?php echo lp_e($name); ?> com a sua marca</h2>
                    <p class="sub">Compre créditos, ative os aparelhos dos seus clientes e gerencie tudo em um painel próprio. Cada crédito ativa 1 aparelho por 1 ano.</p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:28px">
                        <a href="revenda/register.php" class="btn btn-gold btn-lg"><i class="ri-store-2-line"></i> Quero ser revendedor</a>
                        <a href="revenda/" class="btn btn-ghost btn-lg">Já sou revendedor</a>
                    </div>
                </div>
                <div class="rv-list">
                    <div class="rv-item"><i class="ri-coins-line"></i><div><b>Créditos sem complicação</b><span>1 crédito = 1 ano. Ative por 1, 2 ou mais anos.</span></div></div>
                    <div class="rv-item"><i class="ri-dashboard-3-line"></i><div><b>Painel do revendedor</b><span>Veja seus aparelhos, validades e clientes em tempo real.</span></div></div>
                    <div class="rv-item"><i class="ri-palette-line"></i><div><b>Sua marca no app</b><span>Logo, cores e pôsteres próprios para os seus clientes.</span></div></div>
                    <div class="rv-item"><i class="ri-server-line"></i><div><b>Seus servidores</b><span>Cadastre os seus DNS e o app troca sozinho se um cair.</span></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="alt" style="padding-top:40px">
        <div class="wrap">
            <div class="center reveal">
                <span class="eyebrow">Acesso</span>
                <h2 class="h2">Entre na sua área</h2>
            </div>
            <div class="portals">
                <div class="portal c reveal">
                    <i class="ri-user-3-line big"></i>
                    <h3>Área do cliente</h3>
                    <p>Assine um plano, receba o código de ativação e cadastre as suas playlists.</p>
                    <div class="row"><a href="cliente/" class="btn btn-blue">Entrar</a><a href="cliente/register.php" class="btn btn-ghost">Criar conta</a></div>
                </div>
                <div class="portal r reveal">
                    <i class="ri-store-2-line big"></i>
                    <h3>Portal do revendedor</h3>
                    <p>Compre créditos, ative aparelhos, gerencie clientes, servidores e a sua marca.</p>
                    <div class="row"><a href="revenda/" class="btn btn-gold">Entrar</a><a href="revenda/register.php" class="btn btn-ghost">Criar conta</a></div>
                </div>
            </div>
        </div>
    </section>

    <section id="faq">
        <div class="wrap">
            <div class="center reveal">
                <span class="eyebrow">Dúvidas</span>
                <h2 class="h2">Perguntas frequentes</h2>
            </div>
            <div class="faq">
                <?php foreach ($faq as $q) { ?>
                    <details class="reveal"><summary><?php echo lp_e($q[0]); ?> <i class="ri-add-line"></i></summary><p><?php echo lp_e($q[1]); ?></p></details>
                <?php } ?>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="wrap">
        <div class="foot">
            <div>
                <a href="./" class="brand"><img src="<?php echo brand_logo_url(); ?>" alt=""><span><?php echo lp_e($name); ?></span></a>
                <p>Reprodutor de mídia para Android TV, TV Box, Fire Stick e celular. O <?php echo lp_e($name); ?> não fornece nem hospeda nenhum conteúdo.</p>
            </div>
            <div>
                <h4>Acesso</h4>
                <a href="cliente/">Área do cliente</a>
                <a href="revenda/">Portal do revendedor</a>
                <a href="revenda/register.php">Seja revendedor</a>
            </div>
            <div>
                <h4>Informações</h4>
                <a href="#planos">Planos</a>
                <a href="#faq">Perguntas frequentes</a>
                <a href="terms.php">Termos de uso</a>
                <a href="privacy_policy.php">Política de privacidade</a>
            </div>
        </div>
        <div class="copy"><span>© <?php echo $year; ?> <?php echo lp_e($name); ?>. Todos os direitos reservados.</span><span>Feito para a sua TV.</span></div>
    </div>
</footer>

<script>
(function () {
    var b = document.getElementById('burger'), m = document.getElementById('mobile');
    b.addEventListener('click', function () {
        var open = m.classList.toggle('open');
        b.setAttribute('aria-expanded', open ? 'true' : 'false');
        b.innerHTML = open ? '<i class="ri-close-line"></i>' : '<i class="ri-menu-line"></i>';
    });
    m.querySelectorAll('a[href^="#"]').forEach(function (a) { a.addEventListener('click', function () { m.classList.remove('open'); b.innerHTML = '<i class="ri-menu-line"></i>'; }); });
    var els = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); return; }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
})();
</script>
</body>
</html>
