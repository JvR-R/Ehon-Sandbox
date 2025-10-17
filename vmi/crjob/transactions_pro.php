<?php
include('../db/dbh2.php');
include('../db/Datetime.php');

$logOutput = ""; // Initialize a variable to store log messages
$logFilePath = "t_pro.log"; // Define the log file path
$logOutput .= "**************Transaction Starting- $date-$time*************************\n";
$total = 0;
$ins = 0;
$err = 0;

// Retrieve the company IDs from the database
$sql = "SELECT clc.mcs_clientid, cs.device_id, cs.uid, ca.client_id FROM
      console as cs
    JOIN Console_Asociation as ca ON cs.uid = ca.uid
    JOIN Clients as clc on clc.client_id = ca.client_id
    WHERE
      cs.device_type = 201 group by mcs_clientid";
$resultid = $conn->query($sql);

if ($resultid !== false && $resultid->num_rows > 0) {
    while ($row = $resultid->fetch_assoc()) {
        $key = $row["device_id"];
        $masterid = $row["mcs_clientid"];
        $companyId = $row["client_id"];
        $parts = explode('_', $key);
        $token = $parts[0];
        $hour = date("H", strtotime($time));
        $timeOneHourAgo = date("H", strtotime("-2 hour", strtotime($time)));
        // $datep = '2024-07-16';

        if (!empty($token)) {
            // for($i = 1; $i<=31; $i++){
            // $datep = '2024-07-' . $i;
            // API endpoint URL
            $url = 'https://mcstsm.com/api/v1/' . $masterid . '/cardtransactions/';
            while ($url) {
                // cURL initialization
                $ch = curl_init();

                // Set cURL options
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Token ' . $token));

                // Execute the request
                $response = curl_exec($ch);

                // Check for cURL errors
                if (curl_errno($ch)) {
                    //  echo'Error: ' . curl_error($ch);
                    exit;
                }
                // Close cURL
                curl_close($ch);

                $data = json_decode($response, true);

                if ($data !== null && is_array($data)) {
                    $coundata = $data['count'];
                    //  echo$coundata . "<br>";
                    foreach ($data['results'] as $result) {
                        $id = $result['id'];

                        // Initialize variables to avoid retaining old values
                        $siteName = '';
                        $siteNumber = '';
                        $transactionDateTime = '';
                        $custName = '';
                        $cardNo = '';
                        $cardHolderName = '';
                        $pumpNo = '';
                        $quantity = '';
                        $productName = '';
                        $tankNo = '';
                        $tankid = '';
                        $vehicle_registration = '';
                        $odometer = '';

                        // Check if the ID already exists in the database
                        $stmt = $conn->prepare("SELECT mcs_transaction_id FROM client_transaction WHERE mcs_transaction_id = ?");
                        $stmt->bind_param("s", $id);
                        $stmt->execute();
                        $stmt->store_result();

                        if ($stmt->num_rows > 0) {
                            //  echo"ID $id is already added to the database.<br>";
                            continue; // Skip inserting the data
                        }

                        // Proceed with inserting the data into the database
                        $siteName = $result['site_name'];
                        $siteNumber = $result['site_no'];
                        $transactionDateTime = $result['transaction_datetime'];
                        $transactionDateTimetz = $result['transaction_datetime_user_tz'];
                        $custName = $result['customer_name'];
                        $cardNo = $result['card_number'];
                        $cardHolderName = $result['card_holder_name'];
                        $pumpNo = $result['pump_no'];
                        $quantity = $result['quantity'];
                        $productName = $result['product_name'];
                        $tankNo = $result['tank_no'];
                        $tankid = $result['terminal_no'];
                        $vehicle_registration = $result['vehicle_registration'];
                        $odometer = $result['odometer'];

                        //  echo"Transaction Date: $transactionDateTime<br>";
                        //  echo"ID: $id<br>";
                        //  echo"Customer Name: $custName<br>";
                        //  echo"Site Number: $siteNumber<br>";
                        //  echo"Site Name: $siteName<br>";
                        //  echo"Card Number: $cardNo<br>";
                        //  echo"Card Holder Name: $cardHolderName<br>";
                        //  echo"Quantity: $quantity<br>";
                        //  echo"Pump Number: $pumpNo<br>";
                        //  echo"Tank Number: $tankNo<br>";
                        //  echo"Terminal ID: $tankid<br>";
                        //  echo"Product Name: $productName<br>";  
                        //  echo"<br>-------------------------<br>";

                        // Log parameter values
                        //  echo"Query Parameters - Tank Name: $siteName, Tank ID: $tankid, Client ID: $companyId<br>";

                        // Prepare and execute the SQL query
                        $stmttanksel = $conn->prepare("
                            SELECT ts.Site_id, ts.uid 
                            FROM Tanks as ts 
                            JOIN Sites as st 
                            ON st.uid = ts.uid AND st.Site_id = ts.Site_id 
                            WHERE Site_name = ? 
                              AND tank_id = ? 
                              AND ts.client_id = ?;
                        ");
                        //  echo"SQL Query: SELECT ts.Site_id, ts.uid 
                        //     FROM Tanks as ts 
                        //     JOIN Sites as st 
                        //     ON st.uid = ts.uid AND st.Site_id = ts.Site_id 
                        //     WHERE Site_name = '$siteName' 
                        //       AND tank_id = '$tankid' 
                        //       AND ts.client_id = '$companyId';<br>";

                        $stmttanksel->bind_param("sis", $siteName, $tankid, $companyId);
                        $stmttanksel->execute();
                        $stmttanksel->store_result();

                        if ($stmttanksel->num_rows > 0) {
                            $stmttanksel->bind_result($site_id, $uid);
                            $stmttanksel->fetch();		
                            //  echo"Query Result: Site ID: $site_id, UID: $uid<br>";

                            $adjtime = Timezone($uid);
                            $timezoneOffset = $adjtime['offset_string'];
                            $dateTime = new DateTime($transactionDateTime, new DateTimeZone('UTC'));
                            $dateTime2 = new DateTime($transactionDateTime, new DateTimeZone('UTC'));

                            preg_match('/([+-]?\d+):(\d+)/', $timezoneOffset, $matches);
                            $hours = (int)$matches[1];
                            $minutes = (int)$matches[2];

                            $intervalSp//  echours = 'PT' . abs($hours) . 'H';
                            $intervalSpecMinutes = 'PT' . $minutes . 'M';

                            if (strpos($timezoneOffset, '-') === 0) {
                                $dateTime->sub(new DateInterval($intervalSp//  echours));
                                $dateTime->sub(new DateInterval($intervalSpecMinutes));
                            } else {
                                $dateTime->add(new DateInterval($intervalSp//  echours));
                                $dateTime->add(new DateInterval($intervalSpecMinutes));
                            }

                            $dateTimeUserTz = new DateTime($transactionDateTimetz);
                            $formattedDate = $dateTimeUserTz->format('Y-m-d');
                            $formattedTime = $dateTimeUserTz->format('H:i:s');
                            $formattedDate0 = $dateTime2->format('Y-m-d');
                            $formattedTime0 = $dateTime2->format('H:i:s');			

                            $stmttanksel->close();
                            if (empty($tankNo)) {
                                continue;
                            }
                            $stmt = $conn->prepare("INSERT INTO client_transaction (uid, transaction_date, transaction_time, transaction_date_utc0, transaction_time_utc0, card_number, card_holder_name, customer_name, odometer, registration, tank_id, tank_name, pump_id, dispensed_volume, product, mcs_transaction_id, site_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("isssssssssisidssi", $uid, $formattedDate, $formattedTime, $formattedDate0, $formattedTime0, $cardNo, $cardHolderName, $custName, $odometer, $vehicle_registration, $tankNo, $siteName, $pumpNo, $quantity, $productName, $id, $site_id);

                            $stmt->execute();

                            if ($stmt->affected_rows > 0) {
                                $ins++;
                                $logFilePath .= "Transaction Date: $transactionDateTime\n";
                                $logFilePath .= "ID: $id\n";
                                $logFilePath .= "Customer Name: $custName\n";
                                $logFilePath .= "Site Number: $siteNumber\n";
                                $logFilePath .= "Site ID: $site_id\n";
                                $logFilePath .= "Site Name: $siteName\n";
                                $logFilePath .= "Card Number: $cardNo\n";
                                $logFilePath .= "Card Holder Name: $cardHolderName\n";
                                $logFilePath .= "Quantity: $quantity\n";
                                $logFilePath .= "Pump Number: $pumpNo\n";
                                $logFilePath .= "Tank Number: $tankNo\n";
                                $logFilePath .= "Terminal ID: $tankid\n";
                                $logFilePath .= "Product Name: $productName\n";  
                                $logFilePath .= "Date: $formattedDate \n Time: $formattedTime\n";
                                $logFilePath .= "Data inserted into the database successfully.\n";
                            } else {
                                $err++;
                                //  echo"Error: Failed to insert data into the database.<br>" . $conn->error . $siteName . "<br>";
                                //  echo"<br>-------------------------<br>";
                            }
                        } else {
                            //  echo"No matching tank found for Tank Name: $siteName, Tank ID: $tankid, Client ID: $companyId<br>";
                            continue; // Skip if no matching tank is found
                        }
                    }
                }
                if (isset($data['next'])) {
                    $url = $data['next'];
                } else {
                    $url = null;
                    $total += $coundata;
                }
            }

        // }
        } else {
            continue;
        }
    }
    $conn->close();
    //  echo"Total: $total<br>Inserted: $ins <br> Error: $err<br>";
    //  echo"Total: $total<br>";
    //  echo"<br>*****************************************************************<br>";
} else {
    //  echo"Error: No company IDs found in the database.<br>";
}
// file_put_contents($logFilePath, $logOutput, FILE_APPEND);	
?>
