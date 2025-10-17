<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHON Energy Tech</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="script.js"></script>
    <link href="/vmi/images/favicon.ico" rel="shortcut icon" type="image/x-icon">
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class="dashboard-main-content">
            <?php include('../../details/top_menu.php');?>
                <form id="client_info" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Client Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Client Name:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter the Client Name" name="Client_name" required> 
                                <?php if($accessLevel == 1 || $accessLevel == 2){?>
                                    <label style="margin-bottom: 15px">Reseller Name:</label>
                                    <select class="small-dropdown-toggle" name="reseller_id" id="reseller">
                                    <option value="0">Select Reseller</option>
                                    <?php
                                        $resellersql = "SELECT reseller_id, reseller_name FROM `Reseller`";
                                        $reseller = $conn->prepare($resellersql); 
                                        $reseller->execute();
                                        $res_id = null;
                                        $res_name = null;
                                        $reseller->bind_result($res_id, $res_name); 
                                        while ($reseller->fetch()) {
                                            echo '<option value="' . htmlspecialchars($res_id) . '">' . htmlspecialchars($res_name) . '</option>';
                                        }
                                        $reseller->close();
                                    ?>
                                    </select>
                                    <label style="margin-bottom: 15px">Distributor Name:</label>
                                    <select class="small-dropdown-toggle" name="dist_id" id="distributor">
                                    <option value="0">Select Distributor</option>
                                    <?php
                                        $distsql = "SELECT dist_id, dist_name FROM `Distributor`";
                                        $dist = $conn->prepare($distsql); 
                                        $dist->execute();
                                        $dist_id = null;
                                        $dist_name = null;
                                        $dist->bind_result($dist_id, $dist_name); 
                                        while ($dist->fetch()) {
                                            echo '<option value="' . htmlspecialchars($dist_id) . '">' . htmlspecialchars($dist_name) . '</option>';
                                        }
                                        $dist->close();
                                    ?>
                                    </select>
                                <?php } ?>

                                <label>Client Address:</label>
                                <input class="input" type="text" placeholder="Enter Client address" name="Client_address" required>                 
                                <label>Client Phone:</label>
                                <input class="input" type="tel" placeholder="Enter Client phone number" name="Client_phone">
                            </div>
                            <br>
                            <br>
                            <h1 class="display-4 mg-bottom-4px">User login Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label>First Name:</label>
                                <input class="input" type="text" placeholder="Enter user name" name="firstname" autocomplete="off" required>
                                <label>Last Name:</label>
                                <input class="input" type="text" placeholder="Enter user Last Name" name="lastname" autocomplete="off" required>
                                <label>User Email:</label>
                                <input class="input" type="email" placeholder="Enter user contact email" name="username" autocomplete="off" required>
                                <label>User Password:</label>
                                <input class="input" type="password" placeholder="Enter Password" name="Client_password" autocomplete="off" required>
                                <label>Confirm User Password:</label>
                                <input class="input" type="password" placeholder="Confirm Password" name="Client_confirm_password" autocomplete="off" required>
                            </div>
                            <br>                                          
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block">
                        <div class="flex-horizontal gap-column-6px">
                            <div>Create Client</div>
                        </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#client_info').on('submit', function(event) {
            event.preventDefault(); // Prevent the default form submission

            $.ajax({
                url: 'new_client_sbmt.php',
                type: 'POST',
                data: $(this).serialize(), // Serialize the form data
                dataType: 'json', // Expect JSON response from the server
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    toastr.error('An error occurred: ' + textStatus);
                }
            });
        });
    });
</script>

</body>
</html>
