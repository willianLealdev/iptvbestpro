<?php 
    require("includes/db_helper.php");
    require("includes/lb_helper.php");
    require("language/language.php");
	require("language/api_language.php");
	include("smtp_email.php");
    
	$response=array();
	
	$_SESSION['class'] = "success";

	// ---- Segurança: só admin logado, só pedidos do próprio painel, só tabelas/colunas permitidas ----
	header('Content-Type: application/json; charset=utf-8');
	function pd_deny($code = 403){ http_response_code($code); echo json_encode(array('status' => 0, 'msg' => 'Acesso negado.')); exit; }
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') pd_deny(405);
	if (empty($_SESSION['id']) || empty($_SESSION['admin_name']) || !isset($_SESSION['admin_type'])) pd_deny();
	$pd_host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) : '';
	$pd_origin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
	if ($pd_origin === '' || strtolower((string)parse_url($pd_origin, PHP_URL_HOST)) !== $pd_host) pd_deny();
	$pd_type = (int)$_SESSION['admin_type'];

	$pd_action = isset($_POST['action']) ? (string)$_POST['action'] : '';
	$pd_tables_toggle = array('tbl_admin', 'tbl_devices', 'tbl_resellers', 'tbl_sidebar', 'tbl_xui_dns', 'tbl_stream_dns', 'tbl_xui_dns_block', 'tbl_users', 'tbl_custom_ads', 'tbl_notification', 'tbl_poster_gallery');
	$pd_tables_delete = array('tbl_admin', 'tbl_users', 'tbl_policy_deletion', 'tbl_xui_dns', 'tbl_stream_dns', 'tbl_xui_dns_block', 'tbl_notification', 'tbl_reports', 'tbl_resellers', 'tbl_devices', 'tbl_poster_gallery');
	if ($pd_action == 'toggle_status') {
		if (!in_array(isset($_POST['table']) ? $_POST['table'] : '', $pd_tables_toggle, true)) pd_deny(400);
		if ((isset($_POST['column']) ? $_POST['column'] : '') !== 'status') pd_deny(400);
		if ($_POST['table'] == 'tbl_admin' && $pd_type != 3) pd_deny();
		$_POST['id'] = (string)(int)(isset($_POST['id']) ? $_POST['id'] : 0);
		$_POST['for_action'] = (isset($_POST['for_action']) && $_POST['for_action'] == 'enable') ? 'enable' : 'disable';
	} else if ($pd_action == 'multi_action') {
		$pd_table = isset($_POST['table']) ? $_POST['table'] : '';
		if (!in_array($pd_table, $pd_tables_delete, true)) pd_deny(400);
		if ($pd_type == 0) pd_deny();                                   // editor não exclui/altera em massa
		if ($pd_table == 'tbl_admin' && $pd_type != 3) pd_deny();       // só o Super Admin mexe em admins
		$pd_ids = (isset($_POST['id']) && is_array($_POST['id'])) ? $_POST['id'] : explode(',', (string)(isset($_POST['id']) ? $_POST['id'] : ''));
		$pd_ids = array_values(array_filter(array_map('intval', $pd_ids)));
		if ($pd_table == 'tbl_admin') $pd_ids = array_values(array_diff($pd_ids, array((int)$_SESSION['id']))); // não exclui a si mesmo
		if (!$pd_ids) pd_deny(400);
		$_POST['id'] = $pd_ids;
		$_POST['for_action'] = in_array(isset($_POST['for_action']) ? $_POST['for_action'] : '', array('enable', 'disable', 'delete'), true) ? $_POST['for_action'] : '';
	} else if ($pd_action == 'check_smtp') {
		if ($pd_type == 0) pd_deny();
		if (!filter_var(isset($_POST['email']) ? trim($_POST['email']) : '', FILTER_VALIDATE_EMAIL)) pd_deny(400);
	} else {
		pd_deny(400);
	}

	switch ($pd_action) {
	    
	    case 'toggle_status':
        	$table_nm = $_POST['table'];
        
        	$sql_schema="SHOW COLUMNS FROM $table_nm";
        	$res_schema=mysqli_query($mysqli, $sql_schema);
        	$row_schema=mysqli_fetch_array($res_schema);
        
        	$id = $_POST['id'];
        	$for_action = $_POST['for_action'];
        	$column = $_POST['column'];
        	$tbl_id = $row_schema[0];
        
        	$message='';
        
        	if ($for_action == 'enable') {
        		$data = array($column  =>  '1');
        		$edit_status = Update($table_nm, $data, "WHERE $tbl_id = '$id'");
        		$message=$client_lang['13'];
        	} else {
        		$data = array($column  =>  '0');
        		$edit_status = Update($table_nm, $data, "WHERE $tbl_id = '$id'");
        		$message=$client_lang['14'];
        	}
        
        	$response['status'] = 1;
        	$response['action'] = $for_action;
        	$response['msg'] = $message;
        	$response['class'] = "success";
        	
        	echo json_encode($response);
    	break;
    	
    	case 'multi_action':
    
        	$action=$_POST['for_action'];
        	$table=$_POST['table'];
        
        	if(is_array($_POST['id'])) {
        	    $ids=implode(",", $_POST['id']);
        	} else {
        	    $ids=$_POST['id'];
        	}
        
        	if($action=='enable'){
        		$sql="UPDATE $table SET `status`='1' WHERE `id` IN ($ids)";
        		mysqli_query($mysqli, $sql);
        		$_SESSION['msg']="13";	
        		$_SESSION['class']="success";			
        	}
        	else if($action=='disable'){
        		$sql="UPDATE $table SET `status`='0' WHERE `id` IN ($ids)";
        		if(mysqli_query($mysqli, $sql)){
        			$_SESSION['msg']="14";
        			$_SESSION['class']="success";
        		}
        	}
        	else if($action=='delete'){
        	    
        	    if($table=='tbl_admin'){
                    $sqlDelete="DELETE FROM $table WHERE `id` IN ($ids)";
                    mysqli_query($mysqli, $sqlDelete);
                } 
                
                else if($table=='tbl_users'){
					$deleteSql = "DELETE FROM $table WHERE `id` IN ($ids)";
					mysqli_query($mysqli, $deleteSql);
				}

				else if($table=='tbl_policy_deletion'){
					$deleteSql = "DELETE FROM $table WHERE `id` IN ($ids)";
					mysqli_query($mysqli, $deleteSql);
				}
				
				else if($table=='tbl_xui_dns'){
					$deleteSql = "DELETE FROM $table WHERE `id` IN ($ids)";
					mysqli_query($mysqli, $deleteSql);
				}
				
				else if($table=='tbl_stream_dns'){
					$deleteSql = "DELETE FROM $table WHERE `id` IN ($ids)";
					mysqli_query($mysqli, $deleteSql);
				}
				
				else if($table=='tbl_xui_dns_block'){
					$deleteSql = "DELETE FROM $table WHERE `id` IN ($ids)";
					mysqli_query($mysqli, $deleteSql);
				}
				
			    else if($table=='tbl_notification'){
                    $sqlDelete="DELETE FROM $table WHERE `id` IN ($ids)";
                    mysqli_query($mysqli, $sqlDelete);
                }
                
                else if($table=='tbl_reports'){
                    $sqlDelete="DELETE FROM $table WHERE `id` IN ($ids)";
                    mysqli_query($mysqli, $sqlDelete);
                }
				
				else if($table=='tbl_resellers'){
                    $reseller_ids = implode(',', array_filter(array_map('intval', explode(',', $ids))));
                    if($reseller_ids != '' && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] != 0){
                        mysqli_query($mysqli, "DELETE FROM tbl_reseller_credits WHERE `reseller_id` IN ($reseller_ids)");
                        mysqli_query($mysqli, "UPDATE tbl_devices SET `reseller_id` = 0 WHERE `reseller_id` IN ($reseller_ids)");
                        mysqli_query($mysqli, "DELETE FROM tbl_resellers WHERE `id` IN ($reseller_ids)");
                    }
                }

				else if($table=='tbl_devices'){
                    $device_ids = implode(',', array_filter(array_map('intval', explode(',', $ids))));
                    if($device_ids != '' && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] != 0){
                        mysqli_query($mysqli, "DELETE FROM tbl_devices WHERE `id` IN ($device_ids)");
                    }
                }

				else if($table=='tbl_poster_gallery'){
                    $sqlCategory="SELECT * FROM $table WHERE `id` IN ($ids)";
                    $res=mysqli_query($mysqli, $sqlCategory);
                    while ($row=mysqli_fetch_assoc($res)){
                    	if($row['poster_image']!="") {
                    		unlink('images/'.$row['poster_image']);
                    	}
                    }
                    $deleteSql="DELETE FROM $table WHERE `id` IN ($ids)";
                    mysqli_query($mysqli, $deleteSql);
                }
				
                $_SESSION['msg']="12";
                $_SESSION['class']="success";
            }
            $response['status']=1;	
            echo json_encode($response);
        break;
        
    	case 'check_smtp':{
    		$to = trim($_POST['email']);
    		$recipient_name='Check User';
    
    		$subject = '[IMPORTANT] '.APP_NAME.' Check SMTP Configuration';
    
    		$message='<div style="background-color: #f9f9f9;" align="center"><br />
    		<table style="font-family: OpenSans,sans-serif; color: #666666;" border="0" width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#FFFFFF">
    		<tbody>
    		<tr>
    		<td colspan="2" bgcolor="#FFFFFF" align="center"><img src="'.$file_path.brand_logo_url().'" alt="header" /></td>
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
    		<p style="color: #262626; font-size: 24px; margin-top:0px;">Hi, '.$_SESSION['admin_name'].'</p>
    		<p style="color: #262626; font-size: 18px; margin-top:0px;">This is the demo mail to check SMTP Configuration. </p>
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
    
    		send_email($to,$recipient_name,$subject,$message, true);
    		$_SESSION['msg'] = "22";
    		$response['status'] = 1;
			echo json_encode($response);
    		break;
    	}
        
        default:
        	# code...
        	break;
	}
?>