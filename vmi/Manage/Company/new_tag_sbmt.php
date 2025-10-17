<?php
// Suppress error display (recommended for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header to return JSON
header('Content-Type: application/json');

// Include necessary files (ensure these do not output HTML)
include('../../db/dbh2.php'); // Database connection
include('../../db/log.php');   // Logging utility
include('../../db/crc.php');   // Logging utility

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'received_data' => []
];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize POST data
    $customer_id = isset($_POST['customer_name']) ? intval($_POST['customer_name']) : '';
    $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
    $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $card_type = isset($_POST['card_type']) ? intval($_POST['card_type']) : 0;
    $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : 0;
    $enabled_prompt = isset($_POST['enabled_prompt']) ? 1 : 0;
    $pin_number = isset($_POST['pin_number']) ? trim($_POST['pin_number']) : '';
    $pin_prompt = isset($_POST['pin_prompt']) ? 1 : 0;
    $prompt_vehicle = isset($_POST['prompt_vehicle']) ? intval($_POST['prompt_vehicle']) : 0;
    $driver_prompt = isset($_POST['driver_prompt']) ? intval($_POST['driver_prompt']) : 0;
    $projectnum_prompt = isset($_POST['projectnum_prompt']) ? 1 : 0;
    $odo_prompt = isset($_POST['odo_prompt']) ? 1 : 0;
    $additional_info = isset($_POST['additional_info']) ? trim($_POST['additional_info']) : '';
    $list_driver = isset($_POST['list_driver']) ? 1 : 0;
    $list_vehicle = isset($_POST['list_vehicle']) ? 1 : 0;

    if(!empty($pin_number) && $card_type == 1) {
        $card_type = 2;
    }

    // Validate required fields
    if ($customer_id <= 0) {
        $response['message'] = 'Invalid Customer.';
        echo json_encode($response);
        exit;
    }

    if (empty($card_name)) {
        $response['message'] = 'Card Name is required.';
        echo json_encode($response);
        exit;
    }

    if (empty($card_number) && empty($pin_number)) {
        $response['message'] = 'Please provide either a Card Number, a PIN Number, or both.';
        echo json_encode($response);
        exit;
    }

    // If pin_number is provided, validate it
    if (!empty($pin_number) && !preg_match('/^\d{4}$/', $pin_number)) {
        $response['message'] = 'PIN Number must be exactly 4 digits.';
        echo json_encode($response);
        exit;
    }
    if ($card_number === '') {
        $card_number = null; // Use null instead of empty string
    }
    if ($pin_number === '') {
        $pin_number = null; // Use null instead of empty string
    }
    if ($card_type > 100) {
        $response['message'] = 'Invalid Card Type.';
        echo json_encode($response);
        exit;
    }

    if (empty($expiry_date)) {
        $response['message'] = 'Expiry Date is required.';
        echo json_encode($response);
        exit;
    }

    // Optional: Further validations (e.g., date format, card number format)

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO client_tags 
        (client_id, customer_id, card_name, card_number, card_type, expiry_date, enabled_prompt, pin_number, pin_prompt, prompt_vehicle, driver_prompt, projectnum_prompt, odo_prompt, additional_info, list_driver, list_vehicle)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        $response['message'] = 'Database error: Unable to prepare statement.';
        echo json_encode($response);
        exit;
    }
    $stmt->bind_param(
        "iissisisiiiiisii",
        $companyId,
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
        $list_vehicle 
    );
    try {
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Data received and stored successfully.';
            $response['received_data'] = $_POST; // Optionally, send back sanitized data
            tag_crcdata($companyId);
        } 
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {  // Duplicate entry error code
            $response['message'] = 'Duplicate tag: This tag already exists in the database.';
        } else {
            error_log("Database insertion error: " . $e->getMessage());
            $response['message'] = 'Failed to store data due to a database error.';
        }
    }

    $stmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);

?>
