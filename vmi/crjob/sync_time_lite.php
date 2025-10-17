<?php
  include('../db/dbh2.php');
  include('../db/Datetime.php');


// Retrieve the company IDs from the database
$sql = "SELECT cls.client_id, cls.mcs_liteid, cs.device_id as client_key, cs.uid FROM Clients as cls JOIN Console_Asociation as ca on cls.client_id = ca.client_id 
JOIN console as cs on cs.uid=ca.uid where device_type = 200";
$resultid = $conn->query($sql);

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $companyId = $row["client_id"];
        $key = $row["client_key"];
        $masterid = $row["mcs_liteid"];
        $uid = $row["uid"];
        $parts = explode('_', $key);
        $token = $parts[0];
        $siteid = $parts[1];
        // API endpoint URL
        $url = 'https://mcs-connect.com/api/v1/sites/' . $masterid . '/' . $siteid . '/';
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
            $queryTemplate = "UPDATE console SET last_conndate = ?, last_conntime = ? WHERE uid = ?";

            $stmt = $conn->prepare($queryTemplate);
            $site_info = 'MCS_LITE';
            $last_sync_datetime = $data['last_sync_datetime']; // e.g., "2025-10-09T23:30:23.571002+10:00"
            
            // Use the new datetime conversion function (same as dipread_lite.php)
            $convertedDateTime = convertDateTimeWithTimezone($last_sync_datetime, $uid, $conn);
            
            if ($convertedDateTime === null) {
                // Fallback to old method if conversion fails
                $dateTimecs = new DateTime($last_sync_datetime);
                // Store as-is without timezone conversion
                $syncdate = $dateTimecs->format('Y-m-d');
                $synctime = $dateTimecs->format('H:i:s');
            } else {
                // Use the site's LOCAL timezone date/time for display
                $syncdate = $convertedDateTime['local_date'];
                $synctime = $convertedDateTime['local_time'];
                // Also have UTC values available if needed
                $syncdate_utc = $convertedDateTime['utc_date'];
                $synctime_utc = $convertedDateTime['utc_time'];
            }
            
            $id = $data['id'];
            if($siteid==$id){
            echo "$companyId, $uid, $last_sync_datetime, $id<br>";
            echo "Local time: $syncdate $synctime<br>";
            if (isset($syncdate_utc)) {
                echo "UTC time: $syncdate_utc $synctime_utc<br>";
            }
            $stmt->bind_param('ssi', $syncdate, $synctime, $uid);

            // Execute the query
            if($stmt->execute()){
            echo "ID: $id<br>";
            echo "Console updated successfully<br>";
            echo "-------------------<br>";
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
