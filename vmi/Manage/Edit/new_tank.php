<?php
// /vmi/Manage/Edit/new_tank.php
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', 1);
error_reporting(E_ALL);


include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');

require __DIR__ . '/../../db/pdo_boot.php'; // -> $pdo (ERRMODE_EXCEPTION)

/* ----------------- helpers ----------------- */
function get_int_param(array $names): int {
  foreach ($names as $k) {
    if (isset($_GET[$k]))  return (int)$_GET[$k];
    if (isset($_POST[$k])) return (int)$_POST[$k];
    if (isset($_SESSION[$k])) return (int)$_SESSION[$k];
  }
  return 0;
}

function resolve_client_id(PDO $pdo, int $site_id): int {
  $st = $pdo->prepare("SELECT client_id FROM Sites WHERE Site_id=? LIMIT 1");
  $st->execute([$site_id]);
  return (int)($st->fetchColumn() ?: 0);
}

function gw_is_gateway(PDO $pdo, int $uid): bool {
  try {
    $st = $pdo->prepare("SELECT device_type FROM console WHERE uid=? LIMIT 1");
    $st->execute([$uid]);
    $dtype = (int)($st->fetchColumn() ?: 0);
    if ($dtype === 30) return true;
  } catch (Throwable $e) { /* ignore and try fallbacks */ }

  // Fallbacks: if prior data exists, infer gateway
  try {
    $st = $pdo->prepare("SELECT 1 FROM config_ehon_gateway WHERE uid=? LIMIT 1");
    $st->execute([$uid]);
    if ($st->fetchColumn()) return true;
  } catch (Throwable $e) {}
  try {
    $st = $pdo->prepare("SELECT 1 FROM tank_device WHERE uid=? AND role='gateway' LIMIT 1");
    $st->execute([$uid]);
    if ($st->fetchColumn()) return true;
  } catch (Throwable $e) {}
  // As a last fallback, if there are 4 tank slots 1..4 already present, assume gateway UI
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tanks WHERE uid=? AND tank_id BETWEEN 1 AND 4");
    $st->execute([$uid]);
    $cnt = (int)($st->fetchColumn() ?: 0);
    if ($cnt >= 4) return true;
  } catch (Throwable $e) {}
  return false;
}

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

/** DDL must run OUTSIDE transactions (implicit COMMIT on MySQL) */
function ensure_enabled_column(PDO $pdo): void {
  try {
    $pdo->exec("ALTER TABLE tanks ADD COLUMN IF NOT EXISTS enabled TINYINT(1) NOT NULL DEFAULT 0");
  } catch (Throwable $e) { /* ignore */ }
}

/** Ensure 4 basic tank rows exist for any device (no device-config side effects) */
function ensure_four_tanks_basic(PDO $pdo, int $uid, int $site_id, int $client_id): void {
  ensure_enabled_column($pdo);
  $insTank = $pdo->prepare("
    INSERT INTO tanks (uid, Site_id, client_id, tank_id, Tank_name, capacity, product_id, enabled)
    VALUES (:uid,:site,:client,:id,:name,0,0,0)
    ON DUPLICATE KEY UPDATE
      Tank_name = IF(Tank_name='' OR Tank_name IS NULL, VALUES(Tank_name), Tank_name)
  ");
  for ($i=1;$i<=4;$i++){
    $insTank->execute([
      ':uid'=>$uid, ':site'=>$site_id, ':client'=>$client_id,
      ':id'=>$i, ':name'=>"TANK{$i}"
    ]);
  }

  // Also ensure tank_device rows exist (role 'other' for non-gateway)
  $st = $pdo->prepare("SELECT tank_uid, tank_id FROM tanks WHERE uid=? AND Site_id=? AND tank_id BETWEEN 1 AND 4 ORDER BY tank_id");
  $st->execute([$uid,$site_id]);
  $tankRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $insTd = $pdo->prepare("INSERT IGNORE INTO tank_device (tank_uid, uid, role) VALUES (:tank_uid, :uid, 'other')");
  foreach ($tankRows as $tr) {
    $insTd->execute([':tank_uid' => (int)$tr['tank_uid'], ':uid' => $uid]);
  }
}

/** Build 1..4 snapshot and write /home/ehon/files/gateway/cfg/<uid>/TANKS.json */
function write_tanks_json(PDO $pdo, int $uid, int $site_id): void {
  $sql = "
    SELECT t.tank_uid, t.tank_id, t.Tank_name, t.capacity, IFNULL(t.enabled,0) enabled, IFNULL(t.product_id,0) product_id,
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
  for ($i=1; $i<=4; $i++){
    $r = $byId[$i] ?? [];
    $out[] = [
      'id'            => $i,
      'tank_uid'      => isset($r['tank_uid']) ? (int)$r['tank_uid'] : 0,
      'name'          => $r['Tank_name'] ?? ('TANK'.$i),
      'capacity'      => (float)($r['capacity']??0),
      'shape'         => (int)($r['shape']??2),
      'height'        => (float)($r['height']??0),
      'width'         => (float)($r['width']??0),
      'depth'         => (float)($r['depth']??0),
      'offset'        => (float)($r['raw_bias_counts']??0),
      'product'       => 'UNKNOWN',
      'probeId'       => $i,
      'enabled'       => !empty($r) ? ((int)$r['enabled']===1) : false,
      'raw_bias_counts'=> (float)($r['raw_bias_counts']??0),
      'chartName'     => null,
      'chartEnabled'  => false,
      'alarms'        => [
        ['type'=>'HIGH_HIGH','level'=>(int)($r['crithigh_alarm']??0)],
        ['type'=>'HIGH'     ,'level'=>(int)($r['high_alarm']??0)],
        ['type'=>'LOW'      ,'level'=>(int)($r['low_alarm']??0)],
        ['type'=>'LOW_LOW'  ,'level'=>(int)($r['critlow_alarm']??0)],
      ],
    ];
  }
  atomic_write_json("/home/ehon/files/gateway/cfg/{$uid}/TANKS.json", $out);
}

/** Ensure tanks 1..4 + geometry rows + tank_device rows exist */
function gw_ensure_gateway_slots(PDO $pdo, int $uid, int $site_id, int $client_id): void {
  ensure_enabled_column($pdo); // DDL outside txn

  try {
    $pdo->beginTransaction();

    // 1) Ensure 4 tanks
    $insTank = $pdo->prepare("
      INSERT INTO tanks (uid, Site_id, client_id, tank_id, Tank_name, capacity, product_id, enabled)
      VALUES (:uid,:site,:client,:id,:name,0,0,0)
      ON DUPLICATE KEY UPDATE
        Tank_name = IF(Tank_name='' OR Tank_name IS NULL, VALUES(Tank_name), Tank_name)
    ");
    for ($i=1;$i<=4;$i++){
      $insTank->execute([
        ':uid'=>$uid, ':site'=>$site_id, ':client'=>$client_id,
        ':id'=>$i, ':name'=>"TANK{$i}"
      ]);
    }

    // 2) Fetch tank_uids and ensure tank_device rows (schema: id AI, tank_uid, uid, role,...)
    $st = $pdo->prepare("SELECT tank_uid, tank_id FROM tanks WHERE uid=? AND Site_id=? AND tank_id BETWEEN 1 AND 4 ORDER BY tank_id");
    $st->execute([$uid,$site_id]);
    $tankRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $insTd = $pdo->prepare("
      INSERT IGNORE INTO tank_device (tank_uid, uid, role)
      VALUES (:tank_uid, :uid, 'gateway')
    ");
    foreach ($tankRows as $tr) {
      $insTd->execute([':tank_uid' => (int)$tr['tank_uid'], ':uid' => $uid]);
    }

    // 3) Map tank_uid -> tank_device.id and seed config_ehon_gateway rows (idempotent)
    $selTd = $pdo->prepare("SELECT id FROM tank_device WHERE uid=? AND tank_uid=? LIMIT 1");
    $insCfg = $pdo->prepare("
      INSERT IGNORE INTO config_ehon_gateway
        (tank_device_id, tank_uid, uid, tank_id, shape, height, width, depth, raw_bias_counts)
      VALUES
        (:td_id, :tank_uid, :uid, :tank_id, 2, 0, 0, 0, 0)
    ");
    foreach ($tankRows as $tr) {
      $tankUid = (int)$tr['tank_uid'];
      $tankId  = (int)$tr['tank_id'];
      $selTd->execute([$uid, $tankUid]);
      $tdId = (int)($selTd->fetchColumn() ?: 0);
      if ($tdId > 0) {
        $insCfg->execute([
          ':td_id'    => $tdId,
          ':tank_uid' => $tankUid,
          ':uid'      => $uid,
          ':tank_id'  => $tankId,
        ]);
      }
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

/* ----------------- fetch products for dropdown ----------------- */
function get_products(PDO $pdo): array {
  try {
    $st = $pdo->prepare("SELECT product_id, product_name FROM products ORDER BY product_id");
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    error_log("Failed to fetch products: " . $e->getMessage());
    return [];
  }
}

/* ----------------- param intake + fallback ----------------- */
$uid     = get_int_param(['uid','console_uid','device_uid','console']);
$site_id = get_int_param(['site_id','site']);

// Fallback: resolve site_id if we have uid
if ($uid > 0 && $site_id <= 0) {
  try {
    $st = $pdo->prepare("SELECT Site_id FROM tanks WHERE uid=? ORDER BY Site_id LIMIT 1");
    $st->execute([$uid]);
    $site_id = (int)($st->fetchColumn() ?: 0);
  } catch (Throwable $e) {}
}

// Guard
if ($uid <= 0 || $site_id <= 0) {
  error_log("new_tank.php missing params. GET=".json_encode($_GET)." POST=".json_encode($_POST)." SESSION=".json_encode($_SESSION));
  http_response_code(400);
  echo 'uid and site_id are required';
  exit;
}

// Resolve client and (optionally) authorize session scope
$client_id = resolve_client_id($pdo, $site_id);
if ($client_id <= 0) {
  http_response_code(400);
  exit("Site {$site_id} has no client_id");
}
if (isset($_SESSION['companyId'])) {
  $companyId = (int)$_SESSION['companyId'];
  if ($companyId > 0 && $companyId !== $client_id) {
    http_response_code(403);
    exit('Forbidden: site does not belong to your company');
  }
}

// Persist for subsequent posts
$_SESSION['uid'] = $uid;
$_SESSION['site_id'] = $site_id;

/* ----------------- page data ----------------- */
$products = get_products($pdo);
$isGw = gw_is_gateway($pdo, $uid);
if ($isGw) {
  // first load → ensure rows exist + write default JSON for device
  gw_ensure_gateway_slots($pdo, $uid, $site_id, $client_id);
  write_tanks_json($pdo, $uid, $site_id);
  $st = $pdo->prepare("SELECT tank_id, IFNULL(enabled,0) enabled,
                              COALESCE(Tank_name, CONCAT('TANK', tank_id)) AS Tank_name,
                              COALESCE(capacity,0) AS capacity,
                              COALESCE(product_id,0) AS product_id
                         FROM tanks
                        WHERE uid=? AND Site_id=? AND tank_id BETWEEN 1 AND 4
                        ORDER BY tank_id");
  $st->execute([$uid,$site_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $byId = []; foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;
} else {
  // Non-gateway: still ensure 1..4 tank rows exist so UI always has slots
  ensure_four_tanks_basic($pdo, $uid, $site_id, $client_id);
  $st = $pdo->prepare("SELECT tank_id, IFNULL(enabled,0) enabled,
                              COALESCE(Tank_name, CONCAT('TANK', tank_id)) AS Tank_name,
                              COALESCE(capacity,0) AS capacity,
                              COALESCE(product_id,0) AS product_id
                         FROM tanks
                        WHERE uid=? AND Site_id=? AND tank_id BETWEEN 1 AND 4
                        ORDER BY tank_id");
  $st->execute([$uid,$site_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $byId = []; foreach ($rows as $r) $byId[(int)$r['tank_id']] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tank Management - Ehon Energy Tech</title>
  <meta property="og:type" content="website">
  <meta content="summary_large_image" name="twitter:card">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  
  <!-- Standard VMI CSS Files -->
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="/vmi/details/menu.css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  
  <!-- Custom Tank Management CSS -->
  <link rel="stylesheet" href="new_tank.css">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Toastr for notifications -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  
  <!-- JavaScript -->
  <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="dashboard-content">
      <div class="sidebar-spacer"></div>
      <div class="sidebar-spacer2"></div>
      <div class="dashboard-main-content">
        <?php include('../../details/top_menu.php');?>
        
        <div class="tank-page-container">
          <header class="tank-page-header">
            <h1 class="page-title">Tank Management</h1>
            <p class="page-subtitle">Configure and manage fuel tank settings</p>
          </header>
          
          <div class="tank-page-content">
            <div class="tank-info">
              <strong>Device UID:</strong> <?=htmlspecialchars((string)$uid)?> • 
              <strong>Site ID:</strong> <?=htmlspecialchars((string)$site_id)?><?= $isGw? '<span class="gateway-badge">Gateway</span>':'' ?>
            </div>

<?php if ($isGw): ?>
      <form method="post" action="new_tank_sbmt.php" class="tank-form">
        <input type="hidden" name="uid" value="<?=htmlspecialchars((string)$uid)?>">
        <input type="hidden" name="site_id" value="<?=htmlspecialchars((string)$site_id)?>">
        <input type="hidden" name="mode" value="toggle_gateway">

        <table class="tank-table">
          <thead>
            <tr>
              <th>Tank</th>
              <th>Enabled</th>
              <th>Tank Name</th>
              <th>Capacity (L)</th>
              <th>Product</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $byId = $byId ?? [];
            for ($i=1;$i<=4;$i++):
              $r = $byId[$i] ?? ['enabled'=>0,'Tank_name'=>"TANK{$i}",'capacity'=>0,'product_id'=>0];
            ?>
            <tr>
              <td><?= $i ?></td>
              <td>
                <input type="checkbox" 
                       name="enabled[<?= $i ?>]" 
                       value="1" 
                       <?= ((int)$r['enabled']===1?'checked':'') ?>
                       id="enabled_<?= $i ?>"
                       aria-label="Enable Tank <?= $i ?>">
              </td>
              <td>
                <input type="text" 
                       name="name[<?= $i ?>]" 
                       value="<?= htmlspecialchars((string)$r['Tank_name']) ?>"
                       class="tank-name"
                       placeholder="Enter tank name"
                       aria-label="Tank <?= $i ?> Name">
              </td>
              <td>
                <input type="number" 
                       step="1" 
                       name="capacity[<?= $i ?>]" 
                       value="<?= (int)$r['capacity'] ?>"
                       min="0"
                       placeholder="0"
                       aria-label="Tank <?= $i ?> Capacity in Liters">
              </td>
              <td>
                <select name="product_id[<?= $i ?>]" 
                        aria-label="Tank <?= $i ?> Product">
                  <?php foreach ($products as $product): ?>
                    <option value="<?= (int)$product['product_id'] ?>" 
                            <?= ((int)$r['product_id'] === (int)$product['product_id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($product['product_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>

        <div class="actions">
          <button type="submit" class="btn-primary">
            <span class="sr-only">Save all</span>
            Save Tank Configuration
          </button>
          <a href="new_site.php?site_id=<?=urlencode((string)$site_id)?>&uid=<?=urlencode((string)$uid)?>" class="btn-secondary">
            ← Back to Site
          </a>
        </div>
      </form>

<?php else: ?>
      <!-- Non-gateway fallback: minimal single-tank create -->
      <div class="tank-form">
        <h2>Create New Tank</h2>
        <p class="text-muted">Add a single tank configuration for this device.</p>
        
        <form method="post" action="new_tank_sbmt.php">
          <input type="hidden" name="uid" value="<?=htmlspecialchars((string)$uid)?>">
          <input type="hidden" name="site_id" value="<?=htmlspecialchars((string)$site_id)?>">
          <input type="hidden" name="mode" value="create_single">
          
          <table class="tank-table">
            <tbody>
              <tr>
                <th>Tank Number</th>
                <td>
                  <input type="number" 
                         name="tank_id" 
                         min="1" 
                         step="1" 
                         required 
                         placeholder="Enter tank number"
                         aria-label="Tank Number">
                </td>
              </tr>
              <tr>
                <th>Tank Name</th>
                <td>
                  <input type="text" 
                         name="Tank_name" 
                         required 
                         class="tank-name"
                         placeholder="Enter tank name"
                         aria-label="Tank Name">
                </td>
              </tr>
              <tr>
                <th>Capacity (Liters)</th>
                <td>
                  <input type="number" 
                         name="capacity" 
                         step="1" 
                         value="0"
                         min="0"
                         placeholder="Enter capacity in liters"
                         aria-label="Tank Capacity">
                </td>
              </tr>
              <tr>
                <th>Product</th>
                <td>
                  <select name="product_id" aria-label="Product">
                    <?php foreach ($products as $product): ?>
                      <option value="<?= (int)$product['product_id'] ?>" 
                              <?= ((int)$product['product_id'] === 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($product['product_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          
          <div class="actions">
            <button type="submit" class="btn-primary">
              Create Tank
            </button>
            <a href="new_site.php?site_id=<?=urlencode((string)$site_id)?>&uid=<?=urlencode((string)$uid)?>" class="btn-secondary">
              ← Back to Site
            </a>
          </div>
        </form>
      </div>
<?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
