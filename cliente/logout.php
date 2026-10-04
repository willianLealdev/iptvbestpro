<?php
require_once(__DIR__."/_init.php");
unset($_SESSION['cl_id'], $_SESSION['cl_csrf']);
session_regenerate_id(true);
cl_redirect('index.php');
