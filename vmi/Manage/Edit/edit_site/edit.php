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
            $sql = "SELECT s.Site_id, s.Client_id, s.uid, s.Site_name, s.Site_info, s.site_country, s.site_address, s.site_city, s.postcode, s.phone, s.Email, s.time_zone, c.device_type 
                    FROM Sites s 
                    LEFT JOIN console c ON c.uid = s.uid 
                    WHERE s.Site_id = ?;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("i", $siteid);
        }
        else{
            $sql = "SELECT s.Site_id, s.Client_id, s.uid, s.Site_name, s.Site_info, s.site_country, s.site_address, s.site_city, s.postcode, s.phone, s.Email, s.time_zone, c.device_type 
                    FROM Sites s 
                    LEFT JOIN console c ON c.uid = s.uid 
                    WHERE s.client_id = ? AND s.Site_id = ?;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("ii", $companyId, $siteid);
        }
        if ($sqlexec) {
            $sqlexec->execute();
            $sqlexec->bind_result($Site_id, $Client_id, $uid, $Site_name, $Site_info, $site_country, $site_address, $site_city, $postcode, $phone, $Email, $timezone, $device_type);
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
                $response['timezone'] = $timezone;
                $response['device_type'] = $device_type ? $device_type : 0;

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
    
    if($case == 4){
        // Fetch tank details with capacity and associated pumps
        $siteid_tank = $input['siteid_tank'];
        $uid_tank = $input['uid_tank'];
        $tanks = array();
        
        if($companyId==15100){
            $sql = "SELECT t.tank_id, t.Tank_name, t.capacity 
                    FROM Tanks t 
                    WHERE t.Site_id = ? AND t.uid = ? AND t.enabled = 1
                    ORDER BY t.tank_id;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("ii", $siteid_tank, $uid_tank);
        }
        else{
            $sql = "SELECT t.tank_id, t.Tank_name, t.capacity 
                    FROM Tanks t 
                    WHERE t.Site_id = ? AND t.uid = ? AND t.client_id = ? AND t.enabled = 1
                    ORDER BY t.tank_id;";
            $sqlexec = $conn->prepare($sql);
            $sqlexec->bind_param("iii", $siteid_tank, $uid_tank, $companyId);
        }
        
        if ($sqlexec) {
            $sqlexec->execute();
            $sqlexec->bind_result($tank_id, $tank_name, $capacity);
            while ($sqlexec->fetch()) {
                // Fetch pumps for this tank
                $pumps = array();
                $pumpSql = "SELECT pump_id, Nozzle_Number, Nozzle_Walk_Time, Nozzle_Auth_Time, Nozzle_Max_Run_Time, Nozzle_No_Flow, Nozzle_Product, Pulse_Rate 
                            FROM pumps 
                            WHERE uid = ? AND tank_id = ? 
                            ORDER BY Nozzle_Number;";
                $pumpStmt = $conn->prepare($pumpSql);
                $pumpStmt->bind_param("ii", $uid_tank, $tank_id);
                $pumpStmt->execute();
                $pumpStmt->bind_result($pump_id, $nozzle_number, $nozzle_walk_time, $nozzle_auth_time, $nozzle_max_run_time, $nozzle_no_flow, $nozzle_product, $pulse_rate);
                while ($pumpStmt->fetch()) {
                    $pumps[] = array(
                        "pump_id" => $pump_id,
                        "nozzle_number" => $nozzle_number,
                        "nozzle_walk_time" => $nozzle_walk_time,
                        "nozzle_auth_time" => $nozzle_auth_time,
                        "nozzle_max_run_time" => $nozzle_max_run_time,
                        "nozzle_no_flow" => $nozzle_no_flow,
                        "nozzle_product" => $nozzle_product,
                        "pulse_rate" => $pulse_rate
                    );
                }
                $pumpStmt->close();
                
                $tanks[] = array(
                    "tank_id" => $tank_id,
                    "tank_name" => $tank_name ? $tank_name : "Tank " . $tank_id,
                    "capacity" => $capacity,
                    "pumps" => $pumps
                );
            }
            $sqlexec->close();
        } else {
            $tanks['error'] = "SQL preparation failed.";
        }
        echo json_encode($tanks);
    }
    
} else {
    echo json_encode(array('error' => $input));
}

$conn->close();
?>
