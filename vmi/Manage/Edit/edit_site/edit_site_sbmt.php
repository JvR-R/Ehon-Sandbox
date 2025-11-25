<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
ob_start();

// Set header to return JSON content type
header('Content-Type: application/json');

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the raw POST data
    $content = trim(file_get_contents("php://input"));
    
    // Attempt to decode the received JSON data
    $decoded = json_decode($content, true);

    if (is_array($decoded)) {
        $case = isset($decoded['case']) ? $decoded['case'] : 3;
        
        if ($case == 3) {
            // Update site information
            $site_name = $decoded['site_name'];
            $site_country = $decoded['site_country'];
            $site_address = $decoded['site_address'];
            $site_city = $decoded['site_city'];
            $site_postcode = $decoded['site_postcode'];
            $site_phone = $decoded['site_phone'];
            $site_email = $decoded['site_email'];
            $timezone = $decoded['timezone'];
            $consoleid = $decoded['consoleid'];
            $sqld = "UPDATE Sites SET Site_name = ?, site_country = ?, site_address = ?, site_city = ?, postcode = ?, phone = ?, Email = ?, time_zone = ? WHERE uid = ?";
            // Prepare and bind parameters
            $stmtd = $conn->prepare($sqld);
            $stmtd->bind_param("ssssssssi",$site_name, $site_country, $site_address, $site_city, $site_postcode, $site_phone, $site_email, $timezone, $consoleid);

            // Execute and check if insert was successful
            if ($stmtd->execute()) {
                // Success response
                $response = ['success' => true, 'message' => 'Site updated successfully'];
            } else {
                // Error response
                $response = ['success' => false, 'message' => 'Site update failed'];
            }

            $stmtd->close();
        } elseif ($case == 5) {
            // Update tank capacity and pumps
            $uid = isset($decoded['uid']) ? (int)$decoded['uid'] : 0;
            $site_id = isset($decoded['site_id']) ? (int)$decoded['site_id'] : 0;
            $tanks = isset($decoded['tanks']) ? $decoded['tanks'] : [];
            
            if ($uid > 0 && $site_id > 0 && !empty($tanks)) {
                $conn->begin_transaction();
                $success = true;
                $errors = [];
                
                try {
                    foreach ($tanks as $tank) {
                        $tank_id = isset($tank['tank_id']) ? (int)$tank['tank_id'] : 0;
                        $capacity = isset($tank['capacity']) ? (int)$tank['capacity'] : 0;
                        
                        if ($tank_id > 0) {
                            // Update tank capacity
                            $updateTankSql = "UPDATE Tanks SET capacity = ? WHERE uid = ? AND Site_id = ? AND tank_id = ?";
                            $updateTankStmt = $conn->prepare($updateTankSql);
                            $updateTankStmt->bind_param("iiii", $capacity, $uid, $site_id, $tank_id);
                            
                            if (!$updateTankStmt->execute()) {
                                $success = false;
                                $errors[] = "Failed to update tank $tank_id";
                            }
                            $updateTankStmt->close();
                            
                            // Update pumps if provided
                            if (isset($tank['pumps']) && is_array($tank['pumps'])) {
                                foreach ($tank['pumps'] as $pump) {
                                    $pump_id = isset($pump['pump_id']) ? (int)$pump['pump_id'] : 0;
                                    $nozzle_number = isset($pump['nozzle_number']) ? (int)$pump['nozzle_number'] : 0;
                                    $nozzle_walk_time = isset($pump['nozzle_walk_time']) ? (int)$pump['nozzle_walk_time'] : 0;
                                    $nozzle_auth_time = isset($pump['nozzle_auth_time']) ? (int)$pump['nozzle_auth_time'] : 0;
                                    $nozzle_max_run_time = isset($pump['nozzle_max_run_time']) ? (int)$pump['nozzle_max_run_time'] : 0;
                                    $nozzle_no_flow = isset($pump['nozzle_no_flow']) ? (int)$pump['nozzle_no_flow'] : 0;
                                    $nozzle_product = isset($pump['nozzle_product']) ? $pump['nozzle_product'] : '';
                                    $pulse_rate = isset($pump['pulse_rate']) ? (float)$pump['pulse_rate'] : 0;
                                    
                                    if ($pump_id > 0) {
                                        // Update existing pump
                                        $updatePumpSql = "UPDATE pumps SET Nozzle_Number = ?, Nozzle_Walk_Time = ?, Nozzle_Auth_Time = ?, Nozzle_Max_Run_Time = ?, Nozzle_No_Flow = ?, Nozzle_Product = ?, Pulse_Rate = ? WHERE pump_id = ? AND uid = ? AND tank_id = ?";
                                        $updatePumpStmt = $conn->prepare($updatePumpSql);
                                        $updatePumpStmt->bind_param("iiiiiisdi", $nozzle_number, $nozzle_walk_time, $nozzle_auth_time, $nozzle_max_run_time, $nozzle_no_flow, $nozzle_product, $pulse_rate, $pump_id, $uid, $tank_id);
                                        
                                        if (!$updatePumpStmt->execute()) {
                                            $success = false;
                                            $errors[] = "Failed to update pump $pump_id for tank $tank_id";
                                        }
                                        $updatePumpStmt->close();
                                    }
                                }
                            }
                        }
                    }
                    
                    if ($success) {
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Tanks and pumps updated successfully'];
                    } else {
                        $conn->rollback();
                        $response = ['success' => false, 'message' => 'Some updates failed', 'errors' => $errors];
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid parameters'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid case'];
        }
    } else {
        // JSON decode error response
        $response = ['success' => false, 'message' => 'Error in decoding JSON'];
    }

    // Close the connection
    $conn->close();
    
    // Echo the JSON response
    echo json_encode($response);
    exit;
}
?>
