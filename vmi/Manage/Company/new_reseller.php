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
    <title>Console Dispatch</title>
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
    <script src="script.js"></script>
    <link href="/vmi/images/favicon.ico" rel="shortcut icon" type="image/x-icon">
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
            <?php include('../../details/top_menu.php');?>
                <form id="Reseller_info" action="new_reseller_sbmt" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Reseller Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Reseller Name:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter the Reseller Name" name="Client_name" required> 
                                <?php if($accessLevel == 1 || $accessLevel == 2){?>
                                    <label style="margin-bottom: 15px">Distributor Name:</label>
                                    <select class="small-dropdown-toggle" name="dist_id" id="dist">
                                    <option value="0">Select Distributor Name</option>
                                    <?php

                                            $idcheck = "SELECT Dist_id, Dist_name FROM `Distributor`";
                                            
                                            $stmt = $conn->prepare($idcheck); 
                                            $stmt->execute();
                                        
                                            // Bind result variables
                                            $dist_id = null;
                                            $dist_name = null;
                                            $stmt->bind_result($dist_id, $dist_name); 
                                        
                                            while ($stmt->fetch()) {
                                                echo '<option value="' . htmlspecialchars($dist_id) . '">' . htmlspecialchars($dist_name) . '</option>';
                                            }
                                            $stmt->close();
                                        
                                    ?>
                                </select>
                                <?php } 
                                elseif($accessLevel == 4) {?>
                                    <label style="margin-bottom: 15px">Distributor Name:</label>
                                    <select class="small-dropdown-toggle" name="dist_id" id="dist">
                                    <?php
                                            $idcheck = "SELECT Dist_id, Dist_name FROM `Distributor` WHERE Dist_id = ?";
                                            
                                            $stmt = $conn->prepare($idcheck); 
                                            $stmt->bind_param("i", $companyId);
                                            $stmt->execute();
                                        
                                            // Bind result variables
                                            $dist_id = null;
                                            $dist_name = null;
                                            $stmt->bind_result($dist_id, $dist_name); 
                                        
                                            while ($stmt->fetch()) {
                                                echo '<option value="' . $dist_id . '">' . $dist_name . '</option>';
                                            }                                          
                                        
                                    ?>
                                </select>
                                <?php } 
                                else {?>
                                    <label style="margin-bottom: 15px">Distributor Name:</label>
                                    <!-- <select class="small-dropdown-toggle" name="dist_id" id="dist">
                                        <option value=999>Wrong access</option>
                                    </select> -->
                                    Wrong Access
                                    <?php } ?>

                                <label>Reseller Address:</label>
                                <input class="input" type="text" placeholder="Enter Reseller address" name="Client_address" required> 
                                <label>Reseller Email:</label>
                                <input class="input" type="email" placeholder="Enter Reseller contact email" name="Reseller_email" autocomplete="off" required>                     
                                <label>Reseller Phone:</label>
                                <input class="input" type="tel" placeholder="Enter Reseller phone number" name="Client_phone" required>
                            </div>
                            <br>
                            <br>
                            <h1 class="display-4 mg-bottom-4px">User login Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label>Name:</label>
                                <input class="input" type="text" placeholder="Enter user name" name="firstname" autocomplete="off" required>
                                <label>Last Name:</label>
                                <input class="input" type="text" placeholder="Enter user Last Name" name="lastname" autocomplete="off" required>
                                <label>Email:</label>
                                <input class="input" type="email" placeholder="Enter user contact email" name="Client_email" autocomplete="off" required>
                                <label>Password:</label>
                                <input class="input" type="password" placeholder="Enter Password" name="Client_password" autocomplete="off" required>
                                <label>Confirm Password:</label>
                                <input class="input" type="password" placeholder="Confirm Password" name="Client_confirm_password" autocomplete="off" required>
                            </div>
                            <br>                                          
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block">
                        <div class="flex-horizontal gap-column-6px">
                                <div>Create Reseller</div>
                            </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- <script>
    document.getElementById('Reseller_info').addEventListener('submit', function(event) {
    var password = document.getElementsByName('Client_password')[0].value;
    var confirmPassword = document.getElementsByName('Client_confirm_password')[0].value;

    if (password !== confirmPassword) {
        alert('Passwords do not match.');
        event.preventDefault(); // Prevent form submission
    }
    });
</script> -->
</body>
</html>
