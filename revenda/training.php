<?php
require_once("_init.php");
require_once("../includes/training_helper.php");
$reseller = rv_require_login($mysqli);

// abriu o treinamento: a janela de boas-vindas não aparece mais
if ((int)$reseller['training_seen'] == 0) {
    mysqli_query($mysqli, "UPDATE tbl_resellers SET training_seen = 1 WHERE id = ".(int)$reseller['id']);
    $reseller['training_seen'] = 1;
}

rv_header($mysqli, $reseller, 'Treinamento', 'training'); ?>
<div class="rv-card p-4 mb-4">
    <h2 class="rv-title fs-5 mb-1"><i class="ri-play-circle-line me-1"></i> Treinamento do portal</h2>
    <p class="rv-muted mb-0">Vídeos curtos, passo a passo, para você aprender a usar o portal e os apps. Assista na ordem; ao terminar um vídeo, o próximo começa sozinho.</p>
</div>
<?php training_render('../'); ?>
<?php rv_footer(); ?>
