<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
include('../../../db/border.php');

// Check if 'id' parameter is present in the URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Query to fetch the tag information based on the provided ID and client_id
    $query = "SELECT * FROM vehicles WHERE vehicle_id = ? AND client_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the tag data if found
    if ($result->num_rows > 0) {
        $vehicleData = $result->fetch_assoc();
    } else {
        $vehicleData = null; // If no data found
    }
    $stmt->close();
} else {
    $vehicleData = null; // If no 'id' parameter is found
}

// If $vehicleData is null, display an error and exit
if (!$vehicleData) {
    echo "Tag not found or invalid ID.";
    exit;
}

$success = isset($_GET['success']) && $_GET['success'] === 'true';
$error = $_GET['error'] ?? '';
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
    <!-- THEME CSS - MUST BE FIRST -->
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <!-- Other CSS files -->
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="script.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
            <?php include('../../../details/top_menu.php');?>
                <form id="company_info" action="update_vehicle_sbmt" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 750px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <input type="hidden" name="vehicle_id" value="<?php echo $id; ?>">
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">   
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">                  
                                <h1 class="display-4 mg-bottom-4px">Add a Vehicle</h1>
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center; ">
                                    <label style="margin-bottom: 15px">Customer Name:</label>
                                    <select class="small-dropdown-toggle" name="customer_name" id="customer_name" required onchange="customerSelected(this.value, companyId)">
                                        <option value="0">Select a Customer</option>
                                        <?php
                                            $residcheck = "SELECT customer_id, customer_name FROM Customers WHERE client_id = ?";
                                            $stmtresidcheck = $conn->prepare($residcheck);
                                            $stmtresidcheck->bind_param("i", $companyId);
                                            $stmtresidcheck->execute();
                                            $stmtresidcheck->bind_result($cust_id, $cust_name);
                                            while ($stmtresidcheck->fetch()) {
                                                $selected = ($cust_id == $vehicleData['customer_id']) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($cust_id) . '" ' . $selected . '>' . htmlspecialchars($cust_name) . '</option>';
                                            }
                                            $stmtresidcheck->close();
                                        ?>
                                    </select>
                                    <label>Asset Number:</label>
                                    <input class="input" type="number" placeholder="Enter Asset Number" name="assist_number" autocomplete="off" value="<?php echo $vehicleData['vehicle_assetnumber']; ?>">  
                                    <label>Vehicle Name:</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Name" name="vehicle_name" autocomplete="off" value="<?php echo $vehicleData['vehicle_name']; ?>" required>    
                                    <br>
                                </div>
                            </div>                          
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">                  
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center; ">  
                                    <label>Odometer:</label>
                                    <select class="small-dropdown-toggle" name="odometer_unit" required>  
                                        <option value="0">Select a Type</option>
                                        <option value="1" <?php if ($vehicleData['odometer_type'] == 1) echo 'selected'; ?>>KM</option>
                                        <option value="2" <?php if ($vehicleData['odometer_type'] == 2) echo 'selected'; ?>>Hours</option>
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
                                    <div class="prompt" style="margin-top: 15px; text-align: center; margin-bottom: 15px;">             
                                        <label>Enabled:</label>
                                        <select  class="small-dropdown-toggle" style="display: initial;" name="vehicle_enable" required>
                                            <option value="1">Yes</option>
                                            <option value="2">No</option>
                                        </select>
                                    </div> 
                                    <div class="prompt" style="margin-top: 15px; text-align: center; margin-bottom: 15px; display: flex; justify-content: space-evenly;">     
                                        <div class="prompt" style="margin-top: 15px; text-align: center; margin-bottom: 15px;">
                                            <label style="margin-bottom:10px">Odometer Prompt:</label>
                                            <input class="" type="checkbox" name="vehicle_odometer_prompt" id="vehicle_odometer_prompt" style="justify-self: start;">
                                        </div> 
                                        <div class="prompt" style="margin-top: 15px; text-align: center; margin-bottom: 15px;">
                                            <label>Odometer Last:</label>
                                            <input class="input" type="number" placeholder="Odometer" name="odometer_last" style="max-width: 150px;" value="<?php echo $vehicleData['last_odometer']; ?>" autocomplete="off"> 
                                        </div>
                                    </div>
                                </div>
                                <br>
                            </div>                               
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);"> 
                                <br>
                                <h1 class="display-4 mg-bottom-4px">Vehicle Information</h1>
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 2fr; align-items: center;">
                                    <label>Vehicle Brand :</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Brand" name="vehicle_brand" value="<?php echo $vehicleData['vehicle_brand']; ?>"> 
                                    <label>Vehicle Model :</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Model" name="vehicle_model" value="<?php echo $vehicleData['vehicle_model']; ?>"> 
                                    <label>Vehicle Type:</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Type" name="vehicle_type" value="<?php echo $vehicleData['vehicle_type']; ?>"> 
                                    <label>Tank Size :</label>
                                    <input class="input" type="number" placeholder="Enter Tank Size" name="vehicle_tanksize" value="<?php echo $vehicleData['vehicle_tanksize']; ?>"> 
                                    <label>Rego :</label>
                                    <input class="input" type="text" placeholder="Enter Vehicle Rego" name="vehicle_rego" value="<?php echo $vehicleData['vehicle_rego']; ?>"> 
                                    <label for="expiry-date" style="margin-top: 10px;">Date of Registration:</label>
                                    <input class="input" type="date" id="registration_date" name="registration_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;" value="<?php echo $vehicleData['vehicle_rego_date']; ?>">           
                                    <label for="expiry-date" style="margin-top: 10px;">Next Service Date:</label>
                                    <input class="input" type="date" id="service_date" name="service_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;" value="<?php echo $vehicleData['vehicle_service']; ?>"> 
                                    <label>Next Service KM :</label>
                                    <input class="input" type="number" placeholder="Enter Next Service KM" name="vehicle_servicekm" value="<?php echo $vehicleData['vehicle_service_km']; ?>"> 
                                    <label>Requires Service :</label>
                                    <input class="input" type="number" placeholder="Enter Next Service KM" name="vehicle_reqservicekm"> 
                                <br>                            
                            </div>
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);"> 
                                <br>
                                <h1 class="display-4 mg-bottom-4px">Additional Information</h1>
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 2fr; align-items: center;">
                                        <label>Additional Information:</label>
                                        <!-- <input class="input" type="text" id="additional-info" name="additional_info" maxlength="150" placeholder="Enter additional information here"> -->
                                        <textarea class="input" id="additional-info" name="additional_info" maxlength="150" rows="4" cols="50" placeholder="Enter additional information here" value="<?php echo $vehicleData['vehicle_addinfo']; ?>"></textarea>
    
                                </div>
                                <br>                            
                            </div>
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block" style="margin:1rem;">
                        <div class="flex-horizontal gap-column-6px">
                                <div>Modify Vehicle</div>
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
<script>
        $(document).ready(function() {
    <?php if ($success): ?>
        toastr.success('Vehicle updated successfully!');
    <?php elseif ($error): ?>
        const error = '<?= htmlspecialchars($error); ?>';
        switch (error) {
            case 'validation_failed':
                toastr.error('Validation failed. Please check your inputs.');
                break;
            case 'prepare_failed':
                toastr.error('Database error while preparing the statement.');
                break;
            case 'execute_failed':
                toastr.error('Failed to update data in the database.');
                break;
            case 'invalid_method':
                toastr.error('Invalid request method.');
                break;
            default:
                toastr.error('An unexpected error occurred.');
                break;
        }
    <?php endif; ?>
});
</script>
