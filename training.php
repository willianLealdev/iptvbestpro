<?php
    $page_title = 'Treinamento';
    include("includes/header.php");
    require_once("includes/training_helper.php");
?>

<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>
        <div class="card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-1"><i class="ri-play-circle-line me-1"></i> Treinamento do portal da revenda</h5>
                <p class="text-muted mb-0">Os mesmos vídeos que as revendas veem no portal delas (menu Treinamento). Revendas novas recebem uma janela pedindo para assistir no primeiro acesso.</p>
            </div>
        </div>
        <?php training_render(''); ?>
    </div>
</main>
<!-- End: main -->
<?php include("includes/footer.php");?>
