<?php
include('../db/dbh2.php');
include('../db/log.php');

$stmt = null;
if ($companyId == 15100) {
    $stmt = $conn->prepare("SELECT * FROM login WHERE access_level != ?");
    $stmt->bind_param("i", $adminLevel);
} else {
    $stmt = $conn->prepare("SELECT * FROM login WHERE client_id = ? AND access_level != ?");
    $stmt->bind_param("ii", $companyId, $adminLevel);
}
$adminLevel = 999;
$stmt->execute();
$result = $stmt->get_result();

$roles = [
    '1' => 'Petro',
    '2' => 'Petro',
    '3' => 'Petro',
    '4' => 'Admin',
    '6' => 'Admin',
    '8' => 'Admin',
    '5' => 'User',
    '7' => 'User',
    '9' => 'User'
];

$users = $result->fetch_all(MYSQLI_ASSOC);
?>
