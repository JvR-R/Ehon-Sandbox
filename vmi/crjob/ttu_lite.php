<?php
    // include('../db/dbh2.php');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

// API endpoint URL
$url = 'https://mcs-connect.com/api/v1/user/';

// Authorization token
// $token = 'b477440d36fdc7ee139863108a241c44e0a8b7ae';
$token = '30a263539afb914de7b131d46e08af627619ca4d';


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

    foreach ($data['customers'] as $company) {
        $id = $company['id'];
        $companyName = $company['name'];
        $count++;
        $resid = 15101;
        $clientid = 35016;
        $clientemail = "admin@$companyName";
        // $sqlcl = "INSERT INTO Clients (client_id, reseller_id, Client_name, Client_email,mcs_liteid) VALUES (?, ?, ?, ?, ?)";
        // $stmtcl = $conn->prepare($sqlcl);
        // $stmtcl->bind_param("iissi", $clientid, $resid, $companyName, $clientemail, $id);
        // if($stmtcl->execute()){
            echo "Client_id: $clientid<br>";
            echo "ID: $id<br>";
            echo "Company: $companyName<br>";
            // echo "email: $clientemail<br>";
        // } else{
        //     echo "Error $conn->error<br>";
        // }
        // $stmtcl->close();
    }
echo "Total: $count<br>";
$conn->close();
} else {
    echo "Error: Unable to decode API response.";
}

?>
