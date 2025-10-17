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
    <title>EHON Energy</title>
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
                <form method="post" action="new_customer_sbmt.php" class="container-default w-container" style="padding-top: 24px; max-width: 960px;">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Add Customer</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Customer Name:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter the Customer Name" name="customer_name" required>
                                <label for="country">Choose your country:</label>
                                <select class="small-dropdown-toggle" name="customer_country" id="country"></select>
                                <script>
                                    // Fetch countries
                                    fetch('https://restcountries.com/v2/all')
                                        .then(response => response.json())
                                        .then(data => {
                                            const countrySelect = document.getElementById('country');
                                            data.forEach(country => {
                                                const option = document.createElement('option');
                                                option.value = country.name; // Use country.name as the value
                                                option.textContent = country.name;
                                                countrySelect.appendChild(option);
                                            });
                                        });
                        
                                </script>
                                <label>Address:</label>
                                <input class="input" type="text" placeholder="Enter address" name="customer_address" required>   
                                <label>City:</label>
                                <input class="input" type="text" placeholder="Enter City" name="customer_city" required>   
                                <label>Post Code:</label>
                                <input class="input" type="text" placeholder="Enter Post Code" name="customer_postcode" required>                    
                                <label>Phone:</label>
                                <input class="input" type="tel" placeholder="Enter phone number" name="customer_phone">
                                <label>Email:</label>
                                <input class="input" type="email" placeholder="Enter contact email" name="customer_email">
                                <label>Blocked Sites:</label>
                                <select multiple="multiple" class="small-dropdown-toggle" name="block_site" id="block_site">
                                    <!-- <option value="0">Select console ID</option> -->
                                    <?php

                                            $idcheck = "SELECT site_id, site_name FROM Sites WHERE client_id = ?";
                                            
                                            $stmt = $conn->prepare($idcheck);
                                            $stmt->bind_param("s", $companyId); // Bind parameters
                                            $stmt->execute();
                                        
                                            // Bind result variables
                                            $bound_id = null;
                                            $bound_sitename = null;
                                            $stmt->bind_result($bound_id, $bound_sitename); 
                                        
                                            while ($stmt->fetch()) {
                                                echo '<option value="' . htmlspecialchars($bound_id) . '">' . htmlspecialchars($bound_sitename) . '</option>';
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
                        <button type="submit" class="btn-primary w-inline-block">
                            <div class="flex-horizontal gap-column-6px">
                                <div>Create Customer</div>
                            </div>
                        </button>
                    </div>
                </from>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('checktg').addEventListener('change', function() {
    var tankDropdown = document.getElementById('tankCountDropdown');
    if (this.checked) {
        tankDropdown.style.display = 'block';
    } else {
        tankDropdown.style.display = 'none';
    }
});
</script>

</body>
</html>
