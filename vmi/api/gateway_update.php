<?php
// /vmi/api/gateway_update.php
header('Content-Type: application/json');

ini_set('display_errors', 0);
// Avoid long float representations in json_encode
@ini_set('serialize_precision', '-1');
@ini_set('precision', '14');
error_reporting(E_ALL);

require __DIR__ . '/../db/pdo_boot.php'; // -> $pdo (ERRMODE_EXCEPTION)

/** ---------- helpers ---------- */
function ensure_dir(string $dir): void {
  if (is_dir($dir)) return;
  if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
    throw new RuntimeException("Failed to create directory: $dir");
  }
}

function atomic_write_json(string $path, array $data): void {
  ensure_dir(dirname($path));
  // no PRETTY_PRINT → single line JSON
  $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;
  $json = json_encode($data, $flags);
  if ($json === false) throw new RuntimeException('Failed to encode JSON for '.$path);

  $tmp = tempnam(dirname($path), 'gw_');
  if ($tmp === false) throw new RuntimeException('Failed to create temp file for '.$path);
  if (file_put_contents($tmp, $json, LOCK_EX) === false) { @unlink($tmp); throw new RuntimeException('Failed to write temp file '.$path); }
  @chmod($tmp, 0664);
  if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Failed to move temp file into place: '.$path); }
}

function write_json_atomic(string $path, $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if ($json === false) { throw new RuntimeException('json_encode failed'); }

    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $fh = fopen($tmp, 'wb');
    if (!$fh) { throw new RuntimeException("open $tmp failed"); }

    if (flock($fh, LOCK_EX)) {
        $w = fwrite($fh, $json);
        if ($w === false || $w < strlen($json)) { throw new RuntimeException('short write'); }
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    if (!rename($tmp, $path)) { throw new RuntimeException("rename($tmp → $path) failed"); }
    @chmod($path, 0664);
    clearstatcache(true, $path);
}

/**
 * Try to resolve a site_id for this uid/tank_id.
 */
function resolve_site_id(PDO $pdo, int $uid, ?int $tank_id, ?int $fallback = 0): int {
  if ($fallback) return (int)$fallback;
  if ($tank_id) {
    $st = $pdo->prepare("SELECT Site_id FROM tanks WHERE uid=? AND tank_id=? LIMIT 1");
    $st->execute([$uid, $tank_id]);
    $sid = $st->fetchColumn();
    if ($sid !== false) return (int)$sid;
  }
  $st = $pdo->prepare("SELECT Site_id FROM tanks WHERE uid=? ORDER BY Site_id LIMIT 1");
  $st->execute([$uid]);
  $sid = $st->fetchColumn();
  return $sid !== false ? (int)$sid : 0;
}

/**
 * Build the 1..4 TANKS snapshot for a gateway and write TANKS.json.
 * Uses: tanks, config_ehon_gateway, alarms_config, products? (optional), strapping_chart? (optional)
 * Robust to missing columns/tables.
 */
function write_tanks_json(PDO $pdo, int $uid, int $site_id): void {
  if ($site_id <= 0) return; // nothing to do

  // Try query with optional 'enabled' column; if it fails, fall back without it.
  $rows = [];
  $params = [':uid'=>$uid, ':site_id'=>$site_id];

  $sqlWithEnabled = "
    SELECT
      t.tank_id,
      t.Tank_name,
      t.capacity,
      IFNULL(t.enabled, 0)            AS enabled,
      IFNULL(t.product_id, 0)         AS product_id,
      IFNULL(t.chart_id, 0)           AS chart_id,
      IFNULL(t.tank_gauge_type, 0)    AS tank_gauge_type,
      g.shape,
      g.height,
      g.width,
      g.depth,  
      IFNULL(g.`offset`, 0)           AS `offset`,
      IFNULL(g.raw_bias_counts, 0)    AS raw_bias_counts,
      a.high_alarm,
      a.low_alarm,
      a.crithigh_alarm,
      a.critlow_alarm
    FROM tanks t
    LEFT JOIN config_ehon_gateway g
      ON g.uid = t.uid AND g.tank_id = t.tank_id
    LEFT JOIN alarms_config a
      ON a.uid = t.uid AND a.Site_id = t.Site_id AND a.tank_id = t.tank_id
    WHERE t.uid = :uid AND t.Site_id = :site_id AND t.tank_id BETWEEN 1 AND 4
    ORDER BY t.tank_id
  ";

  $sqlNoEnabled = "
    SELECT
      t.tank_id,
      t.Tank_name,
      t.capacity,
      1                               AS enabled,   -- default to enabled when column absent
      IFNULL(t.product_id, 0)         AS product_id,
      IFNULL(t.chart_id, 0)           AS chart_id,
      IFNULL(t.tank_gauge_type, 0)    AS tank_gauge_type,
      g.shape,
      g.height,
      g.width,
      g.depth,
      IFNULL(g.`offset`, 0)           AS `offset`,
      IFNULL(g.raw_bias_counts, 0)    AS raw_bias_counts,
      a.high_alarm,
      a.low_alarm,
      a.crithigh_alarm,
      a.critlow_alarm
    FROM tanks t
    LEFT JOIN config_ehon_gateway g
      ON g.uid = t.uid AND g.tank_id = t.tank_id
    LEFT JOIN alarms_config a
      ON a.uid = t.uid AND a.Site_id = t.Site_id AND a.tank_id = t.tank_id
    WHERE t.uid = :uid AND t.Site_id = :site_id AND t.tank_id BETWEEN 1 AND 4
    ORDER BY t.tank_id
  ";

  try {
    $st = $pdo->prepare($sqlWithEnabled);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    // Column 'enabled' may not exist; fall back.
    $st = $pdo->prepare($sqlNoEnabled);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  // Optional product name map
  $pmap = [];
  try {
    $pr = $pdo->query("SELECT product_id, product_name FROM products");
    foreach ($pr ?: [] as $r) $pmap[(int)$r['product_id']] = (string)$r['product_name'];
  } catch (Throwable $e) {
    // products table not available; default to UNKNOWN
  }

  // Optional chart names (if you persist chart_id somewhere)
  $cmap = [];
  try {
    $cr = $pdo->query("SELECT chart_id, chart_name FROM strapping_chart");
    foreach ($cr ?: [] as $r) $cmap[(int)$r['chart_id']] = (string)$r['chart_name'];
  } catch (Throwable $e) {}

  // Index by tank_id
  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;

  $out = [];
  for ($i = 1; $i <= 4; $i++) {
    $r = $byId[$i] ?? [];
    $name    = isset($r['Tank_name']) && trim($r['Tank_name']) !== '' ? $r['Tank_name'] : ('TANK'.$i);
    $cap     = isset($r['capacity']) ? (float)$r['capacity'] : 0.0;
    $shape   = isset($r['shape']) ? (int)$r['shape'] : 2; // default Rect
    $height  = isset($r['height']) ? (float)$r['height'] : 0.0;
    $width   = isset($r['width'])  ? (float)$r['width']  : 0.0;
    $depth   = isset($r['depth'])  ? (float)$r['depth']  : 0.0;
    $offset  = isset($r['offset']) ? (float)$r['offset'] : 0.0;
    $rawBias = isset($r['raw_bias_counts']) ? (float)$r['raw_bias_counts'] : 0.0;
    $tankGaugeType = isset($r['tank_gauge_type']) ? (int)$r['tank_gauge_type'] : 0;
    
    // If tank_gauge_type is 999 (No TG), override enabled to false
    $enabled = !empty($r) ? ((int)$r['enabled'] === 1) : false;
    if ($tankGaugeType === 999) {
      $enabled = false;
    }

    $prodId  = isset($r['product_id']) ? (int)$r['product_id'] : 0;
    $product = $pmap[$prodId] ?? 'UNKNOWN';

    $chartId     = isset($r['chart_id']) ? (int)$r['chart_id'] : 0;
    $chartName   = $chartId ? ($cmap[$chartId] ?? null) : null;
    $chartEnabled= (bool)$chartId;

    $hh = isset($r['crithigh_alarm']) ? (int)$r['crithigh_alarm'] : 0;
    $h  = isset($r['high_alarm'])     ? (int)$r['high_alarm']     : 0;
    $l  = isset($r['low_alarm'])      ? (int)$r['low_alarm']      : 0;
    $ll = isset($r['critlow_alarm'])  ? (int)$r['critlow_alarm']  : 0;

    $out[] = [
      'id'               => $i,
      'name'             => $name,
      'capacity'         => $cap,
      'shape'            => $shape,
      'height'           => $height,
      'width'            => $width,
      'depth'            => $depth,
      'offset'           => $offset,
      'product'          => $product,
      'probeId'          => $i,
      'enabled'          => $enabled,
      'raw_bias_counts'  => $rawBias,
      'chartName'        => $chartName,
      'chartEnabled'     => $chartEnabled,
      'alarms' => [
        ['type'=>'HIGH_HIGH','level'=>$hh],
        ['type'=>'HIGH'     ,'level'=>$h ],
        ['type'=>'LOW'      ,'level'=>$l ],
        ['type'=>'LOW_LOW'  ,'level'=>$ll],
      ],
    ];
  }

  $path = "/home/ehon/files/gateway/cfg/$uid/TANKS.json";
  write_json_atomic($path, $out);  // replace $tanksPayloadArray with your actual array
  echo json_encode(['ok' => true]);
  exit;

}

/**
 * Build products.json for a gateway. Always writes 5 entries in this order:
 * UNKNOWN, DIESEL, ADBLUE, WATER, and a 5th entry which is either ULP (default)
 * or replaced with the selected product if it is not in the default list.
 */
function write_products_json(PDO $pdo, int $uid, ?int $product_id = null): array {
  $round4 = static function ($v) {
    return round((float)$v, 4);
  };
  $default = [
    ['name' => 'UNKNOWN', 'density' => $round4(1.00)],
    ['name' => 'DIESEL',  'density' => $round4(0.84)],
    ['name' => 'ADBLUE',  'density' => $round4(1.09)],
    ['name' => 'WATER',   'density' => $round4(1.00)],
    ['name' => 'ULP',     'density' => $round4(0.75)],
  ];

  $listNames = ['UNKNOWN','DIESEL','ADBLUE','WATER','ULP'];

  $replace = null; // ['name'=>..., 'density'=>...]
  if ($product_id && $product_id > 0) {
    try {
      $st = $pdo->prepare("SELECT product_name, product_density FROM products WHERE product_id = ? LIMIT 1");
      $st->execute([$product_id]);
      if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $pname = trim((string)$r['product_name']);
        $pdens = (float)$r['product_density'];
        $norm  = strtoupper($pname);
        if (!in_array($norm, $listNames, true)) {
          $replace = ['name' => $pname, 'density' => $round4($pdens)];
        }
      }
    } catch (Throwable $e) { /* ignore, fall back to default ULP */ }
  }

  if ($replace) {
    $default[4] = $replace; // replace ULP entry
  }

  // Ensure all densities are limited to 4 decimals (as numbers)
  foreach ($default as &$entry) {
    if (array_key_exists('density', $entry)) {
      $entry['density'] = $round4($entry['density']);
    }
  }
  unset($entry);

  $path = "/home/ehon/files/gateway/cfg/{$uid}/products.json";
  atomic_write_json($path, $default);
  return $default;
}

/** ---------- main handler ---------- */
try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $op  = $_GET['op'] ?? null;

  $raw = file_get_contents('php://input');
  $body = $raw ? json_decode($raw, true) : [];
  if (!is_array($body)) $body = [];

  $uid = (int)($body['uid'] ?? $_GET['uid'] ?? 0);
  if (!$uid && $op !== 'alerts_get') {
    if ($method === 'POST') {
      if ($uid <= 0) throw new RuntimeException('uid is required');
    }
  }

  switch ($op) {

    /* -----------------------------------------------------------
       PRODUCTS → write products.json
       body: { uid, product_id? }
       ----------------------------------------------------------- */
    case 'products': {
      if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST required']); exit; }
      if (!$uid) throw new RuntimeException('uid is required');

      $product_id = (int)($body['product_id'] ?? 0);
      $arr = write_products_json($pdo, $uid, $product_id ?: null);
      echo json_encode(['ok' => true, 'products' => $arr]);
      exit;
    }

    /* -----------------------------------------------------------
       BASIC tank info (name, capacity, product) → tanks table
       ----------------------------------------------------------- */
    case 'basic': {
      if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST required']); exit; }

      $site_id    = (int)($body['site_id']     ?? 0);
      $tank_no    = (int)($body['tank_no']     ?? $body['tank_number'] ?? 0);
      $tank_name  = trim((string)($body['tank_name']  ?? ''));
      $capacity   = (float)($body['capacity']  ?? 0);
      $product_id = (int)($body['product_id']  ?? 0);
      $chart_id   = isset($body['chart_id']) ? (int)$body['chart_id'] : 0; // 0 => NULL
      $no_tg      = isset($body['no_tg']) ? (int)$body['no_tg'] : 0;

      if (!$uid || !$site_id || !$tank_no) {
        throw new RuntimeException('uid, site_id and tank_no are required');
      }

      // If no_tg is checked, set tank_gauge_type to 999, otherwise set to NULL
      $tank_gauge_type = $no_tg ? 999 : null;

      $sql = "UPDATE tanks
                 SET Tank_name = :name,
                     capacity  = :cap,
                     product_id= :pid,
                     chart_id  = :chart_id,
                     tank_gauge_type = :tank_gauge_type
               WHERE uid      = :uid
                 AND Site_id  = :site_id
                 AND tank_id  = :tank_no";

      $st = $pdo->prepare($sql);
      $st->execute([
        ':name'    => $tank_name,
        ':cap'     => $capacity,
        ':pid'     => $product_id,
        ':chart_id'=> ($chart_id ?: null),
        ':tank_gauge_type' => $tank_gauge_type,
        ':uid'     => $uid,
        ':site_id' => $site_id,
        ':tank_no' => $tank_no
      ]);

      // If alarms_config row is missing, insert using capacity for high/crithigh.
      // If it exists but values are 0/NULL, update them to capacity.
      $sqlAl = "INSERT INTO alarms_config
                   (client_id, uid, Site_id, tank_id,
                    high_alarm, low_alarm, crithigh_alarm, critlow_alarm, alarm_enable,
                    relay1, relay2, relay3, relay4)
                 SELECT
                   0, :uid, :site_id, :tank_no,
                   :cap1, 0, :cap2, 0, 0,
                   0,0,0,0
                 FROM DUAL
                 ON DUPLICATE KEY UPDATE
                   high_alarm = IF(alarms_config.high_alarm IS NULL OR alarms_config.high_alarm=0, VALUES(high_alarm), alarms_config.high_alarm),
                   crithigh_alarm = IF(alarms_config.crithigh_alarm IS NULL OR alarms_config.crithigh_alarm=0, VALUES(crithigh_alarm), alarms_config.crithigh_alarm)";

      $stAl = $pdo->prepare($sqlAl);
      $stAl->execute([
        ':uid'     => $uid,
        ':site_id' => $site_id,
        ':tank_no' => $tank_no,
        ':cap1'    => (int)$capacity,
        ':cap2'    => (int)$capacity,
      ]);

      // refresh TANKS.json
      $resolved_site = resolve_site_id($pdo, $uid, $tank_no, $site_id);
      if ($resolved_site) write_tanks_json($pdo, $uid, $resolved_site);

      echo json_encode(['ok' => true, 'affected' => $st->rowCount()]);
      exit;
    }

    /* -----------------------------------------------------------
       TANK GEOMETRY → config_ehon_gateway
       Payload: { uid, tank_device_id, shape, height, width, depth, offset }
       ----------------------------------------------------------- */
    case 'tanks': {
      if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST required']); exit; }

      $tank_device_id = (int)($body['tank_device_id'] ?? 0);
      if (!$tank_device_id) throw new RuntimeException('tank_device_id is required');

      $shape  = (int)($body['shape']   ?? 0);
      $height = (float)($body['height'] ?? 0);
      $width  = (float)($body['width']  ?? 0);
      $depth  = (float)($body['depth']  ?? 0);

      $sql = "UPDATE config_ehon_gateway
                 SET shape = :shape,
                     height = :height,
                     width = :width,
                     depth = :depth
               WHERE uid = :uid AND tank_device_id = :td";

      $st = $pdo->prepare($sql);
      $st->execute([
        ':shape'  => $shape,
        ':height' => $height,
        ':width'  => $width,
        ':depth'  => $depth,
        ':uid'    => $uid,
        ':td'     => $tank_device_id
      ]);

      // refresh TANKS.json (try to resolve site)
      $tank_no = (int)($body['tank_no'] ?? 0);
      $site_id = (int)($body['site_id'] ?? 0);
      $resolved_site = resolve_site_id($pdo, $uid, $tank_no ?: $tank_device_id, $site_id);
      if ($resolved_site) write_tanks_json($pdo, $uid, $resolved_site);

      echo json_encode(['ok' => true, 'affected' => $st->rowCount()]);
      exit;
    }

    /* -----------------------------------------------------------
      PORT MODES/IDs → config_ehon_gateway + write ports.json
      Payload: { uid, tank_device_id, mindex_0, fmsindex_0, mindex_1, fmsindex_1, tank_no? }
      ----------------------------------------------------------- */
    case 'ports': {
    if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST required']); exit; }

    if (!$uid) throw new RuntimeException('uid is required');

    $m0  = (int)($body['mindex_0']   ?? 0);   // index 0 → UART5
    $fi0 = (int)($body['fmsindex_0'] ?? 0);
    $m1  = (int)($body['mindex_1']   ?? 0);   // index 1 → UART3
    $fi1 = (int)($body['fmsindex_1'] ?? 0);

    // ensure console row exists
    $pdo->prepare("INSERT IGNORE INTO console (uid, UART5, UART5_fms, UART3, UART3_fms) VALUES (?,0,0,0,0)")
        ->execute([$uid]);

    // update device-level ports
    $sql = "UPDATE console
              SET UART5=:m0, UART5_fms=:fi0, UART3=:m1, UART3_fms=:fi1
            WHERE uid=:uid";
    $st = $pdo->prepare($sql);
    $st->execute([':m0'=>$m0, ':fi0'=>$fi0, ':m1'=>$m1, ':fi1'=>$fi1, ':uid'=>$uid]);

    // Use tank_no from frontend when mode index (mindex) == 1 or 4
    $tank_no = (int)($body['tank_no'] ?? 0);
    $ports = [
      ['index'=>0, 'mode'=>$m0, 'tankNum'=>(($m0 === 1 || $m0 === 4) ? $tank_no : 0), 'fms'=>$fi0],
      ['index'=>1, 'mode'=>$m1, 'tankNum'=>(($m1 === 1 || $m1 === 4) ? $tank_no : 0), 'fms'=>$fi1],
    ];
    atomic_write_json("/home/ehon/files/gateway/cfg/{$uid}/ports.json", $ports);

    // optional: keep bundle cohesive
    $site_id = (int)($body['site_id'] ?? 0);
    $resolved_site = resolve_site_id($pdo, $uid, null, $site_id);
    if ($resolved_site) write_tanks_json($pdo, $uid, $resolved_site);

    echo json_encode(['ok'=>true, 'ports'=>$ports]); exit;
  }

  case 'ports_get': {
    $uid = (int)($_GET['uid'] ?? $body['uid'] ?? 0);
    if (!$uid) throw new RuntimeException('uid required');
    $row = ['mindex_0'=>0,'fmsindex_0'=>0,'mindex_1'=>0,'fmsindex_1'=>0];

    $st = $pdo->prepare("SELECT UART5 AS mindex_0, UART5_fms AS fmsindex_0,
                                UART3 AS mindex_1, UART3_fms AS fmsindex_1
                        FROM console WHERE uid=? LIMIT 1");
    $st->execute([$uid]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) $row = $r;

    echo json_encode(['ok'=>true, 'ports'=>$row]); exit;
  }

    /* -----------------------------------------------------------
       ALERTS (READ) → alarms_config
       GET ?op=alerts_get&uid=...&site_id=...&tank_id=...
       ----------------------------------------------------------- */
    case 'alerts_get': {
      $uid     = (int)($_GET['uid']     ?? $body['uid']     ?? 0);
      $site_id = (int)($_GET['site_id'] ?? $body['site_id'] ?? 0);
      $tank_id = (int)($_GET['tank_id'] ?? $body['tank_id'] ?? ($body['tank_no'] ?? 0));

      if (!$uid || !$site_id || !$tank_id) throw new RuntimeException('uid, site_id, tank_id required');

      $st = $pdo->prepare("SELECT client_id, uid, Site_id, tank_id,
                                  high_alarm, low_alarm, crithigh_alarm, critlow_alarm, alarm_enable,
                                  relay1, relay2, relay3, relay4
                             FROM alarms_config
                            WHERE uid=? AND Site_id=? AND tank_id=? LIMIT 1");
      $st->execute([$uid, $site_id, $tank_id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        // Default to tank capacity for high/crithigh when no alarms_config row exists
        $cap = 0;
        try {
          $stc = $pdo->prepare("SELECT COALESCE(capacity,0) FROM tanks WHERE uid=? AND Site_id=? AND tank_id=? LIMIT 1");
          $stc->execute([$uid, $site_id, $tank_id]);
          $v = $stc->fetchColumn();
          if ($v !== false) { $cap = (int)$v; }
        } catch (Throwable $e) { /* ignore */ }

        $row = [
          'client_id'=>0,'uid'=>$uid,'Site_id'=>$site_id,'tank_id'=>$tank_id,
          'high_alarm'=>$cap,'low_alarm'=>0,'crithigh_alarm'=>$cap,'critlow_alarm'=>0,'alarm_enable'=>0,
          'relay1'=>0,'relay2'=>0,'relay3'=>0,'relay4'=>0,'exists'=>false
        ];
      }

      echo json_encode(['ok'=>true, 'alerts'=>$row]);
      exit;
    }

    /* -----------------------------------------------------------
       ALERTS (UPSERT) → alarms_config (no relays for gateway)
       POST ?op=alerts
       body: { uid, site_id, tank_id, client_id=?, high_alarm, low_alarm, crithigh_alarm, critlow_alarm, alarm_enable }
       ----------------------------------------------------------- */
    case 'alerts': {
      if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST required']); exit; }

      $site_id         = (int)($body['site_id'] ?? 0);
      $tank_id         = (int)($body['tank_id'] ?? ($body['tank_no'] ?? 0));
      $client_id       = (int)($body['client_id'] ?? 0);

      $high_alarm      = (int)($body['high_alarm']     ?? 0);
      $low_alarm       = (int)($body['low_alarm']      ?? 0);
      $crithigh_alarm  = (int)($body['crithigh_alarm'] ?? 0);
      $critlow_alarm   = (int)($body['critlow_alarm']  ?? 0);
      $alarm_enable    = (int)($body['alarm_enable']   ?? 0);
      // Validate required early
      if (!$uid || !$site_id || !$tank_id) throw new RuntimeException('uid, site_id, tank_id required');

      // Fail on out-of-range values using capacity
      try {
        $stCap = $pdo->prepare("SELECT COALESCE(capacity,0) FROM tanks WHERE uid=? AND Site_id=? AND tank_id=? LIMIT 1");
        $stCap->execute([$uid, $site_id, $tank_id]);
        $capVal = $stCap->fetchColumn();
        $cap    = ($capVal !== false) ? max(0, (int)$capVal) : 0;
      } catch (Throwable $e) { $cap = 0; }
      if ($cap > 0 && ($high_alarm > $cap || $crithigh_alarm > $cap)) {
        http_response_code(422);
        echo json_encode(['error' => "High and Critical High cannot exceed capacity ($cap)"]); exit;
      }
      if ($low_alarm < 0 || $critlow_alarm < 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Low and Critical Low cannot be below 0']); exit;
      }

      // (uid/site_id/tank_id already validated above)

      // Guard: if 'tanks.enabled' exists and is 0, block writes.
      try {
        $chk = $pdo->prepare("SELECT enabled FROM tanks WHERE uid=? AND Site_id=? AND tank_id=? LIMIT 1");
        $chk->execute([$uid, $site_id, $tank_id]);
        $en = $chk->fetchColumn();
        if ($en !== false && (int)$en === 0) {
          http_response_code(409);
          echo json_encode(['error'=>'Tank slot is disabled']); exit;
        }
      } catch (Throwable $e) {
        // 'enabled' column may not exist yet; ignore guard.
      }

      $sql = "INSERT INTO alarms_config
                (client_id, uid, Site_id, tank_id,
                 high_alarm, low_alarm, crithigh_alarm, critlow_alarm, alarm_enable,
                 relay1, relay2, relay3, relay4)
              VALUES
                (:client_id, :uid, :site_id, :tank_id,
                 :high, :low, :chigh, :clow, :en, 0,0,0,0)
              ON DUPLICATE KEY UPDATE
                 high_alarm=VALUES(high_alarm),
                 low_alarm=VALUES(low_alarm),
                 crithigh_alarm=VALUES(crithigh_alarm),
                 critlow_alarm=VALUES(critlow_alarm),
                 alarm_enable=VALUES(alarm_enable),
                 client_id=VALUES(client_id)";

      $st = $pdo->prepare($sql);
      $st->execute([
        ':client_id'=>$client_id, ':uid'=>$uid, ':site_id'=>$site_id, ':tank_id'=>$tank_id,
        ':high'=>$high_alarm, ':low'=>$low_alarm, ':chigh'=>$crithigh_alarm, ':clow'=>$critlow_alarm, ':en'=>$alarm_enable
      ]);

      // refresh TANKS.json
      $resolved_site = resolve_site_id($pdo, $uid, $tank_id, $site_id);
      if ($resolved_site) write_tanks_json($pdo, $uid, $resolved_site);

      echo json_encode(['ok'=>true, 'affected'=>$st->rowCount()]);
      exit;
    }

    default:
      throw new RuntimeException('Unknown op. Use ?op=basic | tanks | ports | alerts_get | alerts');
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
