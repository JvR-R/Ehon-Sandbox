<?php
// Always run the PDO bootstrap first.
require_once dirname(__DIR__).'/db/pdo_boot.php';

// Convenience: every report needs the current company & date range
$companyId = (int)($_SESSION['companyId'] ?? 0);
$now       = new DateTime('now', new DateTimeZone('Australia/Brisbane'));
$ranges = [
    'd1'  => $now->modify('-1 day')->format('Y-m-d'),
    'm1'  => $now->modify('-1 month')->format('Y-m-d'),
    'm2'  => $now->modify('-2 month')->format('Y-m-d'),
    'm12' => $now->modify('-12 month')->format('Y-m-d'),
];

// helper to decide if the current user is super-admin (15100)
function isGlobalAccount(int $companyId): bool { return $companyId === 15100; }
