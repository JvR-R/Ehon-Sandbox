<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
include('../../../db/crc.php');
ob_start();

// Function to generate VEHICLES.TXT file for a single UID
function generateVehiclesFileForUID($conn, $companyId, $uid) {
    // Create directory if it doesn't exist
    $directory = "/home/ehon/files/fms/cfg/" . $uid;
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Get all vehicles for this client
    $vehiclesStmt = $conn->prepare("SELECT vehicle_id, vehicle_name, vehicle_rego 
                                    FROM vehicles 
                                    WHERE Client_id = ? 
                                    ORDER BY vehicle_id");
    $vehiclesStmt->bind_param("i", $companyId);
    $vehiclesStmt->execute();
    $vehiclesResult = $vehiclesStmt->get_result();
    
    // Build VEHICLES.TXT content
    $vehiclesContent = "";
    while ($vehicle = $vehiclesResult->fetch_assoc()) {
        $line = [];
        
        // Field 1: vehicle_id
        $line[] = $vehicle['vehicle_id'] !== null ? $vehicle['vehicle_id'] : 0;
        
        // Field 2: vehicle_name
        $line[] = $vehicle['vehicle_name'] !== null ? $vehicle['vehicle_name'] : '';
        
        // Field 3: vehicle_rego
        $line[] = $vehicle['vehicle_rego'] !== null ? $vehicle['vehicle_rego'] : '';
        
        $vehiclesContent .= implode(',', $line) . "\n";
    }
    
    $vehiclesStmt->close();
    
    // Write to VEHICLES.TXT
    $filePath = $directory . "/VEHICLES.TXT";
    file_put_contents($filePath, $vehiclesContent);
    
    error_log("VEHICLES.TXT file generated for UID: " . $uid . " at " . $filePath);
}

// Function to generate VEHICLES.TXT files for all UIDs under a client
function generateVehiclesFile($conn, $companyId) {
    // Get all UIDs for this company that have device_type = 10
    $uidStmt = $conn->prepare("SELECT DISTINCT ca.uid 
                               FROM Sites ca
                               INNER JOIN console c ON ca.uid = c.uid
                               WHERE ca.client_id = ? AND c.device_type = 10");
    $uidStmt->bind_param("i", $companyId);
    $uidStmt->execute();
    $uidResult = $uidStmt->get_result();
    
    if ($uidResult->num_rows === 0) {
        error_log("No UIDs found for company ID: " . $companyId . " with device_type = 10");
        $uidStmt->close();
        return;
    }
    
    $uids = [];
    while ($row = $uidResult->fetch_assoc()) {
        $uids[] = $row['uid'];
    }
    $uidStmt->close();
    
    // Generate VEHICLES.TXT for each UID
    foreach ($uids as $uid) {
        generateVehiclesFileForUID($conn, $companyId, $uid);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $vehicle_id = $_POST['vehicle_id'] ?? null;
    if (!$vehicle_id) {
        header("Location: vehicle_edit.php?id=$vehicle_id&error=validation_failed");
        exit;
    }

    $customer_name = $_POST['customer_name'];
    $assist_number = $_POST['assist_number'];
    $odometer_unit = $_POST['odometer_unit'];
    $vehicle_enable = $_POST['vehicle_enable'];
    $vehicle_odometer_prompt = isset($_POST['vehicle_odometer_prompt']) ? 1 : 0;
    $odometer_last = $_POST['odometer_last'];
    $vehicle_brand = $_POST['vehicle_brand'];
    $vehicle_model = $_POST['vehicle_model'];
    $vehicle_name = $_POST['vehicle_name'];
    $vehicle_type = $_POST['vehicle_type'];
    $vehicle_tanksize = $_POST['vehicle_tanksize'];
    $vehicle_rego = $_POST['vehicle_rego'];
    $registration_date = $_POST['registration_date'];
    $service_date = $_POST['service_date'];
    $vehicle_servicekm = $_POST['vehicle_servicekm'];
    $vehicle_reqservicekm = $_POST['vehicle_reqservicekm'];
    $additional_info = $_POST['additional_info'];

    $allowed_products = isset($_POST['allowed_products']) ? json_encode($_POST['allowed_products']) : '[]';

    $sqld = "UPDATE vehicles SET 
                customer_id = ?, vehicle_assetnumber = ?, vehicle_name = ?, odometer_type = ?, 
                allowed_products = ?, odometer_prompt = ?, last_odometer = ?, vehicle_brand = ?, 
                vehicle_model = ?, vehicle_type = ?, vehicle_tanksize = ?, vehicle_rego = ?, 
                vehicle_rego_date = ?, vehicle_service = ?, vehicle_service_km = ?, vehicle_addinfo = ?, 
                vehicle_enabled = ?, Client_id = ?
             WHERE vehicle_id = ?";

    if ($stmtd = $conn->prepare($sqld)) {
        $stmtd->bind_param(
            "iisisissssisssssiii",
            $customer_name, $assist_number, $vehicle_name, $odometer_unit,
            $allowed_products, $vehicle_odometer_prompt, $odometer_last, $vehicle_brand,
            $vehicle_model, $vehicle_type, $vehicle_tanksize, $vehicle_rego,
            $registration_date, $service_date, $vehicle_servicekm, $additional_info,
            $vehicle_enable, $companyId, $vehicle_id
        );

        if ($stmtd->execute()) {
            vehicle_crcdata($companyId);
            generateVehiclesFile($conn, $companyId);
            header("Location: vehicle_edit.php?id=$vehicle_id&success=true");
            exit();
        } else {
            error_log("Execute Failed: " . $stmtd->error);
            header("Location: vehicle_edit.php?id=$vehicle_id&error=execute_failed");
            exit();
        }
        $stmtd->close(); 
    } else {
        error_log("Prepare Failed: " . $conn->error);
        header("Location: vehicle_edit.php?id=$vehicle_id&error=prepare_failed");
        exit();
    }
    $conn->close();
} else {
    header("Location: vehicle_edit.php?id=$vehicle_id&error=invalid_method");
    exit();
}
