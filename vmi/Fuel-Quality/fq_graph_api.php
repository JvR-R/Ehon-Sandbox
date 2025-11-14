<?php
// API endpoint to return fuel quality data in JSON format
header('Content-Type: application/json');

include('../db/dbh2.php');
include('../db/log.php');

// Get parameters
$uid = isset($_GET['uid']) ? intval($_GET['uid']) : null;
$tank_id = isset($_GET['tank_id']) ? intval($_GET['tank_id']) : null;
$time_window = isset($_GET['time_window']) ? intval($_GET['time_window']) : 7200; // Default 2 hours (7200 seconds)

if ($uid === null || $tank_id === null) {
    echo json_encode(['error' => 'Missing uid or tank_id parameter']);
    exit;
}

// Verify user has access to this data
$access_check_query = "
    SELECT 1 FROM Console_Asociation ca
    WHERE ca.uid = ? 
    AND (ca.client_id = ? OR ca.reseller_id = ? OR ca.dist_id = ?)
    LIMIT 1
";
$access_stmt = $conn->prepare($access_check_query);
$access_stmt->bind_param("iiii", $uid, $companyId, $companyId, $companyId);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($access_result->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$access_stmt->close();

// Fetch data from the last X seconds
// Use TIMESTAMP() function to combine DATE and TIME - this is the standard MySQL/MariaDB way
$query = "
    SELECT 
        fq.fq_date,
        fq.fq_time,
        CONCAT(fq.fq_date, ' ', fq.fq_time) AS datetime,
        fq.particle_4um,
        fq.particle_6um,
        fq.particle_14um,
        fq.fq_bubbles,
        fq.fq_temp
    FROM 
        fuel_quality fq
    WHERE 
        fq.uid = ? 
        AND fq.tank_id = ?
        AND fq.fq_date IS NOT NULL
        AND fq.fq_time IS NOT NULL
        AND TIMESTAMP(fq.fq_date, fq.fq_time) >= DATE_SUB(NOW(), INTERVAL ? SECOND)
    ORDER BY 
        fq.fq_date ASC, 
        fq.fq_time ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['error' => 'Query preparation failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iii", $uid, $tank_id, $time_window);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'Query execution failed: ' . $stmt->error]);
    exit;
}
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = [
        'datetime' => $row['datetime'],
        'particle_4um' => $row['particle_4um'] !== null ? intval($row['particle_4um']) : null,
        'particle_6um' => $row['particle_6um'] !== null ? intval($row['particle_6um']) : null,
        'particle_14um' => $row['particle_14um'] !== null ? intval($row['particle_14um']) : null,
        'bubbles' => $row['fq_bubbles'] !== null ? intval($row['fq_bubbles']) : null,
        'temp' => $row['fq_temp'] !== null ? floatval($row['fq_temp']) : null
    ];
}

echo json_encode($data);

$stmt->close();
$conn->close();
?>

