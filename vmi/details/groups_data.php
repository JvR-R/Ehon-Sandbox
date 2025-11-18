<?php
include('../db/dbh2.php');
include('../db/log.php');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Get DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$orderColumn = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';
$companyId = isset($_POST['companyId']) ? intval($_POST['companyId']) : 0;
$groupId = isset($_POST['groupId']) ? intval($_POST['groupId']) : 0;

// Validate order direction
$orderDir = ($orderDir === 'desc') ? 'DESC' : 'ASC';

// Map column index to database column
$columns = ['cs.site_id', 'clc.client_name', 'cs.site_name'];
$orderColumnName = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'cs.site_id';

// Build base query based on companyId
if (!empty($companyId) && $companyId != 15100) {
    $baseQuery = "FROM Sites cs 
                  JOIN Clients clc ON cs.client_id = clc.client_id 
                  WHERE (cs.client_id = ? OR clc.reseller_id = ? OR clc.Dist_id = ?)";
    $params = [$companyId, $companyId, $companyId];
    $paramTypes = 'iii';
} elseif ($companyId == 15100) {
    $baseQuery = "FROM Sites cs 
                  JOIN Clients clc ON cs.client_id = clc.client_id 
                  WHERE cs.uid IN (SELECT uid FROM console WHERE device_type != 999)";
    $params = [];
    $paramTypes = '';
} else {
    // Invalid company ID
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

// Add search filter if provided
$searchQuery = '';
if (!empty($searchValue)) {
    $searchQuery = " AND (cs.site_id LIKE ? OR clc.client_name LIKE ? OR cs.site_name LIKE ?)";
    $searchParam = '%' . $searchValue . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $paramTypes .= 'sss';
}

// Get total count (without search filter)
$countQuery = "SELECT COUNT(*) as total " . $baseQuery;
$stmt = $conn->prepare($countQuery);
if (!empty($paramTypes) && strpos($paramTypes, 's') === false) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get filtered count (with search filter)
$filteredCountQuery = "SELECT COUNT(*) as total " . $baseQuery . $searchQuery;
$stmt = $conn->prepare($filteredCountQuery);
if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$filteredRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get paginated data with LEFT JOIN to check if site is in the selected group
$dataQuery = "SELECT 
                cs.site_id,
                cs.site_name,
                clc.client_name,
                CASE WHEN csg.site_no IS NOT NULL THEN 1 ELSE 0 END as is_checked
              " . $baseQuery;

// Add LEFT JOIN for group membership if groupId is provided
if ($groupId > 0) {
    $dataQuery .= " LEFT JOIN client_site_groups csg 
                    ON cs.site_id = csg.site_no 
                    AND csg.group_id = ? 
                    AND csg.client_id = ?";
    $params[] = $groupId;
    $params[] = $companyId;
    $paramTypes .= 'ii';
} else {
    $dataQuery .= " LEFT JOIN client_site_groups csg ON 1=0"; // Always false join
}

$dataQuery .= $searchQuery;
$dataQuery .= " ORDER BY {$orderColumnName} {$orderDir} LIMIT ? OFFSET ?";
$params[] = $length;
$params[] = $start;
$paramTypes .= 'ii';

$stmt = $conn->prepare($dataQuery);
if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'site_id' => $row['site_id'],
        'client_name' => htmlspecialchars($row['client_name'], ENT_QUOTES, 'UTF-8'),
        'site_name' => htmlspecialchars($row['site_name'], ENT_QUOTES, 'UTF-8'),
        'is_checked' => $row['is_checked']
    ];
}
$stmt->close();

// Return JSON response for DataTables
header('Content-Type: application/json');
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
]);
?>

