<?php
  include('../db/dbh2.php');
  include('../db/log.php');
  
    $message_id = $_POST['id'];
    $message_content = $_POST['content'];

    $query = "UPDATE messages SET message_content = ? WHERE message_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $message_content, $message_id);
    $stmt->execute();
?>
