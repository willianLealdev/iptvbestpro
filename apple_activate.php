<?php $page_title="Ativação Apple";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require_once("includes/reseller_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    $packages = devices_packages();
    $now = time();
    $admin_label = 'admin: '.(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '');
    $is_super_admin = $_SESSION['admin_type'] == 3;
    $admin_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    // Ativar por ID do dispositivo (8 números): iPhone, iPad e Apple TV não têm campo de código
    $msg = ''; $msg_ok = true;
    $form = array('sid' => '', 'name' => '', 'package' => '1y', 'reseller' => 0);
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apple_activate'])){
        $form['sid'] = preg_replace('/\D/', '', isset($_POST['sid']) ? (string)$_POST['sid'] : '');
        $form['name'] = isset($_POST['customer_name']) ? trim((string)$_POST['customer_name']) : '';
        $form['package'] = isset($_POST['package']) ? (string)$_POST['package'] : '1y';
        $form['reseller'] = $is_super_admin && isset($_POST['reseller_id']) ? (int)$_POST['reseller_id'] : 0;
        if(!preg_match('/^[0-9]{8}$/', $form['sid'])){
            $msg_ok = false; $msg = 'O ID do dispositivo tem 8 números. Confira o que aparece na tela do app.';
        } else if($form['name'] == ''){
            $msg_ok = false; $msg = 'Informe o nome do cliente.';
        } else {
            $dev = device_find_by_short_id($mysqli, $form['sid']);
            if(!$dev){
                $msg_ok = false; $msg = 'ID não encontrado. Peça ao cliente para abrir o app no iPhone, iPad ou Apple TV e conferir os 8 números do ID do dispositivo.';
            } else {
                $r = devices_activate($mysqli, (int)$dev['id'], $form['package'], $form['reseller'], $admin_label, $admin_id);
                if($r['ok']){
                    devices_set_customer($mysqli, (int)$dev['id'], $form['name']);
                    $form = array('sid' => '', 'name' => '', 'package' => '1y', 'reseller' => 0);
                }
                $msg_ok = $r['ok']; $msg = $r['msg'];
            }
        }
    }

    $resellers = array();
    if($is_super_admin){
        $res = mysqli_query($mysqli, "SELECT id, name, credits FROM tbl_resellers WHERE status = 1 ORDER BY name");
        while($res && $r = mysqli_fetch_assoc($res)){ $resellers[] = $r; }
    }

    $list = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE platform IN ('iphone','ipad','apple_tv') AND (package <> '' OR owner <> '') ORDER BY last_seen DESC LIMIT 40");
    while($res && $row = mysqli_fetch_assoc($res)){ $list[] = $row; }
?>
<div class="nsofts-container">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb align-items-center">
            <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
            <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
        </ol>
    </nav>
    <div class="nsofts-card">
        <div class="nsofts-card__body p-4">
            <h4 class="mb-1"><i class="ri-apple-line me-1"></i>Ativação Apple</h4>
            <p class="text-muted mb-4">Para iPhone, iPad e Apple TV. O cliente abre o app e vê o <b>ID do dispositivo</b> (8 números). Digite esse ID aqui para ativar.</p>
            <?php if($msg != ''){ ?><div class="alert alert-<?php echo $msg_ok ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
            <form method="post" autocomplete="off" style="max-width:560px">
                <div class="mb-3">
                    <label class="form-label fw-semibold">ID do dispositivo (8 números)</label>
                    <input type="text" name="sid" class="form-control form-control-lg" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required
                           value="<?php echo htmlspecialchars($form['sid'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="00000000" style="font-family:monospace;letter-spacing:4px;font-weight:700">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome do cliente</label>
                    <input type="text" name="customer_name" class="form-control" maxlength="120" required value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Período</label>
                    <select name="package" class="form-select">
                        <?php foreach($packages as $key => $p){ ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['package'] == $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if($is_super_admin && $resellers){ ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Ativar como</label>
                    <select name="reseller_id" class="form-select">
                        <option value="0">Admin (não desconta crédito)</option>
                        <?php foreach($resellers as $r){ ?>
                            <option value="<?php echo (int)$r['id']; ?>" <?php echo $form['reseller'] == (int)$r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$r['credits']; ?> crédito(s))</option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <button type="submit" name="apple_activate" value="1" class="btn btn-primary"><i class="ri-check-line me-1"></i>Ativar</button>
            </form>
        </div>
    </div>

    <?php if($list){ ?>
    <div class="nsofts-card mt-4">
        <div class="nsofts-card__body p-0">
            <div class="p-3 fw-semibold">Aparelhos Apple</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-4">ID do dispositivo</th><th>Cliente</th><th>Aparelho</th><th>Dono</th><th>Vencimento</th><th>Situação</th></tr></thead>
                    <tbody>
                    <?php foreach($list as $d){ $st = devices_activation_status($d); ?>
                        <tr>
                            <td class="ps-4"><span style="font-family:monospace;font-weight:700"><?php echo $d['short_id'] != '' ? htmlspecialchars($d['short_id'], ENT_QUOTES, 'UTF-8') : '—'; ?></span></td>
                            <td><?php echo $d['customer_name'] != '' ? htmlspecialchars($d['customer_name'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td><?php echo htmlspecialchars($d['device_model'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(devices_owner_label($mysqli, $d), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int)$d['expires_at'] > 0 ? date('d/m/Y', (int)$d['expires_at']) : '—'; ?></td>
                            <td><?php echo $st == 'active' ? '<span class="badge bg-success">Ativo</span>' : ($st == 'expired' ? '<span class="badge bg-danger">Vencido</span>' : '<span class="badge bg-secondary">Aguardando</span>'); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<?php include("includes/footer.php");?>
