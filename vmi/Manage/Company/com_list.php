<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');   


// Step 1: Retrieve the input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$dispatchType = $input['dispatchType'];

// Step 2: Query the Database
if ($dispatchType == 1) {
    $query = "SELECT Dist_id, Dist_name FROM Distributor";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->bind_result($Dist_id, $Dist_name);
    // Step 3: Prepare the Data
    while ($stmt->fetch()) {
        $clients[] = array('id' => $Dist_id, 'name' => $Dist_name);
    }
} elseif ($dispatchType == 2) {
    $query = "SELECT reseller_id, reseller_name FROM Reseller WHERE dist_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($reseller_id, $reseller_name);
    // Step 3: Prepare the Data
    while ($stmt->fetch()) {
        $clients[] = array('id' => $reseller_id, 'name' => $reseller_name);
    }
}

// Step 4: Echo the JSON Data
header('Content-Type: application/json');
echo json_encode($clients);
$stmt->close();
?>
