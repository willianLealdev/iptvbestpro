<?php 
    $page_title=(isset($_GET['dns_id'])) ? 'Edit DNS' : 'Create DNS';
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("thumbnail_images.class.php");
    
    $page_save=(isset($_GET['dns_id'])) ? 'Save' : 'Create';
    
    require_once("includes/playlist_helper.php");
    dns_health_migrate($mysqli);
    // Servidor repetido: o mesmo endereço não pode ser cadastrado duas vezes
    if(isset($_POST['submit'])){
        $__dup = dns_find_by_base($mysqli, trim($_POST['dns_base']), isset($_POST['dns_id']) ? (int)$_POST['dns_id'] : 0);
        if ($__dup) {
            $_SESSION['class'] = 'error';
            $_SESSION['msg'] = 'dns_duplicate';
            $_SESSION['dns_dup_name'] = html_entity_decode($__dup['dns_title'], ENT_QUOTES, 'UTF-8');
            header("Location:".(isset($_POST['dns_id']) ? "create_dns.php?dns_id=".(int)$_POST['dns_id'] : "create_dns.php?add=yes"));
            exit;
        }
    }

    if(isset($_POST['submit']) and isset($_GET['add'])){

        $base_url = htmlentities(trim($_POST['dns_base']));
        $data = array(
            'dns_title'  =>  cleanInput($_POST['dns_title']),
            'dns_base'  =>  $base_url
        );
        $qry = Insert('tbl_xui_dns',$data);
        
        $_SESSION['msg']="10";
        $_SESSION['class']='success';
        header( "Location:manage_dns.php");
        exit;
    }
    
    if(isset($_GET['dns_id'])){
        $qry="SELECT * FROM tbl_xui_dns where id='".$_GET['dns_id']."'";
        $result=mysqli_query($mysqli,$qry);
        $row=mysqli_fetch_assoc($result);
    }
    
    if(isset($_POST['submit']) and isset($_POST['dns_id'])){

        $base_url = htmlentities(trim($_POST['dns_base']));
        $data = array(
            'dns_title'  =>  cleanInput($_POST['dns_title']),
            'dns_base'  =>  $base_url
        );
        // endereço antigo (para levar junto as listas dos clientes que estão nele)
        $__old = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT dns_base FROM tbl_xui_dns WHERE id = ".(int)$_POST['dns_id']));
        $category_edit=Update('tbl_xui_dns', $data, "WHERE id = '".$_POST['dns_id']."'");
        // Trocou o endereço do servidor: todas as listas que usavam o endereço antigo passam para o novo na hora
        // (o app do cliente pega o novo ao abrir, ao voltar para a tela inicial e a cada 10 minutos)
        if ($__old) dns_rebase_playlists($mysqli, html_entity_decode($__old['dns_base'], ENT_QUOTES, 'UTF-8'), trim($_POST['dns_base']));
        
        $_SESSION['msg']="11";
        $_SESSION['class']='success';
        header( "Location:create_dns.php?dns_id=".$_POST['dns_id']);
        exit;
    }
?>

<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo (isset($page_title)) ? $page_title : "" ?></li>
            </ol>
        </nav>
            
        <div class="row g-4">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?=$page_title ?></h5>
                        <form action="" name="addeditcategory" method="POST" enctype="multipart/form-data">
                            <input  type="hidden" name="dns_id" value="<?=(isset($_GET['dns_id'])) ? $_GET['dns_id'] : ''?>" />
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">Title</label>
                                <div class="col-sm-10">
                                     <input type="text" name="dns_title" class="form-control" value="<?php if(isset($_GET['dns_id'])){echo htmlspecialchars((string)$row['dns_title'], ENT_QUOTES, 'UTF-8', false);}?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">http://url_here.com:port</label>
                                <div class="col-sm-10">
                                     <input type="text" name="dns_base" class="form-control" value="<?php if(isset($_GET['dns_id'])){echo htmlspecialchars((string)$row['dns_base'], ENT_QUOTES, 'UTF-8', false);}?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">&nbsp;</label>
                                <div class="col-sm-10">
                                    <button type="submit" name="submit" class="btn btn-primary" style="min-width: 120px;"><?=$page_save?></button>
                                </div>
                            </div>
                            
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->
    
<?php include("includes/footer.php");?> 