<?php
include('../../db/dbh2.php');
header('Content-Type: application/json');

$response = array('status' => 'error', 'message' => 'Unknown error occurred.');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    error_log(json_encode($data)); // Log the incoming data for debugging

    if (isset($data['alert_id'])) {
        $alert_id = $data['alert_id'];
        $sql = "UPDATE active_alerts SET aa_active = 0 WHERE alert_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $alert_id);

        if ($stmt->execute()) {
            $csuid = $data['csuid'];
            $alert_type = $data['alert_type'];
            if($alert_type == 10 || $alert_type == 11 || $alert_type == 12){
            $sqlcs = "UPDATE console SET dv_flag = 0 WHERE uid = ?";
            $stmtcs = $conn->prepare($sqlcs);
            $stmtcs->bind_param("i", $csuid);
            if ($stmtcs->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Alert acknowledged successfully!';
            }
        }
        } else {
            $response['message'] = 'Error acknowledging the alert: ' . $conn->error;
        }

        $stmt->close();
    } else {
        $response['message'] = 'Invalid request data.';
    }
}

$conn->close();

// Output the JSON response as-is, without echoing or concatenating
$jsonResponse = json_encode($response);
if ($jsonResponse !== false) {
    echo $jsonResponse;
} else {
    http_response_code(500);
    echo '{"status":"error","message":"Failed to encode JSON response"}';
}
?>