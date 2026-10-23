<?php
error_reporting(0);
ob_start();
session_start();

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header("Content-Type: text/html;charset=UTF-8");

// Easypanel/Docker: credenciais vêm das variáveis de ambiente (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME).
// Os valores de fallback continuam sendo substituídos pelo instalador original.
DEFINE ('DB_USER', getenv('DB_USER') ?: 'db_uname');
DEFINE ('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'db_password');
DEFINE ('DB_HOST', getenv('DB_HOST') ?: 'db_hname');
DEFINE ('DB_NAME', getenv('DB_NAME') ?: 'db_name');

$mysqli = @new mysqli(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
if ($mysqli->connect_errno) {
    /* Use your preferred error logging method here */
    die("Connection failed: " . $conn->connect_error);
} else {
    mysqli_query($mysqli,"SET NAMES 'utf8'");

    $setting_qry="SELECT * FROM tbl_settings where id='1'";
    $setting_result=mysqli_query($mysqli,$setting_qry);
    $settings_details=mysqli_fetch_assoc($setting_result);
    
    define("APP_API_KEY",'UzCbzsPZhsH8aeh1JlsK0gR0nYtmpgwcjtXm9g9lAUt4p');
    define("ONESIGNAL_APP_ID",$settings_details['onesignal_app_id']);
    define("ONESIGNAL_REST_KEY",$settings_details['onesignal_rest_key']);
    
    define("APP_NAME",$settings_details['app_name']);
    define("APP_LOGO",$settings_details['app_logo']);
    require_once(__DIR__."/brand_helper.php"); // brand_logo_url(): mesmo logo em todas as telas
    // Idiomas: pt / en / es (seletor + tradução das páginas). Se algo falhar, o sistema segue no idioma original.
    try { require_once(__DIR__."/i18n.php"); } catch (\Throwable $e) { error_log('i18n: '.$e->getMessage()); }
    if (!function_exists('__')) { function __($t){ $a = func_get_args(); array_shift($a); $r = array(); foreach ($a as $i => $v) $r['{'.$i.'}'] = $v; return strtr($t, $r); } }
    if (!function_exists('i18n_switcher')) { function i18n_switcher($theme = 'light', $prefix = ''){ return ''; } }
    if (!function_exists('i18n_switcher_fixed')) { function i18n_switcher_fixed($theme = 'light'){ return ''; } }
    // Buscadores: só o site público pode aparecer no Google; painel, revenda, cliente, pagamento e API nunca
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        $__script = str_replace(chr(92), '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
        if (!in_array($__script, array('/index.php', '/terms.php', '/privacy_policy.php', '/download.php'), true)) header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
    
    if(isset($_SESSION['id'])){
    	
    	$profile_qry="SELECT * FROM tbl_admin where id='".$_SESSION['id']."'";
    	$profile_result=mysqli_query($mysqli,$profile_qry);
    	$profile_details=mysqli_fetch_assoc($profile_result);

    	define("PROFILE_IMG",$profile_details['image']);
    }
}

// Erro de banco: grava no log do servidor e mostra mensagem genérica (nunca o texto do erro SQL na tela)
function db_fail($mysqli){
    error_log('[db] '.mysqli_error($mysqli));
    return 'Erro no banco de dados. Tente novamente.';
}
?>