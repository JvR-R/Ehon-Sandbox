<?php
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

// Export selected checkboxes as CSV and update flag values
if (isset($_POST["export"])) {
    $selectedCheckboxes = $_POST["selected_checkboxes"];

    if (!empty($selectedCheckboxes)) {
        $selectedIds = implode(",", $selectedCheckboxes);

        // Export selected data as CSV
        $exportSql = "SELECT * FROM ipetroco_ehon_tsm.users AS us INNER JOIN ipetroco_ehon_tsm.card_transaction AS ct ON us.id = ct.company_id WHERE ct.transaction_id IN ($selectedIds)";
        $exportResult = $conn->query($exportSql);

        // Create CSV file
        $filename = "export.csv";
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
            "Volume"
        );
        fputcsv($file, $headers, $delimiter);

        // Write data rows to CSV
        while ($row = $exportResult->fetch_assoc()) {
            $data = array(
                $row["transaction_time"],
                $row["transaction_date"],
                $row["card_holder_name"],
                $row["card_number"],
                $row["customer_name"],
                $row["product_name"],
                $row["price_customer"],
                $row["total_price"],
                $row["quantity"]
            );
            fputcsv($file, $data, $delimiter);
        }

        fclose($file);

        // Download CSV file
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-Type: application/csv");
        readfile($filename);
        exit;
    }

}

$conn->close();
?>
