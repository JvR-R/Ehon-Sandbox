<?php
require __DIR__ . '/../db/dbh2.php';
require __DIR__ . '/../db/log.php';
require __DIR__ . '/../db/crc.php';
require __DIR__ . '/../db/acl.php';
header('Content-Type: application/json; charset=UTF-8');


// ──────────────────────────────────────────────────────────────────
// 0 ─ Accept JSON or classic form posts
// ──────────────────────────────────────────────────────────────────
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0) {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['error'=>'Bad JSON']);
        exit;
    }
    $_POST = $body;
}

// ──────────────────────────────────────────────────────────────────
// 1 ─ Whitelist & canonicalize inputs
// ──────────────────────────────────────────────────────────────────
$allowedCases = [1,2,3];
$rawCase      = $_POST['case'] ?? null;
$case         = filter_var($rawCase, FILTER_VALIDATE_INT);

if ($case === false || ! in_array($case, $allowedCases, true)) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid “case”']);
    exit;
}

$rawSiteId = $_POST['site_id'] ?? null;
$site_id   = filter_var($rawSiteId, FILTER_VALIDATE_INT);
if ($site_id === false || $site_id < 1) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid “site_id”']);
    exit;
}

/*───────────────────────────────────────────────────────────────────
 * 2 ─ Helper getters
 *──────────────────────────────────────────────────────────────────*/
function g (string $k, $d = '') { return $_POST[$k] ?? $d; }
function gi(string $k, $d = 0)  { return intval(g($k, $d)); }
function gs(string $k, $d = '') { return strval(g($k, $d)); }

/*───────────────────────────────────────────────────────────────────
 * 3 ─ Route by case
 *──────────────────────────────────────────────────────────────────*/
$case    = gi('case');
$uid     = gi('uid');
$site_id = gi('site_id');

$response = ['case' => $case];

/*------------------------------------------------------------------
 * CASE 1  —  phone / e-mail + level alert
 *-----------------------------------------------------------------*/
if ($case === 1) {

    $vol_alert  = gi('vol_alert');
    $alert_type = gi('alert_type');
    $tank_no    = gi('tank_no');
    $phone      = gs('phone');
    $email      = gs('email');

    /* Sites */
    $stmt = $conn->prepare(
        'UPDATE Sites SET phone=?, Email=? WHERE uid=? AND Site_id=?');
    $stmt->bind_param('ssii', $phone, $email, $uid, $site_id);
    $response['sites'] = $stmt->execute() ? 'OK' : $conn->error;
    $stmt->close();

    /* Tanks */
    $stmt = $conn->prepare(
        'UPDATE Tanks SET level_alert=?, alert_type=?
         WHERE uid=? AND tank_id=? AND Site_id=?');
    $stmt->bind_param('iisii',
        $vol_alert, $alert_type, $uid, $tank_no, $site_id);
    $response['tanks'] = $stmt->execute() ? 'OK' : $conn->error;
    $stmt->close();
}

/*------------------------------------------------------------------
 * CASE 2  —  full configuration  (tank + TG + FMS + relay box)
 *-----------------------------------------------------------------*/
elseif ($case === 2) {

    /* grab scalars with correct types */
    $product_id     = gi('product_name');      // actually product_id
    $capacity       = gi('capacity');
    $tank_no        = gi('tank_no');
    $tank_name      = gs('tank_name');
    $tank_number    = gi('tank_number');

    $tg_port        = gs('tg_port');           // may be "1_1" / "1_2"
    $tg_type        = gi('tg_type');
    $tg_id          = gi('tg_id');
    $tg_offset      = gi('tg_offset');
    $chart_id       = gi('chart_id');

    $relay_port     = gi('relaybox_port');
    $relay_type     = gi('relaybox_type');

    /* normalise special TG “1_1 / 1_2” */
    if ($tg_port === '1_1') { $tg_port = 11; $tg_id = 1; }
    if ($tg_port === '1_2') { $tg_port = 12; $tg_id = 2; }

    /* FMS array → pad to 3, build CSVs */
    $fms  = g('fms_data', []);
    $ids  = $types = $uarts = [];
    foreach ($fms as $row) {
        $ids[]   = intval($row['fms_id'  ] ?? 0);
        $types[] = intval($row['fms_type'] ?? 0);
        $uarts[] = intval($row['fms_port'] ?? 0);
    }
    $ids   = array_pad($ids  , 3, 0);
    $types = array_pad($types, 3, 0);
    $uarts = array_pad($uarts, 3, 0);
    $fms_id_str   = implode(',', $ids);
    $fms_type_str = implode(',', $types);
    $fms_uart_str = implode(',', $uarts);
    $fms_number   = count(array_filter($ids));

    /* duplicate-port checking (kept as-is) */
    $sqlsel = 'SELECT tank_gauge_uart AS tgp, fms_uart AS fmsp,
                      tank_gauge_type AS tgtype, tank_gauge_id AS tgid
               FROM Tanks
               WHERE uid=? AND tank_id!=? AND tank_gauge_type!=205';
    $stmtsel = $conn->prepare($sqlsel);
    $stmtsel->bind_param('ii', $uid, $tank_no);
    $stmtsel->execute();
    $stmtsel->bind_result($tgp, $fmsp, $tgtype, $tgid);

    while ($stmtsel->fetch()) {
        if ($tgp == $tg_port && $tg_port != 0) {
            if ($tg_port == 1 && $tg_id != $tgid) continue;
            if (($tgtype == 202 && $tg_type == 202) ||
                ($tgtype == 203 && $tg_type == 203)) {
                if ($tgid != $tg_id) continue;
            }
            $response['idduplicate'] = 1;
            $tg_port = $tg_type = 0; $tg_id = 1;
        }
    }
    $stmtsel->close();

    /* Tanks update */
    $sql = 'UPDATE Tanks SET
              product_id=?, capacity=?, tank_id=?, tank_name=?,
              tank_gauge_uart=?, tank_gauge_type=?, tank_gauge_id=?,
              chart_id=?, fms_type=?, fms_uart=?, fms_id=?,
              offset_tank=?, relay_uart=?, relay_type=?, fms_number=?
            WHERE uid=? AND tank_id=? AND Site_id=?';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'iiisiiiisssiiiiiii',
        $product_id, $capacity, $tank_number, $tank_name,
        $tg_port, $tg_type, $tg_id, $chart_id,
        $fms_type_str, $fms_uart_str, $fms_id_str,
        $tg_offset, $relay_port, $relay_type,
        $fms_number, $uid, $tank_no, $site_id);

    if ($stmt->execute()) {
        $response['config'] = 'OK';
        tanks_crcdata($uid);
        $flag = $conn->prepare('UPDATE console SET cfg_flag=1 WHERE uid=?');
        $flag->bind_param('i', $uid); $flag->execute(); $flag->close();
    } else {
        $response['config'] = $conn->error;
    }
    $stmt->close();
}

/*------------------------------------------------------------------
 * CASE 3  —  alarm thresholds & relay mapping
 *-----------------------------------------------------------------*/
elseif ($case === 3) {

    $client_id = gi('client_id');
    $tank_no   = gi('tank_no');
    $capacity  = gi('capacity');

    $higha   = gi('higha',  $capacity);
    $lowa    = gi('lowa');
    $chigha  = gi('chigha', $capacity);
    $clowa   = gi('clowa');

    // Server-side validation: reject out-of-range values
    $cap = max(0, (int)$capacity);
    if ($cap > 0 && ($higha > $cap || $chigha > $cap)) {
        http_response_code(422);
        echo json_encode(['error' => "High and Critical High cannot exceed capacity ($cap)"]);
        exit;
    }
    if ($lowa < 0 || $clowa < 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Low and Critical Low cannot be below 0']);
        exit;
    }

    $r_hh = gi('relay_hh');
    $r_h  = gi('relay_h');
    $r_l  = gi('relay_l');
    $r_ll = gi('relay_ll');

    // Build relay columns dynamically: only 1..4 are valid. Skip 0/empty.
    $relayValsIn = [$r_hh, $r_h, $r_l, $r_ll];
    $seen = [];
    $relayCols = [];
    $relayVals = [];
    foreach ($relayValsIn as $rv) {
        if ($rv >= 1 && $rv <= 4) {
            $col = 'relay' . $rv;
            if (!isset($seen[$col])) { // avoid duplicate columns
                $seen[$col] = true;
                $relayCols[] = $col;
                // store the selected relay number as before (legacy behavior)
                $relayVals[] = $rv;
            }
        }
    }

    // Base columns & values
    $insCols = 'client_id, high_alarm, low_alarm, crithigh_alarm, critlow_alarm, tank_id, uid, Site_id';
    $place   = '?,?,?,?,?,?,?,?';
    $bindT   = 'iiiiiiii';
    $bindV   = [$client_id, $higha, $lowa, $chigha, $clowa, $tank_no, $uid, $site_id];

    // Append relay columns if any valid were provided
    if (!empty($relayCols)) {
        $insCols .= ', ' . implode(', ', $relayCols);
        $place   .= str_repeat(',?', count($relayCols));
        $bindT   .= str_repeat('i', count($relayCols));
        foreach ($relayVals as $v) { $bindV[] = $v; }
    }

    $updates = 'high_alarm=VALUES(high_alarm), low_alarm=VALUES(low_alarm), '
             . 'crithigh_alarm=VALUES(crithigh_alarm), critlow_alarm=VALUES(critlow_alarm), '
             . 'client_id=VALUES(client_id)';
    if (!empty($relayCols)) {
        foreach ($relayCols as $c) { $updates .= ", $c=VALUES($c)"; }
    }

    $sql = "INSERT INTO alarms_config ($insCols) VALUES ($place) ON DUPLICATE KEY UPDATE $updates";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $response['alarms'] = $conn->error;
    } else {
        // bind_param requires references; build dynamically
        $bindParams = [];
        $bindParams[] = & $bindT;
        foreach ($bindV as $k => $v) { $bindParams[] = & $bindV[$k]; }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $response['alarms'] = $stmt->execute() ? 'OK' : $conn->error;
        $stmt->close();
    }

    if ($response['alarms'] === 'OK') {
        $flag = $conn->prepare('UPDATE console SET cfg_flag=1 WHERE uid=?');
        $flag->bind_param('i', $uid); $flag->execute(); $flag->close();
    }
}

/*------------------------------------------------------------------*/
else {
    $response['error'] = 'Unknown or missing "case"';
}

/*──────────────────────────────────────────────────────────────────*/
echo json_encode($response);

?>