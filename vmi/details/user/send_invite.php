<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/email_conf.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'];
    $user_type = (int)$data['user_type']; // Cast to integer
    $accessLevel = (int)$data['accessLevel']; // Cast to integer
    $companyId = (int)$data['companyId']; // Cast to integer

    if($user_type == 1){
        $userlevel = $accessLevel;
        $mail_us = 'Admin';
    }
    elseif($user_type == 2){
        $userlevel = $accessLevel + 1;
        $mail_us = 'User';
    }

    if (!empty($email) && !empty($user_type)) {
        $token = generateToken(16); // Generate a random token
        $stmt = $conn->prepare("INSERT INTO login (username, token, access_level, client_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $email, $token, $userlevel, $companyId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Send the email
            $registration_link = "https://ehon.com.au/vmi/Register/verify?token=$token";
            $subject = "Complete Your Registration with Ehon Energy";
            $message = "
                <html>
                <head>
                    <title>Complete Your Registration</title>
                </head>
                <body>
                    <p>Dear User,</p>
                    <p>You have been invited to register an account on Ehon Energy as a <strong>$mail_us</strong>.</p>
                    <p>Please click the following link to complete your registration:</p>
                    <p><a href='$registration_link'>$registration_link</a></p>
                    <p>If you did not request this invitation, please disregard this email.</p>
                    <p>Thank you,<br>Ehon Energy Team</p>
                    <hr>
                    <p>If you have any questions, feel free to contact our support team.</p>
                </body>
                </html>
            ";

            $email_status = send_email($email, $subject, $message, 'support@petroindustrial.com.au');

            if ($email_status['status'] === 'success') {
                echo json_encode(['status' => 'success', 'message' => 'Invitation sent successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send email: ' . $email_status['message']]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to store token']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Email and user type are required']);
    }
}
?>
