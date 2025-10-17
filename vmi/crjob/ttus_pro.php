<?php
    include('../db/dbh2.php');


// Retrieve the company IDs from the database
$sql = "SELECT * FROM Clients WHERE mcs_clientid = 1629";
$resultid = $conn->query($sql);

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $companyId = $row["client_id"];
        // $key = $row["client_key"];
        $masterid = 1326;
        // $uid = $row["uid"];
        // $parts = explode('_', $key);
        $token = '3ca13bcd0bc2592b8e559eccaba192fcf9e5472e';
        // $token = '21e3b672a980bba3393c8e92bd368d98d4f89e10';
        // API endpoint URL
        $url = 'https://mcstsm.com/api/v1/company/' . $masterid . '/sites/';
        echo "API URL: $url<br>";

        // cURL initialization
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Token ' . $token));

        // Execute the request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
            exit;
        }
        // Close cURL
        curl_close($ch);

        $data = json_decode($response, true);
        echo $response . "<br>";
        if ($data !== null && is_array($data)) {
            $site_info = 'MCS_PRO';
            foreach ($data['results'] as $result) {
                $id = $result['id'];
                $siteName = $result['name'];
                $latitude = $result['latitude'];
                $longitude = $result['longitude'];
                $gps = $latitude . ", " . $longitude;
                $status = $result['status'];        
                $devid =  "$token" . "_" . "$id" . "_" . "$masterid";
                // $sql = "INSERT INTO console (device_id, device_type, man_data, console_coordinates) values (?, ?, ?, ?);";
                // $stmt = $conn->prepare($sql);
                // $dv_type = 201;         
                // $stmt->bind_param("ssss", $devid, $dv_type, $date, $gps);
                // if ($stmt->execute()){
                echo "INS: $masterid, $siteName, $site_info, $id, $status<br>DEV:$devid<br>";
                // // Retrieve the last inserted ID
                // $lastId = $conn->insert_id;
                // echo "$id, UID: $lastId<br>"; 
                // $ascid = $masterid . $id;
                // $distid = 15100;
                // $resid = 15101;
                // $stmt->close();
                // $sqlca = "INSERT INTO Console_Asociation (id, uid, dist_id, reseller_id, Client_id, sales_date, sales_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
                // $stmtca = $conn->prepare($sqlca);
                // $stmtca->bind_param("sssssss", $ascid, $lastId, $distid, $resid, $companyId, $date, $time);
                // $stmtca->execute();
                // $stmtca->close();
                // }
                // else {
                //     echo "Error $conn->error";
                // }
                					
            }
        }
 
    
    } 
} 
else {
    echo "end";
    }
 
//  $stmt->close();
//  $conn->close();
 ?>
