<?php
if(!isset($_SESSION['admin_name']) AND !isset($_SESSION['admin_type'])){
	session_destroy();
	header( "Location:gerencia.php");
	exit;
}
?>