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

// Get company ID from session (session already started in log.php)
$companyId = $_SESSION['companyId'];

$sql = "SELECT cr.Date, st.Site_name, cr.Tank_id, cr.Opening_balance, cr.Closing_balance, cr.Total_Deliveries, cr.Total_transaction, cr.Delta, cr.reconciliation  
FROM clients_recconciliation cr 
JOIN Sites st on (st.uid = cr.uid AND st.Site_id = cr.Site_id) 
WHERE 1=1";

// Apply Site ID filter
if (isset($filters['sites']) && !empty($filters['sites'])) {
    $sites = implode(',', array_map('intval', $filters['sites']));
    $sql .= " AND st.Site_id IN ($sites)";
}

// Apply Group filter
if (isset($filters['group']) && !empty($filters['group'])) {
    $sql .= " AND st.Site_id IN (SELECT site_no FROM client_site_groups where group_id =" . intval($filters['group']) . ")";
}

// Apply Company filter (only for client_id 15100)
if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
    $companyFilter = intval($filters['company']);
    $sql .= " AND st.Client_id = $companyFilter";
}

// Apply Date filter
if (isset($filters['startDate']) && !empty($filters['startDate']) && isset($filters['endDate']) && !empty($filters['endDate'])) {
    $sql .= " AND cr.Date BETWEEN '{$filters['startDate']}' AND '{$filters['endDate']}'";
}

// Apply Tank Number filter
if (isset($filters['tank']) && !empty($filters['tank'])) {
    $sql .= " AND cr.Tank_id = {$filters['tank']}";
}

$sql .= " ORDER BY cr.Date ASC LIMIT $offset, $perPage";

// Log SQL query for debugging
error_log("Generated SQL Query: $sql");

$result = $conn->query($sql);

if (!$result) {
    // Log MySQL error
    error_log("MySQL Error: " . $conn->error);
    // Display error for debugging
    echo json_encode(array("error" => $conn->error));
    exit;
}

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Return data as JSON
header('Content-Type: application/json');
echo json_encode($data);
exit;
?>
