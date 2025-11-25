<?php
include('../../../db/dbh2.php'); 
include('../../../db/log.php');
header('Content-Type: application/json');
ob_start();

// Ensure companyId is available
if (!isset($companyId)) {
    $companyId = isset($_SESSION['companyId']) ? (int)$_SESSION['companyId'] : 15100;
}

// Check database connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check if the request is JSON
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle JSON input from popup
    if (isset($input['case']) && $input['case'] == 5) {
        $uid = isset($input['uid']) ? (int)$input['uid'] : 0;
        $site_id = isset($input['site_id']) ? (int)$input['site_id'] : 0;
        $tank_id = isset($input['tank_id']) ? (int)$input['tank_id'] : 0;
        $capacity = isset($input['capacity']) ? (int)$input['capacity'] : 0;
        $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
        $pumps = isset($input['pumps']) ? $input['pumps'] : [];
        
        if ($uid <= 0 || $site_id <= 0 || $tank_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }
        
        // Verify console device_type is 10 (FMS)
        $checkSql = "SELECT device_type FROM console WHERE uid = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $uid);
        $checkStmt->execute();
        $checkStmt->bind_result($device_type);
        $checkStmt->fetch();
        $checkStmt->close();
        
        if ($device_type != 10) {
            echo json_encode(['success' => false, 'error' => 'This page is only available for FMS consoles']);
            exit;
        }
        
        // Verify site access
        $siteSql = "SELECT Site_id FROM Sites WHERE Site_id = ?";
        if ($companyId != 15100) {
            $siteSql .= " AND Client_id = ?";
        }
        $siteStmt = $conn->prepare($siteSql);
        if ($companyId != 15100) {
            $siteStmt->bind_param("ii", $site_id, $companyId);
        } else {
            $siteStmt->bind_param("i", $site_id);
        }
        $siteStmt->execute();
        $siteResult = $siteStmt->get_result();
        $siteStmt->close();
        
        if ($siteResult->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Site not found or access denied']);
            exit;
        }
        
        // Start transaction
        $conn->begin_transaction();
        $success = true;
        $errors = [];
        
        try {
            // Update tank capacity and product_id
            $updateTankSql = "UPDATE Tanks SET capacity = ?, product_id = ? WHERE uid = ? AND Site_id = ? AND tank_id = ?";
            if ($companyId != 15100) {
                $updateTankSql .= " AND client_id = ?";
            }
            $updateTankStmt = $conn->prepare($updateTankSql);
            
            if ($companyId != 15100) {
                $updateTankStmt->bind_param("iiiii", $capacity, $product_id, $uid, $site_id, $tank_id, $companyId);
            } else {
                $updateTankStmt->bind_param("iiiii", $capacity, $product_id, $uid, $site_id, $tank_id);
            }
            
            if (!$updateTankStmt->execute()) {
                $success = false;
                $errors[] = "Failed to update tank $tank_id: " . $conn->error;
            }
            $updateTankStmt->close();
            
            // Delete existing pumps for this tank
            $deletePumpSql = "DELETE FROM pumps WHERE uid = ? AND tank_id = ?";
            $deletePumpStmt = $conn->prepare($deletePumpSql);
            $deletePumpStmt->bind_param("ii", $uid, $tank_id);
            if (!$deletePumpStmt->execute()) {
                $success = false;
                $errors[] = "Failed to delete existing pumps: " . $conn->error;
            }
            $deletePumpStmt->close();
            
            // Insert new pumps
            foreach ($pumps as $pump) {
                $nozzle_number = isset($pump['nozzle_number']) ? (int)$pump['nozzle_number'] : 0;
                $pulse_rate = isset($pump['pulse_rate']) ? (float)$pump['pulse_rate'] : 0.0;
                
                if ($nozzle_number > 0) {
                    $insertPumpSql = "INSERT INTO pumps (uid, tank_id, Nozzle_Number, Pulse_Rate) VALUES (?, ?, ?, ?)";
                    $insertPumpStmt = $conn->prepare($insertPumpSql);
                    $insertPumpStmt->bind_param("iiid", $uid, $tank_id, $nozzle_number, $pulse_rate);
                    
                    if (!$insertPumpStmt->execute()) {
                        $success = false;
                        $errors[] = "Failed to insert pump with nozzle $nozzle_number: " . $conn->error;
                    }
                    $insertPumpStmt->close();
                }
            }
            
            if ($success) {
                $conn->commit();
                echo json_encode(['success' => true]);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => implode(", ", $errors)]);
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => "Error: " . $e->getMessage()]);
        }
        exit;
    }
    
    // Handle original form POST data
    $uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
    $site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : 0;
    $tanks = isset($_POST['tanks']) ? $_POST['tanks'] : [];
    
    if ($uid <= 0 || $site_id <= 0 || empty($tanks)) {
        header("Location: edit_tank.php?site_id=" . $site_id . "&uid=" . $uid . "&error=" . urlencode("Invalid parameters"));
        exit;
    }
    
    // Verify console device_type is 10 (FMS)
    $checkSql = "SELECT device_type FROM console WHERE uid = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $uid);
    $checkStmt->execute();
    $checkStmt->bind_result($device_type);
    $checkStmt->fetch();
    $checkStmt->close();
    
    if ($device_type != 10) {
        header("Location: edit_tank.php?site_id=" . $site_id . "&uid=" . $uid . "&error=" . urlencode("This page is only available for FMS consoles"));
        exit;
    }
    
    // Verify site access
    $siteSql = "SELECT Site_id FROM Sites WHERE Site_id = ?";
    if ($companyId != 15100) {
        $siteSql .= " AND Client_id = ?";
    }
    $siteStmt = $conn->prepare($siteSql);
    if ($companyId != 15100) {
        $siteStmt->bind_param("ii", $site_id, $companyId);
    } else {
        $siteStmt->bind_param("i", $site_id);
    }
    $siteStmt->execute();
    $siteResult = $siteStmt->get_result();
    $siteStmt->close();
    
    if ($siteResult->num_rows == 0) {
        header("Location: edit_tank.php?site_id=" . $site_id . "&uid=" . $uid . "&error=" . urlencode("Site not found or access denied"));
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    $success = true;
    $errors = [];
    
    try {
        foreach ($tanks as $tank_id => $tankData) {
            $tank_id = (int)$tank_id;
            $capacity = isset($tankData['capacity']) ? (int)$tankData['capacity'] : 0;
            
            // Update tank capacity
            $updateTankSql = "UPDATE Tanks SET capacity = ? WHERE uid = ? AND Site_id = ? AND tank_id = ?";
            if ($companyId != 15100) {
                $updateTankSql .= " AND client_id = ?";
            }
            $updateTankStmt = $conn->prepare($updateTankSql);
            
            if ($companyId != 15100) {
                $updateTankStmt->bind_param("iiii", $capacity, $uid, $site_id, $tank_id, $companyId);
            } else {
                $updateTankStmt->bind_param("iiii", $capacity, $uid, $site_id, $tank_id);
            }
            
            if (!$updateTankStmt->execute()) {
                $success = false;
                $errors[] = "Failed to update tank $tank_id: " . $conn->error;
            }
            $updateTankStmt->close();
            
            // Update pumps if provided
            if (isset($tankData['pumps']) && is_array($tankData['pumps'])) {
                foreach ($tankData['pumps'] as $pump_id => $pumpData) {
                    $pump_id = (int)$pump_id;
                    $nozzle_number = isset($pumpData['nozzle_number']) ? (int)$pumpData['nozzle_number'] : 0;
                    $nozzle_walk_time = isset($pumpData['nozzle_walk_time']) ? (int)$pumpData['nozzle_walk_time'] : 0;
                    $nozzle_auth_time = isset($pumpData['nozzle_auth_time']) ? (int)$pumpData['nozzle_auth_time'] : 0;
                    $nozzle_max_run_time = isset($pumpData['nozzle_max_run_time']) ? (int)$pumpData['nozzle_max_run_time'] : 0;
                    $nozzle_no_flow = isset($pumpData['nozzle_no_flow']) ? (int)$pumpData['nozzle_no_flow'] : 0;
                    $nozzle_product = isset($pumpData['nozzle_product']) ? $pumpData['nozzle_product'] : '';
                    $pulse_rate = isset($pumpData['pulse_rate']) ? (float)$pumpData['pulse_rate'] : 0.0;
                    
                    if ($pump_id > 0) {
                        // Update existing pump
                        $updatePumpSql = "UPDATE pumps SET Nozzle_Number = ?, Nozzle_Walk_Time = ?, Nozzle_Auth_Time = ?, Nozzle_Max_Run_Time = ?, Nozzle_No_Flow = ?, Nozzle_Product = ?, Pulse_Rate = ? WHERE pump_id = ? AND uid = ? AND tank_id = ?";
                        $updatePumpStmt = $conn->prepare($updatePumpSql);
                        $updatePumpStmt->bind_param("iiiiiisdi", $nozzle_number, $nozzle_walk_time, $nozzle_auth_time, $nozzle_max_run_time, $nozzle_no_flow, $nozzle_product, $pulse_rate, $pump_id, $uid, $tank_id);
                        
                        if (!$updatePumpStmt->execute()) {
                            $success = false;
                            $errors[] = "Failed to update pump $pump_id for tank $tank_id: " . $conn->error;
                        }
                        $updatePumpStmt->close();
                    }
                }
            }
        }
        
        if ($success) {
            $conn->commit();
            header("Location: edit_tank.php?site_id=" . $site_id . "&uid=" . $uid . "&success=true");
            exit;
        } else {
            $conn->rollback();
            $errorMsg = implode(", ", $errors);
            header("Location: edit_tank.php?site_id=" . $site_id . "&uid=" . $uid . "&error=" . urlencode($errorMsg));
            exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: edit_tank.php?site_id=" . $site_id . "&uid=" . $uid . "&error=" . urlencode("Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}

$conn->close();
?>

