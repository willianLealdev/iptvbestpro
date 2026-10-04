<?php
/**
 * Proteções globais de todas as páginas do painel (carregado pelo header.php, depois do login).
 *  1. CSRF: todo POST precisa vir do próprio site (Origin/Referer igual ao domínio). Junto com o cookie
 *     de sessão SameSite=Lax, impede que outro site envie formulários usando a sessão do admin.
 *  2. Parâmetros de id: sempre inteiros (as páginas originais montam SQL com eles).
 *  3. Senha padrão/fraca: obriga a trocar antes de usar o painel.
 *  4. Sessão: expira após 8 horas sem uso.
 */

function admin_guard_same_origin(){
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) : '';
    $src = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    if ($src === '' || $host === '') return false;
    return strtolower((string)parse_url($src, PHP_URL_HOST)) === $host;
}

// 1) CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !admin_guard_same_origin()) {
    http_response_code(403);
    exit('Requisição bloqueada por segurança. Recarregue a página e tente novamente.');
}

// 2) ids inteiros
foreach (array('user_id', 'ads_id', 'dns_id', 'sidebar_id', 'report_id', 'page', 'id', 'plan_id', 'order_id', 'poster_id',
               'reseller_id', 'device_pk', 'customer_id', 'sub_id', 'missing_id', 'playlist_id', 'notification_id') as $k) {
    foreach (array('_GET', '_POST') as $src) {
        if (isset($GLOBALS[$src][$k]) && !is_array($GLOBALS[$src][$k])) {
            $GLOBALS[$src][$k] = (string)(int)$GLOBALS[$src][$k];
            $_REQUEST[$k] = $GLOBALS[$src][$k];
        }
    }
}

// 4) sessão expira após 8 horas sem uso
if (!empty($_SESSION['admin_last_seen']) && time() - (int)$_SESSION['admin_last_seen'] > 8 * 3600) {
    session_unset();
    session_destroy();
    header("Location: gerencia.php");
    exit;
}
$_SESSION['admin_last_seen'] = time();

$__guard_file = basename($_SERVER['SCRIPT_NAME']);
$__guard_type = isset($_SESSION['admin_type']) ? (int)$_SESSION['admin_type'] : 0;

// 5) Permissões por página (as checagens originais das páginas tinham falha e não bloqueavam ninguém)
$__super_only = array('manage_admin.php', 'auth_profile.php', 'settings_api.php', 'verification.php', 'settings_payments.php', 'manage_translations.php');
$__no_editor = array('settings.php', 'settings_app.php', 'settings_app_ui.php', 'settings_ads.php', 'settings_web.php', 'settings_xui.php',
                     'settings_stream.php', 'settings_theme.php', 'manage_users.php', 'create_user.php', 'manage_resellers.php', 'create_reseller.php',
                     'manage_credit_orders.php', 'manage_customers.php', 'manage_subscriptions.php', 'manage_sub_plans.php', 'manage_dns_resellers.php',
                     'notification_onesignal.php', 'manage_movie_ui.php', 'manage_data_deletion.php', 'manage_notification.php', 'create_ads.php',
                     'manage_ads.php', 'create_sidebar.php');
if ((in_array($__guard_file, $__super_only) && $__guard_type != 3) || (in_array($__guard_file, $__no_editor) && $__guard_type == 0)) {
    header("Location: dashboard.php");
    exit;
}
// tipo de admin enviado no formulário de usuários: só valores válidos
if (isset($_POST['admin_type'])) $_POST['admin_type'] = in_array((string)$_POST['admin_type'], array('0', '1', '3'), true) ? (string)$_POST['admin_type'] : '0';

// 6) Uploads: só imagens (as páginas originais aceitavam qualquer extensão)
if (!empty($_FILES)) {
    $__ok_ext = array('png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico');
    foreach ($_FILES as $__f) {
        $__names = is_array($__f['name']) ? $__f['name'] : array($__f['name']);
        foreach ($__names as $__n) {
            if ($__n === '' || $__n === null) continue;
            $__ext = strtolower(pathinfo((string)$__n, PATHINFO_EXTENSION));
            if (!in_array($__ext, $__ok_ext, true) || preg_match('/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|htaccess)(\.|$)/i', (string)$__n)) {
                http_response_code(400);
                exit('Arquivo não permitido. Envie apenas imagens (PNG, JPG, WEBP, GIF, SVG ou ICO).');
            }
        }
    }
}

// 3) senha padrão: só a página de troca de senha (e sair) ficam liberadas
if (!empty($_SESSION['admin_must_change_pw']) && !in_array($__guard_file, array('auth_change_password.php', 'logout.php'))) {
    header("Location: auth_change_password.php");
    exit;
}
