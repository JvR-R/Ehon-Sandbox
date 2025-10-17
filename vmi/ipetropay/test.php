<?php
/**
 * Example: Create a Cash Refund in NetSuite using the "netsuite-php" library
 * 
 * 1. Installs library (if not done already): 
 *      composer require ryanwinchester/netsuite-php
 * 2. Enable ext-soap in PHP
 * 3. Adjust the config array below with your TBA credentials & account
 */
require __DIR__ . '/../../vendor/autoload.php';  // Autoload the NetSuite classes

use NetSuite\NetSuiteService;
use NetSuite\Classes\CashRefund;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\AddRequest;
use NetSuite\Classes\CashRefundItem;
use NetSuite\Classes\CashRefundItemList;

/**
 * 1) Configure TBA and NetSuite connection
 *    - Make sure "soap" extension is enabled in the CLI or web environment you use.
 */
$config = [
    // required -------------------------------------
    "endpoint"       => "2021_1",
    "host"           => "https://webservices.netsuite.com",
    "account"        => "9250724_SB1",
    "consumerKey"    => "5bb2707bd81dd3798d5ce1ab3455983d6461f10c860d99f80a4f1ae543c8f105",
    "consumerSecret" => "d89fb1509ed7ae33dd8ca08c9a4f101578620c9720a4de831916a45666ccef63",
    "token"          => "d50b8983603735c842d77909c1379721f4a814281962fbbd0e39e2e694c9694b",
    "tokenSecret"    => "54568e9063fa2acdfca9e0a80a66e1ebe222610c607e411cb14e0883252836f8",
    // optional -------------------------------------
    "signatureAlgorithm" => 'sha256', // Defaults to 'sha256'
    "logging"  => true,
    "log_path" => "/var/www/myapp/logs/netsuite",
    "log_format"     => "netsuite-php-%date-%operation",
    "log_dateformat" => "Ymd.His.u",
];
$service = new NetSuiteService($config);
// 2) Instantiate the service
$service = new NetSuiteService($config);

/**
 * 3) Build a new CashRefund object
 *    - Minimal fields + referencing an existing Cash Sale via "createdFrom"
 *    - NetSuite may or may not show the link in the UI depending on your account's behavior.
 *    - If NetSuite truly requires a transform approach, this might only partially link.
 */

// Create the CashRefund record
$cashRefund = new CashRefund();

// Reference an existing Cash Sale
$cashRefund->createdFrom = new RecordRef();
$cashRefund->createdFrom->internalId = "20814"; // Cash Sale internal ID (example)
$cashRefund->createdFrom->type = "cashSale";    // Possibly optional

// Example: link to the same Customer as the Cash Sale or a different one
$cashRefund->entity = new RecordRef();
$cashRefund->entity->internalId = "7755"; // Customer ID

// Example: set a "cash/bank" account
$cashRefund->account = new RecordRef();
$cashRefund->account->internalId = "1110"; // e.g. a bank or undeposited funds

// Add a memo
// $cashRefund->memo = "iPETRO PAY Summary TEST for 2025-01-01";

/**
 * 4) (Optional) Add a single line item to the refund
 *    If referencing "createdFrom", NetSuite might auto-populate lines—depends on your account.
 *    For safety, let's illustrate how to add 1 item line manually.
 */
$item1 = new CashRefundItem();
$item1->item = new RecordRef();
$item1->item->internalId = "41969"; // Replace with actual Item internal ID
$item1->quantity = 1;
$item1->amount = 6467.97;  // The rate/amount for this line

$item2 = new CashRefundItem();
$item2->item = new RecordRef();
$item2->item->internalId = "41971"; // Replace with actual Item internal ID
$item2->quantity = -3575.43; // Negative quantity for refund
$item2->rate = 0.01; // Adjust as needed

$item3 = new CashRefundItem();
$item3->item = new RecordRef();
$item3->item->internalId = "41970"; // Replace with actual Item internal ID
$item3->quantity = 1; // Negative quantity for refund
$item3->amount = -114.48; // Adjust as needed

$items = [$item1, $item2, $item3];

// Assign items to CashRefundItemList
$itemList = new CashRefundItemList();
$itemList->item = $items;
$cashRefund->itemList = $itemList;


/**
 * 5) Send "add" request to NetSuite
 */
$addRequest = new AddRequest();
$addRequest->record = $cashRefund;

echo "Sending request to create Cash Refund...\n";

$addResponse = $service->add($addRequest);

if (!$addResponse->writeResponse->status->isSuccess) {
    // Display the error
    $details = $addResponse->writeResponse->status->statusDetail;
    echo "Error creating Cash Refund:\n";
    foreach ($details as $detail) {
        echo "- " . $detail->message . "\n";
    }
    exit(1);
}

// On success, retrieve the new record's internal ID
$newRefundId = $addResponse->writeResponse->baseRef->internalId;
echo "Cash Refund created successfully! Internal ID: {$newRefundId}\n";
