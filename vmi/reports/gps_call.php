<?php
/**
 *  /vmi/reports/gps_call.php
 *  Builds JSON for Leaflet: coordinates, per-tank level, alert colour.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/db/pdo_boot.php';   // gives $pdo
require_once dirname(__DIR__) . '/db/log.php';        // starts session + ACL

header('Content-Type: application/json; charset=utf-8');

$cid      = (int)($_SESSION['companyId'] ?? 0);
$isGlobal = ($cid === 15100);

/* ──────────────────────────────────────────────────────────────
 *  Query: coords + tanks + alarm thresholds
 * ──────────────────────────────────────────────────────────── */
$sql = $isGlobal ? <<<SQL
SELECT ca.uid,
       cs.console_coordinates,
       st.Site_name,
       t.tank_id,
       t.current_percent,
       t.current_volume,
       ac.crithigh_alarm,
       ac.high_alarm,
       ac.low_alarm,
       ac.critlow_alarm
  FROM console              cs
  JOIN Console_Asociation   ca ON ca.uid = cs.uid
  JOIN Sites                st ON st.uid = cs.uid
  JOIN Tanks                t  ON t.uid = cs.uid
  LEFT JOIN alarms_config   ac ON ac.uid = t.uid
                              AND ac.tank_id = t.tank_id
 WHERE cs.console_coordinates <> '' AND t.enabled = 1
SQL
: <<<SQL
SELECT ca.uid,
       cs.console_coordinates,
       st.Site_name,
       t.tank_id,
       t.current_percent,
       t.current_volume,
       ac.crithigh_alarm,
       ac.high_alarm,
       ac.low_alarm,
       ac.critlow_alarm
  FROM console              cs
  JOIN Console_Asociation   ca ON ca.uid = cs.uid
  JOIN Sites                st ON st.uid = cs.uid
  JOIN Tanks                t  ON t.uid = cs.uid
  LEFT JOIN alarms_config   ac ON ac.uid = t.uid
                              AND ac.tank_id = t.tank_id
 WHERE cs.console_coordinates <> ''
   AND (ca.client_id   = ?
     OR ca.reseller_id = ?
     OR ca.dist_id     = ?) AND t.enabled = 1
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute($isGlobal ? [] : [$cid, $cid, $cid]);

/* ──────────────────────────────────────────────────────────────
 *  Aggregate by site, compute alert colour
 * ──────────────────────────────────────────────────────────── */
$sites = [];
// severity ranks to escalate site alert to the worst tank status
$severityRank = [
    'CRITHIGH' => 4,
    'CRITLOW'  => 5,
    'HIGH'     => 3,
    'LOW'      => 3,
    'OK'       => 1,
];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // parse "lat,long"
    $parts = explode(',', $row['console_coordinates']);
    if (count($parts) !== 2) continue;            // bad string → skip
    [$lat, $lng] = array_map('floatval', $parts);
    if (!$lat && !$lng) continue;                 // 0,0 → skip

    $uid = $row['uid'];

    // initialise site record
    if (!isset($sites[$uid])) {
        $sites[$uid] = [
            'lat'   => $lat,
            'lng'   => $lng,
            'name'  => $row['Site_name'],
            'tanks' => [],
            'alert' => 'OK'
        ];
    }

    // tank status vs alarms_config using current_volume thresholds → five levels
    $pct   = (float)$row['current_percent'];
    $vol   = isset($row['current_volume']) ? (float)$row['current_volume'] : null;
    $stat  = 'OK';
    if ($vol !== null && $row['crithigh_alarm'] !== null && $vol >= (float)$row['crithigh_alarm']) {
        $stat = 'CRITHIGH';
    } elseif ($vol !== null && $row['critlow_alarm'] !== null && $vol <= (float)$row['critlow_alarm']) {
        $stat = 'CRITLOW';
    } elseif ($vol !== null && $row['high_alarm'] !== null && $vol >= (float)$row['high_alarm']) {
        $stat = 'HIGH';
    } elseif ($vol !== null && $row['low_alarm'] !== null && $vol <= (float)$row['low_alarm']) {
        $stat = 'LOW';
    }

    // escalate site alert if needed (pick worst severity)
    $currentSeverity  = $severityRank[$stat] ?? 0;
    $existingSeverity = $severityRank[$sites[$uid]['alert']] ?? 0;
    if ($currentSeverity > $existingSeverity) {
        $sites[$uid]['alert'] = $stat;
    }

    $sites[$uid]['tanks'][] = [
        'id'    => (int)$row['tank_id'],
        'level' => $pct
    ];
}

echo json_encode([
    'locations' => array_values($sites),
    'is_global' => $isGlobal
], JSON_THROW_ON_ERROR);
