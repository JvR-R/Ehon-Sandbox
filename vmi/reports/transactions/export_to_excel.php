<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
include(db);
include(LOG_PATH);

ini_set('memory_limit', '256M');
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters - using proper escaping
$sql = "SELECT cs.Client_id, ct.*, st.site_name, ss.description 
        FROM `client_transaction` as ct 
        JOIN Console_Asociation as cs ON ct.uid = cs.uid 
        JOIN Sites as st ON st.uid = cs.uid
        JOIN stop_methods ss ON ct.stop_method = ss.id";

$totalVolume = 0;

// Apply filters with proper escaping
$conditions = array();

if (!empty($filters)) {
    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "st.Site_id IN ($sites)";
    }
    if (isset($filters['group']) && !empty($filters['group'])) {
        $groupId = intval($filters['group']);
        $conditions[] = "st.Site_id IN (SELECT site_no FROM client_site_groups WHERE group_id = $groupId)";
    }
    if (isset($filters['cardholder']) && !empty($filters['cardholder'])) {
        $cardholder = $conn->real_escape_string($filters['cardholder']);
        $conditions[] = "ct.card_holder_name LIKE '%$cardholder%'";
    }
    if (isset($filters['cardnumber']) && !empty($filters['cardnumber'])) {
        $cardnumber = $conn->real_escape_string($filters['cardnumber']);
        $conditions[] = "ct.card_number = '$cardnumber'";
    }
    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "cs.Client_id = $companyFilter";
    }
    if (isset($filters['registration']) && !empty($filters['registration'])) {
        $registration = $conn->real_escape_string($filters['registration']);
        $conditions[] = "ct.registration LIKE '%$registration%'";
    }
    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $startDate = $conn->real_escape_string($filters['startDate']);
        $conditions[] = "ct.transaction_date >= '$startDate'";
    }
    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $endDate = $conn->real_escape_string($filters['endDate']);
        $conditions[] = "ct.transaction_date <= '$endDate'";
    }
}

// Add company restriction for non-admin users
if ($companyId != 15100) {
    $conditions[] = "(cs.Client_id = $companyId OR cs.reseller_id = $companyId)";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY transaction_date DESC, transaction_time DESC";

$result = $conn->query($sql);
$headerStyle = [
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => [
            'argb' => 'FF0000FF', // ARGB color code for blue
        ],
    ],
    'font' => [
        'bold' => true,
        'color' => [
            'argb' => 'FFFFFFFF', // ARGB color code for white
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

// Add headers - Enterprise format
$sheet->setCellValue('A1', 'Date');
$sheet->setCellValue('B1', 'Time');
$sheet->setCellValue('C1', 'Site');
$sheet->setCellValue('D1', 'Tank');
$sheet->setCellValue('E1', 'Pump');
$sheet->setCellValue('F1', 'Card Holder');
$sheet->setCellValue('G1', 'Card Number');
$sheet->setCellValue('H1', 'Registration');
$sheet->setCellValue('I1', 'Odometer');
$sheet->setCellValue('J1', 'Volume (L)');

$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

// Populate the sheet with data
$row = 2;
$rowCount = 0;
if ($result && $result->num_rows > 0) {
    while ($row_data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $row_data['transaction_date']);
        $sheet->setCellValue('B' . $row, $row_data['transaction_time']);
        $sheet->setCellValue('C' . $row, $row_data['site_name'] ?? '');
        $sheet->setCellValue('D' . $row, $row_data['tank_id'] ?? '');
        $sheet->setCellValue('E' . $row, $row_data['pump_id'] ?? '');
        $sheet->setCellValue('F' . $row, $row_data['card_holder_name'] ?? '');
        $sheet->setCellValue('G' . $row, $row_data['card_number'] ?? '');
        $sheet->setCellValue('H' . $row, $row_data['registration'] ?? '');
        $sheet->setCellValue('I' . $row, $row_data['odometer'] ?? '');
        $sheet->setCellValue('J' . $row, (float)($row_data['dispensed_volume'] ?? 0));
        $totalVolume += (float)($row_data['dispensed_volume'] ?? 0);
        $row++;
        $rowCount++;
    }
    
    // Summary rows
    $sheet->setCellValue('I' . $row, 'TOTAL:');
    $sheet->setCellValue('J' . $row, $totalVolume);
    $row++;
    $sheet->setCellValue('I' . $row, 'Records:');
    $sheet->setCellValue('J' . $row, $rowCount);
}

// Style summary rows
$sheet->getStyle('I' . ($row-1) . ':J' . $row)->applyFromArray([
    'font' => ['bold' => true],
]);
$sheet->getStyle('A1:J' . $row)->applyFromArray($borderStyle);

// Format volume column as number with 2 decimals
$sheet->getStyle('J2:J' . ($row-2))->getNumberFormat()->setFormatCode('#,##0.00');

$columns = range('A', 'J');
foreach ($columns as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}
// Create a new file in the server's temporary directory
$temp_file = tempnam(sys_get_temp_dir(), 'excel_');

// Create a new Xlsx writer and save the file
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

// Output the file for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="transactions.xlsx"');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);

// Delete the temporary file
unlink($temp_file);
exit;
?>