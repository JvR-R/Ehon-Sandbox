<?php
/**
 * Tank Balance Reconciliation Script
 * 
 * This script calculates and reconciles tank balances by comparing:
 * - Opening balance (previous day's closing balance)
 * - Closing balance (current tank volume)
 * - Transactions (fuel dispensed)
 * - Deliveries (fuel received)
 * 
 * The reconciliation identifies discrepancies between theoretical and actual changes.
 * 
 * Usage: balance.php?date=YYYY-MM-DD (optional, defaults to current date)
 */

include('../db/dbh2.php');

// Get the date parameter or use current date
$datetest = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// $datetest = '2025-09-26';
// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $datetest)) {
    die("Invalid date format. Please use Y-m-d format.");
}

// Subtract 2 days for $datetest2
$date2 = new DateTime($datetest);
$date2->modify('-2 day');
$datetest2 = $date2->format('Y-m-d'); // $datetest2 is '2024-08-13'

// Subtract 1 day for $datetest1
$date1 = new DateTime($datetest);
$date1->modify('-1 day');
$datetest1 = $date1->format('Y-m-d'); // $datetest1 is '2024-08-14'

echo "testing $datetest<br>";

$sqlcvol = "SELECT
    COALESCE(ca.client_id, s.Client_id, 0) as client_id,
    t1.uid,
    t1.transaction_date,
    t1.transaction_time,
    t1.tank_id,
    t1.current_volume,
    COALESCE(s.Site_id, t1.site_id, 0) as site_id
FROM
    dipread_historic t1
JOIN (
    SELECT
        uid,
        transaction_date,
        tank_id,
        MIN(ABS(TIME_TO_SEC(transaction_time) - TIME_TO_SEC('05:00:00'))) AS min_diff
    FROM
        dipread_historic
    GROUP BY
        uid,
        transaction_date,
        tank_id
) t2 ON t1.uid = t2.uid
   AND t1.transaction_date = t2.transaction_date
   AND t1.tank_id = t2.tank_id
   AND ABS(TIME_TO_SEC(t1.transaction_time) - TIME_TO_SEC('05:00:00')) = t2.min_diff
LEFT JOIN Console_Asociation ca ON ca.uid = t1.uid
LEFT JOIN sites s ON s.uid = t1.uid
WHERE
    (t1.uid IN (SELECT uid FROM console WHERE device_type != 999))
    AND t1.transaction_date = ?;";

$querycvol = $conn->prepare($sqlcvol);
if (!$querycvol) {
    die("Prepare failed for main query: " . $conn->error);
}

$querycvol->bind_param("s", $datetest);
if (!$querycvol->execute()) {
    die("Execute failed for main query: " . $querycvol->error);
}

$querycvol->store_result();
$querycvol->bind_result($client_id, $uid, $transaction_date, $transaction_time, $tank_id, $current_volume, $site_id);

// Prepare reusable statements outside the loop for better performance
$sqlprevdip = "SELECT Closing_balance FROM clients_recconciliation WHERE uid = ? AND tank_id = ? AND Date = ?;";
$queryprevdip = $conn->prepare($sqlprevdip);
if (!$queryprevdip) {
    die("Prepare failed for previous balance query: " . $conn->error);
}

$sqltrans = "SELECT sum(dispensed_volume) as total_vol, count(*) as total_transactions
    FROM client_transaction
    WHERE transaction_date BETWEEN ? AND ?
    AND (
        (transaction_date = ? AND transaction_time >= ?)
        OR
        (transaction_date = ? AND transaction_time < ?)
    ) AND uid = ? AND Tank_id = ?";
$stmttransactions = $conn->prepare($sqltrans);
if (!$stmttransactions) {
    die("Prepare failed for transactions query: " . $conn->error);
}

$sqldel = "SELECT sum(delivery) as deliveries
    FROM delivery_historic
    WHERE transaction_date BETWEEN ? AND ?
    AND (
        (transaction_date = ? AND transaction_time >= ?)
        OR
        (transaction_date = ? AND transaction_time < ?)
    ) AND uid = ? AND Tank_id = ?";
$stmtdel = $conn->prepare($sqldel);
if (!$stmtdel) {
    die("Prepare failed for deliveries query: " . $conn->error);
}

$sqlInsert = "INSERT INTO clients_recconciliation (
    client_id, uid, Site_id, Tank_id, Opening_balance, Closing_balance, 
    Total_transaction, Total_volume, Total_deliveries, Delta, reconciliation, 
    reconciliation_flag, Date
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
$queryInsert = $conn->prepare($sqlInsert);
if (!$queryInsert) {
    die("Prepare failed for insert query: " . $conn->error);
}

// Loop through each result of querycvol
while ($querycvol->fetch()) {
    $queryprevdip->bind_param("iis", $uid, $tank_id, $datetest2);
    if (!$queryprevdip->execute()) {
        echo "Error executing previous balance query for UID: $uid, Tank ID: $tank_id<br>";
        continue;
    }
    $queryprevdip->store_result();
    $queryprevdip->bind_result($Closing_balance_prev);
    echo "Processing UID: $uid, Tank ID: $tank_id, Site ID: $site_id, Date: $datetest2<br>";

    // Fetch the result of queryprevdip
    if ($queryprevdip->fetch()) {
        // Previous Closing_balance found
        $Opening_balance = (double) $Closing_balance_prev; // Use previous Closing_balance
    } else {
        // No previous Closing_balance found
        $Opening_balance = 0.0; // Default to 0
    }

    // Set the current day's closing balance
    $Closing_balance = (double) $current_volume; // Current volume as Closing_balance

    // Calculate Total_volume (change in tank volume)
    $Total_volume = $Closing_balance - $Opening_balance;

    // Define date and time variables for the transaction and delivery queries
    $date = $datetest;       // Current date
    $dayago = $datetest1;    // Date minus 1 day
    $time = '05:00:00';

    // Execute the transactions query
    $stmttransactions->bind_param("ssssssii", $dayago, $date, $dayago, $time, $date, $time, $uid, $tank_id);
    if (!$stmttransactions->execute()) {
        echo "Error executing transactions query for UID: $uid, Tank ID: $tank_id<br>";
        $transaction_vol = 0;
        $transaction_total = 0;
    } else {
        $stmttransactions->bind_result($transaction_vol, $transaction_total);
        $stmttransactions->fetch();
    }
    $stmttransactions->free_result();

    // Execute the deliveries query
    $stmtdel->bind_param("ssssssii", $dayago, $date, $dayago, $time, $date, $time, $uid, $tank_id);
    if (!$stmtdel->execute()) {
        echo "Error executing deliveries query for UID: $uid, Tank ID: $tank_id<br>";
        $deliveries = 0;
    } else {
        $stmtdel->bind_result($deliveries);
        $stmtdel->fetch();
    }
    $stmtdel->free_result();

    // Now assign the results, handling possible NULL values
    $Total_transaction = (double) ($transaction_vol ?? 0.0); // Total volume dispensed
    $Total_deliveries = (double) ($deliveries ?? 0.0);       // Total deliveries

    // Calculate Delta (should be negative if volume decreased)
    $Delta = $Opening_balance - $Closing_balance;

    // Calculate reconciliation (theoretical vs actual change)
    $theoretical_change = $Total_deliveries - $Total_transaction; // What should have happened
    $actual_change = $Total_volume; // What actually happened
    $reconciliation = $theoretical_change - $actual_change; // Difference (loss/gain)
    $reconciliation_flag = abs($reconciliation) > 1.0 ? 1 : 0; // Flag if reconciliation difference is significant
    $Date = $datetest1;       // Use date minus 1 day

    // Validate and handle missing site_id
    if ($site_id === null || $site_id === '' || $site_id === 0) {
        echo "⚠ Warning: site_id is missing for UID: $uid, Tank ID: $tank_id. Record not found in sites table. Skipping record.<br>";
        $queryprevdip->free_result();
        continue;
    }

    // Execute the INSERT query using pre-prepared statement
    $queryInsert->bind_param(
        "iiiiddddddiis",
        $client_id,           // client_id (int)
        $uid,                 // uid (int)
        $site_id,             // Site_id (int)
        $tank_id,             // Tank_id (int)
        $Opening_balance,     // Opening_balance (double)
        $Closing_balance,     // Closing_balance (double)
        $Total_transaction,   // Total_transaction (double)
        $Total_volume,        // Total_volume (double)
        $Total_deliveries,    // Total_deliveries (double)
        $Delta,               // Delta (double)
        $reconciliation,      // reconciliation (double)
        $reconciliation_flag, // reconciliation_flag (int)
        $Date                 // Date (string)
    );

    if ($queryInsert->execute()) {
        echo "✓ Inserted record for UID: $uid, Tank ID: $tank_id (Reconciliation: " . number_format($reconciliation, 2) . ")<br>";
    } else {
        echo "✗ Failed to insert record for UID: $uid, Tank ID: $tank_id. Error: " . $queryInsert->error . "<br>";
    }

    // Free result for next iteration
    $queryprevdip->free_result();
}

// Close all prepared statements
$querycvol->close();
$queryprevdip->close();
$stmttransactions->close();
$stmtdel->close();
$queryInsert->close();

echo "<br>Processing completed successfully for date: $datetest<br>";

// Close the database connection
$conn->close();
?>
