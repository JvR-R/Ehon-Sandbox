<?php
include('../../../db/dbh.php');

// Get POST data
$transaction_id = $_POST['transaction_id'] ?? null;
$total_price = $_POST['total_price'] ?? null;
$price_local = $_POST['price_local'] ?? null;
$price_customer = $_POST['price_customer'] ?? null;

if ($transaction_id && $total_price && $price_local && $price_customer) {
    // Validate and sanitize inputs
    $transaction_id = intval($transaction_id);
    $total_price = floatval($total_price);
    $price_local = floatval($price_local);
    $price_customer = floatval($price_customer);

    // Update the database
    $sql = "UPDATE card_transaction SET total_price = ?, price_local = ?, price_customer = ? WHERE transaction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dddi", $total_price, $price_local, $price_customer, $transaction_id);

    if ($stmt->execute()) {
        echo "Success";
    } else {
        http_response_code(500);
        echo "Error updating record: " . $conn->error;
    }

    $stmt->close();
} else {
    http_response_code(400);
    echo "Invalid input";
}

$conn->close();
?>
