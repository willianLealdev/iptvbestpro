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

function rv_head($title){ ?>
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
        .rv-nav { background: #fff; box-shadow: 0 2px 10px rgba(15,23,42,.06); }
        .rv-nav .navbar-brand { font-weight: 700; color: var(--rv-text); display: flex; align-items: center; gap: 10px; }
        .rv-nav .navbar-brand img { width: 34px; height: 34px; border-radius: 8px; object-fit: cover; }
        .rv-nav .nav-link { color: var(--rv-muted); font-weight: 500; border-radius: 8px; padding: 8px 14px !important; }
        .rv-nav .nav-link.active, .rv-nav .nav-link:hover { color: var(--rv-primary); background: #eef3ff; }
        .rv-balance { background: #f3fdf7; color: #15803d; border: 1px solid #ccf7dd; border-radius: 20px; padding: 6px 14px; font-weight: 600; white-space: nowrap; }
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
<?php }

function rv_header($mysqli, $reseller, $title, $active){
    rv_head($title); ?>
    <nav class="navbar navbar-expand-lg rv-nav mb-4">
        <div class="container-xl">
            <a class="navbar-brand" href="dashboard.php">
                <img src="<?php echo rv_e(brand_logo_url('../')); ?>" alt="" onerror="this.style.display='none'">
                <span><?php echo rv_e(APP_NAME); ?> <small class="rv-muted fw-normal">Revenda</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#rvNav" aria-label="Menu"><i class="ri-menu-line"></i></button>
            <div class="collapse navbar-collapse" id="rvNav">
                <ul class="navbar-nav me-auto ms-lg-4 gap-1">
                    <li class="nav-item"><a class="nav-link <?php if($active=='dashboard'){echo 'active';} ?>" href="dashboard.php"><i class="ri-home-4-line me-1"></i>Painel</a></li>
                    <li class="nav-item"><a class="nav-link <?php if($active=='devices'){echo 'active';} ?>" href="devices.php"><i class="ri-device-line me-1"></i>Dispositivos</a></li>
                    <li class="nav-item"><a class="nav-link <?php if($active=='credits'){echo 'active';} ?>" href="credits.php"><i class="ri-coin-line me-1"></i>Créditos</a></li>
                    <li class="nav-item"><a class="nav-link <?php if($active=='dns'){echo 'active';} ?>" href="dns.php"><i class="ri-server-line me-1"></i>DNS</a></li>
                    <li class="nav-item"><a class="nav-link <?php if($active=='theme'){echo 'active';} ?>" href="theme.php"><i class="ri-palette-line me-1"></i>Minha Marca</a></li>
                    <li class="nav-item"><a class="nav-link <?php if($active=='posters'){echo 'active';} ?>" href="posters.php"><i class="ri-image-line me-1"></i>Pôsteres</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3 py-2 py-lg-0">
                    <span class="rv-balance"><i class="ri-coin-fill me-1"></i><?php echo (int)$reseller['credits']; ?> créditos</span>
                    <div class="dropdown">
                        <a href="#" class="text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown"><?php echo rv_e($reseller['name']); ?></a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small rv-muted"><?php echo rv_e($reseller['email']); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="ri-logout-box-r-line me-1"></i>Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <main class="container-xl pb-5">
        <?php $f = rv_take_flash(); if ($f) { ?>
            <div class="alert alert-<?php echo $f['type'] == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo rv_e($f['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>
<?php }

function rv_footer($page_script = ''){ ?>
    </main>
    <script src="../assets/vendors/bootstrap/bootstrap.min.js"></script>
    <script>
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
