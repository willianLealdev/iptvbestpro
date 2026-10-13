<?php 
// Apps de TV (LG / Samsung) chamam a API a partir do próprio app instalado (sem origem web)
header('Access-Control-Allow-Origin: *');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') { header('Access-Control-Allow-Methods: POST'); header('Access-Control-Allow-Headers: Content-Type'); exit; }
// Segurança: os apps só usam POST com o campo "data" (JSON em base64). Qualquer outra coisa é recusada
// antes de abrir o banco (outros métodos, corpo binário/serializado, JSON inválido).
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: POST'); header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"method_not_allowed"}'; exit;
}
$__raw = isset($_POST['data']) && is_string($_POST['data']) ? $_POST['data'] : '';
// mesma leitura do get_api_data() (os apps já publicados mandam assim); só valida que é um JSON de verdade
$__dec = ($__raw !== '' && strlen($__raw) <= 65536) ? base64_decode($__raw) : false;
$__json = $__dec !== false ? json_decode(urldecode($__dec), true) : null;
if (!is_array($__json) || empty($__json['helper_name'])) {
    http_response_code(400); header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"invalid_request"}'; exit;
}
unset($__raw, $__dec, $__json);
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

// Verificação de compra no fornecedor removida: a API do app funciona sem depender do servidor da nemosofts

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

// Resetar licença: o cliente quer usar outro código (trocou de revenda).
// O aparelho sai do painel de quem era dono (revenda, admin ou assinatura), perde a ativação e as listas.
// device_reset: { device_id, application_id } -> status: unlinked
if($get_helper['helper_name']=="device_reset"){
    require_once("includes/reseller_helper.php");
    resellers_migrate($mysqli);
    $did = !empty($get_helper['device_id']) ? devices_clean($get_helper['device_id'], 191) : '';
    $aid = devices_clean(isset($get_helper['application_id']) ? $get_helper['application_id'] : '', 191);
    $ok = '0';
    if ($did !== '') {
        $device = devices_find($mysqli, $did, $aid);
        // apaga o aparelho de vez (some da lista da revenda e do admin) junto com as listas dele
        devices_wipe_playlists($mysqli, $did);
        if ($device) devices_purge($mysqli, array((int)$device['id']));
        $ok = '1';
    }
    $set[$API_NAME][] = array('success' => $ok, 'status' => 'unlinked', 'MSG' => $ok == '1' ? 'Licença resetada. Digite o novo código.' : 'Dispositivo não identificado.');
    header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    die();
}

// Ativação por código de revendedor
// device_link:   { device_id, code, platform?, device_model?, ... } -> vincula o aparelho ao dono do código
// device_status: { device_id } -> status: unlinked | pending | active | expired
if($get_helper['helper_name']=="device_link" || $get_helper['helper_name']=="device_status" || $get_helper['helper_name']=="apple_iap"){
    require_once("includes/reseller_helper.php");
    resellers_migrate($mysqli);

    if($get_helper['helper_name']=="apple_iap"){
        // App iPhone/iPad/Apple TV: compra (ou restauração) pela Apple ativa o aparelho como cliente direto
        require_once("includes/apple_iap_helper.php");
        $link = apple_claim($mysqli, $get_helper);
        $device = $link['device'];
        $success = $link['ok'] ? '1' : '0';
        $msg = $link['msg'];
    } else if($get_helper['helper_name']=="device_link"){
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
    // IPTV Best Pro: suporte e renovação no app
    //  - suporte: WhatsApp da revenda dona do aparelho; sem revenda, o do admin
    //  - renovação/compra pelo QR Code: só para cliente direto (sem revenda) — cliente de revenda renova com a revenda
    require_once("includes/support_helper.php");
    require_once("includes/brand_helper.php");
    support_migrate($mysqli);
    $sp = support_contact_for_device($mysqli, $device);
    $sp_base = rtrim(brand_mail_base_url(), '/');
    $last = count($set[$API_NAME]) - 1;
    $set[$API_NAME][$last]['owner'] = $device ? (string)$device['owner'] : '';
    $set[$API_NAME][$last]['support_name'] = $sp['name'];
    $set[$API_NAME][$last]['support_whatsapp'] = $sp['whatsapp'];
    $set[$API_NAME][$last]['support_note'] = $sp['note'];
    $set[$API_NAME][$last]['renew_url'] = ($device && $device['owner'] != 'reseller') ? $sp_base.'/cliente/' : '';
    // Servidor parceiro: o app mostra o QR da página de envio da lista (/lista) até a lista chegar
    require_once("includes/server_helper.php");
    $srv = server_of_device($mysqli, $device);
    $set[$API_NAME][$last]['owner_kind'] = $srv ? 'server' : ($device ? (string)$device['owner'] : '');
    $set[$API_NAME][$last]['upload_url'] = $srv ? server_upload_url($device['device_id']) : '';
    // imagem do QR code dessa página, para os apps que não geram QR code sozinhos (Roku)
    $set[$API_NAME][$last]['upload_qr'] = $srv ? rtrim(brand_mail_base_url(), '/').'/qr?d='.rawurlencode($device['device_id']) : '';
    if ($device && $device['package'] == 'trial' && $status == 'expired') $set[$API_NAME][$last]['MSG'] = 'Seu teste grátis terminou. Para continuar usando, ative uma licença.';
    if ($device && $device['package'] == 'trial') $set[$API_NAME][$last]['is_trial'] = '1';
    if ($srv && $status == 'expired') $set[$API_NAME][$last]['MSG'] = 'Sua ativação venceu. Digite o código do seu servidor de novo para renovar.';
    $set[$API_NAME][$last]['portal_url'] = $sp_base.'/cliente/';
    // Teste grátis: o app mostra "Teste grátis de N dias" no QR code de compra (só aparelho que ainda não usou)
    require_once("includes/customer_helper.php");
    customers_migrate($mysqli);
    $set[$API_NAME][$last]['trial_days'] = ($status == 'unlinked' && trial_enabled($mysqli) && !trial_used_by_device($mysqli, $device)) ? (string)trial_days($mysqli) : '0';
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
        // IPTV Best Pro: versão publicada na página de download — o app avisa e se atualiza sozinho
        require_once(__DIR__.'/includes/app_release.php');
        require_once(__DIR__.'/includes/brand_helper.php');
        $rel = app_release();
        if (is_file(__DIR__.'/'.$rel['file'])) {
            $base = function_exists('brand_mail_base_url') ? rtrim(brand_mail_base_url(), '/') : '';
            $data_arr['app_update_status'] = 'true';
            $data_arr['app_new_version'] = (string)(int)$rel['version_code'];
            $data_arr['app_update_desc'] = 'Nova versão '.$rel['version'].' disponível.';
            if ($base != '') $data_arr['app_redirect_url'] = $base.'/apk';
        }
        
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
    
	// IPTV Best Pro: guarda o aparelho e a revenda dona dele (ela vê no painel); a senha da lista não é guardada
	require_once("includes/support_helper.php");
	support_migrate($mysqli);
	$rp_dev = isset($get_helper['device_id']) ? substr(trim((string)$get_helper['device_id']), 0, 191) : '';
	$rp_app = isset($get_helper['application_id']) ? substr(trim((string)$get_helper['application_id']), 0, 191) : '';
	$rp_reseller = 0;
	if ($rp_dev !== '') {
	    $st = mysqli_prepare($mysqli, "SELECT reseller_id FROM tbl_devices WHERE device_id = ? AND application_id = ? AND owner = 'reseller' LIMIT 1");
	    mysqli_stmt_bind_param($st, 'ss', $rp_dev, $rp_app);
	    mysqli_stmt_execute($st);
	    $rr = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
	    mysqli_stmt_close($st);
	    if ($rr) $rp_reseller = (int)$rr['reseller_id'];
	}
	$data = array(
        'user_name' => $user_name,
        'user_pass'  =>  '',
        'report_title'  =>  $report_title,
        'report_msg'  =>  $report_msg,
        'report_on'  =>  strtotime(date('d-m-Y h:i:s A')),
        'device_id' => cleanInput($rp_dev),
        'reseller_id' => $rp_reseller,
        'device_model' => cleanInput(isset($get_helper['device_model']) ? substr((string)$get_helper['device_model'], 0, 100) : ''),
        'is_read' => 0
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

    // Pôsteres do dono do aparelho: revendedor com pôsteres próprios ligados, senão os do sistema (admin)
    require_once("includes/poster_helper.php");
    $posters = posters_for_device($mysqli,
        devices_clean(isset($get_helper['device_id']) ? $get_helper['device_id'] : '', 191),
        devices_clean(isset($get_helper['application_id']) ? $get_helper['application_id'] : '', 191));
    shuffle($posters);
    foreach($posters as $data){
        $row['poster_image'] = $file_path.'images/'.$data['poster_image'];
        array_push($jsonObj,$row);
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
    // botão "Atualizar" do app: confere o servidor de cada lista do zero (sem o cache de 10 min)
    $fresh = !empty($get_helper['fresh']);

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
        $row['dns_base'] = playlist_current_dns($mysqli, $data, $fresh, $dns_status);
        // online | offline (nenhum servidor aceita este usuário: o app passa para outra lista do aparelho) | '' (lista por URL)
        $row['dns_status'] = $dns_status;
        $row['device_id'] = $data['device_id'];
        $row['playlist_name'] = isset($data['playlist_name']) ? $data['playlist_name'] : '';
        array_push($jsonObj,$row);
    }
    mysqli_stmt_close($stmt);
    // Usuário que não conecta em nenhum DNS: a lista dele entrega o usuário que está conectando (mesmo número de lista)
    $jsonObj = playlist_apply_fallback($mysqli, $device_id, $jsonObj);
    $set[$API_NAME] = $jsonObj;
    header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    die();
}

else {
    $get_helper = get_api_data($_POST['data']);
}