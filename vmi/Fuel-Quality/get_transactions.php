<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

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

// Get page number from AJAX request
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 100; // Number of records per page
$offset = ($page - 1) * $perPage;

// Get filters from AJAX request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT fq.uid, fq.fq_date, fq.fq_time, st.site_name, fq.tank_id, fq.particle_4um, fq.particle_6um, fq.particle_14um, st.Site_name FROM fuel_quality fq JOIN Sites st on st.uid=fq.uid";

// Apply filters
if (!empty($filters)) {
    $conditions = array();

    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "st.Site_id IN ($sites)";
    }

    if (isset($filters['group']) && !empty($filters['group'])) {
        $conditions[] = "st.Site_id IN (SELECT site_no FROM client_site_groups where group_id =" . $filters['group'] . ")";
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

// Add ordering and pagination
$sql .= " ORDER BY fq.fq_date DESC, fq.fq_time DESC LIMIT $offset, $perPage";

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
exit;
?>
