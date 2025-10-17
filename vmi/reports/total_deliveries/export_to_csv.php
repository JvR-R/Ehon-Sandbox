<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include files using defined paths
include(ROOT_PATH . '/db/dbh2.php');
include(ROOT_PATH . '/db/log.php');

// Increase memory limit if needed
ini_set('memory_limit', '256M');

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
        // Adjust the schema/table reference if needed
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

// If conditions exist, append them
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// If $companyId is not 15100, add additional restrictions
if ($companyId != 15100) {
    // Append the company restrictions
    $sql .= (strpos($sql, 'WHERE') !== false ? " AND" : " WHERE") .
            " (Client_id = $companyId OR reseller_id = $companyId)";
}

// Order the results
$sql .= " ORDER BY transaction_date DESC, transaction_time DESC;";

// Execute the query
$result = $conn->query($sql);

// Define the CSV header (the same columns used above)
$csvHeader = [
    'UID',
    'Transaction Date',
    'Transaction Time',
    'Site Name',
    'Tank ID',
    'Delivery',
    'Current Volume'
];

// Create a temporary file for the CSV
$temp_file = tempnam(sys_get_temp_dir(), 'csv_');
$csvFile   = fopen($temp_file, 'w');

// Write the header to the CSV file
fputcsv($csvFile, $csvHeader);

// Write the data to the CSV file
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Construct each row of data in the same order as $csvHeader
        $csvRow = [
            $row['uid'],
            $row['transaction_date'],
            $row['transaction_time'],
            $row['site_name'],
            $row['tank_id'],
            $row['delivery'],
            $row['current_volume']
        ];
        fputcsv($csvFile, $csvRow);
    }
}

// Close the CSV file
fclose($csvFile);

// Output the CSV file for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Deliveries.csv"');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);

// Delete the temporary file
unlink($temp_file);
exit;
