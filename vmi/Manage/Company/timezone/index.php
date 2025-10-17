<?php
/**
 * Fetch latest TZ/DST info from IPGeolocation and update local table.
 * Requires: PHP ≥7.0, ext‑curl, ext‑mysqli
 */

require_once '../../../db/dbh2.php';      //  ➜ $conn  (mysqli)

// 1.  Fetch all city names you care about
$stmt = $conn->prepare("SELECT example_city FROM timezones");
$stmt->execute();
$stmt->bind_result($city);
$stmt->store_result();   // ← add this


$apiKey = getenv('IPGEO_KEY') ?: '394b1cd88c5842058b88afc7e42c5b52';   // move to .env in prod

// 2. Prepare the UPSERT (INSERT … ON DUPLICATE KEY UPDATE)
$upsertSql = "
    INSERT INTO timezones
      (example_city, example_country, utc_offset,
       is_dst, dst_start_utc, dst_end_utc)
    VALUES
      (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      example_country     = VALUES(example_country),
      utc_offset          = VALUES(utc_offset),
      is_dst              = VALUES(is_dst),
      dst_start_utc       = VALUES(dst_start_utc),
      dst_end_utc         = VALUES(dst_end_utc)";
$up = $conn->prepare($upsertSql);

// 3. Loop through all cities
while ($stmt->fetch()) {
    $url = sprintf(
        "https://api.ipgeolocation.io/timezone?" .
        "apiKey=%s&location=%s",
        $apiKey,
        rawurlencode($city)              // append country code for safety
    );

    // -- call the API (5‑second timeout)
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $json = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200 || $json === false) {
        error_log("Time‑zone API failed for $city (HTTP $http)");
        continue;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['timezone'])) {
        error_log("Bad JSON for $city: $json");
        continue;
    }

    // -- extract & normalise
    // $tzId      = $data['timezone'];
    $country    = $data['geo']['country'];   // → seconds
    $withDst   = $data['timezone_offset_with_dst'];
    $isDst     = $data['is_dst'] ? 1 : 0;
    $dstSav    = intval($data['dst_savings']) * 3600;             // seconds
    $dstStart  = $data['dst_start']['utc_time'] ?? null;           // may be null in non‑DST zones
    $dstEnd    = $data['dst_end']['utc_time']   ?? null;
    $withDstTxt = ($withDst > 0 ? '+' : '') . $withDst . ':00';   
    // 4. UPSERT
    $up->bind_param(
        'sssiss',
        $city,
        // $tzId,
        $country,
        $withDstTxt,
        $isDst,
        $dstStart,
        $dstEnd
    );
    $up->execute();
    echo "$city,
        $country,
        $withDst,
        $isDst,
        $dstStart,
        $dstEnd<br>";

}

$up->close();
$stmt->close();
$conn->close();
echo "DST table refreshed.\n";
?>