<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/playlist_helper.php");
require_once(__DIR__."/../includes/playlist_form.php");

$customer = cl_require_login($mysqli);
$cid = (int)$customer['id'];
playlists_migrate($mysqli);

// O aparelho precisa ser de uma assinatura deste cliente
$sid = isset($_GET['sub']) ? (int)$_GET['sub'] : 0;
$sub = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_subscriptions WHERE id = $sid AND customer_id = $cid AND device_pk > 0"));
$device = $sub ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = ".(int)$sub['device_pk']." AND customer_id = $cid")) : null;
if (!$device) {
    cl_flash('error', 'Aparelho não encontrado nas suas assinaturas.');
    cl_redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!cl_csrf_ok()) {
        cl_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['playlist_submit'])) {
        $r = playlist_handle_submit($mysqli, $device['device_id'], $_POST, 'cliente: '.$customer['email']);
        cl_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } else if (isset($_POST['playlist_delete'])) {
        $ok = playlist_delete($mysqli, $_POST['playlist_id'], $device['device_id']);
        cl_flash($ok ? 'success' : 'error', $ok ? 'Playlist removida.' : 'Playlist não encontrada.');
    }
    cl_redirect('playlist.php?sub='.$sid);
}

$playlists = playlist_list($mysqli, $device['device_id']);
$st = subscription_state($sub);

cl_header($customer, 'Playlists', 'dashboard');
playlist_form_styles(); ?>

<div class="cl-card p-4 mb-4 d-flex flex-wrap gap-4 align-items-center">
    <div>
        <div class="cl-muted small">Aparelho</div>
        <div class="fw-bold fs-5"><?php echo cl_e($device['device_model'] != '' ? $device['device_model'] : $device['device_id']); ?></div>
        <div class="cl-muted small"><?php echo cl_e($device['device_id']); ?></div>
    </div>
    <div>
        <div class="cl-muted small">Assinatura</div>
        <div class="fw-semibold"><?php echo cl_e($sub['plan_name']); ?> · vence em <?php echo date('d/m/Y', $sub['expires_at']); ?> <?php echo subscription_state_badge($st); ?></div>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary ms-auto"><i class="ri-arrow-left-line"></i> Voltar</a>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <?php playlist_form_render(cl_csrf_field(), array(), '', true); ?>
    </div>
    <div class="col-xl-5">
        <div class="cl-card p-4 h-100">
            <h2 class="cl-title fs-5 mb-3">Playlists deste aparelho</h2>
            <?php playlist_list_render($playlists, cl_csrf_field()); ?>
        </div>
    </div>
</div>

<?php ob_start(); playlist_form_script(); cl_footer(ob_get_clean()); ?>
