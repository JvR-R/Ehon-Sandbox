<?php
include('../db/dbh2.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['Client_email'];
    $password = password_hash($_POST['Client_password'], PASSWORD_DEFAULT);
    $token = $_POST['token'];

    if ($email) {
        // Update user information
        $stmt = $conn->prepare("UPDATE login SET name = ?, last_name = ?, username = ?, password = ?, active = 1, token = NULL WHERE token = ?");
        $stmt->bind_param("sssss", $firstname, $lastname, $email, $password, $token);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'User created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update user']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
    }
}
?>
