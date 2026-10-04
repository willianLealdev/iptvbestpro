<?php 
include("includes/db_helper.php");
include("includes/lb_helper.php"); 
include("language/api_language.php"); 

error_reporting(0);

$file_path = getBaseUrl();

$mysqli->set_charset('utf8mb4');

date_default_timezone_set("Asia/Colombo");

define("PACKAGE_NAME",$settings_details['envato_package_name']);

// For Api header
$API_NAME = 'NEMOSOFTS_APP';

// Purchase code verification
if($settings_details['envato_buyer_name']=='' OR $settings_details['envato_purchase_code']=='' OR $settings_details['envato_api_key']=='') {
    $set[$API_NAME][]=array('MSG'=> 'Purchase code verification failed!','success'=>'0');
	header( 'Content-Type: application/json; charset=utf-8' );
	echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}

$get_helper = get_api_data($_POST['data']);

// Registro de dispositivos (dashboard: instalações, plataformas e países)
require_once("includes/device_tracker.php");
if(in_array($get_helper['helper_name'], array('app_details', 'device_register')) && !empty($get_helper['device_id'])){
    devices_register($mysqli, $get_helper);
}
if($get_helper['helper_name']=="device_register"){
    $set[$API_NAME][] = array('MSG' => 'Device registered', 'success' => '1');
    header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    die();
}

// Ativação por código de revendedor
// device_link:   { device_id, code, platform?, device_model?, ... } -> vincula o aparelho ao dono do código
// device_status: { device_id } -> status: unlinked | pending | active | expired
if($get_helper['helper_name']=="device_link" || $get_helper['helper_name']=="device_status"){
    require_once("includes/reseller_helper.php");
    resellers_migrate($mysqli);

    if($get_helper['helper_name']=="device_link"){
        $link = devices_link($mysqli, $get_helper);
        $device = $link['device'];
        $success = $link['ok'] ? '1' : '0';
        $msg = $link['msg'];
    } else {
        if(!empty($get_helper['device_id'])){ devices_register($mysqli, $get_helper); }
        $device = !empty($get_helper['device_id'])
            ? devices_find($mysqli, devices_clean($get_helper['device_id'], 191), devices_clean(isset($get_helper['application_id']) ? $get_helper['application_id'] : '', 191))
            : null;
        $success = '1';
        $msg = '';
    }

    $status = devices_app_status($device);
    $status_msgs = array(
        'unlinked' => 'Digite o código do seu revendedor para continuar.',
        'pending'  => 'Aguardando ativação pelo seu revendedor.',
        'active'   => 'Dispositivo ativo.',
        'expired'  => 'Sua ativação venceu. Fale com seu revendedor para renovar.',
    );
    $set[$API_NAME][] = array(
        'success'      => $success,
        'MSG'          => $msg != '' ? $msg : $status_msgs[$status],
        'status'       => $status,
        'is_active'    => $status == 'active' ? '1' : '0',
        'expires_at'   => ($device && (int)$device['expires_at'] > 0) ? date('Y-m-d', $device['expires_at']) : '',
        'days_left'    => ($device && $status == 'active' && (int)$device['expires_at'] > 0) ? (string)max(0, (int)ceil(($device['expires_at'] - time()) / 86400)) : '',
        'reseller'     => $device ? devices_owner_name($mysqli, $device) : '',
    );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    die();
}

// App details
if($get_helper['helper_name']=="app_details"){
    
    $jsonObj= array();
	$data_arr= array();
    
    $sql="SELECT * FROM tbl_settings WHERE id='1'";
    $result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    while($data = mysqli_fetch_assoc($result)){
        
        // App Details
        $data_arr['app_email'] = $data['app_email'];
        $data_arr['app_author'] = $data['app_author'];
        $data_arr['app_contact'] = $data['app_contact'];
        $data_arr['app_website'] = $data['app_website'];
        $data_arr['app_description'] = $data['app_description'];
        $data_arr['app_developed_by'] = $data['app_developed_by'];
        
        // Envato
        $data_arr['envato_api_key'] = $data['envato_api_key'];
        
        // is_
        $data_arr['is_rtl'] = $data['is_rtl'];
        $data_arr['is_maintenance'] = $data['is_maintenance'];
        $data_arr['is_screenshot'] = $data['is_screenshot'];
        $data_arr['is_apk'] = $data['is_apk'];
        $data_arr['is_vpn'] = $data['is_vpn'];
        $data_arr['is_xui_dns'] = $data['is_xui_dns'];
        $data_arr['is_xui_radio'] = $data['is_xui_radio'];
        $data_arr['is_stream_dns'] = $data['is_stream_dns'];
        $data_arr['is_stream_radio'] = $data['is_stream_radio'];
        $data_arr['is_local_storage'] = $data['is_local_storage'];
        
        // is_select
        $data_arr['is_select_xui'] = $data['is_select_xui'];
        $data_arr['is_select_stream'] = $data['is_select_stream'];
        $data_arr['is_select_playlist'] = $data['is_select_playlist'];
        $data_arr['is_select_device_id'] = $data['is_select_device_id'];
        $data_arr['is_select_single'] = $data['is_select_single'];
        
        // Ads Network
        $data_arr['ad_network'] = $data['ad_network'];
        
        $data_arr['publisher_id'] = ($data['ad_network'] == 'admob') ? $data_arr['publisher_id'] : "";
        
        // BannerAds
        $data_arr['banner_ad'] = $data['banner_ad'];
        if ($data['ad_network'] == 'admob') {
            $data_arr['banner_ad_id'] = $data['banner_ad_id'];
        } else {
            $data_arr['banner_ad_id'] = '';
        }
       
        // InterstitalAds
        $data_arr['interstital_ad'] = $data['interstital_ad'];
        $data_arr['interstital_ad_click'] = $data['interstital_ad_click'];
        if ($data['ad_network'] == 'admob') {
            $data_arr['interstital_ad_id'] = $data['interstital_ad_id'];
        } else {
            $data_arr['interstital_ad_id'] = '';
        }
        
        // AppUpdate
        $data_arr['app_update_status'] = $data['app_update_status'];
        $data_arr['app_new_version'] = $data['app_new_version'];
        $data_arr['app_update_desc'] = $data['app_update_desc'];
        $data_arr['app_redirect_url'] = $data['app_redirect_url'];
        
        // Custom Ads
        $data_arr['custom_ads'] = $data['custom_ads'];
        $data_arr['custom_ads_clicks'] = $data['custom_ads_clicks'];
        
        // Anúncios desativados permanentemente (banner, tela cheia e Custom Ads)
        $data_arr['banner_ad'] = 'false';
        $data_arr['interstital_ad'] = 'false';
        $data_arr['custom_ads'] = 'false';

        // App Themes
        $data_arr['is_theme'] = $data['is_theme'];
        $data_arr['is_epg'] = $data['is_epg'];
        
        // Dowload
        $data_arr['is_download'] = $data['is_dowload'];
       
        // TMDb Key
        $data_arr['tmdb_key'] = $data['account_delete_intruction'];
        
        array_push($jsonObj,$data_arr);
    }
    $row['details'] = $jsonObj;

    // Tema BestPro (App UI = 8): cores e imagens configuradas em Settings → Personalizar Tema
    require_once("includes/theme_helper.php");
    $row['theme_config'] = theme_api_payload($mysqli, $file_path, $settings_details['is_theme']);

    // Marca do revendedor: aparelho vinculado a um revendedor com "Minha Marca" ativa recebe o tema e a marca dele
    $brand = theme_for_device($mysqli,
        devices_clean(isset($get_helper['device_id']) ? $get_helper['device_id'] : '', 191),
        devices_clean(isset($get_helper['application_id']) ? $get_helper['application_id'] : '', 191),
        $file_path);
    if ($brand) {
        foreach ($row['details'] as $i => $detail) { $row['details'][$i]['is_theme'] = $brand['is_theme']; }
        $row['theme_config'] = $brand['theme_config'];
    }
    
    mysqli_free_result($result);
	$jsonObj = array();
	$data_arr = array();
	
    $sql="SELECT * FROM tbl_advertisement WHERE id='1'";
    $result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    while($data = mysqli_fetch_assoc($result)){
        
        $data_arr['ad_status'] = $data['ad_status'];
        
        // Ads Network
        $data_arr['ad_network'] = $data['ad_network'];
        
        // Publisher ID
        $data_arr['publisher_id'] = ($data['ad_network'] == 'admob') ? $data_arr['publisher_id'] : "";
        
        $data_arr['banner_movie'] = $data['banner_movie'];
        $data_arr['banner_series'] = $data['banner_series'];
        $data_arr['banner_epg'] = $data['banner_epg'];
        
        $data_arr['interstital_ad'] = $data['interstitial_post_list'];
        
        $data_arr['reward_ad_on_movie'] = $data['reward_ad_on_movie'];
        $data_arr['reward_ad_on_episodes'] = $data['reward_ad_on_episodes'];
        $data_arr['reward_ad_on_live'] = $data['reward_ad_on_live'];
        $data_arr['reward_ad_on_single'] = $data['reward_ad_on_single'];
        $data_arr['reward_ad_on_local'] = $data['reward_ad_on_local'];
        
        // BannerAds
        if ($data['ad_network'] == 'admob') {
            $data_arr['banner_ad_id'] = $data['admob_banner_unit_id'];
        } else {
            $data_arr['banner_ad_id'] = '';
        }
       
        // InterstitalAds
        $data_arr['interstital_ad_click'] = $data['interstital_ad_click'];
        if ($data['ad_network'] == 'admob') {
            $data_arr['interstital_ad_id'] = $data['admob_interstitial_unit_id'];
        } else {
            $data_arr['interstital_ad_id'] = '';
        }
        
        // RewardAds
        $data_arr['reward_minutes'] = $data['reward_minutes'];
        if ($data['ad_network'] == 'admob') {
            $data_arr['reward_ad_id'] = $data['admob_reward_ad_unit_id'];
        } else {
            $data_arr['reward_ad_id'] = '';
        }

        // Anúncios desativados permanentemente: o app nunca exibe propaganda (cliente paga pela ativação)
        foreach (array('ad_status', 'banner_movie', 'banner_series', 'banner_epg', 'interstital_ad',
                       'reward_ad_on_movie', 'reward_ad_on_episodes', 'reward_ad_on_live', 'reward_ad_on_single', 'reward_ad_on_local') as $ad_key) {
            $data_arr[$ad_key] = 'false';
        }

        array_push($jsonObj,$data_arr);
    }
    $row['ads_details'] = $jsonObj;
    
    mysqli_free_result($result);
	$jsonObj = array();
	$data_arr = array();
	
	// Lista pública de servidores do app: só os DNS do admin (os DNS de revendedores são exclusivos deles)
	$col_reseller = mysqli_query($mysqli, "SHOW COLUMNS FROM tbl_xui_dns LIKE 'reseller_id'");
	$dns_public_where = ($col_reseller && mysqli_num_rows($col_reseller) > 0) ? " AND tbl_xui_dns.reseller_id = 0" : "";
	$sql="SELECT * FROM tbl_xui_dns WHERE tbl_xui_dns.status='1'".$dns_public_where." ORDER BY tbl_xui_dns.id DESC";
	$result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    if(mysqli_num_rows($result) > 0){
        while ($data2 = mysqli_fetch_assoc($result)){
            
            $data_arr['id'] = $data2['id'];
            $data_arr['dns_title'] = $data2['dns_title'];
            $data_arr['dns_base'] = $data2['dns_base'];
            
            array_push($jsonObj, $data_arr);
        }
    }
	$row['xui_dns'] = $jsonObj;
	
	mysqli_free_result($result);
	$jsonObj = array();
	$data_arr = array();
	
	// DNS unificado: a opção "1-Stream" do app usa a mesma lista de servidores do Xtream Codes
	$sql="SELECT * FROM tbl_xui_dns WHERE tbl_xui_dns.status='1'".$dns_public_where." ORDER BY tbl_xui_dns.id DESC";
    $result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    if(mysqli_num_rows($result) > 0){
        while ($data3 = mysqli_fetch_assoc($result)){
            
            $data_arr['id'] = $data3['id'];
            $data_arr['dns_title'] = $data3['dns_title'];
            $data_arr['dns_base'] = $data3['dns_base'];
            
            array_push($jsonObj, $data_arr);
        }
    }
	$row['stream_dns'] = $jsonObj;

    mysqli_free_result($result);
	$jsonObj = array();
	$data_arr = array();
	
	$sql="SELECT * FROM tbl_xui_dns_block WHERE tbl_xui_dns_block.status='1' ORDER BY tbl_xui_dns_block.id DESC";
    $result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    if(mysqli_num_rows($result) > 0){
        while ($data4 = mysqli_fetch_assoc($result)){
            $data_arr['dns_base'] = $data4['dns_base'];
            array_push($jsonObj, $data_arr);
        }
    }
	$row['xui_dns_block'] = $jsonObj;

    mysqli_free_result($result);
	$jsonObj = array();
	$data_arr = array();
	
	$sql="SELECT * FROM tbl_custom_ads WHERE tbl_custom_ads.status='1' AND tbl_custom_ads.ads_type ='popup' ORDER BY RAND() DESC LIMIT 1";
    $result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    if(mysqli_num_rows($result) > 0){
        while ($data5 = mysqli_fetch_assoc($result)){
            
            $data_arr['ads_type'] = $data5['ads_type'];
            $data_arr['ads_title'] = $data5['ads_title'];
            $data_arr['ads_image'] =  $file_path.'images/'.$data5['ads_image'];
            $data_arr['ads_redirect_type'] = $data5['ads_redirect_type'];
            $data_arr['ads_redirect_url'] = $data5['ads_redirect_url'];
            
        	array_push($jsonObj, $data_arr);
        }
    }
	$row['popup_ads'] = $jsonObj;
	
	mysqli_free_result($result);
	$jsonObj = array();
	$data_arr = array();
	
	$sql="SELECT * FROM tbl_notification WHERE tbl_notification.`id` ORDER BY tbl_notification.`id` DESC";
    $result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    if(mysqli_num_rows($result) > 0){
        while ($data6 = mysqli_fetch_assoc($result)){
            
            $data_arr['id'] = $data6['id'];
          	$data_arr['notification_title'] = $data6['notification_title'];
          	$data_arr['notification_msg'] = $data6['notification_msg']; 
          	$data_arr['notification_description'] = $data6['notification_description']; 
            $data_arr['notification_on'] = calculate_time_span($data6['notification_on'],true);
            array_push($jsonObj, $data_arr);
        }
    }
	$row['notification_data'] = $jsonObj;

    $set[$API_NAME] = $row;
	header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}
else if($get_helper['helper_name']=="post_report"){
    
    $jsonObj= array();

    $user_name=cleanInput($get_helper['user_name']);
	$user_pass=cleanInput($get_helper['user_pass']);
	$report_title=cleanInput($get_helper['report_title']);
	$report_msg=cleanInput($get_helper['report_msg']);
    
	$data = array(
        'user_name' => $user_name,
        'user_pass'  =>  $user_pass,
        'report_title'  =>  $report_title,
        'report_msg'  =>  $report_msg,
        'report_on'  =>  strtotime(date('d-m-Y h:i:s A'))
    );
    $qry = Insert('tbl_reports',$data);
    
	$set[$API_NAME][]=array('MSG'=> $app_lang['report_success'],'success'=> '1');
  	header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}
else if($get_helper['helper_name']=="get_interstitial") {

	$jsonObj= array();	

    $sql="SELECT * FROM tbl_custom_ads WHERE tbl_custom_ads.status='1' AND tbl_custom_ads.ads_type ='interstitial' ORDER BY RAND() DESC LIMIT 1";
	$result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
	if(mysqli_num_rows($result) > 0){
	    while($data = mysqli_fetch_assoc($result)){
          	$row['ads_image'] = $file_path.'images/'.$data['ads_image'];
          	$row['ads_redirect_type'] = $data['ads_redirect_type'];
          	$row['ads_redirect_url'] = $data['ads_redirect_url'];
          	
          	array_push($jsonObj,$row);
        }
	}
	$set[$API_NAME] = $jsonObj;
	header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}
else if($get_helper['helper_name']=="get_poster") {

	$jsonObj= array();	

    $sql="SELECT * FROM tbl_poster_gallery WHERE tbl_poster_gallery.status='1' AND tbl_poster_gallery.poster_type ='movie_ui' ORDER BY RAND() DESC";
	$result = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli));
    if(mysqli_num_rows($result) > 0){
        while($data = mysqli_fetch_assoc($result)){
            $row['poster_image'] = $file_path.'images/'.$data['poster_image'];
            array_push($jsonObj,$row);
        }
    }
	$set[$API_NAME] = $jsonObj;
	header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}
else if($get_helper['helper_name']=="get_device_user"){
    
    $jsonObj= array();
    
    require_once("includes/playlist_helper.php");
    playlists_migrate($mysqli);

    $device_id=isset($get_helper['device_id']) ? trim($get_helper['device_id']) : '';

    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_users WHERE status='1' AND device_id = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, 's', $device_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($data = mysqli_fetch_assoc($result)){
        $row['id'] = $data['id'];
        $row['user_type'] = $data['user_type'];
        $row['user_name'] = $data['user_name'];
        $row['user_password'] = $data['user_password'];
        // Failover: devolve o servidor DNS que aceita este usuário agora (queda ou migração de servidor)
        $row['dns_base'] = playlist_current_dns($mysqli, $data);
        $row['device_id'] = $data['device_id'];
        $row['playlist_name'] = isset($data['playlist_name']) ? $data['playlist_name'] : '';
        array_push($jsonObj,$row);
    }
    mysqli_stmt_close($stmt);
    $set[$API_NAME] = $jsonObj;
    header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    die();
}

else {
    $get_helper = get_api_data($_POST['data']);
}