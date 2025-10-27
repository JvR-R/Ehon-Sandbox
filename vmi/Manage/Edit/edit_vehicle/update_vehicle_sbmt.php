<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
include('../../../db/crc.php');
ob_start();

// Include regenerate_vehicles.php to reuse file generation functions
require_once('../../Company/regenerate_vehicles.php');

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
    
    // Handle date fields - convert empty strings to NULL
    $registration_date = (!empty($_POST['registration_date'])) ? $_POST['registration_date'] : null;
    $service_date = (!empty($_POST['service_date'])) ? $_POST['service_date'] : null;
    
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
