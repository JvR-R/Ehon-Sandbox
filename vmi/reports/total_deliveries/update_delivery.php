<?php
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('db', ROOT_PATH . '/db/dbh2.php');
include(db);

// Check if POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
    $delivery    = isset($_POST['delivery'])    ? $_POST['delivery'] : '';

    if (!is_numeric($delivery) || $delivery <= 0) {
        echo json_encode([
            'success' => false,
            'error'   => 'Delivery must be greater than 0'
        ]);
        exit();
    }
    // Update based on delivery_id
    $stmt = $conn->prepare("
        UPDATE delivery_historic
           SET delivery = ?
         WHERE delivery_id = ?
    ");
    $stmt->bind_param("si", $delivery, $delivery_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
}
?>
