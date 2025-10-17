<?php
// Check if the request is a POST request
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input and decode it
    $input = json_decode(file_get_contents('php://input'), true);
    // $countryName = $input['country'];
    $countryName = "australia";

    $curl = curl_init();
    $data = json_encode(array("country" => $countryName));

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://countriesnow.space/api/v0.1/countries/cities",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, // Follow redirects
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 100,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "cache-control: no-cache"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        echo $response; // Echo the response back to the JavaScript fetch call
    }
// } else {
//     echo json_encode(["error" => true, "msg" => "Invalid request"]);
// }
?>
