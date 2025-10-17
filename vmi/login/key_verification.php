<?php
include('../db/dbh2.php');
session_start();
ob_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $string = $_POST['Client_key'];
    echo "$string";
    // Get the first part of the string
    $uid = substr($string, 0, 6);

    // Get the second part of the string
    $deviceid = substr($string, 6);

    // SQL to insert data into the database
    $sql = "SELECT * FROM console WHERE uid = ? AND device_id = ?";
    
    // Prepare and bind parameters
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is",$uid, $deviceid);
    // Execute and check if insert was successful
    if ($stmt->execute()) {
        if ($row = $stmt->fetch()) {
            // echo "Successful $uid, $deviceid<br>";
            $_SESSION['uid'] = $uid;
            $_SESSION['deviceid'] = $deviceid;
            // echo "$uid, $deviceid";
            header("Location: /vmi/login/new_company"); 
        }
        else {
            echo "<script type='text/javascript'>alert('Please enter a valid Code');
            window.location.href='/vmi/login/verification';
            </script>";
            
        }        
    }
    else{
        echo "Error: " . $stmt->error . "<br>";
    }
    // Close the statement and connection
    $stmt->close();
    $conn ->close();
    exit();
    ob_end_flush(); 
    
}
else{
    echo "Post error<br>";
}
?>
