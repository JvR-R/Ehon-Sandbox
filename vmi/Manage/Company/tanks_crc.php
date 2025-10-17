<?php
// Suppress error display (recommended for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header to return JSON
header('Content-Type: application/json');

// Include necessary files (ensure these do not output HTML)
include('../../db/dbh2.php'); // Database connection
include('../../db/log.php');   // Logging utility
include('../../db/crc.php');   // Logging utility

vehicle_crcdata(15100);
// tanks_crcdata(398321);

function calculate_crc32_hex($data_str) {
    // Calculate the CRC32 checksum using PHP's crc32() function
    $crc = crc32($data_str);
    
    // Handle negative values by converting to unsigned integer
    if ($crc < 0) {
        $crc = $crc + 4294967296;  // Add 2^32 to get the correct unsigned value
    }
    
    // Convert the checksum to an uppercase hexadecimal string with leading zeros
    $crc_hex = sprintf('%08X', $crc);
    
    return $crc_hex;
}

function tanks_crcdata($uid) {
    global $conn; // Ensure $conn is available inside the function

    $sql = "SELECT tank_id, capacity, product_name FROM Tanks ts JOIN products ps ON ps.product_id = ts.product_id WHERE uid = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return;
    }
    $result = $stmt->get_result();

    $dataStrings = [];
    while ($row = $result->fetch_assoc()) {
        // Explicitly extract fields in the desired order
        $fields = [
            $row['tank_id'],
            $row['capacity'],
            $row['product_name']
        ];
        // Convert NULL values to empty strings
        foreach ($fields as &$field) {
            if (is_null($field)) {
                $field = 0;
            }
        }
        // Form data string for each row
        $dataString = implode(',', $fields);
        // Add "\n" at the end
        $dataString .= "\n";
        // Add to array
        $dataStrings[] = $dataString;
    }
    $stmt->close();

    // Concatenate data strings
    $concatenatedData = implode('', $dataStrings);


    // Calculate the CRC over $concatenatedData
    $crc = calculate_crc32_hex($concatenatedData);



    $crc = "0x$crc";
    $update = "UPDATE console SET crc_tank= ? WHERE uid = ?";
    $updatequery = $conn->prepare($update);
    if ($updatequery === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $updatequery->bind_param("si", $crc, $uid);
    if (!$updatequery->execute()) {
        error_log("Database execute error: " . $updatequery->error);
    }
    $updatequery->close();
}


function pumps_crcdata($uid) {
    global $conn; // Ensure $conn is available inside the function

    $sql = "SELECT Nozzle_Number, pulse_rate, tank_id FROM pumps WHERE uid = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return;
    }
    $result = $stmt->get_result();

    $dataStrings = [];
    while ($row = $result->fetch_assoc()) {
        // Explicitly extract fields in the desired order
        $fields = [
            $row['Nozzle_Number'],
            $row['pulse_rate'],
            $row['tank_id'],
            0,
            0,
            0
        ];
        // Convert NULL values to empty strings
        foreach ($fields as &$field) {
            if (is_null($field)) {
                $field = 0;
            }
        }
        // Form data string for each row
        $dataString = implode(',', $fields);
        // Add "\n" at the end
        $dataString .= "\n";
        // Add to array
        $dataStrings[] = $dataString;
    }
    $stmt->close();

    // Concatenate data strings
    $concatenatedData = implode('', $dataStrings);


    // Calculate the CRC over $concatenatedData
    $crc = calculate_crc32_hex($concatenatedData);



    $crc = "0x$crc";
    echo "$concatenatedData \r\n";
    $update = "UPDATE console SET crc_pumps= ? WHERE uid = ?";
    $updatequery = $conn->prepare($update);
    if ($updatequery === false) {
        echo "Database prepare error: " . $conn->error;
        return;
    }
    $updatequery->bind_param("si", $crc, $uid);
    if (!$updatequery->execute()) {
        
        error_log("Database execute error: " . $updatequery->error);
    }
    echo "Pump crc: $crc\r\n";
    $updatequery->close();
}

function drivers_crcdata($client_id) {
    global $conn; // Ensure $conn is available inside the function

    $sql = "SELECT driver_id, first_name, surname FROM drivers 
            WHERE client_id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $client_id);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return;
    }
    $result = $stmt->get_result();

    $dataStrings = [];
    while ($row = $result->fetch_assoc()) {
        // Explicitly extract fields in the desired order
        if(!empty($row['surname'])){
            $name = $row['first_name'] . " " . $row['surname'];
        } else {
            $name = $row['first_name'];
        }
        $fields = [
            $row['driver_id'],
            $name
        ];
        // Convert NULL values to empty strings
        foreach ($fields as &$field) {
            if (is_null($field)) {
                $field = 0;
            }
        }
        // Form data string for each row
        $dataString = implode(',', $fields);
        // Add "\n" at the end
        $dataString .= "\n";
        // Add to array
        $dataStrings[] = $dataString;
    }
    $stmt->close();

    // Concatenate data strings
    $concatenatedData = implode('', $dataStrings);

    // Calculate the CRC over $concatenatedData
    $crc = calculate_crc32_hex($concatenatedData);

    $crc = "0x$crc";
    echo "$concatenatedData \r\n";
    $update = "UPDATE console SET crc_driver= ? WHERE uid = ?";
    $updatequery = $conn->prepare($update);
    if ($updatequery === false) {
        echo "Database prepare error: " . $conn->error;
        return;
    }
    $updatequery->bind_param("si", $crc, $uid);
    if (!$updatequery->execute()) {
        
        error_log("Database execute error: " . $updatequery->error);
    }
    echo "Driver crc: $crc\r\n";
    $updatequery->close();
}

function vehicle_crcdata($client_id) {
    global $conn; // Ensure $conn is available inside the function

    $sql = "SELECT vehicle_id, vehicle_name, vehicle_rego FROM vehicles 
            WHERE client_id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $client_id);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return;
    }
    $result = $stmt->get_result();

    $dataStrings = [];
    while ($row = $result->fetch_assoc()) {
        // Explicitly extract fields in the desired order
        $fields = [
            $row['vehicle_id'],
            $row['vehicle_name'],
            $row['vehicle_rego']
        ];
        // Convert NULL values to empty strings
        foreach ($fields as &$field) {
            if (is_null($field)) {
                $field = 0;
            }
        }
        // Form data string for each row
        $dataString = implode(',', $fields);
        // Add "\n" at the end
        $dataString .= "\n";
        // Add to array
        $dataStrings[] = $dataString;
    }
    $stmt->close();

    // Concatenate data strings
    $concatenatedData = implode('', $dataStrings);

    // Calculate the CRC over $concatenatedData
    $crc = calculate_crc32_hex($concatenatedData);

    $crc = "0x$crc";
    echo "$concatenatedData \r\n";
    $update = "UPDATE console SET crc_driver= ? WHERE uid = ?";
    $updatequery = $conn->prepare($update);
    if ($updatequery === false) {
        echo "Database prepare error: " . $conn->error;
        return;
    }
    $updatequery->bind_param("si", $crc, $uid);
    if (!$updatequery->execute()) {
        
        error_log("Database execute error: " . $updatequery->error);
    }
    echo "vehicle crc: $crc\r\n";
    $updatequery->close();
}


?>
