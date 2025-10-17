<?php
date_default_timezone_set('UTC');
function Timezone($uid) {
    include('../db/dbh2.php');
    if (!$conn) {
        error_log('Database connection not available');
        return null;
    }

    date_default_timezone_set('UTC');
    $stmt = $conn->prepare("SELECT tz.utc_offset FROM Sites AS st JOIN timezones AS tz ON st.time_zone = tz.id WHERE uid = ?");
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        return null;
    }
    
    $stmt->bind_param("s", $uid);
    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        return null;
    }
    
    $stmt->bind_result($timezoneOffset);
    if (!$stmt->fetch()) {
        error_log('No timezone found for UID: ' . $uid);
        $stmt->close();
        $conn->close();
        return null;
    }
    
    $stmt->close();
    $conn->close();
    
    // Create a DateTime object for the current time or any time you need
    $datetime = new DateTime("now", new DateTimeZone('UTC'));
    $datetime2 = new DateTime("now", new DateTimeZone('UTC'));

    // Adjust the time by the timezone offset from the database
    $intervalSpec = 'PT' . abs(str_replace(':', 'H', $timezoneOffset)) . 'H';
    if (strpos($timezoneOffset, '-') === 0) {
        $datetime2->sub(new DateInterval($intervalSpec));
    } else {
        $datetime2->add(new DateInterval($intervalSpec));
    }

    // Prepare the return value as an associative array
    $result = [
        'date0' => $datetime->format('Y-m-d'),
        'time0' => $datetime->format('H:i:s'),
        'date' => $datetime2->format('Y-m-d'),
        'time' => $datetime2->format('H:i:s')
    ];

    return $result;
}

// $adjustedDateTime = Timezone('398326');
// if ($adjustedDateTime) {
//     echo "Date0: " . $adjustedDateTime['date0'] . "<br>";
//     echo "Time0: " . $adjustedDateTime['time0'] . "<br>";
//     echo "Date: " . $adjustedDateTime['date'] . "<br>";
//     echo "Time: " . $adjustedDateTime['time'] . "<br>";
// } else {
//     echo "No adjusted datetime returned.";
// }

?>
