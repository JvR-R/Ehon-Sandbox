<?php
include('../db/dbh2.php');
include('../db/log.php');

// Set JSON header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Validate and sanitize input
$groupId = isset($_POST['groupId']) ? intval($_POST['groupId']) : 0;
$companyId = isset($_POST['companyId']) ? intval($_POST['companyId']) : 0;

// Validate inputs
if ($groupId <= 0 || $companyId <= 0) {
    exit(json_encode(['error' => 'Invalid parameters']));
}

// Use prepared statement to prevent SQL injection
$query = "SELECT site_no, site_name FROM client_site_groups WHERE client_id = ? AND group_id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    exit(json_encode(['error' => 'Database error']));
}

$stmt->bind_param("ii", $companyId, $groupId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'siteId' => intval($row['site_no']),
        'siteName' => htmlspecialchars($row['site_name'], ENT_QUOTES, 'UTF-8')
    ];
}

$stmt->close();

echo json_encode($data);
?>
