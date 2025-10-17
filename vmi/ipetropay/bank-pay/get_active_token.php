<?php
// get_active_token.php
// This script returns the latest refresh_act_time from the active_token table in JSON format.
// Ensure the path to dbh.php is correct for your project structure.

header('Content-Type: application/json');
include('../../db/dbh.php');

// Example query – adjust column/table names if needed
$stmt = $conn->prepare("SELECT refresh_act_time FROM active_token LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['refresh_act_time' => $row['refresh_act_time']]);
} else {
    // If there's no active_token row, return null or empty
    echo json_encode(['refresh_act_time' => null]);
}
?>
