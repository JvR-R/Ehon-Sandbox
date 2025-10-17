<?php
// Cron: build weekly/monthly transaction Excel and email to configured recipients
// Uses schedule from `transaction_reportcron` and transactions from `client_transaction`

// 1) Bootstrap
include('../db/dbh2.php');           // $conn (mysqli) + timezone
include('../db/email_conf.php');     // send_email_with_attachment

// Be quiet in cron, but log errors
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Composer autoload (PhpSpreadsheet)
require_once '/home/ehon/public_html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 2) Helpers
function mapWeekdayToToken(string $phpWeekday): string {
    // PHP date('D'): Mon, Tue, Wed, Thu, Fri, Sat, Sun
    $map = [
        'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed',
        'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'
    ];
    return $map[$phpWeekday] ?? strtolower($phpWeekday);
}

function computeWeeklyRange(): array {
    // Previous full week (Mon..Sun) in server timezone
    $start = new DateTime('monday last week');
    $end   = new DateTime('sunday last week');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function computeMonthlyRange(?int $monthDay = null): array {
    // If monthDay is 15, use custom mid-month cycle: 15 prev month .. 14 current month
    if ($monthDay === 15) {
        $prevMonthStart = new DateTime('first day of previous month');
        $currentMonthStart = new DateTime('first day of this month');

        $start = DateTime::createFromFormat('Y-m-d H:i:s', $prevMonthStart->format('Y-m') . '-15 00:00:00');
        // End is 14th of current month
        $end   = DateTime::createFromFormat('Y-m-d H:i:s', $currentMonthStart->format('Y-m') . '-14 23:59:59');
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    // Default: previous full calendar month
    $start = new DateTime('first day of previous month 00:00');
    $end   = new DateTime('last day of previous month 23:59:59');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function explodeEmails(string $raw): array {
    $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $valid = [];
    foreach ($parts as $e) {
        $e = trim($e);
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $valid[] = $e;
        }
    }
    return array_values(array_unique($valid));
}

function getClientName(mysqli $conn, int $clientId): string {
    $name = '';
    $stmt = $conn->prepare('SELECT Client_name FROM clients WHERE client_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $clientId);
        if ($stmt->execute()) {
            $stmt->bind_result($tmp);
            if ($stmt->fetch()) { $name = (string)$tmp; }
        }
        $stmt->close();
    }
    return $name !== '' ? $name : (string)$clientId;
}

function getGroupName(mysqli $conn, int $clientId, int $groupId): ?string {
    $name = null;
    $stmt = $conn->prepare('SELECT group_name FROM site_groups WHERE client_id = ? AND group_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $clientId, $groupId);
        if ($stmt->execute()) {
            $stmt->bind_result($tmp);
            if ($stmt->fetch()) { $name = (string)$tmp; }
        }
        $stmt->close();
    }
    return $name;
}

function buildExcel(mysqli $conn, int $clientId, ?int $groupId, string $startDate, string $endDate): string {
    // Base WHERE + params (date range first)
    $sql = "
        SELECT
            ct.transaction_id,
            ct.transaction_date,
            ct.transaction_time,
            st.site_name,
            COALESCE(ct.fms_id, ct.uid) AS fms_id,
            ct.tank_id,
            ct.card_number,
            ct.card_holder_name,
            ct.odometer,
            ct.registration,
            ct.dispensed_volume
        FROM client_transaction ct
        JOIN Sites st   ON st.uid = ct.uid
        JOIN console c  ON c.uid  = ct.uid
        WHERE c.device_type != 999
          AND ct.transaction_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    $types  = 'ss';

    // Restrict by client's association unless admin (15100)
    if ($clientId !== 15100) {
        $sql    .= ' AND ct.uid IN (SELECT uid FROM Console_Asociation WHERE client_id = ? OR reseller_id = ? OR dist_id = ?)';
        $params[] = $clientId;
        $params[] = $clientId;
        $params[] = $clientId;
        $types   .= 'iii';
    }

    // Optional group constraint (scoped to client)
    if ($groupId !== null && $groupId > 0) {
        $sql    .= ' AND st.Site_id IN (SELECT site_no FROM client_site_groups WHERE client_id = ? AND group_id = ?)';
        $params[] = $clientId;
        $params[] = $groupId;
        $types   .= 'ii';
    }

    $sql .= ' ORDER BY ct.transaction_date ASC, ct.transaction_time ASC, ct.transaction_id ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    // Build spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $headers = [
        'A1' => 'Transaction ID',
        'B1' => 'Date',
        'C1' => 'Time',
        'D1' => 'Site Name',
        'E1' => 'FMS ID',
        'F1' => 'Tank Number',
        'G1' => 'Card Number',
        'H1' => 'Card Holder Name',
        'I1' => 'Odometer',
        'J1' => 'Registration',
        'K1' => 'Volume (L)'
    ];
    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // Style header
    $sheet->getStyle('A1:K1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                   ->getStartColor()->setARGB('FF003366');

    // Rows
    $r = 2;
    while ($row = $res->fetch_assoc()) {
        $sheet->setCellValue('A' . $r, $row['transaction_id']);
        $sheet->setCellValue('B' . $r, $row['transaction_date']);
        $sheet->setCellValue('C' . $r, $row['transaction_time']);
        $sheet->setCellValue('D' . $r, $row['site_name']);
        $sheet->setCellValue('E' . $r, $row['fms_id']);
        $sheet->setCellValue('F' . $r, $row['tank_id']);
        $sheet->setCellValue('G' . $r, $row['card_number']);
        $sheet->setCellValue('H' . $r, $row['card_holder_name']);
        $sheet->setCellValue('I' . $r, $row['odometer']);
        $sheet->setCellValue('J' . $r, $row['registration']);
        $sheet->setCellValue('K' . $r, $row['dispensed_volume']);
        $r++;
    }
    $stmt->close();

    // Borders and autosize
    $sheet->getStyle('A1:K' . ($r - 1))
          ->getBorders()->getAllBorders()
          ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
          ->getColor()->setARGB('FF000000');
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Temp file
    $tmp = tempnam(sys_get_temp_dir(), 'txr_');
    $writer = new Xlsx($spreadsheet);
    $writer->save($tmp);
    return $tmp;
}

// 3) Inspect schedule table columns
$hasScheduleType = false;
$hasMonthDay = false;
if ($res = $conn->query("SHOW COLUMNS FROM transaction_reportcron LIKE 'schedule_type'")) {
    $hasScheduleType = ($res->num_rows > 0);
    $res->close();
}
if ($res = $conn->query("SHOW COLUMNS FROM transaction_reportcron LIKE 'month_day'")) {
    $hasMonthDay = ($res->num_rows > 0);
    $res->close();
}

// 4) Load schedule rows
$selectSql = $hasScheduleType || $hasMonthDay
    ? 'SELECT client_id, user_id, group_id, email_list, weekday, schedule_type, month_day FROM transaction_reportcron'
    : 'SELECT client_id, user_id, group_id, email_list, weekday FROM transaction_reportcron';

$result = $conn->query($selectSql);
if (!$result) {
    // nothing we can do
    exit;
}

$todayToken = mapWeekdayToToken(date('D')); // 'mon'..'sun'
$todayDom   = (int)date('j');               // 1..31

while ($row = $result->fetch_assoc()) {
    $clientId = (int)$row['client_id'];
    $groupId  = isset($row['group_id']) ? (int)$row['group_id'] : 0;
    $emails   = isset($row['email_list']) ? explodeEmails($row['email_list']) : [];
    $weekday  = isset($row['weekday']) ? strtolower(trim((string)$row['weekday'])) : '';
    $stype    = $hasScheduleType ? strtolower(trim((string)($row['schedule_type'] ?? 'weekly'))) : 'weekly';
    $mday     = $hasMonthDay ? (isset($row['month_day']) ? (int)$row['month_day'] : null) : null;

    if (count($emails) === 0) { continue; }

    $shouldRun = false;
    if ($stype === 'monthly') {
        if ($mday !== null && $mday === $todayDom) {
            $shouldRun = true;
        }
    } else { // weekly (default)
        if ($weekday !== '' && $weekday === $todayToken) {
            $shouldRun = true;
        }
    }
    if (!$shouldRun) { continue; }

    // Compute period
    if ($stype === 'monthly') {
        [$startDate, $endDate] = computeMonthlyRange($mday);
    } else {
        [$startDate, $endDate] = computeWeeklyRange();
    }
    $clientName = getClientName($conn, $clientId);

    // Build excel
    try {
        $tmpFile = buildExcel($conn, $clientId, $groupId > 0 ? $groupId : null, $startDate, $endDate);
    } catch (Throwable $e) {
        // Log and continue to next row
        error_log('TXR excel generation failed for client ' . $clientId . ': ' . $e->getMessage());
        continue;
    }

    // Email each recipient separately
    $periodStr = $startDate . ' to ' . $endDate;
    $subject = 'EHON VMI Transactions Report — ' . $clientName . ' — ' . $periodStr;
    $groupLabel = 'All';
    if ($groupId > 0) {
        $maybeName = getGroupName($conn, $clientId, (int)$groupId);
        $groupLabel = $maybeName !== null && $maybeName !== '' ? $maybeName : ('#' . (int)$groupId);
    }
    $body    = '<p>Hello,</p>' .
               '<p>Attached is your transactions report for <strong>' . htmlspecialchars($periodStr, ENT_QUOTES, 'UTF-8') . '</strong>.</p>' .
               '<p>Client: ' . htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') . '</p>' .
               '<p>Group: ' . htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') . '</p>' .
               '<p>Regards,<br>EHON VMI</p>';

    $attachName = 'transactions_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate) . '.xlsx';

    foreach ($emails as $email) {
        $resSend = send_email_with_attachment($email, $subject, $body, $tmpFile, $attachName);
        if (($resSend['status'] ?? 'error') !== 'success') {
            error_log('TXR email failed to ' . $email . ': ' . ($resSend['message'] ?? 'unknown error'));
        }
    }

    // Cleanup temp file
    if (is_file($tmpFile)) { @unlink($tmpFile); }
}

$result->close();
exit;
?>


