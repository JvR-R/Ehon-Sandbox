<?php
include('../db/dbh2.php');
include('../db/log.php');

// For development only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) Get raw JSON from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 2) Extract + sanitize data from the JSON
$off_rawEmails = $data['off_userInput'] ?? '';
$off_groupList = $data['off_groupList'] ?? '';
$schedule_type = strtolower(trim($data['schedule_type'] ?? 'weekly'));
$off_weekday   = strtolower(trim($data['off_weekday'] ?? ''));
$off_month_day = isset($data['off_month_day']) ? (int)$data['off_month_day'] : null;

// Normalize group value (0 means all/none)
if ($off_groupList === 'def' || $off_groupList === '') {
    $off_groupList = 0;
} else {
    $off_groupList = (int) $off_groupList;
}

// 3) Validate + clean email list
$emailArray = preg_split('/[\s,]+/', $off_rawEmails, -1, PREG_SPLIT_NO_EMPTY);
$validEmails = [];
foreach ($emailArray as $possibleEmail) {
    $possibleEmail = trim($possibleEmail);
    if (filter_var($possibleEmail, FILTER_VALIDATE_EMAIL)) {
        $validEmails[] = $possibleEmail;
    }
}
$off_emails = implode(',', $validEmails);

// 4) Validate schedule type and respective field
$allowedWeekdays = ['mon','tue','wed','thu','fri','sat','sun'];
if ($schedule_type !== 'weekly' && $schedule_type !== 'monthly') {
    $schedule_type = 'weekly';
}
if ($schedule_type === 'weekly') {
    if ($off_weekday === '' || !in_array($off_weekday, $allowedWeekdays, true)) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid or missing weekday for weekly schedule'
        ]);
        exit;
    }
    $off_month_day = null; // clear
} else { // monthly
    if (!is_int($off_month_day) || $off_month_day < 1 || $off_month_day > 31) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid or missing month day for monthly schedule'
        ]);
        exit;
    }
    $off_weekday = '';
}

// NOTE: Database persistence will be implemented next.
// This endpoint currently only validates and echoes back the received data.

// 5) If companyId is missing, you may want to fetch from request/session.
// Relying on included files to set $companyId similar to other endpoints.

// 6) If no valid emails, delete any existing schedule for this client and user (or NULL)
if (empty($off_emails)) {
    if (isset($userId) && $userId) {
        $delete = $conn->prepare("DELETE FROM transaction_reportcron WHERE client_id = ? AND user_id = ?");
        $delete->bind_param("ii", $companyId, $userId);
    } else {
        $delete = $conn->prepare("DELETE FROM transaction_reportcron WHERE client_id = ? AND user_id IS NULL");
        $delete->bind_param("i", $companyId);
    }
    if ($delete->execute()) {
        $response = [
            'status'  => 'success',
            'message' => 'Transaction report entry deleted due to empty email list.'
        ];
    } else {
        $response = [
            'status'  => 'error',
            'message' => 'Failed to delete: ' . $delete->error
        ];
    }
    $delete->close();
} else {
    // 7) Upsert new settings
    // Ensure columns exist for schedule_type and month_day if not already present
    $conn->query("ALTER TABLE transaction_reportcron ADD COLUMN IF NOT EXISTS schedule_type ENUM('weekly','monthly') NULL AFTER weekday");
    $conn->query("ALTER TABLE transaction_reportcron ADD COLUMN IF NOT EXISTS month_day TINYINT NULL AFTER schedule_type");

    $stmt = $conn->prepare(
        "INSERT INTO transaction_reportcron (client_id, user_id, group_id, email_list, weekday, schedule_type, month_day)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            group_id      = VALUES(group_id),
            email_list    = VALUES(email_list),
            weekday       = VALUES(weekday),
            schedule_type = VALUES(schedule_type),
            month_day     = VALUES(month_day)"
    );
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'Prepare failed: ' . $conn->error
        ];
    } else {
        $uidParam = isset($userId) && $userId ? (int)$userId : null;
        $weekdayParam = $off_weekday !== '' ? $off_weekday : null;
        $monthDayParam = ($off_month_day !== null) ? $off_month_day : null;
        $scheduleParam = $schedule_type;
        $stmt->bind_param("iiisssi", $companyId, $uidParam, $off_groupList, $off_emails, $weekdayParam, $scheduleParam, $monthDayParam);
        if ($stmt->execute()) {
            $response = [
                'status'       => 'success',
                'message'      => 'Transaction report updated successfully',
                'receivedData' => [
                    'email_list' => $off_emails,
                    'group_id'   => $off_groupList,
                    'weekday'    => $weekdayParam,
                    'schedule_type' => $scheduleParam,
                    'month_day'  => $monthDayParam
                ]
            ];
        } else {
            $response = [
                'status'  => 'error',
                'message' => 'Database update failed: ' . $stmt->error
            ];
        }
        $stmt->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>

