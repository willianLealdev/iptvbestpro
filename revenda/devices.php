<?php
require_once(__DIR__."/_init.php");
$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
$packages = devices_packages();
$now = time();

$back = 'devices.php'.(!empty($_GET) ? '?'.http_build_query(array_diff_key($_GET, array('activate' => 1))) : '');

// Editar o nome do cliente (lápis na lista)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rename_submit'])) {
    $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if ($customer_name == '') {
        rv_flash('error', 'Informe o nome do cliente.');
    } else {
        $pk = (int)$_POST['device_pk'];
        $own = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_devices WHERE id = $pk AND owner = 'reseller' AND reseller_id = $rid"));
        $ok = $own && devices_set_customer($mysqli, $pk, $customer_name, $rid);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Nome do cliente atualizado.' : 'Dispositivo não encontrado na sua lista.');
    }
    rv_redirect($back);
}

// servidor parceiro: a ativação é automática pelo código (aqui não ativa nem renova)
if (rv_is_server() && isset($_POST['activate_submit'])) unset($_POST['activate_submit']);
if (rv_is_server()) {
    require_once(__DIR__."/../includes/playlist_helper.php");
    playlists_migrate($mysqli);
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activate_submit'])) {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else {
        $pk = (int)$_POST['device_pk'];
        $own = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_devices WHERE id = $pk AND owner = 'reseller' AND reseller_id = $rid"));
        $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
        if (!$own) {
            rv_flash('error', 'Dispositivo não encontrado na sua lista.');
        } else if ($customer_name == '') {
            rv_flash('error', 'Informe o nome do cliente.');
        } else {
            $r = devices_activate($mysqli, $pk, isset($_POST['package']) ? $_POST['package'] : '', $rid, $reseller['email'], 0);
            if ($r['ok']) devices_set_customer($mysqli, $pk, $customer_name, $rid);
            rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
        }
    }
    rv_redirect($back);
}

// Filtros
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$status = (isset($_GET['status']) && in_array($_GET['status'], array('pending','active','expired'))) ? $_GET['status'] : '';
$where = array("d.owner = 'reseller'", "d.reseller_id = $rid");
if ($keyword != '') {
    $kw = mysqli_real_escape_string($mysqli, $keyword);
    $where[] = "(d.customer_name LIKE '%$kw%' OR d.device_id LIKE '%$kw%' OR d.device_model LIKE '%$kw%')";
}
if ($status == 'pending') $where[] = "d.package = ''";
if ($status == 'active')  $where[] = "d.package <> '' AND (d.expires_at = 0 OR d.expires_at >= $now)";
if ($status == 'expired') $where[] = "d.package <> '' AND d.expires_at > 0 AND d.expires_at < $now";
$where_sql = 'WHERE '.implode(' AND ', $where);

$total_rows = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_devices d $where_sql"));
$total_rows = (int)$total_rows['num'];
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$last_page = max(1, (int)ceil($total_rows / $limit));
if ($page > $last_page) $page = $last_page;
$start = ($page - 1) * $limit;

$result = mysqli_query($mysqli, "SELECT d.*, COALESCE(NULLIF(d.app_name,''), NULLIF(d.application_id,''), '—') AS app_label
                                 FROM tbl_devices d $where_sql
                                 ORDER BY (d.package = '') DESC, d.linked_at DESC, d.id DESC LIMIT $start, $limit");

function rv_dev_url($changes = array()){
    $params = array_merge($_GET, $changes);
    unset($params['activate']);
    foreach ($params as $k => $v) { if ($v === '' || $v === null) unset($params[$k]); }
    return 'devices.php'.(count($params) ? '?'.http_build_query($params) : '');
}

$auto_open = isset($_GET['activate']) ? (int)$_GET['activate'] : 0;
$auto_mac = '';
if ($auto_open) {
    $a = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT device_id FROM tbl_devices WHERE id = $auto_open AND owner = 'reseller' AND reseller_id = $rid"));
    if ($a) { $auto_mac = $a['device_id']; } else { $auto_open = 0; }
}

rv_header($mysqli, $reseller, 'Dispositivos', 'devices'); ?>

<div class="rv-card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <h1 class="rv-title">Dispositivos</h1>
        <form method="get" class="d-flex flex-wrap gap-2">
            <input type="text" name="keyword" class="form-control" style="width:240px;max-width:100%;" placeholder="Buscar cliente, MAC ou modelo" value="<?php echo rv_e($keyword); ?>">
            <select name="status" class="form-select" style="width:auto;" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="pending" <?php if($status=='pending'){echo 'selected';} ?>>Aguardando ativação</option>
                <option value="active" <?php if($status=='active'){echo 'selected';} ?>>Ativos</option>
                <option value="expired" <?php if($status=='expired'){echo 'selected';} ?>>Vencidos</option>
            </select>
            <button class="btn btn-outline-secondary" type="submit"><i class="ri-search-line"></i></button>
        </form>
        <button type="button" class="btn btn-outline-secondary js-devices-refresh" title="Atualiza sozinho a cada 10 segundos"><i class="ri-refresh-line"></i> Atualizar</button>
    </div>

    <div class="table-responsive">
        <table class="table rv-table mb-0">
            <thead>
                <tr><th>#</th><th>Cliente</th><th>Mac</th><th>App</th><th>Créditos usados</th><th>Pacote</th><th>Ativação</th><th>Vencimento</th><th><?php echo rv_is_server() ? 'Lista' : 'Ação'; ?></th></tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) > 0) { $i = 0; while ($d = mysqli_fetch_assoc($result)) { $i++;
                $st = devices_activation_status($d);
                $pkg = devices_package_info($d['package']); ?>
                <tr>
                    <td><?php echo $start + $i; ?></td>
                    <td class="fw-semibold text-start text-nowrap">
                        <span translate="no"><?php echo $d['customer_name'] != '' ? rv_e($d['customer_name']) : '<span class="rv-muted fw-normal">—</span>'; ?></span>
                        <a href="javascript:void(0)" class="rv-pencil btn_rename ms-1" data-pk="<?php echo (int)$d['id']; ?>" data-name="<?php echo rv_e($d['customer_name']); ?>" data-mac="<?php echo rv_e($d['device_id']); ?>" title="Editar nome do cliente"><i class="ri-pencil-line"></i></a>
                    </td>
                    <td>
                        <?php echo rv_e($d['device_id']); ?>
                        <span class="rv-copy ms-1" data-copy="<?php echo rv_e($d['device_id']); ?>"><i class="ri-file-copy-line"></i></span>
                        <?php if ($d['device_model'] != '' || $d['country_code'] != '') { ?><br><small class="rv-muted"><?php echo rv_e($d['device_model']); ?></small><?php echo devices_flag($d['country_code'], $d['country_name']); ?><?php } ?>
                    </td>
                    <td><?php echo rv_e($d['app_label']); ?></td>
                    <td><?php echo (int)$d['used_credits']; ?></td>
                    <td>
                        <?php if ($st == 'inactive') { ?><span class="dv-badge pk-trial">Aguardando ativação</span>
                        <?php } else if ($st == 'expired') { ?><span class="dv-badge pk-expired">Vencido</span>
                        <?php } else { ?><span class="dv-badge <?php echo $pkg ? $pkg['class'] : 'pk-year'; ?>"><?php echo $pkg ? $pkg['label'] : rv_e($d['package']); ?></span><?php } ?>
                    </td>
                    <td><?php echo (int)$d['activated_at'] > 0 ? date('d/m/Y', $d['activated_at']) : '<span class="rv-muted">—</span>'; ?></td>
                    <td><?php
                        if ($st == 'inactive') echo '<span class="rv-muted">—</span>';
                        else if ((int)$d['expires_at'] == 0) echo 'Sem vencimento';
                        else echo '<span class="'.($st == 'expired' ? 'text-danger fw-semibold' : '').'">'.date('d/m/Y', $d['expires_at']).'</span>';
                    ?></td>
                    <?php if (rv_is_server()) {
                        $nl = (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_users WHERE device_id = '".mysqli_real_escape_string($mysqli, $d['device_id'])."'"))['n']; ?>
                    <td><?php echo $nl > 0 ? '<span class="dv-badge pk-year"><i class="ri-play-list-line"></i> Lista enviada</span>' : '<span class="dv-badge pk-trial">Sem lista</span>'; ?></td>
                    <?php } else { ?>
                    <td>
                        <button type="button" class="btn btn-sm <?php echo $st == 'active' ? 'btn-outline-primary' : 'rv-btn-green'; ?> btn_activate" data-pk="<?php echo (int)$d['id']; ?>" data-mac="<?php echo rv_e($d['device_id']); ?>" data-customer="<?php echo rv_e($d['customer_name']); ?>">
                            <i class="ri-flashlight-line"></i> <?php echo $st == 'inactive' ? 'Ativar' : 'Renovar'; ?>
                        </button>
                        <a href="playlist.php?device=<?php echo (int)$d['id']; ?>" class="btn btn-sm btn-outline-secondary ms-1"><i class="ri-play-list-line"></i> Playlist</a>
                    </td>
                    <?php } ?>
                </tr>
            <?php } } else { ?>
                <tr><td colspan="9"><div class="rv-empty"><i class="ri-inbox-line"></i>Nenhum dispositivo. Quando o cliente digitar seu código <b><?php echo rv_e($reseller['code']); ?></b> no app, ele aparece aqui.</div></td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <b>Total: <?php echo $total_rows; ?></b>
        <?php if ($last_page > 1) { ?>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php if($page<=1){echo 'disabled';} ?>"><a class="page-link" href="<?php echo rv_e(rv_dev_url(array('page' => $page - 1))); ?>">‹</a></li>
                <?php for ($p = max(1, $page - 2); $p <= min($last_page, $page + 2); $p++) { ?>
                    <li class="page-item <?php if($p==$page){echo 'active';} ?>"><a class="page-link" href="<?php echo rv_e(rv_dev_url(array('page' => $p))); ?>"><?php echo $p; ?></a></li>
                <?php } ?>
                <li class="page-item <?php if($page>=$last_page){echo 'disabled';} ?>"><a class="page-link" href="<?php echo rv_e(rv_dev_url(array('page' => $page + 1))); ?>">›</a></li>
            </ul>
        <?php } ?>
    </div>
</div>

<!-- Modal: ativar / renovar -->
<div class="modal fade" id="activateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?php echo rv_csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title">Ativar — <span id="activate_mac"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="device_pk" id="activate_pk">
                <div class="mb-3">
                    <label class="form-label">Cliente</label>
                    <input type="text" name="customer_name" id="activate_customer" class="form-control form-control-lg" maxlength="120" required placeholder="Nome do cliente">
                </div>
                <label class="form-label">Período</label>
                <select name="package" id="activate_package" class="form-select form-select-lg">
                    <?php foreach ($packages as $k => $p) { ?>
                        <option value="<?php echo $k; ?>" data-credits="<?php echo $p['credits']; ?>" <?php if ($p['credits'] > (int)$reseller['credits']) { echo 'disabled'; } ?>>
                            <?php echo $p['label']; ?> — <?php echo $p['credits']; ?> crédito<?php echo $p['credits'] > 1 ? 's' : ''; ?>
                        </option>
                    <?php } ?>
                </select>
                <div class="mt-3 p-3 rounded" style="background:#f7f9fe;">
                    Seu saldo: <b><?php echo (int)$reseller['credits']; ?></b> créditos ·
                    Após ativar: <b id="activate_after"></b>
                </div>
                <p class="rv-muted small mt-2 mb-0">Se o aparelho ainda estiver ativo, o novo período é somado ao vencimento atual.</p>
                <?php if ((int)$reseller['credits'] < CREDITS_PER_YEAR) { ?>
                    <div class="alert alert-warning mt-3 mb-0">Você não tem créditos suficientes. <a href="credits.php">Comprar créditos</a></div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="activate_submit" class="btn rv-btn-green" <?php if ((int)$reseller['credits'] < CREDITS_PER_YEAR) { echo 'disabled'; } ?>>Confirmar ativação</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?php echo rv_csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title">Editar nome do cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="device_pk" id="rename_pk">
                <div class="small rv-muted mb-2">MAC: <b id="rename_mac" translate="no"></b></div>
                <label class="form-label">Cliente</label>
                <input type="text" name="customer_name" id="rename_name" class="form-control form-control-lg" maxlength="120" required placeholder="Nome do cliente">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="rename_submit" class="btn rv-btn-green">Salvar</button>
            </div>
        </form>
    </div>
</div>
<style>
    .rv-pencil { color: #3b6cf6; opacity: .55; font-size: 15px; text-decoration: none; }
    tr:hover .rv-pencil, .rv-pencil:focus { opacity: 1; }
</style>

<?php ob_start(); ?>
<script>
document.addEventListener('click', function (e) {
    var ren = e.target.closest('.btn_rename');
    if (!ren) return;
    document.getElementById('rename_pk').value = ren.getAttribute('data-pk');
    document.getElementById('rename_mac').textContent = ren.getAttribute('data-mac');
    document.getElementById('rename_name').value = ren.getAttribute('data-name') || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('renameModal')).show();
    setTimeout(function () { document.getElementById('rename_name').focus(); }, 300);
});
(function () {
    var balance = <?php echo (int)$reseller['credits']; ?>;
    var sel = document.getElementById('activate_package');
    function updateAfter() {
        var opt = sel.options[sel.selectedIndex];
        document.getElementById('activate_after').textContent = opt ? (balance - parseInt(opt.getAttribute('data-credits'), 10)) + ' créditos' : '—';
    }
    sel.addEventListener('change', updateAfter);
    function open(pk, mac, customer) {
        document.getElementById('activate_pk').value = pk;
        document.getElementById('activate_mac').textContent = mac;
        document.getElementById('activate_customer').value = customer || '';
        updateAfter();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('activateModal')).show();
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.btn_activate');
        if (b) open(b.getAttribute('data-pk'), b.getAttribute('data-mac'), b.getAttribute('data-customer'));
    });
    <?php if ($auto_open) { ?>open(<?php echo $auto_open; ?>, <?php echo json_encode($auto_mac); ?>);<?php } ?>
})();
</script>
<?php require_once(__DIR__."/../includes/devices_live.php"); devices_live_script("devices_sig"); ?>
<?php rv_footer(ob_get_clean()); ?>
