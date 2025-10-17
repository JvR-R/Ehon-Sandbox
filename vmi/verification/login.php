<?php
    // Retrieve form data
    include('../db/dbh2.php');
    include('../db/log.php');

    $username = $_POST['username'];
    $password = $_POST['password'];
    $newpassword = $_POST['newpassword'];
// Prepare and execute the select query
$stmt = $conn->prepare("SELECT password, access_level, client_id FROM login WHERE username = ?");
if ($stmt === false) {
    die("Error in query preparation: " . $conn->error);
}
$stmt->bind_param("s", $username);
if ($stmt->execute() === false) {
    die("Error in query execution: " . $stmt->error);
}
$result = $stmt->get_result();

// Check if a user was found with the entered username
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $storedPassword = $row['password'];
    $accessLevel = $row['access_level'];
    $companyId = $row['client_id'];

    // Verify the entered password against the stored hashed password
    if (password_verify($password, $storedPassword)) {
        // Passwords match, update the password in the database
        $hashedPassword = password_hash($newpassword, PASSWORD_DEFAULT);
    
        // Prepare and execute the update query
        $updateStmt = $conn->prepare("UPDATE login SET password = ? WHERE username = ?");
        if ($updateStmt === false) {
            die("Error in query preparation: " . $conn->error);
        }
        $updateStmt->bind_param("ss", $hashedPassword, $username);
        if ($updateStmt->execute() === false) {
            die("Error in query execution: " . $updateStmt->error);
        }
    
        // Check if the password was updated successfully
        if ($updateStmt->affected_rows > 0) {
            // echo "Password updated successfully!";
            header("Location: /vmi/verification/");
            exit;
        } else {
            echo "Failed to update the password.";
        }
    
        // Close the update statement
        $updateStmt->close();
    } else {
        // Passwords do not match
        echo "<script>alert('Invalid password!$username');</script>";
        echo "<script>window.location.href = '/vmi/verification/';</script>";
    }
    
} else {
    // No user found with the entered username
    echo "<script>alert('Invalid username!');</script>";
    echo "<script>window.location.href = '/vmi/verification/';</script>";
}

// Close the result, statement, and database connection
$result->close();
$stmt->close();
$conn->close();
?>
