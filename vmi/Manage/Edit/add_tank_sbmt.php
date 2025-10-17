<?php
include('../../db/dbh2.php');
include('../../db/log.php');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$site_id = $input['site_id'];
$tank_number = $input['tank_number'];
$tank_name = $input['tank_name'];
$product_id = $input['product_name'];
$checkbox_tg = $input['checkbox_tg'] ? 1 : 0;
$capacity = $input['capacity'] ? $input['capacity'] : 0;
$reorder_level = $input['reorder_level'] ? $input['reorder_level'] : NULL;
$checkbox_piusi = $input['checkbox_piusi'] ? 1 : 0;
$piusi_serial = $input['piusi_serial'] ? $input['piusi_serial'] : NULL;

if (empty($site_id) || empty($tank_number) || empty($tank_name) || empty($product_id) || $product_id == '000') {
    echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled and a product must be selected.']);
    exit();
}

$sqlsel = "SELECT uid, client_id FROM Sites WHERE site_id = ?";
$stmtsel = $conn->prepare($sqlsel);
if (!$stmtsel) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare statement failed: ' . $conn->error]);
    exit();
}
$stmtsel->bind_param("s", $site_id);
if ($stmtsel->execute()) {
    $stmtsel->bind_result($uid, $client_id);
    if ($stmtsel->fetch()) {
        $stmtsel->close();

        $sql = "INSERT INTO Tanks (tank_id, uid, client_id, Site_id, Tank_name, product_id, capacity, level_alert, tank_gauge_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare statement failed: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("iiiissiii", $tank_number, $uid, $client_id, $site_id, $tank_name, $product_id, $capacity, $reorder_level, $tank_number);

        if ($stmt->execute()) {
            $stmt->close();
            tanks_crcdata($uid);
            $low = 0;
            $alarmins = $conn->prepare("INSERT INTO alarms_config (client_id, uid, Site_id, tank_id, high_alarm, low_alarm, crithigh_alarm, critlow_alarm, alarm_enable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);");
            if (!$alarmins) {
                echo json_encode(['status' => 'error', 'message' => 'Prepare statement failed: ' . $conn->error]);
                exit();
            }
            $alarmins->bind_param("iiiiiiiii", $client_id, $uid, $site_id, $tank_number, $capacity, $low, $capacity, $low, $low);
            if ($alarmins->execute()) {
                $alarmins->close();
                $upd = $conn->prepare("UPDATE console set cfg_flag = 1 WHERE uid = ?");
                if (!$upd) {
                    echo json_encode(['status' => 'error', 'message' => 'Prepare statement failed: ' . $conn->error]);
                    exit();
                }
                $upd->bind_param("i", $uid);
                if ($upd->execute()) {
                    $upd->close();
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Update console failed: ' . $upd->error]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Insert alarms_config failed: ' . $alarmins->error]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert tank information failed: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No site found with the provided site ID.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to execute statement: ' . $stmtsel->error]);
}


function tanks_crcdata($uid) {
    global $conn; // Ensure $conn is available inside the function

    $sql = "SELECT tank_id, capacity, product_name FROM Tanks ts JOIN products ps ON ps.product_id = ts.product_id WHERE uid = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return;
    }
    $result = $stmt->get_result();

    $dataStrings = [];
    while ($row = $result->fetch_assoc()) {
        // Explicitly extract fields in the desired order
        $fields = [
            $row['tank_id'],
            $row['capacity'],
            $row['product_name']
        ];
        // Convert NULL values to empty strings
        foreach ($fields as &$field) {
            if (is_null($field)) {
                $field = 0;
            }
        }
        // Form data string for each row
        $dataString = implode(',', $fields);
        // Add "\n" at the end
        $dataString .= "\n";
        // Add to array
        $dataStrings[] = $dataString;
    }
    $stmt->close();

    // Concatenate data strings
    $concatenatedData = implode('', $dataStrings);

    // Calculate the CRC over $concatenatedData
    $crc = calculate_crc32_hex($concatenatedData);

    // // // Output the CRC
    // echo "Calculated CRC: 0x$crc\n";

    $crc = "0x$crc";
    $update = "UPDATE console SET crc_tank= ? WHERE uid = ?";
    $updatequery = $conn->prepare($update);
    if ($updatequery === false) {
        error_log("Database prepare error: " . $conn->error);
        return;
    }
    $updatequery->bind_param("si", $crc, $uid);
    if (!$updatequery->execute()) {
        error_log("Database execute error: " . $updatequery->error);
    }
    $updatequery->close();
}

$conn->close();


?>
