<?php
/**
 * Portal do cliente final: inicialização, sessão, CSRF e layout.
 * Sessão separada do admin e da revenda (cl_id).
 */
require_once(__DIR__."/../includes/db_helper.php");
require_once(__DIR__."/../includes/lb_helper.php");
require_once(__DIR__."/../includes/customer_helper.php");

customers_migrate($mysqli);

function cl_e($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function cl_csrf_token(){
    if (empty($_SESSION['cl_csrf'])) $_SESSION['cl_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['cl_csrf'];
}
function cl_csrf_field(){ return '<input type="hidden" name="csrf" value="'.cl_csrf_token().'">'; }
function cl_csrf_ok(){
    return isset($_POST['csrf']) && isset($_SESSION['cl_csrf']) && hash_equals($_SESSION['cl_csrf'], (string)$_POST['csrf']);
}

function cl_flash($type, $msg){ $_SESSION['cl_flash'] = array('type' => $type, 'msg' => $msg); }
function cl_take_flash(){
    if (empty($_SESSION['cl_flash'])) return null;
    $f = $_SESSION['cl_flash'];
    unset($_SESSION['cl_flash']);
    return $f;
}
function cl_redirect($url){ header("Location: ".$url); exit; }

// Cliente logado (recarregado a cada página: bloqueio tem efeito imediato)
function cl_current($mysqli){
    if (empty($_SESSION['cl_id'])) return null;
    $id = (int)$_SESSION['cl_id'];
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_customers WHERE id = $id"));
    if (!$row || (int)$row['status'] != 1) { unset($_SESSION['cl_id']); return null; }
    return $row;
}
function cl_require_login($mysqli){
    $c = cl_current($mysqli);
    if (!$c) cl_redirect('index.php');
    return $c;
}

function cl_head($title){ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo cl_e($title); ?> | <?php echo cl_e(brand_name()); ?></title>
    <link href="<?php echo cl_e(brand_logo_url('../')); ?>" rel="icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/remixicon/remixicon.min.css">
    <style>
        :root { --cl-primary: #3b6cf6; --cl-green: #4b9a2a; --cl-text: #1f2a44; --cl-muted: #6b7a99; --cl-bg: #f4f6fb; }
        body { font-family: 'Inter', sans-serif; background: var(--cl-bg); color: var(--cl-text); min-height: 100vh; }
        .cl-nav { background: #fff; box-shadow: 0 2px 10px rgba(15,23,42,.06); }
        .cl-nav .navbar-brand { font-weight: 700; color: var(--cl-text); display: flex; align-items: center; gap: 10px; }
        .cl-nav .navbar-brand img { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; }
        .cl-nav .nav-link { color: var(--cl-muted); font-weight: 500; border-radius: 8px; padding: 8px 14px !important; }
        .cl-nav .nav-link.active, .cl-nav .nav-link:hover { color: var(--cl-primary); background: #eef3ff; }
        .cl-card { background: #fff; border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
        .cl-title { font-size: 22px; font-weight: 700; margin: 0; }
        .cl-muted { color: var(--cl-muted); }
        .cl-btn-green { background: var(--cl-green); border-color: var(--cl-green); color: #fff; }
        .cl-btn-green:hover { background: #3f8323; border-color: #3f8323; color: #fff; }
        .cl-auth { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .cl-auth .cl-card { width: 100%; max-width: 440px; }
        .cl-code { font-family: 'Courier New', monospace; font-size: 30px; font-weight: 700; letter-spacing: 6px; color: var(--cl-primary); }
        .cl-plan { border: 2px solid #eef0f4; border-radius: 14px; padding: 20px; height: 100%; display: flex; flex-direction: column; }
        .cl-plan:hover { border-color: var(--cl-primary); }
        .cl-price { font-size: 30px; font-weight: 800; color: var(--cl-text); }
        .dv-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
        .pk-year { color: #389e0d; background: #f6ffed; border-color: #d9f7be; }
        .pk-trial { color: #d46b08; background: #fff7e6; border-color: #ffe7ba; }
        .pk-none { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
        .pk-expired { color: #cf1322; background: #fff1f0; border-color: #ffccc7; }
        .cl-empty { padding: 40px 10px; text-align: center; color: #9ca3af; }
        .cl-empty i { font-size: 44px; display: block; color: #d1d5db; margin-bottom: 6px; }
    </style>
</head>
<body>
<?php }

function cl_header($customer, $title, $active){
    cl_head($title); ?>
    <nav class="navbar navbar-expand-lg cl-nav mb-4">
        <div class="container-xl">
            <a class="navbar-brand" href="dashboard.php">
                <img src="<?php echo cl_e(brand_logo_url('../')); ?>" alt="">
                <span><?php echo cl_e(brand_name()); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clNav" aria-label="Menu"><i class="ri-menu-line"></i></button>
            <div class="collapse navbar-collapse" id="clNav">
                <ul class="navbar-nav me-auto ms-lg-4 gap-1">
                    <li class="nav-item"><a class="nav-link <?php if($active=='dashboard'){echo 'active';} ?>" href="dashboard.php"><i class="ri-vip-crown-line me-1"></i>Minhas assinaturas</a></li>
                    <li class="nav-item"><a class="nav-link <?php if($active=='plans'){echo 'active';} ?>" href="dashboard.php#planos"><i class="ri-shopping-cart-2-line me-1"></i>Assinar</a></li>
                </ul>
                <div class="dropdown py-2 py-lg-0">
                    <a href="#" class="text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown"><?php echo cl_e($customer['name']); ?></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small cl-muted"><?php echo cl_e($customer['email']); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="ri-logout-box-r-line me-1"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <main class="container-xl pb-5">
        <?php $f = cl_take_flash(); if ($f) { ?>
            <div class="alert alert-<?php echo $f['type'] == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo cl_e($f['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>
<?php }

function cl_footer($page_script = ''){ ?>
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
