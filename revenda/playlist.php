<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/playlist_helper.php");
require_once(__DIR__."/../includes/playlist_form.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
playlists_migrate($mysqli);

$pk = isset($_GET['device']) ? (int)$_GET['device'] : 0;
$device = $pk ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $pk AND owner = 'reseller' AND reseller_id = $rid")) : null;
if (!$device) {
    rv_flash('error', 'Dispositivo não encontrado na sua lista.');
    rv_redirect('devices.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['playlist_submit'])) {
        $r = playlist_handle_submit($mysqli, $device['device_id'], $_POST, $reseller['email']);
        rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } else if (isset($_POST['playlist_delete'])) {
        $ok = playlist_delete($mysqli, $_POST['playlist_id'], $device['device_id']);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Playlist removida.' : 'Playlist não encontrada.');
    }
    rv_redirect('playlist.php?device='.$pk);
}

$playlists = playlist_list($mysqli, $device['device_id']);
$dns_list = dns_list_active($mysqli, $rid);
$status = devices_activation_status($device);

rv_header($mysqli, $reseller, 'Playlist', 'devices');
playlist_form_styles(); ?>

<div class="rv-card p-4 mb-4 d-flex flex-wrap gap-4 align-items-center">
    <div>
        <div class="rv-muted small">Dispositivo</div>
        <div class="fw-bold fs-5"><?php echo rv_e($device['device_id']); ?></div>
        <?php if ($device['device_model'] != '') { ?><div class="rv-muted small"><?php echo rv_e($device['device_model']); ?></div><?php } ?>
    </div>
    <div>
        <div class="rv-muted small">Ativação</div>
        <div class="fw-semibold">
            <?php if ($status == 'active') { ?><span class="text-success">Ativo<?php echo (int)$device['expires_at'] > 0 ? ' até '.date('d/m/Y', $device['expires_at']) : ''; ?></span>
            <?php } else if ($status == 'expired') { ?><span class="text-danger">Vencido</span>
            <?php } else { ?><span class="text-warning">Aguardando ativação</span><?php } ?>
        </div>
    </div>
    <a href="devices.php" class="btn btn-outline-secondary ms-auto"><i class="ri-arrow-left-line"></i> Voltar</a>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <?php playlist_form_render(rv_csrf_field(), $dns_list, 'dns.php'); ?>
    </div>
    <div class="col-xl-5">
        <div class="rv-card p-4 h-100">
            <h2 class="rv-title fs-5 mb-3">Playlists deste aparelho</h2>
            <?php playlist_list_render($playlists, rv_csrf_field()); ?>
        </div>
    </div>
</div>

<?php ob_start(); playlist_form_script(); rv_footer(ob_get_clean()); ?>
