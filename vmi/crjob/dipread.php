<?php
	include('../db/dbh2.php');
	include('../db/Datetime.php');


	$logOutput = ""; // Initialize a variable to store log messages
	$logFilePath = "dipread.log"; // Define the log file path
	$logOutput .= "$date, $time, ";
	$total = 0;
	// Retrieve the company IDs from the database
	$sql = "SELECT clc.mcs_clientid, cs.device_id, cs.uid, ca.client_id FROM
		  console as cs
		JOIN Console_Asociation as ca ON cs.uid = ca.uid
        JOIN Clients as clc on clc.client_id = ca.client_id
		WHERE cs.device_type = 201
		group by mcs_clientid";
	$resultid = $conn->query($sql);

	$total = 0;
	if ($resultid !== false && $resultid->num_rows > 0) {
		while ($row = $resultid->fetch_assoc()) {
			$key = $row["device_id"];
			$masterid = $row["mcs_clientid"];
			$companyId = $row["client_id"];
			$parts = explode('_', $key);
			$token = $parts[0];
			$timeOneHourAgo = date("H:i:s", strtotime("-4 hour", strtotime($time)));
			// $datep = '2024-11-18';
			// API endpoint URL
			$url = 'https://mcstsm.com/api/v1/company/' . $masterid . '/dipreadtransactions/?fromdt=' . $date . 'T' . $timeOneHourAgo . '&todt=' . $date . 'T' . $time . '';
			$count = 0;
			while ($url) {

				$logOutput .= "\nAPI URL: $url\n";
				echo "$url<br>";
				// cURL initialization ?fromdt=' . $datep . 'T00:00:00&todt=' . $datep . 'T23:59:59
				//?fromdt=' . $date . 'T' . $timeOneHourAgo . '&todt=' . $date . 'T' . $time . '
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
				}

				// Close cURL
				curl_close($ch);

				$data = json_decode($response, true);
				if ($data !== null && is_array($data)) {
					$coundata = $data['count'];
					$dipr_datet = 0;
					$dipr_timet = 0;
					$logOutput .= $coundata . "\n";
					foreach ($data['results'] as $result) {		
						$count++;
						$total++;
						$id = $result['id'];
						// Check if the ID already exists in the database

						// Proceed with inserting the data into the database
						$siteName = $result['site_name'];
						$siteNumber = $result['site_no'];
						$transactionDateTime = $result['transaction_datetime'];						
						$tankNo = $result['tank_no'];
						$currentvol = $result['quantity'];
						$ullage = $result['ullage'];
						$productName = $result['product_name'];

						if(!empty($ullage)){
							$cap = $ullage + $currentvol;}
						else{
							$cap = Null;
						}


						//SELECT from tanks to obtain  current data
						$stmttanksel = $conn->prepare("SELECT capacity, current_volume, ullage, dipr_date, dipr_time, alert_type, level_alert, alert_flag, site_country, site_city, ts.Site_id, ts.uid FROM Tanks as ts JOIN Sites as st on (st.uid, st.Site_id) = (ts.uid, ts.Site_id) WHERE Site_name = ? and tank_id = ? and ts.client_id = ?;");
						$stmttanksel->bind_param("sis", $siteName, $tankNo, $companyId);
						$stmttanksel->execute();
						$stmttanksel->store_result();

						if ($stmttanksel->num_rows > 0) {
							$stmttanksel->bind_result($capacityt, $current_volumet, $ullaget, $dipr_datet, $dipr_timet, $alert_type, $reorder_alertt, $alert_flagt, $countryt, $cityt, $site_id, $uid);
							$stmttanksel->fetch();		
						}
					$adjtime = Timezone($uid);
					$timezoneOffset = $adjtime['offset_string']; // Use offset_string for "+HH:MM" format
					// Create a DateTime object from the original datetime string
					$dateTime = new DateTime($transactionDateTime, new DateTimeZone('UTC'));
					$dateTime2 = new DateTime($transactionDateTime, new DateTimeZone('UTC'));
					
					// Validate timezone offset format before using it
					if (preg_match('/^([+-])(\d{2}):(\d{2})$/', $timezoneOffset, $matches)) {
						$sign = $matches[1];
						$hours = (int)$matches[2];
						$minutes = (int)$matches[3];
					} else {
						// Fallback to UTC if offset format is invalid
						$sign = '+';
						$hours = 0;
						$minutes = 0;
					}
						
						// Create the interval spec for hours and minutes separately
						$intervalSpecHours = 'PT' . $hours . 'H';
						$intervalSpecMinutes = 'PT' . $minutes . 'M';
						
						if ($sign === '-') {
							$dateTime->sub(new DateInterval($intervalSpecHours));
							$dateTime->sub(new DateInterval($intervalSpecMinutes));
						} else {
							$dateTime->add(new DateInterval($intervalSpecHours));
							$dateTime->add(new DateInterval($intervalSpecMinutes));
						}
						
						// Format the DateTime object to your desired output
						$adjustedDateTime = $dateTime->format('Y-m-d H:i:s');

						// Output the adjusted datetime
						// echo "<br>Adjusted DateTime in $newTimezone timezone: $adjustedDateTime\n";

						// Format the DateTime object to separate date and time strings
						$formattedDate = $dateTime->format('Y-m-d');
						$formattedTime = $dateTime->format('H:i:s');
						$formattedDate0 = $dateTime2->format('Y-m-d');
						$formattedTime0 = $dateTime2->format('H:i:s');			
						$nullValue = null;
						$flagValue = '0';

						$stmttanksel->close();
						if(empty($ullage) && empty($capacityt)){
							$ullage = 0;
						}
						else if(empty($ullage) && !empty($capacityt) && !empty($currentvol)){
							$ullage = $capacityt - $currentvol;
						}
						echo "$site_id<br>, $masterid<br>, $uid<br>offset:$formattedDate<br>utc0:$formattedDate0<br>offset:$formattedTime<br>utc0: $formattedTime0 <br>";
						// echo "$capacityt, $current_volumet, $ullaget, $dipr_datet, $dipr_timet, $reorder_alertt, $alert_flagt\n";
						$sqldrins = "INSERT INTO dipread_historic (uid, transaction_date, transaction_date_utc0, transaction_time, transaction_time_utc0, tank_id, current_volume, ullage, mcs_transaction_id, site_name, site_id) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
						$stmtdrins = $conn->prepare($sqldrins);
						$stmtdrins->bind_param("issssissssi", $uid, $formattedDate, $formattedDate0, $formattedTime, $formattedTime0, $tankNo, $currentvol, $ullage, $id, $siteName, $site_id);
						try {
							if($stmtdrins->execute()){
								$logOutput .= "Number: $count\n";
								$logOutput .= "Transaction Date: $transactionDateTime\n";
								$logOutput .= "Transaction ID: $id\n";
								$logOutput .= "Site Number: $siteNumber\n";
								$logOutput .= "Site Name: $siteName\n";
								$logOutput .= "Tank Number: $tankNo\n";
								$logOutput .= "Product Name: $productName\n";
								$logOutput .= "Current Volume: $currentvol\n";
								$logOutput .= "Ullage: $ullage\n";
								$logOutput .= "Capacity: $cap\n";
								$logOutput .= "Date: $formattedDate\n";
								$logOutput .= "Time: $formattedTime\n";
								$logOutput .= "Insert successful\n";
							}
						} catch (mysqli_sql_exception $e) {
							if($e->getCode() == 1062) { // 1062 is the error code for a duplicate entry
								// Handle the duplicate entry case, maybe just log it or ignore
								$logOutput .= "Duplicate entry for Transaction ID: $id\n";
							} else {
								// For any other database error, log or handle as needed
								$logOutput .= "Database error for Transaction ID: $id - " . $e->getMessage() . "\n";
								$logOutput .= "-----------------------------\n"; 
							}
						}
						// Note: The rest of your error logging seems misplaced, you might want to adjust that part of your code
						
						
						$stmtdrins->close();
						if($formattedDate>$dipr_datet){
							// echo "IF $siteName: $formattedDate>=$dipr_datet<br>";
							$logOutput .= "$formattedDate>=$dipr_datet\n";
							$alert = 0;
							if($reorder_alertt< $currentvol){
								$alert = 1;
							}
							if($capacityt < 1) {
								$capacityt = 1;
							}
							$current_percent =  ($currentvol/$capacityt)*100;
						$stmttankupd = $conn->prepare("UPDATE Tanks SET current_volume = ?, ullage= ?, dipr_date = ?, dipr_date0 = ?, dipr_time = ?, dipr_time0 = ?, current_percent = ?, alert_flag = ? WHERE Site_id = ? and tank_id = ? and client_id = ?");
						$stmttankupd->bind_param("sssssssisis", $currentvol, $ullage, $formattedDate, $formattedDate0, $formattedTime, $formattedTime0, $current_percent, $alert, $site_id, $tankNo, $companyId);							
						if($stmttankupd->execute()){
							$logOutput .= "Tanks Has been updated to new volume: $currentvol which is equivalent to $current_percent\n-----------------------------\n";
						}
						
						// Update console last connection using local date/time (only for newer dates)
						if (!empty($uid) && $formattedDate !== null && $formattedTime !== null) {
							$stmtUpdateConsole = $conn->prepare("UPDATE console SET last_conndate = ?, last_conntime = ? WHERE uid = ?");
							$stmtUpdateConsole->bind_param("ssi", $formattedDate, $formattedTime, $uid);
							$stmtUpdateConsole->execute();
							if ($stmtUpdateConsole->affected_rows > 0) {
								$logOutput .= "Console updated: uid=$uid last_conn={$formattedDate} {$formattedTime}\n";
							}
							$stmtUpdateConsole->close();
						}
					}
					else if($formattedDate==$dipr_datet && $formattedTime>$dipr_timet){
							// echo "IF $siteName: $formattedDate==$dipr_datet && $formattedTime>$dipr_timet<br>";
							$logOutput .= "$formattedDate>=$dipr_datet && $formattedTime>$dipr_timet\n";
							$alert = 0;
							if($reorder_alertt< $currentvol){
								$alert = 1;
							}
							if($capacityt < 1) {
								$capacityt = 1;
							}
							$current_percent =  ($currentvol/$capacityt)*100;
						$stmttankupd = $conn->prepare("UPDATE Tanks SET current_volume = ?, ullage= ?, dipr_date = ?, dipr_date0 = ?, dipr_time = ?, dipr_time0 = ?, current_percent = ?, alert_flag = ? WHERE Site_id = ? and tank_id = ? and client_id = ?");
						$stmttankupd->bind_param("sssssssisis", $currentvol, $ullage, $formattedDate, $formattedDate0, $formattedTime, $formattedTime0, $current_percent, $alert, $site_id, $tankNo, $companyId);							
						if($stmttankupd->execute()){
							$logOutput .= "Tanks Has been updated to new volume: $currentvol which is equivalent to $current_percent\n-----------------------------\n";
						}
						
						// Update console last connection using local date/time (only for newer dates/times)
						if (!empty($uid) && $formattedDate !== null && $formattedTime !== null) {
							$stmtUpdateConsole = $conn->prepare("UPDATE console SET last_conndate = ?, last_conntime = ? WHERE uid = ?");
							$stmtUpdateConsole->bind_param("ssi", $formattedDate, $formattedTime, $uid);
							$stmtUpdateConsole->execute();
							if ($stmtUpdateConsole->affected_rows > 0) {
								$logOutput .= "Console updated: uid=$uid last_conn={$formattedDate} {$formattedTime}\n";
							}
							$stmtUpdateConsole->close();
						}
					}
					else{
							// echo "ELSE $siteName: $formattedDate>=$dipr_datet && $formattedTime>$dipr_timet<br>";
							$logOutput .= "Skipped\n";
							$logOutput .= "\n-----------------------------\n";
							$logOutput .= "$formattedDate>=$dipr_datet && $formattedTime>$dipr_timet\n";
							continue;
						}
						$stmttankupd->close();
						$cvol10 = $current_volumet * 0.1;
						$cvol = $current_volumet + $cvol10;
						if($cvol < $currentvol){
							$logOutput .= "Delivery detected $siteName, previous vol: $current_volumet and new Vol: $currentvol > $cvol.\n";
							echo "Delivery detected $siteName, previous vol: $current_volumet and new Vol: $currentvol > $cvol.<br>";
							$delivery = $currentvol - $current_volumet;
							$stmtdipr = $conn->prepare("INSERT INTO delivery_historic (uid, transaction_date, transaction_date_utc0, transaction_time, transaction_time_utc0, tank_id, current_volume, delivery, mcs_transaction_id, site_name, site_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
							$stmtdipr->bind_param("issssissisi", $uid, $formattedDate, $formattedDate0, $formattedTime, $formattedTime0, $tankNo, $currentvol, $delivery, $id, $siteName, $site_id);
							if($stmtdipr->execute()){
								$logOutput .= "Delivery Inserted; \n";
							}
							else{
								$logOutput .= "Delivery Insert error $conn->error \n";
							}
							$stmtdipr->close();
						}
						$cap = Null;
						$ullage = Null;
					}
				} else {
					$logOutput .= "Error: Invalid response from API.\n";
				}

				// Check if there is more data available
				if (isset($data['next']) && !empty($data['next'])) {
					$logOutput .= "Next page URL (relative): " . $data['next'] . "\n";
					$url = $data['next'];
				} else {
					$url = null;
					$total += $coundata;
				}				
				
			}
		}

		// Finally, write the accumulated log output to the file
		$conn->close();

	} else {
		$logOutput .= "Error: No company IDs found in the database.\n";
	}
		// echo $logOutput . "\n";
		$logOutput .= "Total : $total \n";
		$logOutput .= "\n";
		echo "Total: $total <br>";
		file_put_contents($logFilePath, $logOutput, FILE_APPEND);	
?>