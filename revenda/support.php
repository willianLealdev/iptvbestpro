<?php
/**
 * Suporte da revenda:
 *  - Contato (WhatsApp) que aparece no app para os clientes desta revenda
 *  - Chamados com o admin (abrir, responder, encerrar)
 *  - Mensagens que os clientes enviaram pelo app
 */
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/support_helper.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
support_migrate($mysqli);
$reseller = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbl_resellers WHERE id = $rid"));

$view_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = isset($_GET['tab']) && $_GET['tab'] == 'app' ? 'app' : 'tickets';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $back = 'support.php';
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['save_contact'])) {
        $raw = isset($_POST['support_whatsapp']) ? trim($_POST['support_whatsapp']) : '';
        $phone = support_phone_clean($raw);
        $note = trim(mb_substr(isset($_POST['support_note']) ? $_POST['support_note'] : '', 0, 200));
        if ($raw !== '' && $phone === '') {
            rv_flash('error', 'Número inválido. Use o formato com DDI e DDD, ex.: 55 11 99999-8888.');
        } else {
            $stmt = mysqli_prepare($mysqli, "UPDATE tbl_resellers SET support_whatsapp = ?, support_note = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $phone, $note, $rid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            rv_flash('success', $phone !== '' ? 'Contato salvo. Seus clientes já veem este número no app (Configurações → Suporte).' : 'Contato removido do app.');
        }
    } else if (isset($_POST['new_ticket'])) {
        $r = support_ticket_create($mysqli, $reseller, isset($_POST['subject']) ? $_POST['subject'] : '', isset($_POST['body']) ? $_POST['body'] : '');
        rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
        if ($r['ok']) $back = 'support.php?id='.(int)$r['id'];
    } else if (isset($_POST['reply'])) {
        $tid = (int)$_POST['ticket_id'];
        $r = support_reply_reseller($mysqli, $reseller, $tid, isset($_POST['body']) ? $_POST['body'] : '');
        rv_flash($r['ok'] ? 'success' : 'error', $r['msg']);
        $back = 'support.php?id='.$tid;
    } else if (isset($_POST['close_ticket'])) {
        $tid = (int)$_POST['ticket_id'];
        support_set_status($mysqli, $tid, 'closed', $rid);
        rv_flash('success', 'Chamado encerrado.');
        $back = 'support.php?id='.$tid;
    } else if (isset($_POST['read_app'])) {
        mysqli_query($mysqli, "UPDATE tbl_reports SET is_read = 1 WHERE reseller_id = $rid");
        $back = 'support.php?tab=app';
    }
    rv_redirect($back);
}

$ticket = $view_id ? support_ticket_get($mysqli, $view_id, $rid) : null;
if ($ticket) support_mark_read($mysqli, $ticket['id'], 'reseller');

$tickets = array();
$res = mysqli_query($mysqli, "SELECT * FROM tbl_support_tickets WHERE reseller_id = $rid ORDER BY (status = 'closed'), updated_at DESC LIMIT 100");
while ($res && $t = mysqli_fetch_assoc($res)) $tickets[] = $t;

$app_msgs = array();
$res = mysqli_query($mysqli, "SELECT * FROM tbl_reports WHERE reseller_id = $rid ORDER BY id DESC LIMIT 100");
while ($res && $m = mysqli_fetch_assoc($res)) $app_msgs[] = $m;
$app_unread = 0;
foreach ($app_msgs as $m) if (!(int)$m['is_read']) $app_unread++;

rv_header($mysqli, $reseller, 'Suporte', 'support'); ?>

<style>
    .sp-msg { border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; max-width: 85%; white-space: pre-wrap; word-break: break-word; }
    .sp-msg--me { background: #eef3ff; margin-left: auto; }
    .sp-msg--admin { background: #f3fdf7; border: 1px solid #ccf7dd; }
    .sp-msg small { display: block; color: var(--rv-muted); margin-bottom: 4px; }
    .sp-row { cursor: pointer; }
    .sp-row.unread td { font-weight: 700; }
</style>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="rv-card p-4 mb-4">
            <h2 class="h5 fw-bold mb-1"><i class="ri-whatsapp-line text-success"></i> Contato para seus clientes</h2>
            <p class="rv-muted small mb-3">Este número aparece no app dos <b>seus</b> clientes em Configurações → Suporte. Sem número, o app pede para o cliente falar com a revenda.</p>
            <form method="post">
                <?php echo rv_csrf_field(); ?>
                <label class="form-label small fw-semibold">WhatsApp (com DDI e DDD)</label>
                <input type="text" name="support_whatsapp" class="form-control mb-3" placeholder="55 11 99999-8888" value="<?php echo rv_e($reseller['support_whatsapp']); ?>">
                <label class="form-label small fw-semibold">Recado (opcional)</label>
                <input type="text" name="support_note" class="form-control mb-3" maxlength="200" placeholder="Ex.: Atendimento das 9h às 22h" value="<?php echo rv_e($reseller['support_note']); ?>">
                <button type="submit" name="save_contact" class="btn rv-btn-green w-100">Salvar contato</button>
            </form>
        </div>

        <div class="rv-card p-4">
            <h2 class="h5 fw-bold mb-1"><i class="ri-customer-service-2-line text-primary"></i> Abrir chamado</h2>
            <p class="rv-muted small mb-3">Fale com o suporte da <?php echo rv_e(APP_NAME); ?>. A resposta chega aqui e no seu e-mail.</p>
            <form method="post">
                <?php echo rv_csrf_field(); ?>
                <input type="text" name="subject" class="form-control mb-2" maxlength="160" placeholder="Assunto" required>
                <textarea name="body" class="form-control mb-3" rows="5" maxlength="5000" placeholder="Descreva o que precisa" required></textarea>
                <button type="submit" name="new_ticket" class="btn btn-primary w-100">Enviar</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($ticket) { $msgs = support_messages($mysqli, $ticket['id']); ?>
            <div class="rv-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <a href="support.php" class="small text-decoration-none"><i class="ri-arrow-left-line"></i> Todos os chamados</a>
                        <h1 class="rv-title mt-1">#<?php echo (int)$ticket['id']; ?> · <?php echo rv_e($ticket['subject']); ?></h1>
                    </div>
                    <?php echo support_status_badge($ticket['status']); ?>
                </div>
                <?php foreach ($msgs as $m) { $me = $m['sender'] == 'reseller'; ?>
                    <div class="sp-msg <?php echo $me ? 'sp-msg--me' : 'sp-msg--admin'; ?>">
                        <small><?php echo $me ? 'Você' : rv_e('Suporte '.APP_NAME); ?> · <?php echo date('d/m/Y H:i', $m['created_at']); ?></small><?php echo rv_e($m['body']); ?></div>
                <?php } ?>
                <form method="post" class="mt-3">
                    <?php echo rv_csrf_field(); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                    <textarea name="body" class="form-control mb-2" rows="4" maxlength="5000" placeholder="<?php echo $ticket['status'] == 'closed' ? 'Escreva para reabrir o chamado' : 'Sua mensagem'; ?>" required></textarea>
                    <div class="d-flex gap-2">
                        <button type="submit" name="reply" class="btn btn-primary">Enviar</button>
                        <?php if ($ticket['status'] != 'closed') { ?>
                            <button type="submit" name="close_ticket" class="btn btn-outline-secondary" formnovalidate>Encerrar chamado</button>
                        <?php } ?>
                    </div>
                </form>
            </div>
        <?php } else { ?>
            <div class="rv-card p-4">
                <ul class="nav nav-pills mb-3">
                    <li class="nav-item"><a class="nav-link <?php if ($tab == 'tickets') echo 'active'; ?>" href="support.php">Meus chamados</a></li>
                    <li class="nav-item"><a class="nav-link <?php if ($tab == 'app') echo 'active'; ?>" href="support.php?tab=app">Mensagens dos clientes (app)<?php if ($app_unread) echo ' <span class="badge bg-danger">'.$app_unread.'</span>'; ?></a></li>
                </ul>
                <?php if ($tab == 'tickets') { ?>
                    <?php if (!$tickets) { ?>
                        <div class="rv-empty"><i class="ri-chat-3-line"></i>Nenhum chamado ainda.</div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table rv-table mb-0">
                                <thead><tr><th>#</th><th class="text-start">Assunto</th><th>Status</th><th>Atualizado</th></tr></thead>
                                <tbody>
                                <?php foreach ($tickets as $t) { ?>
                                    <tr class="sp-row <?php if ((int)$t['unread_reseller']) echo 'unread'; ?>" onclick="location.href='support.php?id=<?php echo (int)$t['id']; ?>'">
                                        <td><?php echo (int)$t['id']; ?></td>
                                        <td class="text-start"><?php echo rv_e($t['subject']); ?><?php if ((int)$t['unread_reseller']) echo ' <span class="badge bg-danger">nova resposta</span>'; ?></td>
                                        <td><?php echo support_status_badge($t['status']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', $t['updated_at']); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p class="rv-muted small">Mensagens que <b>seus clientes</b> enviaram pelo app (Configurações → Suporte). Responda pelo WhatsApp ou pelo contato que você tem do cliente.</p>
                    <?php if (!$app_msgs) { ?>
                        <div class="rv-empty"><i class="ri-smartphone-line"></i>Nenhuma mensagem dos clientes.</div>
                    <?php } else { ?>
                        <?php if ($app_unread) { ?>
                            <form method="post" class="mb-2"><?php echo rv_csrf_field(); ?><button type="submit" name="read_app" class="btn btn-sm btn-outline-secondary">Marcar todas como lidas</button></form>
                        <?php } ?>
                        <?php foreach ($app_msgs as $m) { ?>
                            <div class="border rounded-3 p-3 mb-2 <?php if (!(int)$m['is_read']) echo 'border-primary'; ?>">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <b><?php echo rv_e($m['report_title']); ?></b>
                                    <small class="rv-muted"><?php echo is_numeric($m['report_on']) ? date('d/m/Y H:i', (int)$m['report_on']) : rv_e($m['report_on']); ?></small>
                                </div>
                                <div style="white-space:pre-wrap"><?php echo rv_e($m['report_msg']); ?></div>
                                <small class="rv-muted">Aparelho: <?php echo rv_e($m['device_id']); ?><?php if ($m['device_model'] !== '') echo ' · '.rv_e($m['device_model']); ?><?php if ($m['user_name'] !== '') echo ' · Lista: '.rv_e($m['user_name']); ?></small>
                            </div>
                        <?php } ?>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<?php rv_footer(); ?>
