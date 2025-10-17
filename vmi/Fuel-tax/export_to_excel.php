<?php
// Start output buffering and clear any previous output
ob_start();

// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('DB_PATH', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');

// Include files using defined paths
include(DB_PATH);
include(LOG_PATH);
include(BORDER_PATH);

// Increase memory limit for handling large datasets
ini_set('memory_limit', '512M');

// Require the Composer autoloader
require '/home/ehonener/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Get filters from the request
// Assuming filters are sent as a JSON-encoded string
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

if (!is_array($filters)) {
    $filters = [];
}
// Build the base SQL query
$baseSql = "FROM 
    client_transaction ct 
JOIN 
    Console_Asociation ca 
    ON ct.uid = ca.uid  
JOIN 
    client_tasbax ctb 
    ON ctb.card_number = ct.card_number
WHERE 
    ct.uid IN (SELECT uid FROM console WHERE device_type != 999) 
    AND ct.dispensed_volume > 0  AND (ca.client_id = $companyId or ca.reseller_id = $companyId or ca.dist_id = $companyId)";

// Apply filters if provided
$filterConditions = "";
if (!empty($filters)) {
    // Filter by card_number
    if (isset($filters['card_number']) && !empty($filters['card_number'])) {
        $card_number = $conn->real_escape_string($filters['card_number']);
        $filterConditions .= " AND ct.card_number = '$card_number'";
    }

    // Filter by registration
    if (isset($filters['registration']) && !empty($filters['registration'])) {
        $registration = $conn->real_escape_string($filters['registration']);
        $filterConditions .= " AND ct.registration = '$registration'";
    }

    // Filter by date range
    if (isset($filters['startDate']) && !empty($filters['startDate']) && isset($filters['endDate']) && !empty($filters['endDate'])) {
        $startDate = $conn->real_escape_string($filters['startDate']);
        $endDate = $conn->real_escape_string($filters['endDate']);
        $filterConditions .= " AND ct.transaction_date BETWEEN '$startDate' AND '$endDate'";
    }
}

// Complete SQL query for fetching all transactions (no pagination)
$dataSql = "SELECT 
    ct.card_number, 
    ROUND(SUM(ct.dispensed_volume), 2) AS volume, 
    COALESCE(ct.registration, '') AS registration,
    ctb.tax_value,
    ROUND(ROUND(SUM(ct.dispensed_volume), 2)/100 * ctb.tax_value, 2) AS total
" . $baseSql . $filterConditions . "
GROUP BY ctb.tax_value, ct.card_number, ct.registration
ORDER BY total DESC";

// Log SQL query for debugging (optional)
// error_log("Export SQL Query: $dataSql");

$dataResult = $conn->query($dataSql);

if (!$dataResult) {
    // Log MySQL error
    error_log("MySQL Error in export_to_excel.php: " . $conn->error);
    // Display error for debugging (optional)
    header('Content-Type: application/json');
    echo json_encode(array("error" => $conn->error));
    exit;
}

// Define header styles
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
$headers = ['Card Number', 'Volume', 'Registration', 'Tax Value', 'Total'];
$columnLetters = ['A', 'C', 'B', 'D', 'E'];

foreach ($headers as $index => $header) {
    $cell = $columnLetters[$index] . '1';
    $sheet->setCellValue($cell, $header);
}
$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

// Initialize sums
$totalVolume = 0;
$totalSum = 0;

// Populate the sheet with data
$rowNumber = 2; // Start from the second row
if ($dataResult->num_rows > 0) {
    while ($row_data = $dataResult->fetch_assoc()) {
        $sheet->setCellValue('A' . $rowNumber, $row_data['card_number']);
        $sheet->setCellValue('C' . $rowNumber, $row_data['volume']);
        $sheet->setCellValue('B' . $rowNumber, $row_data['registration']);
        $sheet->setCellValue('D' . $rowNumber, $row_data['tax_value']);
        $sheet->setCellValue('E' . $rowNumber, $row_data['total']);
        $rowNumber++;

        // Accumulate sums
        $totalVolume += (float)$row_data['volume'];
        $totalSum += (float)$row_data['total'];
    }
}
$lastDataRow = $rowNumber - 1;
$sheet->getStyle('A1:E' . $lastDataRow)->applyFromArray($borderStyle);

// Add the sum row
$sumRowNumber = $rowNumber;
$sheet->setCellValue('A' . $sumRowNumber, 'Total');
$sheet->setCellValue('C' . $sumRowNumber, $totalVolume);
$sheet->setCellValue('B' . $sumRowNumber, ''); // Empty cell for Registration
$sheet->setCellValue('D' . $sumRowNumber, ''); // Empty cell for Tax Value
$sheet->setCellValue('E' . $sumRowNumber, $totalSum);

// Apply styles to the sum row
$sumStyle = [
    'font' => [
        'bold' => true,
    ],
    'borders' => [
        'top' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
        'bottom' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];
$sheet->getStyle('A' . $sumRowNumber . ':E' . $sumRowNumber)->applyFromArray($sumStyle);

// Auto-size columns for better readability
foreach ($columnLetters as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Optionally, you can set number formats
$sheet->getStyle('B2:B' . $sumRowNumber)
      ->getNumberFormat()
      ->setFormatCode('#,##0.00');

$sheet->getStyle('D2:E' . $sumRowNumber)
      ->getNumberFormat()
      ->setFormatCode('#,##0.00');

// Clear output buffer before sending headers
ob_clean();

// Create a new file in the server's temporary directory
$temp_file = tempnam(sys_get_temp_dir(), 'excel_');

// Create a new Xlsx writer and save the file
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

// Output the file for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Fuel-Tax.xlsx"');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);

// Delete the temporary file
unlink($temp_file);

// End output buffering and flush output
ob_end_flush();
exit;
?>
