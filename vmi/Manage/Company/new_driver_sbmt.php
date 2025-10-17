<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
include('../../db/crc.php');

ob_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $customer_name    = $_POST['customer_name'];
    $first_name       = $_POST['first_name'];
    $surname          = $_POST['surname'];
    $pin_number       = $_POST['pin_number'] ?? 0;
    $mobile_number    = $_POST['mobile_number'];
    $driver_enable    = $_POST['driver_enable'];
    $external_id      = $_POST['external_id'];
    $license_number   = $_POST['license_number'];
    $registration_date= $_POST['registration_date'];
    $license_type     = $_POST['license_type'];
    $driver_email     = $_POST['driver_email'];
    $additional_info  = $_POST['additional_info'];

    // Prepare the insert query
    $sqld = "INSERT INTO drivers 
             (customer_id, client_id, first_name, surname, driver_pinnumber, driver_phone, external_id, license_number, license_expire, license_type, driver_email, driver_enabled, driver_addinfo) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
   
    $stmtd = $conn->prepare($sqld);
    // Note: Adjust parameter types as needed.
    $stmtd->bind_param("iissiisssssis", 
        $customer_name, $companyId, $first_name, $surname, $pin_number, 
        $mobile_number, $external_id, $license_number, $registration_date, 
        $license_type, $driver_email, $driver_enable, $additional_info
    );

    if ($stmtd->execute()) {
        // Set toastr session variables for a success message
        $_SESSION['toastr_msg']  = "Inserted successfully!";
        $_SESSION['toastr_type'] = "success";

        // Call additional functions as needed (e.g., updating CRC data)
        drivers_crcdata($companyId);
    } else {
        // Set toastr session variables for an error message
        $_SESSION['toastr_msg']  = "Driver Creation Error";
        $_SESSION['toastr_type'] = "error";
    }

    $stmtd->close();
    $conn->close();

    // Redirect back to your form page (adjust the URL as needed)
    header("Location: new_driver.php");
    exit();
}
?>
