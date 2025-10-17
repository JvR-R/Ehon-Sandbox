<?php
// /vmi/site/new_tank.php
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../db/pdo_boot.php'; // -> $pdo (ERRMODE_EXCEPTION)

/* ---------- helpers (inline to keep this file drop-in) ---------- */
function gw_is_gateway(PDO $pdo, int $uid): bool {
  $st = $pdo->prepare("SELECT device_type FROM console WHERE uid=? LIMIT 1");
  $st->execute([$uid]);
  return ((int)($st->fetchColumn() ?: 0)) === 30;
}
function ensure_dir(string $d){ if(!is_dir($d)) @mkdir($d,0775,true); }
function atomic_write_json(string $path, array $data): void {
  ensure_dir(dirname($path));
  $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); // single line
  if ($json === false) throw new RuntimeException('json encode failed: '.$path);
  $tmp = tempnam(dirname($path), 'gw_');
  file_put_contents($tmp, $json, LOCK_EX);
  @chmod($tmp, 0664);
  rename($tmp, $path);
}
/** Build 1..4 TANKS.json (kept here so page can regen after first ensure) */
function write_tanks_json(PDO $pdo, int $uid, int $site_id): void {
  $sql = "
    SELECT t.tank_id, t.Tank_name, t.capacity, IFNULL(t.enabled,0) enabled, IFNULL(t.product_id,0) product_id,
           g.shape, g.height, g.width, g.depth, IFNULL(g.raw_bias_counts,0) raw_bias_counts,
           a.high_alarm, a.low_alarm, a.crithigh_alarm, a.critlow_alarm
      FROM tanks t
      LEFT JOIN config_ehon_gateway g ON g.uid=t.uid AND g.tank_device_id=t.tank_id
      LEFT JOIN alarms_config a ON a.uid=t.uid AND a.Site_id=t.Site_id AND a.tank_id=t.tank_id
     WHERE t.uid=? AND t.Site_id=? AND t.tank_id BETWEEN 1 AND 4
     ORDER BY t.tank_id";
  $st = $pdo->prepare($sql);
  $st->execute([$uid,$site_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;

  $out = [];
  for ($i=1; $i<=4; $i++){
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
/** Ensure 1..4 tanks + per-tank geometry + (optional) tank_device rows exist */
function gw_ensure_gateway_slots(PDO $pdo, int $uid, int $site_id): void {
  $pdo->beginTransaction();
  // Try add enabled column if missing (ignore errors on older MySQL)
  try { $pdo->exec("ALTER TABLE tanks ADD COLUMN IF NOT EXISTS enabled TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
  $insTank = $pdo->prepare("
    INSERT INTO tanks (uid, Site_id, tank_id, Tank_name, capacity, product_id, enabled)
    VALUES (:uid,:site,:id,:name,0,0,0)
    ON DUPLICATE KEY UPDATE
      Tank_name = IF(Tank_name='' OR Tank_name IS NULL, VALUES(Tank_name), Tank_name)
  ");
  for ($i=1;$i<=4;$i++){
    $insTank->execute([':uid'=>$uid, ':site'=>$site_id, ':id'=>$i, ':name'=>"TANK{$i}"]);
  }
  $insGw = $pdo->prepare("
    INSERT IGNORE INTO config_ehon_gateway (uid, tank_device_id, shape, height, width, depth, raw_bias_counts)
    VALUES (:uid,:id,2,0,0,0,0)
  ");
  for ($i=1;$i<=4;$i++){ $insGw->execute([':uid'=>$uid, ':id'=>$i]); }
  try {
    $insTd = $pdo->prepare("INSERT IGNORE INTO tank_device (uid, tank_device_id, Site_id) VALUES (:uid,:id,:site)");
    for ($i=1;$i<=4;$i++){ $insTd->execute([':uid'=>$uid, ':id'=>$i, ':site'=>$site_id]); }
  } catch (Throwable $e) {}
  $pdo->commit();
}

$uid     = (int)($_GET['uid']     ?? $_POST['uid']     ?? 0);
$site_id = (int)($_GET['site_id'] ?? $_POST['site_id'] ?? 0);
if ($uid<=0 || $site_id<=0) { http_response_code(400); echo 'uid and site_id required'; exit; }

$isGw = gw_is_gateway($pdo, $uid);
if ($isGw) {
  // make sure rows are there the first time you open this page
  gw_ensure_gateway_slots($pdo, $uid, $site_id);
  // (optional) generate bundle now so device can fetch defaults immediately
  write_tanks_json($pdo, $uid, $site_id);
}

// Load current state
if ($isGw) {
  $st = $pdo->prepare("SELECT tank_id, IFNULL(enabled,0) enabled, COALESCE(Tank_name, CONCAT('TANK', tank_id)) AS Tank_name,
                              COALESCE(capacity,0) AS capacity, COALESCE(product_id,0) AS product_id
                         FROM tanks
                        WHERE uid=? AND Site_id=? AND tank_id BETWEEN 1 AND 4
                        ORDER BY tank_id");
  $st->execute([$uid,$site_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Tanks</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; max-width: 950px; }
    th, td { border: 1px solid #ddd; padding: 8px; }
    th { background: #f6f7f9; text-align: left; }
    input[type="number"] { width: 120px; }
    input[type="text"] { width: 220px; }
    .actions { margin-top: 14px; }
    .note { color: #666; font-size: 13px; margin: 10px 0; }
  </style>
</head>
<body>
  <h2>Tanks</h2>
  <div class="note">UID: <?=htmlspecialchars((string)$uid)?> &mdash; Site: <?=htmlspecialchars((string)$site_id)?><?= $isGw? ' &nbsp;(<strong>Gateway</strong>)':'' ?></div>

<?php if ($isGw): ?>
  <form method="post" action="new_tank_sbmt.php">
    <input type="hidden" name="uid" value="<?=htmlspecialchars((string)$uid)?>">
    <input type="hidden" name="site_id" value="<?=htmlspecialchars((string)$site_id)?>">
    <input type="hidden" name="mode" value="toggle_gateway">

    <table>
      <tr>
        <th>Tank</th>
        <th>Enabled</th>
        <th>Name</th>
        <th>Capacity (L)</th>
        <th>Product ID</th>
      </tr>
      <?php for ($i=1;$i<=4;$i++):
        $r = $byId[$i] ?? ['enabled'=>0,'Tank_name'=>"TANK{$i}",'capacity'=>0,'product_id'=>0];
      ?>
      <tr>
        <td><?= $i ?></td>
        <td><input type="checkbox" name="enabled[<?= $i ?>]" value="1" <?= ((int)$r['enabled']===1?'checked':'') ?>></td>
        <td><input type="text" name="name[<?= $i ?>]" value="<?= htmlspecialchars((string)$r['Tank_name']) ?>"></td>
        <td><input type="number" step="1" name="capacity[<?= $i ?>]" value="<?= (int)$r['capacity'] ?>"></td>
        <td><input type="number" step="1" name="product_id[<?= $i ?>]" value="<?= (int)$r['product_id'] ?>"></td>
      </tr>
      <?php endfor; ?>
    </table>

    <div class="actions">
      <button type="submit">Save Tanks</button>
      <a href="new_site.php?site_id=<?=urlencode((string)$site_id)?>&uid=<?=urlencode((string)$uid)?>">Back</a>
    </div>
  </form>

<?php else: ?>
  <!-- Non-gateway: minimal single-tank creation (adjust to your existing workflow) -->
  <form method="post" action="new_tank_sbmt.php">
    <input type="hidden" name="uid" value="<?=htmlspecialchars((string)$uid)?>">
    <input type="hidden" name="site_id" value="<?=htmlspecialchars((string)$site_id)?>">
    <input type="hidden" name="mode" value="create_single">
    <table>
      <tr><th>Tank #</th><td><input type="number" name="tank_id" min="1" step="1" required></td></tr>
      <tr><th>Name</th><td><input type="text" name="Tank_name" required></td></tr>
      <tr><th>Capacity (L)</th><td><input type="number" name="capacity" step="1" value="0"></td></tr>
      <tr><th>Product ID</th><td><input type="number" name="product_id" step="1" value="0"></td></tr>
    </table>
    <div class="actions">
      <button type="submit">Create Tank</button>
      <a href="new_site.php?site_id=<?=urlencode((string)$site_id)?>&uid=<?=urlencode((string)$uid)?>">Back</a>
    </div>
  </form>
<?php endif; ?>
</body>
</html>
