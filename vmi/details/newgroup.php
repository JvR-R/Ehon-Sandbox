<?php
  include('../db/dbh2.php');
  include('../db/log.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    //Retrieve the companyId and feeType values from the POST data
    $companyId = $_POST['companyId'] ?? '';
    $groupname = $_POST['groupname'];
    
    
    $sql_last_id = "SELECT MAX(group_id) as max_id FROM site_groups";
    $result = $conn->query($sql_last_id);
    if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_id = $row["max_id"] + 1; // Increment the last/bigger id by 1
    } elseif($last_id == 1) {
    // If the database is empty, start from 1
    $last_id = 1000;
    }
    else{
        $last_id = 1000;
    }


    $sql = "INSERT INTO site_groups (group_id, client_id, group_name) 
    VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $last_id, $companyId, $groupname);

    if ($stmt->execute()) {
        echo "<script>
                alert('Goup Created');
                window.location.href = '/vmi/details/groups';
              </script>";
    } else {
        echo "<script>
                alert('Error');
                window.location.href = '/vmi/details/groups';
              </script>";
    }
    $stmt->close();

}
else{
    echo "error post";
}

?>