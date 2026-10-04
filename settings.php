<?php $page_title="Settings Admin";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    
    if(!isset($_SESSION['admin_type'])){
        if($_SESSION['admin_type'] == 0){
            session_destroy();
            header( "Location:gerencia.php");
            exit;
        }
    }
    

    
    $qry="SELECT * FROM tbl_settings where id='1'";
    $result=mysqli_query($mysqli,$qry);
    $settings_data=mysqli_fetch_assoc($result);
    
    require_once("includes/mail_resend.php");
    resend_migrate($mysqli);

    $qry_smtp = "SELECT * FROM tbl_smtp_settings where id='1'";
    $result_smtp = mysqli_query($mysqli, $qry_smtp);
    $row = mysqli_fetch_assoc($result_smtp);

    // Detalhe do erro do último teste de envio (Resend)
    $mail_error_detail = '';
    if(isset($_SESSION['mail_error_detail'])){
        $mail_error_detail = $_SESSION['mail_error_detail'];
        unset($_SESSION['mail_error_detail']);
    }
    
    if(isset($_POST['submit_general'])){
        
        $img_res=mysqli_query($mysqli,"SELECT * FROM tbl_settings WHERE id='1'");
        $img_row=mysqli_fetch_assoc($img_res);
        
        if($_FILES['app_logo']['name']!=""){
            
           
            if($img_row['app_logo']!=""){
                unlink('images/'.$img_row['app_logo']);
            }
            
            $ext = pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION);
            $app_logo=rand(0,99999)."_logo.".$ext;
            $tpath1='images/'.$app_logo;
            
            if($ext!='png')  {
                $pic1=compress_image($_FILES["app_logo"]["tmp_name"], $tpath1, 80);
            }else{
                $tmp = $_FILES['app_logo']['tmp_name'];
                move_uploaded_file($tmp, $tpath1);
            }
            
        } else {
            $app_logo = $img_row['app_logo'];
        }

        $data = array(
            'app_name'  =>  $_POST['app_name'],
            'app_logo'  =>  $app_logo,
        );
        
        $settings_edit=Update('tbl_settings', $data, "WHERE id = '1'");
        $_SESSION['msg']="11";
        header( "Location:settings.php");
        exit;
    }
    
    if (isset($_POST['submit']) && isset($_POST['smtpIndex']) && $_POST['smtpIndex'] == 'resend') {
        // Resend: API Key em branco mantém a chave atual
        $api_key = trim(isset($_POST['resend_api_key']) ? $_POST['resend_api_key'] : '');
        if ($api_key == '') { $api_key = (string)$row['resend_api_key']; }
        $from = trim(isset($_POST['resend_from']) ? $_POST['resend_from'] : '');
        if ($api_key == '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['class'] = "error";
            $_SESSION['msg'] = "resend_invalid";
            header("Location:settings.php");
            exit;
        }
        $stmt = mysqli_prepare($mysqli, "UPDATE tbl_smtp_settings SET smtp_type = 'resend', resend_api_key = ?, resend_from = ?, resend_from_name = ? WHERE id = '1'");
        $from_name = mb_substr(trim(strip_tags(isset($_POST['resend_from_name']) ? $_POST['resend_from_name'] : '')), 0, 120);
        mysqli_stmt_bind_param($stmt, 'sss', $api_key, $from, $from_name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['class'] = "success";
        $_SESSION['msg'] = "11";
        header("Location:settings.php");
        exit;
    }

    if (isset($_POST['submit'])) {
        $key = ($_POST['smtpIndex'] == 'gmail') ? '0' : '1';
        $password = '';
        if ($_POST['smtp_password'][$key] != '') {
            $password = $_POST['smtp_password'][$key];
        } else {
            if ($key == 0) {
                $password = $row['smtp_gpassword'];
            } else {
                $password = $row['smtp_password'];
            }
        }
        if ($key == 0) {
            $data = array(
                'smtp_type'  =>  'gmail',
                'smtp_ghost'  =>  $_POST['smtp_host'][$key],
                'smtp_gemail'  =>  $_POST['smtp_email'][$key],
                'smtp_gpassword'  =>  $password,
                'smtp_gsecure'  =>  $_POST['smtp_secure'][$key],
                'gport_no'  =>  $_POST['port_no'][$key]
            );
        } else {
            $data = array(
                'smtp_type'  =>  'server',
                'smtp_host'  =>  $_POST['smtp_host'][$key],
                'smtp_email'  =>  $_POST['smtp_email'][$key],
                'smtp_password'  =>  $password,
                'smtp_secure'  =>  $_POST['smtp_secure'][$key],
                'port_no'  =>  $_POST['port_no'][$key]
            );
        }
        $sql = "SELECT * FROM tbl_smtp_settings WHERE id='1'";
        $res = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
        if (mysqli_num_rows($res) > 0) {
            $update = Update('tbl_smtp_settings', $data, "WHERE id = '1'");
        } else {
            $insert = Insert('tbl_smtp_settings', $data);
        }
        $_SESSION['class'] = "success";
        $_SESSION['msg'] = "11";
        header("Location:settings.php");
        exit;
    }
?>

<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo (isset($page_title)) ? $page_title : "" ?></li>
            </ol>
        </nav>
            
        <div class="card">
            <div class="card-body p-0">                    
                <div class="nsofts-setting">
                    <div class="nsofts-setting__sidebar">
                        <a class="d-inline-flex align-items-center text-decoration-none fw-semibold mb-4">
                            <span class="ps-2 lh-1"><?php echo (isset($page_title)) ? $page_title : "" ?></span>
                        </a>
                        <div class="nav flex-column nav-pills" id="nsofts_setting" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active" id="nsofts_setting_1" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_1" type="button" role="tab" aria-controls="nsofts_setting_1" aria-selected="true">
                                <i class="ri-list-settings-line"></i>
                                <span>General</span>
                            </button>
                            
                            <button class="nav-link" id="nsofts_setting_2" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_2" type="button" role="tab" aria-controls="nsofts_setting_2" aria-selected="false">
                                <i class="ri-mail-settings-line"></i>
                                <span>SMTP Settings</span>
                            </button>
                            
                            <button class="nav-link" id="nsofts_setting_3" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_3" type="button" role="tab" aria-controls="nsofts_setting_3" aria-selected="false">
                                <i class="ri-mail-send-line"></i>
                                <span>Check Mail Confi</span>
                            </button>
                                
                        </div>
                    </div>
                    <div class="nsofts-setting__content">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="nsofts_setting_content_1" role="tabpanel" aria-labelledby="nsofts_setting_1" tabindex="0">
                                <form action="" name="settings_general" method="POST" enctype="multipart/form-data">
                                    <h4 class="mb-4">General Settings</h4>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Site name</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_name" id="app_name" value="<?php echo $settings_data['app_name']?>" required="required">
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Site logo</label>
                                        <div class="col-sm-10">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <input type="file" class="form-control-file" name="app_logo" value="fileupload" accept=".png, .jpg, .JPG .PNG" onchange="fileValidation()" id="fileupload">
                                                    <p class="control-label-help hint_lbl">(Recommended resolution: 512x512)</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <?php if($settings_data['app_logo']!='' AND file_exists('images/'.$settings_data['app_logo'])) { ?>
                                                        <div class="fileupload_img" id="imagePreview">
                                                            <img  type="image" src="images/<?=$settings_data['app_logo']?>" style="width: 50px;height: 50px"   alt="image" />
                                                        </div>
                                                    <?php }else{ ?>
                                                        <div class="fileupload_img" id="imagePreview">
                                                            <img type="image" src="assets/images/300x300.jpg" style="width: 50px; height: 50px"  alt="image" />
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="submit_general" class="btn btn-primary" style="min-width: 120px;">Save</button>
                                </form>
                            </div>
                            
                            <div class="tab-pane fade" id="nsofts_setting_content_2" role="tabpanel" aria-labelledby="nsofts_setting_2" tabindex="0">
                                <h4 class="mb-4">SMTP Settings</h4>
                                
                                <div class="row">
                                    <form action="" method="post" class="form form-horizontal" enctype="multipart/form-data">
                                        <div class="mb-3 row">
                                            <label class="col-sm-2 col-form-label">SMTP Type</label>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="radio" name="smtp_type" id="gmail" class="form-check-input" value="gmail" <?php if ($row['smtp_type'] == 'gmail') { echo 'checked'; } ?>>
                                                    <label class="form-check-label" for="gmail"> Gmail SMTP</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="radio" name="smtp_type" id="server" class="form-check-input" value="server" <?php if ($row['smtp_type'] == 'server') { echo 'checked'; } ?>>
                                                    <label class="form-check-label" for="server">Server SMTP</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="radio" name="smtp_type" id="resend" class="form-check-input" value="resend" <?php if ($row['smtp_type'] == 'resend') { echo 'checked'; } ?>>
                                                    <label class="form-check-label" for="resend">Resend API</label>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($mail_error_detail != '') { ?>
                                            <div class="alert alert-danger"><b>Falha no envio de teste:</b> <?php echo htmlspecialchars($mail_error_detail); ?></div>
                                        <?php } ?>
                                        <input type="hidden" name="smtpIndex" value="<?= $row['smtp_type'] ?>">
                                        
                                        <div class="gmailContent" <?php if ($row['smtp_type'] == 'gmail') { echo 'style="display:block"'; } else { echo 'style="display:none"'; } ?>>
                                            <div class="mb-3 row">
                                                <label for="" class="col-sm-2 col-form-label">SMTP Host</label>
                                                <div class="col-sm-10">
                                                    <input type="text" name="smtp_host[]" class="form-control col-md-7" value="<?= $row['smtp_ghost'] ?>" placeholder="mail.example.in" <?php if ($row['smtp_type'] == 'gmail') {echo 'required';} ?>> 
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label for="" class="col-sm-2 col-form-label">Email</label>
                                                <div class="col-sm-10">
                                                    <input type="text" name="smtp_email[]" class="form-control col-md-7" value="<?= $row['smtp_gemail'] ?>" placeholder="info@example.com" <?php if ($row['smtp_type'] == 'gmail') { echo 'required';} ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label for="" class="col-sm-2 col-form-label">Password</label>
                                                <div class="col-sm-10">
                                                    <input type="password" name="smtp_password[]" class="form-control col-md-7" value="" placeholder="********">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label class="col-sm-2 col-form-label">SMTPSecure</label>
                                                <div class="col-md-3">
                                                    <select name="smtp_secure[]" class="select2 form-control" <?php if ($row['smtp_type'] == 'gmail') { echo 'required';} ?>>
                                                        <option value="tls" <?php if ($row['smtp_gsecure'] == 'tls') {
                                                            echo 'selected';
                                                        } ?>>TLS</option>
                                                        <option value="ssl" <?php if ($row['smtp_gsecure'] == 'ssl') {
                                                            echo 'selected';
                                                        } ?>>SSL</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" name="port_no[]" class="form-control" value="<?= $row['gport_no'] ?>" placeholder="Enter Port No." <?php if ($row['smtp_type'] == 'gmail') { echo 'required';} ?>>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="serverContent" <?php if ($row['smtp_type'] == 'server') { echo 'style="display:block"'; } else { echo 'style="display:none"'; } ?>>
                                            <div class="mb-3 row">
                                                <label for="" class="col-sm-2 col-form-label">SMTP Host</label>
                                                <div class="col-sm-10">
                                                    <input type="text" name="smtp_host[]" id="smtp_host" class="form-control col-md-7" value="<?= $row['smtp_host'] ?>" placeholder="mail.example.in" <?php if ($row['smtp_type'] == 'server') { echo 'required';} ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label for="" class="col-sm-2 col-form-label">Email</label>
                                                <div class="col-sm-10">
                                                    <input type="text" name="smtp_email[]" id="smtp_email" class="form-control col-md-7" value="<?= $row['smtp_email'] ?>" placeholder="info@example.com" <?php if ($row['smtp_type'] == 'server') { echo 'required';} ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label for="" class="col-sm-2 col-form-label">Password</label>
                                                <div class="col-sm-10">
                                                    <input type="password" name="smtp_password[]" id="smtp_password" class="form-control col-md-7" value="" placeholder="********">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label class="col-sm-2 col-form-label">SMTPSecure</label>
                                                <div class="col-md-3">
                                                    <select name="smtp_secure[]" class="select2 form-control" <?php if ($row['smtp_type'] == 'server') { echo 'required';} ?>>
                                                        <option value="tls" <?php if ($row['smtp_secure'] == 'tls') { echo 'selected'; } ?>>TLS</option>
                                                        <option value="ssl" <?php if ($row['smtp_secure'] == 'ssl') { echo 'selected'; } ?>>SSL</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" name="port_no[]" id="port_no" class="form-control" value="<?= $row['port_no'] ?>" placeholder="Enter Port No." <?php if ($row['smtp_type'] == 'server') { echo 'required';} ?>>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="resendContent" <?php if ($row['smtp_type'] == 'resend') { echo 'style="display:block"'; } else { echo 'style="display:none"'; } ?>>
                                            <div class="alert alert-light border small">
                                                Os e-mails do sistema são enviados pela API da <a href="https://resend.com" target="_blank" rel="noopener">Resend</a>.
                                                O e-mail remetente precisa ser de um <b>domínio verificado</b> na sua conta Resend (Domains).
                                                Crie a chave em <b>API Keys</b> com permissão <b>Sending access</b>.
                                            </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-2 col-form-label">API Key</label>
                                                <div class="col-sm-10">
                                                    <input type="password" name="resend_api_key" class="form-control" value="" autocomplete="new-password"
                                                           placeholder="<?php echo $row['resend_api_key'] != '' ? 'Chave salva: '.htmlspecialchars(resend_mask_key($row['resend_api_key'])).' (deixe em branco para manter)' : 're_xxxxxxxxxxxxxxxxxxxx'; ?>"
                                                           <?php if ($row['smtp_type'] == 'resend' && $row['resend_api_key'] == '') { echo 'required'; } ?>>
                                                </div>
                                            </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-2 col-form-label">E-mail remetente</label>
                                                <div class="col-sm-10">
                                                    <input type="email" name="resend_from" class="form-control" value="<?php echo htmlspecialchars((string)$row['resend_from']); ?>" placeholder="naoresponda@seudominio.com" <?php if ($row['smtp_type'] == 'resend') { echo 'required'; } ?>>
                                                </div>
                                            </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-2 col-form-label">Nome do remetente</label>
                                                <div class="col-sm-10">
                                                    <input type="text" name="resend_from_name" class="form-control" maxlength="120" value="<?php echo htmlspecialchars((string)$row['resend_from_name']); ?>" placeholder="<?php echo htmlspecialchars(APP_NAME); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="submit" class="btn btn-primary" style="min-width: 120px;">Save</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="nsofts_setting_content_3" role="tabpanel" aria-labelledby="nsofts_setting_3" tabindex="0">
                                <form action="" method="post" id="check_smtp_form">
                                    <div class="alert alert-info" role="alert">
                                        <h4 class="mb-4">Check Mail Configuration</h4>
                                        <p>Send test mail to your email to check Email functionality work or not.</p>
                                        <hr>
                                        <div class="mb-3 row">
                                             <div class="form-group">
                                                <label class="control-label">Email <span style="color: red">*</span>:-</label>
                                                <input type="text" name="email" class="form-control" autocomplete="off" placeholder="info@example.com" required="">
                                            </div>
                                        </div>
                                        <button type="submit" name="btn_send" class="btn btn-primary" style="min-width: 120px;">Send</button>
                                    </div>
                                    <?php $mail_log = mail_log_list($mysqli); ?>
                                    <div class="card border-0" style="background:#f7f9fe;">
                                        <div class="card-body">
                                            <h6 class="mb-1">Últimos envios do sistema</h6>
                                            <p class="small text-muted mb-3">Todo e-mail enviado (teste, confirmação de cadastro, nova senha, alertas) aparece aqui com a resposta do provedor. "Aceito" significa que o provedor recebeu; a entrega final aparece no painel da Resend (Emails).</p>
                                            <?php if ($mail_log) { ?>
                                                <div class="table-responsive"><table class="table table-sm mb-0">
                                                    <thead><tr><th>Data</th><th>Para</th><th>Assunto</th><th>Via</th><th>Resultado</th></tr></thead>
                                                    <tbody>
                                                    <?php foreach ($mail_log as $l) { ?>
                                                        <tr>
                                                            <td class="text-nowrap small"><?php echo date('d/m/Y H:i', (int)$l['t']); ?></td>
                                                            <td class="small" translate="no"><?php echo htmlspecialchars($l['to']); ?></td>
                                                            <td class="small" translate="no"><?php echo htmlspecialchars($l['s']); ?></td>
                                                            <td class="small"><?php echo htmlspecialchars($l['p']); ?></td>
                                                            <td class="small"><?php if ($l['ok']) { ?><span class="badge bg-success">Aceito</span><?php } else { ?><span class="badge bg-danger">Falhou</span> <span class="text-danger" translate="no"><?php echo htmlspecialchars($l['e']); ?></span><?php } ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                    </tbody>
                                                </table></div>
                                            <?php } else { ?>
                                                <p class="small text-muted mb-0">Nenhum envio registrado ainda.</p>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->
    
<?php include("includes/footer.php");?>

<script type="text/javascript">
// Troca o bloco visível conforme o tipo escolhido (Gmail SMTP / Server SMTP / Resend API)
function smtpShow(type) {
    var blocks = { gmail: $(".gmailContent"), server: $(".serverContent"), resend: $(".resendContent") };
    $.each(blocks, function (key, el) {
        var active = key === type;
        el.toggle(active);
        el.find("input").not("[type='password'], [name='resend_from_name']").attr("required", active);
    });
    // API Key só é obrigatória se ainda não houver uma salva
    $("input[name='resend_api_key']").attr("required", type === 'resend' && <?php echo $row['resend_api_key'] == '' ? 'true' : 'false'; ?>);
    $("input[name='smtpIndex']").val(type);
}
$("input[name='smtp_type']").on("change", function () { smtpShow($(this).val()); });
smtpShow($("input[name='smtp_type']:checked").val() || 'gmail');
$("#check_smtp_form").on("submit", function(e) {
    e.preventDefault();
    
    var email = $(this).find("input[name='email']").val();
    
    swal({
        title: "Are you sure?",
        text: 'Email will receive to ' + email,
		type: "warning",
		confirmButtonClass: 'btn btn-primary m-2',
        cancelButtonClass: 'btn btn-danger m-2',
        buttonsStyling: false,
		showCancelButton: true,
		confirmButtonText: "Yes",
		cancelButtonText: "No",
		closeOnConfirm: false,
		closeOnCancel: false,
		showLoaderOnConfirm: true
    }).then(function(result) {
        if (result.value) {
            $.ajax({
                type: 'post',
                url: 'processData.php',
                dataType: 'json',
                data: {'action': 'check_smtp','email': email},
                success: function(res) {
                    swal({
                        title: res.ok ? 'Enviado' : 'Falhou',
                        text: (res.msg || '') + (res.from ? '\n\nRemetente: ' + res.from + ' (' + res.provider + ')' : ''),
                        type: res.ok ? 'success' : 'error',
                        confirmButtonClass: 'btn btn-primary m-2',
                        buttonsStyling: false
                    }).then(function () { location.reload(); });
                },
                error: function (xhr) {
                    swal({ title: 'Falhou', text: 'O servidor recusou o pedido (HTTP ' + xhr.status + '). Recarregue a página e tente de novo.', type: 'error', confirmButtonClass: 'btn btn-primary m-2', buttonsStyling: false });
                }
            });
        } else {
            swal.close();
        }
    });
});
</script>