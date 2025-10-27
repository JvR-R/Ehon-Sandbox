<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
include('../../../db/crc.php');
ob_start();

// Include regenerate_drivers.php to reuse file generation functions
require_once('../../Company/regenerate_drivers.php');

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0; // Ensure driver_id is an integer
    $customer_id = isset($_POST['customer_name']) ? intval($_POST['customer_name']) : 0; // Renamed for clarity
    $first_name = trim($_POST['first_name']);
    $surname = trim($_POST['surname']);
    // $pin_number = ($_POST['pin_number']);
    $mobile_number = trim($_POST['mobile_number']);
    $driver_enable = isset($_POST['driver_enable']) ? intval($_POST['driver_enable']) : 0;
    $external_id = trim($_POST['external_id']);
    $license_number = trim($_POST['license_number']);
    $registration_date = trim($_POST['registration_date']);
    $license_type = trim($_POST['license_type']);
    $driver_email = trim($_POST['driver_email']);
    $additional_info = trim($_POST['additional_info']);

    // Debugging: Display sanitized input values
    /*
    echo "Driver ID: " . htmlspecialchars($driver_id) . "<br>";
    echo "Customer ID: " . htmlspecialchars($customer_id) . "<br>";
    echo "First Name: " . htmlspecialchars($first_name) . "<br>";
    echo "Surname: " . htmlspecialchars($surname) . "<br>";
    echo "PIN Number: " . htmlspecialchars($pin_number) . "<br>";
    echo "Mobile Number: " . htmlspecialchars($mobile_number) . "<br>";
    echo "Driver Enabled: " . htmlspecialchars($driver_enable) . "<br>";
    echo "External ID: " . htmlspecialchars($external_id) . "<br>";
    echo "License Number: " . htmlspecialchars($license_number) . "<br>";
    echo "Registration Date: " . htmlspecialchars($registration_date) . "<br>";
    echo "License Type: " . htmlspecialchars($license_type) . "<br>";
    echo "Driver Email: " . htmlspecialchars($driver_email) . "<br>";
    echo "Additional Info: " . htmlspecialchars($additional_info) . "<br>";
    */

    // Validate driver_id
    if ($driver_id <= 0) {
        echo "Invalid Driver ID.";
        exit;
    }

    // Prepare the UPDATE SQL statement
    $sql = "UPDATE drivers 
            SET 
                customer_id = ?, 
                client_id = ?, 
                first_name = ?, 
                surname = ?, 
                driver_phone = ?, 
                external_id = ?, 
                license_number = ?, 
                license_expire = ?, 
                license_type = ?, 
                driver_email = ?, 
                driver_enabled = ?, 
                driver_addinfo = ? 
            WHERE 
                driver_id = ?
                and client_id = ?";

    // Prepare the statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters
        // "i" for integer, "s" for string
        $stmt->bind_param(
            "iississsssiiii", 
            $customer_id, 
            $companyId, 
            $first_name, 
            $surname, 
            $mobile_number, 
            $external_id, 
            $license_number, 
            $registration_date, 
            $license_type, 
            $driver_email, 
            $driver_enable, 
            $additional_info, 
            $driver_id,
            $companyId
        );

        // Execute the statement
        if ($stmt->execute()) {
            // echo "Driver updated successfully!<br>";
            drivers_crcdata($companyId); // process CRC data
            generateDriversFile($conn, $companyId);
            // Optionally, redirect to another page
            header("Location: driver_edit.php?id=$driver_id&success=true");
            // exit();
        } else {
            // Log the error details for debugging
            error_log("Database Error: " . $stmt->error);
            echo "An error occurred while updating the driver. Please try again later.<br>";
        }

        // Close the statement
        $stmt->close();
    } else {
        // Log the error details for debugging
        error_log("Preparation Failed: " . $conn->error);
        echo "An error occurred while preparing the update. Please try again later.<br>";
    }

    // Close the database connection
    $conn->close();
}
?>
