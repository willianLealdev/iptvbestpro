<?php $page_title="Manage Reports";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    
    $tableName="tbl_reports";   
    $targetpage = "manage_report.php"; 
    $limit = 15; 

    $query = "SELECT COUNT(*) as num FROM $tableName";
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
    
    $sql_query="SELECT * FROM tbl_reports ORDER BY tbl_reports.`id` DESC LIMIT $start, $limit"; 
    $result=mysqli_query($mysqli,$sql_query) or die(mysqli_error($mysqli));
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
                <span class="ps-2 lh-1">Total Reports (<?=$total_pages ?>)</span>
                
            </div>
            
            <div class="card-body p-4">
                <?php if(mysqli_num_rows($result) > 0){ ?>
                    <div class="row g-4">
                        <table class="table ">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Title</th>
                                    <th>Report</th>
                                    <th class="text-center">Date</th>
                                    <th style="width: 200px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=0; while($row=mysqli_fetch_array($result)) { ?>
                                    <tr>
                                        <td><?php echo $row['user_name'];?></td>
                                        <td><?php echo $row['report_title'];?></td>
                                        <td><?php echo $row['report_msg'];?></td>
                                        <td class="text-center"><?php echo date('d-m-Y',$row['report_on']);?></td>
                                        
                                        <td class="text-center">
                                            <a href="view_report.php?report_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-icon" style="padding: 10px 10px !important;  margin-right: 10px !important;" data-bs-toggle="tooltip" data-bs-placement="top" title="View Report">
                                                <i class="ri-honour-line"></i>
                                            </a>
                                            <a href="javascript:void(0)" class="btn btn-danger btn-icon btn_delete" data-id="<?php echo $row['id'];?>" data-table="<?=$tableName ?>" style="padding: 10px 10px !important;" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                 <?php $i++; } ?>
                            </tbody>
                        </table>
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