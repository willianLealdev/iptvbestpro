<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

require_once(__DIR__.'/includes/mail_resend.php');
resend_migrate($mysqli);

$sql_smtp="SELECT * FROM tbl_smtp_settings where id='1'";
$smtp_res=mysqli_query($mysqli,$sql_smtp);
$smtp_row=mysqli_fetch_assoc($smtp_res);

// Provedor de envio: 'resend' usa a API da Resend; os demais usam SMTP (PHPMailer)
define("MAIL_PROVIDER", $smtp_row['smtp_type'] == 'resend' ? 'resend' : 'smtp');

if($smtp_row['smtp_type']=='resend'){
    define("SMTP_HOST", '');
    define("SMTP_EMAIL", $smtp_row['resend_from']);
    define("SMTP_PASSWORD", '');
    define("SMTP_SECURE", '');
    define("PORT_NO", '');
    define("RESEND_API_KEY", (string)$smtp_row['resend_api_key']);
    define("RESEND_FROM_NAME", $smtp_row['resend_from_name'] != '' ? $smtp_row['resend_from_name'] : APP_NAME);
}
else if($smtp_row['smtp_type']=='server'){
    define("SMTP_HOST",$smtp_row['smtp_host']);
    define("SMTP_EMAIL",$smtp_row['smtp_email']);
    define("SMTP_PASSWORD",$smtp_row['smtp_password']);
    define("SMTP_SECURE",$smtp_row['smtp_secure']);
    define("PORT_NO",$smtp_row['port_no']);
}
else if($smtp_row['smtp_type']=='gmail'){
    define("SMTP_HOST",$smtp_row['smtp_ghost']);
    define("SMTP_EMAIL",$smtp_row['smtp_gemail']);
    define("SMTP_PASSWORD",$smtp_row['smtp_gpassword']);
    define("SMTP_SECURE",$smtp_row['smtp_gsecure']);
    define("PORT_NO",$smtp_row['gport_no']);
}
else{
    define("SMTP_HOST",$smtp_row['smtp_host']);
    define("SMTP_EMAIL",$smtp_row['smtp_email']);
    define("SMTP_PASSWORD",$smtp_row['smtp_password']);
    define("SMTP_SECURE",$smtp_row['smtp_secure']);
    define("PORT_NO",$smtp_row['port_no']);
}

function send_email($recipient_email,$recipient_name,$subject,$message, $is_test=false)
{
    global $mysqli;
    $GLOBALS['mail_last_error'] = '';
    if(MAIL_PROVIDER == 'resend'){
        $r = resend_send(RESEND_API_KEY, SMTP_EMAIL, RESEND_FROM_NAME, $recipient_email, $recipient_name, $subject, $message);
        $GLOBALS['mail_last_error'] = $r['error'];
        mail_log_result($mysqli, 'Resend', $recipient_email, $subject, $r['ok'], $r['error'], $r['id']);
        if($is_test){
            $_SESSION['msg'] = $r['ok'] ? "mail_sent_success" : "mail_sent_error";
            if(!$r['ok']){
                $_SESSION['class'] = "error";
                $_SESSION['mail_error_detail'] = $r['error'];
            }
        }
        return $r['ok'];
    }

    $mail = new PHPMailer(false);                              // Passing `true` enables exceptions

    //Server settings
    $mail->SMTPDebug = 0; 
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = SMTP_HOST;  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = SMTP_EMAIL;                 // SMTP username
    $mail->Password = SMTP_PASSWORD;                           // SMTP password
    $mail->SMTPSecure = SMTP_SECURE;                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = PORT_NO;                                      // TCP port to connect to


    $mail->AddReplyTo(SMTP_EMAIL, APP_NAME);
    //Recipients
    $mail->setFrom(SMTP_EMAIL, APP_NAME);
    $mail->addAddress($recipient_email, $recipient_name);     // Add a recipient
     
    //Content
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = $subject;
    $mail->Body    = $message;
    //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $sent = $mail->send();
    $GLOBALS['mail_last_error'] = $sent ? '' : (string)$mail->ErrorInfo;
    mail_log_result($mysqli, 'SMTP', $recipient_email, $subject, $sent, $GLOBALS['mail_last_error']);
    if($is_test){
        if($sent){
            $_SESSION['msg'] = "mail_sent_success";
        }
        else{
            $_SESSION['msg'] = "mail_sent_error";
            $_SESSION['class'] = "error";
            $_SESSION['mail_error_detail'] = $GLOBALS['mail_last_error'];
        }
    }
    return $sent;
}