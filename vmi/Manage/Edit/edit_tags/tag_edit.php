<?php
include('../../../db/dbh2.php');
include('../../../db/log.php'); //
include('../../../db/border.php');

// Define $companyId based on $client_id from log.php


// Check if 'id' parameter is present in the URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Query to fetch the tag information based on the provided ID and client_id
    $query = "SELECT * FROM client_tags WHERE id = ? AND client_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the tag data if found
    if ($result->num_rows > 0) {
        $tagData = $result->fetch_assoc();
    } else {
        $tagData = null; // If no data found
    }
    $stmt->close();
} else {
    $tagData = null; // If no 'id' parameter is found
}

// If $tagData is null, display an error and exit
if (!$tagData) {
    echo "Tag not found or invalid ID.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Tag</title>
    <!-- Include necessary CSS and JS files -->
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="style.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">

    <!-- jQuery Library -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        var companyId = <?php echo json_encode($companyId); ?>;
        var selectedCustomerId = <?php echo json_encode($tagData['customer_id']); ?>;
    </script>

    <!-- Other scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>

    <!-- Optional: Toastr Styling Customization -->
    <style>
        #toast-container {
            top: 60px;
            right: 12px;
        }
    </style>

    <script type="text/javascript">
        !function(o,c){
            var n=c.documentElement,t=" w-mod-";
            n.className+=t+"js",
            ("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")
        }(window,document);
    </script>

    <link href="/vmi/images/favicon.ico" rel="shortcut icon" type="image/x-icon">
</head>
<body>
    <div style="opacity:1" class="page-wrapper">
        <div class="dashboard-main-section">
            <div class="dashboard-content">
                <div class="sidebar-spacer"></div>
                <div class="sidebar-spacer2"></div>
                <div class="dashboard-main-content">
                    <?php include('../../../details/top_menu.php'); ?>
                    <form id="company_info" action="update_tag_sbmt.php" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                        <div class="mg-bottom-24px">
                            <div class="card pd-28px">
                                <!-- Card Information Section -->
                                <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">
                                    <h1 class="display-4 mg-bottom-4px">Edit Card</h1>
                                    <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
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
                                                $selected = ($cust_id == $tagData['customer_id']) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($cust_id) . '" ' . $selected . '>' . htmlspecialchars($cust_name) . '</option>';
                                            }
                                            $stmtresidcheck->close();
                                            ?>
                                        </select>

                                        <label>Card Name :</label>
                                        <input class="input" type="text" name="card_name" value="<?php echo htmlspecialchars($tagData['card_name']); ?>" required>

                                        <label>Card Number:</label>
                                        <input class="input" type="number" name="card_number" value="<?php echo htmlspecialchars($tagData['card_number']); ?>" autocomplete="off">

                                        <label>Card Type:</label>
                                        <select class="small-dropdown-toggle" name="card_type" required>
                                            <option value="999">Select a Type</option>
                                            <option value="1" <?php if ($tagData['card_type'] == 1 || $tagData['card_type'] == 2) echo 'selected'; ?>>RFID</option>
                                            <option value="0" <?php if ($tagData['card_type'] == 0) echo 'selected'; ?>>PIN-Only</option>
                                            <option value="3" <?php if ($tagData['card_type'] == 3) echo 'selected'; ?>>Mobile</option>
                                            <option value="4" <?php if ($tagData['card_type'] == 4) echo 'selected'; ?>>iButton</option>
                                            <option value="5" <?php if ($tagData['card_type'] == 5) echo 'selected'; ?>>White Card</option>
                                        </select>

                                        <label for="expiry-date" style="margin-top: 10px;">Expiry Date:</label>
                                        <input class="input" type="date" id="expiry-date" name="expiry_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;" value="<?php echo htmlspecialchars($tagData['expiry_date']); ?>">

                                        <label>Enabled:</label>
                                        <input type="checkbox" name="enabled_prompt" id="enabled_prompt" <?php if ($tagData['enabled_prompt']) echo 'checked'; ?>>
                                    </div>
                                    <br>
                                </div>

                                <!-- Prompts Section -->
                                <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">
                                    <br>
                                    <h1 class="display-4 mg-bottom-4px">Prompts</h1>
                                    <div class="flex-inline">
                                        <div style="display:flex;">
                                            <div class="prompt" style="margin-top: 15px; text-align: center; display: flex; justify-content: center; align-items: center;">
                                                <label for="pin_number" style="justify-self: end; width: 6rem; margin-right: 1rem;">PIN Number:</label>
                                                <input class="input" type="password" id="pin_number" name="pin_number" value="<?php echo htmlspecialchars($tagData['pin_number']); ?>" placeholder="Enter PIN" style="max-width: 150px;" autocomplete="new-password" pattern="\d{4}" maxlength="4" inputmode="numeric" title="Please enter exactly 4 digits." oninput="validatePin(this)">

                                                <div id="pin_error" style="color: red; margin-left: 10px; display: none;">PIN must be exactly 4 digits.</div>
                                            </div>
                                            <div class="prompt" style="margin-top: 15px; text-align: center; margin-left: 3rem;">
                                                <label>Change PIN at next transaction:</label>
                                                <input type="checkbox" name="pin_prompt" id="pin_prompt" <?php if ($tagData['pin_prompt']) echo 'checked'; ?>>
                                            </div>
                                        </div>

                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;">
                                            <label class="label-prompt">Prompt Vehicle:</label>
                                            <select class="small-dropdown-toggle" style="display: initial;" id="prompt_vehicle" name="prompt_vehicle">
                                                <option value="999" <?php if ($tagData['prompt_vehicle'] == 999) echo 'selected'; ?>>Disabled</option>
                                                <option value="0" <?php if ($tagData['prompt_vehicle'] == 0) echo 'selected'; ?>>From List</option>
                                                <option value="1" <?php if ($tagData['prompt_vehicle'] == 1) echo 'selected'; ?>>Prompt Only</option>
                                            </select>
                                            <select class="small-dropdown-toggle" style="display: initial; margin-left: 1rem;" id="list_vehicle" name="list_vehicle">
                                                <option value="0">Disabled</option>
                                            </select>
                                        </div>

                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;">
                                            <label class="label-prompt">Prompt Driver:</label>
                                            <select class="small-dropdown-toggle" style="display: initial;" id="prompt_driver" name="prompt_driver">
                                                <option value="999" <?php if ($tagData['driver_prompt'] == 999) echo 'selected'; ?>>Disabled</option>
                                                <option value="0" <?php if ($tagData['driver_prompt'] == 0) echo 'selected'; ?>>From List</option>
                                                <option value="1" <?php if ($tagData['driver_prompt'] == 1) echo 'selected'; ?>>Prompt Only</option>
                                            </select>
                                            <select class="small-dropdown-toggle" style="display: initial; margin-left: 1rem;" id="list_driver" name="list_driver">
                                                <option value="0">Disabled</option>
                                            </select>
                                        </div>

                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;">
                                            <label class="label-prompt">Project Number:</label>
                                            <input type="checkbox" name="projectnum_prompt" id="projectnum_prompt" <?php if ($tagData['projectnum_prompt']) echo 'checked'; ?>>
                                        </div>

                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;">
                                            <label class="label-prompt">Odometer:</label>
                                            <input type="checkbox" name="odo_prompt" id="odo_prompt" <?php if ($tagData['odo_prompt']) echo 'checked'; ?>>
                                        </div>
                                    </div>
                                    <br>
                                </div>

                                <!-- Additional Information Section -->
                                <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">
                                    <br>
                                    <h1 class="display-4 mg-bottom-4px">Additional Information</h1>
                                    <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 2fr; align-items: center;">
                                        <label>Additional Information:</label>
                                        <textarea class="input" id="additional-info" name="additional_info" maxlength="150" rows="4" cols="50" placeholder="Enter additional information here"><?php echo htmlspecialchars($tagData['additional_info']); ?></textarea>
                                    </div>
                                    <br>
                                </div>
                            </div>
                        </div>
                        <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($tagData['id']); ?>">
                            <button type="submit" class="btn-primary w-inline-block">
                                <div class="flex-horizontal gap-column-6px">
                                    <div>Update Card</div>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Code -->
    <script>
    // PIN Validation Script
    function validatePin(input) {
        const errorDiv = document.getElementById('pin_error');
        const pinPattern = /^\d{4}$/;

        // Remove any non-digit characters
        input.value = input.value.replace(/\D/g, '');

        // Trim the input to 4 digits
        if (input.value.length > 4) {
            input.value = input.value.slice(0, 4);
        }

        // Validate the PIN
        if (pinPattern.test(input.value)) {
            errorDiv.style.display = 'none';
            input.style.borderColor = 'initial'; // Reset border color if needed
        } else {
            errorDiv.style.display = 'block';
            input.style.borderColor = 'red'; // Highlight the input field
        }
    }

    // Define selectedCustomerId globally (already set from PHP)
    // var selectedCustomerId = null; // Already set from PHP

    // Define the customerSelected function
    function customerSelected(customerId, companyId) {
        console.log('Customer ID:', customerId);
        console.log('Company ID:', companyId);
        selectedCustomerId = customerId;

        // Optionally reset prompts and lists when customer changes
        resetPromptsAndLists();
    }

    // Function to reset prompts and lists when customer changes
    function resetPromptsAndLists() {
        // Reset prompt_vehicle and prompt_driver to Disabled
        document.getElementById('prompt_vehicle').value = '999';
        document.getElementById('prompt_driver').value = '999';

        // Disable and clear list_vehicle and list_driver
        disableAndClearSelect('list_vehicle');
        disableAndClearSelect('list_driver');
    }

    // Function to disable and clear a select element
    function disableAndClearSelect(selectId) {
        var selectElement = document.getElementById(selectId);
        selectElement.disabled = true;
        selectElement.innerHTML = '<option value="0">Disabled</option>';
    }

    // Add event listeners after DOM content is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Get references to select elements
        var promptVehicleSelect = document.getElementById('prompt_vehicle');
        var listVehicleSelect = document.getElementById('list_vehicle');

        var promptDriverSelect = document.getElementById('prompt_driver');
        var listDriverSelect = document.getElementById('list_driver');

        // Initially disable list_vehicle and list_driver
        listVehicleSelect.disabled = true;
        listDriverSelect.disabled = true;

        // Check initial values of prompt_vehicle and prompt_driver
        if (promptVehicleSelect.value == '0') {
            listVehicleSelect.disabled = false;
            fetchVehicleList(companyId, selectedCustomerId);
        }

        if (promptDriverSelect.value == '0') {
            listDriverSelect.disabled = false;
            fetchDriverList(companyId, selectedCustomerId);
        }

        // Event listener for prompt_vehicle
        promptVehicleSelect.addEventListener('change', function() {
        if (this.value == '0') {
            if (selectedCustomerId != null && selectedCustomerId != '0') {
                // Enable the list_vehicle select
                listVehicleSelect.disabled = false;
                // Fetch the list of vehicles
                fetchVehicleList(companyId, selectedCustomerId);
            } else {
                toastr.error('Please select a customer first.');
                this.value = '999'; // Reset to Disabled
            }
        } else {
            // Disable the list_vehicle select
            disableAndClearSelect('list_vehicle');
        }
    });

    // Event listener for prompt_driver
    promptDriverSelect.addEventListener('change', function() {
        if (this.value == '0') {
            if (selectedCustomerId != null && selectedCustomerId != '0') {
                // Enable the list_driver select
                listDriverSelect.disabled = false;
                // Fetch the list of drivers
                fetchDriverList(companyId, selectedCustomerId);
            } else {
                toastr.error('Please select a customer first.');
                this.value = '999'; // Reset to Disabled
            }
        } else {
            // Disable the list_driver select
            disableAndClearSelect('list_driver');
        }
    });

        // Function to fetch vehicle list
        function fetchVehicleList(companyId, customerId) {
            fetch('../../Company/fetch_vehicles.php?companyId=' + companyId + '&customerId=' + customerId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateSelect('list_vehicle', data.vehicles);
                        // Set the selected value if it exists
                        <?php if (!empty($tagData['list_vehicle'])): ?>
                            document.getElementById('list_vehicle').value = '<?php echo htmlspecialchars($tagData['list_vehicle']); ?>';
                        <?php endif; ?>
                    } else {
                        toastr.error(data.message || 'Error fetching vehicle list.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching vehicle list:', error);
                    toastr.error('Error fetching vehicle list.');
                });
        }

        // Function to fetch driver list
        function fetchDriverList(companyId, customerId) {
            fetch('../../Company/fetch_drivers.php?companyId=' + companyId + '&customerId=' + customerId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateSelect('list_driver', data.drivers);
                        // Set the selected value if it exists
                        <?php if (!empty($tagData['list_driver'])): ?>
                            document.getElementById('list_driver').value = '<?php echo htmlspecialchars($tagData['list_driver']); ?>';
                        <?php endif; ?>
                    } else {
                        toastr.error(data.message || 'Error fetching driver list.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching driver list:', error);
                    toastr.error('Error fetching driver list.');
                });
        }

        // Function to populate select elements with data
        function populateSelect(selectId, items) {
            var selectElement = document.getElementById(selectId);
            // Clear existing options
            selectElement.innerHTML = '';
            // Add new options
            items.forEach(function(item) {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.name;
                selectElement.appendChild(option);
            });
        }
    });
    </script>
</body>
</html>
