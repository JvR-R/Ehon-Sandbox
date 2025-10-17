<?php
declare(strict_types=1);

include('../../db/dbh2.php');
include('../../db/check.php');
include('../../db/cs_msg.php');

// ----------------------------------------------------
// Configuration
// ----------------------------------------------------
$logFilePath = "../Logs/e_link.log";

// ----------------------------------------------------
// Entry Point
// ----------------------------------------------------
logMessage("[$date-$time]");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("Error: This script only accepts POST requests.");
    sendResponse(["response" => "Sv POST: Error"]);
    exit;
}

// Retrieve JSON content
$message = file_get_contents("php://input");
logMessage("Incoming message: " . $message);

if (empty($message)) {
    logMessage("Error: Empty message body.");
    sendResponse(["response" => "Empty message body."]);
    exit;
}

// Separate the message
$parts      = separateMessage($message);
$uid        = isset($parts['uid']) ? (int)$parts['uid'] : 0;   
$type       = $parts['msgtype'] ?? null;
$checksum   = $parts['checksum'] ?? null;
$rawData    = $parts['data'] ?? null;

if (!$uid || !$type || !$checksum) {
    logMessage("Error: Missing required fields.");
    sendResponse(["response" => "Invalid message format."]);
    exit;
}

// Validate checksum
$messcheck = substr($message, 0, -3);
$calculatedChecksum = checksum($messcheck);

if ($checksum !== $calculatedChecksum) {
    logMessage("Checksum Error");
    sendResponse(["response" => "Checksum Error"]);
    exit;
}

// Process the request by type
$responseMessage = "OK"; // Default success response
switch ($type) {
    case 'UT':
        $responseMessage = handleUT($uid, $rawData);
        break;
    default:
        $responseMessage = "Unknown message type: $type";
        break;
}

// Send final response
$data     = ["response" => $responseMessage];
$response = response($data, $uid, $type);
logMessage("Final response: " . $response);
// echo $response;

// ----------------------------------------------------
// Functions
// ----------------------------------------------------

/**
 * Handle the UT message type.
 *
 * @param int    $uid
 * @param string $rawData  (JSON-encoded data)
 * @return string
 */
function handleUT(int $uid, string $rawData): string
{
    global $conn;

    $devices = json_decode($rawData, true);
    if (!is_array($devices)) {
        return "Data format error";
    }

    // Fetch Tanks for later use
    $tanks = fetchTanks($uid);

    // Iterate over devices
    foreach ($devices as $device) {
        if (isset($device['InternalSlave'])) {
            updateInternalSlave($uid, $device['InternalSlave']);
        }

        if (!isset($device['deviceType'])) {
            continue;
        }

        $deviceType   = $device['deviceType'];
        $deviceIds    = $device['deviceIds']    ?? [];
        $uartNumber   = $device['uartNumber']   ?? null;
        $deviceInfo   = $device['deviceInfo']   ?? null;

        // Handle by device type
        switch ($deviceType) {
            case '20': // Some custom logic
                handleDeviceType20($uid, $uartNumber, $deviceInfo, $deviceIds);
                break;

            case '10': // Some custom logic for FMS
                handleDeviceType10($uid, $uartNumber, $deviceInfo, $deviceIds, $tanks);
                break;

            case '0':  // Another type
                handleDeviceType0($uid, $uartNumber);
                break;

            case '40':
                handleDeviceType40($uid, $uartNumber, $deviceInfo);
                break;

            default:
                // Device type unknown or unhandled
                break;
        }
    }

    return "OK";
}

/**
 * Fetch tank data for a given UID.
 * Returns an associative array keyed by tank_id.
 */
function fetchTanks(int $uid): array
{
    global $conn;

    $sql = "SELECT tank_id, fms_id, fms_type, fms_uart 
            FROM Tanks 
            WHERE uid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $result = $stmt->get_result();

    $tanks = [];
    while ($row = $result->fetch_assoc()) {
        $tank_id = (int)$row['tank_id'];
        $tanks[$tank_id] = [
            'fms_id'   => $row['fms_id'],
            'fms_type' => $row['fms_type'],
            'fms_uart' => $row['fms_uart'],
        ];
    }
    $stmt->close();

    return $tanks;
}

function updateInternalSlave(int $uid, int $slave)
{
    global $conn;
    $sql = "UPDATE console SET internalslave = ? WHERE uid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $slave, $uid);
    $stmt->execute();
    $stmt->close();
}

/**
 * Example of updating console for deviceType = 20
 */
function handleDeviceType20(int $uid, ?int $uartNumber, ?int $deviceInfo, array $deviceIds)
{
    global $conn;

    if ($uartNumber === null) {
        return;
    }

    // If uartNumber != 1, just update the console table
    if ($uartNumber !== 1) {
        $sql = "UPDATE console SET uart{$uartNumber} = ? WHERE uid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $deviceInfo, $uid);
        $stmt->execute();
        $stmt->close();
    } else {
        // If uartNumber == 1, also update UART1_ID with a comma-separated list
        $uart1IdsString = implode(',', $deviceIds);
        $sql = "UPDATE console 
                SET uart{$uartNumber} = ?, UART1_ID = ? 
                WHERE uid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isi', $deviceInfo, $uart1IdsString, $uid);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Example of updating console and Tanks for deviceType = 10 (FMS devices)
 */
function handleDeviceType10(
    int $uid,
    ?int $uartNumber,
    ?int $deviceInfo,
    array $deviceIds,
    array &$tanks
) {
    global $conn;

    if ($uartNumber === null || $deviceInfo === null) {
        return;
    }

    // Update console first
    $sql = "UPDATE console SET uart{$uartNumber} = ? WHERE uid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $deviceInfo, $uid);
    $stmt->execute();
    $stmt->close();

    // Update Tanks
    foreach ($deviceIds as $deviceId) {
        $deviceId = (string)$deviceId; // ensure string for comparison

        foreach ($tanks as $tank_id => $tankData) {
            $fms_ids   = array_map('trim', explode(',', $tankData['fms_id']));
            $fms_types = array_map('trim', explode(',', $tankData['fms_type']));
            $fms_uarts = array_map('trim', explode(',', $tankData['fms_uart']));

            // Ensure each array has length 3 (or whatever your scheme is)
            $desired_length = 3;
            $fms_ids   = array_pad($fms_ids,   $desired_length, '0');
            $fms_types = array_pad($fms_types, $desired_length, '0');
            $fms_uarts = array_pad($fms_uarts, $desired_length, '0');

            // Check if $deviceId is in $fms_ids
            $index = array_search($deviceId, $fms_ids);
            if ($index !== false) {
                // Update the arrays
                $fms_types[$index] = (string)$deviceInfo;
                $fms_uarts[$index] = (string)$uartNumber;

                // Convert back to comma-separated
                $fms_type_str = implode(',', $fms_types);
                $fms_uart_str = implode(',', $fms_uarts);

                // Update in DB
                $updateSql = "UPDATE Tanks 
                              SET fms_type = ?, 
                                  fms_uart = ? 
                              WHERE uid = ? 
                                AND tank_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param('ssii', $fms_type_str, $fms_uart_str, $uid, $tank_id);
                $updateStmt->execute();
                $updateStmt->close();

                // Update local $tanks so we don’t lose changes
                $tanks[$tank_id]['fms_type'] = $fms_type_str;
                $tanks[$tank_id]['fms_uart'] = $fms_uart_str;
            }
        }
    }
}

/**
 * Example of deviceType = 0
 */
function handleDeviceType0(int $uid, ?int $uartNumber)
{
    global $conn;
    if ($uartNumber === null) {
        return;
    }

    $dv_flag = 0;
    $sqlSel  = "SELECT UART3, UART5, UART1, UART6 
                FROM console 
                WHERE uid = ? 
                  AND dv_flag = ?";
    $stmtSel = $conn->prepare($sqlSel);
    $stmtSel->bind_param('ii', $uid, $dv_flag);
    $stmtSel->execute();
    $stmtSel->bind_result($uart3, $uart5, $uart1, $uart6);
    $stmtSel->fetch();
    $stmtSel->close();

    if (($uart3 !== 0 && $uartNumber == 3) || ($uart5 !== 0 && $uartNumber == 5)) {
        $flag = 1;
        $sql  = "UPDATE console 
                 SET dv_flag = ?, 
                     uart{$uartNumber} = 0 
                 WHERE uid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $flag, $uid);
        $stmt->execute();
        $stmt->close();
    } elseif (($uart1 !== 0 && $uartNumber == 1) || ($uart6 !== 0 && $uartNumber == 6)) {
        $sql = "UPDATE console 
                SET uart{$uartNumber} = 0 
                WHERE uid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Example of deviceType = 40
 */
function handleDeviceType40(int $uid, ?int $uartNumber, ?int $deviceInfo)
{
    global $conn;
    if ($uartNumber === null || $deviceInfo === null) {
        return;
    }

    $sql = "UPDATE console SET uart{$uartNumber} = ? WHERE uid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $deviceInfo, $uid);
    $stmt->execute();
    $stmt->close();
}

/**
 * Utility function to log messages to a file.
 */
function logMessage(string $message): void
{
    global $logFilePath;
    file_put_contents($logFilePath, $message . "\r\n", FILE_APPEND);
}

/**
 * Utility function to output a response in your existing format.
 */
function sendResponse(array $data): void
{
    global $uid, $type;
    $datastr = response($data, $uid, $type);
    echo $datastr;
}
?>
