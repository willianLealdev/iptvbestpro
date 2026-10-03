<?php 
include("includes/db_helper.php");
include("includes/lb_helper.php"); 
include("language/api_language.php"); 
include("smtp_email.php");

error_reporting(0);

$file_path = getBaseUrl();

$mysqli->set_charset('utf8mb4');

date_default_timezone_set("Asia/Colombo");

// For Api header
$API_NAME = 'NEMOSOFTS_WEB';

if($_POST['helper_name']=="web_details"){

    $jsonObj= array();	
	$query="SELECT * FROM tbl_web_settings WHERE id='1'";
	$sql = mysqli_query($mysqli,$query);
	
	while($data = mysqli_fetch_assoc($sql)){
	    
        // App Details
        $row['site_name'] = $data['site_name'];
        $row['site_description'] = $data['site_description'];
        $row['site_keywords'] = $data['site_keywords'];
        $row['copyright_text'] = $data['copyright_text'];
        $row['web_logo_1'] = $file_path.'images/'.$data['web_logo_1'];
        $row['web_logo_2'] = $file_path.'images/'.$data['web_logo_2'];
        $row['web_favicon'] = $file_path.'images/'.$data['web_favicon'];
        $row['header_code'] = $data['header_code'];
        $row['footer_code'] = $data['footer_code'];
        $row['privacy_page_title'] = $data['privacy_page_title'];
        $row['privacy_content'] = $data['privacy_content'];
        $row['terms_of_use_page_title'] = $data['terms_of_use_page_title'];
        $row['terms_of_use_content'] = $data['terms_of_use_content'];
        
        array_push($jsonObj,$row);
    }
	$set[$API_NAME] = $jsonObj;
	header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}

else if($_POST['helper_name']=="get_sidebar"){
    
    $jsonObj= array();
    
    $sql="SELECT * FROM tbl_sidebar WHERE tbl_sidebar.status='1' ORDER BY tbl_sidebar.id DESC";
    $result = mysqli_query($mysqli, $sql);
    while($data = mysqli_fetch_assoc($result)){
        
        $row['id'] = $data['id'];
        $row['sidebar_type'] = $data['sidebar_type'];
        $row['sidebar_title'] = $data['sidebar_title'];
        $row['sidebar_icon'] = $data['sidebar_icon'];
        $row['sidebar_editor'] = $data['sidebar_editor'];
        $row['sidebar_html'] = $data['sidebar_html'];
        $row['sidebar_image'] = $file_path.'images/'.$data['sidebar_image'];
        $row['sidebar_image_link'] = $data['sidebar_image_link'];
        
        array_push($jsonObj,$row);
    }
    $set[$API_NAME] = $jsonObj;
    header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    die();
}

else {
    $set[]=array('MSG'=> 'Directory access is forbidden.','success'=> '0');
  	header( 'Content-Type: application/json; charset=utf-8' );
    echo $val= str_replace('\\/', '/', json_encode($set,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	die();
}