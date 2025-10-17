<?php
include('../db/dbh2.php');
include('../db/log.php');   
header('Content-Type: application/json');

if(isset($_POST['group_id'])) {
    $group_id = $_POST['group_id'];
    
    $sql = "SELECT site_no, site_name FROM client_site_groups WHERE group_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $group_id);

if (!$stmt->execute()) {
    die("Execution failed: " . $stmt->error);
}

// Bind the result columns to variables
$stmt->bind_result($site_no, $site_name);

$sites = array();
while ($stmt->fetch()) {
    $sites[] = array(
        "site_no" => $site_no,
        "site_name" => $site_name
    );
}

$stmt->close();

  $response['response'] = $sites;
  
    if($sites) {
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'No sites found or database query error.']);
    }
}
?>
