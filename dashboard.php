<?php $page_title="Dashboard";
    include("includes/header.php");
    require("includes/lb_helper.php");

    $qry_policy="SELECT COUNT(*) as num FROM tbl_policy_deletion";
    $total_policy = mysqli_fetch_array(mysqli_query($mysqli,$qry_policy));
    $total_policy = $total_policy['num'];

    $qry_dns="SELECT COUNT(*) as num FROM tbl_xui_dns";
    $total_dns = mysqli_fetch_array(mysqli_query($mysqli,$qry_dns));
    $total_dns = $total_dns['num'];
    
    $qry_re="SELECT COUNT(*) as num FROM tbl_reports";
    $total_reports = mysqli_fetch_array(mysqli_query($mysqli,$qry_re));
    $total_reports = $total_reports['num'];
    
    $qry_custom_ads="SELECT COUNT(*) as num FROM tbl_custom_ads";
    $total_custom_ads = mysqli_fetch_array(mysqli_query($mysqli,$qry_custom_ads));
    $total_custom_ads = $total_custom_ads['num'];
    
    $sql_reports="SELECT * FROM tbl_reports ORDER BY tbl_reports.`id` DESC LIMIT 10";
    $result_reports=mysqli_query($mysqli,$sql_reports);
    
    $qry="SELECT * FROM tbl_settings where id='1'";
    $result=mysqli_query($mysqli,$qry);
    $settings_data=mysqli_fetch_assoc($result);
    
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
            <style>
                .nsofts-icon i {
                    font-size: 45px;
                }
                .social_img {
                    position: absolute;
                    width: 20px !important;
                    height: 20px !important;
                    z-index: 1;
                    left: 13px;
                }
            </style>
            
            <div class="col-xxl-3 col-md-6">
                <div class="card card-raised border-start border-primary border-4">
                    <div class="card-body px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <div class="display-6"><?php echo thousandsNumberFormat($total_policy); ?></div>
                                <div class="d-block mb-1 text-muted">Policy Deletion</div>
                            </div>
                            <div class="d-inline-flex text-primary nsofts-icon "><i class="ri-alarm-warning-line"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xxl-3 col-md-6">
                <div class="card card-raised border-start border-info border-4">
                    <div class="card-body px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <div class="display-6"><?php echo thousandsNumberFormat($total_dns); ?></div>
                                <div class="d-block mb-1 text-muted">XUI DNS</div>
                            </div>
                            <div class="d-inline-flex text-info nsofts-icon "><i class="ri-links-line"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xxl-3 col-md-6">
                <div class="card card-raised border-start border-danger border-4">
                    <div class="card-body px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <div class="display-6"><?php echo thousandsNumberFormat($total_reports); ?></div>
                                <div class="d-block mb-1 text-muted">Reports</div>
                            </div>
                            <div class="d-inline-flex text-danger nsofts-icon "><i class="ri-feedback-line"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xxl-3 col-md-6">
                <div class="card card-raised border-start border-warning border-4">
                    <div class="card-body px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <div class="display-6"><?php echo thousandsNumberFormat($total_custom_ads); ?></div>
                                <div class="d-block mb-1 text-muted">Custom Ads</div>
                            </div>
                            <div class="d-inline-flex text-warning nsofts-icon "><i class="ri-advertisement-line"></i></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        
        <div class="row g-4 mt-2">
            
        <div class="col-lg-7 col-md-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <h5 class="mb-4">App Theme</h5>
                            </div>
                        </div>
                        <div>
                            <?php  if ($settings_data['is_theme'] == '2') { ?>
                                <img src="assets/images/themes/Glossy.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;" >
                            <?php } else if ($settings_data['is_theme'] == '3') { ?>
                                <img src="assets/images/themes/BlackPanther.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;" >
                            <?php } else if ($settings_data['is_theme'] == '4') { ?>
                                <img src="assets/images/themes/MovieUI.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;" >
                            <?php } else if ($settings_data['is_theme'] == '5') { ?>
                                <img src="assets/images/themes/VUI.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;" >
                            <?php } else if ($settings_data['is_theme'] == '6') { ?>
                                <img src="assets/images/themes/ChristmasUI.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;" >
                            <?php } else if ($settings_data['is_theme'] == '7') { ?>
                                <img src="assets/images/themes/HalloweenUI.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;" >
                            <?php } else { ?>
                                <img src="assets/images/themes/OneUI.png" alt="" style="width: 100%; height: auto; min-width: 100%; min-height: 100%; max-width: 100%; border-radius: 10px;">
                            <?php } ?>
                            
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5 col-md-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">New Reports</h5>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="text-decoration-none text-dark" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ri-more-2-fill"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-sm">
                                    <li><a class="dropdown-item" href="manage_report.php">Manage</a></li>
                                  </ul>
                            </div>
                        </div>
                        <?php if(mysqli_num_rows($result_reports) > 0){ ?>
                        
                            <?php $i=0; while($row=mysqli_fetch_array($result_reports)) { ?>
                            
                                <div class="d-flex align-items-center mt-4">
                                    <span class="d-block fw-semibold"><?php echo $row['report_title'];?></span>
                                    <span><td><?php echo calculate_time_span($row['report_on']);?></td></span>
                                </div>
                                
                            <?php $i++; } ?> 
                            
                        <?php } else { ?>
                            <ul class="p-2">
                                <h3 class="text-center">No data found !</h3>
                            </ul>
                        <?php } ?>
                       
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</main>
<!-- End: main -->
<?php include("includes/footer.php");?> 