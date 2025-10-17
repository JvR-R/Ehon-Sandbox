<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include files using defined paths
include(ROOT_PATH . '/db/dbh2.php');
include(ROOT_PATH . '/db/log.php');

// Increase memory limit if needed
ini_set('memory_limit', '256M');

// Require the Composer autoloader
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ensure $companyId is set (for example, via session)
// If you already set it in your db include then remove this block.
if (!isset($companyId)) {
    $companyId = isset($_SESSION['companyId']) ? intval($_SESSION['companyId']) : 0;
}

// Get filters from the request (passed as GET parameter)
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
        // Note: adjust the schema/table name as needed
        $conditions[] = "Site_id IN (SELECT site_no FROM ehonener_ehon_vmi.client_site_groups WHERE group_id = $group)";
    }
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

// If conditions exist, add them to the query
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// If $companyId is not 15100 then add additional restrictions
if ($companyId != 15100) {
    // Append the company restrictions – if there’s already a WHERE clause, use AND; if not, start one.
    $sql .= (strpos($sql, 'WHERE') !== false ? " AND" : " WHERE") . " (Client_id = $companyId OR reseller_id = $companyId)";
}

// Append the ORDER BY clause
$sql .= " ORDER BY transaction_date DESC, transaction_time DESC;";

// Execute the query
$result = $conn->query($sql);

// Define header and border styles for the Excel file
$headerStyle = [
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => [
            'argb' => 'FF0000FF', // Blue background
        ],
    ],
    'font' => [
        'bold' => true,
        'color' => [
            'argb' => 'FFFFFFFF', // White font
        ],
    ],
];
$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];

// Create a new Spreadsheet object and get the active sheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Add headers to the first row
$sheet->setCellValue('A1', 'UID');
$sheet->setCellValue('B1', 'Transaction Date');
$sheet->setCellValue('C1', 'Transaction Time');
$sheet->setCellValue('D1', 'Site Name');
$sheet->setCellValue('E1', 'Tank ID');
$sheet->setCellValue('F1', 'Delivery');
$sheet->setCellValue('G1', 'Current Volume');


// Apply header style
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// Populate the sheet with query results
$row = 2;
if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $r['uid']);
        $sheet->setCellValue('B' . $row, $r['transaction_date']);
        $sheet->setCellValue('C' . $row, $r['transaction_time']);
        $sheet->setCellValue('D' . $row, $r['site_name']);
        $sheet->setCellValue('E' . $row, $r['tank_id']);
        $sheet->setCellValue('F' . $row, $r['delivery']);
        $sheet->setCellValue('G' . $row, $r['current_volume']);
        $row++;
    }
}

// Apply borders to the entire data area
$sheet->getStyle('A1:G' . $row)->applyFromArray($borderStyle);

// Auto-size columns A through L
foreach (range('A', 'G') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Create a temporary file for the Excel file
$temp_file = tempnam(sys_get_temp_dir(), 'excel_');

// Write the spreadsheet to the temporary file
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

// Output the file for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Deliveries.xlsx"');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);

// Delete the temporary file and exit
unlink($temp_file);
exit;
?>
