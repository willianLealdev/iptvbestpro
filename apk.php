<?php
// Link curto para o app Downloader / navegador da TV: iptvbestpro.com/apk entrega o APK atual
require_once(__DIR__."/includes/app_release.php");
$rel = app_release();
if (!is_file(__DIR__.'/'.$rel['file'])) { header('Location: /download'); exit; }
header('Cache-Control: no-store');
header('Location: /'.$rel['file'].'?v='.(int)$rel['version_code'], true, 302);
exit;
