<?php
declare(strict_types=1);

include('../../db/dbh2.php');
include('../../db/check.php');
include('../../db/cs_msg.php');
include('../../crjob/level_alert.php');
include('../../db/Datetime.php');

ini_set('log_errors', '1');              // Enable error logging
ini_set('error_log', 'error_log.txt');   // Specify the PHP error log
error_reporting(E_ALL);

//----------------------------------------------------
// Configuration
//----------------------------------------------------
$logFilePath = "../Logs/Dip_reading.log";
$logOutput   = ""; // Will accumulate messages we append
$logOutput  .= "$date, $time, ";

//----------------------------------------------------
// MAIN ENTRY POINT
//----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finishAndLog("Sv POST: Error");
    exit;
}

// Get POST input
$message = file_get_contents("php://input");
if (empty($message)) {
    finishAndLog("Message EMPTY");
    exit;
}

// Log the incoming message
$logOutput .= $message . "\r\n";

// Separate the message
$parts        = separateMessage($message);
$uid = (int)($parts['uid'] ?? 0);
$type         = $parts['msgtype'] ?? '';
$dataString   = $parts['data'] ?? '';
$checksumMsg  = $parts['checksum'] ?? '';
$messcheck    = substr($message, 0, -3);

// Calculate & verify checksum
$calcChecksum = checksum($messcheck);
if ($checksumMsg !== $calcChecksum) {
    finishAndLog("Wrong Checksum");
    exit;
}

// Handle by message type
$responseMsg = match ($type) {
    'DR' => handleDR($uid, $dataString),
    default => "Wrong Type",
};

// We’re done – output final response & log
finishAndLog($responseMsg);
exit;


//----------------------------------------------------
// FUNCTIONS
//----------------------------------------------------

/**
 * Process the 'DR' (Dip Reading) message.
 */
function handleDR(int $uid, string $dataString): string
{
    global $conn;

    // Timezone offset for the given uid
    $adjustedDateTime = Timezone($uid);
    $timezoneOffset   = $adjustedDateTime['offset'] ?? '+0:00';

    // Attempt to parse JSON data
    $dataArray = json_decode($dataString);
    if (!is_array($dataArray)) {
        return "Invalid JSON in data";
    }

    foreach ($dataArray as $tank) {
        // Basic fields
        $tankId       = $tank->tank_id ?? null;
        $inventory    = $tank->inventory ?? null;
        if (!$tankId || !$inventory) {
            continue; // skip malformed entries
        }
        
        // Extract inventory fields
        $volume        = $inventory->volume        ?? 0;
        $ullage        = $inventory->ullage        ?? 0;
        $temperature   = $inventory->temperature   ?? 0;
        $tcVolume      = $inventory->tcVolume      ?? 0;
        $volumeHeight  = $inventory->volumeHeight  ?? 0;
        $waterVolume   = $inventory->waterVolume   ?? 0;
        $waterHeight   = $inventory->waterHeight   ?? 0;
        $datecs        = $inventory->date          ?? '';
        $timecs        = $inventory->time          ?? '';

        // Combine date & time, then adjust by console's timezone offset
        $datetimeString = $datecs . ' ' . $timecs;
        $datetime       = new DateTime($datetimeString);
        applyTimezoneOffset($datetime, $timezoneOffset);

        // Separate “console” vs “UTC0” times
        $dateconsole = $datetime->format('Y-m-d');
        $timeconsole = $datetime->format('H:i:s');

        // Retrieve tank & site data
        $stmt = $conn->prepare("
            SELECT 
                capacity,
                current_volume, 
                ullage, 
                dipr_date, 
                dipr_time, 
                alert_type, 
                level_alert, 
                alert_flag, 
                site_country, 
                site_city, 
                ts.Site_id, 
                st.Site_name,
                ts.uid 
            FROM Tanks as ts 
            JOIN Sites as st on (st.uid = ts.uid) 
            WHERE ts.uid = ? AND ts.Tank_id = ?
        ");
        $stmt->bind_param("ii", $uid, $tankId);
        $alertType        = null;
        $reorderAlert     = null;
        $alertFlag        = null;
        $site_id          = null;
        $site_name        = null;
        $capacityt        = 0;
        $current_volumet  = 0;

        if ($stmt->execute()) {
            $stmt->bind_result(
                $capacityt,
                $current_volumet,
                $dummy_ullage,
                $dummy_diprDate,
                $dummy_diprTime,
                $alertType,
                $reorderAlert,
                $alertFlag,
                $dummy_country,
                $dummy_city,
                $site_id,
                $site_name,
                $dummy_uid
            );
            $stmt->fetch();
        }
        $stmt->close();

        // Insert into dipread_historic
        $sqlIns = "
            INSERT INTO dipread_historic (
                uid, 
                transaction_date, 
                transaction_date_utc0, 
                transaction_time, 
                transaction_time_utc0, 
                tank_id, 
                current_volume, 
                ullage, 
                temperature, 
                tc_volume, 
                volume_height, 
                water_volume, 
                water_height, 
                site_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmtIns = $conn->prepare($sqlIns);
        $stmtIns->bind_param(
            "issssisssssssi",
            $uid,
            $dateconsole,
            $datecs,
            $timeconsole,
            $timecs,
            $tankId,
            $volume,
            $ullage,
            $temperature,
            $tcVolume,
            $volumeHeight,
            $waterVolume,
            $waterHeight,
            $site_id
        );
        $stmtIns->execute();
        $stmtIns->close();

        // Update Tanks (current volume, percent, etc.)
        $reorderFlagVal = $alertFlag; // default
        if ($capacityt > 0) {
            $currentPercent = round(($volume / $capacityt) * 100, 2);
        } else {
            $currentPercent = 0; // or null – up to you
        }

        // If there's an alert type 1 or 2, apply your existing logic
        if ($alertType == 1) {
            // reorder for "less than" threshold
            if ($volume < $reorderAlert && $alertFlag == 0) {
                $reorderFlagVal = 1;
            } elseif ($volume < $reorderAlert && $alertFlag == 2) {
                // remain 2? or keep it at 2
                $reorderFlagVal = 2;
            } elseif (($volume - $reorderAlert) > ($capacityt * 0.05)) {
                $reorderFlagVal = 0; // reset
            }
        } elseif ($alertType == 2) {
            // reorder for "greater than" threshold
            if ($volume > $reorderAlert && $alertFlag == 0) {
                $reorderFlagVal = 1;
            } elseif ($volume > $reorderAlert && $alertFlag == 2) {
                $reorderFlagVal = 2;
            } elseif (($reorderAlert - $volume) > ($capacityt * 0.05)) {
                $reorderFlagVal = 0; // reset
            }
        }

        $sqlUpd = "
            UPDATE Tanks 
            SET 
                current_volume = ?, 
                ullage         = ?, 
                current_percent= ?, 
                dipr_date      = ?, 
                dipr_date0     = ?, 
                dipr_time      = ?, 
                dipr_time0     = ?, 
                temperature    = ?,  
                tc_volume      = ?, 
                volume_height  = ?, 
                water_volume   = ?, 
                water_height   = ?, 
                alert_flag     = ?
            WHERE uid = ? AND tank_id = ?
        ";
        $stmtUpd = $conn->prepare($sqlUpd);
        $stmtUpd->bind_param(
            "sssssssssssssii",
            $volume,
            $ullage,
            $currentPercent,
            $dateconsole,
            $datecs,
            $timeconsole,
            $timecs,
            $temperature,
            $tcVolume,
            $volumeHeight,
            $waterVolume,
            $waterHeight,
            $reorderFlagVal,
            $uid,
            $tankId
        );
        $stmtUpd->execute();
        $stmtUpd->close();

        // Check for potential delivery
        $cvoldel = $capacityt * 0.02; // your 2% threshold
        $newvol  = $volume - $cvoldel;

        if ($newvol > $current_volumet) {
            // We have a potential delivery
            $delivery = $volume - $current_volumet;

            // Check if there's an existing entry for this date
            $checkQuery = "
                SELECT delivery_id, delivery 
                FROM delivery_historic 
                WHERE uid = ? AND tank_id = ? AND transaction_date = ?
            ";
            $stmtCheck = $conn->prepare($checkQuery);
            $stmtCheck->bind_param("iis", $uid, $tankId, $dateconsole);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows > 0) {
                $stmtCheck->bind_result($delId, $oldDelivery);
                $stmtCheck->fetch();
                $newDelivery = $oldDelivery + $delivery;

                $updateQuery = "
                    UPDATE delivery_historic 
                    SET delivery = ?, current_volume = ?
                    WHERE delivery_id = ?
                ";
                $stmtUpdate = $conn->prepare($updateQuery);
                $stmtUpdate->bind_param("dii", $newDelivery, $volume, $delId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            } else {
                // Insert a new record
                $stmtDipr = $conn->prepare("
                    INSERT INTO delivery_historic 
                    (uid, transaction_date, transaction_date_utc0, transaction_time, transaction_time_utc0, tank_id, current_volume, delivery, site_id, site_name) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtDipr->bind_param(
                    "issssiddis",
                    $uid,
                    $dateconsole,
                    $datecs,
                    $timeconsole,
                    $timecs,
                    $tankId,
                    $volume,
                    $delivery,
                    $site_id,
                    $site_name
                );
                $stmtDipr->execute();
                $stmtDipr->close();
            }
            $stmtCheck->close();
        }

        // If reorder_flag = 1, call your alert function
        if ($reorderFlagVal == 1) {
            fetch_data2(); // from level_alert.php
        }
    }

    return "OK";
}

/**
 * Applies your timezone offset to the given DateTime object.
 */
// function applyTimezoneOffset(DateTime $datetime, string $timezoneOffset): void
// {
//     // e.g. parse +5:30 or -3:00
//     if (preg_match('/([+\-]?)(\d+):(\d+)/', $timezoneOffset, $matches)) {
//         $sign   = $matches[1]; // +, -, or empty
//         $hours  = (int)$matches[2];
//         $minutes= (int)$matches[3];

//         // Build interval: e.g. 'PT5H30M'
//         $intervalSpec = "PT{$hours}H{$minutes}M";
//         $interval    = new DateInterval($intervalSpec);

//         // Adjust DateTime
//         if ($sign === '-') {
//             $datetime->sub($interval);
//         } else {
//             $datetime->add($interval);
//         }
//     }
// }
function applyTimezoneOffset(DateTime $datetime, string $timezoneOffset): void
{
    // CASE A: traditional "+05:45"
    if (preg_match('/^([+\-]?)(\d{1,2}):(\d{2})$/', $timezoneOffset, $m)) {
        $sign    = $m[1] === '-' ? -1 : 1;
        $hours   = (int)$m[2];
        $minutes = (int)$m[3];
    }
    // CASE B: decimal "+5.75"   (or "-3.5", "+10")
    elseif (preg_match('/^([+\-]?)(\d+(?:\.\d+)?)$/', $timezoneOffset, $m)) {
        $sign      = $m[1] === '-' ? -1 : 1;
        $floatHrs  = (float)$m[2];
        $hours     = (int)floor($floatHrs);
        $minutes   = (int)round(($floatHrs - $hours) * 60); // 0‑59
    }
    // Bad format → bail
    else {
        error_log("Bad offset string: $timezoneOffset");
        return;
    }

    // Build and apply the interval
    $interval = new DateInterval(sprintf('PT%dH%dM', $hours, $minutes));
    $sign === -1 ? $datetime->sub($interval) : $datetime->add($interval);
}

/**
 * Append final response to logs, output it, and close DB connection.
 */
function finishAndLog(string $resp): void
{
    global $uid, $type, $conn, $logFilePath, $logOutput;

    // Build your standard JSON response
    $data       = ["response" => $resp];
    $final      = response($data, $uid, $type); // from cs_msg.php
    $logOutput .= $final . "\r\n";

    // Write logs, close DB
    file_put_contents($logFilePath, $logOutput, FILE_APPEND);
    $conn->close();

    // Output final
    // (We no longer echo outside; `response()` already does an echo)
}
