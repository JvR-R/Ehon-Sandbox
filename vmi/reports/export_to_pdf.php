<?php
include '../db/dbh2.php';
include '../db/log.php';
set_time_limit(300);

require '../../vendor/tecnickcom/tcpdf/tcpdf.php';
use TCPDF;

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Ehonenergy Tech');
$pdf->SetTitle('Transaction Report');
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 10);

$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

$sql = "SELECT cs.Client_id, ct.*, st.site_name FROM `client_transaction` as ct JOIN Console_Asociation as cs ON ct.uid = cs.uid JOIN Sites as st ON st.uid = cs.uid";
$conditions = array();

if (!empty($filters)) {
    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "st.Site_id IN ($sites)";
    }
    if (isset($filters['group']) && !empty($filters['group'])) {
        $conditions[] = "st.Site_id IN (SELECT site_no FROM ehonener_ehon_vmi.client_site_groups where group_id =" . $filters['group'] . ")";
    }
    if (isset($filters['cardholder']) && !empty($filters['cardholder'])) {
        $conditions[] = "ct.card_holder_name LIKE '%{$filters['cardholder']}%'";
    }
    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "cs.Client_id = $companyFilter";
    }
    if (isset($filters['cardnumber']) && !empty($filters['cardnumber'])) {
        $conditions[] = "ct.card_number = {$filters['cardnumber']}";
    }
    if (isset($filters['registration']) && !empty($filters['registration'])) {
        $conditions[] = "ct.registration LIKE '%{$filters['registration']}%'";
    }
    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $conditions[] = "ct.transaction_date >= '{$filters['startDate']}'";
    }
    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $conditions[] = "ct.transaction_date <= '{$filters['endDate']}'";
    }
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY transaction_date DESC, transaction_time DESC";

$header = [
    'Transaction ID' => 'transaction_id', 
    'Date' => 'transaction_date',
    'Time' => 'transaction_time', 
    'Console ID' => 'uid',
    'Site Name' => 'site_name', 
    'Tank Number' => 'tank_id', 
    'Card Number' => 'card_number', 
    'Card Holder Name' => 'card_holder_name', 
    'Odometer' => 'odometer', 
    'Registration' => 'registration', 
    'Volume' => 'dispensed_volume'
];

$batchSize = 1000;
$offset = 0;

do {
    $batchQuery = $sql . " LIMIT $offset, $batchSize;";
    $result = $conn->query($batchQuery);

    if ($result->num_rows > 0) {
        $data = '';
        while ($row = $result->fetch_assoc()) {
            $data .= '<tr>';
            foreach ($header as $key => $column) {
                $data .= '<td style="border: 1px solid #4d5256; padding: 5px;">' . $row[$column] . '</td>';
            }
            $data .= '</tr>';
        }

        $html = '<table style="border-collapse: collapse; width: 100%;">';
        $html .= '<thead><tr style="background-color: #002e60; color: #ffffff;">';
        foreach ($header as $key => $value) {
            $html .= '<th style="border: 1px solid #4d5256; padding: 5px;">' . $key . '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>' . $data . '</tbody></table>';

        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $offset += $batchSize;
} while ($result->num_rows > 0);

// Set headers to force download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="transactions.pdf"');
header('Cache-Control: max-age=0');

// Output the PDF document
$pdf->Output('transactions.pdf', 'D');
exit;
?>
