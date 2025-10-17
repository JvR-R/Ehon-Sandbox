<?php
/******************************************************
 *  Full “copy-paste” version – generates a PDF report
 *  with company-specific logo, date range and total
 *  dispensed volume banner, followed by the paginated
 *  transaction table.
 ******************************************************/

// session_start();                           // so $companyId exists
include('../../db/dbh2.php');
include('../../db/log.php');

// Initialize companyId from session
$companyId = $_SESSION['companyId'] ?? 0;
set_time_limit(300);

require '../../../vendor/tecnickcom/tcpdf/tcpdf.php';
use TCPDF;

$companyId = $_SESSION['companyId'] ?? 0; // fallback if not set

// ─────────────────── TCPDF SETUP ────────────────────
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Ehonenergy Tech');
$pdf->SetTitle('Transaction Report');
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetFont('helvetica', '', 10);

// ────────────────── FILTER HANDLING ──────────────────
$filters    = $_GET['filters'] ?? array();
$conditions = array();

if (!empty($filters['sites'])) {
    $sites = implode(',', array_map('intval', $filters['sites']));
    $conditions[] = "st.Site_id IN ($sites)";
}
if (!empty($filters['group'])) {
    $conditions[] = "st.Site_id IN (SELECT site_no
                                     FROM   ehonener_ehon_vmi.client_site_groups
                                     WHERE  group_id = " . intval($filters['group']) . ")";
}
if (!empty($filters['cardholder'])) {
    $ch   = $conn->real_escape_string($filters['cardholder']);
    $conditions[] = "ct.card_holder_name LIKE '%$ch%'";
}
if (!empty($filters['company'])) {
    $conditions[] = "cs.Client_id = " . intval($filters['company']);
} else if ($companyId && $companyId != 15100) {          // 15100 = super-admin?
    $conditions[] = "cs.Client_id = $companyId";
}

if (!empty($filters['cardnumber'])) {
    $conditions[] = "ct.card_number = " . intval($filters['cardnumber']);
}
if (!empty($filters['registration'])) {
    $reg = $conn->real_escape_string($filters['registration']);
    $conditions[] = "ct.registration LIKE '%$reg%'";
}
if (!empty($filters['startDate'])) {
    $conditions[] = "ct.transaction_date >= '" . $conn->real_escape_string($filters['startDate']) . "'";
}
if (!empty($filters['endDate'])) {
    $conditions[] = "ct.transaction_date <= '" . $conn->real_escape_string($filters['endDate']) . "'";
}

// ─────────────── BASE QUERY (incl. dist_id) ───────────────
$baseSql = "
    SELECT  cs.Client_id,
            cs.dist_id,
            ct.*,
            st.site_name
    FROM    client_transaction ct
    JOIN    Console_Asociation cs ON ct.uid = cs.uid
    JOIN    Sites               st ON st.uid = cs.uid";

$whereSql = !empty($conditions)
    ? ' WHERE ' . implode(' AND ', $conditions)
    : " WHERE cs.Client_id = $companyId";

$fullSql = $baseSql . $whereSql;

// ───────────────── TOTAL VOLUME ──────────────────
$sumSql = "
    SELECT SUM(ct.dispensed_volume) AS total_vol
    FROM   client_transaction ct
    JOIN   Console_Asociation cs ON ct.uid = cs.uid
    JOIN   Sites               st ON st.uid = cs.uid" . $whereSql;

$totalVolume = 0.00;
if ($sumRes = $conn->query($sumSql)) {
    $totalVolume = number_format($sumRes->fetch_assoc()['total_vol'] ?? 0, 2);
}

// ────────────────── COMPANY LOGO ──────────────────
$logoPath = '/home/ehonener/public_html/vmi/images/default_logo.png';
if ($dRes = $conn->query($fullSql . ' LIMIT 1')) {
    if ($dRow = $dRes->fetch_assoc()) {
        $distLogo = '/home/ehonener/public_html/vmi/images/company_' . intval($dRow['dist_id']) . '.png';
        if (file_exists($distLogo)) {
            $logoPath = $distLogo;
        }
    }
}

// ───────────────── HEADER BLOCK – FIRST PAGE ─────────────────
$startDate = $filters['startDate'] ?? '--';
$endDate   = $filters['endDate']   ?? '--';

$pdf->AddPage();
$banner = <<<HTML
<table cellpadding="0" cellspacing="0" width="100%">
  <tr>
    <td width="25%"><img src="$logoPath" height="40"></td>
    <td width="50%" align="center" style="font-size:12px;">
        Date from <b>$startDate</b> to <b>$endDate</b>
    </td>
    <td width="25%" align="right" style="font-size:12px;">
        Total amount dispensed<br><b>$totalVolume&nbsp;L</b>
    </td>
  </tr>
</table>
<hr>
HTML;
$pdf->writeHTML($banner, true, false, true, false, '');

// ───────────────── TABLE COLUMN MAP ─────────────────
$columns = [
    'Transaction ID'   => 'transaction_id',
    'Date'             => 'transaction_date',
    'Time'             => 'transaction_time',
    'Console ID'       => 'uid',
    'Site Name'        => 'site_name',
    'Tank Number'      => 'tank_id',
    'Card Number'      => 'card_number',
    'Card Holder Name' => 'card_holder_name',
    'Odometer'         => 'odometer',
    'Registration'     => 'registration',
    'Volume'           => 'dispensed_volume'
];

// ────────────────── PAGINATED OUTPUT ─────────────────
$batchSize  = 1000;
$offset     = 0;
$firstBatch = true;

do {
    $batchQuery = $fullSql . "
        ORDER BY ct.transaction_date DESC, ct.transaction_time DESC
        LIMIT $offset, $batchSize";

    $result = $conn->query($batchQuery);

    if ($result && $result->num_rows > 0) {

        if (!$firstBatch) {
            $pdf->AddPage();
        }
        $firstBatch = false;

        // rows
        $rows = '';
        while ($r = $result->fetch_assoc()) {
            $rows .= '<tr>';
            foreach ($columns as $field) {
                $rows .= '<td style="border:1px solid #4d5256;padding:5px;">'
                       . htmlspecialchars($r[$field] ?? '')
                       . '</td>';
            }
            $rows .= '</tr>';
        }

        // whole table
        $html  = '<table style="border-collapse:collapse;width:100%;">';
        $html .= '<thead><tr style="background-color:#002e60;color:#fff;">';
        foreach ($columns as $title => $field) {
            $html .= '<th style="border:1px solid #4d5256;padding:5px;">' . $title . '</th>';
        }
        $html .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $offset += $batchSize;
} while ($result && $result->num_rows > 0);

// ───────────────────── OUTPUT ──────────────────────
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="transactions.pdf"');
header('Cache-Control: max-age=0');

$pdf->Output('transactions.pdf', 'D');
exit;
?>
