<?php
include('../db/dbh2.php');
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Find the user with this token
    $stmt = $conn->prepare("SELECT * FROM login WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // Token is valid, activate the account
        $stmt = $conn->prepare("UPDATE login SET token = NULL, active = 1 WHERE token = ?");
        $stmt->bind_param("s", $token);
        if ($stmt->execute()) {
            echo "<script>
                alert('Account activated successfully!');
                window.location.href = '/vmi/login';
                </script>";
        } else {
            echo "<script>
                alert('Error activating account.');
                </script>";
        }
    } else {
        echo "<script>
            alert('Invalid activation link.');
            </script>";
    }
    $stmt->close();
} else {
    echo "<script>
        alert('No activation token provided.');
        </script>";
}

$conn->close();
?>
