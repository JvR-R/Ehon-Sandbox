<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');

$response = ['status' => 'error', 'message' => 'Initial error', 'data' => []];

header('Content-Type: application/json');

$sqld = "SELECT product_id, product_name FROM products";
$stmtd = $conn->prepare($sqld);

if ($stmtd->execute()) {
    $stmtd->bind_result($product_id, $product_name);
    $products = [];
    while ($stmtd->fetch()) {
        array_push($products, ['id' => $product_id, 'name' => $product_name]);
    }
    if (count($products) > 0) {
        $response = ['status' => 'success', 'data' => $products];
    } else {
        $response['message'] = 'No products found';
    }
} else {
    $response['message'] = 'Query execution failed';
}

echo json_encode($response);
?>
