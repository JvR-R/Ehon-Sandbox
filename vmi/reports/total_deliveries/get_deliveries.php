<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));  // Goes up one directory from the current directory

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
$sql = "SELECT dh.uid, transaction_date, transaction_time, site_name, tank_id, delivery, current_volume, delivery_id 
        FROM delivery_historic dh JOIN Console_Asociation ca on ca.uid = dh.uid
        WHERE transaction_date >= '$time30days'";

// Apply filters
if (!empty($filters)) {
    $conditions = array();

    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "Site_id IN ($sites)";
    }

    if (isset($filters['group']) && !empty($filters['group'])) {
        $conditions[] = "Site_id IN (SELECT site_no FROM ehonener_ehon_vmi.client_site_groups where group_id =" . intval($filters['group']) . ")";
    }

    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "ca.Client_id = $companyFilter";
    }

    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $startDate = $conn->real_escape_string($filters['startDate']);
        $conditions[] = "transaction_date >= '$startDate'";
    }

    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $endDate = $conn->real_escape_string($filters['endDate']);
        $conditions[] = "transaction_date <= '$endDate'";
    }

    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
}

if ($companyId == 15100) {
    $sql .= " ORDER BY transaction_date DESC, transaction_time DESC LIMIT $offset, $perPage;";
} else {
    $sql .= " AND dh.uid in (SELECT ca2.uid FROM Console_Asociation ca2 WHERE ca2.Client_id = $companyId or ca2.reseller_id = $companyId or ca2.dist_id = $companyId) ORDER BY transaction_date DESC, transaction_time DESC LIMIT $offset, $perPage;";
}

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
