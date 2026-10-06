<?php $page_title="DNS das Revendas";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");
    require_once("includes/playlist_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    dns_health_migrate($mysqli);

    function rd_flash($ok, $msg){
        $_SESSION['rd_msg'] = $msg;
        $_SESSION['msg'] = 'rd_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    $back_url = "manage_dns_resellers.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    if(isset($_POST['delete_dns'])){
        $id = (int)$_POST['dns_id'];
        mysqli_query($mysqli, "DELETE FROM tbl_xui_dns WHERE id = $id AND reseller_id > 0");
        if (mysqli_affected_rows($mysqli) > 0) @mysqli_query($mysqli, "DELETE FROM tbl_dns_share WHERE dns_id = $id");
        rd_flash(mysqli_affected_rows($mysqli) > 0, mysqli_affected_rows($mysqli) > 0 ? 'Servidor removido.' : 'Servidor não encontrado.');
        header("Location:".$back_url);
        exit;
    }
    if(isset($_POST['toggle_dns'])){
        $id = (int)$_POST['dns_id'];
        mysqli_query($mysqli, "UPDATE tbl_xui_dns SET status = IF(status = 1, 0, 1) WHERE id = $id AND reseller_id > 0");
        rd_flash(true, 'Status do servidor alterado.');
        header("Location:".$back_url);
        exit;
    }
    if(isset($_POST['check_now'])){
        $all = dns_health_check_all($mysqli, 4);
        rd_flash(true, 'Servidores verificados.');
        header("Location:".$back_url);
        exit;
    }

    if(isset($_SESSION['rd_msg'])){
        $client_lang['rd_custom'] = $_SESSION['rd_msg'];
        unset($_SESSION['rd_msg']);
    }

    // Filtros
    $reseller_filter = isset($_GET['reseller']) ? (int)$_GET['reseller'] : 0;
    $health_filter = (isset($_GET['health']) && in_array($_GET['health'], array('online','offline'))) ? $_GET['health'] : '';
    $where = array("d.reseller_id > 0");
    if($reseller_filter > 0){ $where[] = "d.reseller_id = ".$reseller_filter; }
    if($health_filter != ''){ $where[] = "d.health = '".$health_filter."'"; }
    $where_sql = 'WHERE '.implode(' AND ', $where);

    $result = mysqli_query($mysqli, "SELECT d.*, r.name AS reseller_name, r.email AS reseller_email
                                     FROM tbl_xui_dns d LEFT JOIN tbl_resellers r ON r.id = d.reseller_id
                                     $where_sql ORDER BY (d.health = 'offline') DESC, r.name ASC, d.id DESC");

    $totals = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total, SUM(health = 'online') AS online, SUM(health = 'offline') AS offline FROM tbl_xui_dns WHERE reseller_id > 0"));

    $resellers = array();
    $res = mysqli_query($mysqli, "SELECT DISTINCT r.id, r.name FROM tbl_resellers r JOIN tbl_xui_dns d ON d.reseller_id = r.id ORDER BY r.name");
    while($r = mysqli_fetch_assoc($res)){ $resellers[] = $r; }

    function rd_ago($ts){
        if(!$ts){ return 'nunca verificado'; }
        $s = time() - (int)$ts;
        if($s < 60){ return 'agora'; }
        if($s < 3600){ return 'há '.floor($s / 60).' min'; }
        if($s < 86400){ return 'há '.floor($s / 3600).' h'; }
        return 'há '.floor($s / 86400).' dia(s)';
    }
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <div class="row g-3 mb-4">
            <div class="col-sm-4"><div class="card p-3" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);"><div class="text-muted small">Servidores das revendas</div><div class="fs-3 fw-bold"><?php echo (int)$totals['total']; ?></div></div></div>
            <div class="col-sm-4"><div class="card p-3" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);"><div class="text-muted small">Online</div><div class="fs-3 fw-bold" style="color:#16a34a;"><?php echo (int)$totals['online']; ?></div></div></div>
            <div class="col-sm-4"><div class="card p-3" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);"><div class="text-muted small">Offline</div><div class="fs-3 fw-bold" style="color:#dc2626;"><?php echo (int)$totals['offline']; ?></div></div></div>
        </div>

        <div class="card" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <form method="get" class="d-flex flex-wrap gap-2">
                        <select name="reseller" class="form-select" style="width:auto;" onchange="this.form.submit()">
                            <option value="">Todos os revendedores</option>
                            <?php foreach($resellers as $r){ ?>
                                <option value="<?php echo $r['id']; ?>" <?php if($reseller_filter == $r['id']){ echo 'selected'; } ?>><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php } ?>
                        </select>
                        <select name="health" class="form-select" style="width:auto;" onchange="this.form.submit()">
                            <option value="">Online e offline</option>
                            <option value="online" <?php if($health_filter == 'online'){ echo 'selected'; } ?>>Somente online</option>
                            <option value="offline" <?php if($health_filter == 'offline'){ echo 'selected'; } ?>>Somente offline</option>
                        </select>
                    </form>
                    <form method="post">
                        <button type="submit" name="check_now" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i> Verificar agora</button>
                    </form>
                </div>

                <p class="text-muted small">Servidores de revendedores offline por mais de <?php echo DNS_OFFLINE_DELETE_DAYS; ?> dias seguidos são excluídos automaticamente. Os seus servidores (Servidores DNS → DNS) nunca são excluídos automaticamente.</p>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Status</th><th>Servidor</th><th>Revendedor</th><th>Última verificação</th><th>Exclusão automática</th><th class="text-end">Ações</th></tr>
                        </thead>
                        <tbody>
                        <?php if(mysqli_num_rows($result) > 0){ while($d = mysqli_fetch_assoc($result)){
                            $h = $d['health'];
                            $dot = $h == 'online' ? '#16a34a' : ($h == 'offline' ? '#dc2626' : '#9ca3af');
                            $label = $h == 'online' ? 'Online' : ($h == 'offline' ? 'Offline' : 'Não verificado');
                            $left = dns_days_until_delete($d); ?>
                            <tr style="<?php echo (int)$d['status'] != 1 ? 'opacity:.6;' : ''; ?>">
                                <td class="text-nowrap">
                                    <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:<?php echo $dot; ?>;box-shadow:0 0 0 3px <?php echo $dot; ?>33;margin-right:6px;"></span>
                                    <b style="color:<?php echo $dot; ?>;"><?php echo $label; ?></b>
                                    <?php if($h == 'online' && (int)$d['latency_ms'] > 0){ ?><small class="text-muted"> · <?php echo (int)$d['latency_ms']; ?> ms</small><?php } ?>
                                    <?php if((int)$d['status'] != 1){ ?><br><small class="text-muted">Desligado</small><?php } ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($d['dns_title']); ?></div>
                                    <small class="text-muted text-break"><?php echo htmlspecialchars(html_entity_decode($d['dns_base'], ENT_QUOTES, 'UTF-8')); ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($d['reseller_name'] != '' ? $d['reseller_name'] : 'Revendedor removido'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars((string)$d['reseller_email']); ?></small>
                                </td>
                                <td class="text-nowrap"><?php echo rd_ago($d['health_at']); ?></td>
                                <td class="text-nowrap">
                                    <?php if($left !== null){ ?><span class="text-danger fw-semibold">em <?php echo $left; ?> dia(s)</span><br><small class="text-muted">offline desde <?php echo date('d/m/Y', $d['offline_since']); ?></small>
                                    <?php } else { ?><span class="text-muted">—</span><?php } ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="dns_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" name="toggle_dns" class="btn btn-sm btn-outline-primary"><?php echo (int)$d['status'] == 1 ? 'Desligar' : 'Ligar'; ?></button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remover este servidor do revendedor?');">
                                        <input type="hidden" name="dns_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" name="delete_dns" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">Nenhum servidor cadastrado pelos revendedores.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
