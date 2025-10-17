<?php
/**
 * Example: Create a Cash Sale in NetSuite using the "netsuite-php" library
 * 
 * 1. Install the library (if not done already): 
 *      composer require ryanwinchester/netsuite-php
 * 2. Enable ext-soap in PHP
 * 3. Adjust the config array below with your TBA credentials & account
 */

require __DIR__ . '/../../vendor/autoload.php';  // Adjust the path as needed

use NetSuite\NetSuiteService;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\AddRequest;
use NetSuite\Classes\CashSaleItem;
use NetSuite\Classes\CashSaleItemList;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\SelectCustomFieldRef;
use NetSuite\Classes\ListOrRecordRef;

/**
 * 1) Configure TBA and NetSuite connection
 *    - Ensure "soap" extension is enabled in PHP
 *    - Update "log_path" to a valid writable directory on your system
 */
$config = [
    // Required settings
    "endpoint"       => "2021_1", // Use the appropriate NetSuite API version
    "host"           => "https://webservices.netsuite.com", // SOAP endpoint without trailing slash
    "account"        => "9250724_SB1",  
    "consumerKey"    => "5bb2707bd81dd3798d5ce1ab3455983d6461f10c860d99f80a4f1ae543c8f105",
    "consumerSecret" => "d89fb1509ed7ae33dd8ca08c9a4f101578620c9720a4de831916a45666ccef63",
    "token"          => "d50b8983603735c842d77909c1379721f4a814281962fbbd0e39e2e694c9694b",
    "tokenSecret"    => "54568e9063fa2acdfca9e0a80a66e1ebe222610c607e411cb14e0883252836f8",
    
    // Optional settings
    "signatureAlgorithm" => 'sha256', // Defaults to 'sha256' if not set
    "logging"  => true,
    "log_path" => "C:/xampp2/htdocs/sandbox/vmi/ipetropay/logs", // Ensure this directory exists and is writable
    "log_format"     => "netsuite-php-%date-%operation",
    "log_dateformat" => "Ymd.His.u",
];

// Ensure the log path exists
if (!is_dir($config['log_path'])) {
    if (!mkdir($config['log_path'], 0777, true)) {
        die("Failed to create log directory: " . $config['log_path']);
    }
}

// Instantiate the NetSuite service
$service = new NetSuiteService($config);

// Build the CashSale object
$cashSale = new CashSale();

// Set required fields

// 1. Customer (Entity)
$cashSale->entity = new RecordRef();
$cashSale->entity->internalId = "7755"; // Replace with actual Customer internal ID

// 2. Subsidiary
$cashSale->subsidiary = new RecordRef();
$cashSale->subsidiary->internalId = "2"; // Replace with actual Subsidiary ID

// 3. Location (Required Field)
$cashSale->location = new RecordRef();
$cashSale->location->internalId = "17"; // Set to Location internal ID

// 4. Transaction Date
$cashSale->tranDate = "2025-01-09T00:00:00Z"; // ISO 8601 format

// 5. Undeposited Funds
$cashSale->undepFunds = true;

// 6. Memo
$cashSale->memo = "iPETRO PAY Summary TEST for 2025-01-09";

// Add line items
$item = new CashSaleItem();
$item->item = new RecordRef();
$item->item->internalId = "41969"; // Replace with actual Item internal ID
$item->quantity = 1;
$item->amount = 6467.97;

$itemList = new CashSaleItemList();
$itemList->item = [$item];
$cashSale->itemList = $itemList;

// **Set the Sales Channel as a Custom Segment Field**
$customField = new SelectCustomFieldRef();
$customField->scriptId = 'cseg_sales_channel'; // Correct Script ID for Custom Segment

// IMPORTANT:
// - The 'value' should be a ListOrRecordRef object with the internalId of the desired Sales Channel option.
// - From your GET response, 'iPetro Subscriptions and iPetro Pay' has internal ID '5'.
// - The 'type' should correspond to the custom record type, e.g., 'customrecord_cseg_sales_channel'.
$listOrRecordRefForSalesChannel = new ListOrRecordRef();
$listOrRecordRefForSalesChannel->internalId = '5'; // Replace '5' with the actual internal ID
$listOrRecordRefForSalesChannel->type = 'customrecord_cseg_sales_channel'; // Replace with actual record type if different

$customField->value = $listOrRecordRefForSalesChannel;

// Assign the custom field to the CustomFieldList
$customFieldList = new CustomFieldList();
$customFieldList->customField = [$customField];
$cashSale->customFieldList = $customFieldList;

// **Debugging: Verify CustomFieldList Before Sending**
echo "Custom Fields before sending:\n";
print_r($cashSale->customFieldList->customField);

// Create the AddRequest
$addRequest = new AddRequest();
$addRequest->record = $cashSale;

echo "Sending request to create Cash Sale...\n";

try {
    $addResponse = $service->add($addRequest);

    if (!$addResponse->writeResponse->status->isSuccess) {
        // Display and log the error
        $details = $addResponse->writeResponse->status->statusDetail;
        $errorMessages = "Error creating Cash Sale:\n";
        foreach ($details as $detail) {
            $errorMessages .= "- " . $detail->message . "\n";
        }
        echo $errorMessages;

        // Log the error to a file
        $errorLogPath = $config['log_path'] . '/error_log_' . date('Ymd') . '.txt';
        file_put_contents($errorLogPath, $errorMessages, FILE_APPEND);
        exit(1);
    }

    // Retrieve the new record's internal ID
    $baseRef = $addResponse->writeResponse->baseRef;
    $newCashSaleId = $baseRef->internalId;

    echo "Cash Sale created successfully! Internal ID: {$newCashSaleId}\n";
} catch (SoapFault $e) {
    // Handle SOAP Fault
    $soapError = "SOAP Fault: (faultcode: {$e->faultcode}, faultstring: {$e->getMessage()})\n";
    echo $soapError;

    // Log the full exception to a file
    $soapFaultLogPath = $config['log_path'] . '/soap_fault_log_' . date('Ymd') . '.txt';
    file_put_contents($soapFaultLogPath, $e->__toString(), FILE_APPEND);
    exit(1);
}
?>
