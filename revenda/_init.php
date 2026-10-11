<?php
/**
 * Portal do revendedor: inicialização, sessão, CSRF e layout.
 */
require_once(__DIR__."/../includes/db_helper.php");
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/reseller_helper.php");

resellers_migrate($mysqli);

define('RV_LOGIN_MAX_FAILS', 8); // por IP, por hora

function rv_e($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function rv_csrf_token(){
    if (empty($_SESSION['rv_csrf'])) $_SESSION['rv_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['rv_csrf'];
}
function rv_csrf_field(){ return '<input type="hidden" name="csrf" value="'.rv_csrf_token().'">'; }
function rv_csrf_ok(){
    return isset($_POST['csrf']) && isset($_SESSION['rv_csrf']) && hash_equals($_SESSION['rv_csrf'], (string)$_POST['csrf']);
}

function rv_flash($type, $msg){ $_SESSION['rv_flash'] = array('type' => $type, 'msg' => $msg); }
function rv_take_flash(){
    if (empty($_SESSION['rv_flash'])) return null;
    $f = $_SESSION['rv_flash'];
    unset($_SESSION['rv_flash']);
    return $f;
}
function rv_redirect($url){ header("Location: ".$url); exit; }

// Revendedor logado (recarregado a cada página: bloqueio tem efeito imediato)
function rv_current($mysqli){
    if (empty($_SESSION['rv_id'])) return null;
    $id = (int)$_SESSION['rv_id'];
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_resellers WHERE id = $id"));
    if (!$row || (int)$row['status'] != 1) {
        unset($_SESSION['rv_id']);
        return null;
    }
    return geo_sync_account($mysqli, 'tbl_resellers', $row);
}
function rv_require_login($mysqli){
    $r = rv_current($mysqli);
    if (!$r) rv_redirect('index.php');
    return $r;
}

function rv_signup_enabled($mysqli){ return panel_config_get($mysqli, 'reseller_signup', '1') == '1'; }

function rv_head($title, $switcher = true){ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo rv_e($title); ?> | <?php echo rv_e(APP_NAME); ?> Revenda</title>
    <link href="<?php echo rv_e(brand_logo_url('../')); ?>" rel="icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/remixicon/remixicon.min.css">
    <style>
        :root { --rv-primary: #3b6cf6; --rv-green: #4b9a2a; --rv-text: #1f2a44; --rv-muted: #6b7a99; --rv-bg: #f4f6fb; }
        body { font-family: 'Inter', sans-serif; background: var(--rv-bg); color: var(--rv-text); min-height: 100vh; }
        /* menu lateral */
        .rv-side { position: fixed; top: 16px; bottom: 16px; left: 16px; width: 250px; z-index: 1040; background: #fff; border-radius: 16px; box-shadow: 0 6px 24px rgba(15,23,42,.08); display: flex; flex-direction: column; padding: 18px 14px; transition: transform .25s ease; overflow-y: auto; }
        .rv-side__brand { display: flex; align-items: center; gap: 10px; padding: 4px 8px 18px; margin-bottom: 8px; border-bottom: 1px solid #eef1f6; color: var(--rv-text); text-decoration: none; font-weight: 700; font-size: 16px; line-height: 1.2; }
        .rv-side__brand img { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; flex: none; }
        .rv-side__brand small { display: block; font-weight: 500; font-size: 12.5px; color: var(--rv-muted); }
        .rv-side__nav { display: flex; flex-direction: column; gap: 4px; }
        .rv-side__link { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px; color: #4b5873; text-decoration: none; font-weight: 500; font-size: 15px; transition: .15s; }
        .rv-side__link i { font-size: 20px; color: #8a96b0; transition: .15s; }
        .rv-side__link:hover { background: #f4f7ff; color: var(--rv-primary); }
        .rv-side__link:hover i { color: var(--rv-primary); }
        .rv-side__link.active { background: var(--rv-primary); color: #fff; box-shadow: 0 6px 16px rgba(59,108,246,.3); }
        .rv-side__link.active i { color: #fff; }
        .rv-side__foot { margin-top: auto; padding-top: 16px; display: grid; gap: 10px; }
        .rv-side__credits { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 12px; background: #f3fdf7; border: 1px solid #ccf7dd; color: #15803d; text-decoration: none; font-weight: 500; }
        .rv-side__credits i { font-size: 20px; }
        .rv-side__tg { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 14px; border-radius: 12px; background: #229ED9; color: #fff; text-decoration: none; font-weight: 600; }
        .rv-side__tg:hover { color: #fff; filter: brightness(1.05); }
        .rv-body { margin-left: 282px; min-height: 100vh; padding: 16px 24px 0 0; }
        .rv-top { display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 16px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 10px 18px; margin-bottom: 22px; min-height: 64px; position: sticky; top: 16px; z-index: 1020; }
        .rv-top__title { font-size: 19px; font-weight: 700; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rv-top__right { margin-left: auto; display: flex; align-items: center; gap: 12px; }
        .rv-burger { display: none; border: 0; background: #f4f6fb; width: 42px; height: 42px; border-radius: 10px; font-size: 22px; color: var(--rv-text); flex: none; }
        .rv-user { display: flex; align-items: center; gap: 8px; color: var(--rv-text); text-decoration: none; font-weight: 600; }
        .rv-user__av { width: 36px; height: 36px; border-radius: 50%; background: #eef3ff; color: var(--rv-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .rv-main { padding-bottom: 48px; }
        .rv-backdrop { display: none; }
        .rv-balance { display: inline-flex; align-items: center; background: #f3fdf7; color: #15803d; border: 1px solid #ccf7dd; border-radius: 20px; padding: 6px 14px; font-weight: 600; white-space: nowrap; text-decoration: none; }
        @media (max-width: 991.98px) {
            .rv-side { top: 0; bottom: 0; left: 0; border-radius: 0 16px 16px 0; transform: translateX(-105%); }
            .rv-side-open .rv-side { transform: none; }
            .rv-side-open .rv-backdrop { display: block; position: fixed; inset: 0; z-index: 1030; background: rgba(15,23,42,.45); }
            .rv-body { margin-left: 0; padding: 12px 16px 0; }
            .rv-top { top: 12px; padding: 8px 12px; }
            .rv-burger { display: inline-flex; align-items: center; justify-content: center; }
        }
        .rv-card { background: #fff; border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
        .rv-title { font-size: 22px; font-weight: 700; margin: 0; }
        .rv-muted { color: var(--rv-muted); }
        .rv-btn-green { background: var(--rv-green); border-color: var(--rv-green); color: #fff; }
        .rv-btn-green:hover { background: #3f8323; border-color: #3f8323; color: #fff; }
        .rv-stat { display: flex; align-items: center; gap: 16px; padding: 18px 20px; border-bottom: 5px solid var(--c); background: var(--bg); border-radius: 14px; height: 100%; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
        .rv-stat__icon { width: 62px; height: 62px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--ibg); color: var(--c); font-size: 28px; flex: 0 0 62px; }
        .rv-stat__label { color: var(--rv-muted); font-size: 14px; }
        .rv-stat__value { font-size: 22px; font-weight: 700; }
        .rv-code { font-family: 'Courier New', monospace; font-size: 40px; font-weight: 700; letter-spacing: 10px; color: var(--rv-primary); }
        .rv-table thead th { background: #fafbfc; font-weight: 600; white-space: nowrap; text-align: center; padding: 14px 10px; }
        .rv-table tbody td { text-align: center; vertical-align: middle; white-space: nowrap; padding: 12px 10px; }
        .dv-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
        .pk-year { color: #389e0d; background: #f6ffed; border-color: #d9f7be; }
        .pk-lifetime { color: #531dab; background: #f9f0ff; border-color: #efdbff; }
        .pk-trial { color: #d46b08; background: #fff7e6; border-color: #ffe7ba; }
        .pk-none { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
        .pk-expired { color: #cf1322; background: #fff1f0; border-color: #ffccc7; }
        .rv-copy { color: var(--rv-primary); cursor: pointer; }
        .rv-auth { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .rv-auth .rv-card { width: 100%; max-width: 420px; }
        .rv-empty { padding: 44px 10px; text-align: center; color: #9ca3af; }
        .rv-empty i { font-size: 44px; display: block; color: #d1d5db; margin-bottom: 6px; }
    </style>
</head>
<body>
<?php if ($switcher) echo i18n_switcher_fixed(); ?>
<?php }

function rv_header($mysqli, $reseller, $title, $active){
    brand_remember_base_url($mysqli);
    rv_head($title, false); ?>
    <?php
    require_once(__DIR__."/../includes/support_helper.php");
    support_migrate($mysqli);
    $rv_sp_unread = support_unread_reseller($mysqli, (int)$reseller['id']);
    $rv_tg = support_telegram_url($mysqli);
    $rv_menu = array(
        array('dashboard', 'dashboard.php', 'ri-home-4-line', 'Painel'),
        array('devices', 'devices.php', 'ri-device-line', 'Dispositivos'),
        array('credits', 'credits.php', 'ri-coin-line', 'Créditos'),
        array('dns', 'dns.php', 'ri-server-line', 'DNS'),
        array('theme', 'theme.php', 'ri-palette-line', 'Minha Marca'),
        array('posters', 'posters.php', 'ri-image-line', 'Pôsteres'),
        array('support', 'support.php', 'ri-customer-service-2-line', 'Suporte'),
    );
    ?>
    <!-- menu lateral (no celular abre pelo botão ☰ e fecha ao escolher um item ou tocar fora) -->
    <aside class="rv-side" id="rvSide">
        <a class="rv-side__brand" href="dashboard.php">
            <img src="<?php echo rv_e(brand_logo_url('../')); ?>" alt="" onerror="this.style.display='none'">
            <span><?php echo rv_e(APP_NAME); ?><small>Revenda</small></span>
        </a>
        <nav class="rv-side__nav">
            <?php foreach ($rv_menu as $mi) { ?>
                <a class="rv-side__link <?php if ($active == $mi[0]) echo 'active'; ?>" href="<?php echo $mi[1]; ?>">
                    <i class="<?php echo $mi[2]; ?>"></i><span><?php echo $mi[3]; ?></span>
                    <?php if ($mi[0] == 'support' && $rv_sp_unread) { ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $rv_sp_unread; ?></span><?php } ?>
                </a>
            <?php } ?>
        </nav>
        <div class="rv-side__foot">
            <a href="credits.php" class="rv-side__credits"><i class="ri-coin-fill"></i><span><b><?php echo (int)$reseller['credits']; ?></b> créditos</span><i class="ri-add-circle-line ms-auto"></i></a>
            <?php if ($rv_tg !== '') { ?>
                <a href="<?php echo rv_e($rv_tg); ?>" target="_blank" rel="noopener" class="rv-side__tg"><i class="ri-telegram-fill"></i><span>Canal no Telegram</span></a>
            <?php } ?>
        </div>
    </aside>
    <div class="rv-backdrop" id="rvBackdrop"></div>

    <div class="rv-body">
        <header class="rv-top">
            <button type="button" class="rv-burger" id="rvBurger" aria-label="Menu"><i class="ri-menu-line"></i></button>
            <h1 class="rv-top__title"><?php echo rv_e($title); ?></h1>
            <div class="rv-top__right">
                <?php echo i18n_switcher(); ?>
                <a href="credits.php" class="rv-balance d-none d-sm-inline-flex"><i class="ri-coin-fill me-1"></i><?php echo (int)$reseller['credits']; ?> créditos</a>
                <div class="dropdown">
                    <a href="#" class="rv-user dropdown-toggle" data-bs-toggle="dropdown"><span class="rv-user__av"><?php echo rv_e(mb_strtoupper(mb_substr(trim($reseller['name']) ?: 'R', 0, 1, 'UTF-8'), 'UTF-8')); ?></span><span class="d-none d-md-inline"><?php echo rv_e($reseller['name']); ?></span></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small rv-muted"><?php echo rv_e($reseller['email']); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="ri-logout-box-r-line me-1"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </header>
    <main class="rv-main">
        <?php $f = rv_take_flash(); if ($f) { ?>
            <div class="alert alert-<?php echo $f['type'] == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo rv_e($f['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>
<?php }

function rv_footer($page_script = ''){ ?>
    </main>
    </div>
    <script src="../assets/vendors/bootstrap/bootstrap.min.js"></script>
    <script>
    // celular: o botão ☰ abre o menu lateral; ele fecha ao escolher um item ou tocar fora
    (function () {
        var b = document.getElementById('rvBurger'); if (!b) return;
        var open = function (on) { document.body.classList.toggle('rv-side-open', on); };
        b.addEventListener('click', function (e) { e.stopPropagation(); open(!document.body.classList.contains('rv-side-open')); });
        document.getElementById('rvBackdrop').addEventListener('click', function () { open(false); });
        document.querySelectorAll('#rvSide a').forEach(function (a) { a.addEventListener('click', function () { open(false); }); });
    })();
    document.addEventListener('click', function (e) {
        var c = e.target.closest('[data-copy]');
        if (!c) return;
        var text = c.getAttribute('data-copy');
        var done = function () { var old = c.innerHTML; c.innerHTML = '<i class="ri-check-line"></i> Copiado'; setTimeout(function () { c.innerHTML = old; }, 1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
        else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
    });
    </script>
    <?php echo $page_script; ?>
</body>
</html>
<?php }
