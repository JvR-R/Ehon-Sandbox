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
ini_set('memory_limit', '256M');

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

if (!is_array($filters)) {
    $filters = [];
}
// Debugging statements
error_log("Type of filters: " . gettype($filters));
error_log("Content of filters: " . print_r($filters, true));


// Sanitize and prepare filters for SQL
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

// Build the base SQL query
$sql = "
    SELECT 
        ct.card_number, 
        ROUND(SUM(ct.dispensed_volume), 2) AS volume, 
        COALESCE(ct.registration, '') AS registration,
        ctb.tax_value,
        ROUND(ROUND(SUM(ct.dispensed_volume), 2)/100 * ctb.tax_value, 2) AS total
    FROM 
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
        AND ct.dispensed_volume > 0  AND (ca.client_id = $companyId or ca.reseller_id = $companyId or ca.dist_id = $companyId)
    $filterConditions
    GROUP BY 
        ctb.tax_value, ct.card_number, ct.registration
    ORDER BY 
        total DESC
";

// Execute the query
$result = $conn->query($sql);

// Check for query errors
if (!$result) {
    // Log MySQL error
    error_log("MySQL Error in export_to_csv.php: " . $conn->error);
    // Send JSON error response
    header('Content-Type: application/json');
    echo json_encode(array("error" => $conn->error));
    exit;
}

// Define the CSV header
$csvHeader = [
    'Card Number',
    'Registration',
    'Volume',
    'Tax Value',
    'Total'
];

// Create a temporary file for the CSV
$temp_file = tempnam(sys_get_temp_dir(), 'csv_');
$csvFile = fopen($temp_file, 'w');

// Write BOM to the beginning of the file for Excel compatibility
fputs($csvFile, "\xEF\xBB\xBF");

// Write the header to the CSV file
fputcsv($csvFile, $csvHeader);

// Initialize sums
$totalVolume = 0;
$totalSum = 0;

// Write the data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $csvRow = [
            $row['card_number'],
            $row['registration'],
            number_format($row['volume'], 2, '.', ''), // Ensure consistent decimal formatting
            number_format($row['tax_value'], 2, '.', ''),
            number_format($row['total'], 2, '.', '')
        ];
        fputcsv($csvFile, $csvRow);
        
        // Accumulate sums
        $totalVolume += (float)$row['volume'];
        $totalSum += (float)$row['total'];
    }
}

// Write the summary row
$summaryRow = [
    'Total',
    '', // Empty cell for Registration
    number_format($totalVolume, 2, '.', ''),
    '', // Empty cell for Tax Value
    number_format($totalSum, 2, '.', '')
];
fputcsv($csvFile, $summaryRow);

// Close the CSV file
fclose($csvFile);

// Clear output buffer before sending headers
ob_clean();

// Set headers to force download with a dynamic filename
$filename = 'Fuel-Tax.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($temp_file));

// Output the CSV file
readfile($temp_file);

// Delete the temporary file
unlink($temp_file);

// End output buffering and flush output
ob_end_flush();
exit;
?>
