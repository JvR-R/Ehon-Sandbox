<?php
    	include('../db/dbh2.php');
        include('../db/Datetime.php');

    $logOutput = ""; // Initialize a variable to store log messages
    $logFilePath = __DIR__ . "/transaction_lite.log"; // Define the log file path
    $logOutput .= "**************Transaction Starting- $date-$time*************************\n";
    $total = 0;
    $ins = 0;
    $err = 0;
    // Retrieve the company IDs from the database
    $sql = "SELECT clc.mcs_liteid, cs.device_id, cs.uid, ca.client_id FROM
		  console as cs
		JOIN Console_Asociation as ca ON cs.uid = ca.uid
        JOIN Clients as clc on clc.client_id = ca.client_id
		WHERE
		  cs.device_type = 200";
    $resultid = $conn->query($sql);

    if ($resultid !== false && $resultid->num_rows > 0) {
        while ($row = $resultid->fetch_assoc()) {
            $key = $row["device_id"];
			$masterid = $row["mcs_liteid"];
			$companyId = $row["client_id"];
			$parts = explode('_', $key);
			$token = $parts[0];
            $datep = '2024-07-15';
            $datet = '2024-07-16';
            if (!empty($token)) {

                // API endpoint URL
                $url = 'https://mcs-connect.com/api/v1/transactions/' . $masterid . '/';

                //?fromdt=' . $datep . '&todt=' . $datet . '
                //?fromdt=' . $date . 'T' . $timeOneHourAgo . '&todt=' . $date . 'T' . $time . '
                while ($url) {
                    $logOutput .= "API URL: $url\n";
                    echo "API URL: $url<br>";
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
                    $logOutput .= 'Error: ' . curl_error($ch);
                    exit;
                    }
                    // Close cURL
                    curl_close($ch);

                    $data = json_decode($response, true);

                    if ($data !== null && is_array($data)) {
                        $coundata = isset($data['count']) ? (int)$data['count'] : 0;
                        $logOutput .= $coundata . "\n";
                        $results = isset($data['results']) && is_array($data['results']) ? $data['results'] : [];
                        foreach ($results as $result) {
                            $id = $result['id'];

                            // Check if the ID already exists in the database
                            $stmt = $conn->prepare("SELECT mcs_transaction_id FROM client_transaction WHERE mcs_transaction_id = ?");
                            $stmt->bind_param("s", $id);
                            $stmt->execute();
                            $stmt->store_result();

                            if ($stmt->num_rows > 0) {
                                $logOutput .= "ID $id is already added to the database.\n";
                                continue; // Skip inserting the data
                            }

                            // Proceed with inserting the data into the database
                            $siteName = $result['site_name'] ?? null;
                            echo "$siteName<br>";
                            $siteNumber = $result['site'] ?? null;
                            $transactionDateTime = $result['transaction_datetime'] ?? null;
                            $custName = $result['customer_name'] ?? null;
                            $cardNo = $result['card_number'] ?? null;
                            $cardHolderName = $result['card_name'] ?? null;
                            $pumpNo = $result['pump_no'] ?? null;
                            $quantity = $result['volume'] ?? 0;
                            $productName = $result['product_name'] ?? null;
                            $tankNo = $result['tank_no'] ?? null;
                            // $tankid = $result['terminal_no'];
                            $vehicle_registration = $result['vehicle_no'] ?? null;
                            $odometer = $result['odometer'] ?? null;

                            $nullValue = null;
                            $flagValue = '0';

                            // Use the new datetime conversion function
                            $convertedDateTime = convertDateTimeWithTimezone($transactionDateTime, $uid, $conn);
                            
                            if ($convertedDateTime === null) {
                                // Fallback to old method if conversion fails
                                $nonFormattedDate = null;
                                $nonFormattedTime = null;
                                if (is_string($transactionDateTime) && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $transactionDateTime, $matchesIsoParts)) {
                                    $nonFormattedDate = $matchesIsoParts[1];
                                    $nonFormattedTime = $matchesIsoParts[2];
                                }
                            } else {
                                // Store LOCAL datetime in transaction_date/time (for display)
                                $nonFormattedDate = $convertedDateTime['local_date'];
                                $nonFormattedTime = $convertedDateTime['local_time'];
                            }

                            $stmttanksel = $conn->prepare("SELECT ts.Site_id, site_country, site_city, ts.uid FROM Tanks as ts JOIN Sites as st on (st.uid, st.Site_id) = (ts.uid, ts.Site_id) WHERE Site_name = ? and tank_id = ? and ts.client_id = ?;");
                            $stmttanksel->bind_param("sis", $siteName, $tankNo, $companyId);
                            $stmttanksel->execute();
                            $stmttanksel->store_result();

                            if ($stmttanksel->num_rows > 0) {;
                                $stmttanksel->bind_result($site_id, $countryt, $cityt, $uid);
                                $stmttanksel->fetch();		
                                echo "site_id: $site_id<br>";
                            }

                           
                            // Use the converted datetime values
                            if ($convertedDateTime !== null) {
                                // Use the properly converted datetime values
                                $formattedDate = $convertedDateTime['local_date'];    // Site's local timezone
                                $formattedTime = $convertedDateTime['local_time'];
                                $formattedDate0 = $convertedDateTime['utc_date'];     // UTC datetime
                                $formattedTime0 = $convertedDateTime['utc_time'];
                                $adjustedDateTime = $formattedDate . ' ' . $formattedTime;
                            } else {
                                // Fallback to old timezone adjustment method
                                $adjtime = Timezone($uid);
                                $timezoneOffset = $adjtime['offset'];
                                $dateTime = new DateTime($transactionDateTime, new DateTimeZone('UTC'));
                                $dateTime2 = new DateTime($transactionDateTime, new DateTimeZone('UTC'));

                                preg_match('/([+-]?\d+):(\d+)/', $timezoneOffset, $matches);
                                $hours = (int)$matches[1];
                                $minutes = (int)$matches[2];

                                $intervalSpecHours = 'PT' . abs($hours) . 'H';
                                $intervalSpecMinutes = 'PT' . $minutes . 'M';

                                if (strpos($timezoneOffset, '-') === 0) {
                                    $dateTime->sub(new DateInterval($intervalSpecHours));
                                    $dateTime->sub(new DateInterval($intervalSpecMinutes));
                                } else {
                                    $dateTime->add(new DateInterval($intervalSpecHours));
                                    $dateTime->add(new DateInterval($intervalSpecMinutes));
                                }

                                $adjustedDateTime = $dateTime->format('Y-m-d H:i:s');
                                $formattedDate = $dateTime->format('Y-m-d');
                                $formattedTime = $dateTime->format('H:i:s');
                                $formattedDate0 = $dateTime2->format('Y-m-d');
                                $formattedTime0 = $dateTime2->format('H:i:s');
                            }		
                            echo "$uid, $formattedDate, $formattedTime, $cardNo, $cardHolderName, $odometer, $vehicle_registration, $tankNo, $siteName, $pumpNo, $quantity, $productName, $id, $site_id<br>";

                            $stmttanksel->close();
                            
                            // Update console last connection using non-formatted UTC0 date/time (only for newer dates)
                            if (!empty($uid) && $nonFormattedDate !== null && $nonFormattedTime !== null) {
                                // Get current console connection date/time
                                $stmtGetConsole = $conn->prepare("SELECT last_conndate, last_conntime FROM console WHERE uid = ?");
                                $stmtGetConsole->bind_param("i", $uid);
                                $stmtGetConsole->execute();
                                $stmtGetConsole->store_result();
                                
                                $shouldUpdate = false;
                                
                                if ($stmtGetConsole->num_rows > 0) {
                                    $stmtGetConsole->bind_result($currentConnDate, $currentConnTime);
                                    $stmtGetConsole->fetch();
                                    
                                    // Compare dates: update if new date is greater OR same date but newer time
                                    if ($nonFormattedDate > $currentConnDate) {
                                        $shouldUpdate = true;
                                    } elseif ($nonFormattedDate == $currentConnDate && $nonFormattedTime > $currentConnTime) {
                                        $shouldUpdate = true;
                                    }
                                } else {
                                    // No existing record, so update
                                    $shouldUpdate = true;
                                }
                                $stmtGetConsole->close();
                                
                                if ($shouldUpdate) {
                                    $stmtUpdateConsole = $conn->prepare("UPDATE console SET last_conndate = ?, last_conntime = ? WHERE uid = ?");
                                    $stmtUpdateConsole->bind_param("ssi", $nonFormattedDate, $nonFormattedTime, $uid);
                                    $stmtUpdateConsole->execute();
                                    if ($stmtUpdateConsole->affected_rows > 0) {
                                        $logOutput .= "Console updated: uid=$uid last_conn={$nonFormattedDate} {$nonFormattedTime}\n";
                                    }
                                    $stmtUpdateConsole->close();
                                } else {
                                    $logOutput .= "Console update skipped: uid=$uid current={$currentConnDate} {$currentConnTime} >= new={$nonFormattedDate} {$nonFormattedTime}\n";
                                }
                            }
                            $stmt = $conn->prepare("INSERT INTO client_transaction (uid, transaction_date, transaction_time, transaction_date_utc0, transaction_time_utc0, card_number, card_holder_name, customer_name, odometer, registration, tank_id, tank_name, pump_id, dispensed_volume, product, mcs_transaction_id, site_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("isssssssssisidssi", $uid, $nonFormattedDate, $nonFormattedTime, $formattedDate0, $formattedTime0, $cardNo, $cardHolderName, $custName, $odometer, $vehicle_registration, $tankNo, $siteName, $pumpNo, $quantity, $productName, $id, $site_id);

                            $stmt->execute();

                            if ($stmt->affected_rows > 0) {
                                $ins++;
                                $logOutput .= "Transaction Date: $transactionDateTime\n";
                                $logOutput .= "ID: $id\n";
                                $logOutput .= "Site Number: $siteNumber\n";
                                $logOutput .= "Site ID: $site_id\n";
                                $logOutput .= "Site Name: $siteName\n";
                                $logOutput .= "Card Number: $cardNo\n";
                                $logOutput .= "Card Holder Name: $cardHolderName\n";
                                $logOutput .= "Quantity: $quantity\n";
                                $logOutput .= "Pump Number: $pumpNo\n";
                                $logOutput .= "Tank Number: $tankNo\n";
                                // $logOutput .= "Terminal ID: $tankid\n";
                                $logOutput .= "Product Name: $productName\n";  
                                $logOutput .= "Date: $formattedDate \n Time: $formattedTime\n";
                                $logOutput .= "Data inserted into the database successfully.\n";
                                $logOutput .= "\n-------------------------\n";

                            } 
                            else {
                                $err++;
                                $logOutput .= "Error: Failed to insert data into the database.\n" . $conn->error . $siteName . "\n";
                                $logOutput .= "\n-------------------------\n";
                            }
                            $stmt->close();
                        }
                    } 
                    if (isset($data['next'])) {
                        $url = $data['next'];
                    } 
                    else {
                        $url = null;
                        $total += $coundata;
                    }
                }
            } 
            else{
                continue;
            }
        }
        $conn->close();
        $logOutput .= "Total: $total\nInserted: $ins \n Error: $err\n";
        echo "Total: $total<br>";
        $logOutput .= "\n*****************************************************************\n";
    }
    else {
        $logOutput .= "Error: No company IDs found in the database.\n";
    }
    file_put_contents($logFilePath, $logOutput, FILE_APPEND);	
?>
