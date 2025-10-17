<?php
include('../db/dbh2.php');

$query = "UPDATE console SET restart_flag = 1 WHERE uid in (398335, 398348)";
$upd = $conn->prepare($query);
$upd->execute();
$upd->close();

$dateThreshold = date('Y-m-d', strtotime('-3 days'));

$query2 = "UPDATE console 
    SET restart_flag = 1 
    WHERE uid IN (
        SELECT uid FROM Tanks WHERE dipr_date <= ?
    ) AND restart_flag != 1 AND device_type = 20";

$upd2 = $conn->prepare($query2);
$upd2->bind_param("s", $dateThreshold);
$upd2->execute();
$upd2->close();


?>