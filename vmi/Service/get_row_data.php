<?php
// get_row_data.php

// Include necessary files and check access level
include('../db/dbh2.php');   // This file should define $conn for database connection
include('../db/log.php');    // Include logging if necessary

// Check if 'uid' parameter is provided
if (isset($_GET['uid']) && !empty($_GET['uid'])) {
    $uid = $_GET['uid'];

    // Prepare the SQL query using a prepared statement to prevent SQL injection
    $sql = "
        SELECT 
            cs.firmware,
            cs.fw_flag, 
            cs.cfg_flag, 
            cs.restart_flag, 
            cs.logs_flag, 
            cs.bootup, 
            st.ticket_id, 
            st.ticket_comment, 
            cs.console_imei,
            cs.uid,
            cs.device_id
        FROM console cs
        JOIN service_ticket st ON st.uid = cs.uid
        WHERE cs.uid = ?
    ";

    // Initialize the prepared statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind the 'uid' parameter as an integer
        $stmt->bind_param("i", $uid);

        // Execute the statement
        if ($stmt->execute()) {
            // Get the result
            $result = $stmt->get_result();

            // Check if any data was returned
            if ($result->num_rows > 0) {
                // Fetch the data as an associative array
                $data = $result->fetch_assoc();

                // Return the data as JSON
                header('Content-Type: application/json');
                echo json_encode($data);
            } else {
                $sqlcs = "SELECT firmware, fw_flag, cfg_flag, restart_flag, logs_flag, bootup, console_imei, device_id  FROM console cs WHERE uid =  ?";
                if ($stmtcs = $conn->prepare($sqlcs)) {
                    // Bind the 'uid' parameter as an integer
                    $stmtcs->bind_param("i", $uid);
            
                    // Execute the statement
                    if ($stmtcs->execute()) {
                        // Get the result
                        $resultcs = $stmtcs->get_result();
            
                        // Check if any data was returned
                        if ($resultcs->num_rows > 0) {
                            $datacs = $resultcs->fetch_assoc();

                            // Return the data as JSON
                            header('Content-Type: application/json');
                            echo json_encode($datacs);
                        } else {
                            // No data found for the given uid
                            http_response_code(404);
                            echo json_encode(['status' => 'error', 'message' => 'No data found for uid']);
                        }
                    
                    }
                
                }
            }
        } else {
            // Error executing statement
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error during execution.']);
        }

        // Close the statement
        $stmt->close();
    } else {
        // Error preparing statement
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error during preparation.']);
    }
} else {
    // 'uid' parameter is missing
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No uid provided']);
}

// Close the database connection if needed (depends on how $conn is managed)
// $conn->close(); // Uncomment if $conn is not persistent

?>
