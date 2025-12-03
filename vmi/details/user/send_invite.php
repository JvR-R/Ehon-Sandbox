<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/email_conf.php');

// Check if user has admin access level (1, 4, 6, or 8)
if (!in_array($accessLevel, [1, 2, 4, 6, 8])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Admin access required']);
    exit;
}

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
        $password = ''; // Password will be set during registration
        $name = ''; // Name will be set during registration
        $last_name = ''; // Last name will be set during registration
        $stmt = $conn->prepare("INSERT INTO login (username, password, name, last_name, token, access_level, client_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssii", $email, $password, $name, $last_name, $token, $userlevel, $companyId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Send the email
            $registration_link = "https://ehon.com.au/vmi/Register/verify?token=$token";
            $subject = "Complete Your Registration with Ehon Energy";
            $message = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Complete Your Registration</title>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f7fa; color: #333333;'>
                    <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #f4f7fa;'>
                        <tr>
                            <td align='center' style='padding: 40px 20px;'>
                                <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 40px 40px 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;'>Welcome to Ehon Energy</h1>
                                        </td>
                                    </tr>
                                    
                                    <!-- Main Content -->
                                    <tr>
                                        <td style='padding: 40px 40px 30px;'>
                                            <p style='margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #333333;'>Dear User,</p>
                                            
                                            <p style='margin: 0 0 25px; font-size: 16px; line-height: 1.6; color: #555555;'>
                                                You have been invited to register an account on the Ehon Energy platform as a <strong style='color: #1e3c72;'>$mail_us</strong>.
                                            </p>
                                            
                                            <p style='margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #555555;'>
                                                Click the button below to complete your registration and get started:
                                            </p>
                                            
                                            <!-- CTA Button -->
                                            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                                                <tr>
                                                    <td align='center' style='padding: 0 0 30px;'>
                                                        <a href='$registration_link' style='display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: 600; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3); transition: all 0.3s ease;'>Complete Registration</a>
                                                    </td>
                                                </tr>
                                            </table>
                                            
                                            <!-- Alternative Link -->
                                            <div style='background-color: #f8f9fb; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #1e3c72;'>
                                                <p style='margin: 0 0 10px; font-size: 13px; color: #666666; font-weight: 600;'>Or copy this link:</p>
                                                <p style='margin: 0; font-size: 13px; color: #2a5298; word-break: break-all; line-height: 1.5;'>$registration_link</p>
                                            </div>
                                            
                                            <!-- Username Info -->
                                            <div style='background-color: #e8f4f8; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #2a5298;'>
                                                <p style='margin: 0 0 10px; font-size: 13px; color: #666666; font-weight: 600;'>📧 Your Login Information:</p>
                                                <p style='margin: 0; font-size: 14px; color: #1e3c72; line-height: 1.5;'><strong>Username:</strong> $email</p>
                                            </div>
                                            
                                            <p style='margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #777777;'>
                                                If you did not request this invitation, please disregard this email.
                                            </p>
                                            
                                            <p style='margin: 0; font-size: 16px; line-height: 1.6; color: #333333;'>
                                                Best regards,<br>
                                                <strong style='color: #1e3c72;'>Ehon Energy Team</strong>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <!-- Footer -->
                                    <tr>
                                        <td style='background-color: #f8f9fb; padding: 30px 40px; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #e5e9f0;'>
                                            <p style='margin: 0 0 10px; font-size: 14px; color: #666666; line-height: 1.5;'>
                                                📎 <strong>Attached:</strong> Link Portal Documentation
                                            </p>
                                            <p style='margin: 0; font-size: 13px; color: #888888; line-height: 1.5;'>
                                                If you have any questions, feel free to contact our support team at<br>
                                                <a href='mailto:support@petroindustrial.com.au' style='color: #2a5298; text-decoration: none;'>support@petroindustrial.com.au</a>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Bottom Footer -->
                                <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse; margin-top: 20px;'>
                                    <tr>
                                        <td align='center' style='padding: 0 20px;'>
                                            <p style='margin: 0; font-size: 12px; color: #999999; line-height: 1.5;'>
                                                © " . date('Y') . " Ehon Energy. All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";

            $attachment_path = '/home/ehon/public_html/vmi/Docs/Link-Portal_Ov.pdf';
            $attachment_name = 'Link-Portal.pdf';
            $email_status = send_email_with_attachment($email, $subject, $message, $attachment_path, $attachment_name, 'support@petroindustrial.com.au');

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
