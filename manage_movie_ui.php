<?php $page_title="Manage Poster";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    
    $tableName="tbl_poster_gallery";   
    $targetpage = "manage_movie_ui.php"; 
    $limit = 12; 
    $keyword='';

    $query = "SELECT COUNT(*) as num FROM $tableName WHERE tbl_poster_gallery.poster_type ='movie_ui'";
    $total_pages = mysqli_fetch_array(mysqli_query($mysqli,$query));
    $total_pages = $total_pages['num'];
    
    $stages = 3;
    $page=0;
    if(isset($_GET['page'])){
        $page = mysqli_real_escape_string($mysqli,$_GET['page']);
    }
    if($page){
        $start = ($page - 1) * $limit; 
    }else{
        $start = 0; 
    } 
    
    $sql_query="SELECT * FROM tbl_poster_gallery WHERE tbl_poster_gallery.poster_type ='movie_ui' ORDER BY tbl_poster_gallery.`id` DESC LIMIT $start, $limit"; 
    $result=mysqli_query($mysqli,$sql_query) or die(mysqli_error($mysqli));
    
    
    if(isset($_POST['submit'])){
        
        if($_FILES['poster_image']['name']!=""){
            
            $ext = pathinfo($_FILES['poster_image']['name'], PATHINFO_EXTENSION);
            $poster_image=rand(0,99999)."_poster.".$ext;
            $tpath1='images/'.$poster_image;
            
            if($ext!='png')  {
                $pic1=compress_image($_FILES["poster_image"]["tmp_name"], $tpath1, 80);
            } else {
                $tmp = $_FILES['poster_image']['tmp_name'];
                move_uploaded_file($tmp, $tpath1);
            }
            
            $data = array(
                'poster_type'  =>  'movie_ui',
                'poster_image'  =>  $poster_image
            );  
            
            $qry = Insert('tbl_poster_gallery',$data);
            
            $_SESSION['msg']="10";
            $_SESSION['class']='success';
            header( "Location:manage_movie_ui.php");
            exit;
        }
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
            
        <div class="card h-100">
            <div class="card-header d-md-inline-flex align-items-center justify-content-between py-3 px-4">
                <a href="<?php echo (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : "dashboard.php" ?>" class="d-inline-flex align-items-center text-decoration-none fw-semibold nsofts-link">
                    <i class="ri-arrow-left-line text-danger fw-bold"></i>
                    <span class="ps-2 lh-1"><?=$page_title ?></span>
                </a>
                <div class="d-flex mt-2 mt-md-0">
                    
                </div>
            </div>
            <div class="card-body p-4">
                
                <form action="" name="addeditcategory" method="POST" enctype="multipart/form-data">
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">Select Image</label>
                        <div class="col-sm-10">
                            <input type="file" class="form-control-file" name="poster_image"   accept=".png, .jpg, .JPG .PNG" onchange="fileValidation()" id="fileupload" <?php if(!isset($_GET['cat_id'])){?>required<?php } ?>>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">&nbsp;</label>
                        <div class="col-sm-10">
                            <button type="submit" name="submit" class="btn btn-primary" style="min-width: 120px;">Create Poster</button>
                        </div>
                    </div>
                </form>
            
                <?php if(mysqli_num_rows($result) > 0){ ?>
                    <div class="row g-4">
                        <?php $i=0; while($row=mysqli_fetch_array($result)) { ?>
                            <div class="col-lg-6 col-sm-6 col-xs-12">
                                <div class="nsofts-image-card">
                                    <div class="nsofts-image-card__cover" style="width: 100%; height: 250px; min-width: 100%; min-height: 100%; max-width: 100%;">
                                        <div class="nsofts-switch d-flex align-items-center enable_disable" data-bs-toggle="tooltip" data-bs-placement="top" title="Enable / Disable">
                                            <input type="checkbox" id="enable_disable_check_<?= $i ?>" data-id="<?= $row['id'] ?>" data-table="<?=$tableName ?>" data-column="status" class="cbx hidden btn_enable_disable" <?php if ($row['status'] == 1) { echo 'checked'; } ?>>
                                            <label for="enable_disable_check_<?= $i ?>" class="nsofts-switch__label"></label>
                                        </div>
                                        <img src="images/<?=$row['poster_image']?>" alt="" >
                                    </div>
                                    <div class="nsofts-image-card__content">
                                        <div class="position-relative">
                                            <div class="d-flex align-items-center justify-content-between nsofts-image-card__content__text">
                                                <span class="d-block text-truncate fs-6 fw-semibold pe-2"><?php echo $row['banner_title'];?></span>
                                                <div class="nsofts-image-card__option d-flex">
                                                    <a href="javascript:void(0)" class="btn border-0 text-danger btn_delete" data-id="<?php echo $row['id'];?>" data-table="<?=$tableName ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="ri-delete-bin-fill"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $i++; } ?>
                    </div>
                     <?php include("pagination.php"); ?>
                <?php } else { ?>
                    <ul class="p-5">
                        <h1 class="text-center">No data found</h1>
                    </ul>
                <?php } ?>
                </nav>
            </div>
        </div>
        
    </div>
</main>
<!-- End: main -->
    
<?php include("includes/footer.php");?> 