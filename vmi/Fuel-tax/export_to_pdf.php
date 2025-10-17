<?php
// Start output buffering and clear any previous output
ob_start();

// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('DB_PATH', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');

// Include files using defined paths
include(DB_PATH);
include(LOG_PATH);
include(BORDER_PATH);

// Increase memory limit for handling large datasets
ini_set('memory_limit', '512M');

// Require the Composer autoloader
require '/home/ehonener/vendor/autoload.php';

use TCPDF;

// Create new PDF document
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Ehonenergy Tech');
$pdf->SetAuthor('Ehonenergy Tech');
$pdf->SetTitle('Fuel Tax Report');
$pdf->SetSubject('Fuel Tax Report');
$pdf->SetKeywords('Fuel, Tax, Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15, true);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Get filters from the request
// Assuming filters are sent as a JSON-encoded string
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

if (!is_array($filters)) {
    $filters = [];
}

// Build the base SQL query
$baseSql = "FROM 
    client_transaction ct 
JOIN 
    Console_Asociation ca 
    ON ct.uid = ca.uid  
JOIN 
    client_tasbax ctb 
    ON ctb.card_number = ct.card_number
WHERE 
    ct.uid IN (SELECT uid FROM console WHERE device_type != 999) 
    AND ct.dispensed_volume > 0
    AND ct.dispensed_volume > 0  AND (ca.client_id = $companyId or ca.reseller_id = $companyId or ca.dist_id = $companyId)";

// Apply filters if provided
$filterConditions = "";
if (!empty($filters)) {
    // Filter by card_number
    if (isset($filters['card_number']) && !empty($filters['card_number'])) {
        $card_number = $conn->real_escape_string($filters['card_number']);
        $filterConditions .= " AND ct.card_number = '$card_number'";
    }

    // Filter by registration
    if (isset($filters['registration']) && !empty($filters['registration'])) {
        $registration = $conn->real_escape_string($filters['registration']);
        $filterConditions .= " AND ct.registration = '$registration'";
    }

    // Filter by date range
    if (isset($filters['startDate']) && !empty($filters['startDate']) && isset($filters['endDate']) && !empty($filters['endDate'])) {
        $startDate = $conn->real_escape_string($filters['startDate']);
        $endDate = $conn->real_escape_string($filters['endDate']);
        $filterConditions .= " AND ct.transaction_date BETWEEN '$startDate' AND '$endDate'";
    }
}

// Complete SQL query for fetching all transactions (no pagination)
$dataSql = "SELECT 
    ct.card_number, 
    ROUND(SUM(ct.dispensed_volume), 2) AS volume, 
    COALESCE(ct.registration, '') AS registration,
    ctb.tax_value,
    ROUND(ROUND(SUM(ct.dispensed_volume), 2)/100 * ctb.tax_value, 2) AS total
" . $baseSql . $filterConditions . "
GROUP BY ctb.tax_value, ct.card_number, ct.registration
ORDER BY total DESC";

// Log SQL query for debugging (optional)
// error_log("Export SQL Query: $dataSql");

$dataResult = $conn->query($dataSql);

if (!$dataResult) {
    // Log MySQL error
    error_log("MySQL Error in export_to_pdf.php: " . $conn->error);
    // Display error for debugging (optional)
    header('Content-Type: application/json');
    echo json_encode(array("error" => $conn->error));
    exit;
}

// Initialize sums
$totalVolume = 0;
$totalSum = 0;

// Start building the HTML content
$html = '<h1 style="text-align: center;">Fuel Tax Report</h1>';
$html .= '<table border="1" cellpadding="4">';
$html .= '
    <thead>
        <tr style="background-color: #002e60; color: #ffffff;">
            <th>Card Number</th>
            <th>Registration</th>
            <th>Volume</th>
            <th>Tax Value</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
';

// Populate the table with data
if ($dataResult->num_rows > 0) {
    while ($row_data = $dataResult->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row_data['card_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row_data['registration']) . '</td>';
        $html .= '<td style="text-align: right;">' . number_format($row_data['volume'], 2) . '</td>';
        $html .= '<td style="text-align: right;">' . number_format($row_data['tax_value'], 2) . '</td>';
        $html .= '<td style="text-align: right;">' . number_format($row_data['total'], 2) . '</td>';
        $html .= '</tr>';

        // Accumulate sums
        $totalVolume += (float)$row_data['volume'];
        $totalSum += (float)$row_data['total'];
    }
}

// Add the sum row
$html .= '
    </tbody>
    <tfoot>
        <tr>
            <td><strong>Total</strong></td>
            <td></td>
            <td style="text-align: right;"><strong>' . number_format($totalVolume, 2) . '</strong></td>
            <td></td>
            <td style="text-align: right;"><strong>' . number_format($totalSum, 2) . '</strong></td>
        </tr>
    </tfoot>
</table>
';

// Write the HTML content to the PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Optionally, add page numbers or other footers
// For simplicity, we're skipping it here

// Clear output buffer before sending headers
ob_clean();

// Output the PDF as a download
$pdf->Output('Fuel-Tax_Report.pdf', 'D');
exit;
?>
