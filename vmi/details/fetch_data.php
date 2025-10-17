<?php

include('../db/dbh2.php');
include('../db/log.php');



    $groupId = $_POST['groupId'];
    $companyId = $_POST['companyId'];

    // The SQL to fetch required data based on groupId and companyId
    $query = "SELECT * FROM client_site_groups where client_id = $companyId and group_id = $groupId"; // Update with your query
    $result = $conn->query($query);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'siteId' => $row['site_no'],
            'siteName' => $row['site_name'],
            // Add other required fields
        ];
    }

    echo json_encode($data);
?>
