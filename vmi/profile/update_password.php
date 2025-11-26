<?php
/**
 * Update password endpoint
 * Handles AJAX requests to update user's password
 */

declare(strict_types=1);

session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get user ID and username from session
$userId = (int)($_SESSION['userId'] ?? 0);
$username = $_SESSION['username'] ?? '';

if ($userId === 0 || empty($username)) {
    echo json_encode(['success' => false, 'message' => 'User information not found']);
    exit;
}

// Get and validate input
$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

if (empty($currentPassword)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

if (empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
    exit;
}

// Include database connection
require_once __DIR__ . '/../db/pdo_boot.php';

try {
    // Verify current password
    $stmt = $pdo->prepare('SELECT password FROM login WHERE user_id = :uid AND username = :username');
    $stmt->execute(['uid' => $userId, 'username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $updateStmt = $pdo->prepare('UPDATE login SET password = :password WHERE user_id = :uid');
    $updateStmt->execute([
        'password' => $hashedPassword,
        'uid' => $userId
    ]);

    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
} catch (PDOException $e) {
    error_log('Error updating password: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

