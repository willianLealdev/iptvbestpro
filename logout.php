<?php
session_start();
require_once(__DIR__."/includes/db_helper.php");
trust_revoke_current($mysqli, 'adm');
unset($_SESSION["admin_name"]);
unset($_SESSION["admin_type"]);
session_destroy();
header( "Location: gerencia.php?login=bye");
exit;
?>