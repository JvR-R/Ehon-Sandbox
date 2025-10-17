<?php
include('../../db/dbh2.php'); 
include('../../db/log.php');
ob_start();


// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $customer_name = $_POST['customer_name'];
    $customer_country = $_POST['customer_country'];
    $customer_address = $_POST['customer_address'];
    $customer_city = $_POST['customer_city'];
    $customer_postcode = $_POST['customer_postcode'];
    $customer_phone = $_POST['customer_phone'];
    $customer_email = $_POST['customer_email'];
    
    echo "Customer Name: " . htmlspecialchars($customer_name) . "<br>";
    echo "Country: " . htmlspecialchars($customer_country) . "<br>";
    echo "Address: " . htmlspecialchars($customer_address) . "<br>";
    echo "City: " . htmlspecialchars($customer_city) . "<br>";
    echo "Postcode: " . htmlspecialchars($customer_postcode) . "<br>";
    echo "Phone: " . htmlspecialchars($customer_phone) . "<br>";
    echo "Email: " . htmlspecialchars($customer_email) . "<br>";
    
    if (isset($_POST['block_site'])) {
        // Ensure that $selectedConsoles is always an array
        $selectedConsoles = is_array($_POST['block_site']) ? $_POST['block_site'] : [$_POST['block_site']];
        echo "<pre>";
        var_dump($selectedConsoles);
        echo "</pre>";
        // Process each selected value
        foreach ($selectedConsoles as $consoleId) {
            // Handle each consoleId
            echo "siteID = $consoleId<br>";
        }
    }
    

    $selectedConsolesJson = json_encode($selectedConsoles);


        $sqld = "INSERT INTO Customers (client_id, customer_name, customer_country, customer_address, customer_city, customer_zip, customer_phone, customer_email, blocked_sites) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        // Prepare and bind parameters
        $stmtd = $conn->prepare($sqld);
        $stmtd->bind_param("issssiiss",$companyId, $customer_name, $customer_country, $customer_address, $customer_city, $customer_postcode, $customer_phone, $customer_email, $selectedConsolesJson);

        // Execute and check if insert was successful
        if ($stmtd->execute()) {
            // echo "customer inserted successfully!<br>";
            $last_id = $conn->insert_id;
            echo "Customer_id=> $last_id<br>";
            // header("location: new_tank.php?customer_id=$last_id&deviceid=$consoleid");
        }
        else{
            echo "customer Error<br>";
        }
        $stmtd->close(); 
    

    // // Close the statement and connection
    
    $conn ->close();
    
}
?>
