<?php
include('../../db/dbh2.php'); 
ob_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $resellerName = $_POST['Client_name'];
    $resellerAddress = $_POST['Client_address'];
    $resellerPhone = $_POST['Client_phone'];
    $resellerEmail = $_POST['Reseller_email'];
    $dist_id = $_POST['dist_id'];
    $clientEmail = $_POST['Client_email'];
    $resellerrpassword = $_POST['Client_password'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];


    $idcheck = "SELECT MAX(reseller_id) as maxid FROM Reseller";
    
        $stmtidcheck = $conn->prepare($idcheck);
        $stmtidcheck->execute();
    
        // Bind result variables
        $resellerid = null;
        $stmtidcheck->bind_result($resellerid); 

        if ($stmtidcheck->fetch()) {
            // If there is a maximum id, increment it
            if ($resellerid<25000) {
                $resellerid = 25000;
            }
            else{
            $resellerid = $resellerid + 1;
            } 
        }
        echo "Bound = $resellerid<br>";
        $stmtidcheck->close();
    

        //Reseller Insert -----------------------------------------------------------------------
        
        $sqld = "INSERT INTO Reseller (reseller_id, dist_id, reseller_name, reseller_address, reseller_email, reseller_phone) VALUES (?, ?, ?, ?, ?, ?)";
    
        // Prepare and bind parameters
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("iisssi", $resellerid, $dist_id, $resellerName, $resellerAddress, $resellerEmail, $resellerPhone);
        // Execute and check if insert was successful
        if ($stmtd->execute()) {
            echo "Reseller inserted successfully!<br>";
            echo "Inserted: $resellerid, $dist_id, $resellerName, $resellerAddress, $resellerEmail, $resellerPhone<br>";

            //Client --------------------------------------------------------------------
            $sqlc = "INSERT INTO Clients (client_id, reseller_id, Client_name, Client_address, Client_email, Client_phone) VALUES (?, ?, ?, ?, ?, ?)";
    
            // Prepare and bind parameters
                $stmtc = $conn->prepare($sqlc);
                $stmtc->bind_param("iisssi", $resellerid, $resellerid, $resellerName, $resellerAddress, $resellerEmail, $resellerPhone);
                // Execute and check if insert was successful
                if ($stmtc->execute()) {
                    echo "Client inserted successfully!<br>";
                    $last_id3 = $conn->insert_id;
                    $client = $last_id3;
                    echo "Inserted Client: $client, $resellerid, $resellerName, $resellerAddress, $resellerEmail, $resellerPhone<br>";
                    $stmtc->close(); 

                    $accesslvl = 6;
                    $hashedPassword = password_hash($resellerrpassword, PASSWORD_DEFAULT);
                    $stmtin = $conn->prepare("INSERT INTO ehonener_ehon_vmi.login (username, password, access_level, client_id, name, last_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtin->bind_param("ssiiss", $clientEmail, $hashedPassword, $accesslvl, $resellerid, $firstname, $lastname);
                    $stmtin->execute();
                    if ($stmtin->affected_rows > 0) {
                        // header("Location: new_site.php");
                        // exit();
                        echo "INS Login: $clientEmail, $hashedPassword, $accesslvl, $resellerid, $firstname, $lastname";
                        ob_end_flush(); 
                    }
                    else{
                        echo "Error INS Login";
                    }
                }
        }
        else{
            echo "Reseller Error<br>";
        }
        

    // Close the statement and connection
    $stmtd->close(); 
    $conn ->close();
    
}
else{
    echo "Post Error<br>";
}
?>
