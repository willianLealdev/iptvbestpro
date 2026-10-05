<?php $page_title="Suporte";
    include("includes/header.php");
    require_once("includes/support_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    support_migrate($mysqli);
    $is_super = $_SESSION['admin_type'] == 3;
    $admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';

    function sp_flash($ok, $msg){ $_SESSION['sp_flash'] = array('ok' => $ok, 'msg' => $msg); }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $back = 'manage_support.php';
        if (isset($_POST['save_settings']) && $is_super) {
            $raw = isset($_POST['support_whatsapp']) ? trim($_POST['support_whatsapp']) : '';
            $phone = support_phone_clean($raw);
            $tg = trim(isset($_POST['support_telegram']) ? $_POST['support_telegram'] : '');
            if ($raw !== '' && $phone === '') {
                sp_flash(false, 'WhatsApp inválido. Use DDI + DDD + número, ex.: 55 11 99999-8888.');
            } else if ($tg !== '' && !preg_match('#^https://(t\.me|telegram\.me)/#i', $tg)) {
                sp_flash(false, 'Link do Telegram inválido. Use um link https://t.me/...');
            } else {
                panel_config_set($mysqli, 'support_whatsapp', $phone);
                panel_config_set($mysqli, 'support_note', trim(mb_substr(isset($_POST['support_note']) ? $_POST['support_note'] : '', 0, 200)));
                panel_config_set($mysqli, 'support_telegram', $tg);
                sp_flash(true, 'Configurações do suporte salvas.');
            }
        } else if (isset($_POST['reply'])) {
            $tid = (int)$_POST['ticket_id'];
            $r = support_reply_admin($mysqli, $tid, $admin_name, isset($_POST['body']) ? $_POST['body'] : '');
            sp_flash($r['ok'], $r['msg']);
            $back = 'manage_support.php?id='.$tid;
        } else if (isset($_POST['set_status'])) {
            $tid = (int)$_POST['ticket_id'];
            support_set_status($mysqli, $tid, $_POST['set_status'] == 'closed' ? 'closed' : 'open');
            sp_flash(true, $_POST['set_status'] == 'closed' ? 'Chamado encerrado.' : 'Chamado reaberto.');
            $back = 'manage_support.php?id='.$tid;
        }
        header("Location: ".$back);
        exit;
    }

    $flash = '';
    if (!empty($_SESSION['sp_flash'])) {
        $flash = $_SESSION['sp_flash'];
        unset($_SESSION['sp_flash']);
    }

    $view_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $ticket = $view_id ? support_ticket_get($mysqli, $view_id) : null;
    if ($ticket) support_mark_read($mysqli, $ticket['id'], 'admin');

    $filter = isset($_GET['f']) && in_array($_GET['f'], array('open', 'answered', 'closed', 'all'), true) ? $_GET['f'] : 'open';
    $where = $filter == 'all' ? '' : "WHERE t.status = '".$filter."'";
    $tickets = array();
    $res = mysqli_query($mysqli, "SELECT t.*, r.name AS reseller_name FROM tbl_support_tickets t LEFT JOIN tbl_resellers r ON r.id = t.reseller_id $where ORDER BY t.unread_admin DESC, t.updated_at DESC LIMIT 200");
    while ($res && $t = mysqli_fetch_assoc($res)) $tickets[] = $t;
    $counts = array('open' => 0, 'answered' => 0, 'closed' => 0);
    $res = mysqli_query($mysqli, "SELECT status, COUNT(*) AS n FROM tbl_support_tickets GROUP BY status");
    while ($res && $c = mysqli_fetch_assoc($res)) $counts[$c['status']] = (int)$c['n'];
?>

<style>
    .sp-msg { border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; max-width: 85%; white-space: pre-wrap; word-break: break-word; }
    .sp-msg--me { background: #eef3ff; margin-left: auto; }
    .sp-msg--reseller { background: #f8fafc; border: 1px solid #e5e9f2; }
    .sp-msg small { display: block; color: #6b7a99; margin-bottom: 4px; }
    .sp-row { cursor: pointer; }
    .sp-row.unread td { font-weight: 700; }
</style>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page">Suporte</li>
            </ol>
        </nav>

        <?php if ($flash) { ?>
            <div class="alert alert-<?php echo $flash['ok'] ? 'success' : 'danger'; ?>"><?php echo support_e($flash['msg']); ?></div>
        <?php } ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <?php if ($ticket) { $msgs = support_messages($mysqli, $ticket['id']); ?>
                    <div class="card">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <a href="manage_support.php" class="small text-decoration-none"><i class="ri-arrow-left-line"></i> Todos os chamados</a>
                                    <h5 class="fw-bold mt-1 mb-0">#<?php echo (int)$ticket['id']; ?> · <?php echo support_e($ticket['subject']); ?></h5>
                                    <small class="text-muted">Revenda: <b><?php echo support_e($ticket['reseller_name']); ?></b> · <?php echo support_e($ticket['reseller_email']); ?></small>
                                </div>
                                <?php echo support_status_badge($ticket['status']); ?>
                            </div>
                            <?php foreach ($msgs as $m) { $me = $m['sender'] == 'admin'; ?>
                                <div class="sp-msg <?php echo $me ? 'sp-msg--me' : 'sp-msg--reseller'; ?>">
                                    <small><?php echo support_e($me ? 'Você ('.$m['sender_name'].')' : $m['sender_name']); ?> · <?php echo date('d/m/Y H:i', $m['created_at']); ?></small><?php echo support_e($m['body']); ?></div>
                            <?php } ?>
                            <form method="post" class="mt-3">
                                <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                                <textarea name="body" class="form-control mb-2" rows="4" maxlength="5000" placeholder="Sua resposta (a revenda recebe aqui e por e-mail)" required></textarea>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="reply" class="btn btn-primary">Responder</button>
                                    <?php if ($ticket['status'] != 'closed') { ?>
                                        <button type="submit" name="set_status" value="closed" class="btn btn-outline-secondary" formnovalidate>Encerrar</button>
                                    <?php } else { ?>
                                        <button type="submit" name="set_status" value="open" class="btn btn-outline-secondary" formnovalidate>Reabrir</button>
                                    <?php } ?>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="card">
                        <div class="card-header py-3 px-4 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <h5 class="mb-0 fw-bold">Chamados das revendas</h5>
                            <div class="btn-group btn-group-sm">
                                <?php foreach (array('open' => 'Aguardando', 'answered' => 'Respondidos', 'closed' => 'Encerrados', 'all' => 'Todos') as $k => $label) { ?>
                                    <a href="manage_support.php?f=<?php echo $k; ?>" class="btn <?php echo $filter == $k ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $label; ?><?php if (isset($counts[$k]) && $counts[$k]) echo ' ('.$counts[$k].')'; ?></a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!$tickets) { ?>
                                <div class="text-center text-muted py-5"><i class="ri-chat-3-line" style="font-size:40px"></i><br>Nenhum chamado aqui.</div>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table mb-0 align-middle">
                                        <thead><tr><th class="ps-4">#</th><th>Revenda</th><th>Assunto</th><th>Status</th><th>Atualizado</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($tickets as $t) { ?>
                                            <tr class="sp-row <?php if ((int)$t['unread_admin']) echo 'unread'; ?>" onclick="location.href='manage_support.php?id=<?php echo (int)$t['id']; ?>'">
                                                <td class="ps-4"><?php echo (int)$t['id']; ?></td>
                                                <td><?php echo support_e($t['reseller_name']); ?></td>
                                                <td><?php echo support_e($t['subject']); ?><?php if ((int)$t['unread_admin']) echo ' <span class="badge bg-danger">nova</span>'; ?></td>
                                                <td><?php echo support_status_badge($t['status']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', $t['updated_at']); ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="fw-bold"><i class="ri-settings-3-line"></i> Configurações do suporte</h6>
                        <?php if ($is_super) { ?>
                            <form method="post">
                                <label class="form-label small fw-semibold mt-2">Seu WhatsApp de suporte</label>
                                <input type="text" name="support_whatsapp" class="form-control" placeholder="55 11 99999-8888" value="<?php echo support_e(panel_config_get($mysqli, 'support_whatsapp', '')); ?>">
                                <div class="form-text mb-2">Aparece no app para os <b>seus</b> clientes (sem revenda). Clientes de revenda veem o número da revenda.</div>
                                <label class="form-label small fw-semibold">Recado (opcional)</label>
                                <input type="text" name="support_note" class="form-control mb-2" maxlength="200" value="<?php echo support_e(panel_config_get($mysqli, 'support_note', '')); ?>">
                                <label class="form-label small fw-semibold">Canal do Telegram (painel da revenda)</label>
                                <input type="text" name="support_telegram" class="form-control" placeholder="https://t.me/..." value="<?php echo support_e(panel_config_get($mysqli, 'support_telegram', SUPPORT_TELEGRAM_DEFAULT)); ?>">
                                <div class="form-text mb-3">Botão "Canal Telegram" no topo do painel da revenda. Deixe em branco para esconder.</div>
                                <button type="submit" name="save_settings" class="btn btn-primary w-100">Salvar</button>
                            </form>
                        <?php } else { ?>
                            <p class="text-muted small mb-0">Somente o Super Admin altera estas configurações.</p>
                        <?php } ?>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold"><i class="ri-smartphone-line"></i> Mensagens dos clientes (app)</h6>
                        <p class="text-muted small mb-2">O que o cliente envia pelo app em Configurações → Suporte fica em <a href="manage_report.php">Reports</a>. Se o aparelho é de uma revenda, ela também recebe no painel dela.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
