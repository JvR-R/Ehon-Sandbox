<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve the companyId and feeType values from the POST data
    $companyId = $_POST['companyId'] ?? '';
    $feeType = $_POST['feeType'] ?? '';

    // Connect to MySQL server
    $servername = "localhost"; // Replace with your MySQL server IP address or hostname
    $username = "ipetroco_dev_admin_mysql"; // Replace with your MySQL login username
    $password = '$_i_dev789mysql'; // Replace with your MySQL login password
    $dbname = "ipetroco_ehon_tsm"; // Replace with the name of your MySQL database

    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Update the fee_type in the users table if companyId is not empty
    if (!empty($companyId)) {
            $companyfee = "UPDATE ipetroco_ehon_tsm.users SET id_fee = $feeType WHERE id = '$companyId'";
            $result = $conn->query($companyfee);
            if ($result) {
                echo "Fee type updated successfully<br>";
            } else {
                echo "Error updating fee type: " . $conn->error . "<br>";
            }
        }
    
    // Close the database connection
    $conn->close();
} else {
    // Handle the case where the form is not submitted via POST
    echo "Form submission error";
}
?>
