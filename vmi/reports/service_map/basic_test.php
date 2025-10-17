<?php
/**
 * Most basic test - check if PHP and includes work
 */

header('Content-Type: application/json; charset=utf-8');

try {
    // Test 1: Basic PHP functionality
    $test1 = "PHP working";
    
    // Test 2: Check if files exist
    $pdo_boot_exists = file_exists(dirname(__DIR__, 2) . '/db/pdo_boot.php');
    $log_exists = file_exists(dirname(__DIR__, 2) . '/db/log.php');
    
    echo json_encode([
        'success' => true,
        'tests' => [
            'php_working' => $test1,
            'pdo_boot_exists' => $pdo_boot_exists,
            'log_exists' => $log_exists,
            'parent_dir' => dirname(__DIR__, 2),
            'pdo_boot_path' => dirname(__DIR__, 2) . '/db/pdo_boot.php',
            'log_path' => dirname(__DIR__, 2) . '/db/log.php'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
