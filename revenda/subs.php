<?php
/**
 * Sub-revendas: a revenda cria outras revendas abaixo dela (cada uma também pode criar as suas),
 * escolhe se podem ter marca própria e envia ou retira créditos.
 */
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/account_auth.php");

if (rv_is_server()) rv_redirect('dashboard.php');
$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];

// A sub-revenda precisa ser MESMO desta revenda (filho direto)
function subs_mine($mysqli, $rid, $id){
    $sub = reseller_find($mysqli, (int)$id);
    return ($sub && (int)$sub['parent_id'] == (int)$rid) ? $sub : null;
}

$form = array('name' => '', 'email' => '', 'phone' => '', 'can_brand' => 0);
$open_create = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
        rv_redirect('subs.php');
    }
    if (isset($_POST['create_sub'])) {
        $form['name'] = isset($_POST['name']) ? (string)$_POST['name'] : '';
        $form['email'] = isset($_POST['email']) ? (string)$_POST['email'] : '';
        $form['phone'] = isset($_POST['phone']) ? (string)$_POST['phone'] : '';
        $form['can_brand'] = isset($_POST['can_brand']) && $_POST['can_brand'] == '1' ? 1 : 0;
        if (!acc_mail_rate_ok($mysqli, 'rv-sub-create', 30)) {
            rv_flash('error', 'Muitas criações em pouco tempo. Tente mais tarde.');
            rv_redirect('subs.php');
        }
        $r = subs_create($mysqli, $reseller, $form['name'], $form['email'], $form['phone'], $form['can_brand']);
        rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
        if ($r['ok']) rv_redirect('subs.php');
        $open_create = true; // erro: reabre o formulário com o que foi digitado
    } else if (isset($_POST['transfer_sub'])) {
        $sub = subs_mine($mysqli, $rid, isset($_POST['sub_id']) ? $_POST['sub_id'] : 0);
        if (!$sub) {
            rv_flash('error', 'Sub-revenda não encontrada.');
        } else {
            $dir = isset($_POST['direction']) && $_POST['direction'] == 'take' ? 'take' : 'give';
            $r = subs_transfer($mysqli, $rid, (int)$sub['id'], isset($_POST['amount']) ? (int)$_POST['amount'] : 0, $dir);
            rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
        }
        rv_redirect('subs.php');
    } else if (isset($_POST['toggle_brand'])) {
        $sub = subs_mine($mysqli, $rid, isset($_POST['sub_id']) ? $_POST['sub_id'] : 0);
        if ($sub) {
            $new = (int)$sub['can_brand'] ? 0 : 1;
            mysqli_query($mysqli, "UPDATE tbl_resellers SET can_brand = $new, updated_at = ".time()." WHERE id = ".(int)$sub['id']);
            rv_flash('success', $new ? $sub['name'].' agora pode ter a própria marca.' : $sub['name'].' agora usa a marca da sua revenda.');
        } else {
            rv_flash('error', 'Sub-revenda não encontrada.');
        }
        rv_redirect('subs.php');
    } else if (isset($_POST['toggle_status'])) {
        $sub = subs_mine($mysqli, $rid, isset($_POST['sub_id']) ? $_POST['sub_id'] : 0);
        if ($sub) {
            $new = (int)$sub['status'] == 1 ? 0 : 1;
            mysqli_query($mysqli, "UPDATE tbl_resellers SET status = $new, updated_at = ".time()." WHERE id = ".(int)$sub['id']);
            rv_flash('success', $new ? $sub['name'].' foi desbloqueada.' : $sub['name'].' foi bloqueada e não consegue mais entrar.');
        } else {
            rv_flash('error', 'Sub-revenda não encontrada.');
        }
        rv_redirect('subs.php');
    }
}

$subs = subs_list($mysqli, $rid);
rv_header($mysqli, $reseller, 'Sub-revendas', 'subs'); ?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h2 class="rv-title mb-1">Sub-revendas</h2>
        <div class="rv-muted">Crie revendas abaixo da sua. Os créditos delas vêm de você, e você pode enviar ou retirar quando quiser.</div>
    </div>
    <button type="button" class="btn rv-btn-green ms-auto" data-bs-toggle="modal" data-bs-target="#subCreate"><i class="ri-user-add-line me-1"></i>Criar revenda</button>
</div>

<?php if (!$subs) { ?>
    <div class="rv-card p-5 text-center">
        <i class="ri-team-line" style="font-size:46px;color:#8a96b0"></i>
        <h3 class="fs-5 mt-2">Você ainda não tem sub-revendas</h3>
        <p class="rv-muted mb-3">Clique em <b>Criar revenda</b>, preencha nome, e-mail e telefone, e a pessoa recebe o acesso por e-mail.</p>
    </div>
<?php } else { ?>
    <div class="rv-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="rv-muted small">
                        <th class="ps-4">Revenda</th>
                        <th>Créditos</th>
                        <th>Aparelhos</th>
                        <th>Subs abaixo</th>
                        <th>Marca própria</th>
                        <th>Situação</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($subs as $s) { ?>
                    <tr>
                        <td class="ps-4">
                            <b><?php echo rv_e($s['name']); ?></b><br>
                            <span class="rv-muted small"><?php echo rv_e($s['email']); ?><?php echo $s['phone'] != '' ? ' · '.rv_e($s['phone']) : ''; ?></span>
                            <?php if (!empty($s['must_change'])) { ?><br><span class="badge bg-warning text-dark">Ainda não entrou</span><?php } ?>
                        </td>
                        <td><b><?php echo (int)$s['credits']; ?></b></td>
                        <td><?php echo (int)$s['dev_total']; ?></td>
                        <td><?php echo (int)$s['sub_total']; ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <?php echo rv_csrf_field(); ?>
                                <input type="hidden" name="sub_id" value="<?php echo (int)$s['id']; ?>">
                                <button type="submit" name="toggle_brand" value="1" class="btn btn-sm <?php echo (int)$s['can_brand'] ? 'btn-success' : 'btn-outline-secondary'; ?>" title="Clique para mudar">
                                    <?php echo (int)$s['can_brand'] ? 'Sim' : 'Não (usa a sua)'; ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="d-inline">
                                <?php echo rv_csrf_field(); ?>
                                <input type="hidden" name="sub_id" value="<?php echo (int)$s['id']; ?>">
                                <button type="submit" name="toggle_status" value="1" class="btn btn-sm <?php echo (int)$s['status'] == 1 ? 'btn-outline-success' : 'btn-outline-danger'; ?>" title="Clique para mudar">
                                    <?php echo (int)$s['status'] == 1 ? 'Ativa' : 'Bloqueada'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-primary js-transfer" data-id="<?php echo (int)$s['id']; ?>" data-name="<?php echo rv_e($s['name']); ?>" data-credits="<?php echo (int)$s['credits']; ?>" data-dir="give"><i class="ri-send-plane-line me-1"></i>Enviar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-transfer" data-id="<?php echo (int)$s['id']; ?>" data-name="<?php echo rv_e($s['name']); ?>" data-credits="<?php echo (int)$s['credits']; ?>" data-dir="take"><i class="ri-arrow-go-back-line me-1"></i>Retirar</button>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
<?php } ?>

<!-- criar sub-revenda -->
<div class="modal fade" id="subCreate" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content" style="border:0;border-radius:16px">
            <?php echo rv_csrf_field(); ?>
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="ri-user-add-line me-1"></i>Criar revenda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" maxlength="120" required value="<?php echo rv_e($form['name']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo rv_e($form['email']); ?>" placeholder="revenda@email.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Telefone / WhatsApp</label>
                    <input type="tel" name="phone" class="form-control" required value="<?php echo rv_e($form['phone']); ?>" placeholder="+55 11 99999-9999">
                </div>
                <div class="p-3 rounded" style="background:#f7f9fe">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="can_brand" value="1" id="subBrand" <?php echo $form['can_brand'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="subBrand">Esta revenda pode alterar a logo e a marca?</label>
                    </div>
                    <div class="rv-muted small mt-2">
                        <b>Marcado:</b> ela pode colocar a própria logo, nome e cores no app dos clientes dela.<br>
                        <b>Desmarcado:</b> os clientes dela usam a marca que está ativa na sua revenda.
                    </div>
                </div>
                <div class="rv-muted small mt-3"><i class="ri-mail-send-line me-1"></i>Ela recebe um e-mail com uma senha provisória e precisa criar uma senha nova no primeiro acesso.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="create_sub" value="1" class="btn rv-btn-green"><i class="ri-check-line me-1"></i>Criar revenda</button>
            </div>
        </form>
    </div>
</div>

<!-- enviar / retirar créditos -->
<div class="modal fade" id="subTransfer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="post" class="modal-content" style="border:0;border-radius:16px">
            <?php echo rv_csrf_field(); ?>
            <input type="hidden" name="sub_id" id="tfId" value="">
            <input type="hidden" name="direction" id="tfDir" value="give">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="tfTitle">Enviar créditos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="rv-muted small mb-2" id="tfInfo"></div>
                <label class="form-label">Quantidade de créditos</label>
                <input type="number" name="amount" id="tfAmount" class="form-control form-control-lg" min="1" step="1" inputmode="numeric" required>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="transfer_sub" value="1" class="btn btn-primary" id="tfBtn">Enviar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var myCredits = <?php echo (int)$reseller['credits']; ?>;
    var m = new bootstrap.Modal(document.getElementById('subTransfer'));
    document.querySelectorAll('.js-transfer').forEach(function (b) {
        b.addEventListener('click', function () {
            var give = b.dataset.dir === 'give';
            document.getElementById('tfId').value = b.dataset.id;
            document.getElementById('tfDir').value = b.dataset.dir;
            document.getElementById('tfTitle').textContent = give ? 'Enviar créditos' : 'Retirar créditos';
            document.getElementById('tfBtn').textContent = give ? 'Enviar' : 'Retirar';
            document.getElementById('tfInfo').textContent = give
                ? 'Para ' + b.dataset.name + '. Você tem ' + myCredits + ' crédito(s).'
                : 'De ' + b.dataset.name + ', que tem ' + b.dataset.credits + ' crédito(s). Eles voltam para o seu saldo.';
            var a = document.getElementById('tfAmount');
            a.value = '';
            a.max = give ? myCredits : b.dataset.credits;
            m.show();
            setTimeout(function () { a.focus(); }, 300);
        });
    });
    <?php if ($open_create) { ?>try { new bootstrap.Modal(document.getElementById('subCreate')).show(); } catch (e) {}<?php } ?>
});
</script>

<?php rv_footer(); ?>
