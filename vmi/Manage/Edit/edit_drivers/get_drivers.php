<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__, 2)));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
// Include files using defined paths
include(db);
include(LOG_PATH);

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Calculate the date 30 days ago
$time30days = date("Y-m-d", strtotime("-1 Year"));

// Get page number from AJAX request
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 100; // Number of records per page
$offset = ($page - 1) * $perPage;

// Get filters from AJAX request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
// Select specific columns to avoid conflicts
$sql = "SELECT cta.driver_id, cta.first_name, cta.surname, cta.driver_phone, 
               cta.license_expire, cta.updated_at, cta.customer_id, cta.client_id,
               cms.customer_name 
        FROM drivers cta 
        JOIN Customers cms ON cms.customer_id = cta.customer_id 
        WHERE cta.driver_enabled != 999 
        AND cta.client_id = " . intval($companyId);

// Apply filters
if (!empty($filters)) {
    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $siteIds = array_map('intval', $filters['sites']);
        $sites = implode(',', $siteIds);
        $sql .= " AND cta.customer_id IN ($sites)";
    }

    if (isset($filters['cardholder']) && !empty($filters['cardholder'])) {
        $cardholder = $conn->real_escape_string($filters['cardholder']);
        $sql .= " AND (cta.first_name LIKE '%$cardholder%' OR cta.surname LIKE '%$cardholder%')";
    }

    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $startDate = $conn->real_escape_string($filters['startDate']);
        $sql .= " AND DATE(cta.updated_at) >= '$startDate'";
    }

    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $endDate = $conn->real_escape_string($filters['endDate']);
        $sql .= " AND DATE(cta.updated_at) <= '$endDate'";
    }
}

$sql .= " ORDER BY cta.updated_at DESC LIMIT $offset, $perPage";

$result = $conn->query($sql);
$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

ob_end_clean();

// Return data as JSON
header('Content-Type: application/json');
echo json_encode($data);
?>
