<?php
  include('../db/dbh2.php');
  include('../db/log.php');
  include('../db/border.php');
?>
<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link rel="stylesheet" href="menu.css">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

    <main class="table">
    <?php include('top_menu.php');?>
        <div class="dashboard-content">
            <div class="dashboard-main-content">
                <div class="container-default w-container" style="text-align: -webkit-center;">
                    <div class="mg-bottom-16px" style="max-width: 420px;">
                        <form class="small_group-details-card-grid" action="newgroup" method="post">
                                <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card_group top-details">
                                    <input class = "input top-details" type="string" name="groupname" id="groupname">
                                </div>
                                    <div class="">
                                        <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                                        <input type="submit" value="Create Group"
                                            style="font-weight: bold; font-size: 24px; color:white; background-color: #002F60;border-radius: 4px;cursor: pointer;padding: 5px 10px;border: none;">
                                </div>
                        </form>
                    </div>
                    <div class="mg-bottom-24px" style="max-width: 430px;">
                        <div>
                            <div class="card overflow-hidden">
                                <div class="_2-items-wrap-container pd-32px---28px">
                                    <?php
                                        $sel = "SELECT group_id, group_name FROM site_groups where client_id = ?";
                                        $stmt = $conn->prepare($sel);
                                        $stmt->bind_param("d", $companyId);
                                        $stmt->execute();
                                        $stmt->store_result();
                                        if($stmt->num_rows > 0) {
                                            // Bind the columns to variables
                                            $stmt->bind_result($group_id,$group_name);
                                    
                                            // Fetch the row
                                            ?>

                                            <div class="text-300 medium color-neutral-100">Edit your Group</div>
                                            <div class="_2-items-wrap-container gap-12px">
                                            <form action="group_updt" method="post">
                                                <select id="groupDropdown" name="selected_group" class="small-dropdown-link w-dropdown-link" style="font-size: 12px">
                                                <option value="">Select Group</option>
                                                    <?php
                                                    // Loop through the result set and create an option for each row
                                                    while ($stmt->fetch()) {
                                                        echo '<option value="' . $group_id . '">' . $group_name . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>                                   
                                            <?php                                  
                                        
                                        } else {
                                            echo "Create your group";
                                        }
                                    ?>                                                                               
                                </div>                               
                                <div class="card pd-30px---36px">
                                    <div class="orders-status-table-row table-header">
                                        <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px"><img src="/vmi/images/order-table-header-icon-dashdark-webflow-template.svg" loading="eager" alt="">
                                            <div class="text-50 semibold color-neutral-100">Site Number</div>
                                        </div>
                                        <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px"><img src="/vmi/images/client-table-header-icon-dashdark-webflow-template.svg" loading="eager" alt="">
                                            <div class="text-50 semibold color-neutral-100">Company Name</div>
                                        </div> 
                                        <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px"><img src="/vmi/images/client-table-header-icon-dashdark-webflow-template.svg" loading="eager" alt="">
                                            <div class="text-50 semibold color-neutral-100">Site Name</div>
                                        </div>                                            
                                    </div>     
                                    <?php
                                        if (!empty($companyId) && $companyId != 15100) {
                                            $sql = "SELECT site_name as site_name, cs.site_id as site_id, clc.client_name as cname FROM Sites cs join Clients clc on (cs.client_id) = (clc.client_id) WHERE cs.client_id = $companyId or clc.reseller_id = $companyId or clc.Dist_id = $companyId;";
                                            $sqlcount = "SELECT count(site_name) as count FROM Sites cs join Clients clc on (cs.client_id) = (clc.client_id) where cs.client_id = $companyId or clc.reseller_id = $companyId or clc.Dist_id = $companyId;";                                               
                                        } elseif ($companyId == 15100) {
                                            $sql = "SELECT site_name as site_name, cs.site_id as site_id, clc.client_name as cname FROM Sites cs join Clients clc on (cs.client_id) = (clc.client_id)  WHERE cs.uid in (SELECT uid FROM console WHERE device_type != 999);";
                                            $sqlcount = "SELECT COUNT(*) as count
                                            FROM (
                                                SELECT (site_name) as site_name, 
                                                        cs.site_id as site_id, 
                                                        clc.client_name as cname 
                                                FROM Sites cs 
                                                JOIN Clients clc 
                                                ON (cs.client_id) = (clc.client_id) WHERE cs.uid in (SELECT uid FROM console WHERE device_type != 999)
                                            ) as subquery;";                                                                                           
                                        }
                                        $i = 0;
                                        $resultsql = $conn->query($sql);
                                        $resultsqlcount = $conn->query($sqlcount);
                                        if ($resultsqlcount->num_rows > 0) {                                            
                                            $row = $resultsqlcount->fetch_assoc();
                                            $t = $row['count'];
                                        }
                                        for ($i = 0; $i < $t; $i++) {
                                            $row = $resultsql->fetch_assoc();
                                            $name = $row["site_name"];
                                            $siteid = $row['site_id'];
                                            $cname = $row['cname'];
                                            ?>                                                                                                        
                                    <div class="orders-status-table-row">
                                        <div id="w-node-ffe664cd-effd-fb9f-b3b2-a26245283433-4528342e" class="flex align-center">
                                            <div class="mg-bottom-0 hidden-on-mbl w-form">                                                
                                                <label class="w-checkbox checkbox-field-wrapper mg-bottom-0">
                                                <input type="checkbox" id="checkbox-<?php echo $siteid; ?>" name="selected_checkboxes[]" value="<?php echo $siteid . '|' . $name; ?>" data-siteid="<?php echo $siteid; ?>"  data-sitename="<?php echo $name; ?>" class="check-test" data-group="<?php echo $group_id; ?>">
                                                <span class="hidden-on-desktop w-form-label" for="checkbox-<?php echo $siteid; ?>"><?php echo "#$siteid" ?></span>
                                                </label>                                                                                     
                                            </div>
                                            <div class="paragraph-small color-neutral-100" style="margin-left: 0.6rem;"><?php echo "#$siteid" ?></div>
                                        </div>
                                        <div id="w-node-ffe664cd-effd-fb9f-b3b2-a26245283446-4528342e" style="text-align:left;">
                                            <div id="w-node-ffe664cd-effd-fb9f-b3b2-a26245283447-4528342e" class="paragraph-small color-neutral-100 mg-bottom-2px"><?php echo "$cname" ?></div>
                                        </div>                                            
                                        <div id="w-node-ffe664cd-effd-fb9f-b3b2-a26245283446-4528342e" style="text-align:left;">
                                            <div id="w-node-ffe664cd-effd-fb9f-b3b2-a26245283447-4528342e" class="paragraph-small color-neutral-100 mg-bottom-2px"><?php echo "$name" ?></div>
                                        </div>  
                                    </div>
                                    <?php
                                        }
                                    ?> 
                                        <div class="flex-horizontal gap-column-4px">
                                    <input type="submit" name="submit" value="Update Group" class="btn-primary small w-inline-block" style="cursor:pointer;">
                                    <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                                    </form> 
                                    </div>                                            
                                </div>                                                                                                                                                       
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
<script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=65014a9e5ea5cd2c6534f1c8" type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="/js/webflow_rep.js" type="text/javascript"></script>
</body>
</html>
<script>
$(document).ready(function() {
$('#groupDropdown').on('change', function() {
    var groupId = $(this).val();
    var companyId = <?php echo $companyId;?>; 
    console.log(groupId);
    $.ajax({
        url: 'fetch_data',
        type: 'POST',
        data: { groupId: groupId, companyId: companyId },
        dataType: 'json',
        success: function(data) {  
            console.log(data);
            // First, uncheck all checkboxes
            $("input[type='checkbox']").prop("checked", false);

            // Now, loop through the returned pairs and check the checkboxes that match both siteId and siteName
            $.each(data, function(index, item) {
                var checkbox = $("input[type='checkbox'][data-siteid='" + item.siteId + "'][data-sitename='" + item.siteName + "']");
                if (checkbox.length) {
                    checkbox.prop("checked", true);
                }
            });
        }
    });
});
});
</script>
