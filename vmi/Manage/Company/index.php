<?php
include('../../db/dbh2.php');
include('../../db/log.php');   
include('../../db/border.php');  
// Check if we have a message
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHON - Console Dispatch</title>
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
                <form action="console_association" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 960px;">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Console Dispatch</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Console Number:</label>                             
                                <?php
                                $sqlInStock = "SELECT uid, device_id FROM console WHERE console_status = 'In Stock' ORDER BY device_id ASC";
                                if ($stmtInStock = $conn->prepare($sqlInStock)) {
                                    $stmtInStock->execute();
                                    $stmtInStock->bind_result($opt_uid, $opt_device_id);
                                    echo '<select class="small-dropdown-toggle" name="console_number" required>';
                                    echo '<option value="">Select console (In Stock)</option>';
                                    $hasOptions = false;
                                    while ($stmtInStock->fetch()) {
                                        $hasOptions = true;
                                        echo '<option value="' . htmlspecialchars((string)$opt_uid) . '">' . htmlspecialchars((string)$opt_uid) . ' - ' . htmlspecialchars($opt_device_id) . '</option>';
                                    }
                                    if (!$hasOptions) {
                                        echo '<option value="" disabled>No consoles In Stock</option>';
                                    }
                                    echo '</select>';
                                    $stmtInStock->close();
                                } else {
                                    echo '<select class="small-dropdown-toggle" name="console_number" required>';
                                    echo '<option value="" disabled>Error loading consoles</option>';
                                    echo '</select>';
                                }
                                ?>                  
                            </div>
                            <br>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label>Order Number:</label>
                                <input class="input" type="text" placeholder="Enter the Order Number" name="order_number" required>                      
                            </div>
                            <br>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label>Dispatch To:</label>
                                    
                                    <?php 
                                    if($accessLevel == 1 || $accessLevel == 2){
                                        ?>
                                    <select class="small-dropdown-toggle" id="dispatch_type" name="dispatch_type" onchange="Showcompany()">
                                    <option value="1">Distributor</option>                               
                                    <option value="2">Reseller</option>
                                    <option value="3" selected>Client</option>
                                    </select> 
                                    <?php } elseif ($accessLevel == 4 || $accessLevel == 5) { ?>
                                        <select class="small-dropdown-toggle" id="dispatch_type" name="dispatch_type" onchange="Showcompany()">
                                        <option value="2">Reseller</option>
                                        <option value="3" selected>Client</option>
                                        </select> 
                                        <?php } elseif ($accessLevel == 6 || $accessLevel == 7) { ?>
                                            Not required
                                        <?php } ?>
                                        
                                                    
                            </div>
                            <br>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;" id="select_cont">        

                            </div>
                            <br>
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                        <button type="submit" class="btn-primary w-inline-block">
                            <div class="flex-horizontal gap-column-6px">
                                <div>Dispatch</div>
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

