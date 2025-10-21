<?php
// /vmi/api/gateway_config.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../db/pdo_boot.php'; // -> $pdo (ERRMODE_EXCEPTION)

/** ---------- helpers ---------- */
function resolve_site_id(PDO $pdo, int $uid, ?int $given = 0): int {
  if ($given) return (int)$given;
  $st = $pdo->prepare("SELECT Site_id FROM tanks WHERE uid=? ORDER BY Site_id LIMIT 1");
  $st->execute([$uid]);
  $sid = $st->fetchColumn();
  return $sid !== false ? (int)$sid : 0;
}

function get_ports(PDO $pdo, int $uid): array {
  $row = ['mindex_0'=>0,'fmsindex_0'=>0,'mindex_1'=>0,'fmsindex_1'=>0];
  $st = $pdo->prepare("SELECT UART5 AS mindex_0, UART5_fms AS fmsindex_0, UART3 AS mindex_1, UART3_fms AS fmsindex_1
                       FROM console WHERE uid=? LIMIT 1");
  $st->execute([$uid]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) $row = array_map('intval', $r);
  return $row;
}

/** ---------- handler ---------- */
try {
  $uid     = (int)($_GET['uid']     ?? 0);
  $site_id = (int)($_GET['site_id'] ?? 0);
  if ($uid <= 0) throw new RuntimeException('uid is required');

  $site_id = resolve_site_id($pdo, $uid, $site_id);

  // Ports (device-level from console)
  $ports = get_ports($pdo, $uid);

  // Fetch site-level info (email, alert settings)
  $siteInfo = ['mail' => '', 'volal' => 0, 'volal_type' => 0];
  try {
    $stSite = $pdo->prepare("SELECT Email, level_alert, alert_type FROM Sites WHERE uid = :uid AND Site_id = :site_id LIMIT 1");
    $stSite->execute([':uid' => $uid, ':site_id' => $site_id]);
    if ($siteRow = $stSite->fetch(PDO::FETCH_ASSOC)) {
      $siteInfo = [
        'mail' => $siteRow['Email'] ?? '',
        'volal' => isset($siteRow['level_alert']) ? (int)$siteRow['level_alert'] : 0,
        'volal_type' => isset($siteRow['alert_type']) ? (int)$siteRow['alert_type'] : 0,
      ];
    }
  } catch (Throwable $e) { /* continue with defaults */ }

  // Optional product names
  $productMap = [];
  try {
    $q = $pdo->query("SELECT product_id, product_name FROM products");
    foreach ($q ?: [] as $r) $productMap[(int)$r['product_id']] = (string)$r['product_name'];
  } catch (Throwable $e) { /* table may not exist */ }

  // Pull tank + geometry + alarms for 1..4 (be robust to missing `enabled`)
  $params = [':uid'=>$uid, ':site_id'=>$site_id];

  $sql = "
    SELECT
      t.tank_id,
      t.Tank_name,
      t.capacity,
      t.current_volume,
      IFNULL(t.product_id,0)          AS product_id,
      IFNULL(t.enabled,1)             AS enabled,       -- default enabled if column missing
      IFNULL(t.chart_id,0)    AS chart_id,
      g.shape,
      g.height,
      g.width,
      g.depth,
      IFNULL(g.raw_bias_counts,0)     AS raw_bias_counts,
      a.high_alarm,
      a.low_alarm,
      a.crithigh_alarm,
      a.critlow_alarm,
      IFNULL(a.alarm_enable,0)        AS alarm_enable
    FROM tanks t
    LEFT JOIN config_ehon_gateway g
      ON g.uid = t.uid AND g.tank_id = t.tank_id
    LEFT JOIN alarms_config a
      ON a.uid = t.uid AND a.Site_id = t.Site_id AND a.tank_id = t.tank_id
    WHERE t.uid = :uid AND t.Site_id = :site_id AND t.tank_id BETWEEN 1 AND 4
    ORDER BY t.tank_id
  ";

  // If `enabled` column truly doesn't exist, the query above still works because IFNULL() will be resolved at runtime.
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Index by id
  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;

  // Calculate average daily consumption for each tank
  $avgDailyConsumption = [];
  for ($i = 1; $i <= 4; $i++) {
    $stAvg = $pdo->prepare("
      SELECT SUM(ct.dispensed_volume) / COUNT(DISTINCT ct.transaction_date) AS avg_daily
      FROM client_transaction ct
      WHERE ct.uid = :uid AND ct.tank_id = :tank_id
    ");
    $stAvg->execute([':uid' => $uid, ':tank_id' => $i]);
    $avgRow = $stAvg->fetch(PDO::FETCH_ASSOC);
    $avgDailyConsumption[$i] = isset($avgRow['avg_daily']) && $avgRow['avg_daily'] !== null 
      ? (float)$avgRow['avg_daily'] 
      : null;
  }

  // Shape output the UI can consume
  $tanks = [];
  for ($i = 1; $i <= 4; $i++) {
    $r = $byId[$i] ?? [];
    $product_id = isset($r['product_id']) ? (int)$r['product_id'] : 0;
    $product    = $productMap[$product_id] ?? 'UNKNOWN';
    $current_vol = isset($r['current_volume']) ? (float)$r['current_volume'] : 0;

    $tanks[] = [
      'id'        => $i,
      'name'      => (isset($r['Tank_name']) && trim($r['Tank_name']) !== '') ? $r['Tank_name'] : ('TANK'.$i),
      'capacity'  => isset($r['capacity']) ? (float)$r['capacity'] : 0,
      'current_volume' => $current_vol,
      'estimatedDays'  => $avgDailyConsumption[$i],
      'product_id'=> $product_id,
      'product'   => $product,
      'enabled'   => !empty($r) ? ((int)$r['enabled'] === 1) : false,
      'chart_id'   => isset($r['chart_id']) ? (int)$r['chart_id'] : 0,
      'geometry'  => [
        'shape'   => isset($r['shape']) ? (int)$r['shape'] : 2,  // 0=Vert,1=Horiz,2=Rect default
        'height'  => isset($r['height']) ? (float)$r['height'] : 0,
        'width'   => isset($r['width'])  ? (float)$r['width']  : 0,
        'depth'   => isset($r['depth'])  ? (float)$r['depth']  : 0,
        'offset'  => isset($r['raw_bias_counts']) ? (float)$r['raw_bias_counts'] : 0,
      ],
      'alarms'    => [
        'high_high' => isset($r['crithigh_alarm']) ? (int)$r['crithigh_alarm'] : 0,
        'high'      => isset($r['high_alarm'])     ? (int)$r['high_alarm']     : 0,
        'low'       => isset($r['low_alarm'])      ? (int)$r['low_alarm']      : 0,
        'low_low'   => isset($r['critlow_alarm'])  ? (int)$r['critlow_alarm']  : 0,
        'enabled'   => isset($r['alarm_enable'])   ? (int)$r['alarm_enable']   : 0,
      ],
    ];
  }

  // ---------- NEW: last 10 transactions from client_transaction ----------
  $last_tx = [];
  $tank_device_id = isset($_GET['tank_device_id']) ? (int)$_GET['tank_device_id'] : 0;

  if ($tank_device_id > 0) {
      // Resolve numeric tank_id used by transactions
      $st = $pdo->prepare("
          SELECT t.tank_id
          FROM tank_device td
          JOIN tanks t ON t.tank_uid = td.tank_uid
          WHERE td.id = ? AND t.uid = ?
          LIMIT 1
      ");
      $st->execute([$tank_device_id, $uid]);
      $tank_id_for_tx = (int)($st->fetchColumn() ?: 0);

      if ($tank_id_for_tx > 0) {
          $q = $pdo->prepare("
              SELECT
                  transaction_date AS date,
                  transaction_time AS time,
                  ROUND(dispensed_volume, 2) AS volume
              FROM client_transaction
              WHERE uid = ? AND tank_id = ?
              ORDER BY transaction_date DESC, transaction_time DESC
              LIMIT 10
          ");
          $q->execute([$uid, $tank_id_for_tx]);
          $last_tx = $q->fetchAll(PDO::FETCH_ASSOC);
      }
  }

  // ── Strapping charts for this client ──────────────────────────────
  $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
  $schart = [];
  if ($client_id > 0) {
      if ($client_id === 15100) {
          // Owner: pull all strapping charts across all clients
          $st = $pdo->query("
              SELECT chart_id, chart_name
              FROM strapping_chart
              ORDER BY chart_name
          ");
          $schart = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
      } else {
          // Non-owner: charts from client_id 15100 (shared) + their own client
          $st = $pdo->prepare("
              SELECT chart_id, chart_name
              FROM strapping_chart
              WHERE client_id IN (?, 15100)
              ORDER BY chart_name
          ");
          $st->execute([$client_id]);
          $schart = $st->fetchAll(PDO::FETCH_ASSOC);  // [{chart_id, chart_name}]
      }
  }



  echo json_encode([
    'ok'      => true,
    'uid'     => $uid,
    'site_id' => $site_id,
    'ports'   => $ports,          // { mindex_0, fmsindex_0, mindex_1, fmsindex_1 }
    'tanks'   => $tanks,           // 4-slot array with basics + geometry + alarms
    'last_tx' => $last_tx,
    'schart'  => $schart,
    'mail'    => $siteInfo['mail'],
    'volal'   => $siteInfo['volal'],
    'volal_type' => $siteInfo['volal_type'],
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()]);
}
