<?php
include('../db/dbh2.php');
include('../db/log.php');

// For development only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) Read the raw JSON from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 2) Extract and sanitize data
$rawEmails  = $data['userInput']  ?? '';
$groupList  = $data['groupList']  ?? '';
$startHour  = (int)($data['startHour'] ?? 0);
$finishHour = (int)($data['finishHour'] ?? 0);
$interval   = (int)($data['interval']  ?? 1);

// (Assuming $companyId is set from session or included file)
if (!isset($companyId) || empty($companyId)) {
    // Or retrieve from $data if needed:
    // $companyId = (int)($data['companyId'] ?? 0);
}

// Convert groupList to integer, or treat 'def' as 0 if you like
if ($groupList === 'def' || $groupList === '') {
    $groupList = 0;
} else {
    $groupList = (int) $groupList;
}

// Basic range checks (optional)
if ($startHour < 0 || $startHour > 23)   $startHour  = 0;
if ($finishHour < 0 || $finishHour > 23) $finishHour = 0;
if ($interval < 1) $interval = 1;

// 3) Validate email input
$emailArray   = preg_split('/[\s,]+/', $rawEmails, -1, PREG_SPLIT_NO_EMPTY);
$validEmails  = [];

foreach ($emailArray as $possibleEmail) {
    $possibleEmail = trim($possibleEmail);
    if (filter_var($possibleEmail, FILTER_VALIDATE_EMAIL)) {
        $validEmails[] = $possibleEmail;
    }
}

// Build final email list string
$emails = implode(',', $validEmails);

// 4) Calculate the cron hours
$cron = [];
$currentHour = $startHour;

while (true) {
    $cron[] = $currentHour;
    $currentHour = ($currentHour + $interval) % 24;
    if ($currentHour === $finishHour) {
        $cron[] = $currentHour;
        break;
    }
    // If we looped back to the startHour, break
    if ($currentHour === $startHour) {
        break;
    }
}

// If interval == 24, user wants once a day at $startHour
// (and possibly ignoring finishHour)
if ($interval === 24) {
    // you can decide how to handle startHour=0 differently if you wish
    $cronstring = (string) $startHour;
} else {
    $cronstring = implode(', ', $cron);
}

// 5) Delete row if no valid emails:
if (empty($emails)) {
    $delete = $conn->prepare("DELETE FROM report_cron WHERE client_id = ?");
    $delete->bind_param("i", $companyId);
    $delete->execute();
    $delete->close();

    $response = [
        'status'  => 'success',
        'message' => 'Report deleted successfully due to empty email list.'
    ];
} else {
    // 6) Insert or Update row
    $insert = $conn->prepare("
        INSERT INTO report_cron
            (client_id, group_id, email_list, cron, start_time, finish_time, report_interval)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            group_id        = VALUES(group_id),
            email_list      = VALUES(email_list),
            cron            = VALUES(cron),
            start_time      = VALUES(start_time),
            finish_time     = VALUES(finish_time),
            report_interval = VALUES(report_interval)
    ");

    if (!$insert) {
        $response = [
            'status'  => 'error',
            'message' => 'Prepare failed: ' . $conn->error
        ];
    } else {
        $insert->bind_param(
            "iissssi",
            $companyId,
            $groupList,
            $emails,
            $cronstring,
            $startHour,
            $finishHour,
            $interval
        );
        if ($insert->execute()) {
            $response = [
                'status'  => 'success',
                'message' => 'Tank volume report updated successfully.',
                'receivedData' => [
                    'EmailList'   => $emails,
                    'groupList'   => $groupList,
                    'startHour'   => $startHour,
                    'finishHour'  => $finishHour,
                    'interval'    => $interval,
                    'cron'        => $cron,
                    'cronstring'  => $cronstring,
                ],
            ];
        } else {
            $response = [
                'status'  => 'error',
                'message' => 'Database update failed: ' . $insert->error
            ];
        }
        $insert->close();
    }
}

// 7) Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
