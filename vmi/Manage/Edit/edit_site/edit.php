<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
// Check if the required keys are present in the input
if (isset($input['case'])) {
   
    $case = $input['case'];
   


    if ($case == 1) {
        $siteid = $input['siteid'];
        $response = array();
        // Ensure $companyId is defined and valid
        if($companyId==15100){
            $sql = "SELECT Site_id, Client_id, uid, Site_name, Site_info, site_country, site_address, site_city, postcode, phone, Email, time_zone FROM Sites WHERE Site_id = ?;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("i", $siteid);
        }
        else{
            $sql = "SELECT Site_id, Client_id, uid, Site_name, Site_info, site_country, site_address, site_city, postcode, phone, Email, time_zone FROM Sites WHERE client_id = ? AND Site_id = ?;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("ii", $companyId, $siteid);
        }
        if ($sqlexec) {
            $sqlexec->execute();
            $sqlexec->bind_result($Site_id, $Client_id, $uid, $Site_name, $Site_info, $site_country, $site_address, $site_city, $postcode, $phone, $Email, $timezone);
            while ($sqlexec->fetch()) {
                $response['Site_id'] = $Site_id;
                $response['Client_id'] = $Client_id;
                $response['uid'] = $uid;
                $response['Site_name'] = $Site_name;
                $response['Site_info'] = $Site_info;
                $response['site_country'] = $site_country;
                $response['site_address'] = $site_address;
                $response['site_city'] = $site_city;
                $response['postcode'] = $postcode;
                $response['phone'] = $phone;
                $response['Email'] = $Email;
                $response['Email'] = $Email;
                $response['timezone'] = $timezone;

            }
            $sqlexec->close();
        } else {
            // Handle SQL preparation error
            $response['error'] = "SQL preparation failed.";
        }
        echo json_encode($response);
    }
    if($case == 2){
        $siteid_tank = $input['siteid_tank'];
        $uid_tank = $input['uid_tank'];
        $sites = array();
        // Ensure $companyId is defined and valid
        if($companyId==15100){
            $sql = "SELECT tank_id, Tank_name FROM Tanks WHERE Site_id = ? and uid = ?;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("ii", $siteid_tank, $uid_tank);
        }
        else{
            $sql = "SELECT tank_id, Tank_name FROM Tanks WHERE Site_id = ? and uid = ? and client_id = ?";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("iii", $siteid_tank, $uid_tank, $companyId);
        }
        if ($sqlexec) {
            $sqlexec->execute();
            $sqlexec->bind_result($tank_id, $tank_name);
            while ($sqlexec->fetch()) {
                $sites[] = array(
                    "id" => $tank_id,
                    "name" => $tank_name
                );
            }
            $sqlexec->close();
        } else {
            // Handle SQL preparation error
            $sites['error'] = "SQL preparation failed.";
        }
        echo json_encode($sites);
    }
    
} else {
    echo json_encode(array('error' => $input));
}

$conn->close();
?>
