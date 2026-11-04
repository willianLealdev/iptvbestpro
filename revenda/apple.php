<?php
/**
 * Ativação Apple: iPhone, iPad e Apple TV não têm campo de código (regra da Apple).
 * O cliente vê no app o "ID do dispositivo" (8 números) e informa à revenda; a revenda ativa por ele aqui.
 */
require_once(__DIR__."/_init.php");

if (rv_is_server()) rv_redirect('dashboard.php');
$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
$packages = devices_packages();
$now = time();

$form = array('sid' => '', 'name' => '', 'package' => '1y');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apple_activate'])) {
    $form['sid'] = preg_replace('/\D/', '', isset($_POST['sid']) ? (string)$_POST['sid'] : '');
    $form['name'] = isset($_POST['customer_name']) ? trim((string)$_POST['customer_name']) : '';
    $form['package'] = isset($_POST['package']) ? (string)$_POST['package'] : '1y';
    // trava simples contra tentativa de adivinhar IDs: no máximo 10 erros em 10 minutos
    $fails = isset($_SESSION['rv_apple_fail']) && is_array($_SESSION['rv_apple_fail']) ? $_SESSION['rv_apple_fail'] : array();
    $fails = array_values(array_filter($fails, function ($t) use ($now) { return $t > $now - 600; }));
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (count($fails) >= 10) {
        rv_flash('error', 'Muitas tentativas com ID errado. Espere alguns minutos.');
    } else if (!preg_match('/^[0-9]{8}$/', $form['sid'])) {
        rv_flash('error', 'O ID do dispositivo tem 8 números. Confira o que aparece na tela do app.');
    } else if ($form['name'] == '') {
        rv_flash('error', 'Informe o nome do cliente.');
    } else {
        $dev = device_find_by_short_id($mysqli, $form['sid']);
        if (!$dev) {
            $fails[] = $now;
            rv_flash('error', 'ID não encontrado. Peça ao cliente para abrir o app no iPhone, iPad ou Apple TV e conferir os 8 números do ID do dispositivo.');
        } else {
            $r = devices_activate($mysqli, (int)$dev['id'], $form['package'], $rid, $reseller['email'], 0);
            if ($r['ok']) {
                devices_set_customer($mysqli, (int)$dev['id'], $form['name'], $rid);
                $form = array('sid' => '', 'name' => '', 'package' => '1y');
            }
            rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
        }
    }
    $_SESSION['rv_apple_fail'] = $fails;
    if ($form['sid'] == '') rv_redirect('apple.php');
}

// Aparelhos Apple desta revenda (para renovar: é só digitar o mesmo ID de novo)
$list = array();
$res = mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE owner = 'reseller' AND reseller_id = $rid AND platform IN ('iphone','ipad','apple_tv') ORDER BY activated_at DESC, id DESC LIMIT 30");
while ($res && $row = mysqli_fetch_assoc($res)) $list[] = $row;

rv_header($mysqli, $reseller, 'Ativação Apple', 'apple'); ?>

<div class="mb-4">
    <h2 class="rv-title mb-1"><i class="ri-apple-line me-1"></i>Ativação Apple</h2>
    <div class="rv-muted">Para iPhone, iPad e Apple TV. O cliente abre o app e vê o <b>ID do dispositivo</b> (8 números). Digite esse ID aqui para ativar.</div>
</div>

<div class="rv-card p-4 mb-4" style="max-width:640px">
    <form method="post" autocomplete="off">
        <?php echo rv_csrf_field(); ?>
        <div class="mb-3">
            <label class="form-label fw-semibold">ID do dispositivo (8 números)</label>
            <input type="text" name="sid" class="form-control form-control-lg" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required
                   value="<?php echo rv_e($form['sid']); ?>" placeholder="00000000" style="font-family:monospace;letter-spacing:4px;font-weight:700">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Nome do cliente</label>
            <input type="text" name="customer_name" class="form-control" maxlength="120" required value="<?php echo rv_e($form['name']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Período</label>
            <select name="package" class="form-select">
                <?php foreach ($packages as $key => $p) { ?>
                    <option value="<?php echo rv_e($key); ?>" <?php echo $form['package'] == $key ? 'selected' : ''; ?>>
                        <?php echo rv_e($p['label']); ?> (<?php echo (int)$p['credits'] > 0 ? (int)$p['credits'].' crédito(s)' : 'sem crédito'; ?>)
                    </option>
                <?php } ?>
            </select>
            <div class="rv-muted small mt-1">Você tem <b><?php echo (int)$reseller['credits']; ?></b> crédito(s).</div>
        </div>
        <button type="submit" name="apple_activate" value="1" class="btn rv-btn-green"><i class="ri-check-line me-1"></i>Ativar</button>
    </form>
</div>

<?php if ($list) { ?>
<div class="rv-card">
    <div class="p-3 pb-0 fw-semibold">Seus aparelhos Apple</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr class="rv-muted small"><th class="ps-4">ID do dispositivo</th><th>Cliente</th><th>Aparelho</th><th>Pacote</th><th>Vencimento</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($list as $d) {
                $st = devices_activation_status($d);
                $pk = devices_package_info($d['package']); ?>
                <tr>
                    <td class="ps-4"><span style="font-family:monospace;font-weight:700"><?php echo rv_e($d['short_id'] != '' ? $d['short_id'] : '—'); ?></span></td>
                    <td><?php echo $d['customer_name'] != '' ? rv_e($d['customer_name']) : '—'; ?></td>
                    <td><?php echo rv_e($d['device_model']); ?></td>
                    <td><?php echo $pk ? rv_e($pk['label']) : '—'; ?></td>
                    <td><?php echo (int)$d['expires_at'] > 0 ? date('d/m/Y', (int)$d['expires_at']) : '—'; ?></td>
                    <td><?php echo $st == 'active' ? '<span class="badge bg-success">Ativo</span>' : ($st == 'expired' ? '<span class="badge bg-danger">Vencido</span>' : '<span class="badge bg-secondary">Aguardando</span>'); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<?php rv_footer(); ?>
