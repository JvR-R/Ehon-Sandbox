<?php
/**
 * Acknowledge Alarm Endpoint
 * Updates service_flag to 1 and saves ticket information
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/db/pdo_boot.php';
require_once dirname(__DIR__, 2) . '/db/log.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['uid']) || !isset($input['ticket_id']) || !isset($input['ticket_comment'])) {
        throw new Exception('Missing required parameters: uid, ticket_id, ticket_comment');
    }
    
    $uid = (int)$input['uid'];
    $ticket_id = trim($input['ticket_id']);
    $ticket_comment = trim($input['ticket_comment']);
    
    if (empty($ticket_id)) {
        throw new Exception('Ticket ID is required');
    }
    
    if (empty($ticket_comment)) {
        throw new Exception('Ticket comment is required');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update service_flag in console table
    $updateSql = "UPDATE console SET service_flag = 1 WHERE uid = ?";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([$uid]);
    
    if ($updateStmt->rowCount() === 0) {
        throw new Exception('Console not found or already acknowledged');
    }
    
    // Insert or update service_ticket (UNIQUE key on uid)
    $ticketSql = "INSERT INTO service_ticket (uid, ticket_id, ticket_comment) 
                  VALUES (?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  ticket_id = VALUES(ticket_id), 
                  ticket_comment = VALUES(ticket_comment)";
    $ticketStmt = $pdo->prepare($ticketSql);
    $ticketStmt->execute([$uid, $ticket_id, $ticket_comment]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Alarm acknowledged successfully',
        'uid' => $uid,
        'ticket_id' => $ticket_id
    ], JSON_THROW_ON_ERROR);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_THROW_ON_ERROR);
}
?>

