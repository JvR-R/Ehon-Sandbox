<?php
	include('../db/dbh.php');
// Retrieve the search conditions from the query parameters
$token = "dcd8ad7b21a88471145594a58eb2696aaee2aebd";
// Database connection


// Retrieve the company IDs from the database
$sql = "SELECT id FROM users";
$resultid = $conn->query($sql);

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $companyId = $row['id'];

        // API endpoint URL
        $url = 'https://mcstsm.com/api/v1/' . $companyId . '/cardtransactions/';
        echo "API URL: $url<br>";

        // cURL initialization ?fromdt=2023-07-10T00:00:00&todt=2023-07-10T23:59:59
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Token ' . $token));

        // Execute the request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
            exit;
        }
        // Close cURL
        curl_close($ch);

        $data = json_decode($response, true);

        if ($data !== null && is_array($data)) {
            foreach ($data['results'] as $result) {
                $id = $result['id'];

                // Check if the ID already exists in the database
                $stmt = $conn->prepare("SELECT transaction_id FROM card_transaction WHERE transaction_id = ?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    echo "ID $id is already added to the database.<br>";
                    continue; // Skip inserting the data
                }
                else{
                // Proceed with inserting the data into the database
                $siteName = $result['site_name'];
                $siteNumber = $result['site_no'];
                $transactionDate = $result['transaction_datetime_user_tz'];
                $custName = $result['customer_name'];
                $cardNo = $result['card_number'];
                $cardHolderName = $result['card_holder_name'];
                $pumpNo = $result['pump_no'];
                $priceList = $result['price_list'];
                $priceLocal = $result['price_local'];
                $priceCustomer = $result['price_customer'];
                $quantity = $result['quantity'];
                $terminalPrice = $result['terminal_price'];
                $test = floor(($result["quantity"] * $result["price_customer"])*100)/100;
                $productName = $result['product_name'];
                if($custName == "iPETRO Pay" || $custName == "iPETRO PAY"){
					echo "Transaction Date: $transactionDate<br>";
					echo "ID: $id<br>";
					echo "Site Number: $siteNumber<br>";
					echo "Site Name: $siteName<br>";
					echo "Customer Name: $custName<br>";
					echo "Card Number: $cardNo<br>";
					echo "Card Holder Name: $cardHolderName<br>";
					echo "Quantity: $quantity<br>";
					echo "Pump Number: $pumpNo<br>";
					echo "Price List: $priceList<br>";
					echo "Price Local: $priceLocal<br>";
					echo "Price Customer: $priceCustomer<br>";
					echo "Terminal Price: $terminalPrice<br>";
					echo "Total Price: " . $test . "<br>";
					echo "Product Name: $productName<br>";
					echo

					 // Insert data into the database
					 $transactionDateTime = $result['transaction_datetime_user_tz'];
					 $dateTimeParts = explode("T", $transactionDateTime);
					 $transactionDate = $dateTimeParts[0];
					 $transactionTime = str_replace("+10:00", "", $dateTimeParts[1]);
                     if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $transactionTime)) {
                        if ($transactionTime > '17:30:00') {
                            // Convert date string to timestamp and add one day (86400 seconds)
                            $timestamp = strtotime($transactionDate);
                            $settlementTimestamp = $timestamp + 86400; // 1 day in seconds
                            $settlement_Date = date('Y-m-d', $settlementTimestamp);
                        } else {
                            $settlement_Date = $transactionDate;
                        } 
                    } else {
                        // Handle invalid time format
                        throw new Exception("Invalid time format in transaction_datetime_user_tz.");
                    }       
                    echo "<br>Settlement Date: " . $settlement_Date;            
					 $nullValue = null;
					 $flagValue = '0';
					 $stmt = $conn->prepare("INSERT INTO card_transaction (company_id, transaction_id, transaction_date, transaction_time, site_no, site_name, customer_name, card_number, card_holder_name, quantity, pump_no, price_list, price_local, price_customer, total_price, terminal_price, product_name, flag, settlement_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
					 $stmt->bind_param("sssssssssssssssssss", $companyId, $id, $transactionDate, $transactionTime, $siteNumber, $siteName, $custName, $cardNo, $cardHolderName, $quantity, $pumpNo, $priceList, $priceLocal, $priceCustomer, $test, $terminalPrice, $productName, $flagValue, $settlement_Date);
	 
					 $stmt->execute();
	 
					 if ($stmt->affected_rows > 0) {
						 echo "Data inserted into the database successfully.<br>";
					 } else {
						 echo "Error: Failed to insert data into the database.<br>";
					 }
                }else{
                    continue;
                }
				
             }
                
            }
 
    
        } else {
            echo "";
        }
     }
 } else {
     echo "";
 }
 $stmt->close();
 $conn->close();
 ?>
