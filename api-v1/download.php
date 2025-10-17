<?php
/**
 * Simple, safe file‑download endpoint.
 *
 * URL pattern:
 *   https://your‑site.com/api/download.php?f=<relative‑file‑name>
 *
 * Example:
 *   https://your‑site.com/api/download.php?f=firmware_1.0.03.bin
 *
 * Files must live under /home/ehon/files
 */

declare(strict_types=1);

// ─── CONFIG ─────────────────────────────────────────────────────────────
$BASE_DIR = '/home/ehon/files';    // absolute path to your private store
// ────────────────────────────────────────────────────────────────────────

// 1. Get file name from query‑string
if (empty($_GET['f'])) {
    http_response_code(400);
    exit('Missing “f” parameter');
}
$rel = $_GET['f'];

// 2. Basic sanitisation
$rel = str_replace("\0", '', $rel);          // strip null bytes
$rel = ltrim($rel, "/\\");                   // remove leading slashes

// 3. Resolve to real path and ensure it stays inside $BASE_DIR
$path = realpath("$BASE_DIR/$rel");
if ($path === false ||
    strpos($path, $BASE_DIR) !== 0 ||
    !is_file($path)) {
    http_response_code(404);
    exit('File not found');
}

// 4. Send headers and stream the file
$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

// Stream in one go; fine for binaries up to a few hundred MB
readfile($path);
exit;
