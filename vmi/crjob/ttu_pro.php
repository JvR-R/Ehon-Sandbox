<?php
  include('../db/dbh2.php');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

// API endpoint URL
$url = 'https://mcstsm.com/api/v1/user/';

// Authorization token
$token = '10a55a4a58b7ca7a3cebeea39b81c75286054bc4';


// cURL initialization
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Token ' . $token));

// Execute the request
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);
// echo $response;
$count = 0;
if ($data !== null && is_array($data)) {

    foreach ($data['companies'] as $company) {
        $id = $company['id'];
        $companyName = $company['name'];
        $count++;
        echo $companyName;
        echo $id . "<br>";
    //     $sql = "INSERT INTO console (device_id, device_type, man_data) values ('21e3b672a980bba3393c8e92bd368d98d4f89e10_$id', 10, '2024-02-12');";
    //     if ($stmt = $conn->prepare($sql)) {
    //         $stmt->execute();
    //         // Retrieve the last inserted ID
    //         $lastId = $conn->insert_id;
    //         echo "$companyName: $id, UID: $lastId<br>";
    //         $ascid = "1096_$id";
    //         $distid = 15100;
    //         $resid = 1096;
    //         $clientemail = "admin@$companyName";
    //         $stmt->close();
    //         $sqlcl = "INSERT INTO Clients (client_id, reseller_id, Client_name, Client_email) VALUES (?, ?, ?, ?)";
    //         $stmtcl = $conn->prepare($sqlcl);
    //         $stmtcl->bind_param("iiss", $id, $resid, $companyName, $clientemail);
    //         if($stmtcl->execute()){
    //             $stmtcl->close();
    //             $sqlca = "INSERT INTO Console_Asociation (id, uid, dist_id, reseller_id, Client_id, sales_date, sales_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
    //             $stmtca = $conn->prepare($sqlca);
    //             $stmtca->bind_param("sssssss", $ascid, $lastId, $distid, $resid, $id, $date, $time);
    //             $stmtca->execute();
    //             $stmtca->close();
    //         }
    //     } else {
    //         echo "Error: " . $conn->error;
    //     }


    }
echo $count;
} else {
    echo "Error: Unable to decode API response.";
}

?>
