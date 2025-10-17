<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');

// Include files using defined paths
include(db);
include(LOG_PATH);
ini_set('memory_limit', '256M');

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT cs.Client_id, ct.*, st.site_name FROM `client_transaction` as ct JOIN Console_Asociation as cs ON ct.uid = cs.uid JOIN Sites as st ON st.uid = cs.uid";

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

// Define the CSV header
$csvHeader = [
    'Transaction ID',
    'Date',
    'Time',
    'Console ID',
    'Site Name',
    'FMS ID',
    'Tank Number',
    'Card Number',
    'Card Holder Name',
    'Odometer',
    'Registration',
    'Volume'
];

// Create a temporary file for the CSV
$temp_file = tempnam(sys_get_temp_dir(), 'csv_');
$csvFile = fopen($temp_file, 'w');

// Write the header to the CSV file
fputcsv($csvFile, $csvHeader);

// Write the data to the CSV file
if ($result->num_rows > 0) {
    while ($row_data = $result->fetch_assoc()) {
        $csvRow = [
            $row_data['transaction_id'],
            $row_data['transaction_date'],
            $row_data['transaction_time'],
            $row_data['uid'],
            $row_data['site_name'],
            $row_data['piusi_id'],
            $row_data['tank_id'],
            $row_data['card_number'],
            $row_data['card_holder_name'],
            $row_data['odometer'],
            $row_data['registration'],
            $row_data['dispensed_volume']
        ];
        fputcsv($csvFile, $csvRow);
    }
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
