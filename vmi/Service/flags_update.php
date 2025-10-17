<?php
/**
 * flags_update.php
 *
 * Receives POST data:
 *  - uid:  the device uid
 *  - flag: 'fw', 'cfg', 'restart', or 'logs'
 *
 * Updates a row in the `console` table to set the corresponding *_flag to 1.
 * If that flag is already 1, we tell the user it is already active.
 */

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

include('../db/dbh2.php'); // Make sure this sets $conn

$uid  = isset($_POST['uid'])  ? $_POST['uid']  : null;
$flag = isset($_POST['flag']) ? $_POST['flag'] : null;

if (!$uid || !$flag) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing required parameters (uid or flag).'
    ]);
    exit;
}

// Map the incoming `flag` to the matching column
$validFlags = [
    'fw'      => 'fw_flag',
    'cfg'     => 'cfg_flag',
    'restart' => 'restart_flag',
    'logs'    => 'logs_flag'
];
if (!isset($validFlags[$flag])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid flag type.'
    ]);
    exit;
}

// Column name we want to set to 1
$columnName = $validFlags[$flag];

// 1) Update only if the column is not already 1
$sql = "UPDATE console
        SET $columnName = 1
        WHERE uid = ?
          AND $columnName <> 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database prepare failed: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $uid);
$stmt->execute();

// 2) Check how many rows were affected
if ($stmt->affected_rows > 0) {
    // Updated successfully
    echo json_encode([
        'status'  => 'success',
        'message' => "Successfully set {$columnName} to 1 for uid: {$uid}"
    ]);
} else {
    // No rows were updated — either row doesn’t exist, or the flag is already 1
    // Check if the row exists at all
    $checkSql = "SELECT COUNT(*) as count FROM console WHERE uid = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $uid);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();

    if ($row && $row['count'] > 0) {
        // Record exists, so the flag must already be 1
        echo json_encode([
            'status'  => 'info',  // or 'error', if you prefer
            'message' => "Flag {$columnName} is already set for uid: {$uid}"
        ]);
    } else {
        // No such record exists
        echo json_encode([
            'status'  => 'error',
            'message' => "No matching record found for uid: {$uid}"
        ]);
    }

    $checkStmt->close();
}

$stmt->close();
$conn->close();
