<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');

$response = ['status' => 'error', 'message' => 'Initial error', 'options' => ''];

if (isset($_POST['nozzleProduct']) && isset($_POST['consoleId']) && isset($_POST['siteId'])) {
    $nozzleProduct = $_POST['nozzleProduct'];
    $consoleId = $_POST['consoleId'];
    $siteId = $_POST['siteId'];

    header('Content-Type: application/json');

    $sqld = "SELECT tank_id FROM Tanks WHERE product_id = ? and Site_id = ? and uid = ? and client_id = ?";
    $stmtd = $conn->prepare($sqld);
    $stmtd->bind_param("iiii", $nozzleProduct, $siteId, $consoleId, $companyId);

    if ($stmtd->execute()) {
        $stmtd->bind_result($tank_id); // Bind the result to a variable
        $options = "";
        $hasResults = false;
        while ($stmtd->fetch()) {
            $hasResults = true;
            $options .= "<option value='" . $tank_id . "'>Tank " . $tank_id . "</option>";
        }
        if (!$hasResults) {
            // No results found, add "No Tanks" option
            $options = "<option value='0'>No Tanks</option>";
        }
        $response = ['status' => 'success', 'options' => $options];
    } else {
        $response['message'] = 'Query execution failed';
    }
} else {
    $response['message'] = 'Required parameters not set';
}

echo json_encode($response);
?>
