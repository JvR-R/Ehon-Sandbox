<?php
include('../../../db/dbh.php');
include('../../../db/logpriv.php');

// Export selected checkboxes as CSV and update flag values
if (isset($_POST["export"])) {
    $selectedCheckboxes = $_POST["selected_checkboxes"];

    if (!empty($selectedCheckboxes)) {
        $selectedIds = implode(",", $selectedCheckboxes);

        // Update flag values
        $updateSql = "UPDATE users AS us INNER JOIN card_transaction AS ct SET ct.flag = 1 WHERE ct.flag in (0,2,3) AND us.id = ct.company_id AND ct.transaction_id IN ($selectedIds)";
        $conn->query($updateSql);

        // Export selected data as CSV
        $exportSql = "SELECT * FROM users AS us INNER JOIN card_transaction AS ct ON us.id = ct.company_id WHERE ct.flag = 1 AND ct.transaction_id IN ($selectedIds)";
        $exportResult = $conn->query($exportSql);
        // Create CSV file
        $filename = "Transactions.csv";
        $delimiter = ",";

        $file = fopen($filename, "w");

        // Write headers to CSV
        $headers = array(
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
        );
        fputcsv($file, $headers, $delimiter);
        $Taf = 0;
        $Tsf = 0;
        $Tac = 0;
        $Tvol = 0;
        $CPY = 0;
        // Write data rows to CSV
        while ($row = $exportResult->fetch_assoc()) {
            $feeType = $row["id_fee"];
            $sf = 0;
            $bf = 0;
            $if = 0;
            $af = 0;
            $ac = 0;
            $company_name = $row["company_name"];
            $saasuid = $row["saasu_id"];
            $sf = $row["quantity"] * 0.011;
            $Tsf = $Tsf + $sf;
            $bf = $row["total_price"] * 0.0025;
            $if = $row["total_price"] * 0.0088;
            $af = $bf + $if + 0.55 + 0.14;
            $Taf = $Taf + $af;
            $quantity = $row["quantity"];
            $priceCustomer = $row["price_customer"];
            $resulttest = bcmul($quantity, $priceCustomer, 2);
            $ac = floor(bcmul($resulttest, 100)) / 100;
            $Tac = $Tac + $ac;
            $Tvol = $Tvol + $row["quantity"];
            $data = array(
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
                "$" . number_format($sf,2),
                "$" . number_format($bf,2),
                "$" . number_format($if,2),
                "$" . number_format($af,2),
                "$" . $ac,
                "$" . ($row["total_price"] - $sf - $af),
                $Tvol                 
                );

            fputcsv($file, $data, $delimiter);
        }
        $roundedDown = floor($Tsf * 100) / 100;
        $roundedDown2 = floor($Taf*100)/100;
        $CPY = $Tac - $roundedDown - $roundedDown2;
        for ($i = 1; $i <= 4; $i++) {
            if ($i ==1){
            $emptyRow = array("", "");
            }
            if ($i ==2){
                $emptyRow = array("iPETROPAY-SERVICEFEE: ", number_format($Tvol,2), "$" . number_format($Tsf,2),);
                }
            if ($i ==3){
            $emptyRow = array("iPETROPAY-MERCHANTFEE: ", "", "$" . number_format($roundedDown2,2));
            }
            if ($i ==4){
                $emptyRow = array("iPETROPAY-FUELSALE: ", "", "$-" . number_format($Tac,2));
                }
            fputcsv($file, $emptyRow, $delimiter);
        }
        $csvFileContent = file_get_contents($filename);
        $base64CsvFile = base64_encode($csvFileContent);

        $currentDateTime = date('Y-m-d');
        $currentDateTime = new DateTime(); // Create a DateTime object with the current date and time
        $currentDateTime->modify('+0 hours'); // Add 10 hours to the DateTime object
        $formattedDateTime3 = $currentDateTime->format('Y-m-d');
        $currentDateTime2 = new DateTime(); // Create a DateTime object with the current date and time
        $currentDateTime2->modify('-1 day'); // Subtract one day from the current date       
        $formattedDateTime = $currentDateTime2->format('Y-m-d'); // Format the DateTime object as needed
        $formattedDateTime2 = $currentDateTime2->format('d-m');
        $TotalAmountSF = number_format($roundedDown,2);
        $TotalAmountMF = number_format($roundedDown2,2);
        $TotalAmountFST = $Tac;
        $Quantity = 3.0;
        $UnitPrice = 13.5;
        //$accessToken = "8CAB67696F864F7DB4E0549AD5ABFD60";
        $accessToken = "CE087200CA8943299495B67B014750A0";
        //$fileId = 85565;
        $fileId = 29105;
        $invoiceUrl = "https://api.saasu.com/Invoice?FileId=$fileId&wsAccessKey=$accessToken";
        
        $invoiceData = '{
            "LineItems": [
                {
                    "Id": null,
                    "Description": "iPETRO PAY Service Fee per Litre",
                    "AccountId": null,
                    "TaxCode": "G1",
                    "TotalAmount":' . $TotalAmountSF . ',
                    "Quantity":' . $Tvol . ',
                    "UnitPrice": 0.011,
                    "PercentageDiscount": null,
                    "InventoryId": 6194533,
                    "ItemCode": "IPETROPAY-SERVICEFEE",
                    "Tags": [],
                    "Attributes": [],
                    "_links": []
                },
                {
                    "Id": null,
                    "Description": "iPETRO PAY Merchant Fee",
                    "AccountId": null,
                    "TaxCode": "G1",
                    "TotalAmount":' . $TotalAmountMF . ',
                    "Quantity": 1.0,
                    "UnitPrice":' . $TotalAmountMF . ',
                    "PercentageDiscount": null,
                    "InventoryId": 6194535,
                    "ItemCode": "IPETROPAY-MERCHANTFEE",
                    "Tags": [],
                    "Attributes": [],
                    "_links": []
                },
                {
                    "Id": null,
                    "Description": "iPETRO PAY Fuel Sale Total $ Amount",
                    "AccountId": null,
                    "TaxCode": "G1",
                    "TotalAmount":' . $TotalAmountFST . ',
                    "Quantity": -1.0,
                    "UnitPrice":' . $TotalAmountFST . ',
                    "PercentageDiscount": null,
                    "InventoryId": 6194541,
                    "ItemCode": "IPETROPAY-MERCHANTFEE",
                    "Tags": [],
                    "Attributes": [],
                    "_links": []
                }
            ],
            "NotesInternal": null,
            "NotesExternal": null,
            "Terms": {
                "Type": 3,
                "Interval": null,
                "IntervalType": 4,
                "TypeEnum": "CashOnDelivery",
                "IntervalTypeEnum": "CashOnDelivery"
            },
            "Attachments": [
                {
                    "Id": null,
                    "Size": 0,
                    "Name": "test.txt",
                    "Description": "Test document",
                    "ItemIdAttachedTo": ,
                    "_links": [
                        {
                            "rel": "detail",
                            "href": "https://api.saasu.com/InvoiceAttachment/1?FileId=123",
                            "method": "POST",
                            "title": null
                        }
                    ]
                }
            ],
            "TemplateId": null,
            "ForEntityTypeId": null,
            "SendEmailToContact": null,
            "EmailMessage": null,
            "QuickPayment": null,
            "TransactionId": 5093684,
            "LastUpdatedId": "AAAAAAAKgc=",
            "Currency": "AUD",
            "InvoiceNumber":  "<Auto Number>",
            "InvoiceType": "Sale Order",
            "TransactionType": "S",
            "Layout": "I",
            "BrandId": null,
            "Summary": "iPETRO PAY Summary for ' . $formattedDateTime . '",
            "TotalAmount": null,
            "TotalTaxAmount": null,
            "IsTaxInc": true,
            "AmountPaid":null,
            "AmountOwed": null,
            "FxRate": null,
            "AutoPopulateFxRate": false,
            "RequiresFollowUp": false,
            "SentToContact": false,
            "TransactionDate": "' . $formattedDateTime3 . '",
            "BillingContactId": ' . $saasuid . ',
            "BillingContactFirstName": null,
            "BillingContactLastName": null,
            "BillingContactOrganisationName": "Petro Industrial (BNE) Pty Ltd",
            "ShippingContactId": null,
            "ShippingContactFirstName": null,
            "ShippingContactLastName": null,
            "ShippingContactOrganisationName": null,
            "CreatedDateUtc": "2023-08-21T21:52:22.8962137Z",
            "LastModifiedDateUtc": "2023-08-20T21:52:22.8962137Z",
            "PaymentStatus": "U",
            "DueDate": null,
            "InvoiceStatus": "I",
            "PurchaseOrderNumber": null,
            "PaymentCount": 0,
            "Tags": [
                "System",
                "iPetroPay"
            ],
            "_links": []
        }';
        
        $headers = array(
            'Content-Type: application/json',
        );
        
        $ch = curl_init($invoiceUrl);
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $invoiceData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }
        rewind($verbose);
        // $verboseLog = stream_get_contents($verbose);
        echo "Verbose information:\n<br>", $verboseLog, "\n<br>";
        curl_close($ch);
        $responseData = json_decode($response, true);
        echo "<pre>";
        // print_r($responseData);
        echo "</pre>";

 // Now, use the invoiceId to add the attachment
    $attachmentUrl = "https://api.saasu.com/InvoiceAttachment?FileId=$fileId&wsAccessKey=$accessToken";

    if (isset($responseData['InsertedEntityId'])) {
    $insertedEntityId = $responseData['InsertedEntityId'];
    $attachmentData = array(
        "AttachmentData" => $base64CsvFile,
        "Name" => $formattedDateTime2 . "-" . $company_name . ".csv",
        "Description" => "CSV attachment for " . $company_name .  " Submitted from Ehon portal on " . $formattedDateTime3,
        "ItemIdAttachedTo" => $insertedEntityId,
        "_links" => []
    );

    curl_setopt($ch, CURLOPT_URL, $attachmentUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($attachmentData));

    $attachmentResponse = curl_exec($ch);

// Close the curl handle
    curl_close($ch);
    }


        fclose($file);
        // Download CSV file
        // header("Content-Disposition: attachment; filename=$filename");
        // header("Content-Type: application/csv");
        // readfile($filename);
        echo '<script type="text/javascript">alert("Upload Successful. Total: ' . $TotalAmountFST . '");
        window.location.href="/vmi/ipetropay/payment";
        </script>';


        exit;
    }

}

$conn->close();
?>
