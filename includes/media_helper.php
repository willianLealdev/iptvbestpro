<?php
/**
 * Cópia de segurança das imagens no banco de dados.
 *
 * Os arquivos enviados (pôsteres, logos, fundos dos temas, banners...) ficam na pasta images/, mas a pasta
 * pode ser perdida quando o container é recriado num deploy. Por isso cada imagem também é guardada no
 * banco (tbl_media) e volta sozinha para a pasta quando estiver faltando:
 *  - na hora do envio (pôsteres e temas gravam a cópia na mesma hora);
 *  - quando a lista de pôsteres é aberta (restaura o que faltar);
 *  - ao iniciar o container e a cada 5 minutos (cron/media_sync.php): guarda arquivos novos e restaura os que sumiram.
 */

define('MEDIA_MAX_BYTES', 15 * 1024 * 1024);
define('MEDIA_EXTS', 'png,jpg,jpeg,webp,gif,svg,ico');

function media_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_media` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `path` VARCHAR(191) NOT NULL,
        `mime` VARCHAR(60) NOT NULL DEFAULT '',
        `size` INT(11) NOT NULL DEFAULT 0,
        `hash` CHAR(40) NOT NULL DEFAULT '',
        `data` LONGBLOB NOT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_media_path` (`path`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function media_root(){ return realpath(__DIR__.'/..'); }

// Caminho relativo seguro (ex.: images/poster_ab12.jpg). Só pastas images/ e uploads/.
function media_clean_path($rel){
    $rel = str_replace('\\', '/', (string)$rel);
    $rel = ltrim(preg_replace('#/+#', '/', $rel), '/');
    if (strpos($rel, '..') !== false) return '';
    if (!preg_match('#^(images|uploads)/[A-Za-z0-9_\-./]+$#', $rel)) return '';
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, explode(',', MEDIA_EXTS))) return '';
    return $rel;
}

function media_mime($path){
    $map = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
                 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon');
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

// Guarda (ou atualiza) a cópia de um arquivo no banco. $rel = 'images/arquivo.jpg'
function media_backup($mysqli, $rel){
    media_migrate($mysqli);
    $rel = media_clean_path($rel);
    if ($rel === '') return false;
    $file = media_root().'/'.$rel;
    if (!is_file($file)) return false;
    $size = filesize($file);
    if ($size <= 0 || $size > MEDIA_MAX_BYTES) return false;
    $hash = sha1_file($file);
    $stmt = mysqli_prepare($mysqli, "SELECT hash FROM tbl_media WHERE path = ?");
    mysqli_stmt_bind_param($stmt, 's', $rel);
    mysqli_stmt_execute($stmt);
    $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($cur && $cur['hash'] === $hash) return true; // já está salvo
    $data = file_get_contents($file);
    if ($data === false) return false;
    $mime = media_mime($rel);
    $now = time();
    $null = null;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_media (path, mime, size, hash, data, created_at) VALUES (?,?,?,?,?,?)
                                     ON DUPLICATE KEY UPDATE mime = VALUES(mime), size = VALUES(size), hash = VALUES(hash), data = VALUES(data)");
    mysqli_stmt_bind_param($stmt, 'ssisbi', $rel, $mime, $size, $hash, $null, $now);
    mysqli_stmt_send_long_data($stmt, 4, $data);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Recoloca na pasta um arquivo que sumiu (se existir cópia no banco)
function media_restore($mysqli, $rel){
    media_migrate($mysqli);
    $rel = media_clean_path($rel);
    if ($rel === '') return false;
    $file = media_root().'/'.$rel;
    if (is_file($file)) return true;
    $stmt = mysqli_prepare($mysqli, "SELECT data FROM tbl_media WHERE path = ?");
    mysqli_stmt_bind_param($stmt, 's', $rel);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return false;
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return @file_put_contents($file, $row['data']) !== false;
}

function media_forget($mysqli, $rel){
    media_migrate($mysqli);
    $rel = media_clean_path($rel);
    if ($rel === '') return;
    $stmt = mysqli_prepare($mysqli, "DELETE FROM tbl_media WHERE path = ?");
    mysqli_stmt_bind_param($stmt, 's', $rel);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Sincroniza pasta ↔ banco: guarda arquivos novos/alterados e restaura os que faltam.
 * Retorna array('saved' => n, 'restored' => n).
 */
function media_sync($mysqli){
    media_migrate($mysqli);
    $root = media_root();
    $saved = 0; $restored = 0;
    // 1) restaura o que está no banco e não está na pasta
    $res = mysqli_query($mysqli, "SELECT path FROM tbl_media");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        if (!is_file($root.'/'.$r['path']) && media_restore($mysqli, $r['path'])) $restored++;
    }
    // 2) guarda o que está na pasta e ainda não está no banco (ou mudou)
    foreach (array('images', 'uploads') as $dir) {
        if (!is_dir($root.'/'.$dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = $dir.'/'.str_replace('\\', '/', substr($f->getPathname(), strlen($root.'/'.$dir) + 1));
            if (media_clean_path($rel) === '') continue;
            $stmt = mysqli_prepare($mysqli, "SELECT size FROM tbl_media WHERE path = ?");
            mysqli_stmt_bind_param($stmt, 's', $rel);
            mysqli_stmt_execute($stmt);
            $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($cur && (int)$cur['size'] === (int)$f->getSize()) continue;
            if (media_backup($mysqli, $rel)) $saved++;
        }
    }
    return array('saved' => $saved, 'restored' => $restored);
}
