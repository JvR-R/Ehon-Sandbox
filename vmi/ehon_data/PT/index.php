<?php
  include('../../db/dbh2.php');
  include('../../db/check.php');
  include('../../db/cs_msg.php');
$logOutput = ""; 
$logFilePath = "../Logs/PT(transactions).log"; 
$logOutput .= "\r\n$date, $time, ";
$resp = "";
    include('../../db/Datetime.php');
// Check if data is received via the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $postInput = file_get_contents("php://input");
    $logOutput .= "\r\n$postInput\r\n"; // Log the raw POST input
    // echo "\r\n$postInput"; 
    $separatedParts = separateMessage($postInput);
    $uid = $separatedParts['uid'];
    $type = $separatedParts['msgtype'];
    $datast = $separatedParts["data"];
    $checksummsg = $separatedParts['checksum'];
    $messcheck = substr($postInput, 0, -3);
   //checksum calculation
    $chk = checksum($messcheck);
    $data = json_decode($datast);
    
    // $response = ['status' => 'error', 'message' => 'Initial error', 'data' => []];
    //Checksum verification
    if($checksummsg==$chk){
        if($type=="PT"){
            $adjustedDateTime = Timezone($uid);
            // Resolve timezone offset strictly from DB-provided values
            $timezoneOffset = null;
            if (is_array($adjustedDateTime)) {
                if (!empty($adjustedDateTime['offset_string'])) {
                    $timezoneOffset = $adjustedDateTime['offset_string'];
                } elseif (!empty($adjustedDateTime['site_timezone_offset'])) {
                    $timezoneOffset = $adjustedDateTime['site_timezone_offset'];
                } elseif (isset($adjustedDateTime['offset']) && $adjustedDateTime['offset'] !== '') {
                    // Convert decimal-hour offset (e.g., +10, +5.5) to +HH:MM
                    $decimalOffset = (float)$adjustedDateTime['offset'];
                    $signChar = $decimalOffset < 0 ? '-' : '+';
                    $absDecimal = abs($decimalOffset);
                    $offsetHours = (int)floor($absDecimal);
                    $offsetMinutes = (int)round(($absDecimal - $offsetHours) * 60);
                    $timezoneOffset = sprintf('%s%02d:%02d', $signChar, $offsetHours, $offsetMinutes);
                }
            }
            if (empty($timezoneOffset)) {
                // Fallback to UTC if timezone not found; log the issue
                $timezoneOffset = '+00:00';
                $logOutput .= "[warn] timezoneOffset not found for uid=$uid; using UTC (+00:00)\r\n";
            } else {
                $logOutput .= "Resolved timezoneOffset: $timezoneOffset for uid=$uid\r\n";
            }
            $piusiId = $data->piusiId;
            $transactionNum = $data->transactionNum;
            $date = $data->transDate;
            $time = $data->transTime; // Fixed assignment here
            $dateObject = DateTime::createFromFormat('dmy', $date);
            $timeObject = DateTime::createFromFormat('His', $time);
            $transDate2 = $dateObject->format('Y-m-d');
            $transTime2 = $timeObject->format('H:i:s');
            $driverId = $data->driverId;
            $odo = $data->odo === "000000" ? NULL : $data->odo;
            $rego = empty($data->rego) ? NULL : $data->rego;
            $driverName = $data->driverName;
            $tankNum = $data->tankNum;
            $volume = $data->volume;
            $pump_id = 1; // Default pump_id to 1
            // Insert data into the database
            $datetimeString = $transDate2 . ' ' . $transTime2;
            echo "Raw time value: $time\n";
            // Create a DateTime object from the combined string in UTC, then apply site offset
            $datetime = new DateTime($datetimeString, new DateTimeZone('UTC'));
  
            // Create a DateInterval from the timezone offset string, simplified approach
            // $intervalSpec = 'PT' . abs(str_replace(':', 'H', $timezoneOffset)) . 'H';
           // Parse the timezone offset string to extract the sign, hours, and minutes
            // Validate that timezoneOffset exists and is not empty
            if (!empty($timezoneOffset) && preg_match('/^([+-])(\d{2}):(\d{2})$/', $timezoneOffset, $matches)) {
                $sign = $matches[1]; // '+' or '-'
                $hours = (int)$matches[2];
                $minutes = (int)$matches[3];
            } else {
                // Fallback to UTC if offset format is invalid or empty
                $sign = '+';
                $hours = 0;
                $minutes = 0;
            }

            // Construct the interval specification string
            $intervalSpec = 'PT' . $hours . 'H' . $minutes . 'M';

            // Create the DateInterval object
            $interval = new DateInterval($intervalSpec);

            // Determine whether to add or subtract the interval
            if ($sign === '-') {
                $datetime->sub($interval);
            } else {
                $datetime->add($interval);
            }

            $timeconsole = $datetime->format('H:i:s');
            $dateconsole = $datetime->format('Y-m-d');
            $sql = "INSERT INTO client_transaction (uid, piusi_transaction_id, fms_id, transaction_date, transaction_date_utc0, transaction_time, transaction_time_utc0, card_number, card_holder_name, odometer, registration, tank_id, pump_id, dispensed_volume) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssssssssiis", $uid, $transactionNum, $piusiId, $dateconsole, $transDate2, $timeconsole, $transTime2, $driverId, $driverName, $odo, $rego, $tankNum, $pump_id, $volume);
              try {
                if ($stmt->execute()) {
                    $resp = "OK";
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    // Specific handling for duplicate entry
                    $resp = "DUPLICATE";
                    $sqldup = "INSERT INTO transaction_duplicates (uid, piusi_transaction_id, piusi_id, transaction_date, transaction_date_utc0, transaction_time, transaction_time_utc0, card_number, card_holder_name, odometer, registration, tank_id, pump_id, dispensed_volume) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmtdup = $conn->prepare($sqldup);
                    $stmtdup->bind_param("iisssssssssiis", $uid, $transactionNum, $piusiId, $dateconsole, $transDate2, $timeconsole, $transTime2, $driverId, $driverName, $odo, $rego, $tankNum, $pump_id, $volume);
                    $stmtdup->execute();
                    $stmtdup->close();
                } else {
                    // Handling for other errors
                    $resp = (string)$e->getCode();
                }
            }   
            
            $stmt->close();
        } else {
            $resp = "Wrong Type";
        }
    } else {
        $resp = "Wrong Checksum";
    }
} else {
    $resp = "ERROR\n"; // Handling incorrect request method
}

$response = ['Response' => $resp];
$rep = response($response , $uid, $type);
// Log output
$logOutput .= "Response: " . $rep . "\r\n";
file_put_contents($logFilePath, $logOutput, FILE_APPEND);
?>




