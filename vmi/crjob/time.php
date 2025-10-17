<?php

include('/home/ehonener/public_html/vmi/db/dbh2.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);



function setDynamicTimezone($country, $city) {
    $timezone = $country . '/' . $city;
    
    // Check if the timezone is valid
    if (in_array($timezone, timezone_identifiers_list())) {
        date_default_timezone_set($timezone);
        return true; // Timezone successfully set
    } else {
        // Fallback or error handling
        return false; // Indicate failure to set timezone
    }
}

$countryt = 'Australia';
$cityt = 'Perth'; // Example city

if (setDynamicTimezone($countryt, $cityt)) {
    // Proceed with datetime operations in the dynamically set timezone
    $dateTime = new DateTime();
    echo $dateTime->format('Y-m-d H:i:s') . " (Timezone: $countryt/$cityt)\n";
} else {
    echo "Invalid timezone: $countryt/$cityt\n";
}

$originalDateTime = '2024-02-17T22:22:00Z';

// Create a DateTime object from the original datetime string
$dateTime = new DateTime($originalDateTime, new DateTimeZone('UTC'));

// Specify the new timezone you want to convert to, e.g., for Perth, Australia
$newTimezone = ''. $countryt . '/' . $cityt . '';

// Set the new timezone on the DateTime object
$dateTime->setTimezone(new DateTimeZone($newTimezone));

// Format the DateTime object to your desired output
$adjustedDateTime = $dateTime->format('Y-m-d H:i:s');

// Output the adjusted datetime
echo "<br>Adjusted DateTime in $newTimezone timezone: $adjustedDateTime\n";

?>