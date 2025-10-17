<?php
include('../../db/dbh2.php');
include('../../db/check.php');
include('../../db/cs_msg.php');
include('../../db/Datetime.php');
$logOutput = ""; // Initialize a variable to store log messages
$logFilePath = "../Logs/Fuel-quality.log"; // Define the log file path
$logOutput .= "\r\n[$date-$time]";
// Check if data is received via the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the JSON content
    $message = file_get_contents("php://input");
    $logOutput .= $message . "\r\n";
    if (!empty($message)) {
        $separatedParts = separateMessage($message);

        $uid = $separatedParts['uid'];
        $type = $separatedParts['msgtype'];
        $checksummsg = $separatedParts['checksum'];
        $msgdata = $separatedParts['data'];
        $messcheck = substr($message, 0, -3);

        //checksum calculation
        $chk = checksum($messcheck);

        $response = ['status' => 'error', 'message' => 'Initial error', 'data' => []];
        if ($checksummsg == $chk) {
            if ($type == 'FQ') {
                $adjustedDateTime = Timezone($uid);
                $timezoneOffset = $adjustedDateTime['offset'];
                $data = json_decode($msgdata, true);

                // Check if decoding was successful and if $data is an array
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        $timest = $data['timestamp'] ?? null;
                        $tank_number = $data['tank_number'] ?? null;
                        $temperature = $data['temperature'] ?? null;
                        $particle_4um = $data['particle_4um'] ?? null;
                        $particle_6um = $data['particle_6um'] ?? null;
                        $particle_14um = $data['particle_14um'] ?? null;
                        $particle_21um = $data['particle_21um'] ?? null;
                        $time = $data['time'] ?? null;
                        $date = $data['date'] ?? null;
                        $datetimeString = $date . ' ' . $time;
                        $datetime = new DateTime($datetimeString);
                        preg_match('/([+-]?\d+):(\d+)/', $timezoneOffset, $matches);
                        $hours = (int)$matches[1];
                        $minutes = (int)$matches[2];

                        // Convert the entire offset to total hours (as a float)
                        $totalHours = $hours + $minutes / 60.0;

                        // Now you can use abs() safely
                        $intervalSpec = 'PT' . abs($totalHours) . 'H';
                        $interval = new DateInterval($intervalSpec);

                        // Determine whether to add or subtract the interval
                        if (strpos($timezoneOffset, '-') === 0) {
                            $datetime->sub($interval);
                        } else {
                            $datetime->add($interval);
                        }
                        $timeconsole = $datetime->format('H:i:s');
                        $dateconsole = $datetime->format('Y-m-d');

                        $selectclsql = "SELECT Client_id FROM Console_Asociation WHERE uid = ?";
                        $selectcl = $conn->prepare($selectclsql);
                        $selectcl->bind_param("i", $uid);
                        if($selectcl->execute()){
                            $selectcl->bind_result($client_id);
                            $selectcl->fetch();
                            $selectcl->close();
                            $insertfqsql = "INSERT INTO fuel_quality (client_id, uid, tank_id, fq_timestamp, particle_4um, particle_6um, particle_14um, particle_21um, fq_date0, fq_time0, fq_date, fq_time) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $insertfq = $conn->prepare($insertfqsql);
                            $insertfq->bind_param("iiisiiiissss",$client_id, $uid, $tank_number, $timest, $particle_4um, $particle_6um, $particle_14um, $particle_21um, $date, $time, $dateconsole, $timeconsole);
                            if($insertfq->execute()){
                                $respo = "OK";
                                $insertfq->close();
                            } else{
                                $respo = "Error";
                            }
                        }
                        else{
                            $respo = "Client Error";
                            $selectcl->close();
                        }
                        
                    
                } else {
                    $respo = "JSON Decode Error";
                }
            }
        } else {
            $respo = "Checksum Error";
        }
    }
} else {
    $respo = "Sv POST: Error";
}
$data = ["response" => $respo];
response($data, $uid, $type);
file_put_contents($logFilePath, $logOutput, FILE_APPEND);
?>
