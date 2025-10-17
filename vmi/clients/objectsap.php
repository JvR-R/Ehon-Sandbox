<?php
include('../db/dbh2.php');
include('../db/log.php'); 

    // $sitename = $_GET['sitename'];
    $tank_id = $_GET['tank_no'];
    // $companyId = $_GET['companyId'];
    // $client_name = $_GET['company_name'];
    $uid = $_GET['uid'];
    $cs_type = $_GET['cs_type'];
    $tank_name = $_GET['tank_name'];
    $sitename = $_GET['sitename'];
    $site_id = $_GET['site_id'];

//****************************AVG Transactions************************************** */
   $sql = "SELECT
    SUM(ct.dispensed_volume) / COUNT(DISTINCT ct.transaction_date) AS vol,
    st.site_name AS site_name
    FROM
    client_transaction ct JOIN Sites as st on ct.uid = st.uid 
    JOIN Clients as clc on (st.client_id, st.Site_id) = (clc.client_id, ct.site_id) 
    WHERE ct.uid = $uid and ct.tank_id = $tank_id and ct.site_id = $site_id;";

    $result = $conn->query($sql);

    $response = array();

    $chartData = array();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['estimatedDays'] = $row['vol'];
        $response['site_name'] = $row['site_name'];
    }


//**************************Basic INFO************************************* */
    $sql3 = "SELECT * FROM Sites as st JOIN Tanks as ts on (st.uid, st.Site_id) = (ts.uid, ts.Site_id) JOIN alarms_config as ac on (ac.uid, ac.tank_id, ac.Site_id) = (ts.uid, ts.tank_id, ts.Site_id) 
    WHERE ts.uid = $uid and ts.tank_id = $tank_id and ts.Site_id = $site_id;";
    $result3 = $conn->query($sql3);
    if ($result3->num_rows > 0) {
        $row = $result3->fetch_assoc();
        // $response['stat'] = $row['stat'];piusi_serialeh
        $response['mail'] = $row['Email'];
        $response['phone'] = $row['phone'];
        $response['volal'] = $row['level_alert'];
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
        $response['capacity'] = $row['capacity'];
        $response['current_volume'] = $row['current_volume'];
    }

//***************************DipReading Chart************************************* */
    $sql2 = "SELECT cs.uid, dth.transaction_date, dth.tank_id, MIN(dth.current_volume) AS average_volume
    FROM Sites AS cs
    JOIN Clients clc ON clc.client_id = cs.client_id
    JOIN dipread_historic dth ON (cs.uid) = (dth.uid)
    JOIN Tanks as ts ON (ts.uid, ts.tank_id) = (dth.uid, dth.tank_id)
    WHERE ts.tank_id = $tank_id
        AND cs.uid = $uid
        AND ts.Site_id = $site_id
        AND dth.transaction_date IN (
            SELECT DISTINCT(dth.transaction_date)
            FROM Tanks AS ts
            INNER JOIN Clients clc ON clc.client_id = ts.client_id
            INNER JOIN dipread_historic dth ON ts.uid = dth.uid
            WHERE ts.tank_id = $tank_id
                AND ts.Site_id = $site_id
                AND ts.uid = $uid
        )
    GROUP BY cs.uid, dth.transaction_date, dth.tank_id;";

    $result2 = $conn->query($sql2);
    if ($result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            $chartData['averageVolumeData'][] = array(
                "transaction_date" => $row['transaction_date'],
                "average_volume" => $row['average_volume'],
            );
        }
    }
 
//***********************DELIVERIES********************************* */
    $sqldel = "SELECT cs.uid, dth.site_id, dth.transaction_date as tddel, dth.tank_id, sum(dth.delivery) AS delivery_sum
    FROM Sites AS cs JOIN Tanks as ts on (cs.uid, cs.client_id,cs.Site_id) = (ts.uid, ts.client_id, ts.Site_id)
    INNER JOIN Clients clc ON clc.client_id = cs.client_id
    INNER JOIN delivery_historic dth ON (ts.uid, ts.tank_id, ts.Site_id) = (dth.uid, dth.tank_id, dth.site_id)
    WHERE ts.tank_id = $tank_id
        AND cs.uid = $uid
        AND ts.Site_id = $site_id
        AND dth.transaction_date IN (
            SELECT DISTINCT(dth.transaction_date)
            FROM Tanks AS ts
            INNER JOIN Clients clc ON clc.client_id = ts.client_id
            INNER JOIN delivery_historic dth ON (ts.uid, ts.Site_id) = (dth.uid, dth.site_id)
            WHERE ts.tank_id = $tank_id
                AND ts.uid = $uid
                AND ts.Site_id = $site_id
        )
    GROUP BY cs.uid, dth.transaction_date, dth.tank_id;";

    $resultdel = $conn->query($sqldel);

    if ($resultdel->num_rows > 0) {
        while ($row = $resultdel->fetch_assoc()) {
            $chartData['deliveryData'][] = array(
                "transaction_datedel" => $row['tddel'],
                "delivery_sum" => $row['delivery_sum'],
            );
        }
    }


// //***************************TC Volume Chart************************************* */

//     $sqltc = "SELECT cs.uid, dth.transaction_date, dth.tank_id, MIN(dth.tc_volume) AS tc_volume
//     FROM Sites AS cs
//     JOIN Clients clc ON clc.client_id = cs.client_id
//     JOIN dipread_historic dth ON (cs.uid) = (dth.uid)
//     JOIN Tanks as ts ON (ts.uid, ts.tank_id) = (dth.uid, dth.tank_id)
//     WHERE ts.tank_id = $tank_id
//         AND cs.uid = $uid
//         AND dth.transaction_date IN (
//             SELECT DISTINCT(dth.transaction_date)
//             FROM Tanks AS ts
//             INNER JOIN Clients clc ON clc.client_id = ts.client_id
//             INNER JOIN dipread_historic dth ON ts.uid = dth.uid
//             WHERE ts.tank_id = $tank_id
//                 AND ts.uid = $uid
//         )
//     GROUP BY cs.uid, dth.transaction_date, dth.tank_id;";

//     $resulttc = $conn->query($sqltc);
//     if ($resulttc->num_rows > 0) {
//         while ($row = $resulttc->fetch_assoc()) {
//             $chartData['averagetcData'][] = array(
//                 "transaction_date" => $row['transaction_date'],
//                 "tc_volume" => $row['tc_volume'],
//             );
//         }
//     }

    $response['response2'] = $chartData;





// //*****************************TEMPERATURE CHART***************************** */
//     $tempchartData = array();
//     $sqltemp = "SELECT cs.uid, DATE(dth.transaction_date) as date, 
//     DATE_FORMAT(dth.transaction_date, '%Y-%m-%d %H:00:00') as rounded_hour, ts.tank_id, dth.temperature AS average_temperature 
//     FROM Sites AS cs JOIN Clients clc ON clc.client_id = cs.client_id JOIN dipread_historic dth ON cs.uid = dth.uid 
//     JOIN Tanks as ts ON (ts.uid, ts.tank_id) = (dth.uid, dth.tank_id) WHERE ts.tank_id = $tank_id AND cs.uid = $uid 
//     AND dth.transaction_date >= NOW() - INTERVAL 14 DAY 
//     GROUP BY cs.uid, DATE(dth.transaction_date), HOUR(dth.transaction_date) DIV 6, ts.tank_id
//     ORDER BY 
//     dth.transaction_date;";

//     $resulttemp = $conn->query($sqltemp);
//     if ($resulttemp->num_rows > 0) {
//         while ($row = $resulttemp->fetch_assoc()) {
//             $tempchartData['averagetempData'][] = array(
//                 "transaction_date" => $row['date'],
//                 "average_temperature" => $row['average_temperature'],
//             );
//         }
//     }
//     $response['response3'] = $tempchartData;

// //******************************************Device Type ******************************* */
//     $devices=array();
//     $sqldev="SELECT * FROM tankgauge_type";
//     $resultdev = $conn->query($sqldev);
//     if ($resultdev->num_rows > 0) {
//         while ($row = $resultdev->fetch_assoc()) {
//             $devices['devices'][] = array(
//                 "device_id" => $row['tank_gauge_id'],
//                 "device_name" => $row['device_name'],
//             );
//         }
//     }
//     $response['responsedev'] = $devices;
//************************************************************************* */


$conn->close();
// Return the response as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>