<?php $page_title="Manage DNS";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/playlist_helper.php");
    require_once("includes/dns_alert_helper.php");
    dns_alert_migrate($mysqli);

    // Alertas por e-mail quando um DNS cair
    if(isset($_POST['save_alerts']) && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] != 0){
        $emails = array();
        foreach(preg_split('/[\s,;]+/', isset($_POST['alert_emails']) ? $_POST['alert_emails'] : '') as $e){
            $e = trim($e);
            if($e != '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[] = $e;
        }
        panel_config_set($mysqli, 'dns_alert_enabled', !empty($_POST['alert_enabled']) ? '1' : '0');
        panel_config_set($mysqli, 'dns_alert_copy_resellers', !empty($_POST['alert_copy']) ? '1' : '0');
        panel_config_set($mysqli, 'dns_alert_emails', implode(', ', array_slice(array_unique($emails), 0, 5)));
        panel_config_set($mysqli, 'dns_alert_minutes', in_array((int)$_POST['alert_minutes'], array(5, 10, 15, 30)) ? (int)$_POST['alert_minutes'] : 10);
        $_SESSION['dns_alert_saved'] = 1;
        header("Location:manage_dns.php");
        exit;
    }
    $alert_cfg = dns_alert_cfg($mysqli);
    $alert_admins = dns_alert_admin_recipients($mysqli, array('emails' => ''));

    // Status online/offline dos servidores (o monitor atualiza a cada 5 minutos)
    dns_health_migrate($mysqli);
    $never_checked = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_xui_dns WHERE health = '' AND reseller_id = 0"));
    if(isset($_POST['check_now']) || (int)$never_checked['num'] > 0){
        dns_health_check_all($mysqli, 4);
        if(isset($_POST['check_now'])){
            header("Location:manage_dns.php");
            exit;
        }
    }

    function dns_checked_ago($ts){
        if(!$ts){ return 'nunca verificado'; }
        $s = time() - (int)$ts;
        if($s < 60){ return 'agora'; }
        if($s < 3600){ return 'há '.floor($s / 60).' min'; }
        return 'há '.floor($s / 3600).' h';
    }

    $tableName="tbl_xui_dns";
    $targetpage = "manage_dns.php"; 
    $limit = 15; 

    // Somente os DNS do admin; os dos revendedores ficam em "DNS das Revendas"
    $query = "SELECT COUNT(*) as num FROM $tableName WHERE reseller_id = 0";
    $total_pages = mysqli_fetch_array(mysqli_query($mysqli,$query));
    $total_pages = $total_pages['num'];
    
    $stages = 3;
    $page=0;
    if(isset($_GET['page'])){
        $page = mysqli_real_escape_string($mysqli,$_GET['page']);
    }
    if($page){
        $start = ($page - 1) * $limit; 
    }else{
        $start = 0; 
    } 
    $sql_query="SELECT * FROM tbl_xui_dns WHERE reseller_id = 0 ORDER BY tbl_xui_dns.`id` DESC LIMIT $start, $limit";
    $result=mysqli_query($mysqli,$sql_query) or die(mysqli_error($mysqli));
?>

<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo (isset($page_title)) ? $page_title : "" ?></li>
            </ol>
        </nav>
            
        <div class="card h-100">
            <div class="card-header d-md-inline-flex align-items-center justify-content-between py-3 px-4">
                <a href="<?php echo (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : "dashboard.php" ?>" class="d-inline-flex align-items-center text-decoration-none fw-semibold nsofts-link">
                    <i class="ri-arrow-left-line text-danger fw-bold"></i>
                    <span class="ps-2 lh-1"><?=$page_title ?></span>
                </a>
                <span class="ps-2 lh-1">Total DNS (<?=$total_pages ?>)</span>
                <div class="d-flex mt-2 mt-md-0">
                    <form method="post" class="me-2">
                        <button type="submit" name="check_now" class="btn btn-outline-secondary d-inline-flex align-items-center" data-bs-toggle="tooltip" title="Testar todos os servidores agora">
                            <i class="ri-refresh-line"></i>
                            <span class="ps-1 text-nowrap d-none d-sm-block">Verificar agora</span>
                        </button>
                    </form>
                    <a href="create_dns.php?add=yes" class="btn btn-primary d-inline-flex align-items-center justify-content-center">
                        <i class="ri-add-line"></i>
                        <span class="ps-1 text-nowrap d-none d-sm-block">Create DNS</span>
                    </a>
                </div>
            </div>
            
            <div class="card-body p-4">
                <?php if(!empty($_SESSION['dns_alert_saved'])){ unset($_SESSION['dns_alert_saved']); ?>
                    <div class="alert alert-success py-2">Alertas de DNS salvos.</div>
                <?php } ?>
                <form method="post" class="dns-alert mb-4">
                    <style>
                        .dns-alert { background: #f7f9fe; border: 1px solid #e8edf7; border-radius: 14px; padding: 20px 22px; }
                        .dns-alert__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
                        .dns-alert__title { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; }
                        .dns-alert__title i { font-size: 18px; color: #3b6cf6; }
                        .dns-alert__desc { color: #6b7a99; font-size: 13px; margin: 4px 0 0; }
                        .dns-alert .form-label { font-size: 13px; font-weight: 600; color: #3b4560; margin-bottom: 6px; }
                        .dns-alert .form-control, .dns-alert .form-select, .dns-alert__box { height: 44px; border-radius: 10px; }
                        .dns-alert__box { display: flex; align-items: center; padding: 0 12px; background: #fff; border: 1px solid #dee2e6; }
                        .dns-alert__box .form-check { margin: 0; padding-left: 0; display: flex; align-items: center; gap: 10px; }
                        .dns-alert__box .form-check-input { float: none; margin-left: 0 !important; }
                        .dns-alert__box .form-check-input { margin: 0; }
                        .dns-alert__box .form-check-label { font-size: 13.5px; }
                        .dns-alert__hint { font-size: 12.5px; color: #8a94ab; margin-top: 6px; }
                        .dns-alert__foot { display: flex; align-items: center; justify-content: flex-end; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e8edf7; }
                    </style>
                    <div class="dns-alert__head">
                        <div>
                            <div class="dns-alert__title"><i class="ri-mail-send-line"></i> Alerta por e-mail quando um servidor cair</div>
                            <p class="dns-alert__desc">Quando um servidor fica offline, o dono recebe um e-mail; quando volta, recebe outro. Os seus servidores avisam você; os dos revendedores avisam o revendedor. Clientes não recebem.</p>
                        </div>
                        <div class="form-check form-switch m-0 text-nowrap">
                            <input class="form-check-input" type="checkbox" name="alert_enabled" id="alert_enabled" value="1" <?php if($alert_cfg['enabled']){ echo 'checked'; } ?>>
                            <label class="form-check-label fw-semibold" for="alert_enabled">Ligado</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="alert_emails">E-mails que recebem (além do Super Admin)</label>
                            <input type="text" name="alert_emails" id="alert_emails" class="form-control" value="<?php echo htmlspecialchars($alert_cfg['emails']); ?>" placeholder="ex.: suporte@seudominio.com, outro@seudominio.com">
                            <div class="dns-alert__hint">Super Admin: <span translate="no"><?php echo $alert_admins ? htmlspecialchars(implode(', ', array_map(function($a){ return $a['email']; }, $alert_admins))) : '—'; ?></span></div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label class="form-label" for="alert_minutes">Avisar depois de</label>
                            <select name="alert_minutes" id="alert_minutes" class="form-select">
                                <?php foreach(array(5, 10, 15, 30) as $m){ ?><option value="<?php echo $m; ?>" <?php if($alert_cfg['minutes'] == $m){ echo 'selected'; } ?>><?php echo $m; ?> min</option><?php } ?>
                            </select>
                            <div class="dns-alert__hint">offline seguidos</div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <label class="form-label">Revendedores</label>
                            <div class="dns-alert__box">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="alert_copy" id="alert_copy" value="1" <?php if($alert_cfg['copy']){ echo 'checked'; } ?>>
                                    <label class="form-check-label" for="alert_copy">Receber cópia dos alertas deles</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dns-alert__foot">
                        <button type="submit" name="save_alerts" class="btn btn-primary px-4"><i class="ri-save-3-line"></i> Salvar alertas</button>
                    </div>
                </form>
                <?php if(mysqli_num_rows($result) > 0){ ?>
                    <div class="row g-4 mb-3">
                        <?php $i=0; while($row=mysqli_fetch_array($result)) { ?>
                            <div class="col-lg-3 col-sm-6">
                                <div class="nsofts-card-light p-3">
                                    <?php
                                        $h = isset($row['health']) ? $row['health'] : '';
                                        $dot = $h == 'online' ? '#16a34a' : ($h == 'offline' ? '#dc2626' : '#9ca3af');
                                        $label = $h == 'online' ? 'Online' : ($h == 'offline' ? 'Offline' : 'Não verificado');
                                    ?>
                                    <h5 class="mb-2 d-flex align-items-center gap-2">
                                        <span style="width:12px;height:12px;border-radius:50%;background:<?php echo $dot; ?>;flex:0 0 12px;box-shadow:0 0 0 3px <?php echo $dot; ?>33;" title="<?php echo $label; ?>"></span>
                                        <?php echo $row['dns_title'];?>
                                    </h5>
                                    <p class="mb-1"><?php echo $row['dns_base'];?></p>
                                    <p class="small mb-3" style="color:<?php echo $dot; ?>;">
                                        <b><?php echo $label; ?></b>
                                        <?php if($h == 'online' && (int)$row['latency_ms'] > 0){ ?> · <?php echo (int)$row['latency_ms']; ?> ms<?php } ?>
                                        <span class="text-muted"> · <?php echo dns_checked_ago(isset($row['health_at']) ? $row['health_at'] : 0); ?></span>
                                    </p>
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex">
                                           <a href="create_dns.php?dns_id=<?php echo $row['id'];?>" class="btn btn-outline-primary rounded-pill me-2 btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                <i class="ri-pencil-line"></i>
                                            </a>
                                            <a href="javascript:void(0)" class="btn btn-outline-danger rounded-pill me-2 btn-icon btn_delete" data-id="<?php echo $row['id'];?>" data-table="<?=$tableName ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        </div>
                                        <div class="nsofts-switch d-flex align-items-center enable_disable" data-bs-toggle="tooltip" data-bs-placement="top" title="Enable / Disable">
                                            <input type="checkbox" id="enable_disable_check_<?= $i ?>" data-id="<?= $row['id'] ?>" data-table="<?=$tableName ?>" data-column="status" class="cbx hidden btn_enable_disable" <?php if ($row['status'] == 1) { echo 'checked'; } ?>>
                                            <label for="enable_disable_check_<?= $i ?>" class="nsofts-switch__label"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $i++; } ?>
                    </div>
                    <?php include("pagination.php"); ?>
                <?php } else { ?>
                    <ul class="p-5">
                        <h1 class="text-center">No data found</h1>
                    </ul>
                <?php } ?>
                </nav>
            </div>
        </div>
        
    </div>
</main>
<!-- End: main -->
<?php include("includes/footer.php");?> 