<?php
/**
 * /vmi/reports/index.php  – complete, refactored, copy-paste ready
 *
 * Requires:
 *   • /vmi/db/pdo_boot.php  → starts session & gives $pdo
 *   • /vmi/db/log.php       → your login / ACL checks
 *   • /vmi/db/border2.php   → top border / nav (PDO version)
 */
require_once dirname(__DIR__) . '/db/pdo_boot.php';
require_once dirname(__DIR__) . '/db/log.php';
require_once dirname(__DIR__) . '/db/border2.php';

$companyId  = (int)($_SESSION['companyId']  ?? 0);
$accessLvl  = (int)($_SESSION['accessLevel']?? 0);
$IS_GLOBAL  = ($companyId === 15100);
$DEBUG  = isset($_GET['debug']);

ini_set('display_errors', 0);
error_reporting(E_ALL);
/* ──────────────────────────────────────────────────────────────
 *  Helpers
 * ──────────────────────────────────────────────────────────── */
if (!function_exists('esc')) {
    function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
function pct_change(float $new, float $old): float
{
    return $old > 0 ? round(($new / $old - 1) * 100, 1)
      : ($new > 0 ? 100 : 0);
}

/* ──────────────────────────────────────────────────────────────
 *  Date ranges
 * ──────────────────────────────────────────────────────────── */
$tz   = new DateTimeZone('Australia/Brisbane');
$now  = new DateTime('now', $tz);
$D30  = (clone $now)->modify('-30 days')->format('Y-m-d');
$D60  = (clone $now)->modify('-60 days')->format('Y-m-d');
$D365 = (clone $now)->modify('-12 months')->format('Y-m-d');
$D730 = (clone $now)->modify('-24 months')->format('Y-m-d');

/* ──────────────────────────────────────────────────────────────
 *  Prepared queries – explicit, no str_replace
 * ──────────────────────────────────────────────────────────── */

/* Active sites – last 30 days */
if ($IS_GLOBAL) {
    $sqlActive = "SELECT COUNT(*) FROM (
        SELECT 1
          FROM client_transaction
        WHERE transaction_date >= :d30
        GROUP BY uid, tank_id
    ) t";
    $paramsActive = ['d30' => $D30];
} else {
    $sqlActive = "SELECT COUNT(*) FROM (
        SELECT 1
          FROM client_transaction ct
          JOIN Console_Asociation ca USING(uid)
        WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
          AND ct.transaction_date >= :d30
        GROUP BY uid, tank_id
    ) t";
    $paramsActive = ['d30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlActive);
$stmt->execute($paramsActive);
$activeSites = (int)$stmt->fetchColumn();

/* Active sites – previous window (30–60 days ago) */
if ($IS_GLOBAL) {
    $sqlActivePrev = "SELECT COUNT(*) FROM (
        SELECT 1
          FROM client_transaction
        WHERE transaction_date BETWEEN :d60 AND :d30
        GROUP BY uid, tank_id
    ) t";
    $paramsActivePrev = ['d60' => $D60, 'd30' => $D30];
} else {
    $sqlActivePrev = "SELECT COUNT(*) FROM (
        SELECT 1
          FROM client_transaction ct
          JOIN Console_Asociation ca USING(uid)
        WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
          AND ct.transaction_date BETWEEN :d60 AND :d30
        GROUP BY uid, tank_id
    ) t";
    $paramsActivePrev = ['d60' => $D60, 'd30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlActivePrev);
$stmt->execute($paramsActivePrev);
$activeSitesPrev = (int)$stmt->fetchColumn();
$activePct = pct_change($activeSites, $activeSitesPrev);

/* Total transactions – last 30 days */
if ($IS_GLOBAL) {
    $sqlTx = "SELECT COUNT(*) FROM client_transaction WHERE transaction_date >= :d30";
    $paramsTx = ['d30' => $D30];
} else {
    $sqlTx = "SELECT COUNT(*)
                FROM client_transaction ct
                JOIN Console_Asociation ca USING(uid)
              WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
                AND ct.transaction_date >= :d30";
    $paramsTx = ['d30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlTx);
$stmt->execute($paramsTx);
$totalTx = (int)$stmt->fetchColumn();

/* Total transactions – previous window */
if ($IS_GLOBAL) {
    $sqlTxPrev = "SELECT COUNT(*)
                    FROM client_transaction
                  WHERE transaction_date BETWEEN :d60 AND :d30";
    $paramsTxPrev = ['d60' => $D60, 'd30' => $D30];
} else {
    $sqlTxPrev = "SELECT COUNT(*)
                    FROM client_transaction ct
                    JOIN Console_Asociation ca USING(uid)
                  WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
                    AND ct.transaction_date BETWEEN :d60 AND :d30";
    $paramsTxPrev = ['d60' => $D60, 'd30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlTxPrev);
$stmt->execute($paramsTxPrev);
$totalTxPrev = (int)$stmt->fetchColumn();
$txPct = pct_change($totalTx, $totalTxPrev);

/* Total volume – last 30 days */
if ($IS_GLOBAL) {
    $sqlVol = "SELECT SUM(dispensed_volume)
                FROM client_transaction
                WHERE transaction_date >= :d30";
    $paramsVol = ['d30' => $D30];
} else {
    $sqlVol = "SELECT SUM(dispensed_volume)
                FROM client_transaction ct
                JOIN Console_Asociation ca USING(uid)
                WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
                  AND ct.transaction_date >= :d30";
    $paramsVol = ['d30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlVol);
$stmt->execute($paramsVol);
$totalVol = (float)$stmt->fetchColumn();

/* Total volume – previous window */
if ($IS_GLOBAL) {
    $sqlVolPrev = "SELECT SUM(dispensed_volume)
                    FROM client_transaction
                    WHERE transaction_date BETWEEN :d60 AND :d30";
    $paramsVolPrev = ['d60' => $D60, 'd30' => $D30];
} else {
    $sqlVolPrev = "SELECT SUM(dispensed_volume)
                    FROM client_transaction ct
                    JOIN Console_Asociation ca USING(uid)
                    WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
                      AND ct.transaction_date BETWEEN :d60 AND :d30";
    $paramsVolPrev = ['d60' => $D60, 'd30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlVolPrev);
$stmt->execute($paramsVolPrev);
$totalVolPrev = (float)$stmt->fetchColumn();
$volPct = pct_change($totalVol, $totalVolPrev);

/* Total deliveries – last 30 days */
if ($IS_GLOBAL) {
    $sqlDel = "SELECT SUM(delivery)
                FROM delivery_historic
                WHERE tank_id != 99
                  AND transaction_date >= :d30";
    $paramsDel = ['d30' => $D30];
} else {
    $sqlDel = "SELECT SUM(delivery)
                FROM delivery_historic d
                JOIN Console_Asociation ca USING(uid)
                WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
                  AND d.tank_id != 99
                  AND d.transaction_date >= :d30";
    $paramsDel = ['d30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlDel);
$stmt->execute($paramsDel);
$totalDel = (float)$stmt->fetchColumn();

/* Total deliveries – previous window */
if ($IS_GLOBAL) {
    $sqlDelPrev = "SELECT SUM(delivery)
                    FROM delivery_historic
                    WHERE tank_id != 99
                      AND transaction_date BETWEEN :d60 AND :d30";
    $paramsDelPrev = ['d60' => $D60, 'd30' => $D30];
} else {
    $sqlDelPrev = "SELECT SUM(delivery)
                    FROM delivery_historic d
                    JOIN Console_Asociation ca USING(uid)
                    WHERE (ca.client_id = :cid OR ca.reseller_id = :rid)
                      AND d.tank_id != 99
                      AND d.transaction_date BETWEEN :d60 AND :d30";
    $paramsDelPrev = ['d60' => $D60, 'd30' => $D30, 'cid' => $companyId, 'rid' => $companyId];
}
$stmt = $pdo->prepare($sqlDelPrev);
$stmt->execute($paramsDelPrev);
$totalDelPrev = (float)$stmt->fetchColumn();
$delPct = pct_change($totalDel, $totalDelPrev);


/* Chart – volume per month (last 24 months) */
$sqlVolChart = $IS_GLOBAL
  ? "SELECT DATE_FORMAT(transaction_date,'%Y-%m') m,SUM(dispensed_volume) v
       FROM client_transaction
      WHERE transaction_date >= :d730
      GROUP BY m ORDER BY m"
  : "SELECT DATE_FORMAT(ct.transaction_date,'%Y-%m') m,SUM(ct.dispensed_volume) v
       FROM client_transaction ct
       JOIN Console_Asociation ca USING(uid)
      WHERE (ca.client_id=:cid OR ca.reseller_id=:rid)
        AND ct.transaction_date >= :d730
      GROUP BY m ORDER BY m";
$stmt = $pdo->prepare($sqlVolChart);
$stmt->execute($IS_GLOBAL? ['d730'=>$D730] : ['cid'=>$companyId, 'rid' => $companyId, 'd730'=>$D730]);
$chartVol = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Chart – tx per month (last 24 months) */
$sqlTxChart = $IS_GLOBAL
  ? "SELECT DATE_FORMAT(transaction_date,'%Y-%m') m,COUNT(*) n
       FROM client_transaction
      WHERE transaction_date >= :d730
      GROUP BY m ORDER BY m"
  : "SELECT DATE_FORMAT(ct.transaction_date,'%Y-%m') m,COUNT(*) n
       FROM client_transaction ct
       JOIN Console_Asociation ca USING(uid)
      WHERE (ca.client_id=:cid OR ca.reseller_id=:rid)
        AND ct.transaction_date >= :d730
      GROUP BY m ORDER BY m";
$stmt = $pdo->prepare($sqlTxChart);
$stmt->execute($IS_GLOBAL? ['d730'=>$D730] : ['cid'=>$companyId, 'rid' => $companyId, 'd730'=>$D730]);
$chartTx = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($DEBUG) {
    error_log("Reports Debug: companyId=$companyId IS_GLOBAL=" . ($IS_GLOBAL ? 1 : 0) . " D730=$D730");
    error_log("VolChart SQL: " . $sqlVolChart);
    error_log("VolChart params: " . json_encode($IS_GLOBAL ? ['d730'=>$D730] : ['cid'=>$companyId, 'rid' => $companyId, 'd730'=>$D730]));
    error_log("VolChart rows: " . count($chartVol));
    error_log("TxChart SQL: " . $sqlTxChart);
    error_log("TxChart params: " . json_encode($IS_GLOBAL ? ['d730'=>$D730] : ['cid'=>$companyId, 'rid' => $companyId, 'd730'=>$D730]));
    error_log("TxChart rows: " . count($chartTx));
}

/* Recent 6 transactions */
$sqlRecent = $IS_GLOBAL
 ? "SELECT transaction_date,transaction_time,Site_name,dispensed_volume
      FROM client_transaction ct
      JOIN Sites st USING(uid)
     ORDER BY CONCAT(transaction_date,' ',transaction_time) DESC LIMIT 6"
 : "SELECT transaction_date,transaction_time,Site_name,dispensed_volume
      FROM client_transaction ct
      JOIN Sites st USING(uid)
      JOIN Console_Asociation ca USING(uid)
     WHERE ca.client_id=:cid OR ca.reseller_id=:rid
     ORDER BY CONCAT(transaction_date,' ',transaction_time) DESC LIMIT 6";
$stmt = $pdo->prepare($sqlRecent);
$stmt->execute($IS_GLOBAL? [] : ['cid'=>$companyId, 'rid' => $companyId]);
$recentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Fuel-Tax summary */
$sqlFuel = $IS_GLOBAL
 ? "SELECT ROUND(SUM(ct.dispensed_volume),2) vol, ctb.tax_value,
           ROUND(ROUND(SUM(ct.dispensed_volume),2)/100*ctb.tax_value,2) total
      FROM client_transaction ct
      JOIN client_tasbax ctb ON (ctb.card_number = ct.card_number
                              AND ctb.registration = ct.registration)
     WHERE ct.uid IN (SELECT uid FROM console WHERE device_type != 999)
       AND ct.dispensed_volume > 0
       AND ct.transaction_date > :d365
     GROUP BY ctb.tax_value
     ORDER BY vol DESC"
 : "SELECT ROUND(SUM(ct.dispensed_volume),2) vol, ctb.tax_value,
           ROUND(ROUND(SUM(ct.dispensed_volume),2)/100*ctb.tax_value,2) total
      FROM client_transaction ct
      JOIN Console_Asociation ca USING(uid)
      JOIN client_tasbax ctb ON (ctb.card_number = ct.card_number
                              AND ctb.registration = ct.registration)
     WHERE ct.uid IN (SELECT uid FROM console WHERE device_type != 999)
       AND ct.dispensed_volume > 0
       AND (ca.client_id=:cid OR ca.reseller_id=:rid OR ca.dist_id=:did)
       AND ct.transaction_date > :d365
     GROUP BY ctb.tax_value
     ORDER BY vol DESC";
$stmt = $pdo->prepare($sqlFuel);
$paramsFuel = $IS_GLOBAL? ['d365'=>$D365] : ['d365'=>$D365,'cid'=>$companyId, 'rid' => $companyId, 'did' => $companyId];
$stmt->execute($paramsFuel);
$fuelRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Reconciliation  */
$sqlRec = $IS_GLOBAL
 ? "SELECT COALESCE(SUM(Opening_balance),0) opening,
           COALESCE(SUM(Closing_balance),0) closing,
           COALESCE(SUM(Delta),0)          dip
      FROM clients_recconciliation"
 : "SELECT COALESCE(SUM(cr.Opening_balance),0) opening,
           COALESCE(SUM(cr.Closing_balance),0) closing,
           COALESCE(SUM(cr.Delta),0)          dip
      FROM clients_recconciliation cr
      JOIN Console_Asociation ca USING(uid)
     WHERE cr.Client_id=:cid OR ca.reseller_id=:rid OR ca.dist_id=:did";
$stmt = $pdo->prepare($sqlRec);
$stmt->execute($IS_GLOBAL? [] : ['cid'=>$companyId, 'rid' => $companyId, 'did' => $companyId]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['opening'=>0,'closing'=>0,'dip'=>0];

/* Consumption by driver (top-6) */
$sqlCons = $IS_GLOBAL
 ? "SELECT SUM(dispensed_volume) tot, card_holder_name
      FROM client_transaction
     GROUP BY card_holder_name
     ORDER BY tot DESC LIMIT 6"
 : "SELECT SUM(dispensed_volume) tot, card_holder_name
      FROM client_transaction ct
      JOIN Console_Asociation ca USING(uid)
     WHERE ca.client_id=:cid OR ca.reseller_id=:rid
     GROUP BY card_holder_name
     ORDER BY tot DESC LIMIT 6";
$stmt = $pdo->prepare($sqlCons);
$stmt->execute($IS_GLOBAL? [] : ['cid'=>$companyId, 'rid' => $companyId]);
$consRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Volume by site (top-5) */
$sqlSiteVol = $IS_GLOBAL
 ? "SELECT SUM(dispensed_volume) tot, st.Site_name
      FROM client_transaction ct
      JOIN Sites st USING(uid)
     GROUP BY ct.uid ORDER BY tot DESC LIMIT 5"
 : "SELECT SUM(dispensed_volume) tot, st.Site_name
      FROM client_transaction ct
      JOIN Sites st USING(uid)
      JOIN Console_Asociation ca USING(uid)
     WHERE st.client_id=:cid OR ca.reseller_id=:rid
     GROUP BY ct.uid ORDER BY tot DESC LIMIT 5";
$stmt = $pdo->prepare($sqlSiteVol);
$stmt->execute($IS_GLOBAL? [] : ['cid'=>$companyId, 'rid' => $companyId]);
$siteVolRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Json encode for JS */
$chartVolJson = json_encode($chartVol, JSON_THROW_ON_ERROR);
$chartTxJson  = json_encode($chartTx , JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Reports</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="/vmi/images/favicon.ico">
<!-- THEME CSS - MUST BE FIRST -->
<link rel="stylesheet" href="/vmi/css/theme.css">
<!-- Other CSS files -->
<link rel="stylesheet" href="/vmi/css/style_rep.css">
<link rel="stylesheet" href="/vmi/css/test-site-de674e.webflow.css">
<link rel="stylesheet" href="/vmi/css/normalize.css">
<link rel="stylesheet" href="/vmi/css/webflow.css">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="page-wrapper">
<h1 style="color:#EC1C1C">Client Reports</h1>

<!-- ── Top metric cards ─────────────────────────────────────────────── -->
<div class="small-details-card-grid" style="margin-bottom: 1rem;">
  <?php
  // helper to print a metric card quickly
  function metricCard(string $href,string $icon,string $label,$value,$pct)
  { ?>
    <a class="card-link" href="<?=esc($href)?>">
      <div class="card top-details">
        <div class="flex-horizontal justify-space-between">
          <div class="flex align-center gap-column-4px">
            <img src="<?=esc($icon)?>" alt="">
            <div class="text-100 medium" style="color:#fff"><?=esc($label)?></div>
          </div>
        </div>
        <div class="flex align-center gap-column-6px">
          <div class="display-4"><?=esc($value)?></div>
          <div class="status-badge <?= $pct>=0?'green':'red' ?>">
              <div class="flex align-center gap-column-4px">
                <div class="paragraph-small"><?=esc($pct)?>%</div>
                <div class="dashdark-custom-icon icon-size-9px"><?= $pct>=0?'↗':'↘' ?></div>
              </div>
          </div>
        </div>
      </div>
    </a>
  <?php }
  metricCard('/vmi/clients/','/vmi/images/pageviews-icon-dashdark-webflow-template.svg',
            'Active Sites (30 days)',$activeSites,$activePct);
  metricCard('/vmi/reports/transactions/','/vmi/images/monthly-users-icon-dashdark-webflow-template.svg',
            'Total Transactions (30 days)',$totalTx,$txPct);
  metricCard('#','/vmi/images/new-sign-ups-icon-dashdark-webflow-template.svg',
            'Total Volume (30 days)',number_format($totalVol,1).' L',$volPct);
  metricCard('/vmi/reports/total_deliveries/','/vmi/images/subscriptions-icon-dashdark-webflow-template.svg',
            'Total Deliveries (30 days)',number_format($totalDel,1).' L',$delPct);
  ?>
</div>

<!-- ── Charts ───────────────────────────────────────────────────────── -->
<div class="chart-details-card-grid" style="margin-bottom: 1rem;">
  <div class="report-card"><h2>Total Volume</h2><canvas id="volChart"></canvas></div>
  <div class="report-card"><h2>Total Transactions</h2><canvas id="txChart"></canvas></div>
</div>
<div class="chart-details-card-grid" style="margin-bottom: 1rem;">
  <!-- ── Fuel-Tax summary ────────────────────────────────────────────── -->
  <!-- <div class="report-card" style="height:100%;">
    <h2>Fuel Tax (last 12 months)</h2>
    <div class="_2-items-wrap-container">
      <div class="flex align-center gap-column-8px"><div class="text-200 medium">Tax&nbsp;%</div></div>
      <div class="text-200 medium">Volume</div>
      <div class="text-200 medium">Total Tax</div>
    </div><div class="divider"></div>
    <?php foreach ($fuelRows as $row): ?>
      <div class="_2-items-wrap-container">
        <div class="flex align-center gap-column-8px"><div class="small-dot"></div>
            <div class="text-200"><?=number_format($row['tax_value'],2)?></div></div>
        <div class="text-200"><?=number_format($row['vol'],2)?> L</div>
        <div class="text-300 medium color-neutral-200">$<?=number_format($row['total'],2)?></div>
      </div><div class="divider"></div>
    <?php endforeach; ?>
  </div> -->

  <!-- ── Reconciliation summary ──────────────────────────────────────── -->
  <!-- <div class="report-card" style="height: 100%;">
    <h2>Reconciliation</h2>
    <div class="_2-items-wrap-container">
      <div class="flex align-center gap-column-8px"><div class="text-200 medium">Opening</div></div>
      <div class="text-200 medium">Closing</div>
      <div class="text-200 medium">Dip</div>
    </div><div class="divider"></div>
    <div class="_2-items-wrap-container">
      <div class="flex align-center gap-column-8px">
          <div class="text-200"><?=number_format($rec['opening'],2)?></div></div>
      <div class="text-200"><?=number_format($rec['closing'],2)?></div>
      <div class="text-300 medium color-neutral-200"><?=number_format($rec['dip'],2)?></div>
    </div><div class="divider"></div>
  </div>
</div> -->
<div class="chart-details-card-grid" style="margin-bottom: 1rem;">
  <!-- ── Consumption by driver ───────────────────────────────────────── -->
  <div class="report-card" style="height: 100%;">
    <h2 style="color:#fff">Consumption – top 6 (30 days)</h2><div class="divider"></div>
    <?php foreach ($consRows as $row): ?>
      <div class="_2-items-wrap-container">
        <div class="flex align-center gap-column-8px"><div class="small-dot"></div>
          <div class="text-200"><?=esc($row['card_holder_name'] ?? '')?></div></div>
        <div class="text-300 medium color-neutral-200"><?=number_format($row['tot'],1)?></div>
      </div><div class="divider"></div>
    <?php endforeach; ?>
  </div>
  <!-- ── Recent 6 transactions table ─────────────────────────────────── -->
  <div class="report-card">
    <h2>Recent Transactions</h2>
    <table class="recent-orders"><thead>
      <tr><th>#</th><th>Date</th><th>Site</th><th>Volume (L)</th></tr></thead><tbody>
      <?php foreach ($recentRows as $i=>$r):
          $dt = DateTime::createFromFormat('Y-m-d H:i:s', ($r['transaction_date'] ?? '') . ' ' . ($r['transaction_time'] ?? ''));
      ?><tr>
        <td><?=esc($i+1)?></td>
        <td><?=esc($dt? $dt->format('M j, h:i a') : ($r['transaction_date'] ?? ''))?></td>
        <td><?=esc($r['Site_name'] ?? '')?></td>
        <td><?=number_format($r['dispensed_volume'],1)?></td>
      </tr><?php endforeach; ?>
    </tbody></table>
  </div>
</div>

</div>
<!-- ── Volume by site + map ────────────────────────────────────────── -->
<div class="report-card">
  <div class="grid-2-columns gap-0">
    <div class="percentage-bars-small-section-container">
      <h2>Volume by Site</h2><div class="divider"></div>
      <?php foreach ($siteVolRows as $row): ?>
        <div class="text-100"><?=esc($row['Site_name'] ?? '')?></div>
        <div class="flex align-center gap-column-20px" style="justify-content:end">
            <div class="text-100"><?=number_format($row['tot'],1)?> L</div></div>
        <div class="divider"></div>
      <?php endforeach; ?>
    </div>
    <div class="pd-20px---52px"><div id="map" style="height:21rem"></div></div>
  </div>
</div><!-- /.page-wrapper -->

<!-- ── Charts JS ───────────────────────────────────────────────────── -->
<script>
const volData = <?= $chartVolJson ?>, txData = <?= $chartTxJson ?>;

new Chart(document.getElementById('volChart'),{
  type:'bar',
  data:{labels:volData.map(r=>r.m),datasets:[{label:'Volume (L)',
        data:volData.map(r=>r.v),backgroundColor:'#6c72ff',borderWidth:1}]},
  options:{scales:{y:{beginAtZero:true}}}
});

new Chart(document.getElementById('txChart'),{
  type:'line',
  data:{labels:txData.map(r=>r.m),datasets:[{label:'Transactions',
        data:txData.map(r=>r.n),borderColor:'#57c3ff',borderWidth:2,fill:false}]},
  options:{scales:{y:{beginAtZero:true}}}
});
</script>
<script src="map.js"></script>
</body>
</html>
