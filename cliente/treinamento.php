<?php
require_once(__DIR__."/_init.php");
require_once(__DIR__."/../includes/training_helper.php");
$customer = cl_require_login($mysqli);

cl_header($customer, 'Treinamento', 'training'); ?>

<div class="cl-card p-4 mb-4">
    <h1 class="cl-title fs-4 mb-1"><i class="ri-play-circle-line me-1"></i> Como usar</h1>
    <p class="cl-muted mb-0">Dois vídeos curtos: como ativar o seu aparelho e como colocar a sua lista. Ao terminar um, o próximo começa sozinho.</p>
</div>
<div class="cl-card p-3 mb-4">
    <?php training_render('../', 'customer'); ?>
</div>
<div class="cl-card p-4">
    <h2 class="cl-title fs-5 mb-2">Passo a passo rápido</h2>
    <ol class="mb-0" style="line-height:1.9">
        <li>Em <b>Minhas assinaturas</b>, copie o seu <b>código de ativação</b> (8 números).</li>
        <li>Abra o app no aparelho, digite o código e confirme em <b>Ativar</b>.</li>
        <li>Volte em <b>Minhas assinaturas</b> e clique em <b>Playlists deste aparelho</b>.</li>
        <li>Adicione a sua lista: escolha <b>Conta</b> (usuário e senha do seu fornecedor) ou <b>URL</b> (o link da lista).</li>
        <li>No app, aperte <b>Atualizar</b>: a sua lista aparece e é só assistir.</li>
    </ol>
</div>

<?php cl_footer(); ?>
