<?php
// Include necessary database and logging files
include('../../../db/dbh.php');
include('../../../db/logpriv.php');

// -----------------------------------------------------------------------------
// 1) CONFIGURATION
// -----------------------------------------------------------------------------
/**
 * Base URL for NetSuite’s REST web services.
 * e.g. https://9250724-sb1.suitetalk.api.netsuite.com
 */
define('NETSUITE_BASE_URL', 'https://9250724-sb1.suitetalk.api.netsuite.com');

/**
 * The folder ID where CSV files will be uploaded in the File Cabinet.
 * Adjust as needed.
 */
define('NETSUITE_FOLDER_ID', '947');

/**
 * Retrieve (or set) your OAuth2 bearer token.
 * In real usage, you should call NetSuite’s
 * /services/rest/auth/oauth2/v1/token endpoint using your refresh_token
 * to get a fresh access token. For brevity, we’ll store a placeholder here.
 */
function getActiveTokens($conn, $idactive_token = 1) {
    $stmt = $conn->prepare("SELECT token_id, token_refresh, activation_time FROM ehonener_ehon_tsm.active_token WHERE idactive_token = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param("i", $idactive_token);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $tokenData = $result->fetch_assoc();

    if (!$tokenData) {
        throw new Exception("No active tokens found in the database.");
    }

    $stmt->close();
    $activationTime = $tokenData['activation_time'];
    $activationDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $activationTime);
    if (!$activationDateTime) {
        throw new Exception("Invalid activation_time format.");
    }

    $currentDateTime = new DateTime("now", new DateTimeZone("UTC"));
    $activationDateTime->setTimezone(new DateTimeZone("UTC")); // Ensure same timezone

    $interval = $currentDateTime->getTimestamp() - $activationDateTime->getTimestamp();
    $intervalMinutes = $interval / 60;
    if($intervalMinutes >= 50) {
        $newtoken = refreshAccessToken($tokenData['token_refresh'],'42f1b5f1efada912b6bb28f5d95009a21f2e4f487061f0d96fd34c0cc957dcdc','6dada77b4d0e20d5bce92b3dd41df980fe979bfe4661bd8755c3a15cd8bbf3fd');
        updateTokens($conn, $idactive_token, $newtoken);
        return $newtoken;
    }
    else{
        return $tokenData['token_id'];
        // return [
        //     'access_token'    => $tokenData['token_id'],
        //     'refresh_token'   => $tokenData['token_refresh'],
        //     'activation_time' => $tokenData['activation_time']
        // ];
    }
}


function refreshAccessToken($refreshToken, $clientId, $clientSecret) {
    $url = 'https://9250724-sb1.suitetalk.api.netsuite.com/services/rest/auth/oauth2/v1/token';

    $postFields = http_build_query([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id'     => $clientId,
        'redirect_uri' => 'https://ehonenergy.com.au/vmi/ipetropay/payment/netsuite_callback',
        'client_secret' => $clientSecret
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    // echo '<pre>' . print_r($postFields, true) . '</pre><br>';
    // echo '<pre>' . print_r($response, true) . '</pre><br>';

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error while refreshing token: " . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to refresh token. HTTP Code: $httpCode. Response: $response");
    }

    $decoded = json_decode($response, true);
    if (!isset($decoded['access_token'])) {
        throw new Exception("Access token not found in refresh response.");
    }
    return $decoded['access_token'] ;
}
function updateTokens($conn, $idactive_token, $newAccessToken) {
    // Get the current UTC time
    $activationTime = (new DateTime("now", new DateTimeZone("UTC")))->format('Y-m-d H:i:s');

    if ($newAccessToken) {
        // Update both access token and refresh token
        $stmt = $conn->prepare("UPDATE ehonener_ehon_tsm.active_token SET token_id = ?, activation_time = ? WHERE idactive_token = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param("ssi", $newAccessToken, $activationTime, $idactive_token);
    } else {
            throw new Exception("No Acees token: " . $newAccessToken);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to update tokens: " . $stmt->error);
    }

    $stmt->close();
}
// -----------------------------------------------------------------------------
// 2) CSV EXPORT FUNCTION (unchanged from your code except minor adjustments)
// -----------------------------------------------------------------------------

/**
 *   - Flags selected transactions in DB.
 *   - Groups them by (company, date) for multiple Cash Sales (returned in dateGroups).
 *   - Creates ONE CSV containing all transactions from all dates.
 *   - Accumulates sums for ServiceFee, MerchantFee, FuelSale, etc.
 *   - Writes a single summary row at the end.
 */
function exportTransactions($conn, $selectedCheckboxes) {
    if (empty($selectedCheckboxes)) {
        throw new Exception("No transactions selected for export.");
    }

    $selectedIds = implode(",", array_map('intval', $selectedCheckboxes));

    // 1) Update flags
    $updateSql = "
        UPDATE users AS us
        INNER JOIN card_transaction AS ct ON us.id = ct.company_id
        SET ct.flag = 1
        WHERE ct.flag IN (0, 2, 3)
          AND ct.transaction_id IN ($selectedIds)
    ";
    if (!$conn->query($updateSql)) {
        throw new Exception("Failed to update transaction flags: " . $conn->error);
    }

    // 2) Summaries grouped by date (for multiple Cash Sales)
    $exportSql = "
        SELECT 
            us.company_name,
            us.saasu_id,
            ct.transaction_date,
            ct.settlement_date,
            SUM(ct.quantity) AS total_quantity,
            SUM(ct.total_price) AS total_total_price
        FROM users AS us
        INNER JOIN card_transaction AS ct ON us.id = ct.company_id
        WHERE ct.transaction_id IN ($selectedIds)
        GROUP BY us.company_name, us.saasu_id, ct.settlement_date
        ORDER BY ct.settlement_date
    ";
    $exportResult = $conn->query($exportSql);
    if (!$exportResult) {
        throw new Exception("Failed to fetch summary data: " . $conn->error);
    }

    // 3) CSV with all transactions from all dates
    $filename = "Transactions.csv";
    $file = fopen($filename, "w");
    if (!$file) {
        throw new Exception("Cannot open file for writing: $filename");
    }

    // Write CSV headers once
    $headers = [
        "Transaction Time",
        "Transaction Date",
        "Card Holder Name",
        "Card Number",
        "Customer Name",
        "Product",
        "Customer Price",
        "Total Price",
        "Volume",
        "Fee Type",
        "Switch",
        "Bank",
        "iPETROPAY-ServiceFee",
        "Bank Fee",
        "Interchange Fee",
        "iPETROPAY-MERCHANTFEE",
        "iPETROPAY-FUELSALE",
        "Payment to customer",
        "Total Volume"
    ];
    fputcsv($file, $headers);

    // We'll store data for multiple Cash Sales
    $dateGroups = [];

    // Grand totals for the SINGLE summary lines in the CSV:
    $sumAllPrices      = 0.0;
    $sumAllQuantity    = 0.0;
    $sumAllServiceFee  = 0.0;
    $sumAllMerchantFee = 0.0;
    $sumAllFuelSale    = 0.0;
    $latestDate        = null;

    // For each group (company, date)
    while ($summaryRow = $exportResult->fetch_assoc()) {
        $companyName = $summaryRow["company_name"];
        $saasuId     = $summaryRow["saasu_id"];
        $date        = $summaryRow["transaction_date"];
        $settleDate  = $summaryRow["settlement_date"];
        $sumQty      = $summaryRow["total_quantity"];
        $sumPrice    = $summaryRow["total_total_price"];

        // Update global sums
        $sumAllPrices   += $sumPrice;
        $sumAllQuantity += $sumQty;
        if ($latestDate === null || $date > $latestDate) {
            $latestDate = $date;
        }

        // Save one entry for this date => separate Cash Sale
        $dateGroups[] = [
            'company_name'     => $companyName,
            'saasuid'          => $saasuId,
            'transaction_date' => $date,
            'settlement_date'  => $settleDate,
            'total_amount_fst' => $sumPrice,
            'quantity'         => $sumQty
        ];

        // Now fetch the individual transactions for the CSV
        $detailsSql = "
        SELECT
            ct.transaction_time,
            ct.transaction_date,
            ct.card_holder_name,
            ct.card_number,
            ct.customer_name,
            ct.product_name,
            ct.price_customer,
            ct.total_price,
            ct.quantity
        FROM card_transaction AS ct
        INNER JOIN users AS us ON us.id = ct.company_id
        WHERE ct.flag = 1
          AND ct.transaction_id IN ($selectedIds)
          AND us.company_name = '{$companyName}'
          AND us.saasu_id = '{$saasuId}'
          AND ct.settlement_date = '{$settleDate}'
    ";    
        $detailsRes = $conn->query($detailsSql);
        if (!$detailsRes) {
            fclose($file);
            throw new Exception("Failed to fetch detail for date $date: " . $conn->error);
        }

        while ($row = $detailsRes->fetch_assoc()) {
            $quantity   = $row['quantity'];
            $priceCust  = $row['price_customer'];
            $totalPrice = $row['total_price'];

            // Calculate fees
            $sf = $quantity * 0.01;                 // iPETROPAY-ServiceFee
            $bf = $totalPrice * 0.0025;             // Bank
            $if = $totalPrice * 0.0088;             // Interchange
            $af = $bf + $if + 0.55 + 0.14;          // Merchant fee
            $ac = bcmul($quantity, $priceCust, 2);  // Fuel sale

            // Accumulate into grand totals
            $sumAllServiceFee  += $sf;
            $sumAllMerchantFee += $af;
            $sumAllFuelSale    += $ac;

            // CSV row
            $data = [
                $row["transaction_time"],
                $row["transaction_date"],
                $row["card_holder_name"],
                "'" . $row["card_number"],
                $row["customer_name"],
                $row["product_name"],
                $row["price_customer"],
                $row["total_price"],
                $row["quantity"],
                "Standard",
                "$0.55",
                "$0.14",
                "$" . number_format($sf, 2),
                "$" . number_format($bf, 2),
                "$" . number_format($if, 2),
                "$" . number_format($af, 2),
                "$" . number_format($ac, 2),
                "$" . number_format($totalPrice - $sf - $af, 2),
                $quantity
            ];
            fputcsv($file, $data);
        }
    }

    // Now that we've written all transactions, add a SINGLE summary block
    fputcsv($file, ["", ""]);
    fputcsv($file, ["iPETROPAY-SERVICEFEE:", "", "$" . number_format($sumAllServiceFee, 2)]);
    fputcsv($file, ["iPETROPAY-MERCHANTFEE:", "", "$" . number_format($sumAllMerchantFee, 2)]);
    fputcsv($file, ["iPETROPAY-FUELSALE:", "", "$" . number_format($sumAllFuelSale, 2)]);

    fclose($file);

    $newname = "{$companyName}_{$latestDate}.csv";
    rename($filename, $newname);

    return [
        'csv_filename'     => $newname,
        'dateGroups'       => $dateGroups,
        'sumAllPrices'     => $sumAllPrices,
        'sumAllQuantity'   => $sumAllQuantity,
        'sumAllServiceFee' => $sumAllServiceFee,
        'sumAllMerchantFee'=> $sumAllMerchantFee,
        'sumAllFuelSale'   => $sumAllFuelSale,
        'latestDate'       => $latestDate
    ];
}

// -----------------------------------------------------------------------------
// 3) HELPER: POST JSON TO NETSUITE REST
// -----------------------------------------------------------------------------
/**
 * Generic helper to POST JSON to NetSuite's REST Record API.
 * Adjust if you want to handle PUT or GET, or if you want more error logs.
 */
/**
 * Generic helper to POST JSON to NetSuite's REST Record API.
 * Returns an associative array with 'body' and 'headers'.
 */
function netsuitePostJson($endpoint, array $body, $accessToken) {
    $url = rtrim(NETSUITE_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
    
    // Encode the body ensuring numeric values are not converted to strings
    $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    if ($jsonBody === false) {
        throw new Exception("Failed to encode JSON: " . json_last_error_msg());
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, // Include headers in the output
        CURLOPT_FOLLOWLOCATION => false // Do not follow redirects automatically
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error while POSTing to NetSuite: " . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersRaw = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    // Parse headers into an associative array
    $headers = [];
    $headerLines = explode("\r\n", $headersRaw);
    foreach ($headerLines as $headerLine) {
        if (strpos($headerLine, ':') !== false) {
            list($key, $value) = explode(':', $headerLine, 2);
            $headers[trim($key)] = trim($value);
        }
    }

    // Decode JSON body if not empty
    $decodedBody = [];
    if (!empty($body)) {
        $decodedBody = json_decode($body, true);
        if ($decodedBody === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }
    }

    return [
        'body' => $decodedBody,
        'headers' => $headers,
        'http_code' => $httpCode
    ];
}


// -----------------------------------------------------------------------------
// 4) CREATE CASH SALE USING REST
// -----------------------------------------------------------------------------
function createCashSaleForDateRest($dateData, $accessToken) {
    // JSON body for the Cash Sale
    $amount = ($dateData['total_amount_fst'])/1.1;
    $body = [
        "entity" => [
            "id"   => $dateData['saasuid'],
            "type" => "customer"
        ],
        "subsidiary" => [
            "id" => "2"
        ],
        "location" => [
            "id" => "17"
        ],
         "customForm" => [
            "id" => "179"
         ],
        "tranDate" => $dateData['settlement_date'], // format: YYYY-MM-DD
        "undepFunds" => [ "id" => true],
        "memo" => "iPETRO PAY Summary for " . $dateData['settlement_date'],
        "item" => [
            "items" => [
                [
                    "item" => [ "id" => "41969", "type" => "Non-inventory Item" ],
                    "quantity" => 1,
                    "amount"   => (float)$amount
                ]
            ]
        ],
        // Optional custom segment or field
        "cseg_sales_channel" => "6"  // <== Example. Adjust as needed.
    ];
    // Make POST request
    $response = netsuitePostJson('/services/rest/record/v1/cashSale', $body, $accessToken);
    // print_r($response) . "<br>";
    // Check if body contains 'id'
    if (!empty($response['body']['id'])) {
        return $response['body']['id'];
    }
    
    // If body is empty, check for 'Location' header
    if (empty($response['body']) && $response['http_code'] === 201 || $response['http_code'] === 204) {
        if (isset($response['headers']['location'])) {
            $location = $response['headers']['location'];
            // Extract the ID from the URL, assuming the URL ends with /cashsale/{id}
            $parts = explode('/', rtrim($location, '/'));
            $cashSaleId = end($parts);
            if (is_numeric($cashSaleId)) {
                return $cashSaleId;
            } else {
                throw new Exception("Invalid Cash Sale ID extracted from Location header: " . $location);
            }
        } else {
            throw new Exception("Cash Sale created but 'Location' header is missing.");
        }
    }
    error_log("Response Headers: " . print_r($response['headers'], true));
    error_log("Response Body: " . print_r($response['body'], true));
    error_log("HTTP Status Code: " . $response['http_code']);
    // If neither body nor Location header contains the ID, throw an error
    throw new Exception("Unable to retrieve Cash Sale ID from response.");
}

// -----------------------------------------------------------------------------
// 5) CREATE CASH REFUND USING REST
// -----------------------------------------------------------------------------
function createSingleCashRefundRest($cashSaleId, $latestDate, $saasUidForRefund,
                                    $sumAllFuelSale, $sumAllQuantity, $sumAllMerchantFee,
                                    $accessToken, $restletUrl) {
    // Define the JSON payload
    $formattedTranDate = date('d/m/Y', strtotime($latestDate));
    $payload = [
        "cashSaleId" => $cashSaleId,
        "entityId" => $saasUidForRefund,
        "tranDate" => $formattedTranDate, // Format: YYYY-MM-DD
        "memo" => "iPETRO PAY Refund for " . $latestDate,
        "items" => [
            [
                "itemId" => "41971", // Replace with your actual Item Internal ID
                "quantity" => -$sumAllQuantity,   // Negative for refund
                "rate" => 0.01
            ],
            [
                "itemId" => "41970", // Replace with your actual Item Internal ID
                "quantity" => -1,
                "rate" => $sumAllMerchantFee
            ]
        ]
    ];
    // print_r($payload) . "\r\n";
    // Initialize cURL
    $ch = curl_init($restletUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true // Ensure SSL verification
    ]);

    // Execute the request
    $response = curl_exec($ch);
    // print_r($response) . "\r\n";
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error while calling Restlet: " . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Decode the response
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON response from Restlet: " . json_last_error_msg());
    }

    // Handle the response
    if (isset($decodedResponse['success']) && $decodedResponse['success'] === true) {
        if (isset($decodedResponse['id'])) {
            // echo $decodedResponse['id']; // Move echo before return
            return ['id' => $decodedResponse['id']];
        } else {
            throw new Exception("Response indicates success but 'id' is missing.");
        }
    } else {
        $errorMessage = isset($decodedResponse['message']) ? $decodedResponse['message'] : 'Unknown error';
        throw new Exception("Failed to create Cash Refund: " . $errorMessage);
    }
}



// -----------------------------------------------------------------------------
// 6) UPLOAD CSV FILE TO NETSUITE USING REST
// -----------------------------------------------------------------------------
/**
 * Function to upload a CSV file and attach it to a Cash Refund via SuiteScript Restlet.
 *
 * @param string $csvFilePath Path to the local CSV file.
 * @param string $fileName Desired name for the CSV file in NetSuite.
 * @param int $folderId Internal ID of the target folder in NetSuite's File Cabinet.
 * @param int $cashRefundId Internal ID of the Cash Refund to attach the CSV to.
 * @param string $restletUrl The full URL of the SuiteScript Restlet.
 * @param string $accessToken OAuth2 access token.
 *
 * @return array Response from the Restlet containing success status and IDs.
 *
 * @throws Exception If any step fails.
 */
function uploadAndAttachCsv($csvFilePath, $fileName, $folderId, $cashRefundId, $restletUrl, $accessToken) {
    // Read the CSV file content
    $fileContents = file_get_contents($csvFilePath);
    if ($fileContents === false) {
        throw new Exception("Unable to read local CSV file: $csvFilePath");
    }

    // Base64-encode the file content
    $encodedContent = base64_encode($fileContents);
    if ($encodedContent === false) {
        throw new Exception("Failed to Base64-encode the CSV file content.");
    }

    // Prepare the payload
    $payload = [
        "name" => $fileName,
        "content" => $encodedContent,
        "folderId" => $folderId,
        "cashRefundId" => $cashRefundId
    ];

    // Initialize cURL
    $ch = curl_init($restletUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true // Ensure SSL verification
    ]);

    // Execute the request
    $response = curl_exec($ch);
    error_log("response" . $response);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error while calling Restlet: " . $err);
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Decode the JSON response
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON response from Restlet: " . json_last_error_msg());
    }

    // Check success status
    if (isset($decodedResponse['success']) && $decodedResponse['success'] === true) {
        return [
            'fileId' => $decodedResponse['fileId']
        ];
    } else {
        $errorMessage = isset($decodedResponse['message']) ? $decodedResponse['message'] : 'Unknown error';
        throw new Exception("Failed to upload and attach CSV: " . $errorMessage);
    }
}

// -----------------------------------------------------------------------------
// 7) MAIN LOGIC WHEN FORM SUBMITS
// -----------------------------------------------------------------------------
if (isset($_POST["export"])) {
    $selectedCheckboxes = $_POST["selected_checkboxes"] ?? [];
    error_log("SELECTED CHECKBOXES: " . print_r($selectedCheckboxes, true));
    
    try {
        // Step A: Export all transactions to CSV, gather date-based data + sums
        $exportData = exportTransactions($conn, $selectedCheckboxes);

        $dateGroups       = $exportData['dateGroups'];
        $sumAllPrices     = $exportData['sumAllPrices'];
        $sumAllQuantity   = $exportData['sumAllQuantity'];
        $sumAllServiceFee = $exportData['sumAllServiceFee'];
        $sumAllMerchantFee= $exportData['sumAllMerchantFee'];
        $sumAllFuelSale   = $exportData['sumAllFuelSale'];
        $latestDate       = $exportData['latestDate'];
        $csvFilename      = $exportData['csv_filename'];
        // echo $sumAllFuelSale . "<br>";

        // Obtain a valid OAuth2 Bearer token
        $accessToken = getActiveTokens($conn);  // Implement your real token retrieval

        // Restlet URL (replace placeholders with actual values)
        $restletUrl = 'https://9250724-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=2872&deploy=9';

        // Step B: Create multiple Cash Sales (one per date) via REST
        $lastCashSaleId      = null;
        $lastCashSaleDate    = null;
        $lastSaasUidForGroup = null;

        foreach ($dateGroups as $group) {
            $cashSaleId = createCashSaleForDateRest($group, $accessToken);
            if (!$lastCashSaleDate || $group['settlement_date'] > $lastCashSaleDate) {
                $lastCashSaleDate    = $group['settlement_date'];
                $lastCashSaleId      = $cashSaleId;
                $lastSaasUidForGroup = $group['saasuid'];
            }
        }

        // Step C: Create ONE Cash Refund referencing the latest date’s Cash Sale via Restlet
        $newRefundId = null;
        if ($lastCashSaleId && $lastSaasUidForGroup) {
            $newRefundId = createSingleCashRefundRest(
                $lastCashSaleId,
                $lastCashSaleDate,
                $lastSaasUidForGroup,
                $sumAllFuelSale,
                $sumAllQuantity,
                $sumAllMerchantFee,
                $accessToken,
                $restletUrl
            );
            // echo "<br>" . $newRefundId['id'] . "<br>";
        }
        $newRefundId = $newRefundId['id'];
        if ($newRefundId) {
            try {
                // Define the PATCH endpoint with the newRefundId
                $patchEndpoint = "/services/rest/record/v1/cashRefund/{$newRefundId}/item/1";
        
                // Construct the JSON payload
                $patchBody = [
                    "amount" => $sumAllFuelSale  // Ensure this is a numeric value
                ];
        
                // Perform the PATCH request
                $patchResponse = netsuitePatchJson($patchEndpoint, $patchBody, $accessToken);
        
                // Debugging: Print the response
                // echo "<br><br>PATCH Response:<br>";
                // print_r($patchResponse);
        
                // Check for successful update (typically HTTP 200)
                if ($patchResponse['http_code'] === 200 || $patchResponse['http_code'] === 204) {
                    // echo "<br>Cash Refund updated successfully.<br>";
                } else {
                    // Handle unexpected HTTP codes
                    throw new Exception("Unexpected HTTP code during PATCH: " . $patchResponse['http_code']);
                }
        
            } catch (Exception $ex) {
                // Log the error and provide feedback
                error_log("Failed to PATCH Cash Refund: " . $ex->getMessage());
                echo '<script>
                    alert("An error occurred while updating the Cash Refund: ' . addslashes($ex->getMessage()) . '");
                    window.location.href="/vmi/ipetropay/payment";
                </script>';
                exit;
            }
            try {
                // Define parameters for uploading and attaching the CSV
                $localCsvPath = $csvFilename; // Ensure this path is correct and accessible
                $fileName = basename($localCsvPath);
                $folderId = 947; // Defined in your configuration
                $cashRefundId = $newRefundId;
                $attachmentRestletUrl = 'https://9250724-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=2873&deploy=1'; // Replace with your actual Attachment Restlet URL

                // Call the combined function to upload and attach the CSV
                $uploadAttachResponse = uploadAndAttachCsv(
                    $localCsvPath,
                    $fileName,
                    $folderId,
                    $cashRefundId,
                    $attachmentRestletUrl, // The main Restlet URL handling upload and attach
                    $accessToken
                );

                // echo "CSV uploaded successfully with File ID: " . $uploadAttachResponse['fileId'] . "<br>";
                // echo "CSV attached successfully with Attachment ID: " . $uploadAttachResponse['attachmentId'] . "<br>";

            } catch (Exception $ex) {
                // Decide whether to fail the entire export or just log this error
                error_log("Failed to upload and attach CSV to Cash Refund: " . $ex->getMessage());
                echo '<script>
                    alert("An error occurred while uploading and attaching the CSV: ' . addslashes($ex->getMessage()) . '");
                    window.location.href="/vmi/ipetropay/payment";
                </script>';
                exit;
            }
        }

        // Step E: Finally, serve the CSV for download
        header("Content-Disposition: attachment; filename={$csvFilename}");
        header("Content-Type: application/csv");
        readfile($csvFilename);
        exit;
    } catch (Exception $e) {
        echo '<script>
            alert("An error occurred: ' . addslashes($e->getMessage()) . '");
            window.location.href="/vmi/ipetropay/payment";
        </script>';
        exit;
    }
}
// -----------------------------------------------------------------------------
// 8) Patch the fuelsale in the cashrefund
// -----------------------------------------------------------------------------
function netsuitePatchJson($endpoint, array $body, $accessToken) {
    $url = rtrim(NETSUITE_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
    
    // Encode the body to JSON
    $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    if ($jsonBody === false) {
        throw new Exception("Failed to encode JSON: " . json_last_error_msg());
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PATCH",  // Set method to PATCH
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, // Include headers in the output
        CURLOPT_SSL_VERIFYPEER => true // Ensure SSL verification
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error while PATCHing to NetSuite: " . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersRaw = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    // Parse headers into an associative array
    $headers = [];
    $headerLines = explode("\r\n", $headersRaw);
    foreach ($headerLines as $headerLine) {
        if (strpos($headerLine, ':') !== false) {
            list($key, $value) = explode(':', $headerLine, 2);
            $headers[trim($key)] = trim($value);
        }
    }

    // Decode JSON body if not empty
    $decodedBody = [];
    if (!empty($body)) {
        $decodedBody = json_decode($body, true);
        if ($decodedBody === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }
    }

    return [
        'body' => $decodedBody,
        'headers' => $headers,
        'http_code' => $httpCode
    ];
}

// Close DB
$conn->close();
?>
