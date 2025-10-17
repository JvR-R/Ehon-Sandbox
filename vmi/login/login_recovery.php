<?php
include('../db/dbh2.php');
include('../db/email_conf.php');

if (isset($_POST['username'])) {
    $username = $_POST['username'];
    
    // Check if the user exists
    $stmt = $conn->prepare("SELECT * FROM login WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $token = bin2hex(random_bytes(16));
        $expiry = new DateTime('now + 30 minutes');
        $expdate = $expiry->format('Y-m-d H:i:s');

        $stmt = $conn->prepare("UPDATE login SET token = ?, token_expiry = ? WHERE username = ?");
        $stmt->bind_param("sss", $token, $expdate, $username);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $resetLink = "https://www.ehon.com.au/vmi/recovery?token=" . $token;
            $emailContent = 'Here is your password reset link, active for 30 minutes:<br>' . $resetLink . "<br>";
            $emailStatus = send_email($username, 'Password Reset', $emailContent);
            echo json_encode($emailStatus);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating password in the database.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User does not exist.']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Username not provided.']);
}
?>