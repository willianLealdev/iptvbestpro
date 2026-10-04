<?php $page_title="Pôsteres do MovieUI";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/poster_helper.php");

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] == 0){
        header("Location:dashboard.php");
        exit;
    }
    posters_migrate($mysqli);

    function ps_flash($ok, $msg){
        $_SESSION['ps_msg'] = $msg;
        $_SESSION['msg'] = 'ps_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }

    if(isset($_POST['upload_posters'])){
        $r = posters_upload($mysqli, 0, 'posters');
        ps_flash($r['added'] > 0, ($r['added'] > 0 ? $r['added'].' pôster(es) enviado(s) e já aparecendo no app. ' : '').implode(' ', $r['errors']));
        header("Location:manage_movie_ui.php");
        exit;
    }
    if(isset($_POST['toggle_poster'])){
        posters_toggle($mysqli, 0, (int)$_POST['poster_id']);
        ps_flash(true, 'Pôster atualizado.');
        header("Location:manage_movie_ui.php");
        exit;
    }
    if(isset($_POST['delete_poster'])){
        $ok = posters_delete($mysqli, 0, (int)$_POST['poster_id']);
        ps_flash($ok, $ok ? 'Pôster excluído.' : 'Pôster não encontrado.');
        header("Location:manage_movie_ui.php");
        exit;
    }

    if(isset($_SESSION['ps_msg'])){
        $client_lang['ps_custom'] = $_SESSION['ps_msg'];
        unset($_SESSION['ps_msg']);
    }

    $rows = posters_list($mysqli, 0);
    $active = 0;
    foreach($rows as $r){ if((int)$r['status'] == 1) $active++; }
    $reseller_posters = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS num, COUNT(DISTINCT reseller_id) AS resellers FROM tbl_poster_gallery WHERE poster_type = 'movie_ui' AND reseller_id > 0"));
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <div class="card" style="border:0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="mb-0">Pôsteres do sistema</h5>
                    <span class="badge bg-success fs-6"><?php echo $active; ?> de <?php echo count($rows); ?> aparecendo no app</span>
                </div>
                <p class="text-muted small">
                    No tema <b>MovieUI</b>, o fundo do app é um pôster em tela cheia que vai trocando. Aqui você escolhe <b>quais pôsteres aparecem</b>:
                    use <b>Mostrar no app</b> / <b>Ocultar do app</b> em cada um. Estes pôsteres valem para os seus aparelhos, para os clientes de assinatura
                    e para os revendedores que não enviaram pôsteres próprios.
                    <?php if((int)$reseller_posters['num'] > 0){ ?><br><?php echo (int)$reseller_posters['resellers']; ?> revendedor(es) usam <?php echo (int)$reseller_posters['num']; ?> pôster(es) próprio(s).<?php } ?>
                </p>
                <?php posters_upload_form(''); ?>
                <?php posters_manager_render($rows, 'images/', ''); ?>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
