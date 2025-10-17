<?php
include('../../db/dbh2.php');

function balance_insert($conn, $client_id, $uid, $site_id, $tankId, $volume, $date, $time) {
    $transaction_vol = 0;
    $transaction_total = 0;
    $closing_balance = 0;
    $deliveries = 0;
    $dayago = date("Y-m-d", strtotime("-1 day", strtotime($date)));
    $twodaysago = date("Y-m-d", strtotime("-2 day", strtotime($date)));
    $sqltrans = "SELECT sum(dispensed_volume) as total_vol, count(*) as total_transactions
    FROM client_transaction
    WHERE transaction_date BETWEEN ? AND ?
    AND (
        (transaction_date = ? AND transaction_time >= ?)
        OR
        (transaction_date = ? AND transaction_time < ?)
    ) AND uid = ? AND Tank_id = ?
    ";
    $stmttransactions = $conn->prepare($sqltrans);
    $stmttransactions->bind_param("ssssssii", $dayago, $date, $dayago, $time, $date, $time, $uid, $tankId);
    $stmttransactions->execute();
    $stmttransactions->bind_result($transaction_vol, $transaction_total);
    $stmttransactions->fetch();
    $stmttransactions->close();


    $sqldel = "SELECT sum(delivery) as deliveries
    FROM delivery_historic
    WHERE transaction_date BETWEEN ? AND ?
    AND (
        (transaction_date = ? AND transaction_time >= ?)
        OR
        (transaction_date = ? AND transaction_time < ?)
    ) AND uid = ? AND Tank_id = ?
    ";
    $stmtdel = $conn->prepare($sqldel);
    $stmtdel->bind_param("ssssssii", $dayago, $date, $dayago, $time, $date, $time, $uid, $tankId);
    $stmtdel->execute();
    $stmtdel->bind_result($deliveries);
    $stmtdel->fetch();
    $stmtdel->close();
    // echo "$dayago, $date, $dayago, $time, $date, $time, $uid, $tankId\n";

    $prevsql = "SELECT Closing_balance FROM clients_recconciliation WHERE uid = ? AND tank_id = ? AND Date = ?";
    $cbalance = $conn->prepare($prevsql);
    $cbalance->bind_param("iis", $uid, $tankId, $twodaysago);
    $cbalance->execute();
    $cbalance->bind_result($closing_balance);
    $cbalance->fetch();
    $cbalance->close();

    if(empty($deliveries)){
        $deliveries = 0;
    }
    if(empty($transaction_total)){
        $transaction_total = 0;
        $transaction_vol = 0;
    }
    $delta = $volume - $closing_balance;
    $reconciliation = $deliveries + $transaction_vol + $delta;
    $insertsql = "INSERT INTO clients_recconciliation (client_id, uid, Site_id, Tank_id, Closing_balance, Total_transaction, Total_volume, Total_Deliveries, Delta, Date, reconciliation, Opening_balance) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert = $conn->prepare($insertsql);
    $insert->bind_param("iiiissssssss", $client_id, $uid, $site_id, $tankId, $volume, $transaction_total, $transaction_vol, $deliveries, $delta, $dayago, $reconciliation, $closing_balance);
    $insert->execute();
    $insert->close();


    // echo "SELECT Closing_balance FROM clients_recconciliation WHERE uid = $uid AND tank_id = $tankId AND Date = $twodaysago\n";
    // echo "$client_id, $uid, $site_id, $tankId, $volume, $transaction_total, $transaction_vol, $deliveries, $delta, $dayago\n";
}
?>
