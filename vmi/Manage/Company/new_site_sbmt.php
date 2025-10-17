<?php
// /vmi/site/new_site_sbmt.php
// Handles site submit AND, if the selected console is a GATEWAY (device_type=30),
// auto-provisions 4 tanks + per-tank config + tank_device rows + writes TANKS.json.

header('X-Content-Type-Options: nosniff');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../db/pdo_boot.php'; // -> $pdo (ERRMODE_EXCEPTION)

/* ----------------- small utilities ----------------- */
function is_ajax(): bool {
  $a = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
  return strtolower($a) === 'xmlhttprequest' || (($_SERVER['HTTP_ACCEPT'] ?? '') && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}
function ensure_dir(string $d){ if(!is_dir($d)) @mkdir($d,0775,true); }
function atomic_write_json(string $path, array $data): void {
  ensure_dir(dirname($path));
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); // single line
  if ($json === false) throw new RuntimeException('JSON encode failed for '.$path);
  $tmp = tempnam(dirname($path), 'gw_');
  if ($tmp === false) throw new RuntimeException('Temp file create failed for '.$path);
  if (file_put_contents($tmp, $json, LOCK_EX) === false) { @unlink($tmp); throw new RuntimeException('Write failed for '.$path); }
  @chmod($tmp, 0664);
  if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Atomic move failed for '.$path); }
}

/* ----------------- bundle writer ----------------- */
function write_tanks_json(PDO $pdo, int $uid, int $site_id): void {
  $sql = "
    SELECT t.tank_id, t.Tank_name, t.capacity, IFNULL(t.enabled,0) enabled, IFNULL(t.product_id,0) product_id,
           g.shape, g.height, g.width, g.depth, IFNULL(g.raw_bias_counts,0) raw_bias_counts,
           a.high_alarm, a.low_alarm, a.crithigh_alarm, a.critlow_alarm
      FROM tanks t
      LEFT JOIN config_ehon_gateway g
        ON g.uid=t.uid AND g.tank_device_id=t.tank_id
      LEFT JOIN alarms_config a
        ON a.uid=t.uid AND a.Site_id=t.Site_id AND a.tank_id=t.tank_id
     WHERE t.uid=? AND t.Site_id=? AND t.tank_id BETWEEN 1 AND 4
     ORDER BY t.tank_id";
  $st = $pdo->prepare($sql);
  $st->execute([$uid, $site_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;

  $out = [];
  for ($i=1;$i<=4;$i++){
    $r = $byId[$i] ?? [];
    $out[] = [
      'id'      => $i,
      'name'    => $r['Tank_name'] ?? ('TANK'.$i),
      'capacity'=> (float)($r['capacity'] ?? 0),
      'shape'   => (int)($r['shape'] ?? 2),
      'height'  => (float)($r['height'] ?? 0),
      'width'   => (float)($r['width'] ?? 0),
      'depth'   => (float)($r['depth'] ?? 0),
      'offset'  => (float)($r['raw_bias_counts'] ?? 0),
      'product' => 'UNKNOWN',
      'probeId' => $i,
      'enabled' => !empty($r) ? ((int)$r['enabled'] === 1) : false,
      'raw_bias_counts' => (float)($r['raw_bias_counts'] ?? 0),
      'chartName'    => null,
      'chartEnabled' => false,
      'alarms' => [
        ['type'=>'HIGH_HIGH','level'=>(int)($r['crithigh_alarm']??0)],
        ['type'=>'HIGH'     ,'level'=>(int)($r['high_alarm']??0)],
        ['type'=>'LOW'      ,'level'=>(int)($r['low_alarm']??0)],
        ['type'=>'LOW_LOW'  ,'level'=>(int)($r['critlow_alarm']??0)],
      ],
    ];
  }
  atomic_write_json("/home/ehon/files/gateway/cfg/{$uid}/TANKS.json", $out);
}

/* ----------------- gateway bootstrap ----------------- */
function bootstrap_gateway_for_site(PDO $pdo, int $uid, int $site_id): array {
  // Verify device type (gateway == 30)
  $st = $pdo->prepare("SELECT device_type FROM console WHERE uid=? LIMIT 1");
  $st->execute([$uid]);
  $dtype = (int)($st->fetchColumn() ?: 0);
  if ($dtype !== 30) return ['skipped'=>true, 'device_type'=>$dtype];

  $pdo->beginTransaction();

  // 4 fixed tank slots (idempotent)
  $insTank = $pdo->prepare("
    INSERT INTO tanks (uid, Site_id, tank_id, Tank_name, capacity, product_id, enabled)
    VALUES (:uid,:site,:id,:name,0,0,0)
    ON DUPLICATE KEY UPDATE
      Tank_name = IF(Tank_name='' OR Tank_name IS NULL, VALUES(Tank_name), Tank_name)
  ");
  $created_tanks = 0;
  for ($i=1;$i<=4;$i++){
    $created_tanks += (int)$insTank->execute([':uid'=>$uid, ':site'=>$site_id, ':id'=>$i, ':name'=>"TANK{$i}"]);
  }

  // Per-tank geometry rows (idempotent)
  $insGw = $pdo->prepare("
    INSERT IGNORE INTO config_ehon_gateway (uid, tank_device_id, shape, height, width, depth, raw_bias_counts)
    VALUES (:uid,:id,2,0,0,0,0)
  ");
  $created_gw = 0;
  for ($i=1;$i<=4;$i++){
    $insGw->execute([':uid'=>$uid, ':id'=>$i]);
    $created_gw += (int)$insGw->rowCount();
  }

  // Optional: tank_device rows (schema may vary; ignore on error)
  $created_td = 0;
  try {
    $insTd = $pdo->prepare("INSERT IGNORE INTO tank_device (uid, tank_device_id, Site_id) VALUES (:uid,:id,:site)");
    for ($i=1;$i<=4;$i++){
      $insTd->execute([':uid'=>$uid, ':id'=>$i, ':site'=>$site_id]);
      $created_td += (int)$insTd->rowCount();
    }
  } catch (Throwable $e) {}

  // Optional: seed alarms rows (zeros; ignore if table missing)
  $created_al = 0;
  try {
    $insAl = $pdo->prepare("
      INSERT IGNORE INTO alarms_config (client_id, uid, Site_id, tank_id, high_alarm, low_alarm, crithigh_alarm, critlow_alarm, alarm_enable, relay1, relay2, relay3, relay4)
      VALUES (0,:uid,:site,:id,0,0,0,0,0,0,0,0,0)
    ");
    for ($i=1;$i<=4;$i++){
      $insAl->execute([':uid'=>$uid, ':site'=>$site_id, ':id'=>$i]);
      $created_al += (int)$insAl->rowCount();
    }
  } catch (Throwable $e) {}

  $pdo->commit();

  // Write bundle
  write_tanks_json($pdo, $uid, $site_id);

  return [
    'skipped'      => false,
    'device_type'  => $dtype,
    'created' => [
      'tanks'  => $created_tanks,
      'gw'     => $created_gw,
      'device' => $created_td,
      'alarms' => $created_al,
    ],
  ];
}

/* ----------------- main handler ----------------- */
try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo is_ajax() ? json_encode(['error'=>'POST required']) : 'POST required';
    exit;
  }

  // You likely already created the site above this block in your original file.
  // We just need the final $site_id and the selected console $uid:
  $uid     = (int)($_POST['uid']     ?? 0);
  $site_id = (int)($_POST['site_id'] ?? 0);

  if ($uid <= 0 || $site_id <= 0) {
    http_response_code(400);
    echo is_ajax() ? json_encode(['error'=>'uid and site_id are required']) : 'uid and site_id are required';
    exit;
  }

  // Bootstrap if gateway
  $res = bootstrap_gateway_for_site($pdo, $uid, $site_id);

  // Decide response
  if (is_ajax()) {
    echo json_encode(['ok'=>true, 'uid'=>$uid, 'site_id'=>$site_id, 'bootstrap'=>$res]);
  } else {
    // redirect back to site detail (adjust URL to your app)
    header('Location: ./new_site.php?site_id='.$site_id.'&uid='.$uid.'&boot='.($res['skipped']?'skip':'ok'));
  }
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  $msg = 'Error: '.$e->getMessage();
  echo is_ajax() ? json_encode(['error'=>$msg]) : $msg;
}
