<?php
include('../../db/dbh2.php');
include('../../db/log.php');   

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $consolenumber2 = $_POST['console_number'];
    $ordernumber = $_POST['order_number'];
    $client_list = isset($_POST['client_list']) ? $_POST['client_list'] : 0;
    $dispatch_type = $_POST['dispatch_type'];

    $consolenumber = substr($consolenumber2, 0, 6);

    if($dispatch_type == 1 && $client_list > 0){
        $disp = "Distributor";
        $dist_id = $client_list;
        $clientid = null;
        $resid = null;
    }
    elseif($dispatch_type == 2 && $client_list > 0){
        $disp = "Reseller";
        $resid = $client_list;
        $query = "SELECT dist_id FROM Reseller WHERE reseller_id = ?";
        $stmtr = $conn->prepare($query);
        $stmtr->bind_param("i", $resid);
        $stmtr->execute();
        $stmtr->bind_result($dist_id);
        $stmtr->fetch();      
        $stmtr->close();
        $clientid = null;
    }
    elseif($dispatch_type == 3){
        $disp = "Client";
        $clientid = null;
        $resid = $companyId;
        $dist_id = $companyId;
    }

    $date = date("Y-m-d");
    $time = date("H:i:s");
    $status = "Dispatched to " . $disp;

    // SQL to insert data
    $sql = "INSERT INTO Console_Asociation (id, uid, dist_id, reseller_id, Client_id, sales_date, sales_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiiiss", $ordernumber, $consolenumber, $dist_id, $resid, $clientid, $date, $time);

    try {
        $stmt->execute();
        // Update console status
        $upd = "UPDATE console SET console_status = ? WHERE uid = ?";
        $stmtupd = $conn->prepare($upd);
        $stmtupd->bind_param("si", $status, $consolenumber);

        if ($stmtupd->execute()) {
            // Success: Save a "success" message in session
            $_SESSION['toastr_msg']  = 'Console dispatch successful!';
            $_SESSION['toastr_type'] = 'success';

            // Redirect back to index.php or wherever you want to show the toastr
            header("Location: index.php");
            exit();
        } else {
            // If updating status failed
            $_SESSION['toastr_msg']  = 'Error updating console status: ' . $stmtupd->error;
            $_SESSION['toastr_type'] = 'error';
            header("Location: index.php");
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        $_SESSION['toastr_msg']  = "DB Error: " . $e->getMessage();
        $_SESSION['toastr_type'] = 'error';
        header("Location: index.php");
        exit();
    }

    // Close statements and connection
    $stmt->close();
    $stmtupd->close();
    $conn->close();
}
?>
