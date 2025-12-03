<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');

if (isset($_GET['deviceid']) && isset($_GET['site_id'])) {
$consoleid = $_GET['deviceid'];
$site_id = $_GET['site_id'];
?>
<script type="text/javascript">
    var consoleId = "<?php echo $consoleid; ?>";
    var siteId = "<?php echo $site_id; ?>";
</script>
<?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Site</title>
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
                <form method="post" action="new_pump_sbmt" class="container-default w-container" style="padding-top: 24px; max-width: 960px;">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Nozzle Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                            <label>Select number of Nozzle:</label>
                            <div class = "select-div">
                                <!-- Dropdown menu for selecting number of pumps -->
                                <select class="small-dropdown-toggle" id="pumpCountDropdown" name="pump_no" onchange="showPumps()">
                                    <option value="0">Click Finish if not needed</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>    
                            <br>
                        </div>
                    </div>
                    <div id="inputFieldsContainer">
                        <!-- Dynamic input fields will be appended here -->
                    </div>
                    <br>
                    <input type="hidden" name="site_id" value="<?php echo htmlspecialchars($site_id); ?>">
                    <input type="hidden" name="consoleid" value="<?php echo htmlspecialchars($consoleid); ?>">
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row" style="justify-content: end;">
                        <button type="submit" class="btn-primary w-inline-block">
                            <div class="flex-horizontal gap-column-6px">
                                <div>Finish</div>
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
