<?php
include('../../db/dbh2.php');
include('../../db/check.php');
include('../../db/cs_msg.php');
$logOutput = ""; // Initialize a variable to store log messages
$logFilePath = "../Logs/Link_config.log"; // Define the log file path
$logOutput .= "\r\n[$date-$time]";
$resp="No errors\r\n";
// Check if data is received via the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the value of the counter parameter
    $message = file_get_contents("php://input");



  // Check if any data is received
  if (!empty($message)) {
    $logOutput .= $message . "\r\n";
    $separatedParts = separateMessage($message);

    $uid = $separatedParts['uid'];
    $type = $separatedParts['msgtype'];
    $checksummsg = $separatedParts['checksum'];
    $msgdata = $separatedParts['data'];
    $messcheck = substr($message, 0, -3);

    //checksum calculation
    $chk = checksum($messcheck);

    $response = [];
    if($checksummsg==$chk){
      // header('Content-Type: application/json');
      if($type == 'TN'){
        $sqld = "SELECT client_id, product_name, product_density, tank_id, capacity, chart_id, fms_id, tank_gauge_uart, tank_gauge_id, product_cte, product_basetemp, current_volume, ullage, temperature, tc_volume, volume_height, water_volume, water_height, dipr_date0, dipr_time0 FROM Tanks as ts join products as pr on ts.product_id = pr.product_id where uid = ?";
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("i", $uid);

        if ($stmtd->execute()) {
            $stmtd->bind_result($client_id, $product_name, $product_density, $tank_id, $capacity, $chart_id, $fms_id, $tg_port, $tg_id, $product_cte, $product_basetemp, $volume, $ullage, $temperature, $tc_vol, $vol_height, $water_vol, $water_height, $date0, $time0);
            $products = [];
            
            while ($stmtd->fetch()) {
                $formatted_number = number_format($product_density, 4);
                $formatted_cte = number_format($product_cte, 6);
                $product_info = [
                  'name' => $product_name, 
                  'density' => $formatted_number,
                  'cte' => $formatted_cte,
                  'base_temp' => $product_basetemp
                  // Add more product-related information here if needed
              ];
              $inventory = [
                'volume' => $volume, 
                'ullage' => $ullage,
                'temperature' => $temperature,
                'tc_vol' => $tc_vol,
                'vol_height' => $vol_height,
                'water_vol' => $water_vol,
                'water_height' => $water_height,
                'date' => $date0,
                'time' => $time0,
                // Add more product-related information here if needed
            ];
                array_push($products, ['id' => $tank_id, 'capacity' => $capacity, 'product' => $product_info, 'inventory' => $inventory]);
            }
            if (count($products) > 0) {
                $response = ['status' => 'success', 'data' => $products];
            } else {
                $response['message'] = 'No products found';
            }
        } else {
            $response['message'] = 'Query execution failed';
        }

        $json_length = strlen(json_encode($response));
        $json_length = $json_length + 2;
        $formatted_length = str_pad($json_length, 3, '0', STR_PAD_LEFT);
        $msg = "SV$uid" . "$formatted_length" . "TN" . json_encode($response);
        $checksum = checksum($msg);
        
        echo "SV$uid" . "$formatted_length" . "TN" . json_encode($response) . "%$checksum\r\n";
        $stmtd->close();
      } 
      else if($type == 'SC') {
        $sqld = "SELECT json_data, chart_name, chart_id FROM strapping_chart WHERE chart_id = ?";
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("i", $msgdata);
    
        if ($stmtd->execute()) {
            $stmtd->bind_result($responsesc, $chartname, $chartid);
            $stmtd->fetch();
            $dataArray = json_decode($responsesc, true);
            // Create a new array with the elements you want to add at the beginning
            $newElements = array(
                'chart_id' => $chartid,
                'chart_name' => $chartname
            );
            $stmtd->close();
            // The new elements will be at the beginning of the array
            $modifiedArray = array_merge($newElements, $dataArray);

            // Encode the modified array back into JSON
            $modifiedJsonString = json_encode($modifiedArray);
            // Assuming $responsesc is already a JSON string
            $json_length = strlen($modifiedJsonString);
            $json_length = $json_length + 2;
            $formatted_length = str_pad($json_length, 3, '0', STR_PAD_LEFT);
            $msg = "SV$uid" . "$formatted_length" . "SC" . $responsesc;
            $checksum = checksum($msg);
            $updQuery = "UPDATE console SET cfg_flag = 0, firmware = 0 WHERE uid = ?";
            $updStmt = $conn->prepare($updQuery);
            $updStmt->bind_param("s", $uid);
            $updStmt->execute();
            $updStmt->close();
            $tankupdq = "UPDATE Tanks SET offset_flag = 0 WHERE uid = ?";
            $tankupd = $conn->prepare($tankupdq);
            $tankupd->bind_param("s", $uid);
            $tankupd->execute();
            $tankupd->close();
            // echo "SV$uid" . "$formatted_length" . "SC" . $modifiedJsonString . "%$checksum\r\n";
            $resp = response($modifiedArray, $uid, $type);
        }
      }  
    else if ($type == 'DV') {
        $fmsData = [];
        $tgData = [];
        $relay_board = [];

        // Fetch the latest transaction number
        $sqltrs = "SELECT fms_id, MAX(piusi_transaction_id) as max_tr FROM client_transaction WHERE uid = ? GROUP BY fms_id";
        $stmttrs = $conn->prepare($sqltrs);
        $stmttrs->bind_param("i", $uid);
        $stmttrs->execute();
        $stmttrs->bind_result($fms_id, $piusi_tr);
        
        // Build an associative array mapping fms_id to piusi_tr
        $piusiTransactions = array();
        while ($stmttrs->fetch()) {
            $piusiTransactions[strval($fms_id)] = $piusi_tr;
        }
        $stmttrs->close();
        

        // Fetch tank and device data
        $sqlfms = "SELECT tank_gauge_type, tank_gauge_id, tank_gauge_uart, chart_id, ts.tank_id, crithigh_alarm, high_alarm, critlow_alarm, 
        low_alarm, fms_type, fms_id, fms_uart, offset_tank, relay_type, relay_uart, relay1, relay2, relay3, relay4, recon_time, fms_number 
        FROM Tanks as ts 
        JOIN alarms_config as ac ON (ts.client_id, ts.uid, ts.tank_id) = (ac.client_id, ac.uid, ac.tank_id) 
        WHERE ts.uid = ?";

        $stmtdv = $conn->prepare($sqlfms);
        $stmtdv->bind_param("i", $uid);
        $stmtdv->execute();
        $stmtdv->bind_result(
            $tank_gauge_type, $tank_gauge_id, $tank_gauge_uart, $chart_id, $tank_id, $crithigh_alarm, $high_alarm, 
            $critlow_alarm, $low_alarm, $fms_type, $fms_id, $fms_uart, $offset, $relay_type, $relay_uart, 
            $relay1, $relay2, $relay3, $relay4, $recon_time, $fms_number
        );

        while ($stmtdv->fetch()) {
            // Handle TG data as before
            if ($tank_gauge_uart > 0) {
                $tgData[] = [
                    'tg_id' => $tank_gauge_id,
                    'tg_type' => $tank_gauge_type,
                    'tg_uart' => $tank_gauge_uart,
                    'chart_id' => $chart_id,
                    'tank_id' => $tank_id,
                    'crithigh_alarm' => $crithigh_alarm,
                    'high_alarm' => $high_alarm,
                    'critlow_alarm' => $critlow_alarm,
                    'low_alarm' => $low_alarm,
                    'offset' => $offset,
                    'recon' => $recon_time
                ];
            }
            // Handle multiple FMS devices
            if ($fms_number > 0) {
                $fms_uart_array = explode(',', $fms_uart);
                $fms_type_array = explode(',', $fms_type);
                $fms_id_array = explode(',', $fms_id);

                for ($i = 0; $i < $fms_number; $i++) {
                    $current_fms_uart = isset($fms_uart_array[$i]) ? (int)$fms_uart_array[$i] : 0;
                    $current_fms_type = isset($fms_type_array[$i]) ? (int)$fms_type_array[$i] : 0;
                    $current_fms_id = isset($fms_id_array[$i]) ? (int)$fms_id_array[$i] : 0;

                    if ($current_fms_uart > 0 || $current_fms_type == 104) {
                        // Get the currentTransNum for this fms_id using integer keys
                        $currentTransNum = isset($piusiTransactions[$current_fms_id]) ? (int)$piusiTransactions[$current_fms_id] : 0;

                        $fmsData[] = [
                            'fms_id' => $current_fms_id,         // Now an integer
                            'fms_type' => $current_fms_type,     // Now an integer
                            'fms_uart' => $current_fms_uart,     // Now an integer
                            'tank_id' => (int)$tank_id,          // Ensure tank_id is an integer
                            'currentTransNum' => $currentTransNum // Ensure currentTransNum is an integer
                        ];
                    }
                }
            }
 
            // Handle relay board as before
            if ($relay_uart > 0) {
                $relay_board[] = [
                    'tank_id' => $tank_id,
                    'relay_type' => $relay_type,
                    'relay_uart' => $relay_uart,
                    'relay1' => $relay1,
                    'relay2' => $relay2,
                    'relay3' => $relay3,
                    'relay4' => $relay4
                ];
            }
        }
        $stmtdv->close();

        // Assign the FMS data to the response
        $response['fms'] = $fmsData;

        // Add the TG and relay board data to the response
        $response['tg'] = $tgData;
        $response['relay_board'] = $relay_board;

        // Encode and output the JSON response
        $resp = response($response, $uid, $type);
    }

     elseif ($type == 'BG'){

            $dataArray = json_decode($msgdata);
            foreach ($dataArray as $modemdata) {
                $console_ip = $modemdata->ip;
                $signal = $modemdata->signal;
                $console_imei = $modemdata->imei;
                $console_coordinates = $modemdata->coordinates;
                // echo "$console_ip, $console_coordinates, $console_imei\n";
               // Initialize the query and parameters
                $sqltrs = "UPDATE console SET";
                $params = [];
                $types = "";

                // Add console_ip if not null
                if (!empty($console_ip)) {
                    $sqltrs .= " console_ip = ?,";
                    $params[] = $console_ip;
                    $types .= "s";
                }

                // Add console_imei if not null
                if (!empty($console_imei)) {
                    $sqltrs .= " console_imei = ?,";
                    $params[] = $console_imei;
                    $types .= "s";
                }

                // Add console_coordinates if not null
                if (!empty($console_coordinates)) {
                    $sqltrs .= " console_coordinates = ?,";
                    $params[] = $console_coordinates;
                    $types .= "s";
                }
                // Add console_coordinates if not null
                if (!empty($signal)) {
                    $sqltrs .= " cs_signal = ?,";
                    $params[] = $signal;
                    $types .= "s";
                }

                // Remove the trailing comma and add the WHERE clause
                $sqltrs = rtrim($sqltrs, ',') . " WHERE uid = ?";
                $params[] = $uid;
                $types .= "i";

                // Prepare and execute the statement
                $stmttrs = $conn->prepare($sqltrs);
                $stmttrs->bind_param($types, ...$params);
                $stmttrs->execute();

                if($stmttrs->execute()){
                    $resp = "OK";
                }
                else{
                    $resp = "Error";
                }
                $stmttrs->close();
                $data = ["response" => $resp];
                $resp = response($data, $uid, $type);
            }
          
        
        }   
        else{
            // echo "Wrong Type";
            $resp = 'Wrong Type';
        }
    }
    else{
    // echo "Wrong Checksum";
    $resp = 'Wrong Checksum';
    }
  }
  else {
    // echo "Message EMPTY $message";
    $resp = 'Message EMPTY';
  }
}
else{
//   echo "Sv POST Error<br>\r\n";
  $resp = 'Sv POST Error';
}
$logOutput .= "$resp\r\n";
file_put_contents($logFilePath, $logOutput, FILE_APPEND);
?>

