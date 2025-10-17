<?php
    include('../../db/dbh2.php');
    include('../../db/log.php');  
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    $data = json_decode(file_get_contents('php://input'), true);

    $firstname = $data['firstname'] ?? '';
    $lastname = $data['lastname'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $level = $data['level'] ?? '';
    $casecheck = $data['casecheck'] ?? ''; // Ensure this is passed or defined properly
    $active = 1;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $response = []; // Initialize response array

    if ($casecheck == 0) {
        $stmt = $conn->prepare("INSERT INTO login (username, password, access_level, client_id, active, name, last_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiss", $email, $hashedPassword, $level, $companyId, $active, $firstname, $lastname);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $response = [
                'status' => 'success',
                'message' => 'Data inserted successfully',
                'receivedData' => [
                    'email' => $email,
                    'level' => $level,
                    'companyId' => $companyId,
                    'active' => $active,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                ],
            ];
        } else {
            $response = ['status' => "Error    $companyId: $conn->error"];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
?>
