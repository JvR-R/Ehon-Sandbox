<?php
header('Content-Type: application/json');
include('../db/dbh2.php');
include('../db/check.php');

// Check if device_id parameter is provided
if (!isset($_GET['device_id']) || empty($_GET['device_id'])) {
    echo json_encode(['error' => 'Device ID is required']);
    exit;
}

$device_id = $_GET['device_id'];
$device_type = isset($_GET['device_type']) ? $_GET['device_type'] : '';

// Determine the correct log directory based on device type
$base_log_dir = '/home/ehon/public_html/backend/ingest/management/logs';
if ($device_type === 'fms') {
    $log_file = $base_log_dir . '/fms/' . $device_id . '.log';
} elseif ($device_type === 'gateway') {
    $log_file = $base_log_dir . '/gateway/' . $device_id . '.log';
} else {
    // Fallback: try both locations
    $fms_log = $base_log_dir . '/fms/' . $device_id . '.log';
    $gateway_log = $base_log_dir . '/gateway/' . $device_id . '.log';
    
    if (file_exists($fms_log)) {
        $log_file = $fms_log;
    } elseif (file_exists($gateway_log)) {
        $log_file = $gateway_log;
    } else {
        echo json_encode(['error' => 'Log file not found for device: ' . $device_id, 'debug' => 'Checked both fms and gateway directories']);
        exit;
    }
}

// Check if log file exists
if (!file_exists($log_file)) {
    echo json_encode(['error' => 'Log file not found for device: ' . $device_id, 'path' => $log_file]);
    exit;
}

try {
    // Read the last 25 lines from the log file
    $lines = [];
    $file = new SplFileObject($log_file);
    $file->seek(PHP_INT_MAX);
    $total_lines = $file->key();
    
    // Start from the line that gives us the last 25 lines
    $start_line = max(0, $total_lines - 25);
    $file->seek($start_line);
    
    while (!$file->eof()) {
        $line = trim($file->current());
        if (!empty($line)) {
            $lines[] = $line;
        }
        $file->next();
    }
    
    // If we have fewer than 25 lines, just return what we have
    $last_25_lines = array_slice($lines, -25);
    
    echo json_encode([
        'success' => true,
        'device_id' => $device_id,
        'logs' => $last_25_lines,
        'total_lines' => count($last_25_lines)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error reading log file: ' . $e->getMessage()]);
}
?>
