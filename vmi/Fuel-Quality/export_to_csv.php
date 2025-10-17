<?php
// Start output buffering and clear any previous output
ob_start();

// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');
// Include files using defined paths
include(db);
include(LOG_PATH);
include(BORDER_PATH);
ini_set('memory_limit', '256M');

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT fq.uid, fq.fq_date, fq.fq_time, st.site_name, fq.tank_id, fq.particle_4um, fq.particle_6um, fq.particle_14um FROM fuel_quality fq JOIN Sites st on st.uid=fq.uid";

// Apply filters
if (!empty($filters)) {
    $conditions = array();
    if (isset($filters['sites']) && !empty($filters['sites'])) {
        $sites = implode(',', array_map('intval', $filters['sites']));
        $conditions[] = "st.Site_id IN ($sites)";
    }
    if (isset($filters['group']) && !empty($filters['group'])) {
        $conditions[] = "st.Site_id IN (SELECT site_no FROM ehonener_ehon_vmi.client_site_groups where group_id =" . $filters['group'] . ")";
    }
    if (isset($filters['cardholder']) && !empty($filters['cardholder'])) {
        $conditions[] = "fq.card_holder_name LIKE '%{$filters['cardholder']}%'";
    }
    if (isset($filters['cardnumber']) && !empty($filters['cardnumber'])) {
        $conditions[] = "fq.card_number = {$filters['cardnumber']}";
    }
    if (isset($filters['registration']) && !empty($filters['registration'])) {
        $conditions[] = "fq.registration LIKE '%{$filters['registration']}%'";
    }
    if (isset($filters['startDate']) && !empty($filters['startDate'])) {
        $conditions[] = "fq.fq_date >= '{$filters['startDate']}'";
    }
    if (isset($filters['endDate']) && !empty($filters['endDate'])) {
        $conditions[] = "fq.fq_date <= '{$filters['endDate']}'";
    }
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
}

// Finalize the SQL query with ordering
$sql .= " ORDER BY fq.fq_date DESC, fq.fq_time DESC";

$result = $conn->query($sql);

// Define the CSV header
$csvHeader = [
    'UID',
    'Date',
    'Time',
    'Site Name',
    'Tank ID',
    '4um Particles',
    '6um Particles',
    '14um Particles',
    'Concatenated Particles'
];

// Create a temporary file for the CSV
$temp_file = tempnam(sys_get_temp_dir(), 'csv_');
$csvFile = fopen($temp_file, 'w');

// Write the header to the CSV file
fputcsv($csvFile, $csvHeader);

// Write the data to the CSV file
if ($result->num_rows > 0) {
    while ($row_data = $result->fetch_assoc()) {
        $concatenatedParticles = $row_data['particle_4um'] . '/' . $row_data['particle_6um'] . '/' . $row_data['particle_14um'];
        $csvRow = [
            $row_data['uid'],
            $row_data['fq_date'],
            $row_data['fq_time'],
            $row_data['site_name'],
            $row_data['tank_id'],
            $row_data['particle_4um'],
            $row_data['particle_6um'],
            $row_data['particle_14um'],
            $concatenatedParticles
        ];
        fputcsv($csvFile, $csvRow);
    }
}

// Close the CSV file
fclose($csvFile);

// Clear output buffer before sending headers
ob_clean();

// Output the CSV file for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="transactions.csv"');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);

// Delete the temporary file
unlink($temp_file);

// End output buffering and flush output
ob_end_flush();
exit;
?>
