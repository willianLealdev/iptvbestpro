<?php $page_title="Clientes";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/customer_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    customers_migrate($mysqli);

    function cu_flash($ok, $msg){
        $_SESSION['cu_msg'] = $msg;
        $_SESSION['msg'] = 'cu_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    $back = "manage_customers.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    if(isset($_POST['toggle_customer'])){
        $id = (int)$_POST['customer_id'];
        mysqli_query($mysqli, "UPDATE tbl_customers SET status = IF(status = 1, 0, 1) WHERE id = $id");
        cu_flash(true, 'Status do cliente alterado.');
        header("Location:".$back);
        exit;
    }
    if(isset($_POST['set_currency'])){
        $id = (int)$_POST['customer_id'];
        currency_mode_save($mysqli, 'tbl_customers', $id, (string)$_POST['currency']);
        cu_flash(true, $_POST['currency'] == 'AUTO' ? 'Moeda do cliente volta a seguir o IP (geolocalização).' : 'Moeda do cliente fixada.');
        header("Location:".$back);
        exit;
    }

    if(isset($_SESSION['cu_msg'])){
        $client_lang['cu_custom'] = $_SESSION['cu_msg'];
        unset($_SESSION['cu_msg']);
    }

    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $currency_filter = (isset($_GET['currency']) && in_array($_GET['currency'], array('BRL','USD'))) ? $_GET['currency'] : '';
    $where = array();
    if($keyword != ''){
        $kw = mysqli_real_escape_string($mysqli, $keyword);
        $where[] = "(c.name LIKE '%$kw%' OR c.email LIKE '%$kw%' OR c.phone LIKE '%$kw%')";
    }
    if($currency_filter != '') $where[] = "c.currency = '$currency_filter'";
    $where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $now = time();

    $total_rows = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_customers c $where_sql"));
    $total_rows = (int)$total_rows['num'];
    $limit = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $last_page = max(1, (int)ceil($total_rows / $limit));
    if($page > $last_page) $page = $last_page;
    $start = ($page - 1) * $limit;

    $result = mysqli_query($mysqli, "SELECT c.*,
            (SELECT COUNT(*) FROM tbl_subscriptions s WHERE s.customer_id = c.id AND s.status = 'paid' AND s.code_used_at > 0 AND s.expires_at >= $now) AS active_subs,
            (SELECT COUNT(*) FROM tbl_subscriptions s WHERE s.customer_id = c.id AND s.status = 'pending') AS pending_subs,
            (SELECT COUNT(*) FROM tbl_subscriptions s WHERE s.customer_id = c.id AND s.status = 'paid' AND s.code_used_at = 0) AS free_codes,
            (SELECT COALESCE(SUM(amount),0) FROM tbl_subscriptions s WHERE s.customer_id = c.id AND s.status = 'paid') AS total_paid
        FROM tbl_customers c $where_sql ORDER BY c.id DESC LIMIT $start, $limit");

    $tot = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total, SUM(currency = 'BRL') AS brl, SUM(currency = 'USD') AS usd, SUM(created_at >= ".strtotime('today').") AS today FROM tbl_customers"));
    $portal_url = rtrim(getBaseUrl(), '/').'/cliente/';

    function cu_url($changes = array()){
        $params = array_merge($_GET, $changes);
        foreach($params as $k => $v){ if($v === '' || $v === null){ unset($params[$k]); } }
        return 'manage_customers.php'.(count($params) ? '?'.http_build_query($params) : '');
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

        <style>.cu-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }</style>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="card cu-card p-3"><div class="text-muted small">Clientes</div><div class="fs-3 fw-bold"><?php echo (int)$tot['total']; ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="card cu-card p-3"><div class="text-muted small">Cadastrados hoje</div><div class="fs-3 fw-bold" style="color:#16a34a;"><?php echo (int)$tot['today']; ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="card cu-card p-3"><div class="text-muted small">Pagam em R$</div><div class="fs-3 fw-bold"><?php echo (int)$tot['brl']; ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="card cu-card p-3"><div class="text-muted small">Pagam em US$</div><div class="fs-3 fw-bold"><?php echo (int)$tot['usd']; ?></div></div></div>
        </div>

        <div class="card cu-card">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <form method="get" class="d-flex flex-wrap gap-2">
                        <input type="text" name="keyword" class="form-control" style="width:260px;max-width:100%;" placeholder="Nome, e-mail ou WhatsApp" value="<?php echo htmlspecialchars($keyword); ?>">
                        <select name="currency" class="form-select" style="width:auto;" onchange="this.form.submit()">
                            <option value="">Todas as moedas</option>
                            <option value="BRL" <?php if($currency_filter=='BRL'){echo 'selected';} ?>>R$ (Brasil)</option>
                            <option value="USD" <?php if($currency_filter=='USD'){echo 'selected';} ?>>US$ (exterior)</option>
                        </select>
                        <button class="btn btn-outline-secondary" type="submit"><i class="ri-search-line"></i></button>
                    </form>
                    <div class="small text-muted">Portal do cliente: <a href="<?php echo htmlspecialchars($portal_url); ?>" target="_blank"><?php echo htmlspecialchars($portal_url); ?></a></div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Cliente</th><th>País / Moeda</th><th class="text-center">Ativas</th><th class="text-center">Códigos livres</th><th class="text-center">Pendentes</th><th class="text-end">Total pago</th><th>Cadastro</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                        <tbody>
                        <?php if(mysqli_num_rows($result) > 0){ while($c = mysqli_fetch_assoc($result)){ ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($c['name']); ?></b><br><small class="text-muted"><?php echo htmlspecialchars($c['email']); ?><?php echo $c['phone'] != '' ? ' · '.htmlspecialchars($c['phone']) : ''; ?></small></td>
                                <td class="text-nowrap">
                                    <?php if($c['country_code'] != ''){ ?><img src="https://flagcdn.com/w40/<?php echo strtolower(htmlspecialchars($c['country_code'])); ?>.png" alt="" style="width:22px;border-radius:3px;"> <?php echo htmlspecialchars($c['country_code']); ?><?php } else { ?><span class="text-muted">—</span><?php } ?>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>">
                                        <input type="hidden" name="set_currency" value="1">
                                        <?php echo currency_mode_select($c, false); ?>
                                    </form>
                                </td>
                                <td class="text-center"><b style="color:#16a34a;"><?php echo (int)$c['active_subs']; ?></b></td>
                                <td class="text-center"><?php echo (int)$c['free_codes']; ?></td>
                                <td class="text-center"><?php echo (int)$c['pending_subs'] > 0 ? '<span class="badge bg-warning text-dark">'.(int)$c['pending_subs'].'</span>' : '0'; ?></td>
                                <td class="text-end text-nowrap"><?php echo money_format_currency($c['total_paid'], $c['currency']); ?></td>
                                <td class="text-nowrap"><?php echo date('d/m/Y', $c['created_at']); ?><?php if($c['last_login']){ ?><br><small class="text-muted">último acesso <?php echo date('d/m/Y', $c['last_login']); ?></small><?php } ?></td>
                                <td><?php echo (int)$c['status'] == 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Bloqueado</span>'; ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="manage_subscriptions.php?customer=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary" title="Assinaturas"><i class="ri-vip-crown-line"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('<?php echo (int)$c['status'] == 1 ? 'Bloquear' : 'Desbloquear'; ?> este cliente?');">
                                        <input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" name="toggle_customer" class="btn btn-sm <?php echo (int)$c['status'] == 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>" title="<?php echo (int)$c['status'] == 1 ? 'Bloquear' : 'Desbloquear'; ?>"><i class="<?php echo (int)$c['status'] == 1 ? 'ri-forbid-line' : 'ri-check-line'; ?>"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="9" class="text-center text-muted py-5">Nenhum cliente cadastrado ainda.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <b>Total: <?php echo $total_rows; ?></b>
                    <?php if($last_page > 1){ ?>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for($p = max(1, $page - 2); $p <= min($last_page, $page + 2); $p++){ ?>
                                <li class="page-item <?php if($p == $page){ echo 'active'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(cu_url(array('page' => $p))); ?>"><?php echo $p; ?></a></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
