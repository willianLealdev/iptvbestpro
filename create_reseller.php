<?php
    $is_edit = isset($_GET['reseller_id']);
    $page_title = $is_edit ? 'Editar Reseller' : 'Novo Reseller';
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }

    resellers_migrate($mysqli);

    $reseller = array('id' => 0, 'name' => '', 'email' => '', 'credits' => 0, 'status' => 1, 'remark' => '');
    if($is_edit){
        $rid = (int)$_GET['reseller_id'];
        $res = mysqli_query($mysqli, "SELECT * FROM tbl_resellers WHERE id = $rid");
        $found = $res ? mysqli_fetch_assoc($res) : null;
        if(!$found){
            header("Location:manage_resellers.php");
            exit;
        }
        $reseller = $found;
    }

    if(isset($_POST['submit'])){
        $name = mb_substr(trim(strip_tags($_POST['name'])), 0, 120);
        $email = mb_strtolower(trim($_POST['email']));
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $status = (isset($_POST['status']) && $_POST['status'] == '1') ? 1 : 0;
        $remark = mb_substr(trim(strip_tags($_POST['remark'])), 0, 2000);
        $phone = mb_substr(preg_replace('/[^0-9+()\- ]/', '', isset($_POST['phone']) ? $_POST['phone'] : ''), 0, 30);
        $now = time();

        $invalid = ($name == '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
                    || (!$is_edit && strlen($password) < 6)
                    || ($is_edit && $password != '' && strlen($password) < 6));

        $stmt = mysqli_prepare($mysqli, "SELECT id FROM tbl_resellers WHERE email = ? AND id <> ?");
        $self_id = (int)$reseller['id'];
        mysqli_stmt_bind_param($stmt, 'si', $email, $self_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $email_taken = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if($invalid){
            $_SESSION['msg'] = 'reseller_invalid';
            $_SESSION['class'] = 'error';
        } else if($email_taken){
            $_SESSION['msg'] = 'reseller_email_exists';
            $_SESSION['class'] = 'error';
        } else if(!$is_edit){
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $code = activation_code_generate($mysqli);
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_resellers (name, email, phone, password, code, credits, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,0,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sssssisii', $name, $email, $phone, $hash, $code, $status, $remark, $now, $now);
            mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($mysqli);
            mysqli_stmt_close($stmt);

            $initial = max(0, (int)$_POST['initial_credits']);
            if($new_id && $initial > 0){
                resellers_add_credits($mysqli, $new_id, $initial, 'Créditos iniciais', 'admin', isset($_SESSION['id']) ? $_SESSION['id'] : 0);
            }
            $_SESSION['msg'] = 'reseller_added';
            $_SESSION['class'] = 'success';
            header("Location:manage_resellers.php");
            exit;
        } else {
            if($password != ''){
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($mysqli, "UPDATE tbl_resellers SET name=?, email=?, phone=?, password=?, status=?, remark=?, updated_at=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssisii', $name, $email, $phone, $hash, $status, $remark, $now, $self_id);
            } else {
                $stmt = mysqli_prepare($mysqli, "UPDATE tbl_resellers SET name=?, email=?, phone=?, status=?, remark=?, updated_at=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssisii', $name, $email, $phone, $status, $remark, $now, $self_id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['msg'] = 'reseller_updated';
            $_SESSION['class'] = 'success';
            header("Location:create_reseller.php?reseller_id=".$self_id);
            exit;
        }

        // Mantém o que foi digitado quando houver erro
        $reseller['name'] = $name;
        $reseller['email'] = $email;
        $reseller['status'] = $status;
        $reseller['remark'] = $remark;
        $reseller['phone'] = $phone;
    }
    if(!isset($reseller['phone'])){ $reseller['phone'] = ''; }

    // Histórico de créditos (edição)
    $history = null;
    if($is_edit){
        $history = mysqli_query($mysqli, "SELECT * FROM tbl_reseller_credits WHERE reseller_id = ".(int)$reseller['id']." ORDER BY id DESC LIMIT 20");
    }
?>
<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_resellers.php">Resellers</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="<?php echo $is_edit ? 'col-lg-7' : 'col-12'; ?>">
                <div class="card h-100" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
                    <div class="card-body p-4">
                        <h5 class="mb-4"><?php echo $page_title; ?></h5>
                        <form action="" method="POST" autocomplete="off">
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">Nome</label>
                                <div class="col-sm-9">
                                    <input type="text" name="name" maxlength="120" value="<?php echo htmlspecialchars($reseller['name']); ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">E-mail</label>
                                <div class="col-sm-9">
                                    <input type="email" name="email" maxlength="191" value="<?php echo htmlspecialchars($reseller['email']); ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">WhatsApp</label>
                                <div class="col-sm-9">
                                    <input type="tel" name="phone" maxlength="30" value="<?php echo htmlspecialchars($reseller['phone']); ?>" class="form-control" placeholder="+55 11 99999-9999">
                                </div>
                            </div>
                            <?php if($is_edit){ ?>
                                <div class="mb-3 row">
                                    <label class="col-sm-3 col-form-label">Código de ativação</label>
                                    <div class="col-sm-9">
                                        <div class="form-control-plaintext" style="font-family:'Courier New',monospace;font-size:22px;font-weight:700;letter-spacing:4px;color:#3b6cf6;"><?php echo htmlspecialchars((string)$reseller['code']); ?></div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">Senha</label>
                                <div class="col-sm-9">
                                    <input type="password" name="password" minlength="6" class="form-control" autocomplete="new-password" <?php echo $is_edit ? 'placeholder="Deixe em branco para manter a senha atual"' : 'required'; ?>>
                                    <small class="text-muted">Mínimo 6 caracteres. A senha é guardada criptografada.</small>
                                </div>
                            </div>
                            <?php if(!$is_edit){ ?>
                                <div class="mb-3 row">
                                    <label class="col-sm-3 col-form-label">Créditos iniciais</label>
                                    <div class="col-sm-9">
                                        <input type="number" name="initial_credits" min="0" step="1" value="0" class="form-control">
                                    </div>
                                </div>
                            <?php } else { ?>
                                <div class="mb-3 row">
                                    <label class="col-sm-3 col-form-label">Créditos</label>
                                    <div class="col-sm-9">
                                        <div class="form-control-plaintext fw-bold"><?php echo thousandsNumberFormat($reseller['credits']); ?>
                                            <small class="text-muted fw-normal">(altere pelo botão <i class="ri-coin-line"></i> na lista de Resellers)</small>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">Status</label>
                                <div class="col-sm-9">
                                    <select name="status" class="form-select">
                                        <option value="1" <?php if($reseller['status'] == 1){ echo 'selected'; } ?>>Ativo</option>
                                        <option value="0" <?php if($reseller['status'] == 0){ echo 'selected'; } ?>>Bloqueado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">Observação</label>
                                <div class="col-sm-9">
                                    <textarea name="remark" rows="3" class="form-control" placeholder="Anotações internas sobre este revendedor"><?php echo htmlspecialchars((string)$reseller['remark']); ?></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-9 offset-sm-3">
                                    <button type="submit" name="submit" class="btn btn-primary" style="min-width:120px;"><?php echo $is_edit ? 'Salvar' : 'Criar'; ?></button>
                                    <a href="manage_resellers.php" class="btn btn-outline-secondary ms-2">Voltar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if($is_edit){ ?>
                <div class="col-lg-5">
                    <div class="card h-100" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
                        <div class="card-body p-4">
                            <h5 class="mb-4">Histórico de Créditos</h5>
                            <?php if($history && mysqli_num_rows($history) > 0){ ?>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead><tr><th>Data</th><th class="text-end">Valor</th><th class="text-end">Saldo</th></tr></thead>
                                        <tbody>
                                        <?php while($h = mysqli_fetch_assoc($history)){ ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', $h['created_at']); ?>
                                                    <?php if($h['note'] != ''){ ?><br><small class="text-muted"><?php echo htmlspecialchars($h['note']); ?></small><?php } ?>
                                                </td>
                                                <td class="text-end fw-bold" style="color: <?php echo $h['amount'] > 0 ? '#16a34a' : '#dc2626'; ?>"><?php echo $h['amount'] > 0 ? '+'.$h['amount'] : $h['amount']; ?></td>
                                                <td class="text-end"><?php echo $h['balance_after']; ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted text-center py-4 mb-0">Nenhuma movimentação ainda.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</main>
<!-- End: main -->

<?php include("includes/footer.php");?>
