<?php
/**
 * Test includes and database connection
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $response = ['success' => true, 'steps' => []];
    
    // Step 1: Check if files exist
    $pdo_boot_path = dirname(__DIR__, 2) . '/db/pdo_boot.php';
    $log_path = dirname(__DIR__, 2) . '/db/log.php';
    
    $response['steps'][] = [
        'step' => 'file_check',
        'pdo_boot_exists' => file_exists($pdo_boot_path),
        'log_exists' => file_exists($log_path),
        'pdo_boot_path' => $pdo_boot_path,
        'log_path' => $log_path
    ];
    
    // Step 2: Try to include pdo_boot
    if (file_exists($pdo_boot_path)) {
        require_once $pdo_boot_path;
        $response['steps'][] = ['step' => 'pdo_boot_included', 'success' => true];
        
        // Check if PDO is available
        if (isset($pdo)) {
            $response['steps'][] = ['step' => 'pdo_available', 'success' => true];
        } else {
            $response['steps'][] = ['step' => 'pdo_available', 'success' => false];
        }
    } else {
        $response['steps'][] = ['step' => 'pdo_boot_included', 'success' => false, 'error' => 'File not found'];
    }
    
    // Step 3: Try to include log
    if (file_exists($log_path)) {
        require_once $log_path;
        $response['steps'][] = ['step' => 'log_included', 'success' => true];
        
        // Check if session is available
        if (isset($_SESSION)) {
            $response['steps'][] = [
                'step' => 'session_available', 
                'success' => true,
                'session_keys' => array_keys($_SESSION),
                'companyId' => $_SESSION['companyId'] ?? 'not set'
            ];
        } else {
            $response['steps'][] = ['step' => 'session_available', 'success' => false];
        }
    } else {
        $response['steps'][] = ['step' => 'log_included', 'success' => false, 'error' => 'File not found'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'fatal_error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
