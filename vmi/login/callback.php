<?php
declare(strict_types=1);

/**
 * Composer autoloader (walk up a few parents to find /vendor/autoload.php)
 */
$dir = __DIR__;
$autoload = null;
for ($i = 0; $i < 4; $i++) {
    $candidate = $dir . '/vendor/autoload.php';
    if (is_file($candidate)) { $autoload = $candidate; break; }
    $dir = dirname($dir);
}
if (!$autoload) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Autoloader not found.']);
    exit;
}
require_once $autoload;

header('Content-Type: application/json');
session_start();

use Google\Client as GoogleClient;

// TODO: replace with your real client ID (keep it server-side)
$CLIENT_ID = '856620376784-6oso2q1m27hk5huc1l78b6379j43q4vb.apps.googleusercontent.com';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['credential'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$idToken = $_POST['credential'];

try {
    $client = new GoogleClient();
    $client->setClientId($CLIENT_ID);

    // Verify token (audience is checked from setClientId)
    $payload = $client->verifyIdToken($idToken);
    if (!$payload) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID token.']);
        exit;
    }

    $email    = $payload['email'] ?? null;
    $name     = $payload['name'] ?? null;
    $googleId = $payload['sub'] ?? null;

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Token missing email claim.']);
        exit;
    }

    // Use absolute path for your DB include to avoid relative-path surprises
    require_once dirname(__DIR__) . '/db/dbh2.php'; // adjust if your dbh2.php lives elsewhere

    $stmt = $conn->prepare(
        "SELECT user_id, username, access_level, client_id, active
         FROM login
         WHERE username = ?"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($userId, $username, $accessLevel, $companyId, $active);

    if ($stmt->fetch() && (int)$active === 1) {
        $_SESSION['loggedin']    = true;
        $_SESSION['username']    = $username;
        $_SESSION['accessLevel'] = (int)$accessLevel;
        $_SESSION['companyId']   = (int)$companyId;
        $_SESSION['userId']      = (int)$userId;

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or inactive.']);
    }
    $stmt->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error verifying token: ' . $e->getMessage()]);
}
