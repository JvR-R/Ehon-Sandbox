<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');

// HTML content here
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
    <script>
    toastr.options = {
        "closeButton": true,
        "newestOnTop": true,
        "positionClass": "toast-top-right",
        "timeOut": "5000"
    };
    </script>
</head>
<body>
<?php
    if (isset($_SESSION['toastr_msg']) && isset($_SESSION['toastr_type'])) {
        $msg  = $_SESSION['toastr_msg'];
        $type = $_SESSION['toastr_type'];
        
        // Clear it right away so we don’t repeat on refresh
        unset($_SESSION['toastr_msg']);
        unset($_SESSION['toastr_type']);
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Based on the type, call the correct Toastr function
            if ("<?php echo $type; ?>" === "success") {
            toastr.success("<?php echo $msg; ?>");
            } else {
            toastr.error("<?php echo $msg; ?>");
            }
        });
        </script>
        <?php
    } else {
        echo "no session<br>";
    }
?>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
            <?php include('../../details/top_menu.php');?>
                <form id="company_info" action="new_vehicle_sbmt" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 750px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">   
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">                  
                                <h1 class="display-4 mg-bottom-4px">Add a Vehicle</h1>
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center; ">
                                    <label style="margin-bottom: 15px">Customer Name:</label>
                                    <select class="small-dropdown-toggle" name="customer_name" required>  
                                        <option value="0">No Customer</option>
                                        <?php
                                        $residcheck = "SELECT customer_id, customer_name FROM Customers WHERE client_id = ? and customer_id !=0";

                                        $stmtresidcheck = $conn->prepare($residcheck);
                                        $stmtresidcheck->bind_param("i", $companyId); 
                                        $stmtresidcheck->execute();

                                        // Correct placement of bind_result
                                        $stmtresidcheck->bind_result($cust_id, $cust_name);

                                        while ($stmtresidcheck->fetch()) {
                                            echo '<option value="' . htmlspecialchars($cust_id) . '">' . htmlspecialchars($cust_name) . '</option>';
                                        }
                                        $stmtresidcheck->close();
                                        ?>
                                    </select>  
                                    <label>Asset Number:</label>
                                    <input class="input" type="number" placeholder="Enter Asset Number" name="assist_number" autocomplete="off">  
                                    <label>Vehicle Name:</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Name" name="vehicle_name" autocomplete="off" required>    
                                    <br>
                                </div>
                            </div>                          
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">                  
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center; ">  
                                    <label>Odometer:</label>
                                    <select class="small-dropdown-toggle" name="odometer_unit">  
                                        <option value="0">Select a Type</option>
                                        <option value="1">KM</option>
                                        <option value="2">Hours</option>
                                    </select>    
                                    <label style="margin-bottom: 15px">Allowed Products:</label>
                                    <select multiple="multiple" class="small-dropdown-toggle" style="max-width:300px;" name="allowed_products[]"  required>  
                                        <?php
                                        $residcheck = "SELECT product_id, product_name FROM products;";

                                        $stmtresidcheck = $conn->prepare($residcheck);
                                        $stmtresidcheck->execute();

                                        // Correct placement of bind_result
                                        $stmtresidcheck->bind_result($product_id, $product_name);

                                        while ($stmtresidcheck->fetch()) {
                                            echo '<option value="' . htmlspecialchars($product_id) . '">' . htmlspecialchars($product_name) . '</option>';
                                        }
                                        $stmtresidcheck->close();
                                        ?>
                                    </select>      
                                        <label>Enabled:</label>
                                        <select  class="small-dropdown-toggle" style="display: initial; max-width: 5rem;" name="vehicle_enable" required>
                                            <option value="1">Yes</option>
                                            <option value="2">No</option>
                                        </select>
                                </div>
                                <br>
                            </div>                               
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);"> 
                                <br>
                                <h1 class="display-4 mg-bottom-4px">Vehicle Information</h1>
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 2fr; align-items: center;">
                                    <label>Vehicle Brand :</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Brand" name="vehicle_brand" > 
                                    <label>Vehicle Model :</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Model" name="vehicle_model" > 
                                    <label>Vehicle Type:</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Type" name="vehicle_type" > 
                                    <label>Tank Size :</label>
                                    <input class="input" type="number" placeholder="Enter Tank Size" name="vehicle_tanksize" > 
                                    <label>Rego :</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Rego" name="vehicle_rego" required> 
                                    <label for="expiry-date" style="margin-top: 10px;">Date of Registration:</label>
                                    <input class="input" type="date" id="registration_date" name="registration_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;">           
                                    <label for="expiry-date" style="margin-top: 10px;">Next Service Date:</label>
                                    <input class="input" type="date" id="service_date" name="service_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;"> 
                                    <label>Next Service KM :</label>
                                    <input class="input" type="number" placeholder="Enter Next Service KM" name="vehicle_servicekm" > 
                                    <label>Requires Service :</label>
                                    <input class="input" type="number" placeholder="Enter Next Service KM" name="vehicle_reqservicekm" > 
                                <br>                            
                            </div>
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);"> 
                                <br>
                                <h1 class="display-4 mg-bottom-4px">Additional Information</h1>
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 2fr; align-items: center;">
                                        <label>Additional Information:</label>
                                        <!-- <input class="input" type="text" id="additional-info" name="additional_info" maxlength="150" placeholder="Enter additional information here"> -->
                                        <textarea class="input" id="additional-info" name="additional_info" maxlength="150" rows="4" cols="50" placeholder="Enter additional information here"></textarea>
    
                                </div>
                                <br>                            
                            </div>
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block">
                        <div class="flex-horizontal gap-column-6px">
                                <div>Add Vehicle</div>
                            </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('company_info').addEventListener('submit', function(event) {
    var password = document.getElementsByName('Client_password')[0].value;
    var confirmPassword = document.getElementsByName('Client_confirm_password')[0].value;

    if (password !== confirmPassword) {
        alert('Passwords do not match.');
        event.preventDefault(); // Prevent form submission
    }
    });
</script>
</body>
</html>
