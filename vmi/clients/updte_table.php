<?php
include('../db/dbh2.php');
include('../db/log.php');

header('Content-Type: application/json; charset=UTF-8');

/* ── only accept POST requests ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. POST required.']);
    exit;
}

/* ── accept both JSON and regular form posts ────────────────────── */
$payload = [];
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $payload = $_POST;
}

$group_id = $payload['group_id'] ?? null;
if (!$group_id) {
    http_response_code(400);
    echo json_encode(['error' => 'group_id missing']);
    exit;
}

/* ── DB query exactly as before ─────────────────────────────────── */
$sql  = 'SELECT site_no AS site_id, site_name FROM client_site_groups WHERE group_id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $group_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => $stmt->error]);
    exit;
}

$stmt->bind_result($site_id, $site_name);
$sites = [];
while ($stmt->fetch()) { $sites[] = compact('site_id', 'site_name'); }
$stmt->close();

echo json_encode(['response' => $sites]);
exit;
?>
