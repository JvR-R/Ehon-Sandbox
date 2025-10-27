<?php
// Script to regenerate DRIVERS.CSV file for a specific client
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

// Function to generate DRIVERS.CSV file for a single UID
function generateDriversFileForUID($conn, $companyId, $uid) {
    // Create directory if it doesn't exist
    $directory = "/home/ehon/files/fms/cfg/" . $uid;
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            error_log("Failed to create directory for UID: " . $uid);
            return ['success' => false, 'message' => 'Failed to create directory for UID: ' . $uid];
        }
    }
    
    // Get all drivers for this client
    $driversStmt = $conn->prepare("SELECT driver_id, first_name, surname 
                                   FROM drivers 
                                   WHERE client_id = ? 
                                   ORDER BY driver_id");
    $driversStmt->bind_param("i", $companyId);
    $driversStmt->execute();
    $driversResult = $driversStmt->get_result();
    
    if ($driversResult->num_rows === 0) {
        $driversStmt->close();
        // Create empty file if no drivers
        $filePath = $directory . "/DRIVERS.CSV";
        file_put_contents($filePath, "");
        return ['success' => true, 'message' => 'DRIVERS.CSV created (empty - no drivers found)', 'uid' => $uid, 'file_path' => $filePath];
    }
    
    // Build DRIVERS.CSV content
    $driversContent = "";
    $driverCount = 0;
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
        $driverCount++;
    }
    
    $driversStmt->close();
    
    // Write to DRIVERS.CSV
    $filePath = $directory . "/DRIVERS.CSV";
    if (file_put_contents($filePath, $driversContent) === false) {
        error_log("Failed to write DRIVERS.CSV for UID: " . $uid);
        return ['success' => false, 'message' => 'Failed to write DRIVERS.CSV file for UID: ' . $uid];
    }
    
    error_log("DRIVERS.CSV file generated for UID: " . $uid . " at " . $filePath);
    return ['success' => true, 'message' => "DRIVERS.CSV generated successfully with $driverCount drivers", 'uid' => $uid, 'file_path' => $filePath, 'driver_count' => $driverCount];
}

// Function to generate DRIVERS.CSV files for all UIDs under a client
function generateDriversFile($conn, $companyId) {
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
    
    // Generate DRIVERS.CSV for each UID
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($uids as $uid) {
        $result = generateDriversFileForUID($conn, $companyId, $uid);
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
        
        $result = generateDriversFile($conn, $client_id);
        echo json_encode($result);
    } else {
        $response['message'] = 'Invalid request method. Use POST with client_id parameter.';
        echo json_encode($response);
    }
}

?>

