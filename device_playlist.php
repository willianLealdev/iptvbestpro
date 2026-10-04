<?php $page_title="Playlist";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");
    require_once("includes/playlist_helper.php");
    require_once("includes/playlist_form.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    playlists_migrate($mysqli);

    $pk = isset($_GET['device']) ? (int)$_GET['device'] : 0;
    $device = $pk ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $pk")) : null;
    if(!$device){
        header("Location:manage_devices.php");
        exit;
    }

    function pl_flash($ok, $msg){
        $_SESSION['pl_msg'] = $msg;
        $_SESSION['msg'] = 'pl_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }

    if(isset($_POST['playlist_submit'])){
        $r = playlist_handle_submit($mysqli, $device['device_id'], $_POST, 'admin: '.(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : ''));
        pl_flash($r['ok'], $r['msg']);
        header("Location:device_playlist.php?device=".$pk);
        exit;
    }
    if(isset($_POST['playlist_delete'])){
        $ok = playlist_delete($mysqli, $_POST['playlist_id'], $device['device_id']);
        pl_flash($ok, $ok ? 'Playlist removida.' : 'Playlist não encontrada.');
        header("Location:device_playlist.php?device=".$pk);
        exit;
    }

    if(isset($_SESSION['pl_msg'])){
        $client_lang['pl_custom'] = $_SESSION['pl_msg'];
        unset($_SESSION['pl_msg']);
    }

    $playlists = playlist_list($mysqli, $device['device_id']);
    $dns_list = dns_list_active($mysqli);
    $status = devices_activation_status($device);
    $owner_label = devices_owner_label($mysqli, $device);
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_devices.php">Devices</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page">Playlist</li>
            </ol>
        </nav>

        <?php playlist_form_styles(); ?>

        <div class="card mb-4" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
            <div class="card-body p-4 d-flex flex-wrap gap-4 align-items-center">
                <div>
                    <div class="text-muted small">Dispositivo</div>
                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($device['device_id']); ?></div>
                    <?php if($device['device_model'] != ''){ ?><div class="text-muted small"><?php echo htmlspecialchars($device['device_model']); ?></div><?php } ?>
                </div>
                <div>
                    <div class="text-muted small">Revendedor</div>
                    <div class="fw-semibold"><?php echo $owner_label != '' ? htmlspecialchars($owner_label) : '—'; ?></div>
                </div>
                <div>
                    <div class="text-muted small">Ativação</div>
                    <div class="fw-semibold">
                        <?php if($status == 'active'){ ?><span class="text-success">Ativo<?php echo (int)$device['expires_at'] > 0 ? ' até '.date('d/m/Y', $device['expires_at']) : ''; ?></span>
                        <?php } else if($status == 'expired'){ ?><span class="text-danger">Vencido</span>
                        <?php } else { ?><span class="text-warning">Aguardando ativação</span><?php } ?>
                    </div>
                </div>
                <a href="manage_devices.php" class="btn btn-outline-secondary ms-auto"><i class="ri-arrow-left-line"></i> Voltar</a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-7">
                <?php playlist_form_render('', $dns_list); ?>
            </div>
            <div class="col-xl-5">
                <div class="card h-100" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Playlists deste aparelho</h5>
                        <?php playlist_list_render($playlists, ''); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php playlist_form_script(); ?>
<?php include("includes/footer.php");?>
