<?php $page_title="Resellers";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);

    // Adicionar / remover créditos
    if(isset($_POST['credit_submit'])){
        $amount = abs((int)$_POST['credit_amount']);
        if(isset($_POST['credit_operation']) && $_POST['credit_operation'] == 'remove'){ $amount = -$amount; }
        $ok = resellers_add_credits($mysqli, $_POST['reseller_id'], $amount, $_POST['credit_note'], 'admin', isset($_SESSION['id']) ? $_SESSION['id'] : 0);
        $_SESSION['msg'] = ($ok === false) ? 'reseller_credits_error' : 'reseller_credits_ok';
        $_SESSION['class'] = ($ok === false) ? 'error' : 'success';
        header("Location:manage_resellers.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : ''));
        exit;
    }

    // Moeda do revendedor (R$ ou US$): define o preço do crédito e a moeda dos pedidos
    if(isset($_POST['set_currency'])){
        $rid = (int)$_POST['reseller_id'];
        currency_mode_save($mysqli, 'tbl_resellers', $rid, (string)$_POST['currency']);
        header("Location:manage_resellers.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : ''));
        exit;
    }

    // Filtros
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $status_filter = (isset($_GET['status']) && ($_GET['status'] === '1' || $_GET['status'] === '0')) ? $_GET['status'] : '';

    $sortable = array(
        'name' => 'r.name', 'email' => 'r.email', 'credits' => 'r.credits',
        'devices' => 'devices', 'created' => 'r.created_at', 'status' => 'r.status'
    );
    $sort = (isset($_GET['sort']) && isset($sortable[$_GET['sort']])) ? $_GET['sort'] : 'created';
    $dir = (isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc') ? 'ASC' : 'DESC';

    // servidores parceiros ficam em Servidores (manage_servers.php)
    require_once("includes/server_helper.php");
    server_migrate($mysqli);
    $where = array(isset($rs_kind) ? "r.kind = '".$rs_kind."'" : "r.kind <> 'server'");
    if($keyword != ''){
        $kw = mysqli_real_escape_string($mysqli, $keyword);
        $where[] = "(r.name LIKE '%$kw%' OR r.email LIKE '%$kw%' OR r.remark LIKE '%$kw%' OR r.code LIKE '%$kw%' OR r.phone LIKE '%$kw%')";
    }
    if($status_filter !== ''){ $where[] = "r.status = ".(int)$status_filter; }
    $where_sql = count($where) ? 'WHERE '.implode(' AND ', $where) : '';

    $total_rows = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_resellers r $where_sql"));
    $total_rows = (int)$total_rows['num'];

    $limit = 15;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $last_page = max(1, (int)ceil($total_rows / $limit));
    if($page > $last_page){ $page = $last_page; }
    $start = ($page - 1) * $limit;

    $sql = "SELECT r.*, (SELECT COUNT(*) FROM tbl_devices d WHERE d.owner = 'reseller' AND d.reseller_id = r.id AND d.package <> '') AS devices
            FROM tbl_resellers r $where_sql
            ORDER BY ".$sortable[$sort]." $dir, r.id DESC
            LIMIT $start, $limit";
    $result = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));

    function rs_url($changes = array()){
        $params = array_merge($_GET, $changes);
        foreach($params as $k => $v){ if($v === '' || $v === null){ unset($params[$k]); } }
        return 'manage_resellers.php'.(count($params) ? '?'.http_build_query($params) : '');
    }
    function rs_sort_link($key, $label, $sort, $dir){
        $next = ($sort == $key && $dir == 'ASC') ? 'desc' : 'asc';
        $icon = 'ri-arrow-up-down-line';
        if($sort == $key){ $icon = ($dir == 'ASC') ? 'ri-arrow-up-s-fill' : 'ri-arrow-down-s-fill'; }
        return '<a class="rs-sort'.($sort == $key ? ' active' : '').'" href="'.htmlspecialchars(rs_url(array('sort' => $key, 'dir' => $next, 'page' => ''))).'">'
              .$label.' <i class="'.$icon.'"></i></a>';
    }
?>

<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <style>
            .rs-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .rs-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 18px; }
            .rs-search { position: relative; width: 300px; max-width: 100%; }
            .rs-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6b7280; }
            .rs-search input { padding-left: 34px; border-radius: 8px; }
            .rs-filter { width: 170px; border-radius: 8px; }
            .rs-btn-add { background: #4b9a2a; border-color: #4b9a2a; color: #fff; border-radius: 8px; padding: 8px 16px; }
            .rs-btn-add:hover { background: #3f8323; border-color: #3f8323; color: #fff; }
            .rs-table-wrap { border: 1px solid #eef0f4; border-radius: 10px; overflow: hidden; }
            .rs-table { margin: 0; }
            .rs-table thead th { background: #fafbfc; color: #1f2937; font-weight: 600; text-align: center; white-space: nowrap; padding: 16px 12px; border-bottom: 1px solid #eef0f4; }
            .rs-table tbody td { text-align: center; vertical-align: middle; padding: 12px; }
            .rs-sort { color: inherit; text-decoration: none; }
            .rs-sort i { color: #b6bcc8; font-size: 14px; }
            .rs-sort.active i { color: #3b6cf6; }
            .rs-empty { padding: 50px 10px; text-align: center; color: #9ca3af; }
            .rs-empty i { font-size: 46px; display: block; margin-bottom: 6px; color: #d1d5db; }
            .rs-footer { background: #fafbfc; border-top: 1px solid #eef0f4; padding: 14px 16px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
            .rs-credits { font-weight: 700; color: #1f2a44; }
            .rs-remark { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; margin: 0 auto; }
            .rs-actions .btn { padding: 8px 9px !important; margin: 0 3px; }
        </style>

        <div class="card rs-card">
            <div class="card-body p-4">
                <div class="rs-toolbar">
                    <form method="get" action="manage_resellers.php" class="d-flex flex-wrap gap-2">
                        <div class="rs-search">
                            <i class="ri-search-line"></i>
                            <input type="text" name="keyword" class="form-control" placeholder="Buscar nome, e-mail, código ou WhatsApp" value="<?php echo htmlspecialchars($keyword); ?>">
                        </div>
                        <select name="status" class="form-select rs-filter" onchange="this.form.submit()">
                            <option value="" <?php if($status_filter === ''){ echo 'selected'; } ?>>Todos os status</option>
                            <option value="1" <?php if($status_filter === '1'){ echo 'selected'; } ?>>Ativos</option>
                            <option value="0" <?php if($status_filter === '0'){ echo 'selected'; } ?>>Bloqueados</option>
                        </select>
                        <?php if($sort != 'created' || $dir != 'DESC'){ ?>
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                            <input type="hidden" name="dir" value="<?php echo strtolower($dir); ?>">
                        <?php } ?>
                        <?php if($keyword != '' || $status_filter !== ''){ ?>
                            <a href="manage_resellers.php" class="btn btn-outline-secondary d-inline-flex align-items-center">Limpar</a>
                        <?php } ?>
                    </form>
                    <a href="create_reseller.php?add=yes" class="btn rs-btn-add d-inline-flex align-items-center">
                        <i class="ri-add-line"></i><span class="ps-1">Add Reseller</span>
                    </a>
                </div>

                <div class="rs-table-wrap">
                    <div class="table-responsive">
                        <table class="table rs-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo rs_sort_link('name', 'Nome', $sort, $dir); ?></th>
                                    <th><?php echo rs_sort_link('email', 'E-mail', $sort, $dir); ?></th>
                                    <th>Código</th>
                                    <th><?php echo rs_sort_link('credits', 'Créditos', $sort, $dir); ?></th>
                                    <th>Moeda</th>
                                    <th><?php echo rs_sort_link('devices', 'Dispositivos Ativados', $sort, $dir); ?></th>
                                    <th><?php echo rs_sort_link('created', 'Criado em', $sort, $dir); ?></th>
                                    <th><?php echo rs_sort_link('status', 'Status', $sort, $dir); ?></th>
                                    <th>Observação</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(mysqli_num_rows($result) > 0){ ?>
                                <?php $i = 0; while($row = mysqli_fetch_assoc($result)){ $i++; ?>
                                    <tr>
                                        <td><?php echo $start + $i; ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?><?php if($row['phone'] != ''){ ?><br><small class="text-muted"><?php echo htmlspecialchars($row['phone']); ?></small><?php } ?></td>
                                        <td><span style="font-family:'Courier New',monospace;font-weight:700;letter-spacing:2px;color:#3b6cf6;"><?php echo htmlspecialchars((string)$row['code']); ?></span></td>
                                        <td><span class="rs-credits"><?php echo thousandsNumberFormat($row['credits']); ?></span></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="reseller_id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="set_currency" value="1">
                                                <?php echo currency_mode_select($row); ?>
                                            </form>
                                        </td>
                                        <td><?php echo thousandsNumberFormat($row['devices']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', $row['created_at']); ?></td>
                                        <td>
                                            <div class="nsofts-switch enable_disable d-inline-block" data-bs-toggle="tooltip" title="Ativar / Bloquear">
                                                <input type="checkbox" id="rs_status_<?php echo $row['id']; ?>" data-id="<?php echo $row['id']; ?>" data-table="tbl_resellers" data-column="status" class="cbx hidden btn_enable_disable" <?php if($row['status'] == 1){ echo 'checked'; } ?>>
                                                <label for="rs_status_<?php echo $row['id']; ?>" class="nsofts-switch__label"></label>
                                            </div>
                                        </td>
                                        <td><div class="rs-remark" title="<?php echo htmlspecialchars((string)$row['remark']); ?>"><?php echo $row['remark'] != '' ? htmlspecialchars($row['remark']) : '—'; ?></div></td>
                                        <td class="rs-actions text-nowrap">
                                            <a href="javascript:void(0)" class="btn btn-success btn-icon btn_credits" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['name']); ?>" data-credits="<?php echo (int)$row['credits']; ?>" data-bs-toggle="tooltip" title="Créditos"><i class="ri-coin-line"></i></a>
                                            <a href="create_reseller.php?reseller_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-icon" data-bs-toggle="tooltip" title="Editar"><i class="ri-pencil-line"></i></a>
                                            <a href="javascript:void(0)" class="btn btn-danger btn-icon btn_delete" data-id="<?php echo $row['id']; ?>" data-table="tbl_resellers" data-bs-toggle="tooltip" title="Excluir"><i class="ri-delete-bin-line"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr><td colspan="11"><div class="rs-empty"><i class="ri-inbox-line"></i>Nenhum revendedor encontrado</div></td></tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="rs-footer">
                        <span>Total: <?php echo thousandsNumberFormat($total_rows); ?></span>
                        <?php if($last_page > 1){ ?>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(rs_url(array('page' => $page - 1))); ?>">‹</a></li>
                                <?php for($p = max(1, $page - 2); $p <= min($last_page, $page + 2); $p++){ ?>
                                    <li class="page-item <?php if($p == $page){ echo 'active'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(rs_url(array('page' => $p))); ?>"><?php echo $p; ?></a></li>
                                <?php } ?>
                                <li class="page-item <?php if($page >= $last_page){ echo 'disabled'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(rs_url(array('page' => $page + 1))); ?>">›</a></li>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->

<!-- Modal: créditos -->
<div class="modal fade" id="creditsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Créditos — <span id="credits_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="reseller_id" id="credits_reseller_id">
                <p class="text-muted mb-3">Saldo atual: <b id="credits_current">0</b> créditos</p>
                <div class="mb-3">
                    <label class="form-label">Operação</label>
                    <select name="credit_operation" class="form-select">
                        <option value="add">Adicionar créditos</option>
                        <option value="remove">Remover créditos</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantidade</label>
                    <input type="number" name="credit_amount" class="form-control" min="1" step="1" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Observação (opcional)</label>
                    <input type="text" name="credit_note" class="form-control" maxlength="255" placeholder="Ex.: pagamento via Pix">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="credit_submit" class="btn btn-success">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn_credits');
    if (!btn) return;
    document.getElementById('credits_reseller_id').value = btn.getAttribute('data-id');
    document.getElementById('credits_name').textContent = btn.getAttribute('data-name');
    document.getElementById('credits_current').textContent = btn.getAttribute('data-credits');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('creditsModal')).show();
});
</script>

<?php include("includes/footer.php");?>
