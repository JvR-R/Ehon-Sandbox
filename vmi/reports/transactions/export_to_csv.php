<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
include(db);
include(LOG_PATH);

ini_set('memory_limit', '256M');
require '../../../vendor/autoload.php';

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters - using proper escaping
$sql = "SELECT cs.Client_id, ct.*, st.site_name, ss.description 
        FROM `client_transaction` as ct 
        JOIN Console_Asociation as cs ON ct.uid = cs.uid 
        JOIN Sites as st ON st.uid = cs.uid
        JOIN stop_methods ss ON ct.stop_method = ss.id";

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
    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "cs.Client_id = $companyFilter";
    }
    if (isset($filters['cardnumber']) && !empty($filters['cardnumber'])) {
        $cardnumber = $conn->real_escape_string($filters['cardnumber']);
        $conditions[] = "ct.card_number = '$cardnumber'";
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

// Define the CSV header - Enterprise format
$csvHeader = [
    'Date',
    'Time',
    'Site',
    'Tank',
    'Pump',
    'Card Holder',
    'Card Number',
    'Registration',
    'Odometer',
    'Volume (L)'
];

// Create a temporary file for the CSV
$temp_file = tempnam(sys_get_temp_dir(), 'csv_');
$csvFile = fopen($temp_file, 'w');

// Write the header to the CSV file
fputcsv($csvFile, $csvHeader);

// Track total volume
$totalVolume = 0;
$rowCount = 0;

// Write the data to the CSV file
if ($result && $result->num_rows > 0) {
    while ($row_data = $result->fetch_assoc()) {
        $volume = number_format((float)($row_data['dispensed_volume'] ?? 0), 2);
        $csvRow = [
            $row_data['transaction_date'],
            $row_data['transaction_time'],
            $row_data['site_name'] ?? '',
            $row_data['tank_id'] ?? '',
            $row_data['pump_id'] ?? '',
            $row_data['card_holder_name'] ?? '',
            $row_data['card_number'] ?? '',
            $row_data['registration'] ?? '',
            $row_data['odometer'] ?? '',
            $volume
        ];
        fputcsv($csvFile, $csvRow);
        $totalVolume += (float)($row_data['dispensed_volume'] ?? 0);
        $rowCount++;
    }
    
    // Add summary row
    fputcsv($csvFile, ['', '', '', '', '', '', '', '', 'TOTAL:', number_format($totalVolume, 2)]);
    fputcsv($csvFile, ['', '', '', '', '', '', '', '', 'Records:', $rowCount]);
}

// Close the CSV file
fclose($csvFile);

// Output the CSV file for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="transactions.csv"');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);

// Delete the temporary file
unlink($temp_file);
exit;
?>
