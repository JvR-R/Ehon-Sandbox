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

// Calculate the date 1 week in the future
$timeOneWeekFuture = date("Y-m-d", strtotime("+1 week"));

// Get page number from AJAX request
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 100; // Number of records per page
$offset = ($page - 1) * $perPage;

// Get filters from AJAX request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT st.Client_id, ct.*, st.site_name, ss.description FROM `client_transaction` as ct 
        JOIN Sites as st ON st.uid = ct.uid
        JOIN console cos on cos.uid = st.uid 
        JOIN stop_methods ss on ct.stop_method = ss.id
        JOIN Clients cs ON cs.client_id = st.client_id
        WHERE device_type != 999 AND transaction_date >= '$time30days' AND transaction_date <= '$timeOneWeekFuture'";

// Apply filters
if (!empty($filters)) {
    $conditions = array();

    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "st.Site_id IN ($sites)";
    }

    if (isset($filters['group']) && !empty($filters['group'])) {
        $conditions[] = "st.Site_id IN (SELECT site_no FROM client_site_groups where group_id =" . intval($filters['group']) . ")";
    }

    if ($companyId == 15100 && isset($filters['company']) && !empty($filters['company'])) {
        $companyFilter = intval($filters['company']);
        $conditions[] = "cs.Client_id = $companyFilter";
    }

    if (isset($filters['cardholder']) && !empty($filters['cardholder'])) {
        $cardholder = $conn->real_escape_string($filters['cardholder']);
        $conditions[] = "ct.card_holder_name LIKE '%$cardholder%'";
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

    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
}

if ($companyId == 15100) {
    $sql .= " ORDER BY transaction_date DESC, transaction_time DESC LIMIT $offset, $perPage;";
} else {
    $sql .= " AND (cs.Client_id = $companyId OR cs.reseller_id = $companyId) ORDER BY transaction_date DESC, transaction_time DESC LIMIT $offset, $perPage;";
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
