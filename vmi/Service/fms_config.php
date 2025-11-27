<?php
include('../db/dbh2.php');

header('Content-Type: application/json');

// Handle GET request - load configuration
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    
    if ($uid <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid UID']);
        exit;
    }
    
    $sql = "SELECT * FROM config_ehon_fms WHERE idconfig_ehon_fms = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No configuration found']);
    }
    exit;
}

// Handle POST request - save configuration and create CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    
    if ($uid <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid UID']);
        exit;
    }
    
    $nozzle_trigger_timeout_ms = isset($_POST['nozzle_trigger_timeout_ms']) ? intval($_POST['nozzle_trigger_timeout_ms']) : 30000;
    $pulse_inactive_timeout_ms = isset($_POST['pulse_inactive_timeout_ms']) ? intval($_POST['pulse_inactive_timeout_ms']) : 30000;
    $max_pulse_duration_timeout_ms = isset($_POST['max_pulse_duration_timeout_ms']) ? intval($_POST['max_pulse_duration_timeout_ms']) : 900000;
    $driver_auth_timeout_ms = isset($_POST['driver_auth_timeout_ms']) ? intval($_POST['driver_auth_timeout_ms']) : 30000;
    $pump_selection_timeout_ms = isset($_POST['pump_selection_timeout_ms']) ? intval($_POST['pump_selection_timeout_ms']) : 30000;
    $tank_gauging_method = isset($_POST['tank_gauging_method']) ? trim($_POST['tank_gauging_method']) : 'MODBUS';
    $tank_ocio_number = isset($_POST['tank_ocio_number']) ? intval($_POST['tank_ocio_number']) : 0;
    
    // Sanitize tank_gauging_method to allowed values (convert to uppercase for consistency)
    $tank_gauging_method = strtoupper(trim($tank_gauging_method));
    $allowed_methods = ['MODBUS', 'OCIO', 'NONE'];
    if (!in_array($tank_gauging_method, $allowed_methods)) {
        $tank_gauging_method = 'MODBUS';
    }
    
    // Check if record exists
    $checkSql = "SELECT idconfig_ehon_fms FROM config_ehon_fms WHERE idconfig_ehon_fms = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $uid);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $sql = "UPDATE config_ehon_fms SET 
                nozzle_trigger_timeout_ms = ?,
                pulse_inactive_timeout_ms = ?,
                max_pulse_duration_timeout_ms = ?,
                driver_auth_timeout_ms = ?,
                pump_selection_timeout_ms = ?,
                tank_gauging_method = ?,
                tank_ocio_number = ?
                WHERE idconfig_ehon_fms = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiisii", 
            $nozzle_trigger_timeout_ms,
            $pulse_inactive_timeout_ms,
            $max_pulse_duration_timeout_ms,
            $driver_auth_timeout_ms,
            $pump_selection_timeout_ms,
            $tank_gauging_method,
            $tank_ocio_number,
            $uid
        );
    } else {
        // Insert new record with specific ID
        $sql = "INSERT INTO config_ehon_fms 
                (idconfig_ehon_fms, nozzle_trigger_timeout_ms, pulse_inactive_timeout_ms, max_pulse_duration_timeout_ms, 
                 driver_auth_timeout_ms, pump_selection_timeout_ms, tank_gauging_method, tank_ocio_number) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiisi", 
            $uid,
            $nozzle_trigger_timeout_ms,
            $pulse_inactive_timeout_ms,
            $max_pulse_duration_timeout_ms,
            $driver_auth_timeout_ms,
            $pump_selection_timeout_ms,
            $tank_gauging_method,
            $tank_ocio_number
        );
    }
    
    if ($stmt->execute()) {
        // Create CSV file - using public_html directory to comply with open_basedir restrictions
        $csv_dir = "/home/ehon/files/fms/cfg/{$uid}";
        
        // Create directory if it doesn't exist with group-writable permissions
        if (!is_dir($csv_dir)) {
            $old_umask = umask(0);
            if (!mkdir($csv_dir, 0775, true)) {
                umask($old_umask);
                $error = error_get_last();
                echo json_encode(['success' => false, 'error' => 'Failed to create directory: ' . ($error ? $error['message'] : 'Unknown error')]);
                exit;
            }
            umask($old_umask);
            // Ensure the directory is group-writable
            chmod($csv_dir, 0775);
        }
        
        // Build CSV content
        $csv_content = "sound_enabled,1\n\n";
        $csv_content .= "nozzle_trigger_timeout_ms,{$nozzle_trigger_timeout_ms}\n";
        $csv_content .= "pulse_inactive_timeout_ms,{$pulse_inactive_timeout_ms}\n";
        $csv_content .= "max_pulse_duration_timeout_ms,{$max_pulse_duration_timeout_ms}\n";
        $csv_content .= "driver_auth_timeout_ms,{$driver_auth_timeout_ms}\n";
        $csv_content .= "pump_selection_timeout_ms,{$pump_selection_timeout_ms}\n";
        $csv_content .= "tank_gauging_method,{$tank_gauging_method}\n";
        $csv_content .= "tank_ocio_number,{$tank_ocio_number}\n";
        
        // Write CSV file
        $csv_file = "{$csv_dir}/CONFIG.CSV";
        if (file_put_contents($csv_file, $csv_content) !== false) {
            echo json_encode(['success' => true, 'message' => 'Configuration saved and CSV created', 'csv_path' => $csv_file]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database saved but failed to create CSV file']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $stmt->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
?>

