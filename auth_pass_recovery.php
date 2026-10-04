<?php
    include("includes/db_helper.php");
    include("includes/lb_helper.php");
    include("language/language.php");
    include("language/app_language.php");
    include("smtp_email.php");
    date_default_timezone_set("Asia/Colombo");
    $file_path = getBaseUrl();
    
    function generateRandomPassword($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    if(isset($_POST['submit'])){
        
        $email=addslashes(trim($_POST['forgot_email']));
        
    	$qry = "SELECT * FROM tbl_admin WHERE email = '$email' AND `id` <> 0"; 
    	$result = mysqli_query($mysqli,$qry);
    	$row = mysqli_fetch_assoc($result);
	
    	if($row['email']!=""){
    		$password=generateRandomPassword(7);
    		
    		$new_password = $password;
    
    		$to = $row['email'];
    		$recipient_name=$row['username'];
    		// subject
    		$subject = str_replace('###', APP_NAME, $app_lang['forgot_password_sub_lbl']);
     		
    		$message='
            <div style="background-color: #f9f9f9;" align="center"><br />
            <table style="font-family: OpenSans,sans-serif; color: #666666;" border="0" width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#FFFFFF">
            <tbody>
            <tr>
            <td colspan="2" bgcolor="#FFFFFF" align="center"><img src="'.brand_logo_url($file_path).'" alt="header" style="width:100px;height:auto"/></td>
            </tr>
            <tr>
            <td width="600" valign="top" bgcolor="#FFFFFF"><br>
            <table style="font-family:OpenSans,sans-serif; color: #666666; font-size: 10px; padding: 15px;" border="0" width="100%" cellspacing="0" cellpadding="0" align="left">
            <tbody>
            <tr>
            <td valign="top"><table border="0" align="left" cellpadding="0" cellspacing="0" style="font-family:OpenSans,sans-serif; color: #666666; font-size: 10px; width:100%;">
            <tbody>
            <tr>
            <td>
            <p style="color: #262626; font-size: 24px; margin-top:0px;"><strong>'.$app_lang['dear_lbl'].' '.$row['username'].'</strong></p>
            <p style="color:#262626; font-size:20px; line-height:32px;font-weight:500;margin-top:5px;"><br>'.$app_lang['your_password_lbl'].': <span style="font-weight:400;">'.$new_password.'</span></p>
            <p style="color:#262626; font-size:17px; line-height:32px;font-weight:500;margin-bottom:30px;">'.$app_lang['thank_you_lbl'].' '.APP_NAME.'</p>
            </td>
            </tr>
            </tbody>
            </table></td>
            </tr>
            </tbody>
            </table></td>
            </tr>
            <tr>
            <td style="color: #262626; padding: 20px 0; font-size: 18px; border-top:5px solid #52bfd3;" colspan="2" align="center" bgcolor="#ffffff">'.$app_lang['email_copyright'].' '.APP_NAME.'.</td>
            </tr>
            </tbody>
            </table>
            </div>';
    
    		send_email($to,$recipient_name,$subject,$message);
    
            $data = array(
              'password'  =>  md5(trim($new_password))
            );
            
            $update_edit=Update('tbl_admin', $data, "WHERE id = '".$row['id']."'");
            
            $_SESSION['msg']="20";
            $_SESSION['class']='success'; 
            header("Location:auth_pass_recovery.php");	 
            exit;
    	} else { 	 
    		$_SESSION['msg']="21";
            $_SESSION['class']='error'; 
            header("location:auth_pass_recovery.php");	 
            exit;
    	}
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
auth_start(array('prefix' => '', 'title' => 'Recuperar senha', 'badge' => 'Painel administrativo', 'icon' => 'ri-shield-user-line', 'subtitle' => 'Informe o e-mail cadastrado para receber uma nova senha.')); ?>
        <?php auth_alert($au_type, $au_msg); ?>
        <form action="" method="POST">
            <?php auth_input(array('name' => 'forgot_email', 'id' => 'forgot_email', 'type' => 'email', 'label' => 'E-mail', 'icon' => 'ri-mail-line', 'placeholder' => 'seu@email.com', 'attrs' => 'autofocus autocomplete="email"')); ?>
            <button type="submit" name="submit" class="au-btn"><i class="ri-mail-send-line"></i> Enviar nova senha</button>
        </form>
        <p class="au-alt"><a href="gerencia.php"><i class="ri-arrow-left-line"></i> Voltar para o login</a></p>
<?php auth_end(); ?>
