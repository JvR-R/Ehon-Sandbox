<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
ob_start();


// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $site_name = $_POST['site_name'];
    $site_country = $_POST['site_country'];
    $site_address = $_POST['site_address'];
    $site_city = $_POST['site_city'];
    $site_postcode = $_POST['site_postcode'];
    $site_phone = $_POST['site_phone'];
    $site_email = $_POST['site_email'];
    $consoleid = $_POST['consoleid']; // Ensure this name matches the 'name' attribute in 
    $timezone = $_POST['timezone'];
    $stat = 'On Site';


        $sqld = "INSERT INTO Sites (Client_id, uid, Site_name, site_country, site_address, site_city, postcode, phone, Email, last_date, last_time, time_zone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $statupd = "UPDATE console SET console_status = ? WHERE uid = ?";
        // Prepare and bind parameters
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("iissssisssss",$companyId, $consoleid, $site_name, $site_country, $site_address, $site_city, $site_postcode, $site_phone, $site_email, $date, $time, $timezone);

        $stmtstatupd = $conn->prepare($statupd);
        $stmtstatupd->bind_param("si", $stat, $consoleid);
        // Execute and check if insert was successful
        if ($stmtd->execute()) {
            // echo "Site inserted successfully!<br>";
            $last_id = $conn->insert_id;
            $stmtstatupd->execute();
            header("location: new_tank.php?site_id=$last_id&uid=$consoleid");
        }
        else{
            echo "Site Error<br>";
        }
        $stmtd->close(); 
    
    $conn ->close();
    
}
?>
