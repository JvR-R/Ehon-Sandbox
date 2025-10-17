<?php
include('../../db/dbh2.php');
include('../../db/check.php');
include('../../db/cs_msg.php');
include('../../db/Datetime.php');

$logOutput = ""; // Initialize a variable to store log messages
$logFilePath = "../Logs/registration.log"; // Define the log file path

$date = date('Y-m-d'); 
$time = date('H:i:s');
$logOutput .= "\r\n$date, $time, ";

// Initialize variables to prevent "undefined variable" warnings
$fw = "";
$bootup = "";
$uid = "";

// Check if data is received via the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = file_get_contents("php://input");
    $logOutput .= "Received Message: $message\r\n";
} else {
    $logOutput .= "Sv POST: Error\r\n";
    file_put_contents($logFilePath, $logOutput, FILE_APPEND);
    error_log("Failed to write to log file: $logFilePath");
    exit;
}

// Check if any data is received
if (!empty($message)) {
    $status = "In Stock";
    $separatedParts = separateMessage($message);

    // Debugging logs
    error_log("Separated Message: " . print_r($separatedParts, true));

    // Extract parts safely
    $uid2 = $separatedParts['uid'] ?? "";
    $type = $separatedParts['msgtype'] ?? "";
    $checksummsg = $separatedParts['checksum'] ?? "";
    $msgdata = $separatedParts['data'] ?? "";

    $messcheck = substr($message, 0, -3);
    $chk = checksum($messcheck);
    $response = ['status' => 'error', 'message' => 'Initial error', 'data' => []];

    if ($checksummsg == $chk) {
        if ($type == 'RG') {
            // Decode JSON safely
            $msgdataArray = json_decode($msgdata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON Decode Error: " . json_last_error_msg());
                exit;
            }

            // Extract values safely
            $fw = $msgdataArray['build'] ?? "";
            $dvtype = $msgdataArray['type'] ?? "";

            // Check if device is already registered
            $checkQuery = "SELECT uid FROM console WHERE device_id = ?";
            if ($checkStmt = $conn->prepare($checkQuery)) {
                $checkStmt->bind_param("s", $uid2);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $checkStmt->bind_result($uid);
                    $checkStmt->fetch();
                    $adjustedDateTime = Timezone($uid);

                    $respdata = [
                        'uid' => $uid,
                        'date0' => $adjustedDateTime['date0'],
                        'time0' => $adjustedDateTime['time0'],
                        'date' => $adjustedDateTime['date'],
                        'time' => $adjustedDateTime['time'],
                        'offset' => $adjustedDateTime['offset']
                    ];
                    $resp = response($respdata, $uid, $type);

                    $datebt = is_array($adjustedDateTime['date']) ? json_encode($adjustedDateTime['date']) : $adjustedDateTime['date'];
                    $timebt = is_array($adjustedDateTime['time']) ? json_encode($adjustedDateTime['time']) : $adjustedDateTime['time'];
                    $bootup = "$datebt T $timebt";

                    $checkStmt->close();

                    // Update console data
                    $updQuery = "UPDATE console SET firmware = ?, bootup = ? WHERE uid = ?";
                    if ($updStmt = $conn->prepare($updQuery)) {
                        $updStmt->bind_param("sss", $fw, $bootup, $uid);
                        $updStmt->execute();
                        $updStmt->close();
                    } else {
                        error_log("Update Query Error: " . $conn->error);
                    }

                    file_put_contents($logFilePath, $logOutput, FILE_APPEND);
                    exit;
                }
            } else {
                error_log("Check Query Error: " . $conn->error);
            }

            // Insert new device
            $sql = "INSERT INTO console (device_id, device_type, man_data, console_status, firmware) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssss", $uid2, $dvtype, $date, $status, $fw);
                if ($stmt->execute()) {
                    $last_id = $conn->insert_id;
                    $utcDateTime = new DateTime('now', new DateTimeZone('UTC'));
                    $utcDate = $utcDateTime->format('Y-m-d');
                    $utcTime = $utcDateTime->format('H:i:s');

                    $respdata = [
                        'uid' => $last_id,
                        'date0' => $utcDate,
                        'time0' => $utcTime,
                        'offset' => "+10:00",
                    ];
                    $resp = response($respdata, $last_id, $type);
                } else {
                    error_log("Insert Error: " . $conn->error);
                }
                $stmt->close();
            } else {
                error_log("Insert Query Error: " . $conn->error);
            }
        } else {
            $resp = "Wrong Type\r\n";
        }
    } else {
        $resp = "Wrong Checksum\r\n";
    }
} else {
    $resp = "Sv EMPTY: $message\r\n";
}

// Logging
$logOutput .= "Response: $resp\n(fw: $fw, bootup: $bootup, uid: $uid)\n";

if (file_put_contents($logFilePath, $logOutput, FILE_APPEND) === false) {
    error_log("Failed to write to log file: $logFilePath");
} else {
    error_log("Successfully wrote to log file: $logFilePath");
}
?>
