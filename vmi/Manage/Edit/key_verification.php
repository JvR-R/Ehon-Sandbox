<?php
include('../../db/dbh2.php');
include('../../db/log.php');
ob_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $deviceid = $_POST['Client_key'];

    $sql = "SELECT uid, device_id, console_status FROM console WHERE device_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $deviceid);

    if ($stmt->execute()) {
        $stmt->bind_result($bound_uid, $bound_deviceid, $bound_console_status);

        // If we can fetch a matching row
        if ($stmt->fetch()) {
            // Check console_status is 'Dispatched to Client'
            if ($bound_console_status === "Dispatched to Client") {
                $stmt->close();
                $upd  = "UPDATE console SET console_status = ? WHERE device_id = ?";
                $upd2 = "UPDATE Console_Asociation SET Client_id = ? WHERE uid = ?";

                $status    = "In Use";
                $stmtupd   = $conn->prepare($upd);
                $stmtupd->bind_param("ss", $status, $deviceid);

                $stmtupd2  = $conn->prepare($upd2);
                $stmtupd2->bind_param("si", $companyId, $bound_uid);

                if ($stmtupd->execute() && $stmtupd2->execute()) {
                    // SUCCESS
                    header("Location: new_console.php?status=success");
                    exit();
                } else {
                    // ERROR updating
                    header("Location: new_console.php?status=error");
                    exit();
                }
            } 
            // Not "Dispatched to Client"
            else {
                header("Location: new_console.php?status=invalid");
                exit();
            }
        } 
        // No row fetched => invalid code
        else {
            $stmt->close();
            header("Location: new_console.php?status=invalid");
            exit();
        }
    } 
    // SQL error
    else {
        header("Location: new_console.php?status=error");
        exit();
    }
    $conn->close();
    ob_end_flush(); 
}
?>
