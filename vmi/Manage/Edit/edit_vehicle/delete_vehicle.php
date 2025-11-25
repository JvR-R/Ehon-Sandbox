<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__, 2)));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
// Include files using defined paths
include(db);
include(LOG_PATH);

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get the vehicle ID from the request
$vehicleId = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;

if ($vehicleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid vehicle ID']);
    exit;
}

try {
    // First, verify the vehicle exists and belongs to the client
    $checkQuery = "SELECT vehicle_id, client_id FROM vehicles WHERE vehicle_id = ? AND client_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $vehicleId, $companyId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Vehicle not found or access denied']);
        exit;
    }
    
    $checkStmt->close();
    
    // Update vehicle_enabled to 999 (soft delete)
    $updateQuery = "UPDATE vehicles SET vehicle_enabled = 999 WHERE vehicle_id = ? AND client_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $vehicleId, $companyId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
    } else {
        $updateStmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete vehicle']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

