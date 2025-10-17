<?php
include('../../db/dbh2.php');


header('Content-Type: application/json');

if (isset($_GET['companyId']) && isset($_GET['customerId'])) {
    // Sanitize input
    $companyId = intval($_GET['companyId']);
    $customerId = intval($_GET['customerId']);

    // Prepare the SQL query
    $sql = "SELECT vehicle_id, vehicle_name FROM vehicles WHERE client_id = ? AND customer_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(array('success' => false, 'message' => 'Failed to prepare statement.'));
        exit();
    }

    $stmt->bind_param("ii", $companyId, $customerId);
    $stmt->execute();
    $stmt->bind_result($vehicle_id, $vehicle_name);

    $vehicles = array();
    while ($stmt->fetch()) {
        $vehicles[] = array('id' => $vehicle_id, 'name' => $vehicle_name);
    }
    $stmt->close();

    echo json_encode(array('success' => true, 'vehicles' => $vehicles));
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid parameters.'));
}
$conn->close();
?>
