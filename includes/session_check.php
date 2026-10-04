<?php
if(empty($_SESSION['id']) || !isset($_SESSION['admin_name']) || !isset($_SESSION['admin_type'])){
	session_destroy();
	header( "Location:gerencia.php");
	exit;
}
?>