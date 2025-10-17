<?php
  include('../db/dbh2.php');


// Retrieve the company IDs from the database
$sql = "SELECT st.*, cs.device_id, clc.mcs_liteid FROM console as cs 
        JOIN Sites as st on st.uid = cs.uid 
        JOIN Clients as clc on clc.Client_id = st.Client_id
        where device_type = 200 and cls.client_id BETWEEN 35024 AND 35079";

$resultid = $conn->query($sql); 

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $site_id = $row["mcs_id"];
        $siteid_ehon = $row["Site_id"];
        $key = $row["device_id"];
        $masterid = $row["mcs_liteid"];
        $companyId = $row["Client_id"];
        $uid = $row["uid"];
        $parts = explode('_', $key);
        $token = $parts[0];
        // API endpoint URL
        $url = 'https://mcs-connect.com/api/v1/sites/' . $masterid . '/' . $site_id . '/';
        echo "API URL: $url<br>UID: $uid<br>----------------<br>";

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
            $queryTemplate = "INSERT INTO Tanks (tank_id, uid, client_id, Site_id, Tank_name, product_id, capacity, current_volume)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            capacity = VALUES(capacity),
            current_volume = VALUES(current_volume)";

            $stmt = $conn->prepare($queryTemplate);
            $site_info = 'MCS_LITE';
            foreach ($data['tanks'] as $result) {
                $id = $data['id'];
                $tankname = $data['name'];
                $product = $result['product'];
                $tank_no = $result['number'];
                $current_vol = $result['current_volume'];
                $capacity = $result['capacity'] ?? 0;
                $product_id = 0;
                if($product == 'Diesel'){
                    $product_id = 1;
                }
                else if($product == 'Adblue'){
                    $product_id = 5;
                }
                $stmt->bind_param('iisisidd', $tank_no, $uid, $companyId, $siteid_ehon, $tankname, $product_id, $capacity, $current_vol);

                // Execute the query
                // if($stmt->execute()){
                echo "ID: $id<br>";
				echo "tank Name: $tankname<br>";
                echo "product: $product<br>";
                echo "tank_no: $tank_no<br>";
                echo "capacity: $capacity<br>";
				echo "-------------------<br>";                
                // }                          					
            }
            $stmt->close();				
        }
    } 
} 
else {
    echo "end";
    }
 
 ?>
