<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
include('../../../db/border.php');

// Check if 'id' parameter is present in the URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Query to fetch driver information based on ID and client_id
    $query = "SELECT * FROM drivers WHERE driver_id = ? AND client_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $driverData = $result->fetch_assoc();
    } else {
        $driverData = null;
    }
    $stmt->close();
} else {
    $driverData = null;
}

// Redirect if driver data is not found
if (!$driverData) {
    echo "Driver not found or invalid ID.";
    exit;
}

// Toastr Notifications based on URL parameters
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
                <form id="company_info" action="update_driver_sbmt" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 750px;" autocomplete="off">
                <input type="hidden" name="driver_id" value="<?php echo $id; ?>">
                <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">   
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">                  
                                <h1 class="display-4 mg-bottom-4px">Add a Driver</h1>
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
                                                $selected = ($cust_id == $driverData['customer_id']) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($cust_id) . '" ' . $selected . '>' . htmlspecialchars($cust_name) . '</option>';
                                            }
                                            $stmtresidcheck->close();
                                        ?>
                                    </select> 
                                    <label>First Name:</label>
                                    <input class="input" type="text" placeholder="Enter First Name" name="first_name" autocomplete="off" value="<?php echo $driverData['first_name']; ?>" required>   
                                    <label>Surname:</label>
                                    <input class="input" type="text" placeholder="Enter Surname" name="surname" autocomplete="off" value="<?php echo $driverData['surname']; ?>">   
                                    <!-- <label>Pin Number:</label> -->
                                    <!-- <input class="input" type="number" placeholder="Enter Pin Number" name="pin_number" autocomplete="off" required>     -->
                                    <!-- <label>Pin Number:</label>
                                    <input class="input" type="text" placeholder="Enter Pin Number" name="pin_number" autocomplete="off" pattern="\d{4}" maxlength="4" minlength="4" title="Please enter a 4-digit PIN" value="<?php echo $driverData['driver_pinnumber']; ?>"> -->
                                    <label>Mobile Number:</label>
                                    <input class="input" type="number" placeholder="Enter Mobile Number" name="mobile_number" autocomplete="off" value="<?php echo $driverData['driver_phone']; ?>">   
                                    <label style="margin-bottom:10px">Enabled:</label>
                                    <select  class="small-dropdown-toggle" style="display: initial; max-width:5rem;" name="driver_enable"  required>
                                        <option value="1" <?php if ($driverData['driver_enabled'] == 1) echo 'selected'; ?>>Yes</option>
                                        <option value="2" <?php if ($driverData['driver_enabled'] == 2) echo 'selected'; ?>>No</option>
                                    </select>
                                    <br>
                                </div>
                            </div>                          
                            <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">   
                                <br>               
                                <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center; ">  
                                    <label>External ID:</label>
                                    <input class="input" type="text" placeholder="Enter External ID" name="external_id" autocomplete="off" value="<?php echo $driverData['external_id']; ?>">   
                                    <label style="margin-bottom: 15px">License Number:</label>
                                    <input class="input" type="text" placeholder="Enter License Number" name="license_number" autocomplete="off" value="<?php echo $driverData['license_number']; ?>">
                                    <label style="margin-bottom: 15px">License Expire:</label>
                                    <input class="input" type="date" id="registration_date" name="registration_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;" value="<?php echo $driverData['license_expire']; ?>">    
                                    <label style="margin-bottom: 15px">License Type:</label>
                                    <input class="input" type="text" placeholder="Enter License Type" name="license_type" autocomplete="off" value="<?php echo $driverData['license_type']; ?>">
                                    <label style="margin-bottom: 15px">Email Address:</label>
                                    <input class="input" type="email" placeholder="Enter Email Address" name="driver_email" autocomplete="off" value="<?php echo $driverData['driver_email']; ?>">
                                </div>
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
                                <div>Update Driver</div>
                            </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<script>
$(document).ready(function() {
    <?php if ($success): ?>
        toastr.success('Driver updated successfully!');
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