<?php
/**
 * Painel instalado? Antes dependia só do arquivo includes/.lic, que o instalador grava
 * consultando o servidor do fornecedor. Agora, se o banco já tem as tabelas do painel,
 * o painel é considerado instalado e o marcador é recriado localmente — sem depender de ninguém.
 */
function panel_installed(){
    $lic = __DIR__.'/.lic';
    if (is_file($lic) && filesize($lic) > 0) return true;

    $host = getenv('DB_HOST'); $user = getenv('DB_USER'); $name = getenv('DB_NAME');
    if (!$host || !$user || !$name) return false;
    $pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($host, $user, $pass, $name);
    if ($db->connect_errno) return false;
    $res = $db->query("SHOW TABLES LIKE 'tbl_settings'");
    $ok = $res && $res->num_rows > 0;
    $db->close();

    if ($ok) @file_put_contents($lic, 'local-'.date('Y-m-d'), LOCK_EX);
    return $ok;
}
