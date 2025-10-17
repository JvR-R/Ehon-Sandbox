<?php
header('Content-Type: application/json');

// Include database connection
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('DB_PATH', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');

// Include files using defined paths
include(DB_PATH);
include(LOG_PATH);
// Get POST data
$card_number = $_POST['card_number'];
$registration = $_POST['registration'];
$tax_value = $_POST['tax_value'];

// Validate inputs
if (!isset($card_number) || !isset($registration) || !isset($tax_value)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$tax_value = floatval($tax_value);

// Prepare and execute the update query
$stmt = $conn->prepare("UPDATE client_tasbax SET tax_value = ? WHERE card_number = ? AND registration = ?");
$stmt->bind_param("dss", $tax_value, $card_number, $registration);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
