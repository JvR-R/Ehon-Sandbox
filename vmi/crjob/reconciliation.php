<?php
include('../db/dbh2.php');

// $date = '2024-07-01'; // Example date, replace with your actual date value
$date2 = date("Y-m-d", strtotime("-1 day", strtotime($date)));
echo "Previous date: $date, new date: $date2<br>";

// echo $date . "<br>";
$sqlcvol = "SELECT ts.client_id, ts.uid, ts.Site_id, ts.tank_id, max(dh.transaction_time), dh.current_volume FROM Tanks ts JOIN dipread_historic dh on 
(dh.uid, dh.tank_id) = (ts.uid, ts.tank_id) WHERE ts.uid in (SELECT uid FROM console WHERE device_type = 201) and 
dh.transaction_date = ? group by ts.uid, ts.tank_id;";
$querycvol = $conn->prepare($sqlcvol);
$querycvol->bind_param("s", $date2);
$querycvol->execute();
$querycvol->store_result();
$querycvol->bind_result($client_id, $uid, $site_id, $tank_id, $tras_date, $current_volume);

if ($querycvol) {
    // Loop through each result
    while ($querycvol->fetch()) {
        // Perform your desired action with each row
        
        $sqltrvol = "SELECT COUNT(*), SUM(dispensed_volume) FROM client_transaction WHERE uid = ? AND tank_id = ? AND transaction_Date   = ?";
        $querytransvol = $conn->prepare($sqltrvol);
        $querytransvol->bind_param("iis", $uid, $tank_id, $date);
        $querytransvol->execute();
        $querytransvol->bind_result($transcount, $transvolume);
        $querytransvol->fetch();
        $voltransaction = number_format($transvolume, 2);
        // Example: Print the current volume
        echo "Client ID: $client_id, UID: $uid, Site ID: $site_id, Tank ID: $tank_id, Current Volume: $current_volume, Transactions: $transcount, Trans Volume: $voltransaction<br>";

        // Close the statement for the current transaction volume query
        $querytransvol->close();
    }
    // Close the main query result set after the loop
    $querycvol->close();
} else {
    echo "Query execution failed: " . $conn->error;
}
?>
