<?php
// Include necessary files (ensure these do not output HTML)
include('../../../db/dbh2.php'); // Database connection
include('../../../db/log.php');   
include('../../../db/crc.php');

// Function to generate AUTH.TXT file
function generateAuthFile($conn, $companyId) {
    // Get the UID for this company
    $uidStmt = $conn->prepare("SELECT uid FROM console_asociation WHERE client_id = ?");
    $uidStmt->bind_param("i", $companyId);
    $uidStmt->execute();
    $uidResult = $uidStmt->get_result();
    
    if ($uidResult->num_rows === 0) {
        error_log("No UID found for company ID: " . $companyId);
        $uidStmt->close();
        return;
    }
    
    $uidRow = $uidResult->fetch_assoc();
    $uid = $uidRow['uid'];
    $uidStmt->close();
    
    // Create directory if it doesn't exist
    $directory = "/home/ehon/files/fms/cfg/" . $uid;
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Get all tags for this client
    $tagsStmt = $conn->prepare("SELECT id, card_number, pin_number, card_type, list_driver, list_vehicle, 
                                driver_prompt, prompt_vehicle, projectnum_prompt, odo_prompt, enabled_prompt 
                                FROM client_tags 
                                WHERE client_id = ? 
                                ORDER BY id");
    $tagsStmt->bind_param("i", $companyId);
    $tagsStmt->execute();
    $tagsResult = $tagsStmt->get_result();
    
    // Build AUTH.TXT content
    $authContent = "";
    while ($tag = $tagsResult->fetch_assoc()) {
        $line = [];
        
        // Field 1: card_number (or '0')
        $line[] = $tag['card_number'] !== null ? $tag['card_number'] : '0';
        
        // Field 2: pin_number (or '0')
        $line[] = $tag['pin_number'] !== null ? $tag['pin_number'] : '0';
        
        // Field 3: id
        $line[] = $tag['id'] !== null ? $tag['id'] : 0;
        
        // Field 4: card_type (or '0')
        $line[] = $tag['card_type'] !== null ? $tag['card_type'] : '0';
        
        // Field 5: list_driver
        $line[] = $tag['list_driver'] !== null ? intval($tag['list_driver']) : 0;
        
        // Field 6: list_vehicle
        $line[] = $tag['list_vehicle'] !== null ? intval($tag['list_vehicle']) : 0;
        
        // Field 7: driver_prompt (0 if null or 999)
        $driver_prompt_val = $tag['driver_prompt'];
        $line[] = ($driver_prompt_val === null || intval($driver_prompt_val) == 999) ? 0 : intval($driver_prompt_val);
        
        // Field 8: prompt_vehicle (0 if null or 999)
        $prompt_vehicle_val = $tag['prompt_vehicle'];
        $line[] = ($prompt_vehicle_val === null || intval($prompt_vehicle_val) == 999) ? 0 : intval($prompt_vehicle_val);
        
        // Field 9: projectnum_prompt
        $line[] = $tag['projectnum_prompt'] ? intval($tag['projectnum_prompt']) : 0;
        
        // Field 10: odo_prompt
        $line[] = $tag['odo_prompt'] ? intval($tag['odo_prompt']) : 0;
        
        // Field 11: enabled_prompt
        $line[] = $tag['enabled_prompt'] ? intval($tag['enabled_prompt']) : 0;
        
        $authContent .= implode(',', $line) . "\n";
    }
    
    $tagsStmt->close();
    
    // Write to AUTH.TXT
    $filePath = $directory . "/AUTH.TXT";
    file_put_contents($filePath, $authContent);
    
    error_log("AUTH.TXT file generated for UID: " . $uid . " at " . $filePath);
}

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
        
        // Generate AUTH.TXT file
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
