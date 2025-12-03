<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');

if (isset($_GET['site_id'])) {
    $site_id = $_GET['site_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Tank - Ehon Energy Tech</title>
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
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
     <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class="dashboard-main-content">
            <?php include('../../details/top_menu.php');?>
                <div class="container-default w-container" style="padding-top: 24px; max-width: 960px;">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Tank Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 1.82fr 1fr;">
                                <label>Tank Number:</label>
                                <input class="input" type="number" placeholder="Enter Tank Number" id="tank_number" required>
                                <label>Tank Name:</label>
                                <input class="input" type="text" placeholder="Enter Tank Name" id="tank_name" required>
                                <label>Select Product:</label>
                                <select class="small-dropdown-toggle" id="product_name" name="product_name">
                                    <option value="000">Choose a product</option>
                                </select>
                                <label for="checkbox_tg">Tank Gauge?</label>
                                <input type="checkbox" id="checkbox_tg" style="transform: scale(0.7);">
                                <div class="reorder-level-capacity" id="reorder_capacity" style="display: none; margin-top: 10px;">
                                    <div style="display: grid; grid-template-columns: 1.82fr 1fr;">
                                        <label>Capacity:</label>
                                        <input class="input" type="text" placeholder="Enter Capacity" id="capacity">
                                        <label>Re-Order Level:</label>
                                        <input class="input" type="number" placeholder="Enter Re-Order Level" id="reorder_level">
                                    </div>
                                </div>
                                <div id="spacer" style="display:none;"></div>
                                <label for="checkbox_piusi">Piusi?</label>
                                <input type="checkbox" id="checkbox_piusi" style="transform: scale(0.7);">
                                <div class="piusi" id="piusi" style="display: none; margin-top: 10px;">
                                    <div style="display: grid; grid-template-columns: 1.82fr 1fr;">
                                        <label>Piusi:</label>
                                        <input class="input" type="text" placeholder="Enter Piusi Serial" id="piusi_serial">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="site_id" value="<?php echo htmlspecialchars($site_id); ?>">
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row" style="justify-content: end;">
                        <button type="button" class="btn-primary w-inline-block" id="submit_button">
                            <div class="flex-horizontal gap-column-6px">
                                <div>Next</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();

    const tgCheckbox = document.getElementById('checkbox_tg');
    const rcDiv = document.getElementById('reorder_capacity');
    const spacer = document.getElementById('spacer');
    tgCheckbox.addEventListener('change', function() {
        rcDiv.style.display = this.checked ? 'block' : 'none';
        spacer.style.display = this.checked ? 'block' : 'none';
    });

    const piusiCheckbox = document.getElementById('checkbox_piusi');
    const piusiDiv = document.getElementById('piusi');
    piusiCheckbox.addEventListener('change', function() {
        piusiDiv.style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('submit_button').addEventListener('click', submitTank);
});

function loadProducts() {
    fetch('product_sel.php')
        .then(response => response.json())
        .then(response => {
            if (response.status === 'success') {
                updateProductDropdowns(response.data);
            } else {
                console.error('Error loading products:', response.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function updateProductDropdowns(products) {
    const dropdown = document.getElementById('product_name');
    dropdown.length = 1;

    products.forEach(product => {
        const option = document.createElement('option');
        option.value = product.id;
        option.textContent = product.name;
        dropdown.appendChild(option);
    });
}

function submitTank() {
    const siteId = document.getElementById('site_id').value;
    const tankNumber = document.getElementById('tank_number').value;
    const tankName = document.getElementById('tank_name').value;
    const productName = document.getElementById('product_name').value;
    const checkboxTg = document.getElementById('checkbox_tg').checked;
    const capacity = document.getElementById('capacity').value;
    const reorderLevel = document.getElementById('reorder_level').value;
    const checkboxPiusi = document.getElementById('checkbox_piusi').checked;
    const piusiSerial = document.getElementById('piusi_serial').value;

    // Validate inputs
    if (!validateInputs(tankNumber, tankName, productName, checkboxTg, capacity, reorderLevel, checkboxPiusi, piusiSerial)) {
        return;
    }

    const data = {
        site_id: siteId,
        tank_number: tankNumber,
        tank_name: tankName,
        product_name: productName,
        checkbox_tg: checkboxTg,
        capacity: capacity,
        reorder_level: reorderLevel,
        checkbox_piusi: checkboxPiusi,
        piusi_serial: piusiSerial
    };

    fetch('add_tank_sbmt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(response => {
        if (response.status === 'success') {
            toastr.success('Tank information submitted successfully.');
        } else {
            toastr.error('Error submitting tank information: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('An error occurred while submitting the tank information.');
    });
}

function validateInputs(tankNumber, tankName, productName, checkboxTg, capacity, reorderLevel, checkboxPiusi, piusiSerial) {
    let valid = true;

    if (tankNumber < 1 || tankNumber > 6) {
        toastr.error('Tank number must be between 1 and 6.');
        valid = false;
    }

    if (!tankName) {
        toastr.error('Tank name is required.');
        valid = false;
    }

    if (productName === '000') {
        toastr.error('You must choose a product.');
        valid = false;
    }

    if (checkboxTg) {
        if (!capacity) {
            toastr.error('Capacity is required when Tank Gauge is checked.');
            valid = false;
        }
        if (!reorderLevel) {
            toastr.error('Re-Order Level is required when Tank Gauge is checked.');
            valid = false;
        }
    }

    if (checkboxPiusi && !piusiSerial) {
        toastr.error('Piusi Serial is required when Piusi is checked.');
        valid = false;
    }

    return valid;
}
</script>
</body>
</html>
