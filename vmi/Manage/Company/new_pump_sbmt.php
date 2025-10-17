<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
include('../../db/crc.php');
ob_start();


// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $pump_no = $_POST['pump_no'];
    $consoleid = $_POST['consoleid'];
    $site_id = $_POST['site_id'];
    $last_ids = [];
    for($i=1; $i<=$pump_no; $i++){
        $nozzle_number = $_POST['nozzle_number' . $i];
        $nozzle_walktime = $_POST['nozzle_walktime' . $i];
        $nozzle_authtime = $_POST['nozzle_authtime' . $i];
        $nozzle_maxruntime = $_POST['nozzle_maxruntime' . $i];
        $nozzle_noflow = $_POST['nozzle_noflow' . $i];
        $nozzle_product = $_POST['nozzle_product' . $i];
        $nozzle_tank = $_POST['nozzle_tank' . $i];
        $nozzle_pulserate = $_POST['nozzle_pulserate' . $i];
        echo "nozzle_number = $nozzle_number<br>pump_no= $pump_no<br>consoleid= $consoleid<br>nozzle_walktime= $nozzle_walktime<br>nozzle_authtime=$nozzle_authtime<br>nozzle_maxruntime:$nozzle_maxruntime<br>nozzle_noflow:$nozzle_noflow<br>nozzle_product:$nozzle_product<br>nozzle_tank=$nozzle_tank<br>nozzle_pulserate: $nozzle_pulserate<br>";
        
        
        $sqld = "INSERT INTO pumps (uid, tank_id, Nozzle_number, Nozzle_Walk_Time, Nozzle_Auth_Time, Nozzle_Max_Run_Time, Nozzle_No_Flow, Nozzle_Product, Pulse_Rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        // Prepare and bind parameters
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("iiiiiiiis",$consoleid, $nozzle_tank, $nozzle_number, $nozzle_walktime, $nozzle_authtime, $nozzle_maxruntime, $nozzle_noflow, $nozzle_product, $nozzle_pulserate);
        // Execute and check if insert was successful
        if ($stmtd->execute()) {
            header("location: /vmi/clients");
            $last_ids[] = $conn->insert_id;
            pumps_crcdata($consoleid);
        }
        else{
            echo "Site Error<br>";
        }
        $stmtd->close(); 
    }




    // Close the statement and connection
    
    $conn ->close();
    
}
header("location: /vmi/reports");
?>
