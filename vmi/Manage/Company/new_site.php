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
    <title>New Site - Ehon Energy Tech</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <!-- THEME CSS - MUST BE FIRST -->
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <!-- Other CSS files -->
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
            <?php include('../../details/top_menu.php');?>
                <form id="siteForm" method="post" action="new_site_sbmt" class="container-default w-container" style="padding-top: 24px; max-width: 960px;">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Site Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Site Name:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter the Site Name" name="site_name" required>
                                <label for="country">Country:</label>
                                <input class="input" type="text" placeholder="Enter your Country" name="site_country" required>   
                                <label>Address:</label>
                                <input class="input" type="text" placeholder="Enter address" name="site_address" required>   
                                <label>City:</label>
                                <input class="input" type="text" placeholder="Enter City" name="site_city" required>   
                                <label>Post Code:</label>
                                <input class="input" type="text" placeholder="Enter Post Code" name="site_postcode" required>   
                                <label>Timezone:</label>
                                <select class="small-dropdown-toggle" name="timezone" id="timezone" required>
                                    <option value="0">Select your Timezone</option>
                                    <?php
                                            $timez = "SELECT id, time_zone, example_city, utc_offset FROM `timezones` ORDER BY utc_offset ASC";                                           
                                            $stmt = $conn->prepare($timez);
                                            $stmt->execute();
                                        
                                            // Bind result variables
                                            $bound_uid = null;
                                            $bound_deviceid = null;
                                            $bound_console_status = null;
                                            $bound_example_city = null;
                                            $stmt->bind_result($id, $timezone_name, $example_city, $utc); 
                                        
                                            while ($stmt->fetch()) {                                             
                                                echo '<option value="' . htmlspecialchars($id) . '" ' . $selected . '>' . htmlspecialchars($timezone_name) . ' - ' . htmlspecialchars($example_city) . ' ( ' . htmlspecialchars($utc) . ' )</option>';                                    
                                            }
                                            echo '<option value="add">Add a console</option>';
                                            $stmt->close();
                                    ?>
                                </select>                    
                                <label>Site Phone:</label>
                                <input class="input" type="tel" placeholder="Enter phone number" name="site_phone" pattern="[0-9]*" title="Please enter a valid phone number">
                                <label>Site Email:</label>
                                <input class="input" type="email" placeholder="Enter contact email" name="site_email">
                                <label>Console:</label>
                                <select class="small-dropdown-toggle" name="consoleid" id="consoleid" required>
                                    <option value="0">Select console ID</option>
                                    <?php

                                            $idcheck = "SELECT cs.uid, cs.device_id, cs.console_status FROM `Console_Asociation` as ca JOIN console as cs on cs.uid = ca.uid where cs.console_status = 'In Use' and ca.client_id = ?";
                                            
                                            $stmt = $conn->prepare($idcheck);
                                            $stmt->bind_param("s", $companyId); // Bind parameters
                                            $stmt->execute();
                                        
                                            // Bind result variables
                                            $bound_uid = null;
                                            $bound_deviceid = null;
                                            $bound_console_status = null;
                                            $stmt->bind_result($bound_uid, $bound_deviceid, $bound_console_status); 
                                        
                                            while ($stmt->fetch()) {
                                                echo '<option value="' . htmlspecialchars($bound_uid) . '">' . htmlspecialchars($bound_uid) . '</option>';
                                            }
                                        
                                    ?>
                                </select>
                            </div>
                            <br>
                        </div>
                    </div>
                    <br>
                    
                    <br>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row" style="justify-content: end;">
                        <button type="submit" class="btn-primary w-inline-block" id="submitBtn">
                            <div class="flex-horizontal gap-column-6px">
                                <div>Next</div>
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
    $('#siteForm').on('submit', function(e) {
        var consoleSelect = $('#consoleid');
        var selectedValue = consoleSelect.val();
        if (selectedValue === "0") {
            toastr.error('Console is needed');
            e.preventDefault(); // Prevent form submission
        }
    });
    
    $('#checktg').change(function() {
        var tankDropdown = $('#tankCountDropdown');
        if ($(this).is(':checked')) {
            tankDropdown.show();
        } else {
            tankDropdown.hide();
        }
    });
});

</script>

</body>
</html>
