<?php
// Include database connection and session management
include('../../db/dbh2.php');
include('../../db/log.php');

// Initialize companyId from session
$companyId = $_SESSION['companyId'] ?? 0;

ini_set('memory_limit', '256M');
// Require the Composer autoloader
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT cs.Client_id, ct.*, st.site_name FROM `client_transaction` as ct JOIN Console_Asociation as cs ON ct.uid = cs.uid JOIN Sites as st ON st.uid = cs.uid";
$totalVolume = 0;
// Apply filters
if (!empty($filters)) {
    $conditions = array();
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
    if (isset($filters['cardnumber']) && !empty($filters['cardnumber'])) {
        $conditions[] = "ct.card_number = {$filters['cardnumber']}";
    }
    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "cs.Client_id = $companyFilter";
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
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
}

if ($companyId == 15100) {
    $sql .= " ORDER BY transaction_date DESC, transaction_time DESC;";
} else {
    $sql .= " AND (cs.Client_id = $companyId OR cs.reseller_id = $companyId) ORDER BY transaction_date DESC, transaction_time DESC;";
}

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

// Add headers to the first row of the sheet
$sheet->setCellValue('A1', 'Transaction ID');
$sheet->setCellValue('B1', 'Date');
$sheet->setCellValue('C1', 'Time');
$sheet->setCellValue('D1', 'Link ID');
$sheet->setCellValue('E1', 'Site Name');
$sheet->setCellValue('F1', 'FMS ID');
$sheet->setCellValue('G1', 'Tank Number');
$sheet->setCellValue('H1', 'Card Number');
$sheet->setCellValue('I1', 'Card Holder Name');
$sheet->setCellValue('J1', 'Odometer');
$sheet->setCellValue('K1', 'Registration');
$sheet->setCellValue('L1', 'Volume');

$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
// Populate the sheet with data
$row = 2;
if ($result->num_rows > 0) {
    while ($row_data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $row_data['transaction_id']);
        $sheet->setCellValue('B' . $row, $row_data['transaction_date']);
        $sheet->setCellValue('C' . $row, $row_data['transaction_time']);
        $sheet->setCellValue('D' . $row, $row_data['uid']);
        $sheet->setCellValue('E' . $row, $row_data['site_name']);
        $sheet->setCellValue('F' . $row, $row_data['fms_id']);
        $sheet->setCellValue('G' . $row, $row_data['tank_id']);
        $sheet->setCellValue('H' . $row, $row_data['card_number']);
        $sheet->setCellValue('I' . $row, $row_data['card_holder_name']);
        $sheet->setCellValue('J' . $row, $row_data['odometer']);
        $sheet->setCellValue('K' . $row, $row_data['registration']);
        $sheet->setCellValue('L' . $row, $row_data['dispensed_volume']);
        $totalVolume += (float) $row_data['dispensed_volume'];
        $row++;
    }
    $sheet->setCellValue('K' . $row, 'TOTAL');
    $sheet->setCellValue('L' . $row, $totalVolume); 
}
$sheet->getStyle('K' . $row . ':L' . $row)->applyFromArray([
    'font' => ['bold' => true],
    'borders' => [
        'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
    ],
]);
$sheet->getStyle('A1:L' . $row)->applyFromArray($borderStyle);
$columns = range('A', 'L');
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