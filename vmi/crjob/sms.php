<?php
    include('../db/dbh2.php');
    
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email_config = [
        'sender_email' => 'vmi@ehon.com.au',
        'sender_password' => 'VMIEHON2023',
        'smtp_server' => 'smtp.gmail.com',
        'smtp_port' => 587
    ];

    // Function to send an email
function send_email($receiver_email, $email_subject, $email_content)
{
    global $email_config;

    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = $email_config['smtp_server'];
        $mail->Port = $email_config['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['sender_email'];
        $mail->Password = $email_config['sender_password'];
        $mail->SMTPSecure = 'tls';

        // Sender and recipient
        $mail->setFrom($email_config['sender_email']);
        $mail->addAddress($receiver_email);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $email_subject;
        $mail->Body = $email_content;

        echo "Sender email: " . $email_config['sender_email'] . "\n";
        echo "Recipient email: $receiver_email\n";

        $mail->send();
        echo "Email sent successfully.\n";
    } catch (Exception $e) {
        echo "Failed to send email. Error: {$mail->ErrorInfo}\n";
    }
}
function sms_token(){
    // API endpoint URL
    $url = 'https://products.api.telstra.com/v2/oauth/token';

    // Authorization token
    $CONSUMER_KEY = 'tJcaAF9fGAXbdzlH3k45de0fWbI7J0lA';
    $CONSUMER_SECRET = 'Am4ZbGecWb7HeZAp';

    // cURL initialization
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'grant_type' => 'client_credentials',
        'client_id' => $CONSUMER_KEY,
        'client_secret' => $CONSUMER_SECRET,
        'scope' => 'free-trial-numbers:read free-trial-numbers:write messages:read messages:write virtual-numbers:read virtual-numbers:write reports:read reports:write'
    )));

    // Execute the request
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
        exit;
    }
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $ACCESS_TOKEN = $data['access_token'];
        return $ACCESS_TOKEN;
    } else {
        echo 'Error: Access token not found in response.';
        return null;
    }
}

//Telstra SMS Function
function send_sms($ACCESS_TOKEN, $phone, $company_name, $sitename, $tankno, $formattedPercent, $formattedVolume, $formattedUllage){
    $messageData = array(
        'to' => "$phone",
        'from' => 'privateNumber',
        'messageContent' => "Ehon Alert for $company_name, Site: $sitename, Tank: $tankno, Current Percent: $formattedPercent%,  Volume: $formattedVolume, Ullage: $formattedUllage"
    );
    $url = 'https://products.api.telstra.com/messaging/v3/messages';
    
    // cURL initialization
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'authorization: Bearer ' . $ACCESS_TOKEN,
        'accept: application/json',
        'accept-charset: utf-8',
        'content-type: application/json',
        'content-language: en-au'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
    
    // Execute the request
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
        exit;
    }
    curl_close($ch);
    echo $response;
}

    // Retrieve the company IDs from the database
    $sql = "select * from ipetroco_ehon_tsm.client_sites as cs inner join ipetroco_ehon_tsm.clients_login_companies as clc on (cs.company_id,cs.sub_id) = (clc.company_id,clc.sub_id)";
    $resultid = $conn->query($sql);
    
    if ($resultid->num_rows > 0) {
        $ACCESS_TOKEN = sms_token();
        while ($row = $resultid->fetch_assoc()) {
                $case = 0;
                $alertstupd= 0;
                $company_name=$row['company_name'];
                $percent=$row['current_percent'];
                $volume=$row['current_volume'];
                $volume_alert=$row['volume_alert'];
                $percent_alert = $row['percent_alert'];
                $alertst=$row['alert'];
                $companyid=$row['company_id'];
                $clientid=$row['sub_id'];
                $sitename=$row['site_name'];
                $tankno=$row['tank_no'];
                $phone=$row['mobile'];
                $receiver_email=$row['email'];
                $ullage=$row['ullage'];
                $formattedUllage = number_format($ullage);
                $formattedVolume = number_format($volume);
                $formattedPercent = number_format($percent,2);
                $email_content = "Ehon Alert for $company_name, Site: $sitename, Tank: $tankno, Current Percent: $formattedPercent%,  Volume: $formattedVolume, Ullage: $formattedUllage";
                $email_subject = "Ehon Alert for $sitename, Tank $tankno is Low";
                
                //case 1: mobile
                if(!empty($phone)){
                    if($percent < $percent_alert && $alertst==0 && $percent_alert>0){
                        send_sms($ACCESS_TOKEN, $phone, $company_name, $sitename, $tankno, $formattedPercent, $formattedVolume, $formattedUllage);
                        $alertstupd=1;
                        $case=1;
                    }
                    if($volume < $volume_alert && $alertst==0  && $volume_alert>0){
                        send_sms($ACCESS_TOKEN, $phone, $company_name, $sitename, $tankno, $formattedPercent, $formattedVolume, $formattedUllage);
                        $alertstupd=1;
                        $case=1;
                    }
                } 
                if(!empty($receiver_email)){
                    if($percent < $percent_alert && $alertst==0 && $percent_alert>0){
                        $alertstupd=1;
                        $case=1;
                        send_email($receiver_email, $email_subject, $email_content);                
                    }
                    if($volume < $volume_alert && $alertst==0  && $volume_alert>0){
                        $alertstupd=1;
                        $case=1;
                        send_email($receiver_email, $email_subject, $email_content);                       
                    }
                } 
                
                
                //update for percent
                if($percent > $percent_alert && $alertst==1  && $percent_alert>0){
                    echo "Alert percent: $percent<br>";
                    $alertstupd=0;
                    $case=1;
                }
                if($volume > $volume_alert && $alertst==1  && $volume_alert>0){
                    echo "Alert volume: $volume<br>";
                    $alertstupd=0;
                    $case=1;
                } 
            
            $upd = "UPDATE ipetroco_ehon_tsm.client_sites set alert = $alertstupd where company_id = $companyid and sub_id=$clientid and site_name like '%$sitename%' and tank_no=$tankno and current_volume=$volume";
            if($case==1){
                $resultupd = $conn->query($upd);
                echo"<br>update $sitename, alert: $alertstupd <br>where company_id = $companyid and sub_id=$clientid and site_name like '%$sitename%' and tank_no=$tankno and current_volume=$volume<br>";

            }
            else{
                continue;
            }
        }

    }
    $conn->close();
?>