<?php
include('../db/dbh2.php');
include('../db/log.php'); 

// Set JSON header first to prevent any output before JSON
header('Content-Type: application/json; charset=UTF-8');

// $sitename = $_GET['sitename'];
$tank_id = (int)($_GET['tank_no'] ?? 0);
// $companyId = $_GET['companyId'];
// $client_name = $_GET['company_name'];
$uid = (int)($_GET['uid'] ?? 0);
$__prof = [];

//****************************AVG Transactions************************************** */
   $sql = "SELECT
    SUM(ct.dispensed_volume) / COUNT(DISTINCT ct.transaction_date) AS vol,
    st.site_name AS site_name
    FROM client_transaction ct
    JOIN Sites as st on ct.uid = st.uid
    WHERE ct.uid = $uid AND ct.tank_id = $tank_id";

    $__t0 = microtime(true);
    $result = $conn->query($sql);
    $__prof['avg_txn_sec'] = round(microtime(true) - $__t0, 4);

    $response = array();

    $chartData = array();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['estimatedDays'] = $row['vol'];
        $response['site_name'] = $row['site_name'];
    }


//**************************Basic INFO************************************* */
    $sql3 = "SELECT * FROM Sites as st
    JOIN Tanks as ts on (st.uid, st.Site_id) = (ts.uid, ts.Site_id)
    JOIN alarms_config as ac on (ac.uid, ac.tank_id) = (ts.uid, ts.tank_id)
    WHERE ts.uid = $uid AND ts.tank_id = $tank_id
    LIMIT 1";
    $__t0 = microtime(true);
    $result3 = $conn->query($sql3);
    $__prof['basic_info_sec'] = round(microtime(true) - $__t0, 4);
    if ($result3->num_rows > 0) {
        $row = $result3->fetch_assoc();
        // $response['stat'] = $row['stat'];piusi_serialeh
        $response['mail'] = $row['Email'];
        $response['phone'] = $row['phone'];
        $response['volal'] = $row['level_alert'];
        $response['volal_type'] = $row['alert_type'];
        $response['fms_id'] = $row['fms_id'];
        $response['fms_uart'] = $row['fms_uart'];
        $response['fms_type'] = $row['fms_type'];
        $response['tank_gauge_id'] = $row['tank_gauge_id'];
        $response['tank_gauge_uart'] = $row['tank_gauge_uart'];
        $response['tank_gauge_type'] = $row['tank_gauge_type'];
        $response['product_id'] = $row['product_id'];
        $response['chart_id'] = $row['chart_id'];
        $response['tc_volume'] = $row['tc_volume'];
        $response['high_alarmr'] = $row['high_alarm'];
        $response['low_alarmr'] = $row['low_alarm'];
        $response['crithigh_alarmr'] = $row['crithigh_alarm'];
        $response['critlow_alarmr'] = $row['critlow_alarm'];
        $response['current_volume'] = $row['current_volume'];
        $response['capacity'] = $row['capacity'];
        $response['tank_gauge_offset'] = $row['offset_tank'];
        $response["relay_uart"] = $row["relay_uart"];
        $response["relay_type"] = $row["relay_type"];
        $response["relay1"] = $row["relay1"];
        $response["relay2"] = $row["relay2"];
        $response["relay3"] = $row["relay3"];
        $response["relay4"] = $row["relay4"];
    }

//***************************DipReading Chart************************************* */
    $sql2 = "SELECT transaction_date,
                    MIN(current_volume) AS min_volume,
                    MAX(current_volume) AS max_volume
    FROM dipread_historic
    WHERE uid = $uid AND tank_id = $tank_id
      AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    GROUP BY transaction_date
    ORDER BY transaction_date DESC";

    $__t0 = microtime(true);
    $result2 = $conn->query($sql2);
    $__prof['avg_vol_sec'] = round(microtime(true) - $__t0, 4);
    if ($result2 && $result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            // Back-compat payload (legacy name kept):
            $chartData['averageVolumeData'][] = array(
                "transaction_date" => $row['transaction_date'],
                "average_volume"    => $row['min_volume'],
            );
            // New gateway-like payloads (preferred):
            $chartData['minVolumeData'][] = array(
                "d" => $row['transaction_date'],
                "v" => $row['min_volume'],
            );
            $chartData['maxVolumeData'][] = array(
                "d" => $row['transaction_date'],
                "v" => $row['max_volume'],
            );
        }
    }
 
//***********************DELIVERIES********************************* */
    $sqldel = "SELECT transaction_date as tddel, SUM(delivery) AS delivery_sum
    FROM delivery_historic
    WHERE uid = $uid AND tank_id = $tank_id
      AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    GROUP BY transaction_date
    ORDER BY transaction_date DESC";

    $__t0 = microtime(true);
    $resultdel = $conn->query($sqldel);
    $__prof['deliveries_sec'] = round(microtime(true) - $__t0, 4);

    if ($resultdel && $resultdel->num_rows > 0) {
        while ($row = $resultdel->fetch_assoc()) {
            $chartData['deliveryData'][] = array(
                "transaction_datedel" => $row['tddel'],
                "delivery_sum" => $row['delivery_sum'],
            );
        }
    }


//***************************TC Volume Chart************************************* */

    $sqltc = "SELECT transaction_date, MIN(tc_volume) AS tc_volume
    FROM dipread_historic
    WHERE uid = $uid AND tank_id = $tank_id
      AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    GROUP BY transaction_date
    ORDER BY transaction_date DESC";

    $__t0 = microtime(true);
    $resulttc = $conn->query($sqltc);
    $__prof['tc_sec'] = round(microtime(true) - $__t0, 4);
    if ($resulttc && $resulttc->num_rows > 0) {
        while ($row = $resulttc->fetch_assoc()) {
            $chartData['averagetcData'][] = array(
                "transaction_date" => $row['transaction_date'],
                "tc_volume" => $row['tc_volume'],
            );
        }
    }

    $response['response2'] = $chartData;


//***************************Last Transactions (for MCS)************* */
    $lastTxSql = "SELECT
        transaction_date AS date,
        transaction_time AS time,
        ROUND(dispensed_volume, 2) AS volume
      FROM client_transaction
      WHERE uid = $uid AND tank_id = $tank_id
      ORDER BY transaction_date DESC, transaction_time DESC
      LIMIT 10";
    $__t0 = microtime(true);
    $resultTx = $conn->query($lastTxSql);
    $__prof['last_tx_sec'] = round(microtime(true) - $__t0, 4);
    $lastTx = [];
    if ($resultTx && $resultTx->num_rows > 0) {
        while ($row = $resultTx->fetch_assoc()) {
            $lastTx[] = [
                'date'   => $row['date'],
                'time'   => $row['time'],
                'volume' => (float)$row['volume'],
            ];
        }
    }
    $response['last_tx'] = $lastTx;

//******************************************Device Type ******************************* */
    $devices=array();
    $sqldev = "SELECT DISTINCT
        tt.tank_gauge_id AS device_id,
        tt.device_name,
        cs.last_conndate AS lastconn,
        cs.last_conntime AS lastconn_time,
        CASE WHEN tt.tank_gauge_id IN (204,205) THEN cs.UART1_ID END AS UART1_ID
      FROM console cs
      LEFT JOIN tankgauge_type tt
        ON tt.tank_gauge_id IN (cs.uart1, cs.uart3, cs.uart5, cs.uart6, 0)
      WHERE cs.uid = $uid";
    $__t0 = microtime(true);
    $resultdev = $conn->query($sqldev);
    $__prof['devices_sec'] = round(microtime(true) - $__t0, 4);
    if ($resultdev && $resultdev->num_rows > 0) {
        while ($row = $resultdev->fetch_assoc()) {
            $device = array(
                "device_id" => $row['device_id'],
                "device_name" => $row['device_name'],
            );
            if ($row['device_id'] == 205 || $row['device_id'] == 204) {
                if (isset($row['UART1_ID'])) { $device['UART1_ID'] = $row['UART1_ID']; }
            }
            $devices['devices'][] = $device;
            if (!isset($response['lastconn']) && isset($row['lastconn'])) {
                $response['lastconn'] = $row['lastconn'];
            }
            if (!isset($response['lastconn_time']) && isset($row['lastconn_time'])) {
                $response['lastconn_time'] = $row['lastconn_time'];
            }
        }        
    }
    $response['responsedev'] = $devices;
//************************************************************************* */


$conn->close();
// Return the response as JSON
echo json_encode($response);
exit();
?>