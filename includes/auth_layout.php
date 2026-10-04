<?php
/**
 * Layout único das telas de acesso (login, cadastro, recuperar senha) do admin, da revenda e do cliente.
 * Todas ficam iguais: fundo da marca, cartão central com logo, campos com ícone, botão dourado e seletor de idioma.
 *
 * auth_start(array('prefix' => '../', 'title' => 'Entrar', 'subtitle' => 'Área do Cliente', 'badge' => 'Cliente', 'wide' => false));
 * ... formulário ...
 * auth_end(array('prefix' => '../'));
 */

function auth_e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function auth_start($o){
    $prefix = isset($o['prefix']) ? $o['prefix'] : '';
    $title = isset($o['title']) ? $o['title'] : '';
    $subtitle = isset($o['subtitle']) ? $o['subtitle'] : '';
    $badge = isset($o['badge']) ? $o['badge'] : '';
    $icon = isset($o['icon']) ? $o['icon'] : 'ri-user-3-line';
    $wide = !empty($o['wide']);
    $site = isset($o['site']) ? $o['site'] : $prefix; // link "voltar ao site"
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?php echo auth_e($title); ?> | <?php echo auth_e(brand_name()); ?></title>
    <link href="<?php echo auth_e(brand_logo_url($prefix)); ?>" rel="icon">
    <link rel="apple-touch-icon" href="<?php echo auth_e(brand_logo_url($prefix)); ?>">
    <meta name="theme-color" content="#070b18">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $prefix; ?>assets/vendors/remixicon/remixicon.min.css">
    <style>
        :root { --bg: #070b18; --gold: #f5b82e; --gold2: #ffd56b; --blue: #2f6bff; --text: #1f2a44; --muted: #6b7a99; --line: #e3e8f2; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; -webkit-font-smoothing: antialiased; }
        a { color: var(--blue); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .au-bg { position: fixed; inset: 0; z-index: 0; background: url('<?php echo $prefix; ?>assets/images/brand/hero-bg.jpg') center / cover no-repeat; opacity: .6; }
        .au-bg::after { content: ""; position: absolute; inset: 0; background: radial-gradient(60% 50% at 50% 0%, rgba(47,107,255,.28), transparent 70%), linear-gradient(180deg, rgba(7,11,24,.25), rgba(7,11,24,.92)); }
        .au-top { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; }
        .au-back { color: #c8d0e4; font-size: 14px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
        .au-back:hover { color: #fff; text-decoration: none; }
        .au-wrap { position: relative; z-index: 1; min-height: calc(100vh - 70px); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 16px 40px; }
        .au-card { width: 100%; max-width: <?php echo $wide ? '520px' : '430px'; ?>; background: #fff; border-radius: 22px; box-shadow: 0 30px 80px rgba(0,0,0,.45); padding: 34px 32px 28px; }
        .au-brand { text-align: center; margin-bottom: 22px; }
        .au-brand img { width: 78px; height: 78px; border-radius: 18px; object-fit: cover; box-shadow: 0 8px 22px rgba(15,23,42,.22); }
        .au-brand .n { font-size: 22px; font-weight: 800; margin-top: 12px; letter-spacing: -.3px; }
        .au-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 5px 12px; border-radius: 999px; background: #fff6e0; color: #8a5a00; border: 1px solid #f7dc9c; font-size: 13px; font-weight: 700; }
        .au-sub { color: var(--muted); font-size: 14.5px; text-align: center; margin: -8px 0 22px; }
        .au-field { margin-bottom: 16px; }
        .au-field label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        .au-input { position: relative; }
        .au-input > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #93a0bb; font-size: 18px; pointer-events: none; }
        .au-input input { width: 100%; height: 50px; border: 1.5px solid var(--line); border-radius: 12px; padding: 0 44px 0 42px; font-size: 15.5px; font-family: inherit; color: var(--text); background: #fbfcfe; transition: .15s; outline: none; }
        .au-input input:focus { border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(47,107,255,.12); }
        .au-eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: none; color: #93a0bb; font-size: 19px; cursor: pointer; padding: 6px; }
        .au-eye:hover { color: var(--text); }
        .au-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .au-line { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: -4px 0 18px; font-size: 14px; }
        .au-check { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); cursor: pointer; }
        .au-check input { width: 17px; height: 17px; accent-color: var(--blue); }
        .au-btn { width: 100%; height: 52px; border: 0; border-radius: 12px; background: linear-gradient(180deg, var(--gold2), var(--gold)); color: #1b1300; font-weight: 700; font-size: 16px; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 10px 24px rgba(245,184,46,.3); transition: .15s; }
        .au-btn:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(245,184,46,.4); }
        .au-btn i { font-size: 19px; }
        .au-alt { text-align: center; color: var(--muted); font-size: 14.5px; margin: 20px 0 0; }
        .au-alert { border-radius: 12px; padding: 12px 14px; font-size: 14px; margin-bottom: 16px; display: flex; gap: 10px; align-items: flex-start; }
        .au-alert i { font-size: 18px; line-height: 1.2; }
        .au-alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .au-alert.ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .au-alert.warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .au-hint { font-size: 12.5px; color: var(--muted); margin-top: 6px; }
        .au-foot { color: #8f9ab3; font-size: 13px; margin-top: 22px; text-align: center; }
        .au-done { text-align: center; margin-bottom: 20px; }
        .au-done__ico { width: 70px; height: 70px; margin: 0 auto 14px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; background: #eef4ff; color: var(--blue); }
        .au-done__ico.ok { background: #f0fdf4; color: #16a34a; }
        .au-done__ico.err { background: #fef2f2; color: #dc2626; }
        .au-done h2 { font-size: 20px; margin: 0 0 10px; }
        .au-done p { color: #4b5875; font-size: 15px; line-height: 1.6; margin: 0 0 10px; }
        .au-small { font-size: 13px !important; color: var(--muted) !important; }
        a.au-btn { text-decoration: none; }
        .au-link-btn { background: none; border: 0; color: var(--blue); font: inherit; font-weight: 600; cursor: pointer; padding: 0; }
        .au-link-btn:hover { text-decoration: underline; }
        @media (max-width: 480px) { .au-card { padding: 26px 20px 22px; border-radius: 18px; } .au-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="au-bg"></div>
<div class="au-top">
    <a class="au-back" href="<?php echo auth_e($site === '' ? './' : $site); ?>"><i class="ri-arrow-left-line"></i> Voltar ao site</a>
    <?php echo function_exists('i18n_switcher') ? i18n_switcher('dark') : ''; ?>
</div>
<main class="au-wrap">
    <div class="au-card">
        <div class="au-brand">
            <img src="<?php echo auth_e(brand_logo_url($prefix)); ?>" alt="">
            <div class="n"><?php echo auth_e(brand_name()); ?></div>
            <?php if ($badge != '') { ?><div class="au-badge"><i class="<?php echo auth_e($icon); ?>"></i> <?php echo auth_e($badge); ?></div><?php } ?>
        </div>
        <?php if ($subtitle != '') { ?><p class="au-sub"><?php echo auth_e($subtitle); ?></p><?php } ?>
<?php }

function auth_end($o = array()){ ?>
    </div>
    <div class="au-foot">© <?php echo date('Y'); ?> <?php echo auth_e(brand_name()); ?></div>
</main>
<script>
// mostrar / esconder senha
document.addEventListener('click', function (e) {
    var b = e.target.closest('.au-eye');
    if (!b) return;
    var inp = b.parentNode.querySelector('input');
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    b.innerHTML = show ? '<i class="ri-eye-off-line"></i>' : '<i class="ri-eye-line"></i>';
});
</script>
</body>
</html>
<?php }

// Mensagem (type: err | ok | warn). $msgs: texto ou lista de textos.
function auth_alert($type, $msgs){
    if (empty($msgs)) return;
    $msgs = is_array($msgs) ? $msgs : array($msgs);
    $icons = array('err' => 'ri-error-warning-line', 'ok' => 'ri-checkbox-circle-line', 'warn' => 'ri-alert-line');
    echo '<div class="au-alert '.auth_e($type).'"><i class="'.$icons[$type].'"></i><div>';
    foreach ($msgs as $m) echo '<div>'.auth_e($m).'</div>';
    echo '</div></div>';
}

/**
 * Campo com ícone. $o: name, label, type (text|email|password|tel), icon, value, placeholder, required, attrs, hint, id
 */
function auth_input($o){
    $type = isset($o['type']) ? $o['type'] : 'text';
    $id = isset($o['id']) ? $o['id'] : 'f_'.$o['name'];
    ?>
    <div class="au-field">
        <label for="<?php echo auth_e($id); ?>"><?php echo auth_e($o['label']); ?></label>
        <div class="au-input">
            <i class="<?php echo auth_e(isset($o['icon']) ? $o['icon'] : 'ri-edit-line'); ?>"></i>
            <input type="<?php echo auth_e($type); ?>" name="<?php echo auth_e($o['name']); ?>" id="<?php echo auth_e($id); ?>"
                   value="<?php echo auth_e(isset($o['value']) ? $o['value'] : ''); ?>"
                   placeholder="<?php echo auth_e(isset($o['placeholder']) ? $o['placeholder'] : ''); ?>"
                   <?php echo !isset($o['required']) || $o['required'] ? 'required' : ''; ?> <?php echo isset($o['attrs']) ? $o['attrs'] : ''; ?>>
            <?php if ($type == 'password') { ?><button type="button" class="au-eye" tabindex="-1" aria-label="Mostrar senha"><i class="ri-eye-line"></i></button><?php } ?>
        </div>
        <?php if (!empty($o['hint'])) { ?><div class="au-hint"><?php echo auth_e($o['hint']); ?></div><?php } ?>
    </div>
<?php }
