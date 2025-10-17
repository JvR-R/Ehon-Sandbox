<?php
session_start();

// Set session to expire after 15 minutes
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 900) {
    // Session started more than 15 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
    header("Location: /"); // Redirect to home page or any other page
    exit();
}

// Include the database connection
include("../db/dbh2.php");

if (isset($_GET['console-id'])) {
    // Assign the value of 'console-id' to the $uid variable
    $uid = $_GET['console-id'];
    $sql = "SELECT cs.Site_name, ts.tank_name, ts.tank_id, FORMAT(ts.current_volume, 0) as current_volume, ts.current_percent, FORMAT(ts.ullage, 0) as ullage, ts.temperature, ps.product_name, ts.capacity, ts.tank_uid FROM Sites as cs join Clients clc on clc.client_id = cs.client_id join Tanks as ts on (cs.client_id, cs.uid, cs.Site_id) = (ts.client_id, ts.uid, ts.Site_id) JOIN products as ps on ps.product_id = ts.product_id where cs.uid = ?;";
    $result = $conn->prepare($sql);
    $result->bind_param("i", $uid);
    $result->execute();
    $result_set = $result->get_result();

    if ($result_set->num_rows > 0) {
        // Initialize the $site_name variable
        $site_name = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EHON Energy Tech - Calibration</title>
  <link rel="stylesheet" href="/vmi/css/normalize.css">
  <link rel="stylesheet" href="/vmi/css/style_rep.css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="/vmi/css/toastr.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="dashboard-content" style="padding-left: 0px;">
    <div style="height: 1rem; z-index: 100; text-align: center;"><img src="/vmi/images/EHON-VMI.png" alt="Logo" style="width: 10rem; height: auto;"></div>
      <div class="dashboard-main-content">
        <div class="container-default w-container" style="max-width: 1920px;">
            <div class="mg-bottom-32px">
              <div class="_2-items-wrap-container">
                <div id="w-node-_4e606362-eabc-753a-260a-8d85f152b3ca-6534f24f">
                  <h1 class="display-4 mg-bottom-4px" style="color: #EC1C1C; font-size: 38px;">Site: <?php echo $site_name; ?></h1>
                  <p class="mg-bottom-0"></p>
                </div>
              </div>
            </div>
            <?php
            while ($row = $result_set->fetch_assoc()) {
                $site_name = $row['Site_name'];
                $tank_name = $row['tank_name'];
                $tank_number = $row['tank_id'];
                $tank_uid = $row['tank_uid'];
                $current_volume = $row['current_volume'];
                $ullage = $row['ullage'];
                $temperature = $row['temperature'];
                $product_name = $row['product_name'];
                $capacity = $row['capacity'];
            ?>
            <div class="mg-bottom-40px">
                <div class="card">
                    <div class="card overflow-hidden">
                        <div class="_2-items-wrap-container pd-32px---28px">
                        <div class="text-300 medium color-neutral-100" style="font-size: 32px;"><?php echo "Tank $tank_number Information"; ?></div>
                        </div>
                        <div class="table-main-container">
                            <div class="recent-orders-table-row2" style="grid-template-columns: 1fr 1fr;">
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a5f-1b609a5a" class="flex align-center">
                                    <div class="paragraph-small color-neutral-100" style="font-size: 36px">Tank Name</div>
                                </div>
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a72-1b609a5a" class="paragraph-small color-neutral-100" style="font-size: 32px; justify-self: center;"><?php echo $tank_name; ?></div>
                            </div>   
                            <div class="recent-orders-table-row2" style="grid-template-columns: 1fr 1fr;">
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a5f-1b609a5a" class="flex align-center">
                                    <div class="paragraph-small color-neutral-100" style="font-size: 36px">Tank Product</div>
                                </div>
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a72-1b609a5a" class="paragraph-small color-neutral-100" style="font-size: 32px; justify-self: center;"><?php echo $product_name; ?></div>
                            </div>    
                            <div class="recent-orders-table-row2" style="grid-template-columns: 1fr 1fr;">
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a5f-1b609a5a" class="flex align-center">
                                    <div class="paragraph-small color-neutral-100" style="font-size: 36px">Current Volume</div>
                                </div>
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a72-1b609a5a" class="paragraph-small color-neutral-100" style="font-size: 32px; justify-self: center;"><?php echo $current_volume . "L"; ?></div>
                            </div>     
                            <div class="recent-orders-table-row2" style="grid-template-columns: 1fr 1fr;">
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a5f-1b609a5a" class="flex align-center">
                                    <div class="paragraph-small color-neutral-100" style="font-size: 36px">Ullage</div>
                                </div>
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a72-1b609a5a" class="paragraph-small color-neutral-100" style="font-size: 32px; justify-self: center;"><?php echo $ullage . "L"; ?></div>
                            </div>  
                            <div class="recent-orders-table-row2" style="grid-template-columns: 1fr 1fr;">
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a5f-1b609a5a" class="flex align-center">
                                    <div class="paragraph-small color-neutral-100" style="font-size: 36px">Fluid Temperature</div>
                                </div>
                                <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a72-1b609a5a" class="paragraph-small color-neutral-100" style="font-size: 32px; justify-self: center;"><?php echo $temperature . "ºC"; ?></div>
                            </div>              
                        </div>
                    </div>                   
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <button class="btn-primary" style="font-size: 30px;" id="calibrate-btn-<?php echo $tank_number; ?>"
                        data-tank-id="<?php echo $tank_number; ?>"
                        data-tank-uid="<?php echo $tank_uid; ?>"
                        data-tank-name="<?php echo $tank_name; ?>"
                        data-product-name="<?php echo $product_name; ?>"
                        data-current-volume="<?php echo $current_volume; ?>"
                        data-ullage="<?php echo $ullage; ?>"
                        data-temperature="<?php echo $temperature; ?>"
                        data-capacity="<?php echo $capacity; ?>"
                        onclick="calibrateTank(this)">Calibrate
                    </button>
                </div>
            </div>
            <div class="spacer" style="min-height: 2rem;"> </div>
            <?php } ?>
        </div>
      </div>    
    </div>                      
  </div>
</div>

<script>
function calibrateTank(btn) {
  const tankId = btn.dataset.tankId;
  const tankUid = btn.dataset.tankUid; // Correct the case here
  const tankName = btn.dataset.tankName;
  const productName = btn.dataset.productName;
  const currentVolume = btn.dataset.currentVolume;
  const ullage = btn.dataset.ullage;
  const temperature = btn.dataset.temperature;
  const capacity = btn.dataset.capacity;

  // Prompt the user to input the current volume
  const newVolume = prompt("Enter the new current volume (in liters):");

  if (newVolume !== null) {
    // Perform calibration logic here, using the retrieved data
    console.log(`Calibrating Tank ${tankId}: ${tankName} (${productName})`);
    console.log(`Current Volume: ${currentVolume}L, Ullage: ${ullage}L, Temperature: ${temperature}°C, Capacity: ${capacity}L`);
    console.log(`New Volume: ${newVolume}L`);

    // Send the data via AJAX
    $.ajax({
      url: 'calibrate_tank.php',
      method: 'POST',
      data: {
        tank_id: tankId,
        tank_uid: tankUid, // Include tank_uid in the data
        tank_name: tankName,
        product_name: productName,
        current_volume: newVolume,
        ullage: ullage,
        temperature: temperature,
        capacity: capacity
      },
      success: function(response) {
        alert(response);
        location.reload();
      },
      error: function(xhr, status, error) {
        console.error(error);
        alert("An error occurred while calibrating the tank.");
      }
    });
  }
}
</script>
</body>
</html>

<?php
    } else {
        echo '<script type="text/javascript">';
        echo 'alert("Console Not Found!");';
        echo 'window.location.href = "/";';  // Redirect to another page after showing the alert
        echo '</script>';
        exit;
    }
    $result->close();
} else {
    // Handle the case where 'console-id' is not set
    echo '<script type="text/javascript">';
    echo 'alert("Console Not Found!");';
    echo 'window.location.href = "/";';  // Redirect to another page after showing the alert
    echo '</script>';
    exit;
}
?>
