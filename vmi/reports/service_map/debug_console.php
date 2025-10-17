<?php
/**
 * Debug Console Status API
 */

// Turn on error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // Test basic requirements
    if (!file_exists(dirname(__DIR__, 2) . '/db/pdo_boot.php')) {
        throw new Exception('pdo_boot.php not found at: ' . dirname(__DIR__, 2) . '/db/pdo_boot.php');
    }
    
    if (!file_exists(dirname(__DIR__, 2) . '/db/log.php')) {
        throw new Exception('log.php not found at: ' . dirname(__DIR__, 2) . '/db/log.php');
    }
    
    require_once dirname(__DIR__, 2) . '/db/pdo_boot.php';
    require_once dirname(__DIR__, 2) . '/db/log.php';
    
    if (!isset($pdo)) {
        throw new Exception('PDO connection not available');
    }
    
    if (!isset($_SESSION)) {
        throw new Exception('Session not started');
    }
    
    $cid = (int)($_SESSION['companyId'] ?? 0);
    $isGlobal = ($cid === 15100);
    
    // Test basic query
    $sql = "SELECT COUNT(*) as console_count FROM console LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'debug' => [
            'company_id' => $cid,
            'is_global' => $isGlobal,
            'console_count' => $result['console_count'] ?? 0,
            'session_keys' => array_keys($_SESSION),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
