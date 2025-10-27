<?php
// Include necessary files (ensure these do not output HTML)
include('../../../db/dbh2.php'); // Database connection
include('../../../db/log.php');   
include('../../../db/crc.php');

// Include regenerate_auth.php to reuse file generation functions
require_once('../../Company/regenerate_auth.php');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize POST data
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $customer_id = isset($_POST['customer_name']) ? intval($_POST['customer_name']) : 0;
    $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
    $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $card_type = isset($_POST['card_type']) ? intval($_POST['card_type']) : 0;
    $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    $enabled_prompt = isset($_POST['enabled_prompt']) ? 1 : 0;
    $pin_number = isset($_POST['pin_number']) ? trim($_POST['pin_number']) : '';
    $pin_prompt = isset($_POST['pin_prompt']) ? 1 : 0;
    $prompt_vehicle = isset($_POST['prompt_vehicle']) ? intval($_POST['prompt_vehicle']) : 0;
    $driver_prompt = isset($_POST['prompt_driver']) ? intval($_POST['prompt_driver']) : 0;
    $projectnum_prompt = isset($_POST['projectnum_prompt']) ? 1 : 0;
    $odo_prompt = isset($_POST['odo_prompt']) ? 1 : 0;
    $additional_info = isset($_POST['additional_info']) ? trim($_POST['additional_info']) : '';
    $list_driver = isset($_POST['list_driver']) ? trim($_POST['list_driver']) : '';
    $list_vehicle = isset($_POST['list_vehicle']) ? trim($_POST['list_vehicle']) : '';

    // Validate required fields
    if ($id <= 0 || $customer_id <= 0 || empty($card_name) || $card_type >= 10 || empty($expiry_date)) {
        header("Location: /vmi/Manage/Edit/edit_tags?error=validation_failed");
        exit;
    }
    
    // Validate PIN if provided and it's a new 4-digit PIN
    if (!empty($pin_number) && strlen($pin_number) <= 4) {
        // Only validate format if it's a new PIN (4 or fewer characters)
        if (!preg_match('/^\d{4}$/', $pin_number)) {
            header("Location: /vmi/Manage/Edit/edit_tags?error=invalid_pin");
            exit;
        }
    }
    // If PIN is longer than 4 digits, it's the existing value from DB, keep it as is
    
    if(!empty($pin_number) && $card_type == 1) {
        $card_type = 2;
    }
    if ($pin_number === '') {
        $pin_number = null; // Use null instead of empty string
    }
    if ($card_number === '') {
        $card_number = null; // Use null instead of empty string
    }
    // Prepare and bind
    $stmt = $conn->prepare("UPDATE client_tags 
        SET customer_id = ?, card_name = ?, card_number = ?, card_type = ?, expiry_date = ?, enabled_prompt = ?, 
            pin_number = ?, pin_prompt = ?, prompt_vehicle = ?, driver_prompt = ?, projectnum_prompt = ?, odo_prompt = ?, additional_info = ?, list_driver = ?, list_vehicle = ?
        WHERE id = ?");

    if ($stmt === false) {
        // Log the error for debugging
        error_log("Database prepare statement error: " . $conn->error);
        header("Location: /vmi/Manage/Edit/edit_tags?error=prepare_failed");
        exit;
    }

    $stmt->bind_param(
        "issisiiiiiiisiii",
        $customer_id,
        $card_name,
        $card_number,
        $card_type,
        $expiry_date,
        $enabled_prompt,
        $pin_number,
        $pin_prompt,
        $prompt_vehicle,
        $driver_prompt,
        $projectnum_prompt,
        $odo_prompt,
        $additional_info,
        $list_driver,
        $list_vehicle,
        $id
    );

    // Execute the statement
    if ($stmt->execute()) {
        // On success, redirect to the desired page
        tag_crcdata($companyId);
        
        // Generate AUTH.CSV file
        generateAuthFile($conn, $companyId);
        
        header("Location: /vmi/Manage/Edit/edit_tags?success=true");
        exit;
    } else {
        // Log the error for debugging
        error_log("Database update error: " . $stmt->error);
        header("Location: /vmi/Manage/Edit/edit_tags?error=execute_failed");
        exit;
    }

    // Close the statement
    $stmt->close();
} else {
    // Invalid request method
    header("Location: /vmi/Manage/Edit/edit_tags?error=invalid_method");
    exit;
}
?>
