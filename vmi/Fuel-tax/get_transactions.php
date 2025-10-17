<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('DB_PATH', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');

// Include files using defined paths
include(DB_PATH);
include(LOG_PATH);

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get page number from AJAX request
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 100; // Number of records per page
$offset = ($page - 1) * $perPage;

// Get filters from AJAX request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Build the base SQL query for fetching transactions
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
    AND ct.dispensed_volume > 0 
    AND (ca.client_id = $companyId or ca.reseller_id = $companyId or ca.dist_id = $companyId)";

// Apply filters if needed
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

// Complete SQL query for fetching transactions with pagination
$dataSql = "SELECT 
    ct.card_number, 
    ROUND(SUM(ct.dispensed_volume), 2) AS volume, 
    COALESCE(ct.registration, '') AS registration,
    ctb.tax_value,
    ROUND(ROUND(SUM(ct.dispensed_volume), 2)/100 * ctb.tax_value, 2) AS total
" . $baseSql . $filterConditions . "
GROUP BY ctb.tax_value, ct.card_number, ct.registration
ORDER BY total DESC
LIMIT $offset, $perPage";

// Log SQL query for debugging
// error_log("Generated Data SQL Query: $dataSql");

$dataResult = $conn->query($dataSql);

if (!$dataResult) {
    // Log MySQL error
    error_log("MySQL Error in get_transactions.php: " . $conn->error);
    // Display error for debugging
    echo json_encode(array("error" => $conn->error));
    exit;
}

$data = [];
if ($dataResult->num_rows > 0) {
    while ($row = $dataResult->fetch_assoc()) {
        $data[] = $row;
    }
}

// Build SQL query for calculating sums (across all filtered data)
$sumSql = "SELECT 
    ROUND(SUM(ct.dispensed_volume), 2) AS total_volume, 
    ROUND(SUM(ROUND(ROUND(ct.dispensed_volume, 2)/100 * ctb.tax_value, 2)), 2) AS total_sum
" . $baseSql . $filterConditions;

// Log SQL query for debugging
// error_log("Generated Sum SQL Query: $sumSql");

$sumResult = $conn->query($sumSql);

$totalVolume = 0;
$totalSum = 0;

if ($sumResult && $sumResult->num_rows > 0) {
    $sumRow = $sumResult->fetch_assoc();
    $totalVolume = $sumRow['total_volume'] ? (float)$sumRow['total_volume'] : 0;
    $totalSum = $sumRow['total_sum'] ? (float)$sumRow['total_sum'] : 0;
}

// Prepare the JSON response
$response = array(
    "data" => $data,
    "sums" => array(
        "total_volume" => $totalVolume,
        "total_sum" => $totalSum
    )
);

// Return data as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
