<?php
require_once(__DIR__."/_init.php");
trust_revoke_current($mysqli, 'cl');
unset($_SESSION['cl_id'], $_SESSION['cl_csrf']);
session_regenerate_id(true);
cl_redirect('index.php');
