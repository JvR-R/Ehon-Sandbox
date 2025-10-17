<?php
    include('../../db/dbh2.php');
    include('../../db/check.php');
    include('../../db/cs_msg.php');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
$logOutput = ""; // Initialize a variable to store log messages
$logFilePath = "../Logs/Ping-Server.log"; // Define the log file path
$logOutput .= "\r\n[$date-$time]";
$resp="No errors\r\n";
// Check if data is received via the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the value of the counter parameter
    $message = file_get_contents("php://input");


  // Check if any data is received
  if (!empty($message)) {
    $logOutput .= $message . "\r\n";
        //Message clasification function
        $separatedParts = separateMessage($message);
        $uid = $separatedParts['uid'];
        $type = $separatedParts['msgtype'];
        $checksummsg = $separatedParts['checksum'];
        $size = $separatedParts['size'];
        $msgdata = $separatedParts['data'];
        $messcheck = substr($message, 0, -3);
        // echo $size . "\r\n";
        //checksum calculation
        $chk = checksum($messcheck);

        $response = ['status' => 'error', 'message' => 'Initial error', 'data' => []];
        //Checksum verification
        if($checksummsg==$chk){

            if($type == 'PS'){
                
                $firmware = 0;
                $cfg = 0;
                $sqld = "SELECT fw_flag, cfg_flag, restart_flag, logs_flag FROM console WHERE uid = ?";
                $stmtd = $conn->prepare($sqld);
                $stmtd->bind_param("i", $uid);
                if ($stmtd->execute()) {
                    $stmtd->bind_result($firmware, $cfg, $restart, $logs);
                    $stmtd->fetch();
                    
                    $status = [
                        'firmware' => $firmware, 
                        'config' => $cfg,
                        'restart' => $restart, 
                        'logs' => $logs,
                    ];
                    // array_push($status, ['status' => $status]);
                    if (count($status) > 0) {
                        $response = ['data' => $status];
                        $resp = response($response, $uid, $type);
                        $stmtd->close();
                        $sqlflagupd = "UPDATE console SET fw_flag = 0, cfg_flag = 0, restart_flag = 0, last_conndate = ?, last_conntime = ? WHERE uid = ?";
                        $stmtflagupd = $conn->prepare($sqlflagupd);
                        $stmtflagupd->bind_param("ssi", $date, $time, $uid);
                        $stmtflagupd->execute();
                        $stmtflagupd->close();
                    } else {
                        $response['message'] = 'No products found';
                    }
                }
                else{
                    $response['message'] = 'Query execution failed';
                }
                $dataArray = json_decode($msgdata);
                foreach ($dataArray as $modemdata) {
                    $signal = $modemdata->signal;
                    // echo "$console_ip, $console_coordinates, $console_imei\n";
                   // Initialize the query and parameters
                    $sqltrs = "UPDATE console SET";
                    $params = [];
                    $types = "";
    
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
    // echo "Message EMPTY";
    $resp = 'Message EMPTY';
    }
}
else{
    $resp =  "Sv POST: Error<br>\r\n";
  }
  
  $logOutput .= "$resp\r\n";
  $conn->close();
  file_put_contents($logFilePath, $logOutput, FILE_APPEND);
?>