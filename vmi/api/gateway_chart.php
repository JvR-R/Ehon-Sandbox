<?php
require_once __DIR__ . '/../db/pdo_boot.php';
require_once __DIR__ . '/../db/log.php'; 
header('Content-Type: application/json');

$uid  = (int)($_GET['uid']      ?? 0);
$tank = (int)($_GET['tank_id']  ?? 0);
if (!$uid || !$tank) { http_response_code(400); exit; }

/* ---------- 1. min & max per day (one query) */
$sql = "
  SELECT transaction_date AS d,
         MIN(current_volume) AS min_v,
         MAX(current_volume) AS max_v
  FROM   dipread_historic
  WHERE  uid=? AND tank_id=?
  GROUP  BY transaction_date
  ORDER  BY transaction_date DESC
  LIMIT  30";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid, $tank]);

$minVol = $maxVol = [];
foreach ($stmt as $row) {
    $minVol[] = ['d'=>$row['d'], 'v'=>$row['min_v']];
    $maxVol[] = ['d'=>$row['d'], 'v'=>$row['max_v']];
}

/* ---------- 2. deliveries unchanged */
$q2 = $pdo->prepare("
  SELECT transaction_date AS d,
         SUM(delivery)    AS delivery_sum
  FROM   delivery_historic
  WHERE  uid=? AND tank_id=?
  GROUP  BY transaction_date
  ORDER  BY transaction_date DESC
  LIMIT  30");
$q2->execute([$uid, $tank]);
$del = $q2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'minVolumeData' => $minVol,
  'maxVolumeData' => $maxVol,
  'deliveryData'  => $del
]);
