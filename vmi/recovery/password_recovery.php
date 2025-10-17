<?php
// Include your database connection script
include('../db/dbh2.php');

// Check if the form data is posted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve POST data
    $username = isset($_POST['username']) ? $_POST['username'] : null;
    $password = isset($_POST['password']) ? $_POST['password'] : null;
    $token = isset($_POST['token']) ? $_POST['token'] : null;

    // Simple validation
    if (!$username || !$password || !$token) {
        echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
        exit;
    }

    // Token is valid, update the password
    $updateQuery = "UPDATE login SET password = ?, token = NULL WHERE username = ?";

    if ($updateStmt = $conn->prepare($updateQuery)) {
        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $updateStmt->bind_param('ss', $hashedPassword, $username);
        $updateStmt->execute();

        if ($updateStmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating password']);
        }
        $updateStmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unable to prepare update statement']);
    }
} else {
    // Not a POST request
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

// Close the database connection
$conn->close();
?>
