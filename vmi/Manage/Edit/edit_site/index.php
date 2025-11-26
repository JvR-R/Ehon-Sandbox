<?php
include('../../../db/dbh2.php'); 
include('../../../db/log.php');
include('../../../db/border.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sites - Ehon Energy Tech</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/Manage/Edit/edit_site/modern_style.css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
            <?php include('../../../details/top_menu.php');?>
                <div class="container-default w-container" style="padding-top: 24px; max-width: 960px;">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Site Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                            <label>Select Site:</label>
                                <select class="small-dropdown-toggle" name="site_namesel" id="site_namesel" onchange="console_select()" required>
                                    <option value="0">Select Site</option>
                                    <?php         
                                    if($companyId == 15100){
                                        $sql = "SELECT Site_id, Site_name FROM Sites ORDER BY Site_name ASC;";
                                        $sqlexec = $conn->prepare($sql);
                                    }
                                    else{
                                        $sql = "SELECT Site_id, Site_name FROM Sites WHERE Client_id = ? ORDER BY Site_name ASC;";
                                        $sqlexec = $conn->prepare($sql);
                                        $sqlexec->bind_param("i", $companyId);
                                    }
                                    $sqlexec->execute();
                                                                            
                                    // Bind result variables
                                    $Site_id = null;
                                    $Site_name = null;
                                    $sqlexec->bind_result($Site_id, $Site_name);             
                                            while ($sqlexec->fetch()) {
                                                echo '<option value="' . htmlspecialchars($Site_id) . '">' . htmlspecialchars($Site_name) . '</option>';
                                            }                                       
                                    ?>
                                </select>
                                <label style="margin-bottom: 15px">Site Name:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder ="Enter Site Name" name="site_name" id="site_name" required>
                                <label for="country">Choose your country:</label>
                                <input class="input" type="text" placeholder="Enter Country" name="site_country" id="site_country" required> 
                                <label>Address:</label>
                                <input class="input" type="text" placeholder="Enter address" name="site_address" id="site_address" required>   
                                <label>City:</label>
                                <input class="input" type="text" placeholder="Enter City" name="site_city" id="site_city" required>   
                                <label>Post Code:</label>
                                <input class="input" type="text" placeholder="Enter Post Code" name="site_postcode" id="site_postcode" required>        
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
                                                echo '<option value="' . htmlspecialchars($id) . '">' . htmlspecialchars($timezone_name) . ' - ' . htmlspecialchars($example_city) . ' ( ' . htmlspecialchars($utc) . ' )</option>';                                    
                                            }
                                            echo '<option value="add">Add a console</option>';
                                            $stmt->close();
                                    ?>
                                </select>            
                                <label>Phone:</label>
                                <input class="input" type="tel" placeholder="Enter phone number" name="site_phone" id="site_phone">
                                <label>Email:</label>
                                <input class="input" type="email" placeholder="Enter contact email" name="site_email" id="site_email">
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
                                            echo '<option value="add">Add a console</option>';
                                        
                                    ?>
                                </select>
                                <div class="list-section">
                                    <h2>Tank List</h2>
                                    <div id="siteList"></div>
                                    <div id="tankEditSection" style="display: none; margin-top: 1rem;">
                                        <div id="tankButtonsContainer" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
                                            <!-- Tank buttons will be dynamically generated here -->
                                        </div>
                                        <div id="totalPumpsInfo" style="margin-bottom: 1rem; color: var(--text-secondary);">
                                            Total Pumps: <span id="totalPumpsCount">0</span> / 4
                                        </div>
                                    </div>
                                    <button type="button" class="btn-primary w-inline-block" id="addTankButton" onclick="redirectToAddTankPage()" style="margin-top: 1rem; display: none;">
                                        Add Tank
                                    </button>
                                </div>
                            </div>
                            <br>
                        </div>
                    </div>
                    <br>
                    
                    <br>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row" style="justify-content: end;">
                    <button type="button" class="btn-primary w-inline-block" id="submit_b" onclick="submitFormWithCase3()">
                        <div class="flex-horizontal gap-column-6px">
                            <div>Update</div>
                        </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tank Edit Modal -->
<div id="tankEditModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h2 id="modalTankTitle">Edit Tank</h2>
            <span class="modal-close" onclick="closeTankModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="tankEditForm">
                <input type="hidden" id="modalTankId" value="">
                <input type="hidden" id="modalUid" value="">
                <input type="hidden" id="modalSiteId" value="">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Tank Capacity (Liters):</label>
                    <input type="number" class="input" id="modalTankCapacity" min="0" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Product:</label>
                    <select class="small-dropdown-toggle" id="modalTankProduct" required>
                        <option value="">Select Product</option>
                        <!-- Products will be loaded dynamically -->
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Number of Pumps:</label>
                    <select class="small-dropdown-toggle" id="modalPumpCount" onchange="updatePumpFields()">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                    <small style="color: var(--text-secondary);">Note: Console can hold maximum 4 pumps total across all tanks</small>
                </div>
                
                <div id="pumpFieldsContainer" style="margin-top: 1.5rem;">
                    <!-- Pump fields will be dynamically generated here -->
                </div>
                
                <div class="buttons-row" style="margin-top: 1.5rem; justify-content: flex-end;">
                    <button type="button" class="btn-minimal" onclick="closeTankModal()">Cancel</button>
                    <button type="button" class="btn-primary" onclick="saveTankData()">Save Tank</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: var(--bg-card, #fff);
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color, #e0e0e0);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.modal-close {
    color: var(--text-secondary, #aaa);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.modal-close:hover,
.modal-close:focus {
    color: var(--text-primary, #000);
}

.modal-body {
    padding: 1.5rem;
}

.pump-field-group {
    background: var(--bg-primary, #f5f5f5);
    border: 1px solid var(--border-light, #ddd);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.pump-field-group h4 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1rem;
}

.tank-button {
    padding: 1rem;
    background: var(--bg-card, #fff);
    border: 2px solid var(--border-color, #e0e0e0);
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: all 0.3s;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.tank-button:hover {
    border-color: var(--primary-color, #007bff);
    background: var(--bg-primary, #f5f5f5);
}

.tank-button.active {
    border-color: var(--primary-color, #007bff);
    background: var(--primary-light, rgba(0,123,255,0.1));
}
</style>

</body>
</html>
<script>
function console_select() {
    var consoleDropdown = document.getElementById("site_namesel");
    var selectedValue = consoleDropdown.value;
    var dataToSend = JSON.stringify({ siteid: selectedValue, case: 1 });
    // console.log("Sending data:", dataToSend);

    fetch('edit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: dataToSend
    })
    .then(response => response.json())
    .then(data => {
        // console.log("Response from server:", data);
        if (data['Site_name']) {
                document.querySelector('input[name="site_name"]').value = data['Site_name'];
            }
        if (data['site_country']) {
            document.querySelector('input[name="site_country"]').value = data['site_country'];
        }
        if (data['site_address']) {
            document.querySelector('input[name="site_address"]').value = data['site_address'];
        }
        if (data['site_city']) {
            document.querySelector('input[name="site_city"]').value = data['site_city'];
        }
        if (data['postcode']) {
            document.querySelector('input[name="site_postcode"]').value = data['postcode'];
        }
        if (data['phone']) {
            document.querySelector('input[name="site_phone"]').value = data['phone'];
        }
        if (data['Email']) {
            document.querySelector('input[name="site_email"]').value = data['Email'];
        }
        if (data['uid']) {
            var consoleDropdown = document.getElementById("consoleid");
            var selectedConsoleId = data['uid'];

            var newOption = document.createElement("option");
            newOption.value = selectedConsoleId;
            newOption.text = selectedConsoleId; // You might want to use a more descriptive text
            newOption.selected = true;

            // Add the new option as the first option in the dropdown
            consoleDropdown.add(newOption, consoleDropdown.options[selectedConsoleId]);


        }
        if (data['timezone']) {
            var timezoneDropdown = document.getElementById('timezone');
            for (var i = 0; i < timezoneDropdown.options.length; i++) {
                if (timezoneDropdown.options[i].value === data['timezone']) {
                    timezoneDropdown.options[i].selected = true;
                    break;
                }
            }
        }
        
        // Check if device_type is 10 (FMS) and show tank edit button
        var deviceType = data['device_type'] || 0;
        var tankEditSection = document.getElementById('tankEditSection');
        if (deviceType == 10) {
            tankEditSection.style.display = 'block';
        } else {
            tankEditSection.style.display = 'none';
        }
        
        console.log(<?php echo $companyId; ?>, selectedConsoleId, selectedValue);
        loadSiteList(<?php echo $companyId; ?>, selectedConsoleId, selectedValue);
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function redirectToAddTankPage() {
    const siteDropdown = document.getElementById('site_namesel');
    const selectedSiteId = siteDropdown.value;

    if (selectedSiteId === "0") {
        alert("Please select a site first.");
        return;
    }

    const url = `add_tank.php?site_id=${selectedSiteId}`;
    window.location.href = url;
}

function redirectToEditTankPage() {
    const siteDropdown = document.getElementById('site_namesel');
    const consoleDropdown = document.getElementById('consoleid');
    const selectedSiteId = siteDropdown.value;
    const selectedConsoleId = consoleDropdown.value;

    if (selectedSiteId === "0" || selectedConsoleId === "0") {
        alert("Please select a site and console first.");
        return;
    }

    const url = `edit_tank.php?site_id=${selectedSiteId}&uid=${selectedConsoleId}`;
    window.location.href = url;
}

var tanksData = {}; // Store tank data globally
var productsData = []; // Store products globally

function loadSiteList(companyId, uid, siteid) {
    var dataToTank = JSON.stringify({ siteid_tank: siteid, case: 2, companyId_tank: companyId, uid_tank: uid });
    fetch('edit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: dataToTank
    })
    .then(response2 => response2.json())
    .then(data => {
        const siteListElement = document.getElementById('siteList');
        siteListElement.innerHTML = ''; // Clear existing content
        data.forEach(site => {
            siteListElement.innerHTML += `
                <div class="site-item">
                    ${site.name}
                </div>
            `;
        });
        
        // Load detailed tank data with pumps
        loadTanksData(companyId, uid, siteid);
    })
    .catch(error => console.error('Error:', error));
}

function loadTanksData(companyId, uid, siteid) {
    var dataToTank = JSON.stringify({ siteid_tank: siteid, case: 4, companyId_tank: companyId, uid_tank: uid });
    fetch('edit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: dataToTank
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Loaded tanks data:', data);
        tanksData = {};
        const tankButtonsContainer = document.getElementById('tankButtonsContainer');
        if (!tankButtonsContainer) {
            console.error('tankButtonsContainer not found!');
            return;
        }
        tankButtonsContainer.innerHTML = '';
        
        // Create buttons for up to 4 tanks
        for (let i = 1; i <= 4; i++) {
            const tank = data.find(t => t.tank_id == i);
            const tankButton = document.createElement('button');
            tankButton.type = 'button';
            tankButton.className = 'tank-button';
            tankButton.id = `tankButton_${i}`;
            tankButton.onclick = () => openTankModal(i, uid, siteid);
            
            if (tank) {
                tanksData[i] = tank;
                tankButton.innerHTML = `
                    <strong>Tank ${i}</strong><br>
                    <small>${tank.tank_name || 'Unnamed'}</small><br>
                    <small>${tank.pumps ? tank.pumps.length : 0} pump(s)</small>
                `;
            } else {
                tankButton.innerHTML = `
                    <strong>Tank ${i}</strong><br>
                    <small>Not configured</small>
                `;
                tankButton.style.opacity = '0.5';
            }
            
            tankButtonsContainer.appendChild(tankButton);
        }
        
        updateTotalPumpsCount();
        
        // Load products for later use
        if (productsData.length === 0) {
            loadProducts();
        }
    })
    .catch(error => console.error('Error loading tanks:', error));
}

function loadProducts(callback) {
    fetch('product_sel.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                productsData = data.data;
                if (callback) callback();
            }
        })
        .catch(error => {
            console.error('Error loading products:', error);
            // Try alternative path
            fetch('../product_sel.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        productsData = data.data;
                        if (callback) callback();
                    }
                })
                .catch(err => console.error('Error loading products from alternative path:', err));
        });
}

function populateProductDropdown(selectedProductId) {
    const productSelect = document.getElementById('modalTankProduct');
    if (productSelect && productsData.length > 0) {
        // Clear existing options
        productSelect.innerHTML = '<option value="">Select Product</option>';
        productsData.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = product.name;
            if (selectedProductId && product.id == selectedProductId) {
                option.selected = true;
            }
            productSelect.appendChild(option);
        });
    }
}

function openTankModal(tankId, uid, siteId) {
    const modal = document.getElementById('tankEditModal');
    const tank = tanksData[tankId] || { tank_id: tankId, capacity: 0, product_id: 0, pumps: [] };
    
    document.getElementById('modalTankId').value = tankId;
    document.getElementById('modalUid').value = uid;
    document.getElementById('modalSiteId').value = siteId;
    document.getElementById('modalTankTitle').textContent = `Edit Tank ${tankId}`;
    document.getElementById('modalTankCapacity').value = tank.capacity || 0;
    
    // Set pump count first
    const pumpCount = tank.pumps ? tank.pumps.length : 0;
    document.getElementById('modalPumpCount').value = pumpCount;
    
    // Update pump fields - this will create the pump input fields
    updatePumpFields();
    
    // Populate existing pump data after fields are created
    setTimeout(() => {
        if (tank.pumps && tank.pumps.length > 0) {
            const container = document.getElementById('pumpFieldsContainer');
            tank.pumps.forEach((pump, index) => {
                const pumpGroup = container.querySelector(`.pump-field-group[data-pump-index="${index}"]`);
                if (pumpGroup) {
                    const nozzleInput = pumpGroup.querySelector(`input[name="nozzle_number"]`);
                    const pulseInput = pumpGroup.querySelector(`input[name="pulse_rate"]`);
                    if (nozzleInput) nozzleInput.value = pump.nozzle_number || '';
                    if (pulseInput) pulseInput.value = pump.pulse_rate || '';
                    if (pump.pump_id) {
                        const hiddenId = document.createElement('input');
                        hiddenId.type = 'hidden';
                        hiddenId.name = 'pump_id';
                        hiddenId.value = pump.pump_id;
                        pumpGroup.appendChild(hiddenId);
                    }
                }
            });
        }
    }, 50);
    
    // Load products into dropdown
    modal.style.display = 'block';
    if (productsData.length === 0) {
        loadProducts(() => {
            populateProductDropdown(tank.product_id || 0);
        });
    } else {
        populateProductDropdown(tank.product_id || 0);
    }
}

function closeTankModal() {
    document.getElementById('tankEditModal').style.display = 'none';
}

function updatePumpFields() {
    const pumpCount = parseInt(document.getElementById('modalPumpCount').value) || 0;
    const container = document.getElementById('pumpFieldsContainer');
    container.innerHTML = '';
    
    // Validate total pumps
    if (!validatePumpCount(pumpCount)) {
        alert('Total pumps cannot exceed 4 across all tanks!');
        document.getElementById('modalPumpCount').value = getCurrentTankPumpCount();
        return;
    }
    
    for (let i = 0; i < pumpCount; i++) {
        const pumpGroup = document.createElement('div');
        pumpGroup.className = 'pump-field-group';
        pumpGroup.setAttribute('data-pump-index', i);
        pumpGroup.innerHTML = `
            <h4>Pump ${i + 1}</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Nozzle Number:</label>
                    <input type="number" class="input" name="nozzle_number" min="1" required>
                </div>
                <div class="form-group">
                    <label>Pulse Rate:</label>
                    <input type="number" class="input" step="0.01" name="pulse_rate" min="0">
                </div>
            </div>
        `;
        container.appendChild(pumpGroup);
    }
    
    updateTotalPumpsCount();
}

function getCurrentTankPumpCount() {
    const currentTankId = parseInt(document.getElementById('modalTankId').value);
    const currentTank = tanksData[currentTankId];
    return currentTank && currentTank.pumps ? currentTank.pumps.length : 0;
}

function validatePumpCount(newPumpCount) {
    const currentTankId = parseInt(document.getElementById('modalTankId').value);
    const currentPumpCount = getCurrentTankPumpCount();
    const otherTanksPumpCount = Object.keys(tanksData).reduce((total, tankId) => {
        if (parseInt(tankId) !== currentTankId && tanksData[tankId] && tanksData[tankId].pumps) {
            return total + tanksData[tankId].pumps.length;
        }
        return total;
    }, 0);
    
    const totalPumps = otherTanksPumpCount + newPumpCount;
    return totalPumps <= 4;
}

function updateTotalPumpsCount() {
    const total = Object.keys(tanksData).reduce((sum, tankId) => {
        if (tanksData[tankId] && tanksData[tankId].pumps) {
            return sum + tanksData[tankId].pumps.length;
        }
        return sum;
    }, 0);
    const totalPumpsElement = document.getElementById('totalPumpsCount');
    if (totalPumpsElement) {
        totalPumpsElement.textContent = total;
    }
}

function saveTankData() {
    const tankId = parseInt(document.getElementById('modalTankId').value);
    const uid = parseInt(document.getElementById('modalUid').value);
    const siteId = parseInt(document.getElementById('modalSiteId').value);
    const capacity = parseInt(document.getElementById('modalTankCapacity').value);
    const productId = parseInt(document.getElementById('modalTankProduct').value);
    const pumpCount = parseInt(document.getElementById('modalPumpCount').value) || 0;
    
    if (!capacity || capacity <= 0) {
        alert('Please enter a valid tank capacity.');
        return;
    }
    
    if (!productId || productId <= 0) {
        alert('Please select a product.');
        return;
    }
    
    // Collect pump data
    const pumps = [];
    const pumpGroups = document.querySelectorAll('#pumpFieldsContainer .pump-field-group');
    pumpGroups.forEach(group => {
        const nozzleNumber = parseInt(group.querySelector('input[name="nozzle_number"]').value);
        const pulseRate = parseFloat(group.querySelector('input[name="pulse_rate"]').value) || 0;
        const pumpIdInput = group.querySelector('input[name="pump_id"]');
        const pumpId = pumpIdInput ? parseInt(pumpIdInput.value) : 0;
        
        if (nozzleNumber > 0) {
            pumps.push({
                pump_id: pumpId,
                nozzle_number: nozzleNumber,
                pulse_rate: pulseRate
            });
        }
    });
    
    // Validate pump count - get current total excluding this tank
    const currentTankId = parseInt(document.getElementById('modalTankId').value);
    const otherTanksPumpCount = Object.keys(tanksData).reduce((total, tankId) => {
        if (parseInt(tankId) !== currentTankId && tanksData[tankId] && tanksData[tankId].pumps) {
            return total + tanksData[tankId].pumps.length;
        }
        return total;
    }, 0);
    
    if (otherTanksPumpCount + pumps.length > 4) {
        alert('Total pumps cannot exceed 4 across all tanks! Currently other tanks have ' + otherTanksPumpCount + ' pump(s).');
        return;
    }
    
    // Prepare data to send
    const dataToSend = JSON.stringify({
        case: 5,
        tank_id: tankId,
        uid: uid,
        site_id: siteId,
        capacity: capacity,
        product_id: productId,
        pumps: pumps
    });
    
    fetch('edit_tank_sbmt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: dataToSend
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Save response:', data);
        if (data.success) {
            closeTankModal();
            // Reload tank data
            const siteDropdown = document.getElementById("site_namesel");
            const siteId = siteDropdown.value;
            const consoleDropdown = document.getElementById("consoleid");
            const uid = consoleDropdown.value;
            
            // Wait a moment for the modal to close, then reload data
            setTimeout(() => {
                loadTanksData(<?php echo $companyId; ?>, uid, siteId);
                console.log('Tank data reloaded after save');
            }, 100);
            
            alert('Tank saved successfully!');
        } else {
            alert('Error saving tank: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving tank:', error);
        alert('Error saving tank. Please check the console for details.');
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('tankEditModal');
    if (event.target == modal) {
        closeTankModal();
    }
}


function submitFormWithCase3() {
    // Gather form data
    var formData = {
        site_namesel: document.getElementById('site_namesel').value,
        site_name: document.getElementById('site_name').value,
        site_country: document.getElementById('site_country').value,
        site_address: document.getElementById('site_address').value,
        site_city: document.getElementById('site_city').value,
        site_postcode: document.getElementById('site_postcode').value,
        timezone: document.getElementById('timezone').value,
        site_phone: document.getElementById('site_phone').value,
        site_email: document.getElementById('site_email').value,
        consoleid: document.getElementById('consoleid').value,
        case: 3 // Add the case=3 as specified
    };
    console.log(formData);
    // Convert formData to JSON string
    var dataToSend = JSON.stringify(formData);

    // POST request with formData
    fetch('edit_site_sbmt', { // Assuming you want to post to this URL, adjust if needed
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: dataToSend
    })
    .then(response => response.json())
    .then(data => {
        console.log("Response from server:", data);
        // Handle response data
        // For example, you might want to redirect or display a success message
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

</script>
