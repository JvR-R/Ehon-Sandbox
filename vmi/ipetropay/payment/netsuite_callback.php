<?php
	include('../../db/dbh.php');
// 1) Check if we got a code
if (!isset($_GET['code'])) {
    // If there's an error parameter, you can also check $_GET['error'] here.
    echo "Error: No 'code' parameter found in callback.";
    exit;
}

// 2) Retrieve the authorization code
$authorizationCode = $_GET['code'];

// Optionally check the `state` param to prevent CSRF, if you sent a custom state
$state = $_GET['state'] ?? null;
// if ($state !== 'expected_value') { ... }

// 3) Prepare to exchange this code for tokens
//    Replace the client_id/client_secret/redirect_uri with your actual values
$clientId     = 'cf5ba1fc20403f778a9d82f12954c10068865857a1df4731285886ffdb3d5c99';
$clientSecret = 'acb6cb3863d3e865dfa7b8b589a14cba2d0f16041ae9481a125c4154331d70a0';
$redirectUri  = 'http://localhost/vmi/ipetropay/payment/netsuite_callback'; // e.g., 'https://myapp.com/netsuite_callback'

// NetSuite token endpoint for sandbox vs production:
$tokenEndpoint = 'https://9250724-sb1.suitetalk.api.netsuite.com/services/rest/auth/oauth2/v1/token';
// Or if you're in production, maybe: 'https://system.netsuite.com/services/rest/auth/oauth2/v1/token'

// 4) Build the POST fields
$postData = [
    'grant_type'    => 'authorization_code',
    'code'          => $authorizationCode,
    'redirect_uri'  => $redirectUri,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret
];

// 5) Execute cURL request to exchange the code for tokens
$ch = curl_init($tokenEndpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response     = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Error exchanging code for tokens.\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    echo "cURL Error: $curlError\n";
    exit;
}

// 6) Parse the JSON response to get access_token, refresh_token
$data = json_decode($response, true);
if (!$data || empty($data['access_token'])) {
    echo "Failed to parse access token from response.\n";
    echo "Response: $response\n";
    exit;
}

// 7) Extract tokens
$accessToken  = $data['access_token'];
$refreshToken = $data['refresh_token'] ?? null;  // some cases NetSuite doesn't return this? Usually it does

// 8) Store tokens in DB or config. For now, just display them (for dev/demo).
//    In production, DO NOT simply echo them—store them securely.
echo "<h2>Success!</h2>";
echo "<p>Access Token: <strong>{$accessToken}</strong></p>";
echo "<p>Refresh Token: <strong>{$refreshToken}</strong></p>";

 // 9) Update refresh token in DB
if ($refreshToken) {
    // Example to store the current timestamp in refresh_act_time
    $datetime = date("Y-m-d H:i:s");

    $sql = "UPDATE active_token 
            SET token_refresh = ?, refresh_act_time = ?
            WHERE idactive_token = 1";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $refreshToken, $datetime);

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        // redirect with a success flag + custom message
        header('Location: ../bank‑pay?status=success&message=' . urlencode('Refresh token saved successfully'));
        exit;
    } else {
        // redirect with an error flag + custom message
        header('Location: ../bank‑pay?status=error&message=' . urlencode('No record was updated'));
        exit;
    }

    $stmt->close();
} else {
    echo "<p>No refresh token returned from NetSuite.</p>";
}
?>
