<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/poster_helper.php");
require_once(__DIR__."/../includes/theme_helper.php");

$reseller = rv_require_login($mysqli);
$rid = (int)$reseller['id'];
posters_migrate($mysqli);
$allowed = theme_reseller_allowed($mysqli);
$brand = theme_reseller_row($mysqli, $rid);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $allowed) {
    if (!rv_csrf_ok()) {
        rv_flash('error', 'Sessão expirada. Tente novamente.');
    } else if (isset($_POST['upload_posters'])) {
        $r = posters_upload($mysqli, $rid, 'posters');
        rv_flash($r['added'] > 0 ? 'success' : 'error', ($r['added'] > 0 ? $r['added'].' pôster(es) enviado(s). ' : '').implode(' ', $r['errors']));
    } else if (isset($_POST['toggle_poster'])) {
        posters_toggle($mysqli, $rid, (int)$_POST['poster_id']);
        rv_flash('success', 'Pôster atualizado.');
    } else if (isset($_POST['delete_poster'])) {
        $ok = posters_delete($mysqli, $rid, (int)$_POST['poster_id']);
        rv_flash($ok ? 'success' : 'error', $ok ? 'Pôster excluído.' : 'Pôster não encontrado.');
    }
    rv_redirect('posters.php');
}

$mine = posters_list($mysqli, $rid);
$mine_active = 0;
foreach ($mine as $p) { if ((int)$p['status'] == 1) $mine_active++; }
$system = posters_list($mysqli, 0, true);

rv_header($mysqli, $reseller, 'Pôsteres', 'posters'); ?>

<?php if (!$allowed) { ?>
    <div class="rv-card p-4"><div class="rv-empty"><i class="ri-image-line"></i>A personalização de marca não está disponível no momento. Fale com o administrador.</div></div>
<?php } else { ?>

<div class="rv-card p-4 mb-4">
    <h1 class="rv-title mb-1">Pôsteres do MovieUI</h1>
    <p class="rv-muted mb-3">
        No tema <b>MovieUI</b>, o fundo do app é um pôster em tela cheia que vai trocando. Por padrão seus clientes veem os <b>pôsteres do sistema</b>.
        Se você enviar pôsteres e deixar pelo menos um como <b>"Aparece no app"</b>, seus clientes passam a ver <b>os seus</b>.
    </p>
    <div class="p-3 rounded mb-3" style="background:<?php echo $mine_active > 0 ? '#f6ffed' : '#f7f9fe'; ?>;">
        <?php if ((int)$brand['enabled'] != 1) { ?>
            <i class="ri-information-line"></i> Sua marca está <b>desligada</b>: seus clientes veem o visual padrão. Ligue em <a href="theme.php">Minha Marca</a> para usar seus pôsteres.
        <?php } else if ($mine_active > 0) { ?>
            <i class="ri-checkbox-circle-line text-success"></i> Seus clientes estão vendo <b>os seus <?php echo $mine_active; ?> pôster(es)</b>.
        <?php } else { ?>
            <i class="ri-information-line"></i> Seus clientes estão vendo os <b>pôsteres do sistema</b>.
        <?php } ?>
    </div>
    <?php posters_upload_form(rv_csrf_field()); ?>
    <h2 class="rv-title fs-5 mb-3">Meus pôsteres</h2>
    <?php posters_manager_render($mine, '../images/', rv_csrf_field()); ?>
</div>

<div class="rv-card p-4">
    <h2 class="rv-title fs-5 mb-1">Pôsteres do sistema (padrão)</h2>
    <p class="rv-muted small mb-3">Usados enquanto você não tiver pôsteres próprios aparecendo no app.</p>
    <?php posters_manager_render($system, '../images/', '', false); ?>
</div>
<?php } ?>

<?php rv_footer(); ?>
