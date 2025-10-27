<?php
// Script to regenerate VEHICLES.CSV file for a specific client
// Can be called directly or included in edit operations

// Only include database and set headers if this file is called directly (not included)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // Suppress error display (recommended for production)
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Set header to return JSON
    header('Content-Type: application/json');

    // Include necessary files
    include('../../db/dbh2.php'); // Database connection
}

// Function to generate VEHICLES.CSV file for a single UID
function generateVehiclesFileForUID($conn, $companyId, $uid) {
    // Create directory if it doesn't exist
    $directory = "/home/ehon/files/fms/cfg/" . $uid;
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            error_log("Failed to create directory for UID: " . $uid);
            return ['success' => false, 'message' => 'Failed to create directory for UID: ' . $uid];
        }
    }
    
    // Get all vehicles for this client
    $vehiclesStmt = $conn->prepare("SELECT vehicle_id, vehicle_name, vehicle_rego 
                                    FROM vehicles 
                                    WHERE Client_id = ? 
                                    ORDER BY vehicle_id");
    $vehiclesStmt->bind_param("i", $companyId);
    $vehiclesStmt->execute();
    $vehiclesResult = $vehiclesStmt->get_result();
    
    if ($vehiclesResult->num_rows === 0) {
        $vehiclesStmt->close();
        // Create empty file if no vehicles
        $filePath = $directory . "/VEHICLES.CSV";
        file_put_contents($filePath, "");
        return ['success' => true, 'message' => 'VEHICLES.CSV created (empty - no vehicles found)', 'uid' => $uid, 'file_path' => $filePath];
    }
    
    // Build VEHICLES.CSV content
    $vehiclesContent = "";
    $vehicleCount = 0;
    while ($vehicle = $vehiclesResult->fetch_assoc()) {
        $line = [];
        
        // Field 1: vehicle_id
        $line[] = $vehicle['vehicle_id'] !== null ? $vehicle['vehicle_id'] : 0;
        
        // Field 2: vehicle_name
        $line[] = $vehicle['vehicle_name'] !== null ? $vehicle['vehicle_name'] : '';
        
        // Field 3: vehicle_rego
        $line[] = $vehicle['vehicle_rego'] !== null ? $vehicle['vehicle_rego'] : '';
        
        $vehiclesContent .= implode(',', $line) . "\n";
        $vehicleCount++;
    }
    
    $vehiclesStmt->close();
    
    // Write to VEHICLES.CSV
    $filePath = $directory . "/VEHICLES.CSV";
    if (file_put_contents($filePath, $vehiclesContent) === false) {
        error_log("Failed to write VEHICLES.CSV for UID: " . $uid);
        return ['success' => false, 'message' => 'Failed to write VEHICLES.CSV file for UID: ' . $uid];
    }
    
    error_log("VEHICLES.CSV file generated for UID: " . $uid . " at " . $filePath);
    return ['success' => true, 'message' => "VEHICLES.CSV generated successfully with $vehicleCount vehicles", 'uid' => $uid, 'file_path' => $filePath, 'vehicle_count' => $vehicleCount];
}

// Function to generate VEHICLES.CSV files for all UIDs under a client
function generateVehiclesFile($conn, $companyId) {
    // Get all UIDs for this company that have device_type = 10
    // Join console_asociation with console table to filter by device_type
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
        return ['success' => false, 'message' => 'No UIDs found for this client with device_type = 10'];
    }
    
    $uids = [];
    while ($row = $uidResult->fetch_assoc()) {
        $uids[] = $row['uid'];
    }
    $uidStmt->close();
    
    // Generate VEHICLES.CSV for each UID
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($uids as $uid) {
        $result = generateVehiclesFileForUID($conn, $companyId, $uid);
        $results[] = $result;
        if ($result['success']) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    $totalUIDs = count($uids);
    $message = "Processed $totalUIDs UID(s): $successCount successful, $failCount failed";
    
    return [
        'success' => ($failCount === 0),
        'message' => $message,
        'total_uids' => $totalUIDs,
        'successful' => $successCount,
        'failed' => $failCount,
        'details' => $results,
        'uids' => $uids
    ];
}

// Only execute POST handling if this file is called directly (not included)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // Initialize response
    $response = [
        'success' => false,
        'message' => ''
    ];

    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        
        if ($client_id <= 0) {
            $response['message'] = 'Invalid Client ID.';
            echo json_encode($response);
            exit;
        }
        
        $result = generateVehiclesFile($conn, $client_id);
        echo json_encode($result);
    } else {
        $response['message'] = 'Invalid request method. Use POST with client_id parameter.';
        echo json_encode($response);
    }
}

?>

