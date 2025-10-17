<?php
include('../../../../db/dbh.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    $transactionId = $_POST['transaction_id'];

    $stmt = $conn->prepare("UPDATE card_transaction SET flag = 0 WHERE transaction_id = ?");
    $stmt->bind_param('s', $transactionId);

    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Transaction successfully reset.'];
    } else {
        $response['message'] = 'Failed to reset transaction.';
    }

    $stmt->close();
}

$conn->close();

echo json_encode($response);
?>