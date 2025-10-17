<?php
include('../../db/email_conf.php');

// fetch_data2();

function fetch_data2(){
    include('../../db/dbh2.php');
    // Retrieve the company IDs from the database
    $sql = "SELECT st.uid, st.Client_id, Site_name, tank_id, level_alert, alert_flag, current_volume, current_percent, ullage, phone, Email 
    FROM Sites as st JOIN Tanks as ts on (st.uid, st.Site_id) = (ts.uid, ts.Site_id) 
    where (phone is not null or Email is not null) and alert_flag = 1;";
    $resultid = $conn->query($sql);
    
    if ($resultid->num_rows > 0) {
        while ($row = $resultid->fetch_assoc()) {
            $case = 0;
            $alertstupd= 0;
            $companyid=$row['Client_id'];
            $percent=$row['current_percent'];
            $volume=$row['current_volume'];
            $volume_alert=$row['level_alert'];
            $alert_flag = $row['alert_flag'];
            $clientid=$row['uid'];
            $sitename=$row['Site_name'];
            $tankno=$row['tank_id'];
            $phone=$row['phone'];
            $receiver_email=$row['Email'];
            $ullage=$row['ullage'];
            $formattedUllage = number_format($ullage);
            $formattedVolume = number_format($volume);
            $formattedPercent = number_format($percent,2);
            $email_content = "Ehon Alert for Site: $sitename<br>Tank: $tankno<br>Current Percent: $formattedPercent%<br>Volume: $formattedVolume" . "L<br>Ullage: $formattedUllage" . "L<br><br>";
            $email_subject = "Ehon Alert for $sitename, Tank $tankno is Low";
            $flag = 0;
            
            if(!empty($receiver_email)){        
                if($volume < $volume_alert && $alert_flag==1){
                    echo "Sending email to $receiver_email for site $sitename and tank $tankno<br>";
                    $alertstupd=2;
                    $email_status = send_email($receiver_email, $email_subject, $email_content);
                    save_historic($conn, $receiver_email);
                    echo "Email status: " . $email_status['message'] . "<br>";
                    $flag = 1;                     
                }
            } 
            
            if ($flag == 1){
                $upd = "UPDATE Tanks set alert_flag = ? WHERE uid = ? and Tank_id = ? and current_volume = ?";
                $resultupd = $conn->prepare($upd);
                $resultupd->bind_param("iiis", $alertstupd, $clientid, $tankno, $volume);
                if($resultupd->execute()){
                    echo "Updated alert flag for site $sitename, tank $tankno<br>";
                }
                else{
                    echo "Error updating alert flag for site $sitename, tank $tankno: $conn->error<br>";
                }

                $resultupd->close();
            }
        }   

    } else {
        // echo "No alerts found.<br>";
    }
    $conn->close();
}
?>
