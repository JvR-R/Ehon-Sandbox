<?php
// Start output buffering and clear any previous output
ob_start();

// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');
// Include files using defined paths
include(db);
include(LOG_PATH);
include(BORDER_PATH);
ini_set('memory_limit', '256M');

// Require the Composer autoloader
require '/home/ehonener/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT fq.uid, fq.fq_date, fq.fq_time, st.site_name, fq.tank_id, fq.particle_4um, fq.particle_6um, fq.particle_14um, fq.fq_bubbles, fq.fq_cutting, fq.fq_sliding, fq.fq_fatigue, fq.fq_fibre, fq.fq_air, fq.fq_unknown, fq.fq_temp FROM fuel_quality fq JOIN Sites st on st.uid=fq.uid";

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
        $conditions[] = "fq.card_holder_name LIKE '%{$filters['cardholder']}%'";
    }
    if (isset($filters['cardnumber']) && !empty($filters['cardnumber'])) {
        $conditions[] = "fq.card_number = {$filters['cardnumber']}";
    }
    if (isset($filters['registration']) && !empty($filters['registration'])) {
        $conditions[] = "fq.registration LIKE '%{$filters['registration']}%'";
    }
    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $conditions[] = "fq.fq_date >= '{$filters['startDate']}'";
    }
    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $conditions[] = "fq.fq_date <= '{$filters['endDate']}'";
    }
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
}

// Finalize the SQL query with ordering
$sql .= " ORDER BY fq.fq_date DESC, fq.fq_time DESC";

$result = $conn->query($sql);

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
$sheet->setCellValue('A1', 'UID');
$sheet->setCellValue('B1', 'Date');
$sheet->setCellValue('C1', 'Time');
$sheet->setCellValue('D1', 'Site Name');
$sheet->setCellValue('E1', 'Tank ID');
$sheet->setCellValue('F1', '4um Particles');
$sheet->setCellValue('G1', '6um Particles');
$sheet->setCellValue('H1', '14um Particles');
$sheet->setCellValue('I1', 'Bubbles');
$sheet->setCellValue('J1', 'Cutting');
$sheet->setCellValue('K1', 'Sliding');
$sheet->setCellValue('L1', 'Fatigue');
$sheet->setCellValue('M1', 'Fibre');
$sheet->setCellValue('N1', 'Air');
$sheet->setCellValue('O1', 'Unknown');
$sheet->setCellValue('P1', 'Temp');
$sheet->setCellValue('Q1', 'Concatenated Particles');

$sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);

// Populate the sheet with data
$row = 2;
if ($result->num_rows > 0) {
    while ($row_data = $result->fetch_assoc()) {
        $concatenatedParticles = $row_data['particle_4um'] . '/' . $row_data['particle_6um'] . '/' . $row_data['particle_14um'];
        $sheet->setCellValue('A' . $row, $row_data['uid']);
        $sheet->setCellValue('B' . $row, $row_data['fq_date']);
        $sheet->setCellValue('C' . $row, $row_data['fq_time']);
        $sheet->setCellValue('D' . $row, $row_data['site_name']);
        $sheet->setCellValue('E' . $row, $row_data['tank_id']);
        $sheet->setCellValue('F' . $row, $row_data['particle_4um']);
        $sheet->setCellValue('G' . $row, $row_data['particle_6um']);
        $sheet->setCellValue('H' . $row, $row_data['particle_14um']);
        $sheet->setCellValue('I' . $row, $row_data['fq_bubbles']);
        $sheet->setCellValue('J' . $row, $row_data['fq_cutting']);
        $sheet->setCellValue('K' . $row, $row_data['fq_sliding']);
        $sheet->setCellValue('L' . $row, $row_data['fq_fatigue']);
        $sheet->setCellValue('M' . $row, $row_data['fq_fibre']);
        $sheet->setCellValue('N' . $row, $row_data['fq_air']);
        $sheet->setCellValue('O' . $row, $row_data['fq_unknown']);
        $sheet->setCellValue('P' . $row, $row_data['fq_temp']);
        $sheet->setCellValue('Q' . $row, $concatenatedParticles);
        $row++;
    }
}
$sheet->getStyle('A1:Q' . $row)->applyFromArray($borderStyle);
$columns = range('A', 'Q');
foreach ($columns as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Clear output buffer before sending headers
ob_clean();

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

// End output buffering and flush output
ob_end_flush();
exit;
?>
