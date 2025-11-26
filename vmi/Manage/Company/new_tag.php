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
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <!-- THEME CSS - MUST BE FIRST -->
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <!-- Other CSS files -->
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="style.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>


    <!-- Pass PHP variable to JavaScript -->
    <script>
        var companyId = <?php echo json_encode($companyId); ?>;
    </script>

    <!-- Other scripts (ensure they load after jQuery and Toastr) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>

    <!-- Optional: Toastr Styling Customization -->
    <style>
        /* Customize Toastr position if needed */
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
                    <?php include('../../details/top_menu.php'); ?>
                    <form id="company_info" action="new_tag_sbmt.php" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                        <!-- Division for input fields -->
                        <div class="mg-bottom-24px">
                            <div class="card pd-28px">   
                                <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);">                  
                                    <h1 class="display-4 mg-bottom-4px">Add Card</h1>
                                    <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                        <label style="margin-bottom: 15px">Customer Name:</label>
                                        <select class="small-dropdown-toggle" onchange="customerSelected(this.value, companyId)" name="customer_name" required>  
                                            <option value="0">Select a Customer</option>
                                            <?php
                                            $residcheck = "SELECT customer_id, customer_name FROM Customers WHERE client_id = ?";
        
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
                                        <label>Card Name :</label>
                                        <input class="input" type="text" placeholder="Enter Card Name" name="card_name" required> 
                                        <label>Card Number:</label>
                                        <input class="input" type="number" placeholder="Enter Card Number" name="card_number" id="card_number" autocomplete="off">                     
                                        <label>Card Type:</label>
                                        <select class="small-dropdown-toggle" name="card_type" id="card_type" required>  
                                            <option value="999">Select a Type</option>
                                            <option value="1">RFID(card number)</option>
                                            <option value="0">PIN-Only</option>
                                        </select> 
                                        <label for="expiry-date" style="margin-top: 10px;">Expiry Date:</label>
                                        <input class="input" type="date" id="expiry-date" name="expiry_date" style="max-width: 10rem; background-color: #2a3a6b9e; margin-top: 10px;">           
                                        <label>Enabled:</label>
                                        <input class="" type="checkbox" name="enabled_prompt" id="enabled_prompt" style="justify-self: start;" checked>   
                                    </div>
                                    <br>
                                </div>
                                <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);"> 
                                    <br>
                                    <h1 class="display-4 mg-bottom-4px">Prompts</h1>
                                    <div class="flex-inline">
                                        <div style="display:flex;">
                                            <div class="prompt" style="margin-top: 15px; text-align: center; display: flex; justify-content: center; align-items: center;">
                                                <label for="pin_number" style="justify-self: end; width: 6rem; margin-right: 1rem;">PIN Number:</label>
                                                <input
                                                    class="input"
                                                    type="password"
                                                    id="pin_number"
                                                    name="pin_number"
                                                    placeholder="Empty if not Needed"
                                                    style="max-width: 150px;"
                                                    autocomplete="new-password"
                                                    pattern="\d{4}"
                                                    maxlength="4"
                                                    inputmode="numeric"
                                                    title="Please enter exactly 4 digits."
                                                    oninput="validatePin(this)"
                                                >
                                                <div id="pin_error" style="color: red; margin-left: 10px; display: none;">PIN must be exactly 4 digits.</div>
                                            </div>
                                            <div class="prompt" style="margin-top: 15px; text-align: center; margin-left: 3rem;">   
                                                <label>Change PIN at next transaction:</label>
                                                <input class="" type="checkbox" name="pin_prompt" id="pin_prompt">  
                                            </div> 
                                        </div>
                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;"> 
                                            <label class="label-prompt">Prompt Vehicle:</label>
                                            <select class="small-dropdown-toggle" id="prompt_vehicle" name="prompt_vehicle">  
                                                <option value="999">Disabled</option>
                                                <option value="0">From List</option>
                                                <option value="1">Prompt Only</option>
                                            </select> 
                                            <select class="small-dropdown-toggle" id="list_vehicle" name="list_vehicle">  
                                                <option value="0">Disabled</option>
                                            </select>  
                                        </div>
                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;"> 
                                            <label class="label-prompt">Prompt Driver:</label>
                                            <select class="small-dropdown-toggle" id="prompt_driver" name="prompt_driver">  
                                                <option value="999">Disabled</option>
                                                <option value="0">From List</option>
                                                <option value="1">Prompt Only</option>
                                            </select> 
                                            <select class="small-dropdown-toggle" id="list_driver" name="list_driver">  
                                                <option value="0">Disabled</option>
                                            </select>
                                        </div>
                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;"> 
                                            <label class="label-prompt">Project Number:</label>
                                            <input class="" type="checkbox" name="projectnum_prompt" id="projectnum_prompt">     
                                        </div> 
                                        <div class="prompt" style="margin-top: 15px; text-align: center; display: flex;">
                                            <label class="label-prompt">Odometer:</label>
                                            <input class="" type="checkbox" name="odo_prompt" id="odo_prompt"> 
                                        </div>
                                    </div>          
                                    <br>                            
                                </div>
                                <div class="box" style="align-items: center; border-bottom: 1px solid rgb(20 88 229 / 34%);"> 
                                    <br>
                                    <h1 class="display-4 mg-bottom-4px">Additional Information</h1>
                                    <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 2fr; align-items: center;">
                                        <label>Additional Information:</label>
                                        <textarea class="input" id="additional-info" name="additional_info" maxlength="150" rows="4" cols="50" placeholder="Enter additional information here"></textarea>
                                    </div>
                                    <br>                            
                                </div>
                            </div>
                        </div>
                        <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                            <button type="submit" class="btn-primary w-inline-block">
                                <div class="flex-horizontal gap-column-6px">
                                    <div>Create Tag</div>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
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

    // Handle Form Submission with Fetch API and Toastr
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('company_info');

        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent the default form submission

            // Retrieve form fields
            const cardNumberInput = document.querySelector('input[name="card_number"]');
            const pinNumberInput = document.getElementById('pin_number');
            const cardTypeSelect = document.getElementById('card_type');
            const pinPattern = /^\d{4}$/;
            const pinErrorDiv = document.getElementById('pin_error');

            // Trim values
            const cardNumber = cardNumberInput.value.trim();
            const pinNumber = pinNumberInput.value.trim();
            const cardType = cardTypeSelect.value;

            // Validate based on card type
            if (cardType === '0') {
                // PIN-Only: PIN is required
                if (pinNumber === "") {
                    toastr.error('PIN Number is required for PIN-Only card type.');
                    pinNumberInput.focus();
                    return;
                }
                // Validate PIN format
                if (!pinPattern.test(pinNumber)) {
                    pinErrorDiv.style.display = 'block';
                    pinNumberInput.style.borderColor = 'red';
                    pinNumberInput.focus();
                    toastr.error('PIN must be exactly 4 digits.');
                    return;
                } else {
                    pinErrorDiv.style.display = 'none';
                    pinNumberInput.style.borderColor = 'initial';
                }
            } else if (cardType === '1') {
                // RFID: Card Number is required
                if (cardNumber === "") {
                    toastr.error('Card Number is required for RFID card type.');
                    cardNumberInput.focus();
                    return;
                }
                // Validate Card Number format
                if (!/^\d{6,20}$/.test(cardNumber)) {
                    toastr.error('Card Number must be between 6 and 20 digits.');
                    cardNumberInput.focus();
                    return;
                }
                // Validate PIN if filled
                if (pinNumber !== "") {
                    if (!pinPattern.test(pinNumber)) {
                        pinErrorDiv.style.display = 'block';
                        pinNumberInput.style.borderColor = 'red';
                        pinNumberInput.focus();
                        toastr.error('PIN must be exactly 4 digits.');
                        return;
                    } else {
                        pinErrorDiv.style.display = 'none';
                        pinNumberInput.style.borderColor = 'initial';
                    }
                } else {
                    pinErrorDiv.style.display = 'none';
                    pinNumberInput.style.borderColor = 'initial';
                }
            } else {
                // No card type selected or default
                toastr.error('Please select a Card Type.');
                cardTypeSelect.focus();
                return;
            }

            // Collect form data
            const formData = new FormData(form);

            // Convert FormData to URL-encoded string
            const data = new URLSearchParams();
            for (const pair of formData) {
                data.append(pair[0], pair[1]);
            }

            // Disable the submit button to prevent multiple submissions
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            // Optional: Show a loading indicator
            toastr.info('Submitting your data...', { timeOut: 0, extendedTimeOut: 0 });

            // Send data via Fetch API
            fetch('new_tag_sbmt.php', {
                method: 'POST',
                body: data,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                // Remove loading indicator
                toastr.clear();

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json(); // Parse JSON response
            })
            .then(data => {
                if (data.success) {
                    toastr.success(data.message || 'Form submitted successfully!');
                    console.log('Received Data:', data.received_data); // For debugging
                    form.reset(); // Optional: Reset the form after successful submission
                } else {
                    toastr.error(data.message || 'There was an error submitting the form.');
                    console.error('Error Data:', data.received_data); // For debugging
                }
                // Re-enable the submit button
                submitButton.disabled = false;
            })
            .catch((error) => {
                // Remove loading indicator
                toastr.clear();

                console.error('Fetch Error:', error);
                toastr.error('An unexpected error occurred.');
                // Re-enable the submit button
                submitButton.disabled = false;
            });
        });
    });

    // Define the customerSelected function
    function customerSelected(customerId, companyId) {
        console.log('Customer ID:', customerId);
        console.log('Company ID:', companyId);
        // Add your custom logic here
    }
// Define selectedCustomerId globally
var selectedCustomerId = null;

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

    // Card Type validation elements
    var cardTypeSelect = document.getElementById('card_type');
    var cardNumberInput = document.getElementById('card_number');
    var pinNumberInput = document.getElementById('pin_number');

    // Initially disable list_vehicle and list_driver
    listVehicleSelect.disabled = true;
    listDriverSelect.disabled = true;

    // Event listener for card type changes
    cardTypeSelect.addEventListener('change', function() {
        if (this.value == '0') {
            // PIN-Only selected
            // Disable and clear card number
            cardNumberInput.disabled = true;
            cardNumberInput.value = '';
            cardNumberInput.required = false;
            cardNumberInput.style.backgroundColor = '#cccccc';
            cardNumberInput.style.cursor = 'not-allowed';
            
            // Make PIN required
            pinNumberInput.required = true;
            pinNumberInput.placeholder = 'PIN is Required';
        } else if (this.value == '1') {
            // RFID selected
            // Enable card number and make it required
            cardNumberInput.disabled = false;
            cardNumberInput.required = true;
            cardNumberInput.style.backgroundColor = '';
            cardNumberInput.style.cursor = '';
            
            // Make PIN optional
            pinNumberInput.required = false;
            pinNumberInput.placeholder = 'Optional';
        } else {
            // Default/Select a Type
            // Reset both to default state
            cardNumberInput.disabled = false;
            cardNumberInput.required = false;
            cardNumberInput.style.backgroundColor = '';
            cardNumberInput.style.cursor = '';
            
            pinNumberInput.required = false;
            pinNumberInput.placeholder = 'Empty if not Needed';
        }
    });

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
        fetch('fetch_vehicles.php?companyId=' + companyId + '&customerId=' + customerId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateSelect('list_vehicle', data.vehicles);
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
        fetch('fetch_drivers.php?companyId=' + companyId + '&customerId=' + customerId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateSelect('list_driver', data.drivers);
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
