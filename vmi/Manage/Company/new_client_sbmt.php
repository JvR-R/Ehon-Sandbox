<?php
include('../../db/dbh2.php'); 
ob_start();

header('Content-Type: application/json');

$response = array('success' => false, 'message' => '');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $clientName = $_POST['Client_name'];
    $clientAddress = $_POST['Client_address'];
    $clientPhone = $_POST['Client_phone'];
    $username = $_POST['username'];
    $reseller_id = $_POST['reseller_id'];
    $dist_id = $_POST['dist_id'];
    $clientrpassword = $_POST['Client_password'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];

    $idcheck = "SELECT MAX(client_id) as maxid FROM Clients";
    
    $stmtidcheck = $conn->prepare($idcheck);
    $stmtidcheck->execute();

    $client_id = null;
    $stmtidcheck->bind_result($client_id); 

    if ($stmtidcheck->fetch()) {
        $client_id = $client_id < 25000 ? 35000 : $client_id + 1;
    }

    $stmtidcheck->close();

    $sqlc = "INSERT INTO Clients (client_id, reseller_id, dist_id, Client_name, Client_address, Client_email, Client_phone) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtc = $conn->prepare($sqlc);
    $stmtc->bind_param("iiisssi", $client_id, $reseller_id, $dist_id, $clientName, $clientAddress, $username, $clientPhone);

    if ($stmtc->execute()) {
        $stmtc->close(); 
        $active = 1;
        $accesslvl = 8;
        $hashedPassword = password_hash($clientrpassword, PASSWORD_DEFAULT);
        $stmtin = $conn->prepare("INSERT INTO login (username, password, access_level, client_id, name, last_name, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtin->bind_param("ssiissi", $username, $hashedPassword, $accesslvl, $client_id, $firstname, $lastname, $active);
        $stmtin->execute();

        if ($stmtin->affected_rows > 0) {
            $stmtin->close();
            $response['success'] = true;
            $response['message'] = "Client and login information inserted successfully!";
        } else {
            $stmtin->close();
            $response['message'] = "Error inserting login information.";
        }
    } else {
        $stmtc->close(); 
        $response['message'] = "Error inserting client information.";
    }

    $conn->close();
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
ob_end_flush();
?>
