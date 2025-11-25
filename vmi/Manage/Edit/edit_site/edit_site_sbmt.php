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
        // Retrieve data from decoded JSON
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
            $response = ['success' => true, 'message' => 'Site inserted successfully'];
        } else {
            // Error response
            $response = ['success' => false, 'message' => 'Site insertion failed'];
        }

        $stmtd->close();
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
