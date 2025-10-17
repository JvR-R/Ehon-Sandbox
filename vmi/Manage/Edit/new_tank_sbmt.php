<?php
// /vmi/Manage/Edit/new_tank_sbmt.php
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../../db/pdo_boot.php'; // -> $pdo (ERRMODE_EXCEPTION)

// ---------- helpers ----------
function ensure_dir(string $d){ if(!is_dir($d)) @mkdir($d,0775,true); }
function atomic_write_json(string $path, array $data): void {
  ensure_dir(dirname($path));
  $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if ($json === false) throw new RuntimeException('json encode failed: '.$path);
  $tmp = tempnam(dirname($path), 'gw_');
  file_put_contents($tmp, $json, LOCK_EX);
  @chmod($tmp, 0664);
  rename($tmp, $path);
}
function resolve_client_id(PDO $pdo, int $site_id): int {
  try {
    $st = $pdo->prepare("SELECT client_id FROM Sites WHERE Site_id=? LIMIT 1");
    $st->execute([$site_id]);
    return (int)($st->fetchColumn() ?: 0);
  } catch (Throwable $e) {
    return 0;
  }
}
function write_tanks_json(PDO $pdo, int $uid, int $site_id): void {
  $sql = "
    SELECT t.tank_id, t.Tank_name, t.capacity, IFNULL(t.enabled,0) enabled, IFNULL(t.product_id,0) product_id,
           g.shape, g.height, g.width, g.depth, IFNULL(g.raw_bias_counts,0) raw_bias_counts,
           a.high_alarm, a.low_alarm, a.crithigh_alarm, a.critlow_alarm
      FROM tanks t
      LEFT JOIN config_ehon_gateway g ON g.uid=t.uid AND g.tank_id=t.tank_id
      LEFT JOIN alarms_config a ON a.uid=t.uid AND a.Site_id=t.Site_id AND a.tank_id=t.tank_id
     WHERE t.uid=? AND t.Site_id=? AND t.tank_id BETWEEN 1 AND 4
     ORDER BY t.tank_id";
  $st = $pdo->prepare($sql);
  $st->execute([$uid,$site_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;
  $out = [];
  for ($i=1;$i<=4;$i++){
    $r = $byId[$i] ?? [];
    $out[] = [
      'id'=>$i,
      'name'=>$r['Tank_name'] ?? ('TANK'.$i),
      'capacity'=>(float)($r['capacity']??0),
      'shape'=>(int)($r['shape']??2),
      'height'=>(float)($r['height']??0),
      'width'=>(float)($r['width']??0),
      'depth'=>(float)($r['depth']??0),
      'offset'=>(float)($r['raw_bias_counts']??0),
      'product'=>'UNKNOWN',
      'probeId'=>$i,
      'enabled'=>!empty($r) ? ((int)$r['enabled']===1) : false,
      'raw_bias_counts'=>(float)($r['raw_bias_counts']??0),
      'chartName'=>null,
      'chartEnabled'=>false,
      'alarms'=>[
        ['type'=>'HIGH_HIGH','level'=>(int)($r['crithigh_alarm']??0)],
        ['type'=>'HIGH'     ,'level'=>(int)($r['high_alarm']??0)],
        ['type'=>'LOW'      ,'level'=>(int)($r['low_alarm']??0)],
        ['type'=>'LOW_LOW'  ,'level'=>(int)($r['critlow_alarm']??0)],
      ],
    ];
  }
  atomic_write_json("/home/ehon/files/gateway/cfg/{$uid}/TANKS.json", $out);
}

// ---------- handler ----------
try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo 'POST required'; exit; }

  $uid     = (int)($_POST['uid'] ?? 0);
  $site_id = (int)($_POST['site_id'] ?? 0);
  $mode    = (string)($_POST['mode'] ?? '');

  if ($uid<=0 || $site_id<=0) { http_response_code(400); echo 'uid and site_id required'; exit; }

  if ($mode === 'toggle_gateway') {
    // Update 4 slots
    $enabled   = $_POST['enabled']    ?? [];
    $names     = $_POST['name']       ?? [];
    $capac     = $_POST['capacity']   ?? [];
    $products  = $_POST['product_id'] ?? [];
    $client_id = resolve_client_id($pdo, $site_id);

    // Prepare alarms upsert (levels only)
    $insAlarm = $pdo->prepare("\n      INSERT INTO alarms_config\n        (client_id, uid, Site_id, tank_id, high_alarm, low_alarm, crithigh_alarm, critlow_alarm)\n      VALUES\n        (:client_id, :uid, :site, :tid, :high, 0, :crithigh, 0)\n      ON DUPLICATE KEY UPDATE\n        high_alarm=VALUES(high_alarm),\n        low_alarm=VALUES(low_alarm),\n        crithigh_alarm=VALUES(crithigh_alarm),\n        critlow_alarm=VALUES(critlow_alarm)\n    ");

    $st = $pdo->prepare("UPDATE tanks
                            SET enabled=:en,
                                Tank_name=COALESCE(NULLIF(:name,''), Tank_name),
                                capacity=:cap,
                                product_id=:pid
                          WHERE uid=:uid AND Site_id=:site AND tank_id=:tid");
    for ($i=1;$i<=4;$i++){
      $st->execute([
        ':en'  => isset($enabled[$i]) ? 1 : 0,
        ':name'=> trim((string)($names[$i] ?? "TANK{$i}")),
        ':cap' => (int)($capac[$i] ?? 0),
        ':pid' => (int)($products[$i] ?? 0),
        ':uid' => $uid,
        ':site'=> $site_id,
        ':tid' => $i
      ]);

      // Upsert alarms based on current capacity
      $capVal = (int)($capac[$i] ?? 0);
      $insAlarm->execute([
        ':client_id' => $client_id,
        ':uid'       => $uid,
        ':site'      => $site_id,
        ':tid'       => $i,
        ':high'      => $capVal,
        ':crithigh'  => $capVal,
      ]);
    }

    // regenerate device bundle
    write_tanks_json($pdo, $uid, $site_id);

    header('Location: new_tank.php?site_id='.$site_id.'&uid='.$uid.'&updated=1');
    exit;
  }

  // Non-gateway fallback: create single tank
  if ($mode === 'create_single') {
    $tank_id   = (int)($_POST['tank_id'] ?? 0);
    $Tank_name = trim((string)($_POST['Tank_name'] ?? ''));
    $capacity  = (int)($_POST['capacity'] ?? 0);
    $product_id= (int)($_POST['product_id'] ?? 0);
    if ($tank_id<=0 || $Tank_name==='') { http_response_code(400); echo 'tank_id and name required'; exit; }

    $sql = "INSERT INTO tanks (uid, Site_id, tank_id, Tank_name, capacity, product_id, enabled)
            VALUES (:uid,:site,:tid,:name,:cap,:pid,1)
            ON DUPLICATE KEY UPDATE Tank_name=VALUES(Tank_name), capacity=VALUES(capacity), product_id=VALUES(product_id), enabled=1";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':uid'=>$uid, ':site'=>$site_id, ':tid'=>$tank_id,
      ':name'=>$Tank_name, ':cap'=>$capacity, ':pid'=>$product_id
    ]);

    // Upsert alarms for this tank (levels only)
    $client_id = resolve_client_id($pdo, $site_id);
    $insAlarm = $pdo->prepare("\n      INSERT INTO alarms_config\n        (client_id, uid, Site_id, tank_id, high_alarm, low_alarm, crithigh_alarm, critlow_alarm)\n      VALUES\n        (:client_id, :uid, :site, :tid, :high, 0, :crithigh, 0)\n      ON DUPLICATE KEY UPDATE\n        high_alarm=VALUES(high_alarm),\n        low_alarm=VALUES(low_alarm),\n        crithigh_alarm=VALUES(crithigh_alarm),\n        critlow_alarm=VALUES(critlow_alarm)\n    ");
    $insAlarm->execute([
      ':client_id' => $client_id,
      ':uid'       => $uid,
      ':site'      => $site_id,
      ':tid'       => $tank_id,
      ':high'      => $capacity,
      ':crithigh'  => $capacity,
    ]);

    header('Location: new_tank.php?site_id='.$site_id.'&uid='.$uid.'&created=1');
    exit;
  }

  // Default: nothing matched
  http_response_code(400);
  echo 'Unsupported operation';

} catch (Throwable $e) {
  http_response_code(500);
  echo 'Error: '.$e->getMessage();
}
?>
