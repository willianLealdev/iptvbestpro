<?php
require_once(__DIR__."/_init.php");
unset($_SESSION['rv_id'], $_SESSION['rv_csrf']);
session_regenerate_id(true);
rv_redirect('index.php');
