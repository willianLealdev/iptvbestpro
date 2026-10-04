<?php $page_title="Planos de Assinatura";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/customer_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    customers_migrate($mysqli);

    function sp_flash($ok, $msg){
        $_SESSION['sp_msg'] = $msg;
        $_SESSION['msg'] = 'sp_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    function sp_money($v){ return number_format((float)str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', (string)$v)), 2, '.', ''); }

    if(isset($_POST['save_plan'])){
        $id = (int)$_POST['plan_id'];
        $name = mb_substr(trim(strip_tags($_POST['name'])), 0, 80);
        $months = max(1, min(120, (int)$_POST['months']));
        $brl = (float)sp_money($_POST['price_brl']);
        $usd = (float)sp_money($_POST['price_usd']);
        $desc = mb_substr(trim(strip_tags($_POST['description'])), 0, 255);
        $status = !empty($_POST['status']) ? 1 : 0;
        $sort = (int)$_POST['sort'];
        if($name == ''){
            sp_flash(false, 'Informe o nome do plano.');
        } else if($id > 0){
            $stmt = mysqli_prepare($mysqli, "UPDATE tbl_sub_plans SET name=?, months=?, price_brl=?, price_usd=?, description=?, status=?, sort=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'siddsiii', $name, $months, $brl, $usd, $desc, $status, $sort, $id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            sp_flash(true, 'Plano atualizado.');
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_sub_plans (name, months, price_brl, price_usd, description, status, sort) VALUES (?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'siddsii', $name, $months, $brl, $usd, $desc, $status, $sort);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            sp_flash(true, 'Plano criado.');
        }
        header("Location:manage_sub_plans.php");
        exit;
    }
    if(isset($_POST['delete_plan'])){
        $id = (int)$_POST['plan_id'];
        mysqli_query($mysqli, "DELETE FROM tbl_sub_plans WHERE id = $id");
        sp_flash(true, 'Plano excluído. Assinaturas já feitas não são afetadas.');
        header("Location:manage_sub_plans.php");
        exit;
    }

    if(isset($_SESSION['sp_msg'])){
        $client_lang['sp_custom'] = $_SESSION['sp_msg'];
        unset($_SESSION['sp_msg']);
    }
    $plans = sub_plans_list($mysqli, false);
    $edit = null;
    if(isset($_GET['edit'])){ foreach($plans as $p){ if((int)$p['id'] == (int)$_GET['edit']) $edit = $p; } }
    $portal_url = rtrim(getBaseUrl(), '/').'/cliente/';
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_customers.php">Clientes</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Planos</h5>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Plano</th><th>Duração</th><th class="text-end">Preço R$</th><th class="text-end">Preço US$</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                                <tbody>
                                <?php if($plans){ foreach($plans as $p){ ?>
                                    <tr>
                                        <td><b><?php echo htmlspecialchars($p['name']); ?></b><?php if($p['description'] != ''){ ?><br><small class="text-muted"><?php echo htmlspecialchars($p['description']); ?></small><?php } ?></td>
                                        <td><?php echo (int)$p['months']; ?> <?php echo (int)$p['months'] == 1 ? 'mês' : 'meses'; ?></td>
                                        <td class="text-end"><?php echo money_format_currency($p['price_brl'], 'BRL'); ?></td>
                                        <td class="text-end"><?php echo money_format_currency($p['price_usd'], 'USD'); ?></td>
                                        <td><?php echo (int)$p['status'] == 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Oculto</span>'; ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="manage_sub_plans.php?edit=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="ri-pencil-line"></i></a>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Excluir este plano?');">
                                                <input type="hidden" name="plan_id" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" name="delete_plan" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } } else { ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum plano.</td></tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            Clientes do <b>Brasil</b> veem o preço em <b>R$</b>; dos <b>demais países</b>, em <b>US$</b> (detectado pelo IP no cadastro; você pode trocar a moeda de um cliente em Clientes).
                            Planos com preço 0 na moeda do cliente aparecem como "Preço em breve".
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-4" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?php echo $edit ? 'Editar plano' : 'Novo plano'; ?></h5>
                        <form method="post">
                            <input type="hidden" name="plan_id" value="<?php echo $edit ? (int)$edit['id'] : 0; ?>">
                            <div class="mb-3"><label class="form-label">Nome</label><input type="text" name="name" class="form-control" maxlength="80" required value="<?php echo htmlspecialchars($edit ? $edit['name'] : ''); ?>" placeholder="Ex.: Anual"></div>
                            <div class="mb-3"><label class="form-label">Duração (meses)</label><input type="number" name="months" class="form-control" min="1" max="120" required value="<?php echo $edit ? (int)$edit['months'] : 12; ?>"></div>
                            <div class="row g-2 mb-3">
                                <div class="col-6"><label class="form-label">Preço em R$</label><input type="text" name="price_brl" class="form-control" value="<?php echo $edit ? number_format((float)$edit['price_brl'], 2, ',', '') : ''; ?>" placeholder="0,00"></div>
                                <div class="col-6"><label class="form-label">Preço em US$</label><input type="text" name="price_usd" class="form-control" value="<?php echo $edit ? number_format((float)$edit['price_usd'], 2, '.', '') : ''; ?>" placeholder="0.00"></div>
                            </div>
                            <div class="mb-3"><label class="form-label">Descrição</label><input type="text" name="description" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($edit ? $edit['description'] : ''); ?>" placeholder="Ex.: 1 aparelho por 12 meses"></div>
                            <div class="mb-3"><label class="form-label">Ordem de exibição</label><input type="number" name="sort" class="form-control" value="<?php echo $edit ? (int)$edit['sort'] : 0; ?>"></div>
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="status" id="plan_status" value="1" <?php if(!$edit || (int)$edit['status'] == 1){ echo 'checked'; } ?>><label class="form-check-label" for="plan_status">Plano visível para os clientes</label></div>
                            <button type="submit" name="save_plan" class="btn btn-primary w-100"><?php echo $edit ? 'Salvar alterações' : 'Criar plano'; ?></button>
                            <?php if($edit){ ?><a href="manage_sub_plans.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar edição</a><?php } ?>
                        </form>
                    </div>
                </div>
                <div class="card" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
                    <div class="card-body p-4">
                        <div class="small text-muted">Portal do cliente</div>
                        <a href="<?php echo htmlspecialchars($portal_url); ?>" target="_blank"><?php echo htmlspecialchars($portal_url); ?></a>
                        <p class="small text-muted mt-2 mb-0">As instruções de pagamento mostradas ao cliente são as mesmas de <a href="manage_credit_orders.php">Resellers → Pedidos de Créditos</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
