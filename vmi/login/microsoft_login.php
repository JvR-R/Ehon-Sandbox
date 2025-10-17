<?php
session_start();

// Microsoft OAuth 2.0 credentials
$client_id = 'a50b5441-5cfc-42fb-a238-c78a1f735fa3';
$redirect_uri = 'https://ehon.com.au/vmi/login/microsoft_callback.php';
$scope = 'openid profile email User.Read';  // Include 'User.Read' to access basic profile information

$authorization_url = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query([
    'client_id' => $client_id,
    'response_type' => 'code',
    'redirect_uri' => $redirect_uri,
    'response_mode' => 'query',
    'scope' => $scope,
]);

header('Location: ' . $authorization_url);
exit();

?>
