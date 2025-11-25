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
                                                echo '<option value="' . htmlspecialchars($id) . '" ' . $selected . '>' . htmlspecialchars($timezone_name) . ' - ' . htmlspecialchars($example_city) . ' ( ' . htmlspecialchars($utc) . ' )</option>';                                    
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
                                        <button type="button" class="btn-minimal" id="editTanksButton" onclick="redirectToEditTankPage()">
                                            Edit Tanks & Pumps
                                        </button>
                                    </div>
                                    <button type="button" class="btn-primary w-inline-block" id="addTankButton" onclick="redirectToAddTankPage()" style="margin-top: 1rem;">
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

function loadSiteList(companyId, uid, siteid) {
    var dataToTank = JSON.stringify({ siteid_tank: siteid, case: 2, companyId_tank: companyId, uid_tank: uid });
    console.log("Sending data to Tank:", dataToTank);
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
        console.log("Response from server:", data);
        data.forEach(site => {
            siteListElement.innerHTML += `
                <div class="site-item">
                    ${site.name}
                </div>
            `;
        });
    })
    .catch(error => console.error('Error:', error));
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
