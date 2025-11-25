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

// Get the tag ID from the request
$tagId = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;

if ($tagId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid tag ID']);
    exit;
}

try {
    // First, verify the tag exists and belongs to the client
    $checkQuery = "SELECT id, client_id FROM client_tags WHERE id = ? AND client_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $tagId, $companyId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tag not found or access denied']);
        exit;
    }
    
    $checkStmt->close();
    
    // Update enabled_prompt to 999 (soft delete)
    $updateQuery = "UPDATE client_tags SET enabled_prompt = 999 WHERE id = ? AND client_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $tagId, $companyId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        echo json_encode(['success' => true, 'message' => 'Tag deleted successfully']);
    } else {
        $updateStmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete tag']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

