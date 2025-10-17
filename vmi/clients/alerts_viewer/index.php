<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');

// Fetch active alerts with message content and site name
$sql = "
    SELECT aa.*, m.message_content, s.Site_name 
    FROM active_alerts aa
    JOIN messages m ON aa.alert_type = m.message_type AND aa.message_id = m.message_id
    JOIN Sites s ON aa.uid = s.uid
    JOIN Console_Asociation ca ON ca.uid = s.uid
    WHERE aa.aa_active = 1 AND ($companyId = 15100 OR (ca.Client_id = $companyId OR ca.dist_id = $companyId OR ca.reseller_id = $companyId))
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html data-wf-page="65014a9e5ea5cd2c6534f24f" data-wf-site="65014a9e5ea5cd2c6534f1c8">
<head>
  <meta charset="utf-8">
  <title>EHON Energy Tech - Alerts</title>
  <meta property="og:type" content="website">
  <meta content="summary_large_image" name="twitter:card">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/details/menu.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
        <div class="dashboard-content">
            <div class="dashboard-main-content">
                <?php include('../../details/top_menu.php');?>
                <div class="container-default w-container" style="max-width: 920px;">
                    <div class="mg-bottom-32px">
                        <div class="_2-items-wrap-container">
                            <div id="w-node-_4e606362-eabc-753a-260a-8d85f152b3ca-6534f24f">
                                <h1 class="display-4 mg-bottom-4px" style="color: #EC1C1C">Alerts and Warnings Viewer</h1>
                                <p class="mg-bottom-0"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mg-bottom-40px">
                        <div class="card">
                            <div class="card overflow-hidden">
                                <div class="table-main-container">
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<div class="recent-orders-table-row2" style="grid-template-columns: 0.5fr 0.8fr 2fr 0.5fr;">';
                                                echo '<div class="flex align-center">';
                                                    echo '<div class="paragraph-small color-neutral-100" style="font-size: 14px">' . $row['Site_name'] . '</div>';
                                                echo '</div>';
                                                echo '<div class="flex align-center">';
                                                    echo '<div class="paragraph-small color-neutral-100" style="font-size: 14px">' . $row['alert_timestamp'] . '</div>';
                                                echo '</div>';
                                                if($row['alert_type'] == 10){
                                                    echo '<div class="paragraph-small color-neutral-100" style="font-size: 20px; justify-self: start; color: #ebeb00;">' . $row['message_content'] . '</div>';
                                                }
                                                elseif($row['alert_type'] == 11){
                                                    echo '<div class="paragraph-small color-neutral-100" style="font-size: 20px; justify-self: start; color: #db8e01;">' . $row['message_content'] . '</div>';
                                                }
                                                elseif($row['alert_type'] == 13){
                                                    echo '<div class="paragraph-small color-neutral-100" style="font-size: 20px; justify-self: start; color: red;">' . $row['message_content'] . '</div>';
                                                }
                                                else{
                                                    echo '<div class="paragraph-small color-neutral-100" style="font-size: 20px; justify-self: start; color: white;">' . $row['message_content'] . '</div>';
                                                }
                                                echo '<div class="paragraph-small color-neutral-100" style="font-size: 20px; justify-self: end;">';
                                                    echo '<button type="button" class="acknowledge-btn" data-alert-id="' . $row['alert_id'] . '" data-uid="' . $row['uid'] . '" data-alert-type="' . $row['alert_type'] . '">Acknowledge</button>';
                                                echo '</div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<p>No active alerts.</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>    
        </div>                      
    </div>
</div>
<div class="loading-bar-wrapper">
    <div class="loading-bar"></div>
</div>

<!-- Toastr Notification Script -->
<script>
$(document).ready(function() {
    $('.acknowledge-btn').click(function(event) {
        var alertId = $(this).data('alert-id');
        var csuid = $(this).data('uid');
        var alert_type = $(this).data('alert-type');
        // console.log("Acknowledging alert with ID:", alert_type);

        $.ajax({
            url: 'acknowledge_alert.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ alert_id: alertId, csuid: csuid, alert_type: alert_type}),
            success: function(response) {
                // console.log("Server response:", response);
                if (response.status === 'success') {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
                // Optionally reload the page or update the UI to reflect the acknowledged alert
                location.reload();
            },
            error: function(xhr, status, error) {
                // console.error("Error:", status, error);
                toastr.error('An error occurred while acknowledging the alert.');
            }
        });
    });
});
</script>
</body>
</html>
