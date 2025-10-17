<?php
  include('../db/dbh2.php');


// Retrieve the company IDs from the database
$sql = "SELECT cls.client_id, cls.reseller_id, cs.device_id as client_key, cs.uid, cls.mcs_clientid FROM Clients as cls JOIN Console_Asociation as ca on cls.client_id = ca.client_id JOIN console as cs on cs.uid=ca.uid where device_type = 201 and cls.mcs_clientid = 1629;";
$resultid = $conn->query($sql);

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $companyId = $row["client_id"];
        $key = $row["client_key"];
        $masterid = $row["mcs_clientid"];
        $uid = $row["uid"];
        $parts = explode('_', $key);
        $token = $parts[0];
        $siteid = $parts[1];
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
        // echo $response;
        if ($data !== null && is_array($data)) {
            $queryTemplate = "INSERT INTO Sites (client_id, uid, Site_name, Site_Info, mcs_id, site_status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            Site_name = VALUES(site_name),
            client_id = VALUES(client_id),
            uid = VALUES(uid),
            site_status = VALUES(site_status),
            Site_Info = VALUES(Site_Info)";

            $stmt = $conn->prepare($queryTemplate);
            $site_info = 'MCS_PRO';
            foreach ($data['results'] as $result) {
                $id = $result['id'];
                $siteName = $result['name'];
                $latitude = $result['latitude'];
                $longitude = $result['longitude'];
                $status = $result['status'];
                if($siteid==$id){
                echo "$masterid, $uid, $siteName, $site_info, $id, $status<br>";
                $stmt->bind_param('sissss', $companyId, $uid, $siteName, $site_info, $id, $status);

                // Execute the query
                if($stmt->execute()){
                echo "ID: $id<br>";
				echo "Site Name: $siteName<br>";
				echo "Latitude: $latitude<br>";
				echo "Longitude: $longitude<br>";
				echo "Status: $status<br>";
				echo "-------------------<br>";
                }
            }
                					
            }
            $stmt->close();
				
        }
 
    
    } 
} 
else {
    echo "end";
    }
 
//  $stmt->close();
//  $conn->close();
 ?>
