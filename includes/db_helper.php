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
    
    if(isset($_SESSION['id'])){
    	
    	$profile_qry="SELECT * FROM tbl_admin where id='".$_SESSION['id']."'";
    	$profile_result=mysqli_query($mysqli,$profile_qry);
    	$profile_details=mysqli_fetch_assoc($profile_result);

    	define("PROFILE_IMG",$profile_details['image']);
    }
}
?>