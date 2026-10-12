<?php
require_once(__DIR__."/_init.php");
$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
$now = time();

function rv_count($mysqli, $sql){ $r = mysqli_fetch_assoc(mysqli_query($mysqli, $sql)); return $r ? (int)$r['num'] : 0; }
$base = "FROM tbl_devices WHERE owner = 'reseller' AND reseller_id = $rid";
$total    = rv_count($mysqli, "SELECT COUNT(*) AS num $base");
$pending  = rv_count($mysqli, "SELECT COUNT(*) AS num $base AND package = ''");
$active   = rv_count($mysqli, "SELECT COUNT(*) AS num $base AND package <> '' AND (expires_at = 0 OR expires_at >= $now)");
$expiring = rv_count($mysqli, "SELECT COUNT(*) AS num $base AND package <> '' AND expires_at >= $now AND expires_at < ".($now + 30 * 86400));

$pending_list = mysqli_query($mysqli, "SELECT * $base AND package = '' ORDER BY linked_at DESC LIMIT 8");
// servidor parceiro: aparelhos ativos sem lista (o cliente ainda não enviou usuário e senha na página /lista)
if (rv_is_server()) {
    require_once(__DIR__."/../includes/playlist_helper.php");
    playlists_migrate($mysqli);
    $nolist = rv_count($mysqli, "SELECT COUNT(*) AS num $base AND package <> '' AND expires_at >= $now AND device_id NOT IN (SELECT device_id FROM tbl_users WHERE device_id IS NOT NULL)");
    $recent = mysqli_query($mysqli, "SELECT * $base AND package <> '' ORDER BY activated_at DESC LIMIT 8");
    $upload_page = rtrim(brand_mail_base_url(), '/').'/lista';
}

require_once(__DIR__."/../includes/playlist_helper.php");
$dns_offline = array();
foreach (dns_reseller_list($mysqli, $rid) as $d) { if (dns_days_until_delete($d) !== null) $dns_offline[] = $d; }

rv_header($mysqli, $reseller, 'Painel', 'dashboard'); ?>

<?php if ($dns_offline) { ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <i class="ri-alarm-warning-line fs-5"></i>
        <span>
            <b><?php echo count($dns_offline); ?> servidor(es) DNS offline.</b>
            <?php $first = $dns_offline[0]; $left = dns_days_until_delete($first); ?>
            "<?php echo rv_e($first['dns_title']); ?>" será excluído automaticamente em <b><?php echo $left; ?> dia(s)</b> se não voltar a responder.
        </span>
        <a href="dns.php" class="btn btn-sm btn-warning ms-auto">Ver servidores</a>
    </div>
<?php } ?>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="rv-card p-4 h-100 text-center">
            <div class="rv-muted mb-2">Seu código de ativação</div>
            <div class="rv-code"><?php echo rv_e($reseller['code']); ?></div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-copy="<?php echo rv_e($reseller['code']); ?>"><i class="ri-file-copy-line"></i> Copiar código</button>
            <?php if (rv_is_server()) { ?>
                <p class="rv-muted small mt-3 mb-0">Passe este código aos seus clientes. Ao digitar no aplicativo, o aparelho <b>ativa na hora</b> e usa 1 crédito (1 ano). Depois o cliente envia a lista na página abaixo.</p>
            <?php } else { ?>
                <p class="rv-muted small mt-3 mb-0">Passe este código ao seu cliente. Ao digitar no aplicativo, o aparelho dele aparece em <b>Dispositivos</b> para você ativar.</p>
            <?php } ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="row g-3 h-100">
            <div class="col-sm-6">
                <div class="rv-stat" style="--c:#16c784;--bg:#f3fdf7;--ibg:#ccf7dd;">
                    <div class="rv-stat__icon"><i class="ri-coin-fill"></i></div>
                    <div><div class="rv-stat__label">Créditos</div><div class="rv-stat__value"><?php echo (int)$reseller['credits']; ?></div></div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="rv-stat" style="--c:#f5b82e;--bg:#fffcf3;--ibg:#fff1cc;">
                    <div class="rv-stat__icon"><i class="ri-time-fill"></i></div>
                    <?php if (rv_is_server()) { ?>
                        <div><div class="rv-stat__label">Ativos sem lista</div><div class="rv-stat__value"><?php echo $nolist; ?></div></div>
                    <?php } else { ?>
                        <div><div class="rv-stat__label">Aguardando ativação</div><div class="rv-stat__value"><?php echo $pending; ?></div></div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="rv-stat" style="--c:#3b6cf6;--bg:#f7f9fe;--ibg:#e3eafd;">
                    <div class="rv-stat__icon"><i class="ri-checkbox-circle-fill"></i></div>
                    <div><div class="rv-stat__label">Ativos</div><div class="rv-stat__value"><?php echo $active; ?> <small class="rv-muted fs-6">/ <?php echo $total; ?></small></div></div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="rv-stat" style="--c:#f0769a;--bg:#fff7f9;--ibg:#ffe0e8;">
                    <div class="rv-stat__icon"><i class="ri-alarm-warning-fill"></i></div>
                    <div><div class="rv-stat__label">Vencem em 30 dias</div><div class="rv-stat__value"><?php echo $expiring; ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (rv_is_server()) { ?>
<div class="rv-card p-4 mb-4">
    <h2 class="rv-title fs-5 mb-1"><i class="ri-upload-cloud-2-line me-1"></i> Página para enviar a lista</h2>
    <p class="rv-muted small mb-3">Depois de ativar, o app mostra um QR code que leva a esta página. O cliente digita o ID do aparelho, o usuário e a senha, e o sistema confere só nas <b>suas DNS</b>. Você também pode mandar o endereço direto:</p>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <code class="px-3 py-2 rounded" style="background:#f4f6fb;font-size:16px" translate="no"><?php echo rv_e($upload_page); ?></code>
        <button type="button" class="btn btn-outline-primary btn-sm" data-copy="<?php echo rv_e($upload_page); ?>"><i class="ri-file-copy-line"></i> Copiar</button>
        <a href="<?php echo rv_e($upload_page); ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="ri-external-link-line"></i> Abrir</a>
    </div>
</div>
<div class="rv-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="rv-title fs-5">Ativados recentemente</h2>
        <a href="devices.php" class="btn btn-sm btn-outline-secondary">Ver todos</a>
    </div>
    <?php if (mysqli_num_rows($recent) > 0) { ?>
        <div class="table-responsive">
            <table class="table rv-table mb-0">
                <thead><tr><th>Mac</th><th>Modelo</th><th>Ativado em</th><th>Vence em</th></tr></thead>
                <tbody>
                <?php while ($d = mysqli_fetch_assoc($recent)) { ?>
                    <tr>
                        <td><?php echo rv_e($d['device_id']); ?></td>
                        <td><?php echo $d['device_model'] != '' ? rv_e($d['device_model']) : '<span class="rv-muted">—</span>'; ?></td>
                        <td><?php echo $d['activated_at'] ? date('d/m/Y H:i', $d['activated_at']) : '—'; ?></td>
                        <td><?php echo $d['expires_at'] ? date('d/m/Y', $d['expires_at']) : '—'; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="rv-empty"><i class="ri-inbox-line"></i>Nenhum aparelho ativado ainda. Passe seu código para os clientes.</div>
    <?php } ?>
</div>
<?php } else { ?>
<div class="rv-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="rv-title fs-5">Aguardando ativação</h2>
        <a href="devices.php?status=pending" class="btn btn-sm btn-outline-secondary">Ver todos</a>
    </div>
    <?php if (mysqli_num_rows($pending_list) > 0) { ?>
        <div class="table-responsive">
            <table class="table rv-table mb-0">
                <thead><tr><th>Mac</th><th>Modelo</th><th>Vinculado em</th><th></th></tr></thead>
                <tbody>
                <?php while ($d = mysqli_fetch_assoc($pending_list)) { ?>
                    <tr>
                        <td><?php echo rv_e($d['device_id']); ?></td>
                        <td><?php echo $d['device_model'] != '' ? rv_e($d['device_model']) : '<span class="rv-muted">—</span>'; ?></td>
                        <td><?php echo $d['linked_at'] ? date('d/m/Y H:i', $d['linked_at']) : '—'; ?></td>
                        <td><a href="devices.php?activate=<?php echo (int)$d['id']; ?>" class="btn btn-sm rv-btn-green"><i class="ri-flashlight-line"></i> Ativar</a></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="rv-empty"><i class="ri-inbox-line"></i>Nenhum aparelho aguardando. Passe seu código para os clientes.</div>
    <?php } ?>
</div>
<?php } ?>

<?php rv_footer(); ?>
