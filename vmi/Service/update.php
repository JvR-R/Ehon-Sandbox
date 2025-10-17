<?php
include('../db/dbh2.php');

header('Content-Type: application/json'); // Ensure the response is JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $case = $_POST['case'];
  if ($case == 1){
    $clientId = $_POST['client_id'];
    $column = $_POST['column'];
    $newValue = $_POST['value'];

    // Validate the input values
    if ($column === 'Client_phone' || $column === 'Client_email') {
      // Prepare SQL to update the correct column
      $sql = "UPDATE Clients SET $column = ? WHERE client_id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("sd", $newValue, $clientId);

      if ($stmt->execute()) {
        echo json_encode(['success' => true]);
      } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed.']);
      }
    } else {
      echo json_encode(['success' => false, 'error' => 'Invalid column name.']);
    }
  }
  else if($case == 2){
    $uid = $_POST['uid'];
    $ticket = $_POST['ticket'];
    $comment = $_POST['comment'];
    $updflag = 1;
    $sql = "UPDATE console SET service_flag = ? WHERE uid = ?";
    $stmtcs = $conn->prepare($sql);
    $stmtcs->bind_param("ii", $updflag, $uid);

    if ($stmtcs->execute()) {
      $insertquery = "
        INSERT INTO service_ticket (uid, ticket_id, ticket_comment)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            ticket_id = VALUES(ticket_id),
            ticket_comment = VALUES(ticket_comment)
      ";
      $insertexec = $conn->prepare($insertquery);
      $insertexec->bind_param("iis", $uid, $ticket, $comment);
      if ($insertexec->execute()){
        echo json_encode(['success' => true]);
      } else {
        echo json_encode(['success' => false, 'error' => 'Database sv_Ticket update failed.']);
      }
    } else {
      echo json_encode(['success' => false, 'error' => 'Database Console update failed.']);
    }
  }
} else {
  echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
