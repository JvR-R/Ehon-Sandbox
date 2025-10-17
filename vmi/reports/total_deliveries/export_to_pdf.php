<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include files using defined paths
include(ROOT_PATH . '/db/dbh2.php');
include(ROOT_PATH . '/db/log.php');

// Increase memory limit if needed
ini_set('memory_limit', '256M');

// Require the Composer autoloader for TCPDF
require '../../../vendor/tecnickcom/tcpdf/tcpdf.php';

use TCPDF;

// Ensure $companyId is set (for example, via session)
// If you already set it in your db include then remove or adjust this block.
if (!isset($companyId)) {
    $companyId = isset($_SESSION['companyId']) ? intval($_SESSION['companyId']) : 0;
}

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Build the base SQL query
$sql = "SELECT dh.uid, transaction_date, transaction_time, site_name, tank_id, delivery, current_volume 
        FROM delivery_historic dh JOIN Console_Asociation ca on ca.uid = dh.uid";

// Build an array for any filtering conditions
$conditions = [];

// Apply filters if provided
if (!empty($filters)) {
    if (isset($filters['sites']) && !empty($filters['sites'])) {
        // Expecting an array of site IDs
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "Site_id IN ($sites)";
    }
    if (isset($filters['group']) && !empty($filters['group'])) {
        $group = intval($filters['group']);
        // Adjust schema/table references if needed
        $conditions[] = "Site_id IN (SELECT site_no FROM ehonener_ehon_vmi.client_site_groups WHERE group_id = $group)";
    }
    // For a special case if $companyId is 15100
    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "Client_id = $companyFilter";
    }
    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $startDate = $conn->real_escape_string($filters['startDate']);
        $conditions[] = "transaction_date >= '$startDate'";
    }
    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $endDate = $conn->real_escape_string($filters['endDate']);
        $conditions[] = "transaction_date <= '$endDate'";
    }
}

// If conditions exist, append them
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// If $companyId is not 15100, add additional restrictions
if ($companyId != 15100) {
    // Append the company restrictions
    $sql .= (strpos($sql, 'WHERE') !== false ? " AND" : " WHERE") . " (Client_id = $companyId OR reseller_id = $companyId)";
}

// Order the results
$sql .= " ORDER BY transaction_date DESC, transaction_time DESC;";

// Execute the query
$result = $conn->query($sql);

// Prepare an HTML table (with similar styling to your Excel header)
$headerLabels = [
    'UID'              => 'uid',
    'Transaction Date' => 'transaction_date',
    'Transaction Time' => 'transaction_time',
    'Site Name'        => 'site_name',
    'Tank ID'          => 'tank_id',
    'Delivery'         => 'delivery',
    'Current Volume'   => 'current_volume'
];

// Build HTML table header
$html = '<table style="border-collapse: collapse; width: 100%;">';
$html .= '<thead>';
$html .= '<tr style="background-color: #002e60; color: #ffffff;">';
foreach ($headerLabels as $label => $field) {
    $html .= '<th style="border: 1px solid #4d5256; padding: 5px;">' . $label . '</th>';
}
$html .= '</tr></thead>';
$html .= '<tbody>';

// Build rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>';
        foreach ($headerLabels as $field) {
            $cellValue = isset($row[$field]) ? $row[$field] : '';
            $html .= '<td style="border: 1px solid #4d5256; padding: 5px;">' . $cellValue . '</td>';
        }
        $html .= '</tr>';
    }
} else {
    // Optionally handle no results found
    $html .= '<tr><td colspan="' . count($headerLabels) . '" style="border: 1px solid #4d5256; padding: 5px;">No records found.</td></tr>';
}

$html .= '</tbody>';
$html .= '</table>';

// Initialize TCPDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Ehonenergy Tech');
$pdf->SetAuthor('Ehonenergy Tech');
$pdf->SetTitle('Delivery Historic Report');
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetFont('helvetica', '', 10);

// Add a page and output the HTML
$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');

// Set headers to force download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Deliveries.pdf"');
header('Cache-Control: max-age=0');

// Output the PDF
$pdf->Output('deliveries.pdf', 'D');
exit;
