<?php $page_title="Devices";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);
    $packages = devices_packages();
    $admin_label = 'admin: '.(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '');
    // Somente o Super Admin escolhe "Ativar como" (cobrar de um revendedor) e gera novo código
    $is_super_admin = isset($_SESSION['admin_type']) && $_SESSION['admin_type'] == 3;
    $charge_reseller = ($is_super_admin && isset($_POST['reseller_id'])) ? (int)$_POST['reseller_id'] : 0;
    $admin_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    $default_app_id = isset($settings_details['envato_package_name']) ? $settings_details['envato_package_name'] : '';

    function dev_flash($ok, $msg){
        $_SESSION['dev_msg'] = $msg;
        $_SESSION['msg'] = 'dev_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    $back_url = "manage_devices.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    // Ativar / renovar dispositivo existente
    $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    if(isset($_POST['activate_submit'])){
        if($customer_name == ''){
            dev_flash(false, 'Informe o nome do cliente.');
            header("Location:".$back_url);
            exit;
        }
        $r = devices_activate($mysqli, $_POST['device_pk'], $_POST['package'], $charge_reseller, $admin_label, $admin_id);
        if($r['ok']){ devices_set_customer($mysqli, $_POST['device_pk'], $customer_name); }
        dev_flash($r['ok'], $r['msg']);
        header("Location:".$back_url);
        exit;
    }

    // Editar o nome do cliente (lápis na lista)
    if(isset($_POST['rename_submit'])){
        if($customer_name == ''){
            dev_flash(false, 'Informe o nome do cliente.');
        } else {
            $ok = devices_set_customer($mysqli, $_POST['device_pk'], $customer_name);
            dev_flash($ok, $ok ? 'Nome do cliente atualizado.' : 'Não foi possível salvar o nome.');
        }
        header("Location:".$back_url);
        exit;
    }

    // Ativação manual pelo MAC / Device ID (só para quando o código de ativação der erro)
    if(isset($_POST['add_submit'])){
        $device_id = mb_substr(devices_format_mac(strip_tags($_POST['device_id'])), 0, 191);
        $application_id = mb_substr(trim(strip_tags($_POST['application_id'])), 0, 191);
        if($customer_name == ''){
            dev_flash(false, 'Informe o nome do cliente.');
        } else if($device_id == ''){
            dev_flash(false, 'Informe o MAC / Device ID.');
        } else {
            $now = time();
            $stmt = mysqli_prepare($mysqli, "INSERT IGNORE INTO tbl_devices (device_id, application_id, app_name, first_seen, last_seen) VALUES (?,?,?,?,0)");
            $app_name = defined('APP_NAME') ? APP_NAME : '';
            mysqli_stmt_bind_param($stmt, 'sssi', $device_id, $application_id, $app_name, $now);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($mysqli, "SELECT id FROM tbl_devices WHERE device_id = ? AND application_id = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $device_id, $application_id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $r = $row ? devices_activate($mysqli, $row['id'], $_POST['package'], $charge_reseller, $admin_label, $admin_id)
                      : array('ok' => false, 'msg' => 'Não foi possível criar o dispositivo.');
            if($r['ok']){ devices_set_customer($mysqli, $row['id'], $customer_name); }
            dev_flash($r['ok'], $r['msg']);
        }
        header("Location:".$back_url);
        exit;
    }

    // Aba "Dispositivos dev": separa aparelhos de teste da lista de clientes reais
    if(isset($_POST['dev_toggle'])){
        $to = $_POST['dev_toggle'] == '1' ? 1 : 2;
        $pk = (int)$_POST['device_pk'];
        mysqli_query($mysqli, "UPDATE tbl_devices SET is_dev = $to WHERE id = $pk");
        dev_flash(true, $to == 1 ? 'Dispositivo movido para Dispositivos dev.' : 'Dispositivo movido para a lista de dispositivos reais.');
        header("Location:".$back_url);
        exit;
    }
    // Ações em massa (caixinhas da lista): mover para dev / reais, desativar ou excluir vários de uma vez
    if(isset($_POST['bulk_action'])){
        $ids = isset($_POST['ids']) ? implode(',', array_filter(array_map('intval', explode(',', (string)$_POST['ids'])))) : '';
        $n = $ids != '' ? count(explode(',', $ids)) : 0;
        $act = (string)$_POST['bulk_action'];
        if($act == 'dev_bots'){
            // todos os que estão aguardando ativação e vieram de rede de loja, servidor ou emulador
            mysqli_query($mysqli, "UPDATE tbl_devices SET is_dev = 1 WHERE is_dev <> 1 AND package = '' AND origin IN ('store_google','store_amazon','store_roku','store_apple','datacenter','emulator')");
            dev_flash(true, mysqli_affected_rows($mysqli).' dispositivo(s) de teste das lojas / robôs movido(s) para Dispositivos dev.');
            header("Location:".$back_url);
            exit;
        } else if($n == 0){
            dev_flash(false, 'Nenhum dispositivo selecionado.');
        } else if($act == 'dev' || $act == 'real'){
            mysqli_query($mysqli, "UPDATE tbl_devices SET is_dev = ".($act == 'dev' ? 1 : 2)." WHERE id IN ($ids)");
            dev_flash(true, $act == 'dev' ? $n.' dispositivo(s) movido(s) para Dispositivos dev.' : $n.' dispositivo(s) movido(s) para a lista de dispositivos reais.');
        } else if($act == 'deactivate'){
            foreach(explode(',', $ids) as $one){ devices_deactivate($mysqli, (int)$one); }
            dev_flash(true, $n.' dispositivo(s) desativado(s).');
        } else if($act == 'delete'){
            devices_purge($mysqli, $ids); // apaga os aparelhos e todas as listas deles
            dev_flash(true, $n.' dispositivo(s) excluído(s).');
        }
        header("Location:".$back_url);
        exit;
    }
    // Detecção automática (só nos ainda não classificados; marcar à mão sempre vale):
    // emuladores, aparelhos ativados com o código de teste das lojas e clientes com nome de teste
    $store_test = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM tbl_customers WHERE email = 'teste-lojas@iptvbestpro.com'"));
    $store_test_id = $store_test ? (int)$store_test['id'] : -1;
    mysqli_query($mysqli, "UPDATE tbl_devices SET is_dev = 1 WHERE is_dev = 0 AND (
        (owner = 'customer' AND customer_id = $store_test_id)
        OR LOWER(device_model) REGEXP 'sdk_gphone|sdk_google|android sdk built|emulator|emulador|generic_x86|genymotion|headlesschrome|simulator'
        OR LOWER(customer_name) REGEXP '(^|[^a-z])(teste?|testing|dev|demo|emulador|emulator)([^a-z]|$)'
    )");

    // Desativar
    if(isset($_POST['deactivate_submit'])){
        devices_deactivate($mysqli, $_POST['device_pk']);
        dev_flash(true, 'Dispositivo desativado e livre para ativar com qualquer código.');
        header("Location:".$back_url);
        exit;
    }

    // Gerar novo código de ativação do admin: somente o Super Admin (admin_type 3)
    if(isset($_POST['regen_admin_code']) && $is_super_admin){
        $new_code = activation_code_generate($mysqli);
        if($new_code){ panel_config_set($mysqli, 'admin_code', $new_code); }
        dev_flash((bool)$new_code, $new_code ? 'Novo código do admin: '.$new_code : 'Não foi possível gerar o código.');
        header("Location:".$back_url);
        exit;
    }
    $admin_code = panel_config_get($mysqli, 'admin_code', '');

    if(isset($_SESSION['dev_msg'])){
        $client_lang['dev_custom'] = $_SESSION['dev_msg'];
        unset($_SESSION['dev_msg']);
    }

    // Filtros
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $app_filter = isset($_GET['app']) ? trim($_GET['app']) : '';
    $pkg_filter = (isset($_GET['package']) && ($_GET['package'] == 'none' || isset($packages[$_GET['package']]))) ? $_GET['package'] : '';
    $status_filter = (isset($_GET['status']) && in_array($_GET['status'], array('active','expired','inactive'))) ? $_GET['status'] : '';
    $owner_filter = isset($_GET['owner']) ? trim($_GET['owner']) : ''; // '' | admin | none | id do revendedor

    $sortable = array('app' => 'app_label', 'credits' => 'd.used_credits', 'activated' => 'd.activated_at', 'expires' => 'd.expires_at', 'seen' => 'd.first_seen');
    $sort = (isset($_GET['sort']) && isset($sortable[$_GET['sort']])) ? $_GET['sort'] : 'seen';
    $dir = (isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc') ? 'ASC' : 'DESC';

    $tab = (isset($_GET['tab']) && in_array($_GET['tab'], array('dev', 'srv', 'wait'))) ? $_GET['tab'] : '';
    $usage_filter = (isset($_GET['usage']) && in_array($_GET['usage'], array('using', 'idle', 'gone'))) ? $_GET['usage'] : '';
    $origin_filter = (isset($_GET['origin']) && in_array($_GET['origin'], array('real', 'test', 'unknown'))) ? $_GET['origin'] : '';
    $now = time();
    // aparelhos ativados com o código de um servidor parceiro ficam numa aba só deles
    require_once("includes/server_helper.php");
    server_migrate($mysqli);
    $srv_sql = "(d.owner = 'reseller' AND d.reseller_id IN (SELECT id FROM tbl_resellers WHERE kind = 'server'))";
    // Dispositivos = já ativados; Aguardando ativação = abriram o app mas ainda não foram ativados (ao ativar, passam sozinhos para Dispositivos)
    $tab_sql = array(
        ''     => "d.is_dev <> 1 AND NOT $srv_sql AND d.package <> ''",
        'wait' => "d.is_dev <> 1 AND NOT $srv_sql AND d.package = ''",
        'srv'  => "d.is_dev <> 1 AND $srv_sql",
        'dev'  => "d.is_dev = 1",
    );
    $where = array($tab_sql[$tab]);
    $dev_count = (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_devices d WHERE ".$tab_sql['dev']))['n'];
    $srv_count = (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_devices d WHERE ".$tab_sql['srv']))['n'];
    $real_count = (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_devices d WHERE ".$tab_sql['']))['n'];
    $wait_count = (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_devices d WHERE ".$tab_sql['wait']))['n'];
    // Aparelhos antigos (de antes da origem existir): descobre a empresa dona do IP aos poucos (200 por vez)
    $bf = mysqli_query($mysqli, "SELECT id, ip_address, device_model FROM tbl_devices WHERE origin = '' AND ip_address <> '' ORDER BY last_seen DESC LIMIT 200");
    while($bf && $b = mysqli_fetch_assoc($bf)){
        $org = devices_asn_org($b['ip_address']);
        $og = devices_origin_guess($b['device_model'], $org);
        if($og == '') continue;
        $st = mysqli_prepare($mysqli, "UPDATE tbl_devices SET asn_org = ?, origin = ? WHERE id = ?");
        mysqli_stmt_bind_param($st, 'ssi', $org, $og, $b['id']);
        mysqli_stmt_execute($st); mysqli_stmt_close($st);
    }
    $wait_origin = array('real' => 0, 'test' => 0, 'unknown' => 0);
    $wo = mysqli_query($mysqli, "SELECT CASE WHEN d.origin = 'real' THEN 'real' WHEN d.origin = '' THEN 'unknown' ELSE 'test' END AS k, COUNT(*) AS n FROM tbl_devices d WHERE ".$tab_sql['wait']." GROUP BY k");
    while($wo && $w = mysqli_fetch_assoc($wo)){ $wait_origin[$w['k']] = (int)$w['n']; }
    if($usage_filter != ''){ $where[] = devices_usage_sql($usage_filter); }
    if($origin_filter == 'real'){ $where[] = "d.origin = 'real'"; }
    if($origin_filter == 'unknown'){ $where[] = "d.origin = ''"; }
    if($origin_filter == 'test'){ $where[] = "d.origin NOT IN ('', 'real')"; }
    $my_ip = devices_client_ip();
    if($keyword != ''){
        $kw = mysqli_real_escape_string($mysqli, $keyword);
        $where[] = "(d.customer_name LIKE '%$kw%' OR d.device_id LIKE '%$kw%' OR d.activated_by LIKE '%$kw%' OR d.device_model LIKE '%$kw%' OR d.ip_address LIKE '%$kw%')";
    }
    if($app_filter != ''){
        $af = mysqli_real_escape_string($mysqli, $app_filter);
        $where[] = "COALESCE(NULLIF(d.app_name,''), d.application_id) = '$af'";
    }
    if($pkg_filter == 'none'){ $where[] = "d.package = ''"; }
    else if($pkg_filter != ''){ $where[] = "d.package = '".mysqli_real_escape_string($mysqli, $pkg_filter)."'"; }
    if($status_filter == 'active'){ $where[] = "d.package <> '' AND (d.expires_at = 0 OR d.expires_at >= $now)"; }
    if($status_filter == 'expired'){ $where[] = "d.package <> '' AND d.expires_at > 0 AND d.expires_at < $now"; }
    if($status_filter == 'inactive'){ $where[] = "d.package = ''"; }
    if($owner_filter == 'admin'){ $where[] = "d.owner = 'admin'"; }
    else if($owner_filter == 'none'){ $where[] = "d.owner = ''"; }
    else if($owner_filter == 'customer'){ $where[] = "d.owner = 'customer'"; }
    else if(ctype_digit($owner_filter)){ $where[] = "d.owner = 'reseller' AND d.reseller_id = ".(int)$owner_filter; }
    $where_sql = count($where) ? 'WHERE '.implode(' AND ', $where) : '';

    $total_rows = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num FROM tbl_devices d $where_sql"));
    $total_rows = (int)$total_rows['num'];

    $limit = 20;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $last_page = max(1, (int)ceil($total_rows / $limit));
    if($page > $last_page){ $page = $last_page; }
    $start = ($page - 1) * $limit;

    $result = mysqli_query($mysqli, "SELECT d.*, COALESCE(NULLIF(d.app_name,''), NULLIF(d.application_id,''), '—') AS app_label
                                     FROM tbl_devices d $where_sql
                                     ORDER BY ".$sortable[$sort]." $dir, d.id DESC LIMIT $start, $limit") or die(mysqli_error($mysqli));

    $apps = array();
    $res = mysqli_query($mysqli, "SELECT DISTINCT COALESCE(NULLIF(app_name,''), application_id) AS a FROM tbl_devices WHERE COALESCE(NULLIF(app_name,''), application_id) <> '' ORDER BY a");
    while($a = mysqli_fetch_assoc($res)){ $apps[] = $a['a']; }

    $app_ids = array();
    if($default_app_id != ''){ $app_ids[] = $default_app_id; }
    $res = mysqli_query($mysqli, "SELECT DISTINCT application_id FROM tbl_devices WHERE application_id <> ''");
    while($a = mysqli_fetch_assoc($res)){ if(!in_array($a['application_id'], $app_ids)){ $app_ids[] = $a['application_id']; } }

    $resellers = array();
    $res = mysqli_query($mysqli, "SELECT id, name, email, credits FROM tbl_resellers WHERE status = 1 ORDER BY name");
    while($r = mysqli_fetch_assoc($res)){ $resellers[] = $r; }

    $all_resellers = array();
    $res = mysqli_query($mysqli, "SELECT id, name FROM tbl_resellers ORDER BY name");
    while($r = mysqli_fetch_assoc($res)){ $all_resellers[] = $r; }

    function dv_url($changes = array()){
        $params = array_merge($_GET, $changes);
        foreach($params as $k => $v){ if($v === '' || $v === null){ unset($params[$k]); } }
        return 'manage_devices.php'.(count($params) ? '?'.http_build_query($params) : '');
    }
    function dv_sort($key, $sort, $dir){
        $next = ($sort == $key && $dir == 'ASC') ? 'desc' : 'asc';
        $icon = 'ri-arrow-up-down-line';
        if($sort == $key){ $icon = ($dir == 'ASC') ? 'ri-arrow-up-s-fill' : 'ri-arrow-down-s-fill'; }
        return '<a class="dv-sort'.($sort == $key ? ' active' : '').'" href="'.htmlspecialchars(dv_url(array('sort' => $key, 'dir' => $next, 'page' => ''))).'" title="Ordenar"><i class="'.$icon.'"></i></a>';
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
            .dv-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .dv-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 18px; }
            .dv-search { position: relative; width: 300px; max-width: 100%; }
            .dv-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6b7280; }
            .dv-search input { padding-left: 34px; border-radius: 8px; }
            .dv-filter { width: auto; min-width: 150px; border-radius: 8px; }
            .dv-btn-add { background: #4b9a2a; border-color: #4b9a2a; color: #fff; border-radius: 8px; padding: 8px 16px; }
            .dv-btn-add:hover { background: #3f8323; border-color: #3f8323; color: #fff; }
            .dv-table-wrap { border: 1px solid #eef0f4; border-radius: 10px; overflow: hidden; }
            .dv-table { margin: 0; }
            .dv-table thead th { background: #fafbfc; color: #1f2937; font-weight: 600; text-align: center; white-space: nowrap; padding: 16px 12px; border-bottom: 1px solid #eef0f4; border-right: 1px solid #f1f3f7; }
            .dv-table tbody td { text-align: center; vertical-align: middle; padding: 12px; border-right: 1px solid #f6f7f9; white-space: nowrap; }
            .dv-table th:last-child, .dv-table td:last-child { border-right: 0; position: sticky; right: 0; background: #fff; box-shadow: -6px 0 8px -6px rgba(0,0,0,.08); }
            .dv-table thead th:last-child { background: #fafbfc; }
            .dv-sort { color: #b6bcc8; text-decoration: none; margin-left: 4px; }
            .dv-sort.active { color: #3b6cf6; }
            .dv-copy { color: #3b6cf6; cursor: pointer; margin-left: 4px; }
            .dv-app { display: inline-flex; align-items: center; gap: 8px; }
            .dv-app img { width: 30px; height: 30px; border-radius: 6px; object-fit: cover; background: #111827; }
            .dv-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }
            .pk-year { color: #389e0d; background: #f6ffed; border-color: #d9f7be; }
            .pk-lifetime { color: #531dab; background: #f9f0ff; border-color: #efdbff; }
            .pk-trial { color: #d46b08; background: #fff7e6; border-color: #ffe7ba; }
            .pk-none { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
            .dv-expired { color: #dc2626; font-weight: 600; }
            .dv-muted { color: #9ca3af; }
            .dv-empty { padding: 50px 10px; text-align: center; color: #9ca3af; }
            .dv-empty i { font-size: 46px; display: block; margin-bottom: 6px; color: #d1d5db; }
            .dv-footer { background: #fafbfc; border-top: 1px solid #eef0f4; padding: 14px 16px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
            .dv-tabs { display: flex; gap: 6px; border-bottom: 1px solid #eef0f4; margin-bottom: 18px; flex-wrap: wrap; }
            .dv-tabs a { padding: 10px 16px; color: #6b7280; text-decoration: none; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -1px; display: inline-flex; align-items: center; gap: 6px; }
            .dv-tabs a.active { color: #3b6cf6; border-bottom-color: #3b6cf6; }
            .dv-usage { display: inline-flex; align-items: center; gap: 3px; margin-top: 4px; font-size: 12px; font-weight: 600; white-space: nowrap; }
            .us-using { color: #389e0d; }
            .us-idle { color: #d46b08; }
            .us-gone { color: #cf1322; }
            .dv-origin { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 4px; font-size: 12.5px; font-weight: 600; border: 1px solid transparent; white-space: nowrap; }
            .or-real { color: #389e0d; background: #f6ffed; border-color: #d9f7be; }
            .or-store { color: #d46b08; background: #fff7e6; border-color: #ffe7ba; }
            .or-bot { color: #cf1322; background: #fff1f0; border-color: #ffccc7; }
            .or-none { color: #8c8c8c; background: #fafafa; border-color: #f0f0f0; }
            .dv-tabs .dv-count { background: #eef2ff; color: #3b6cf6; border-radius: 10px; padding: 0 8px; font-size: 12px; }
            .dv-check { width: 18px; height: 18px; cursor: pointer; vertical-align: middle; }
            .dv-bulk { display: none; align-items: center; gap: 8px; flex-wrap: wrap; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; }
            .dv-bulk.show { display: flex; }
            .dv-bulk b { color: #3b6cf6; margin-right: 6px; }
            tr.dv-selected td { background: #f5f8ff; }
            .dv-dots { border: 0; background: transparent; font-size: 20px; line-height: 1; color: #374151; padding: 2px 8px; }
        </style>

        <div class="card dv-card mb-4">
            <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="text-muted">Seu código de ativação (admin)</div>
                    <div style="font-family:'Courier New',monospace;font-size:32px;font-weight:700;letter-spacing:8px;color:#3b6cf6;">
                        <?php echo htmlspecialchars($admin_code); ?>
                        <i class="ri-file-copy-line dv-copy" style="font-size:20px;letter-spacing:0;" data-copy="<?php echo htmlspecialchars($admin_code); ?>" title="Copiar"></i>
                    </div>
                    <small class="text-muted">O cliente digita este código no app e o aparelho aparece aqui com o filtro <b>Meus (admin)</b>.</small>
                </div>
                <?php if($is_super_admin){ ?>
                    <form method="post" onsubmit="return confirm('Gerar um novo código? O código atual deixa de funcionar para novos vínculos.');">
                        <button type="submit" name="regen_admin_code" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i> Gerar novo código</button>
                    </form>
                <?php } ?>
            </div>
        </div>

        <div class="card dv-card">
            <div class="card-body p-4">
                <div class="dv-tabs">
                    <a href="manage_devices.php" class="<?php echo $tab == '' ? 'active' : ''; ?>"><i class="ri-device-line"></i>Dispositivos <span class="dv-count"><?php echo thousandsNumberFormat($real_count); ?></span></a>
                    <a href="manage_devices.php?tab=wait" class="<?php echo $tab == 'wait' ? 'active' : ''; ?>"><i class="ri-time-line"></i>Aguardando ativação <span class="dv-count"><?php echo thousandsNumberFormat($wait_count); ?></span></a>
                    <a href="manage_devices.php?tab=srv" class="<?php echo $tab == 'srv' ? 'active' : ''; ?>"><i class="ri-server-line"></i>Servidores <span class="dv-count"><?php echo thousandsNumberFormat($srv_count); ?></span></a>
                    <a href="manage_devices.php?tab=dev" class="<?php echo $tab == 'dev' ? 'active' : ''; ?>"><i class="ri-bug-line"></i>Dispositivos dev <span class="dv-count"><?php echo thousandsNumberFormat($dev_count); ?></span></a>
                </div>
                <?php if($tab == 'srv'){ ?>
                    <p class="text-muted small mb-3">Aparelhos ativados com o código de um servidor parceiro. A ativação é automática (1 crédito do servidor) e a lista é enviada pelo cliente na página /lista.</p>
                <?php } ?>
                <?php if($tab == 'wait'){ ?>
                    <p class="text-muted small mb-2">Aparelhos que abriram o app mas ainda não foram ativados. Quando são ativados, passam sozinhos para <b>Dispositivos</b>.
                        A <b>origem provável</b> vem da empresa dona da internet do aparelho: as lojas (Google, Amazon, Roku, Apple) testam o app dentro da rede delas, e cliente de verdade usa internet de casa ou do celular.</p>
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <a href="<?php echo htmlspecialchars(dv_url(array('origin' => '', 'page' => ''))); ?>" class="btn btn-sm <?php echo $origin_filter == '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todos</a>
                        <a href="<?php echo htmlspecialchars(dv_url(array('origin' => 'real', 'page' => ''))); ?>" class="btn btn-sm <?php echo $origin_filter == 'real' ? 'btn-success' : 'btn-outline-success'; ?>"><i class="ri-user-heart-line"></i> Prováveis clientes reais (<?php echo $wait_origin['real']; ?>)</a>
                        <a href="<?php echo htmlspecialchars(dv_url(array('origin' => 'test', 'page' => ''))); ?>" class="btn btn-sm <?php echo $origin_filter == 'test' ? 'btn-warning' : 'btn-outline-warning'; ?>"><i class="ri-robot-2-line"></i> Lojas / robôs (<?php echo $wait_origin['test']; ?>)</a>
                        <a href="<?php echo htmlspecialchars(dv_url(array('origin' => 'unknown', 'page' => ''))); ?>" class="btn btn-sm <?php echo $origin_filter == 'unknown' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Sem dados (<?php echo $wait_origin['unknown']; ?>)</a>
                        <?php if($wait_origin['test'] > 0){ ?>
                        <form method="post" class="ms-auto" onsubmit="return confirm('Mover para Dispositivos dev todos os que estão aguardando ativação e vieram das lojas, de servidores ou de emuladores?');">
                            <button type="submit" name="bulk_action" value="dev_bots" class="btn btn-sm btn-outline-primary"><i class="ri-bug-line"></i> Mover lojas / robôs para Dispositivos dev</button>
                        </form>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if($tab == 'dev'){ ?>
                    <p class="text-muted small mb-3">Aparelhos de teste (emuladores, revisão das lojas e os que você marcar). Ficam fora da lista de dispositivos reais.</p>
                <?php } ?>
                <div class="dv-toolbar">
                    <form method="get" action="manage_devices.php" class="d-flex flex-wrap gap-2">
                        <?php if($tab != ''){ ?><input type="hidden" name="tab" value="<?php echo $tab; ?>"><?php } ?>
                        <div class="dv-search">
                            <i class="ri-search-line"></i>
                            <input type="text" name="keyword" class="form-control" placeholder="Buscar cliente, MAC, e-mail ou IP" value="<?php echo htmlspecialchars($keyword); ?>">
                        </div>
                        <select name="app" class="form-select dv-filter" onchange="this.form.submit()">
                            <option value="">Todos os apps</option>
                            <?php foreach($apps as $a){ ?>
                                <option value="<?php echo htmlspecialchars($a); ?>" <?php if($app_filter == $a){ echo 'selected'; } ?>><?php echo htmlspecialchars($a); ?></option>
                            <?php } ?>
                        </select>
                        <select name="package" class="form-select dv-filter" onchange="this.form.submit()">
                            <option value="">Todos os pacotes</option>
                            <?php foreach($packages as $k => $p){ ?>
                                <option value="<?php echo $k; ?>" <?php if($pkg_filter == $k){ echo 'selected'; } ?>><?php echo $p['label']; ?></option>
                            <?php } ?>
                            <option value="none" <?php if($pkg_filter == 'none'){ echo 'selected'; } ?>>Aguardando ativação</option>
                        </select>
                        <select name="status" class="form-select dv-filter" onchange="this.form.submit()">
                            <option value="">Todos os status</option>
                            <option value="active" <?php if($status_filter == 'active'){ echo 'selected'; } ?>>Ativos</option>
                            <option value="expired" <?php if($status_filter == 'expired'){ echo 'selected'; } ?>>Expirados</option>
                            <option value="inactive" <?php if($status_filter == 'inactive'){ echo 'selected'; } ?>>Aguardando ativação</option>
                        </select>
                        <?php if($sort != 'seen' || $dir != 'DESC'){ ?>
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                            <input type="hidden" name="dir" value="<?php echo strtolower($dir); ?>">
                        <?php } ?>
                        <select name="usage" class="form-select dv-filter" onchange="this.form.submit()" title="Pelo último acesso: o app não avisa quando é removido">
                            <option value="">Todos os usos</option>
                            <option value="using" <?php if($usage_filter == 'using'){ echo 'selected'; } ?>>Usando (até 3 dias)</option>
                            <option value="idle" <?php if($usage_filter == 'idle'){ echo 'selected'; } ?>>Parados (4 a 15 dias)</option>
                            <option value="gone" <?php if($usage_filter == 'gone'){ echo 'selected'; } ?>>Provavelmente removeram (mais de 15 dias)</option>
                        </select>
                        <?php if($origin_filter != ''){ ?><input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin_filter); ?>"><?php } ?>
                        <select name="owner" class="form-select dv-filter" onchange="this.form.submit()">
                            <option value="">Todos os revendedores</option>
                            <option value="admin" <?php if($owner_filter == 'admin'){ echo 'selected'; } ?>>Meus (admin)</option>
                            <option value="customer" <?php if($owner_filter == 'customer'){ echo 'selected'; } ?>>Clientes (assinatura)</option>
                            <option value="none" <?php if($owner_filter == 'none'){ echo 'selected'; } ?>>Sem vínculo</option>
                            <?php foreach($all_resellers as $r){ ?>
                                <option value="<?php echo $r['id']; ?>" <?php if($owner_filter === (string)$r['id']){ echo 'selected'; } ?>><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php } ?>
                        </select>
                        <?php if($keyword != '' || $app_filter != '' || $pkg_filter != '' || $status_filter != '' || $owner_filter != '' || $usage_filter != ''){ ?>
                            <a href="manage_devices.php<?php echo $tab != '' ? '?tab='.$tab : ''; ?>" class="btn btn-outline-secondary d-inline-flex align-items-center">Limpar</a>
                        <?php } ?>
                    </form>
                    <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center js-devices-refresh" title="Atualiza sozinho a cada 10 segundos"><i class="ri-refresh-line"></i><span class="ps-1">Atualizar</span></button>
                    <a href="javascript:void(0)" class="btn dv-btn-add d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                        <i class="ri-add-line"></i><span class="ps-1">Ativação manual</span>
                    </a>
                </div>

                <form method="post" id="dvBulkForm" class="dv-bulk">
                    <input type="hidden" name="ids" id="dvBulkIds">
                    <b><span id="dvBulkCount">0</span> selecionado(s)</b>
                    <?php if($tab == 'dev'){ ?>
                        <button type="submit" name="bulk_action" value="real" class="btn btn-sm btn-outline-primary"><i class="ri-user-line"></i> Mover para dispositivos reais</button>
                    <?php } else { ?>
                        <button type="submit" name="bulk_action" value="dev" class="btn btn-sm btn-outline-primary"><i class="ri-bug-line"></i> Mover para Dispositivos dev</button>
                    <?php } ?>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary" data-confirm="Desativar os dispositivos selecionados? Eles ficam livres (sem revenda e sem listas) para ativar com qualquer código. Os créditos usados não são devolvidos."><i class="ri-forbid-line"></i> Desativar</button>
                    <button type="submit" name="bulk_action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="Excluir os dispositivos selecionados e todas as listas deles? Não dá para desfazer."><i class="ri-delete-bin-line"></i> Excluir</button>
                    <button type="button" class="btn btn-sm btn-link text-muted" id="dvBulkClear">Limpar seleção</button>
                </form>
                <div class="dv-table-wrap">
                    <div class="table-responsive">
                        <table class="table dv-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="dv-check" id="dvCheckAll" title="Selecionar todos desta página"></th>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Mac</th>
                                    <th>Revendedor</th>
                                    <th>App<?php echo dv_sort('app', $sort, $dir); ?></th>
                                    <?php if($tab == 'wait'){ ?>
                                    <th>Origem provável</th>
                                    <th>Primeiro acesso<?php echo dv_sort('seen', $sort, $dir); ?></th>
                                    <th>Último acesso</th>
                                    <th>Dias de uso</th>
                                    <?php } else { ?>
                                    <th>Créditos usados<?php echo dv_sort('credits', $sort, $dir); ?></th>
                                    <th>Pacote</th>
                                    <th>Ativação<?php echo dv_sort('activated', $sort, $dir); ?></th>
                                    <th>Vencimento<?php echo dv_sort('expires', $sort, $dir); ?></th>
                                    <?php } ?>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(mysqli_num_rows($result) > 0){ ?>
                                <?php $i = 0; while($row = mysqli_fetch_assoc($result)){ $i++;
                                    $status = devices_activation_status($row);
                                    $pkg = devices_package_info($row['package']); $owner_label = devices_owner_label($mysqli, $row); ?>
                                    <tr>
                                        <td><input type="checkbox" class="dv-check dv-row-check" value="<?php echo (int)$row['id']; ?>"></td>
                                        <td><?php echo $start + $i; ?></td>
                                        <td class="fw-semibold text-start text-nowrap">
                                            <span translate="no"><?php echo $row['customer_name'] != '' ? htmlspecialchars($row['customer_name']) : '<span class="dv-muted fw-normal">—</span>'; ?></span>
                                            <a href="javascript:void(0)" class="dv-pencil btn_rename ms-1" data-pk="<?php echo (int)$row['id']; ?>" data-name="<?php echo htmlspecialchars($row['customer_name']); ?>" data-mac="<?php echo htmlspecialchars($row['device_id']); ?>" title="Editar nome do cliente"><i class="ri-pencil-line"></i></a>
                                        </td>
                                        <td>
                                            <span><?php echo htmlspecialchars($row['device_id']); ?></span>
                                            <i class="ri-file-copy-line dv-copy" data-copy="<?php echo htmlspecialchars($row['device_id']); ?>" title="Copiar"></i>
                                            <?php if($row['device_model'] != '' || $row['country_code'] != ''){ ?><br><small class="dv-muted"><?php echo htmlspecialchars($row['device_model']); ?></small><?php echo devices_flag($row['country_code'], $row['country_name']); ?><?php } ?>
                                        </td>
                                        <td>
                                            <?php $who = $owner_label != '' ? $owner_label : $row['activated_by']; ?>
                                            <?php if($who != ''){ ?>
                                                <span><?php echo htmlspecialchars($who); ?></span>
                                                <i class="ri-file-copy-line dv-copy" data-copy="<?php echo htmlspecialchars($who); ?>" title="Copiar"></i>
                                                <?php if($row['activated_by'] != '' && $row['activated_by'] != $who){ ?><br><small class="dv-muted">ativado por <?php echo htmlspecialchars($row['activated_by']); ?></small><?php } ?>
                                            <?php } else { ?><span class="dv-muted">Sem vínculo</span><?php } ?>
                                        </td>
                                        <td>
                                            <span class="dv-app">
                                                <img src="<?php echo htmlspecialchars(brand_logo_url()); ?>" alt="" onerror="this.style.visibility='hidden'">
                                                <span><?php echo htmlspecialchars($row['app_label']); ?></span>
                                            </span>
                                            <?php $us = devices_usage_info($row['last_seen']); ?>
                                            <br><span class="dv-usage <?php echo $us[2]; ?>" title="Último acesso: <?php echo (int)$row['last_seen'] > 0 ? date('d/m/Y H:i', $row['last_seen']) : '—'; ?>"><i class="<?php echo $us[3]; ?>"></i> <?php echo $us[1]; ?></span>
                                        </td>
                                        <?php if($tab == 'wait'){ $oi = devices_origin_info($row['origin']); ?>
                                        <td>
                                            <span class="dv-origin <?php echo $oi[1]; ?>"><i class="<?php echo $oi[2]; ?>"></i> <?php echo $oi[0]; ?></span>
                                            <?php if($row['asn_org'] != ''){ ?><br><small class="dv-muted" translate="no"><?php echo htmlspecialchars($row['asn_org']); ?></small><?php } ?>
                                            <?php if($row['ip_address'] != '' && $row['ip_address'] === $my_ip){ ?><br><small class="text-primary fw-semibold">Mesma internet que você</small><?php } ?>
                                        </td>
                                        <td><?php echo (int)$row['first_seen'] > 0 ? date('d/m/Y H:i', $row['first_seen']) : '—'; ?></td>
                                        <td><?php echo (int)$row['last_seen'] > 0 ? date('d/m/Y H:i', $row['last_seen']) : '—'; ?><?php if($row['app_version'] != ''){ ?><br><small class="dv-muted">versão <?php echo htmlspecialchars($row['app_version']); ?></small><?php } ?></td>
                                        <td><?php $ds = max(1, (int)$row['days_seen']); echo $ds; ?><?php if($ds >= 2){ ?><br><small class="text-success">voltou outros dias</small><?php } else { ?><br><small class="dv-muted">só 1 dia</small><?php } ?></td>
                                        <?php } else { ?>
                                        <td><?php echo (int)$row['used_credits']; ?></td>
                                        <td>
                                            <?php if($pkg){ ?>
                                                <span class="dv-badge <?php echo $pkg['class']; ?>"><?php echo $pkg['label']; ?></span>
                                            <?php } else { ?>
                                                <span class="dv-badge pk-trial">Aguardando ativação</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo (int)$row['activated_at'] > 0 ? date('d/m/Y', $row['activated_at']) : '<span class="dv-muted">—</span>'; ?></td>
                                        <td>
                                            <?php if($status == 'inactive'){ ?>
                                                <span class="dv-muted">—</span>
                                            <?php } else if((int)$row['expires_at'] == 0){ ?>
                                                Vitalício
                                            <?php } else { ?>
                                                <span class="<?php echo $status == 'expired' ? 'dv-expired' : ''; ?>"><?php echo date('d/m/Y', $row['expires_at']); ?></span>
                                                <?php if($status == 'expired'){ ?><br><small class="dv-expired">Expirado</small><?php } ?>
                                            <?php } ?>
                                        </td>
                                        <?php } ?>
                                        <td>
                                            <div class="dropdown">
                                                <button class="dv-dots" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">···</button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item btn_activate" href="javascript:void(0)" data-pk="<?php echo $row['id']; ?>" data-mac="<?php echo htmlspecialchars($row['device_id']); ?>" data-customer="<?php echo htmlspecialchars($row['customer_name']); ?>" data-owner="<?php echo htmlspecialchars($row['owner']); ?>" data-reseller="<?php echo (int)$row['reseller_id']; ?>" data-owner-name="<?php echo htmlspecialchars($owner_label); ?>">
                                                        <i class="ri-flashlight-line me-1"></i><?php echo $status == 'inactive' ? 'Ativar' : 'Renovar / trocar pacote'; ?></a></li>
                                                    <li><a class="dropdown-item" href="device_playlist.php?device=<?php echo $row['id']; ?>"><i class="ri-play-list-line me-1"></i>Playlist</a></li>
                                                    <?php if($status != 'inactive'){ ?>
                                                        <li>
                                                            <form method="post" onsubmit="return confirm('Desativar este dispositivo? Ele fica livre (sem revenda e sem listas) para ativar com qualquer código. Os créditos usados não são devolvidos.');">
                                                                <input type="hidden" name="device_pk" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" name="deactivate_submit" class="dropdown-item"><i class="ri-forbid-line me-1"></i>Desativar</button>
                                                            </form>
                                                        </li>
                                                    <?php } ?>
                                                    <li>
                                                        <form method="post">
                                                            <input type="hidden" name="device_pk" value="<?php echo $row['id']; ?>">
                                                            <?php if((int)$row['is_dev'] == 1){ ?>
                                                                <button type="submit" name="dev_toggle" value="2" class="dropdown-item"><i class="ri-user-line me-1"></i>Mover para dispositivos reais</button>
                                                            <?php } else { ?>
                                                                <button type="submit" name="dev_toggle" value="1" class="dropdown-item"><i class="ri-bug-line me-1"></i>Mover para Dispositivos dev</button>
                                                            <?php } ?>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger btn_delete" href="javascript:void(0)" data-id="<?php echo $row['id']; ?>" data-table="tbl_devices"><i class="ri-delete-bin-line me-1"></i>Excluir</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr><td colspan="11"><div class="dv-empty"><i class="ri-inbox-line"></i>Nenhum dispositivo encontrado</div></td></tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="dv-footer">
                        <span>Total: <?php echo thousandsNumberFormat($total_rows); ?></span>
                        <?php if($last_page > 1){ ?>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(dv_url(array('page' => $page - 1))); ?>">‹</a></li>
                                <?php for($p = max(1, $page - 2); $p <= min($last_page, $page + 2); $p++){ ?>
                                    <li class="page-item <?php if($p == $page){ echo 'active'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(dv_url(array('page' => $p))); ?>"><?php echo $p; ?></a></li>
                                <?php } ?>
                                <li class="page-item <?php if($page >= $last_page){ echo 'disabled'; } ?>"><a class="page-link" href="<?php echo htmlspecialchars(dv_url(array('page' => $page + 1))); ?>">›</a></li>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->
<script>
(function () {
    var form = document.getElementById('dvBulkForm'), all = document.getElementById('dvCheckAll');
    if (!form || !all) return;
    var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.dv-row-check')); };
    function sync() {
        var sel = boxes().filter(function (b) { return b.checked; });
        boxes().forEach(function (b) { b.closest('tr').classList.toggle('dv-selected', b.checked); });
        document.getElementById('dvBulkCount').textContent = sel.length;
        document.getElementById('dvBulkIds').value = sel.map(function (b) { return b.value; }).join(',');
        form.classList.toggle('show', sel.length > 0);
        all.checked = sel.length > 0 && sel.length === boxes().length;
        all.indeterminate = sel.length > 0 && sel.length < boxes().length;
    }
    all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; }); sync(); });
    document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('dv-row-check')) sync(); });
    document.getElementById('dvBulkClear').addEventListener('click', function () { boxes().forEach(function (b) { b.checked = false; }); sync(); });
    form.querySelectorAll('button[data-confirm]').forEach(function (b) {
        b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
    });
})();
</script>

<?php
    // Campos compartilhados pelos dois modais
    function dv_package_reseller_fields($packages, $resellers, $show_charge){ ?>
        <div class="mb-3">
            <label class="form-label">Pacote</label>
            <select name="package" class="form-select" required>
                <?php foreach($packages as $k => $p){ ?>
                    <option value="<?php echo $k; ?>" <?php if($k == '1y'){ echo 'selected'; } ?>>
                        <?php echo $p['label']; ?> — <?php echo $p['days'] > 0 ? $p['days'].' dias' : 'sem vencimento'; ?> · <?php echo $p['credits']; ?> crédito(s)
                    </option>
                <?php } ?>
            </select>
        </div>
        <?php if($show_charge){ ?>
        <div class="mb-0">
            <label class="form-label">Ativar como</label>
            <select name="reseller_id" class="form-select">
                <option value="0">Admin (sem custo de créditos)</option>
                <?php foreach($resellers as $r){ ?>
                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name'].' — '.$r['email']); ?> (<?php echo (int)$r['credits']; ?> créditos)</option>
                <?php } ?>
            </select>
            <small class="text-muted">Escolhendo um revendedor, os créditos do pacote são descontados do saldo dele.</small>
        </div>
        <?php } ?>
<?php } ?>

<!-- Modal: ativar / renovar -->
<div class="modal fade" id="activateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ativar / Renovar — <span id="activate_mac"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="device_pk" id="activate_pk">
                <div class="mb-3">
                    <label class="form-label">Cliente</label>
                    <input type="text" name="customer_name" id="activate_customer" class="form-control" maxlength="120" required placeholder="Nome do cliente">
                </div>
                <p class="text-muted small">Se o dispositivo ainda estiver válido, os dias do novo pacote são somados ao vencimento atual.</p>
                <?php dv_package_reseller_fields($packages, $resellers, $is_super_admin); ?>
                <div id="activate_owner_note" class="alert alert-info small mt-3 mb-0" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="activate_submit" class="btn btn-success">Ativar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: adicionar dispositivo -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ativação manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small d-flex gap-2 align-items-start">
                    <i class="ri-error-warning-line fs-5 lh-1"></i>
                    <div><b>Use só em casos específicos.</b> O normal é ativar pelo código de ativação no app. Use a ativação manual apenas quando o código apresentar algum erro.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cliente</label>
                    <input type="text" name="customer_name" class="form-control" maxlength="120" required placeholder="Nome do cliente">
                </div>
                <div class="mb-3">
                    <label class="form-label">MAC / Device ID</label>
                    <input type="text" name="device_id" id="add_device_id" class="form-control" maxlength="191" required placeholder="Ex.: D9:30:89:BB:51:D6" autocomplete="off" spellcheck="false" style="font-family:'Courier New',monospace;font-weight:700;letter-spacing:1px;">
                    <small class="text-muted">Digite só letras e números: os dois-pontos entram sozinhos.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">App</label>
                    <select name="application_id" class="form-select">
                        <?php if(count($app_ids) == 0){ ?><option value="">(padrão)</option><?php } ?>
                        <?php foreach($app_ids as $a){ ?>
                            <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($a); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php dv_package_reseller_fields($packages, $resellers, $is_super_admin); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="add_submit" class="btn btn-success">Ativar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: editar nome do cliente -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar nome do cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="device_pk" id="rename_pk">
                <div class="small text-muted mb-2">MAC: <b id="rename_mac" translate="no"></b></div>
                <label class="form-label">Cliente</label>
                <input type="text" name="customer_name" id="rename_name" class="form-control form-control-lg" maxlength="120" required placeholder="Nome do cliente">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="rename_submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<style>
    .dv-pencil { color: #3b6cf6; opacity: .55; font-size: 15px; text-decoration: none; }
    tr:hover .dv-pencil, .dv-pencil:focus { opacity: 1; }
</style>

<script>
// MAC: coloca os ":" sozinho (D9:30:89:BB:51:D6). Device IDs maiores ficam sem separador.
(function () {
    var inp = document.getElementById('add_device_id');
    if (!inp) return;
    inp.addEventListener('input', function () {
        var hex = inp.value.replace(/[\s:\-\.]/g, '');
        if (!/^[0-9a-fA-F]*$/.test(hex)) return; // não é MAC: deixa como está
        hex = hex.toUpperCase();
        inp.value = hex.length <= 12 ? (hex.match(/.{1,2}/g) || []).join(':') : hex;
    });
})();
document.addEventListener('click', function (e) {
    var ren = e.target.closest('.btn_rename');
    if (ren) {
        document.getElementById('rename_pk').value = ren.getAttribute('data-pk');
        document.getElementById('rename_mac').textContent = ren.getAttribute('data-mac');
        document.getElementById('rename_name').value = ren.getAttribute('data-name') || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('renameModal')).show();
        setTimeout(function () { document.getElementById('rename_name').focus(); }, 300);
        return;
    }
    var copy = e.target.closest('.dv-copy');
    if (copy) {
        var text = copy.getAttribute('data-copy');
        var done = function () { if (window.jQuery && $.notify) { $.notify('Copiado: ' + text, { position: 'top right', className: 'success' }); } };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
        else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
        return;
    }
    var act = e.target.closest('.btn_activate');
    if (act) {
        document.getElementById('activate_pk').value = act.getAttribute('data-pk');
        document.getElementById('activate_mac').textContent = act.getAttribute('data-mac');
        document.getElementById('activate_customer').value = act.getAttribute('data-customer') || '';
        // O aparelho fica com o dono do código digitado: só esse dono pode ativar/renovar
        var sel = document.querySelector('#activateModal select[name="reseller_id"]'), note = document.getElementById('activate_owner_note');
        var ow = act.getAttribute('data-owner') || '', rid = act.getAttribute('data-reseller') || '0';
        if (sel) {
            for (var i = 0; i < sel.options.length; i++) {
                var v = sel.options[i].value;
                sel.options[i].disabled = ow === '' ? false : (ow === 'reseller' ? v !== rid : v !== '0');
            }
            sel.value = ow === 'reseller' ? rid : '0';
        }
        if (note) {
            note.textContent = ow === '' ? '' : 'Este aparelho está ligado ao código de ' + (act.getAttribute('data-owner-name') || ow) + '. Só esse dono pode ativar. Para trocar, o cliente digita outro código no app ou reseta a licença.';
            note.style.display = ow === '' ? 'none' : 'block';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('activateModal')).show();
    }
});
</script>

<?php require_once("includes/devices_live.php"); devices_live_script("devices_sig"); ?>

<?php include("includes/footer.php");?>
