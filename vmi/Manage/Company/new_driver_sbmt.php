<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
include('../../db/crc.php');

ob_start();

// Function to generate DRIVERS.TXT file for a single UID
function generateDriversFileForUID($conn, $companyId, $uid) {
    // Create directory if it doesn't exist
    $directory = "/home/ehon/files/fms/cfg/" . $uid;
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Get all drivers for this client
    $driversStmt = $conn->prepare("SELECT driver_id, first_name, surname 
                                   FROM drivers 
                                   WHERE client_id = ? 
                                   ORDER BY driver_id");
    $driversStmt->bind_param("i", $companyId);
    $driversStmt->execute();
    $driversResult = $driversStmt->get_result();
    
    // Build DRIVERS.TXT content
    $driversContent = "";
    while ($driver = $driversResult->fetch_assoc()) {
        $line = [];
        
        // Field 1: driver_id
        $line[] = $driver['driver_id'] !== null ? $driver['driver_id'] : 0;
        
        // Field 2: first_name + surname (if surname exists)
        $fullName = $driver['first_name'];
        if (!empty($driver['surname'])) {
            $fullName .= ' ' . $driver['surname'];
        }
        $line[] = $fullName;
        
        $driversContent .= implode(',', $line) . "\n";
    }
    
    $driversStmt->close();
    
    // Write to DRIVERS.TXT
    $filePath = $directory . "/DRIVERS.TXT";
    file_put_contents($filePath, $driversContent);
    
    error_log("DRIVERS.TXT file generated for UID: " . $uid . " at " . $filePath);
}

// Function to generate DRIVERS.TXT files for all UIDs under a client
function generateDriversFile($conn, $companyId) {
    // Get all UIDs for this company that have device_type = 10
    $uidStmt = $conn->prepare("SELECT DISTINCT ca.uid 
                               FROM Sites ca
                               INNER JOIN console c ON ca.uid = c.uid
                               WHERE ca.client_id = ? AND c.device_type = 10");
    $uidStmt->bind_param("i", $companyId);
    $uidStmt->execute();
    $uidResult = $uidStmt->get_result();
    
    if ($uidResult->num_rows === 0) {
        error_log("No UIDs found for company ID: " . $companyId . " with device_type = 10");
        $uidStmt->close();
        return;
    }
    
    $uids = [];
    while ($row = $uidResult->fetch_assoc()) {
        $uids[] = $row['uid'];
    }
    $uidStmt->close();
    
    // Generate DRIVERS.TXT for each UID
    foreach ($uids as $uid) {
        generateDriversFileForUID($conn, $companyId, $uid);
    }
}

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
        generateDriversFile($conn, $companyId);
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
