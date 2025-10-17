<?php
    include('../db/dbh2.php');

// Retrieve the company IDs from the database
$sql = "SELECT * FROM Clients WHERE client_id in (35016);";
$resultid = $conn->query($sql);

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $companyId = $row["client_id"];
        $masterid = $row["mcs_liteid"];
        $token = '75b445414c0d679dcf10524265525722dce614bb';
        // $datecall = '2024-09-13';
        // API endpoint URL
        $url = 'https://mcs-connect.com/api/v1/sites/' . $masterid . '/';
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
        $devid = "";
        $data = json_decode($response, true);
        echo $response . "<br><br>";
        if ($data !== null && is_array($data)) {
            $site_info = 'MCS_LITE';
            foreach ($data['results'] as $result) {
                $id = $result['id'];
                $siteName = $result['name'];  
                $devid =  "$token" . "_" . "$id" . "_" . "$masterid";
                $sql = "INSERT IGNORE INTO console (device_id, device_type, man_data) values (?, ?, ?);";
                $stmt = $conn->prepare($sql);
                $dv_type = 200;         
                $stmt->bind_param("sss", $devid, $dv_type, $date);
                if ($stmt->execute()){
                    if ($stmt->affected_rows > 0) {
                        echo "INS: $masterid, $siteName, $site_info, $id<br>";
                        // Retrieve the last inserted ID
                        $lastId = $conn->insert_id;
                        echo "UID: $lastId<br>";
                        $ascid = "$id";
                        $distid = 15100;
                        $resid = 15100;
                        $stmt->close();
                        $sqlca = "INSERT IGNORE INTO Console_Asociation (id, uid, dist_id, reseller_id, Client_id, sales_date, sales_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmtca = $conn->prepare($sqlca);
                        $stmtca->bind_param("sssssss", $ascid, $lastId, $distid, $resid, $companyId, $date, $time);
                        $stmtca->execute();
                        $stmtca->close();
                    } else {
                        echo "Duplicate entry skipped for device_id: $devid<br>";
                    }
                }
                else {
                    echo "Error $conn->error";
                }					
            }
        }
    } 
} 
else {
    echo "end";
}
?>
