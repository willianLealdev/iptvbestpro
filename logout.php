<?php
session_start();
unset($_SESSION["admin_name"]);
unset($_SESSION["admin_type"]);
session_destroy();
header( "Location: gerencia.php?login=bye");
exit;
?>