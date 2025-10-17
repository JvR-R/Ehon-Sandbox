<?php
/* ------------------------------------------------------------
 *  Secure file‑upload endpoint  •  2025‑07‑28
 *  Place in:  /home/ehon/public_html/api-v1/upload.php
 * ------------------------------------------------------------ */

declare(strict_types=1);
ini_set('display_errors', 1);          // keep fatals out of the response
header('Content-Type: application/json');

$TARGET_DIR = '/home/ehon/files/';               // outside web‑root
$MAX_SIZE   = 10 * 1024 * 1024;                  // 10 MB
$TOKEN      = 'b477440d36fdc7ee139863108a241c44e0a8b7ae';

/* ---------- 0.  Robust auth ------------------------------------------------ */

# ── grab the header no matter how PHP is invoked ───────────────────────────
$headers    = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization']
           ?? $headers['authorization']
           ?? ($_SERVER['HTTP_AUTHORIZATION']
              ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
              ?? '');

# strip “Bearer ” prefix, collapse whitespace
$tokenIn = preg_replace('/^\s*Bearer\s+/i', '', trim($authHeader));

if ($TOKEN !== '' && !hash_equals($TOKEN, $tokenIn)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorised']);
    exit;
}

# uncomment during troubleshooting – writes the headers PHP sees
# file_put_contents('/home/ehon/auth_debug.log', print_r($headers, true));

/* ---------- 1.  Must be HTTPS -------------------------------------------- */
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'HTTPS required']);
    exit;
}

/* ---------- 2.  Must be POST + multipart ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'POST multipart/form-data with field \"file\" required']);
    exit;
}

$f = $_FILES['file'];

/* ---------- 3.  Basic sanity checks --------------------------------------- */
if ($f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'upload failed (' . $f['error'] . ')']);
    exit;
}
if ($f['size'] > $MAX_SIZE) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'file too large']);
    exit;
}

/* ---------- 4.  Whitelist filename ---------------------------------------- */
$origName = basename($f['name']);
if (!preg_match('/^[A-Za-z0-9._-]+$/', $origName)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid filename']);
    exit;
}

/* ---------- 5.  Move file into place -------------------------------------- */
$dest = $TARGET_DIR . $origName;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'failed to move file']);
    exit;
}
chmod($dest, 0644);

/* ---------- 6.  Done ------------------------------------------------------- */
echo json_encode(['ok' => true, 'stored_as' => $dest, 'bytes' => $f['size']]);
