<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
include('../../db/crc.php');

ob_start(); // Begin output buffering

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
    // Retrieve data from POST
    $customer_name          = $_POST['customer_name'];
    $assist_number          = $_POST['assist_number'] ?? 0;
    $odometer_unit          = $_POST['odometer_unit'] ?? 1;
    $vehicle_enable         = $_POST['vehicle_enable'] ?? 1;
    $vehicle_odometer_prompt= $_POST['vehicle_odometer_prompt'] ?? 0;
    $odometer_last          = $_POST['odometer_last'] ?? 0; // Provide a default if needed
    $vehicle_brand          = $_POST['vehicle_brand'];
    $vehicle_model          = $_POST['vehicle_model'];
    $vehicle_name           = $_POST['vehicle_name'];
    $vehicle_type           = $_POST['vehicle_type'];
    $vehicle_tanksize       = $_POST['vehicle_tanksize'];
    $vehicle_rego           = $_POST['vehicle_rego'];
    $registration_date      = $_POST['registration_date'];
    $service_date           = $_POST['service_date'];
    $vehicle_servicekm      = $_POST['vehicle_servicekm'];
    $vehicle_reqservicekm   = $_POST['vehicle_reqservicekm'];
    $additional_info        = $_POST['additional_info'];
    
    // Process allowed products
    if (isset($_POST['allowed_products'])) {
        $selectedConsoles = is_array($_POST['allowed_products']) ? $_POST['allowed_products'] : [$_POST['allowed_products']];
        // Remove any debug echoes if not required
    } else {
        $selectedConsoles = [];
    }
    $selectedConsolesJson = json_encode($selectedConsoles);
    
    // Prepare the SQL insert statement
    $sqld = "INSERT INTO vehicles 
             (customer_id, vehicle_assetnumber, vehicle_name, odometer_type, allowed_products, odometer_prompt, last_odometer, vehicle_brand, vehicle_model, vehicle_type, vehicle_tanksize, vehicle_rego_date, vehicle_service, vehicle_service_km, vehicle_addinfo, vehicle_enabled, Client_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtd = $conn->prepare($sqld);
    // Bind parameters (adjust type string if necessary)
    $stmtd->bind_param(
        "iisisiisssissssii",
        $customer_name, 
        $assist_number, 
        $vehicle_name, 
        $odometer_unit, 
        $selectedConsolesJson, 
        $vehicle_odometer_prompt, 
        $odometer_last, 
        $vehicle_brand, 
        $vehicle_model, 
        $vehicle_type, 
        $vehicle_tanksize, 
        $registration_date, 
        $service_date, 
        $vehicle_servicekm, 
        $additional_info, 
        $vehicle_enable, 
        $companyId  // Assuming $companyId is defined in one of your includes
    );
    
    // Execute the query and set toastr session messages accordingly
    if ($stmtd->execute()) {
        // On success, set a success toastr message
        $_SESSION['toastr_msg']  = "Inserted successfully!";
        $_SESSION['toastr_type'] = "success";
        
        // Optionally call additional functions
        vehicle_crcdata($companyId);
        generateVehiclesFile($conn, $companyId);
    } else {
        // On failure, set an error toastr message
        $_SESSION['toastr_msg']  = "Vehicle Creation Error";
        $_SESSION['toastr_type'] = "error";
    }
    
    $stmtd->close();
    $conn->close();
    
    // Redirect back to your vehicle form page (adjust the URL as needed)
    header("Location: new_vehicle.php");
    exit();
}
?>
