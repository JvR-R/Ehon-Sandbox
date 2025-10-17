<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

/* ─── bootstrap ─────────────────────────────────────────────────── */
require __DIR__ . '/../db/pdo_boot.php';
require __DIR__ . '/../db/log.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
/* ─── company scope ─────────────────────────────────────────────── */
$cid = (int)($_SESSION['companyId'] ?? 0);
if (!$cid) { http_response_code(401); exit; }

/* ─── 1. DataTables request vars ────────────────────────────────── */
$draw      = (int)($_POST['draw']   ?? 0);
$start     = (int)($_POST['start']  ?? 0);
$rawLen    = (int)($_POST['length'] ?? 25);
$length    = $rawLen < 0 ? PHP_INT_MAX : max(1, $rawLen);

$searchVal = trim((string)($_POST['search']['value'] ?? ''));
// Collect multi-column ordering from DataTables
$ordersRaw = $_POST['order'] ?? [];
if (!is_array($ordersRaw)) { $ordersRaw = []; }

/* ─── 2. Column map ─────────────────────────────────────────────── */
$colMap = [
    // 0: dt-control (unsortable placeholder)
    0 => null,
    // 1..11 match DataTables column definitions in /vmi/js/vmi-js/table.js
    1 => 'clc.Client_name',     // client_name
    2 => 'ts.dipr_date',        // dipr_date
    3 => 'ts.dipr_time',        // dipr_time
    4 => 'cs.Site_name',        // site_name
    5 => 'ts.tank_id',          // tank_id
    6 => 'ps.product_name',     // product_name
    7 => 'ts.capacity',         // capacity (numeric)
    8 => 'ts.current_volume',   // current_volume (numeric)
    9 => 'COALESCE(status_rank, 999)',  // computed in SQL for status sorting, nulls sort last
    10 => 'ts.ullage',          // ullage (numeric)
    11 => 'ts.current_percent', // current_percent (numeric)
];

// Build ORDER BY clause supporting multiple columns
$orderParts = [];
foreach ($ordersRaw as $ord) {
    $idx = (int)($ord['column'] ?? 11);
    $dirRaw = strtolower((string)($ord['dir'] ?? 'desc'));
    $dir = in_array($dirRaw, ['asc','desc'], true) ? strtoupper($dirRaw) : 'ASC';
    $col = $colMap[$idx] ?? null;
    if ($col) {
        $orderParts[] = "$col $dir";
    }
}
if (!$orderParts) {
    $orderParts[] = ($colMap[11] ?? 'ts.current_percent') . ' DESC';
}
$orderClause = implode(', ', $orderParts);

/* ─── 3. Site-group filter ──────────────────────────────────────── */
$raw     = $_POST['site_ids'] ?? [];
$raw     = is_string($raw) ? explode(',', $raw) : $raw;
$siteIds = array_values(array_filter(array_map('intval', $raw)));
$idList  = implode(',', $siteIds);

/* ─── 4. Load & clean base SQL ──────────────────────────────────── */
$sql = rtrim(file_get_contents(__DIR__.'/sql/tanks.sql'), ";\r\n\t ");
if ($sql === '') { http_response_code(500); echo 'tanks.sql missing'; exit; }

$sql = str_replace('-- {{SITE_FILTER}}',
                   $siteIds ? "AND cs.Site_id IN ($idList)" : '',
                   $sql);
$sql = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', $sql);

/* ─── 5. Base bind array ────────────────────────────────────────── */
$baseBind = [
    ':cid'  => $cid,   // plain :cid (for good measure)
    ':cid1' => $cid,
    ':cid2' => $cid,
    ':cid3' => $cid,
];

/* ─── 6. Search clause & bind ───────────────────────────────────── */
$searchBind   = $baseBind;
$searchClause = '';
if ($searchVal !== '') {
    $searchBind[':searchParam'] = "%{$searchVal}%";
    $cols  = ['clc.Client_name','ts.Tank_name','ps.product_name','cs.Site_name'];
    $likes = implode(' OR ', array_map(fn($c)=>"$c LIKE :searchParam", $cols));
    $searchClause = " AND ($likes)";
}

/* ─── helper: trim extras & add missing :cid* ───────────────────── */
function execSQL(PDO $pdo, string $sql, array $bind, int $cid): PDOStatement
{
    preg_match_all('/:[a-zA-Z0-9_]+/', $sql, $m);
    $need = array_flip($m[0]);            // placeholders in the SQL

    $bind = array_intersect_key($bind, $need);   // drop extras

    foreach ($need as $ph => $_) {                // fill missing :cid*
        if (!isset($bind[$ph]) && str_starts_with($ph, ':cid')) {
            $bind[$ph] = $cid;
        }
    }

    // /* ★★★ one-off dump ★★★ */
    // echo "<pre>SQL expects:\n";   print_r(array_keys($need));
    // echo "Bind we send:\n";       print_r(array_keys($bind));
    // echo "</pre>";  exit;          // stop right here
    // /* ★★★★★★★★★★★★★★★★ */

    $st = $pdo->prepare($sql);
    $st->execute($bind);
    return $st;
}


/* ─── 7. recordsTotal (no search) ───────────────────────────────── */
$recordsTotal = (int) execSQL(
    $pdo,
    "SELECT COUNT(*) FROM ({$sql}) AS allRows",
    $baseBind,
    $cid
)->fetchColumn();

/* ─── 8. recordsFiltered (with search) ──────────────────────────── */
$sqlFilt = $sql . $searchClause;
$recordsFiltered = (int) execSQL(
    $pdo,
    "SELECT COUNT(*) FROM ({$sqlFilt}) AS filteredRows",
    $searchBind,
    $cid
)->fetchColumn();

/* ─── 9. Fetch page ─────────────────────────────────────────────── */
// $pagedSql = "
//     {$sqlFilt}
//     ORDER BY {$orderClause}
//     LIMIT :lim OFFSET :off
// ";
// $pageBind = $searchBind + [':lim'=>$length, ':off'=>$start];

// $stmt = execSQL($pdo, $pagedSql, $pageBind, $cid);
// $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($length === PHP_INT_MAX) {              // user chose “Show all”
    $pagedSql = "
        {$sqlFilt}
        ORDER BY {$orderClause}             -- no LIMIT/OFFSET
    ";
    $pageBind = $searchBind;                // nothing extra to bind
} else {
    $pagedSql = "
        {$sqlFilt}
        ORDER BY {$orderClause}
        LIMIT $length OFFSET $start         -- embed raw ints
    ";
    $pageBind = $searchBind;                // placeholders already trimmed
}

$stmt = execSQL($pdo, $pagedSql, $pageBind, $cid);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ─── 10. JSON back to DataTables ──────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
]);
