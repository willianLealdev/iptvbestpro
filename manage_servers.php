<?php $page_title = "Servidores";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/server_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    server_migrate($mysqli);
    require_once("includes/playlist_helper.php");
    dns_health_migrate($mysqli);
    $admin_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    function sv_flash($ok, $msg){
        $_SESSION['sv_msg'] = $msg;
        $_SESSION['msg'] = 'sv_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    $back = "manage_servers.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    // Pacotes: criar / editar
    if(isset($_POST['pkg_save'])){
        $id = (int)$_POST['pkg_id'];
        $name = mb_substr(trim(strip_tags($_POST['pkg_name'])), 0, 120);
        $credits = max(0, (int)$_POST['pkg_credits']);
        $brl = (float)str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $_POST['pkg_brl']));
        $usd = (float)str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $_POST['pkg_usd']));
        $sort = (int)$_POST['pkg_sort'];
        if($name == '' || $credits < 1 || ($brl <= 0 && $usd <= 0)){
            sv_flash(false, 'Informe o nome, a quantidade de créditos e pelo menos um preço.');
        } else if($id > 0){
            $stmt = mysqli_prepare($mysqli, "UPDATE tbl_server_packages SET name = ?, credits = ?, price_brl = ?, price_usd = ?, sort = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'siddii', $name, $credits, $brl, $usd, $sort, $id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            sv_flash(true, 'Pacote atualizado.');
        } else {
            $now = time();
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_server_packages (name, credits, price_brl, price_usd, sort, status, created_at) VALUES (?,?,?,?,?,1,?)");
            mysqli_stmt_bind_param($stmt, 'siddii', $name, $credits, $brl, $usd, $sort, $now);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            sv_flash(true, 'Pacote criado.');
        }
        header("Location:".$back); exit;
    }
    if(isset($_POST['pkg_toggle'])){
        mysqli_query($mysqli, "UPDATE tbl_server_packages SET status = 1 - status WHERE id = ".(int)$_POST['pkg_id']);
        sv_flash(true, 'Pacote alterado.');
        header("Location:".$back); exit;
    }
    // Servidores: créditos e bloqueio
    if(isset($_POST['credit_submit'])){
        $sid = (int)$_POST['server_id'];
        $amount = abs((int)$_POST['credit_amount']);
        if(isset($_POST['credit_operation']) && $_POST['credit_operation'] == 'remove'){ $amount = -$amount; }
        $is_srv = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_resellers WHERE id = $sid AND kind = 'server'"));
        $ok = $is_srv && resellers_add_credits($mysqli, $sid, $amount, mb_substr(trim(strip_tags($_POST['credit_note'])), 0, 255), 'admin', $admin_id) !== false;
        sv_flash($ok, $ok ? 'Créditos atualizados.' : 'Não foi possível alterar os créditos (o saldo não pode ficar negativo).');
        header("Location:".$back); exit;
    }
    if(isset($_POST['server_toggle'])){
        mysqli_query($mysqli, "UPDATE tbl_resellers SET status = 1 - status WHERE id = ".(int)$_POST['server_id']." AND kind = 'server'");
        sv_flash(true, 'Status do servidor alterado.');
        header("Location:".$back); exit;
    }
    if(isset($_POST['server_signup'])){
        panel_config_set($mysqli, 'server_signup', isset($_POST['signup_on']) ? '1' : '0');
        sv_flash(true, 'Configuração salva.');
        header("Location:".$back); exit;
    }
    if(isset($_SESSION['sv_msg'])){
        $client_lang['sv_custom'] = $_SESSION['sv_msg'];
        unset($_SESSION['sv_msg']);
    }

    $packages = server_packages($mysqli, false);
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $where = "r.kind = 'server'";
    if($keyword != ''){
        $kw = mysqli_real_escape_string($mysqli, $keyword);
        $where .= " AND (r.name LIKE '%$kw%' OR r.email LIKE '%$kw%' OR r.code LIKE '%$kw%' OR r.phone LIKE '%$kw%')";
    }
    $now = time();
    $servers = mysqli_query($mysqli, "SELECT r.*,
        (SELECT COUNT(*) FROM tbl_devices d WHERE d.owner = 'reseller' AND d.reseller_id = r.id) AS devices,
        (SELECT COUNT(*) FROM tbl_devices d WHERE d.owner = 'reseller' AND d.reseller_id = r.id AND d.package <> '' AND d.expires_at >= $now) AS active_devices,
        ((SELECT COUNT(*) FROM tbl_xui_dns x WHERE x.reseller_id = r.id) + (SELECT COUNT(*) FROM tbl_dns_share sh WHERE sh.reseller_id = r.id)) AS dns_count
        FROM tbl_resellers r WHERE $where ORDER BY r.id DESC LIMIT 200");
    $signup_on = panel_config_get($mysqli, 'server_signup', '1') == '1';
    $site = rtrim(brand_mail_base_url(), '/');
?>
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page">Servidores</li>
            </ol>
        </nav>

        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h5 class="mb-1"><i class="ri-server-line me-1"></i> Servidores parceiros</h5>
                        <p class="text-muted mb-0 small">O servidor compra pacotes de créditos. O código dele (8 números) ativa o aparelho na hora no app, usando 1 crédito (1 ano), e o cliente envia a lista em <b><?php echo htmlspecialchars($site); ?>/lista</b>, conferida só nas DNS do servidor.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo htmlspecialchars($site); ?>/servidor/register" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="ri-external-link-line"></i> Página de cadastro</a>
                        <form method="post" class="d-flex align-items-center gap-2 border rounded px-2">
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="signup_on" id="svSignup" <?php echo $signup_on ? 'checked' : ''; ?> onchange="this.form.submit()"><label class="form-check-label small" for="svSignup">Cadastro aberto</label></div>
                            <input type="hidden" name="server_signup" value="1">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Pacotes</h5>
                    <button type="button" class="btn btn-primary btn-sm js-pkg-edit" data-id="0" data-name="" data-credits="" data-brl="" data-usd="" data-sort="0"><i class="ri-add-line"></i> Novo pacote</button>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>Pacote</th><th>Créditos</th><th>Preço R$</th><th>Preço US$</th><th>Por aparelho</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php if(!$packages){ ?><tr><td colspan="7" class="text-muted py-4 text-center">Nenhum pacote. Crie o primeiro para os servidores poderem comprar.</td></tr><?php } ?>
                        <?php foreach($packages as $p){ $c = max(1, (int)$p['credits']); ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo (int)$p['credits']; ?></td>
                                <td><?php echo $p['price_brl'] > 0 ? money_currency($p['price_brl'], 'BRL') : '—'; ?></td>
                                <td><?php echo $p['price_usd'] > 0 ? money_currency($p['price_usd'], 'USD') : '—'; ?></td>
                                <td class="text-muted small"><?php echo $p['price_brl'] > 0 ? money_currency($p['price_brl'] / $c, 'BRL') : ''; ?><?php echo $p['price_usd'] > 0 ? ' · '.money_currency($p['price_usd'] / $c, 'USD') : ''; ?></td>
                                <td><?php echo (int)$p['status'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Oculto</span>'; ?></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary js-pkg-edit" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-credits="<?php echo (int)$p['credits']; ?>" data-brl="<?php echo $p['price_brl'] > 0 ? $p['price_brl'] : ''; ?>" data-usd="<?php echo $p['price_usd'] > 0 ? $p['price_usd'] : ''; ?>" data-sort="<?php echo (int)$p['sort']; ?>"><i class="ri-pencil-line"></i></button>
                                    <form method="post" class="d-inline"><input type="hidden" name="pkg_id" value="<?php echo (int)$p['id']; ?>"><button type="submit" name="pkg_toggle" value="1" class="btn btn-sm btn-outline-secondary"><?php echo (int)$p['status'] ? 'Ocultar' : 'Mostrar'; ?></button></form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">Servidores cadastrados</h5>
                    <form method="get" class="d-flex gap-2">
                        <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Buscar nome, e-mail ou código" value="<?php echo htmlspecialchars($keyword); ?>">
                        <button class="btn btn-sm btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>Servidor</th><th>Código</th><th>Créditos</th><th>Aparelhos</th><th>DNS</th><th>Cadastro</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php if(mysqli_num_rows($servers) == 0){ ?><tr><td colspan="8" class="text-muted py-4 text-center">Nenhum servidor cadastrado ainda.</td></tr><?php } ?>
                        <?php while($s = mysqli_fetch_assoc($servers)){ ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($s['name']); ?></b><br><small class="text-muted"><?php echo htmlspecialchars($s['email']); ?><?php echo $s['phone'] != '' ? ' · '.htmlspecialchars($s['phone']) : ''; ?></small></td>
                                <td><code style="font-size:15px" translate="no"><?php echo htmlspecialchars((string)$s['code']); ?></code></td>
                                <td class="fw-bold"><?php echo (int)$s['credits']; ?></td>
                                <td><a href="manage_devices.php?tab=srv&owner=<?php echo (int)$s['id']; ?>"><?php echo (int)$s['active_devices']; ?> ativos</a><br><small class="text-muted"><?php echo (int)$s['devices']; ?> no total</small></td>
                                <td><?php echo (int)$s['dns_count']; ?></td>
                                <td class="small"><?php echo date('d/m/Y', $s['created_at']); ?><?php echo !(int)$s['email_verified'] ? '<br><span class="badge bg-warning text-dark">E-mail pendente</span>' : ''; ?></td>
                                <td><?php echo (int)$s['status'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Bloqueado</span>'; ?></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success js-credit" data-id="<?php echo (int)$s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>"><i class="ri-coin-line"></i> Créditos</button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Alterar o status deste servidor?');"><input type="hidden" name="server_id" value="<?php echo (int)$s['id']; ?>"><button type="submit" name="server_toggle" value="1" class="btn btn-sm btn-outline-<?php echo (int)$s['status'] ? 'danger' : 'success'; ?>"><?php echo (int)$s['status'] ? 'Bloquear' : 'Desbloquear'; ?></button></form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">Pedidos de compra dos pacotes aparecem em <a href="manage_credit_orders.php">Pedidos de créditos</a>, junto com os das revendas.</p>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="pkgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="pkgTitle">Pacote</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="pkg_id" id="pkg_id">
                <div class="mb-3"><label class="form-label">Nome do pacote</label><input type="text" name="pkg_name" id="pkg_name" class="form-control" maxlength="120" placeholder="Ex.: Pacote 100 ativações" required></div>
                <div class="mb-3"><label class="form-label">Créditos (1 crédito = 1 aparelho por 1 ano)</label><input type="number" name="pkg_credits" id="pkg_credits" class="form-control" min="1" required></div>
                <div class="row g-3 mb-3">
                    <div class="col-6"><label class="form-label">Preço em R$</label><input type="text" name="pkg_brl" id="pkg_brl" class="form-control" placeholder="0,00"></div>
                    <div class="col-6"><label class="form-label">Preço em US$</label><input type="text" name="pkg_usd" id="pkg_usd" class="form-control" placeholder="0.00"></div>
                </div>
                <div class="mb-1"><label class="form-label">Ordem na lista</label><input type="number" name="pkg_sort" id="pkg_sort" class="form-control" value="0"></div>
                <small class="text-muted">Servidores do Brasil veem o preço em R$; os de outros países, em US$. Deixe o preço vazio para não oferecer o pacote naquela moeda.</small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="pkg_save" value="1" class="btn btn-primary">Salvar</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="creditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Créditos — <span id="credit_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="server_id" id="credit_server">
                <div class="mb-3">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="credit_operation" id="opAdd" value="add" checked><label class="btn btn-outline-success" for="opAdd">Adicionar</label>
                        <input type="radio" class="btn-check" name="credit_operation" id="opRem" value="remove"><label class="btn btn-outline-danger" for="opRem">Remover</label>
                    </div>
                </div>
                <div class="mb-3"><label class="form-label">Quantidade</label><input type="number" name="credit_amount" class="form-control" min="1" required></div>
                <div class="mb-1"><label class="form-label">Observação</label><input type="text" name="credit_note" class="form-control" maxlength="255" placeholder="Ex.: pagamento por Pix"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="credit_submit" value="1" class="btn btn-success">Salvar</button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
document.querySelectorAll('.js-pkg-edit').forEach(function (b) {
    b.addEventListener('click', function () {
        ['id', 'name', 'credits', 'brl', 'usd', 'sort'].forEach(function (k) { document.getElementById('pkg_' + k).value = b.getAttribute('data-' + k) || ''; });
        document.getElementById('pkgTitle').textContent = b.getAttribute('data-id') === '0' ? 'Novo pacote' : 'Editar pacote';
        new bootstrap.Modal(document.getElementById('pkgModal')).show();
    });
});
document.querySelectorAll('.js-credit').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('credit_server').value = b.getAttribute('data-id');
        document.getElementById('credit_name').textContent = b.getAttribute('data-name');
        new bootstrap.Modal(document.getElementById('creditModal')).show();
    });
});
});
</script>
<?php include("includes/footer.php"); ?>
