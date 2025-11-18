<?php
// Start output buffering and clear any previous output
ob_start();

// Include necessary files
include '../db/dbh2.php';
include '../db/log.php';
set_time_limit(300);

require '/home/ehonener/vendor/tecnickcom/tcpdf/tcpdf.php';
use TCPDF;

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Ehonenergy Tech');
$pdf->SetTitle('Fuel Quality Report');
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 10);

// Get filters from the request
$filters = isset($_GET['filters']) ? $_GET['filters'] : array();

// Construct the SQL query with filters
$sql = "SELECT fq.uid, fq.fq_date, fq.fq_time, st.site_name, fq.tank_id, fq.particle_4um, fq.particle_6um, fq.particle_14um, fq.fq_bubbles, fq.fq_cutting, fq.fq_sliding, fq.fq_fatigue, fq.fq_fibre, fq.fq_air, fq.fq_unknown, fq.fq_temp FROM fuel_quality fq JOIN Sites st on st.uid=fq.uid";
$conditions = array();

if (!empty($filters)) {
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
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY fq.fq_date DESC, fq.fq_time DESC";

// Define the header
$header = [
    'UID' => 'uid',
    'Date' => 'fq_date',
    'Time' => 'fq_time',
    'Site Name' => 'site_name',
    'Tank ID' => 'tank_id',
    '4um Particles' => 'particle_4um',
    '6um Particles' => 'particle_6um',
    '14um Particles' => 'particle_14um',
    'Bubbles' => 'fq_bubbles',
    'Cutting' => 'fq_cutting',
    'Sliding' => 'fq_sliding',
    'Fatigue' => 'fq_fatigue',
    'Fibre' => 'fq_fibre',
    'Air' => 'fq_air',
    'Unknown' => 'fq_unknown',
    'Temp' => 'fq_temp',
    'Concatenated Particles' => 'concatenated_particles'
];

$batchSize = 1000;
$offset = 0;

do {
    $batchQuery = $sql . " LIMIT $offset, $batchSize;";
    $result = $conn->query($batchQuery);

    if ($result->num_rows > 0) {
        $data = '';
        while ($row = $result->fetch_assoc()) {
            $concatenatedParticles = $row['particle_4um'] . '/' . $row['particle_6um'] . '/' . $row['particle_14um'];
            $data .= '<tr>';
            foreach ($header as $key => $column) {
                $value = ($column == 'concatenated_particles') ? $concatenatedParticles : $row[$column];
                $data .= '<td style="border: 1px solid #4d5256; padding: 5px;">' . $value . '</td>';
            }
            $data .= '</tr>';
        }

        $html = '<table style="border-collapse: collapse; width: 100%;">';
        $html .= '<thead><tr style="background-color: #002e60; color: #ffffff;">';
        foreach ($header as $key => $value) {
            $html .= '<th style="border: 1px solid #4d5256; padding: 5px;">' . $key . '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>' . $data . '</tbody></table>';

        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $offset += $batchSize;
} while ($result->num_rows > 0);

// Clear output buffer before sending headers
ob_clean();

// Set headers to force download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="transactions.pdf"');
header('Cache-Control: max-age=0');

// Output the PDF document
$pdf->Output('transactions.pdf', 'D');
exit;
?>
