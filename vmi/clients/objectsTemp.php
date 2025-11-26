<?php
include('../db/dbh2.php');

// Set JSON header first to prevent any output before JSON
header('Content-Type: application/json; charset=UTF-8');

$uid     = (int)($_GET['uid']      ?? 0);
$tank_id = (int)($_GET['tank_no']  ?? 0);

$sqltemp = "
SELECT
        DATE(dth.transaction_date)                        AS tdate,
        DATE_FORMAT(dth.transaction_time,'%H:00')         AS rounded_hour,
        ROUND(MIN(dth.temperature),1)                     AS average_temperature
FROM    dipread_historic dth
WHERE   dth.uid      = $uid
  AND   dth.tank_id  = $tank_id
  AND   dth.temperature IS NOT NULL
GROUP BY tdate, rounded_hour
ORDER BY tdate DESC, rounded_hour ASC
LIMIT 168";

$rows = $conn->query($sqltemp);
if ($rows === false) {
    http_response_code(500);
    echo json_encode(['sqlError' => $conn->error, 'sql' => $sqltemp]);
    exit;
}

$data = ['averagetempData' => []];
while ($row = $rows->fetch_assoc()) {
    $data['averagetempData'][] = [
        'transaction_date'    => $row['tdate'].'T'.$row['rounded_hour'],
        'average_temperature' => $row['average_temperature']
    ];
}

echo json_encode(['response3' => $data], JSON_NUMERIC_CHECK);
exit();
?>
