<?php
include('../../db/dbh2.php'); 
ob_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $distributorName = $_POST['Client_name'];
    $distributorAddress = $_POST['Client_address'];
    $distributorPhone = $_POST['Client_phone'];
    $distributorEmail = $_POST['Distributor_email'];
    $clientEmail = $_POST['Client_email'];
    $distributorpassword = $_POST['Client_password'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];


        //Distributor ----------------------------------------------------------
        $sqld = "INSERT INTO Distributor (Dist_name, Dist_address, Dist_email, Dist_phone) VALUES (?, ?, ?, ?)";
    
        // Prepare and bind parameters
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("sssi",$distributorName, $distributorAddress, $distributorEmail, $distributorPhone);
        // Execute and check if insert was successful
        if ($stmtd->execute()) {
            echo "Dist inserted successfully!<br>";
            $last_id = $conn->insert_id;
            $dist = $last_id;
            echo "Inserted Dist: $dist, $distributorName, $distributorAddress, $distributorEmail, $distributorPhone<br>";
            $stmtd->close(); 
            //Reseller ------------------------------------------------------------------
            $sqlr = "INSERT INTO Reseller (reseller_id, dist_id, reseller_name, reseller_address, reseller_email, reseller_phone) VALUES (?, ?, ?, ?, ?, ?)";
    
            // Prepare and bind parameters
            $stmtr = $conn->prepare($sqlr);
            $stmtr->bind_param("iisssi", $dist, $dist, $distributorName, $distributorAddress, $distributorEmail, $distributorPhone);
            // Execute and check if insert was successful
            if ($stmtr->execute()) {
                echo "Reseller inserted successfully!<br>";
                $last_id2 = $conn->insert_id;
                $res = $last_id2;
                echo "Inserted Reseller: $res, $dist, $dist, $distributorName, $distributorAddress, $distributorEmail, $distributorPhone<br>";
                $stmtr->close();

                //Client --------------------------------------------------------------------
                $sqlc = "INSERT INTO Clients (client_id, reseller_id, Client_name, Client_address, Client_email, Client_phone) VALUES (?, ?, ?, ?, ?, ?)";
    
            // Prepare and bind parameters
                $stmtc = $conn->prepare($sqlc);
                $stmtc->bind_param("iisssi", $dist, $dist, $distributorName, $distributorAddress, $distributorEmail, $distributorPhone);
                // Execute and check if insert was successful
                if ($stmtc->execute()) {
                    echo "Client inserted successfully!<br>";
                    $last_id3 = $conn->insert_id;
                    $client = $last_id3;
                    echo "Inserted Client: $res, $dist, $dist, $distributorName, $distributorAddress, $distributorEmail, $distributorPhone<br>";
                    $stmtc->close(); 

                    $accesslvl = 4;
                    $hashedPassword = password_hash($distributorpassword, PASSWORD_DEFAULT);
                    $stmtin = $conn->prepare("INSERT INTO ehonener_ehon_vmi.login (username, password, access_level, client_id, name, last_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtin->bind_param("ssiiss", $clientEmail, $hashedPassword, $accesslvl, $dist, $firstname, $lastname);
                    $stmtin->execute();
                    if ($stmtin->affected_rows > 0) {
                        // header("Location: new_site.php");
                        // exit();
                        echo "INS Login: $clientEmail, $hashedPassword, $accesslvl, $dist, $firstname, $lastname";
                        ob_end_flush(); 
                    }
                    else{
                        echo "Error INS Login";
                    }
                }
                else{
                    echo "Reseller Error<br>";
                }
            }
            else{
                echo "Reseller Error<br>";
            }
            
    
        }
        else{
            echo "Dist Error<br>";
        }

       
    // Close the statement and connection
    $conn ->close();
    
}
?>
