<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/playlist_helper.php");
require_once(__DIR__."/../includes/dns_alert_helper.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
dns_health_migrate($mysqli);
dns_alert_migrate($mysqli);

// Enquanto a revenda digita o endereço: diz se o servidor já existe no sistema (para reaproveitar, sem repetir)
if (isset($_GET['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)$_GET['check']);
    $out = array('found' => false);
    if (dns_normalize($q) != '') {
        $ex = dns_find_by_base($mysqli, $q);
        if ($ex) {
            $users = (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_dns_share WHERE dns_id = ".(int)$ex['id']))['n'] + ((int)$ex['reseller_id'] > 0 ? 1 : 0);
            $mine = (int)$ex['reseller_id'] == $rid || (int)mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n FROM tbl_dns_share WHERE dns_id = ".(int)$ex['id']." AND reseller_id = $rid"))['n'] > 0;
            $out = array('found' => true, 'title' => html_entity_decode($ex['dns_title'], ENT_QUOTES, 'UTF-8'),
                         'admin' => (int)$ex['reseller_id'] == 0, 'users' => $users, 'mine' => $mine);
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['add_dns'])) {
        $r = dns_reseller_add($mysqli, $rid, isset($_POST['dns_title']) ? $_POST['dns_title'] : '', isset($_POST['dns_base']) ? $_POST['dns_base'] : '');
        rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } else if (isset($_POST['delete_dns'])) {
        $ok = dns_reseller_delete($mysqli, $rid, $_POST['dns_id']);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Servidor removido.' : 'Servidor não encontrado.');
    } else if (isset($_POST['toggle_dns'])) {
        $ok = dns_reseller_toggle($mysqli, $rid, $_POST['dns_id']);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Status do servidor alterado.' : 'Servidor não encontrado.');
    } else if (isset($_POST['toggle_alerts'])) {
        $on = !empty($_POST['dns_alerts']) ? 1 : 0;
        mysqli_query($mysqli, "UPDATE tbl_resellers SET dns_alerts = $on WHERE id = $rid");
        rv_flash('success', $on ? 'Alertas por e-mail ligados.' : 'Alertas por e-mail desligados.');
    } else if (isset($_POST['check_now'])) {
        dns_health_check_all($mysqli, 4, $rid);
        rv_flash('success', 'Servidores verificados.');
    }
    rv_redirect('dns.php');
}

$rows = dns_reseller_list($mysqli, $rid);
$offline = array();
foreach ($rows as $r) { if (dns_days_until_delete($r) !== null) $offline[] = $r; }

function rv_dns_ago($ts){
    if (!$ts) return 'nunca verificado';
    $s = time() - (int)$ts;
    if ($s < 60) return 'agora';
    if ($s < 3600) return 'há '.floor($s / 60).' min';
    if ($s < 86400) return 'há '.floor($s / 3600).' h';
    return 'há '.floor($s / 86400).' dia(s)';
}

rv_header($mysqli, $reseller, 'DNS', 'dns'); ?>

<?php if ($offline) { ?>
    <div class="alert alert-warning">
        <b><i class="ri-alarm-warning-line"></i> Servidores offline</b><br>
        Servidores offline por mais de <?php echo DNS_OFFLINE_DELETE_DAYS; ?> dias seguidos são <b>excluídos automaticamente</b>.
        <ul class="mb-0 mt-2">
            <?php foreach ($offline as $o) { $left = dns_days_until_delete($o); ?>
                <li><b><?php echo rv_e($o['dns_title']); ?></b> — offline desde <?php echo date('d/m/Y H:i', $o['offline_since']); ?> ·
                    <?php echo $left > 0 ? 'será excluído em <b>'.$left.' dia(s)</b>' : '<b>será excluído hoje</b>'; ?></li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="rv-card p-4">
            <h2 class="rv-title fs-5 mb-3">Cadastrar servidor</h2>
            <form method="post">
                <?php echo rv_csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="dns_title" class="form-control" maxlength="120" required placeholder="Ex.: Servidor 1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Endereço (DNS)</label>
                    <input type="text" name="dns_base" id="dnsBase" class="form-control" maxlength="255" required placeholder="http://servidor.com:8080" autocomplete="off">
                </div>
                <div id="dnsFound" class="small mb-3" style="display:none;border-radius:10px;padding:10px 12px;background:#fff7e6;border:1px solid #f5d38a;"></div>
                <button type="submit" name="add_dns" class="btn rv-btn-green w-100"><i class="ri-add-line"></i> Cadastrar</button>
            </form>
            <p class="rv-muted small mt-3 mb-0">
                Cada servidor existe uma vez só no sistema: se outra revenda já cadastrou o mesmo endereço, ele é reaproveitado e fica compartilhado (não cria outro igual).
                Nas playlists cadastradas por usuário e senha, o sistema procura sozinho qual servidor aceita o login.
            </p>
        </div>
        <script>
        (function () {
            var inp = document.getElementById('dnsBase'), box = document.getElementById('dnsFound'), title = document.querySelector('input[name=dns_title]'), t = null;
            if (!inp || !box) return;
            function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
            inp.addEventListener('input', function () {
                clearTimeout(t);
                var v = inp.value.trim();
                if (v.length < 4) { box.style.display = 'none'; return; }
                t = setTimeout(function () {
                    fetch('dns.php?check=' + encodeURIComponent(v), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
                        if (!j.found) { box.style.display = 'none'; return; }
                        var msg;
                        if (j.mine) msg = '<i class="ri-information-line"></i> Este servidor já está na sua lista como <b>' + esc(j.title) + '</b>.';
                        else if (j.admin) msg = '<i class="ri-information-line"></i> Este servidor já está no sistema como <b>' + esc(j.title) + '</b> e já vale para os seus clientes. Não precisa cadastrar.';
                        else msg = '<i class="ri-share-line"></i> Este servidor já existe no sistema como <b>' + esc(j.title) + '</b> (usado por ' + j.users + ' revenda' + (j.users == 1 ? '' : 's') + '). Ao cadastrar, ele entra na sua lista compartilhado, sem criar outro igual.';
                        box.innerHTML = msg; box.style.display = 'block';
                        if (title && !title.value) title.value = j.title;
                    }).catch(function () {});
                }, 450);
            });
        })();
        </script>
        <?php $alerts_on = !isset($reseller['dns_alerts']) || (int)$reseller['dns_alerts'] == 1; ?>
        <div class="rv-card p-4 mt-4">
            <form method="post">
                <?php echo rv_csrf_field(); ?>
                <input type="hidden" name="toggle_alerts" value="1">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <h2 class="rv-title fs-6 mb-0"><i class="ri-mail-send-line"></i> Alerta por e-mail</h2>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="dns_alerts" value="1" <?php if ($alerts_on) { echo 'checked'; } ?> onchange="this.form.submit()">
                    </div>
                </div>
                <p class="rv-muted small mt-2 mb-0">Quando um servidor seu ficar offline, você recebe um e-mail em <b translate="no"><?php echo rv_e($reseller['email']); ?></b>, e outro quando ele voltar.</p>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="rv-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="rv-title fs-5 mb-0">Meus servidores (<?php echo count($rows); ?>/<?php echo DNS_MAX_PER_RESELLER; ?>)</h2>
                <form method="post">
                    <?php echo rv_csrf_field(); ?>
                    <button type="submit" name="check_now" class="btn btn-sm btn-outline-secondary"><i class="ri-refresh-line"></i> Verificar agora</button>
                </form>
            </div>
            <?php if ($rows) { ?>
                <div class="row g-3">
                    <?php foreach ($rows as $d) {
                        $h = $d['health'];
                        $dot = $h == 'online' ? '#16a34a' : ($h == 'offline' ? '#dc2626' : '#9ca3af');
                        $label = $h == 'online' ? 'Online' : ($h == 'offline' ? 'Offline' : 'Não verificado');
                        $left = dns_days_until_delete($d); ?>
                        <div class="col-md-6">
                            <div class="p-3 rounded h-100" style="border:1px solid #eef0f4;<?php echo (int)$d['status'] != 1 ? 'opacity:.6;' : ''; ?>">
                                <div class="d-flex align-items-center gap-2 fw-semibold">
                                    <span style="width:12px;height:12px;border-radius:50%;background:<?php echo $dot; ?>;flex:0 0 12px;box-shadow:0 0 0 3px <?php echo $dot; ?>33;"></span>
                                    <?php echo rv_e($d['dns_title']); ?>
                                    <?php if ((int)$d['users'] > 1) { ?><span class="dv-badge pk-trial ms-auto" title="O mesmo servidor é usado por outras revendas (existe uma vez só no sistema)"><i class="ri-share-line"></i> Compartilhado · <?php echo (int)$d['users']; ?> revendas</span><?php } ?>
                                    <?php if ((int)$d['status'] != 1) { ?><span class="dv-badge pk-none <?php echo (int)$d['users'] > 1 ? '' : 'ms-auto'; ?>">Desligado</span><?php } ?>
                                </div>
                                <div class="rv-muted small mt-1 text-break"><?php echo rv_e(html_entity_decode($d['dns_base'], ENT_QUOTES, 'UTF-8')); ?></div>
                                <div class="small mt-1" style="color:<?php echo $dot; ?>;">
                                    <b><?php echo $label; ?></b><?php if ($h == 'online' && (int)$d['latency_ms'] > 0) { ?> · <?php echo (int)$d['latency_ms']; ?> ms<?php } ?>
                                    <span class="rv-muted"> · <?php echo rv_dns_ago($d['health_at']); ?></span>
                                </div>
                                <?php if ($left !== null) { ?>
                                    <div class="small text-danger mt-1"><i class="ri-error-warning-line"></i> Exclusão automática em <?php echo $left; ?> dia(s)</div>
                                <?php } ?>
                                <div class="d-flex gap-2 mt-3">
                                    <?php if (!empty($d['is_owner'])) { ?>
                                    <form method="post">
                                        <?php echo rv_csrf_field(); ?>
                                        <input type="hidden" name="dns_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" name="toggle_dns" class="btn btn-sm btn-outline-primary"><?php echo (int)$d['status'] == 1 ? 'Desligar' : 'Ligar'; ?></button>
                                    </form>
                                    <?php } ?>
                                    <form method="post" onsubmit="return confirm('Remover este servidor da sua lista?');">
                                        <?php echo rv_csrf_field(); ?>
                                        <input type="hidden" name="dns_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" name="delete_dns" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i> Remover</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="rv-empty"><i class="ri-server-line"></i>Nenhum servidor cadastrado ainda.</div>
            <?php } ?>
        </div>
    </div>
</div>

<?php rv_footer(); ?>
