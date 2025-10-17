<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropdown Example with Pumps</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="/vmi/images/favicon.ico" rel="shortcut icon" type="image/x-icon">
</head>
<body>
<section class="topborder">
    <div class="logo">
        <a href="/vmi/">
            <img src="/vmi/images/EHON-VMI.png" alt="Logo" class="logo">
        </a>
    </div>
</section>
<section class="border">
    <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/'">
        <img src="/vmi/images/EHON-VMI_icons-HOME.png" style="width:48px; height: 48px;">    
    </button>
    <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='#'">
        <img src="/vmi/images/EHON-VMI_REPORT-icon.png" style="width:48px; height: 48px;">
    </button>
    <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/clients/'">
        <img src="/vmi/images/EHON-VMI_icons-SITE.png" style="width:48px; height: 48px;">
    </button>
    <?php if($accessLevel===5){
        
    }
    else{
    ?>
    <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/clients/details'">
        <img src="/vmi/images/EHON-VMI_icons-INFO.png" style="width:48px; height: 48px;">        
    </button>
    <?php } ?>
    <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/verification/'">
        <img src="/vmi/images/EHON-VMI_icons-SETTINGS.png" style="width:48px; height: 48px;">  
    </button>
        <form method="post" action="">
            <button type="submit" name="logout" class="button-link" style="cursor:pointer;">
                <img src="/vmi/images/EHON-VMI_icons-LOGOUT.png" style="width:48px; height: 48px;">
            </button>
        </form>
</section>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
        <div class="dashboard-content" style="padding-left:76px;">
            <div class = "dashboard-main-content">
                <div class="container-default w-container">
                    <div class = "select-div">
                        <!-- Dropdown menu for selecting number of tanks -->
                        <select class="small-dropdown-toggle" id="tankCountDropdown" onchange="showTanks()">
                            <option value="0">Select number of tanks</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <br>
                    <!-- Division for input fields -->
                    <div id="inputFieldsContainer">
                        <!-- Dynamic input fields will be appended here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function showTanks() {
    const dropdown = document.getElementById('tankCountDropdown');
    const numberOfTanks = parseInt(dropdown.value);
    const container = document.getElementById('inputFieldsContainer');
    
    // Clear existing fields
    container.innerHTML = '';

    // Generate desired number of input fields
    for (let i = 1; i <= numberOfTanks; i++) {
        const tankDiv = document.createElement('div');
        tankDiv.innerHTML = `
            <div class="mg-bottom-24px">
                <div class="card pd-28px">                    
                    <h1 class="display-4 mg-bottom-4px">Tank ${i}</h1>
                    <div class="grid-2-columns _1-82fr---1fr gap-0">
                        <label style="margin-bottom: 15px">Tank Number:</label>
                        <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter Tank Number" name="tankNumber${i}">
                        
                        <label>Product Name:</label>
                        <input class="input" type="text" placeholder="Enter Product Name" name="productName${i}">
                        
                    </div>
                    <br>
                    <div class="grid-2-columns _1-82fr---1fr gap-0">
                        <label>Capacity:</label>
                        <input class="input" type="text" placeholder="Enter Capacity" name="capacity${i}">                      
                        <label>Select number of pumps:</label>
                        <select class="small-dropdown-toggle" onchange="showPumps(this, 'pumpContainer${i}')">
                            <option value="0">0</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <br>
                    <div class="grid-2-columns _1-82fr---1fr gap-0">
                    <div id="pumpContainer${i}">
                        <!-- Pump input fields will be appended here -->
                    </div>
                    <br><br>
                </div>
            </div>
        `;

        container.appendChild(tankDiv);
    }
}

function showPumps(selectElement, containerId) {
    const numberOfPumps = parseInt(selectElement.value);
    const container = document.getElementById(containerId);
    
    // Clear existing fields
    container.innerHTML = '';

    // Generate desired number of pump input fields
    for (let i = 1; i <= numberOfPumps; i++) {
        const pumpDiv = document.createElement('div');
        pumpDiv.innerHTML = `
            <label>Pump Number ${i}:</label>
            <input type="text" placeholder="Enter Pump Number" name="pumpNumber${i}">
            <br>
        `;

        container.appendChild(pumpDiv);
    }
}

</script>

</body>
</html>
