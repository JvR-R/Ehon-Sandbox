<?php
include('../../db/dbh2.php'); 
include('../../db/email_conf.php');
header('Content-Type: application/json');
$response = array();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $companyName = $_POST['Client_name'];
    $companyAddress = $_POST['Client_address'];
    $companyPhone = $_POST['Client_phone'];
    $clientEmail = $_POST['Client_email'];
    $companypassword = $_POST['Client_password'];
    $uid = $_POST['uid'];
    $deviceid = $_POST['deviceid'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];

    // Check if the email already exists in the login table
    $emailCheck = "SELECT COUNT(*) FROM login WHERE username = ?";
    $stmtEmailCheck = $conn->prepare($emailCheck);
    $stmtEmailCheck->bind_param("s", $clientEmail);
    $stmtEmailCheck->execute();
    $stmtEmailCheck->bind_result($emailCount);
    $stmtEmailCheck->fetch();
    $stmtEmailCheck->close();

    if ($emailCount > 0) {
        $response['status'] = 'error';
        $response['message'] = 'Email already exists.';
        echo json_encode($response);
        exit();
    } else{



        // ID Check for client reseller ------------------------------------------------------------------------
        $residcheck = "SELECT reseller_id as resid FROM Console_Asociation WHERE uid = ?";
        $stmtresidcheck = $conn->prepare($residcheck);
        $stmtresidcheck->bind_param("i", $uid); 
        $stmtresidcheck->execute();
        $stmtresidcheck->bind_result($res_id); 
        $stmtresidcheck->fetch();
        $stmtresidcheck->close();

        // ID Check for clients ------------------------------------------------------------------------
        $idcheck = "SELECT MAX(client_id) as maxid FROM Clients";
        $stmtidcheck = $conn->prepare($idcheck);
        $stmtidcheck->execute();
        $stmtidcheck->bind_result($bound_mid); 

        if ($stmtidcheck->fetch()) {
            $bound_mid = $bound_mid >= 35000 ? $bound_mid + 1 : 35000;
        }
        $stmtidcheck->close();
        
        // SQL to insert data into Clients Table ------------------------------------------------
        $sql = "INSERT INTO Clients (client_id, reseller_id, Client_name, Client_address, Client_email, Client_phone) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssi", $bound_mid, $res_id, $companyName, $companyAddress, $clientEmail, $companyPhone);
        if ($stmt->execute()) {
            // echo "Ins Successful<br>";
        }
        $stmt->close();

        // Update of the console Status
        $status = "In Use";
        $updcons = "UPDATE console SET console_status = ? WHERE uid = ? and device_id = ?";
        $stmtupdcons = $conn->prepare($updcons);
        $stmtupdcons->bind_param("sss", $status, $uid, $deviceid);
        $stmtupdcons->execute();
        $stmtupdcons->close();

        // Update of the console Client ID
        $updconscid = "UPDATE Console_Asociation SET Client_id = ? WHERE uid = ?";
        $stmtupdconscid = $conn->prepare($updconscid);
        $stmtupdconscid->bind_param("ii", $bound_mid, $uid);
        $stmtupdconscid->execute();
        $stmtupdconscid->close();

      // Client Login user insert ---------------------------------------------------------------
        $hashedPassword = password_hash($companypassword, PASSWORD_DEFAULT);
        $token = generateToken(); // Generate a unique token

        $stmtin = $conn->prepare("INSERT INTO login (username, password, client_id, name, last_name, token) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtin->bind_param("ssssss", $clientEmail, $hashedPassword, $bound_mid, $firstname, $lastname, $token);

        if ($stmtin->execute()) {
            // Create the activation link
            $activation_link = "https://ehonenergy.com.au/vmi/login/activate.php?token=$token";
            
            // Prepare the email content
            $email_subject = "Activate Your Account";
            $email_content = "<p>Dear $firstname,</p>";
            $email_content .= "<p>Thank you for registering. Please click the link below to activate your account:</p>";
            $email_content .= "<p><a href='$activation_link'>Activate Account</a></p>";
            $email_content .= "<p>If you did not register, please ignore this email.</p>";

            // Send the email
            $email_status = send_email($clientEmail, $email_subject, $email_content);

            if ($email_status['status'] == 'success') {
                $response['status'] = 'success';
                $response['message'] = 'Company created successfully. An activation email has been sent.';
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Company created successfully, but failed to send activation email. ' . $email_status['message'];
            }
            echo json_encode($response);
            exit();
        } else {
            $response['status'] = 'error';
            $response['message'] = 'An error occurred while creating the company.';
            echo json_encode($response);
            exit();
        }
        $stmtin->close();

    }
    $conn->close();
}
?>
