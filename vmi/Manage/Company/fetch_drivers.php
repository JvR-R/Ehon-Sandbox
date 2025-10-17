<?php
include('../../db/dbh2.php');

header('Content-Type: application/json');

if (isset($_GET['companyId']) && isset($_GET['customerId'])) {
    // Sanitize input
    $companyId = intval($_GET['companyId']);
    $customerId = intval($_GET['customerId']);

    // Prepare the SQL query
    $sql = "SELECT driver_id, first_name, surname FROM drivers WHERE client_id = ? AND customer_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(array('success' => false, 'message' => 'Failed to prepare statement.'));
        exit();
    }

    $stmt->bind_param("ii", $companyId, $customerId);
    $stmt->execute();
    $stmt->bind_result($driver_id, $first_name, $surname);

    $drivers = array();
    while ($stmt->fetch()) {
        // Concatenate the first name and surname with a space in between
        $driver_name = $first_name . ' ' . $surname;

        $drivers[] = array('id' => $driver_id, 'name' => $driver_name);
    }
    $stmt->close();

    echo json_encode(array('success' => true, 'drivers' => $drivers));
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid parameters.'));
}
$conn->close();
?>
