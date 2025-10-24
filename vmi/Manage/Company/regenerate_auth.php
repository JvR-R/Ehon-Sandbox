<?php
// Script to regenerate AUTH.TXT file for a specific client
// Can be called directly or included in edit operations

// Suppress error display (recommended for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header to return JSON
header('Content-Type: application/json');

// Include necessary files
include('../../db/dbh2.php'); // Database connection

// Function to generate AUTH.TXT file for a single UID
function generateAuthFileForUID($conn, $companyId, $uid) {
    // Create directory if it doesn't exist
    $directory = "/home/ehon/files/cfg/fms/" . $uid;
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            error_log("Failed to create directory for UID: " . $uid);
            return ['success' => false, 'message' => 'Failed to create directory for UID: ' . $uid];
        }
    }
    
    // Get all tags for this client
    $tagsStmt = $conn->prepare("SELECT id, card_number, pin_number, card_type, list_driver, list_vehicle, 
                                driver_prompt, prompt_vehicle, projectnum_prompt, odo_prompt, enabled_prompt 
                                FROM client_tags 
                                WHERE client_id = ? 
                                ORDER BY id");
    $tagsStmt->bind_param("i", $companyId);
    $tagsStmt->execute();
    $tagsResult = $tagsStmt->get_result();
    
    if ($tagsResult->num_rows === 0) {
        $tagsStmt->close();
        // Create empty file if no tags
        $filePath = $directory . "/AUTH.TXT";
        file_put_contents($filePath, "");
        return ['success' => true, 'message' => 'AUTH.TXT created (empty - no tags found)', 'uid' => $uid, 'file_path' => $filePath];
    }
    
    // Build AUTH.TXT content
    $authContent = "";
    $tagCount = 0;
    while ($tag = $tagsResult->fetch_assoc()) {
        $line = [];
        
        // Field 1: card_number (or '0')
        $line[] = $tag['card_number'] !== null ? $tag['card_number'] : '0';
        
        // Field 2: pin_number (or '0')
        $line[] = $tag['pin_number'] !== null ? $tag['pin_number'] : '0';
        
        // Field 3: id
        $line[] = $tag['id'] !== null ? $tag['id'] : 0;
        
        // Field 4: card_type (or '0')
        $line[] = $tag['card_type'] !== null ? $tag['card_type'] : '0';
        
        // Field 5: list_driver
        $line[] = $tag['list_driver'] !== null ? intval($tag['list_driver']) : 0;
        
        // Field 6: list_vehicle
        $line[] = $tag['list_vehicle'] !== null ? intval($tag['list_vehicle']) : 0;
        
        // Field 7: driver_prompt (0 if null or 999)
        $driver_prompt_val = $tag['driver_prompt'];
        $line[] = ($driver_prompt_val === null || intval($driver_prompt_val) == 999) ? 0 : intval($driver_prompt_val);
        
        // Field 8: prompt_vehicle (0 if null or 999)
        $prompt_vehicle_val = $tag['prompt_vehicle'];
        $line[] = ($prompt_vehicle_val === null || intval($prompt_vehicle_val) == 999) ? 0 : intval($prompt_vehicle_val);
        
        // Field 9: projectnum_prompt
        $line[] = $tag['projectnum_prompt'] ? intval($tag['projectnum_prompt']) : 0;
        
        // Field 10: odo_prompt
        $line[] = $tag['odo_prompt'] ? intval($tag['odo_prompt']) : 0;
        
        // Field 11: enabled_prompt
        $line[] = $tag['enabled_prompt'] ? intval($tag['enabled_prompt']) : 0;
        
        $authContent .= implode(',', $line) . "\n";
        $tagCount++;
    }
    
    $tagsStmt->close();
    
    // Write to AUTH.TXT
    $filePath = $directory . "/AUTH.TXT";
    if (file_put_contents($filePath, $authContent) === false) {
        error_log("Failed to write AUTH.TXT for UID: " . $uid);
        return ['success' => false, 'message' => 'Failed to write AUTH.TXT file for UID: ' . $uid];
    }
    
    error_log("AUTH.TXT file generated for UID: " . $uid . " at " . $filePath);
    return ['success' => true, 'message' => "AUTH.TXT generated successfully with $tagCount tags", 'uid' => $uid, 'file_path' => $filePath, 'tag_count' => $tagCount];
}

// Function to generate AUTH.TXT files for all UIDs under a client
function generateAuthFile($conn, $companyId) {
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
    
    // Generate AUTH.TXT for each UID
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($uids as $uid) {
        $result = generateAuthFileForUID($conn, $companyId, $uid);
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
    
    $result = generateAuthFile($conn, $client_id);
    echo json_encode($result);
} else {
    $response['message'] = 'Invalid request method. Use POST with client_id parameter.';
    echo json_encode($response);
}

?>

