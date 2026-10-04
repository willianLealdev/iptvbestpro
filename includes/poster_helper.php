<?php
/**
 * Pôsteres do MovieUI (fundo em tela cheia que vai trocando no app).
 * - Admin (reseller_id = 0): pôsteres do sistema; o admin escolhe quais aparecem no app ("Mostrar no app").
 * - Revendedor: pode subir os próprios pôsteres. Se tiver algum ligado, os clientes dele veem os dele;
 *   senão, veem os pôsteres do sistema.
 * - Clientes finais (assinatura) e aparelhos do admin: sempre os pôsteres do admin.
 */

require_once(__DIR__."/reseller_helper.php");
require_once(__DIR__."/media_helper.php"); // cópia das imagens no banco (não somem no deploy)

define('POSTER_MAX_BYTES', 8 * 1024 * 1024);
define('POSTER_MAX_PER_RESELLER', 20);

function posters_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_poster_gallery` LIKE 'reseller_id'");
    if ($col && mysqli_num_rows($col) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_poster_gallery` ADD `reseller_id` INT(11) NOT NULL DEFAULT 0, ADD KEY `idx_poster_reseller` (`reseller_id`)");
    }
}

function posters_list($mysqli, $scope, $only_active = false){
    posters_migrate($mysqli);
    $scope = (int)$scope;
    $rows = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_poster_gallery WHERE poster_type = 'movie_ui' AND reseller_id = $scope".($only_active ? " AND status = 1" : "")." ORDER BY id DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) {
        // arquivo sumiu da pasta (ex.: depois de um deploy)? volta da cópia do banco
        if ($r['poster_image'] != '' && !is_file(__DIR__.'/../images/'.basename($r['poster_image']))) media_restore($mysqli, 'images/'.basename($r['poster_image']));
        $rows[] = $r;
    }
    return $rows;
}

/**
 * Recebe um ou vários arquivos do campo $field (input multiple). Retorna array('added' => n, 'errors' => array).
 */
function posters_upload($mysqli, $scope, $field){
    posters_migrate($mysqli);
    $scope = (int)$scope;
    $added = 0;
    $errors = array();
    if (empty($_FILES[$field])) return array('added' => 0, 'errors' => array('Escolha pelo menos uma imagem.'));
    $f = $_FILES[$field];
    $names = is_array($f['name']) ? $f['name'] : array($f['name']);
    $count = count($names);
    $current = count(posters_list($mysqli, $scope));
    for ($i = 0; $i < $count; $i++) {
        $err = is_array($f['error']) ? $f['error'][$i] : $f['error'];
        $tmp = is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'];
        $size = is_array($f['size']) ? $f['size'][$i] : $f['size'];
        if ($err == UPLOAD_ERR_NO_FILE) continue;
        if ($scope > 0 && $current + $added >= POSTER_MAX_PER_RESELLER) { $errors[] = 'Limite de '.POSTER_MAX_PER_RESELLER.' pôsteres.'; break; }
        if ($err != UPLOAD_ERR_OK) { $errors[] = 'Falha no envio de uma imagem.'; continue; }
        if ($size > POSTER_MAX_BYTES) { $errors[] = 'Imagem maior que 8 MB.'; continue; }
        $info = @getimagesize($tmp);
        $types = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp');
        if (!$info || !isset($types[$info[2]])) { $errors[] = 'Formato inválido (use JPG, PNG ou WEBP).'; continue; }
        $name = 'poster_'.($scope > 0 ? 'r'.$scope.'_' : '').bin2hex(random_bytes(6)).'.'.$types[$info[2]];
        if (!move_uploaded_file($tmp, __DIR__.'/../images/'.$name)) { $errors[] = 'Não foi possível salvar uma imagem.'; continue; }
        if (!media_backup($mysqli, 'images/'.$name)) error_log('[posters] cópia no banco falhou: '.$name);
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_poster_gallery (poster_type, poster_image, status, reseller_id) VALUES ('movie_ui', ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'si', $name, $scope);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $added++;
    }
    return array('added' => $added, 'errors' => array_values(array_unique($errors)));
}

function posters_toggle($mysqli, $scope, $id){
    posters_migrate($mysqli);
    mysqli_query($mysqli, "UPDATE tbl_poster_gallery SET status = IF(status = 1, 0, 1) WHERE id = ".(int)$id." AND reseller_id = ".(int)$scope);
    return mysqli_affected_rows($mysqli) > 0;
}

function posters_delete($mysqli, $scope, $id){
    posters_migrate($mysqli);
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT poster_image FROM tbl_poster_gallery WHERE id = ".(int)$id." AND reseller_id = ".(int)$scope));
    if (!$r) return false;
    mysqli_query($mysqli, "DELETE FROM tbl_poster_gallery WHERE id = ".(int)$id." AND reseller_id = ".(int)$scope);
    $file = basename($r['poster_image']);
    if ($file != '' && is_file(__DIR__.'/../images/'.$file)) @unlink(__DIR__.'/../images/'.$file);
    if ($file != '') media_forget($mysqli, 'images/'.$file);
    return true;
}

// Revendedor que controla os pôsteres deste aparelho (0 = admin)
function posters_scope_for_device($mysqli, $device_id, $application_id){
    if ($device_id == '') return 0;
    require_once(__DIR__.'/theme_helper.php');
    if (!theme_reseller_allowed($mysqli)) return 0;
    $stmt = mysqli_prepare($mysqli, "SELECT reseller_id FROM tbl_devices WHERE device_id = ? AND application_id = ? AND owner = 'reseller' LIMIT 1");
    if (!$stmt) return 0;
    mysqli_stmt_bind_param($stmt, 'ss', $device_id, $application_id);
    mysqli_stmt_execute($stmt);
    $dev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$dev || (int)$dev['reseller_id'] <= 0) return 0;
    $row = theme_reseller_row($mysqli, $dev['reseller_id']);
    return (int)$row['enabled'] == 1 ? (int)$dev['reseller_id'] : 0;
}

// Pôsteres que o app deste aparelho deve mostrar
function posters_for_device($mysqli, $device_id, $application_id){
    posters_migrate($mysqli);
    $scope = posters_scope_for_device($mysqli, $device_id, $application_id);
    if ($scope > 0) {
        $own = posters_list($mysqli, $scope, true);
        if ($own) return $own;
    }
    return posters_list($mysqli, 0, true);
}

// Pôster para a pré-visualização do MovieUI no editor de temas
function posters_preview_image($mysqli, $scope){
    $own = $scope > 0 ? posters_list($mysqli, $scope, true) : array();
    if ($own) return $own[0]['poster_image'];
    $sys = posters_list($mysqli, 0, true);
    return $sys ? $sys[0]['poster_image'] : '';
}

/**
 * Gerenciador visual de pôsteres (admin e revendedor).
 */
function posters_manager_render($rows, $img_base, $hidden_html, $editable = true){ ?>
<style>
    .ps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
    .ps-card { border: 2px solid #eef0f4; border-radius: 12px; overflow: hidden; background: #fff; }
    .ps-card.on { border-color: #16a34a; }
    .ps-card .ps-img { position: relative; aspect-ratio: 16 / 9; background: #f3f4f6 center / cover no-repeat; }
    .ps-card .ps-badge { position: absolute; top: 8px; left: 8px; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
    .ps-card .ps-body { padding: 10px 12px; display: flex; align-items: center; gap: 8px; }
</style>
<?php if ($rows) { ?>
    <div class="ps-grid">
        <?php foreach ($rows as $p) { $on = (int)$p['status'] == 1; ?>
            <div class="ps-card <?php echo $on ? 'on' : ''; ?>">
                <div class="ps-img" style="background-image:url('<?php echo htmlspecialchars($img_base.$p['poster_image']); ?>');">
                    <span class="ps-badge" style="background:<?php echo $on ? '#16a34a' : '#6b7280'; ?>;color:#fff;"><?php echo $on ? 'Aparece no app' : 'Oculto'; ?></span>
                </div>
                <?php if ($editable) { ?>
                    <div class="ps-body">
                        <form method="post" style="flex:1;display:flex;">
                            <?php echo $hidden_html; ?>
                            <input type="hidden" name="poster_id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" name="toggle_poster" class="btn btn-sm <?php echo $on ? 'btn-outline-secondary' : 'btn-success'; ?> w-100">
                                <i class="<?php echo $on ? 'ri-eye-off-line' : 'ri-eye-line'; ?>"></i> <?php echo $on ? 'Ocultar do app' : 'Mostrar no app'; ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Excluir este pôster?');">
                            <?php echo $hidden_html; ?>
                            <input type="hidden" name="poster_id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" name="delete_poster" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
<?php } else { ?>
    <div class="text-center text-muted py-4">Nenhum pôster enviado.</div>
<?php } ?>
<?php }

function posters_upload_form($hidden_html){ ?>
<form method="post" enctype="multipart/form-data" class="p-3 rounded mb-4" style="background:#f7f9fe;">
    <?php echo $hidden_html; ?>
    <label class="form-label fw-semibold">Enviar pôsteres</label>
    <input type="file" name="posters[]" class="form-control mb-2" accept="image/jpeg,image/png,image/webp" multiple required>
    <small class="text-muted d-block mb-3">
        <b>Tamanho ideal: 1920 × 1080 px</b> (16:9, Full HD), JPG ou WEBP de até 8 MB. Pode escolher várias imagens de uma vez.
        O pôster ocupa a <b>tela inteira</b> atrás do menu: deixe o título/rosto na parte de cima e o rodapé mais escuro, onde ficam os botões.
    </small>
    <button type="submit" name="upload_posters" class="btn btn-primary"><i class="ri-upload-2-line"></i> Enviar</button>
</form>
<?php }
