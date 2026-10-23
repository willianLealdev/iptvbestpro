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
    
    require_once("includes/support_helper.php");
    support_migrate($mysqli);
    $sql_query="SELECT tbl_reports.*, r.name AS reseller_name FROM tbl_reports LEFT JOIN tbl_resellers r ON r.id = tbl_reports.reseller_id ORDER BY tbl_reports.`id` DESC LIMIT $start, $limit"; 
    $result=mysqli_query($mysqli,$sql_query) or die(db_fail($mysqli));
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
                <a href="<?php echo (isset($_SERVER['HTTP_REFERER'])) ? htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, 'UTF-8') : "dashboard.php" ?>" class="d-inline-flex align-items-center text-decoration-none fw-semibold nsofts-link">
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
                                    <th>Aparelho / Revenda</th>
                                    <th>Title</th>
                                    <th>Report</th>
                                    <th class="text-center">Date</th>
                                    <th style="width: 200px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=0; while($row=mysqli_fetch_array($result)) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['user_name'], ENT_QUOTES, 'UTF-8', false);?></td>
                                        <td><small><?php echo htmlspecialchars((string)$row['device_id'], ENT_QUOTES, 'UTF-8', false);?><?php if (!empty($row['device_model'])) echo '<br>'.htmlspecialchars((string)$row['device_model'], ENT_QUOTES, 'UTF-8', false); ?><br><b><?php echo !empty($row['reseller_name']) ? htmlspecialchars((string)$row['reseller_name'], ENT_QUOTES, 'UTF-8', false) : 'Sem revenda'; ?></b></small></td>
                                        <td><?php echo htmlspecialchars((string)$row['report_title'], ENT_QUOTES, 'UTF-8', false);?></td>
                                        <td><?php echo htmlspecialchars((string)$row['report_msg'], ENT_QUOTES, 'UTF-8', false);?></td>
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