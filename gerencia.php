<?php
    require_once("includes/brand_helper.php"); // logo padrão também na tela de requisitos do servidor
    $license_filename="includes/.lic";
    if(!file_exists($license_filename)){
        header("Location:install/index.php");
        exit;
    }
    
    $errors = false;
    if(phpversion() < "7.4"){
        $errors = true;
    }
    if(!extension_loaded('mysqli')){
        $errors = true; 
    }
    if(!extension_loaded('curl')){
        $errors = true; 
    }
    if(!extension_loaded('pdo')){
        $errors = true; 
    }
    if(!extension_loaded('json')){
        $errors = true; 
    }

    if(!$errors){
        include("includes/db_helper.php");
        include("language/language.php");
        
        if(isset($_SESSION['admin_name']) AND isset($_SESSION['admin_type'])) {
            header("Location:dashboard.php");
            exit;
        }
    } else {
        define("APP_NAME","Errors extension");
        define("APP_LOGO","errors");
    }
?>
<?php
require_once("includes/auth_layout.php");
$au_msg = '';
$au_type = 'err';
if (isset($_SESSION['msg'])) {
    $au_msg = isset($client_lang[$_SESSION['msg']]) ? $client_lang[$_SESSION['msg']] : '';
    $au_type = (isset($_SESSION['class']) && $_SESSION['class'] == 'success') ? 'ok' : 'err';
    unset($_SESSION['msg'], $_SESSION['class']);
}
auth_start(array('prefix' => '', 'title' => 'Entrar', 'badge' => 'Painel administrativo', 'icon' => 'ri-shield-user-line', 'subtitle' => 'Acesso restrito à administração.'));
if ($errors) {
    $checks = array(
        array(phpversion() >= "7.4", 'PHP '.phpversion().' (mínimo 7.4)'),
        array(extension_loaded('mysqli'), 'Extensão MySQLi'),
        array(extension_loaded('curl'), 'Extensão Curl'),
        array(extension_loaded('pdo'), 'Extensão PDO'),
        array(extension_loaded('json'), 'Extensão JSON'),
    );
    auth_alert('warn', 'O servidor não atende aos requisitos do sistema.');
    foreach ($checks as $c) auth_alert($c[0] ? 'ok' : 'err', $c[1]);
} else { ?>
        <?php auth_alert($au_type, $au_msg); ?>
        <form action="login_db.php" method="post" autocomplete="on">
            <?php auth_input(array('name' => 'user_login', 'id' => 'user_login', 'label' => 'E-mail ou usuário', 'icon' => 'ri-user-3-line', 'placeholder' => 'admin', 'attrs' => 'autofocus autocomplete="username"')); ?>
            <?php auth_input(array('name' => 'nsofts_password_input', 'id' => 'nsofts_password_input', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'placeholder' => '••••••••', 'attrs' => 'autocomplete="current-password"')); ?>
            <div class="au-line">
                <label class="au-check"><input type="checkbox" id="remember"> Lembrar de mim</label>
                <a href="auth_pass_recovery.php">Esqueceu a senha?</a>
            </div>
            <button type="submit" class="au-btn"><i class="ri-login-box-line"></i> Entrar</button>
        </form>
<?php }
auth_end(); ?>
