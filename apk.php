<?php
// Link curto para o app Downloader / navegador da TV: iptvbestpro.com/apk entrega o APK atual
require_once(__DIR__."/includes/app_release.php");
$rel = app_release();
if (!is_file(__DIR__.'/'.$rel['file'])) { header('Location: /download'); exit; }
header('Location: /'.$rel['file'], true, 302);
exit;
