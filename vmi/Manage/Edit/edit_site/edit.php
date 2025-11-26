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
        
        // First, get the device_type to determine if it's a gateway (30) or FMS (10)
        $deviceTypeSql = "SELECT device_type FROM console WHERE uid = ?";
        $deviceTypeStmt = $conn->prepare($deviceTypeSql);
        $deviceTypeStmt->bind_param("i", $uid_tank);
        $deviceTypeStmt->execute();
        $deviceTypeStmt->bind_result($device_type);
        $deviceTypeStmt->fetch();
        $deviceTypeStmt->close();
        
        $isGateway = ($device_type == 30);
        
        // For gateways, fetch all tanks (including disabled ones). For FMS, only enabled tanks.
        if($companyId==15100){
            if ($isGateway) {
                // Gateway: fetch all tanks with enabled status
                $sql = "SELECT t.tank_id, t.Tank_name, t.capacity, t.product_id, t.enabled 
                        FROM Tanks t 
                        WHERE t.Site_id = ? AND t.uid = ?
                        ORDER BY t.tank_id;";
                $sqlexec = $conn->prepare($sql);
                $sqlexec->bind_param("ii", $siteid_tank, $uid_tank);
            } else {
                // FMS: only enabled tanks
                $sql = "SELECT t.tank_id, t.Tank_name, t.capacity, t.product_id 
                        FROM Tanks t 
                        WHERE t.Site_id = ? AND t.uid = ? AND t.enabled = 1
                        ORDER BY t.tank_id;";
                $sqlexec = $conn->prepare($sql);
                $sqlexec->bind_param("ii", $siteid_tank, $uid_tank);
            }
        }
        else{
            if ($isGateway) {
                // Gateway: fetch all tanks with enabled status
                $sql = "SELECT t.tank_id, t.Tank_name, t.capacity, t.product_id, t.enabled 
                        FROM Tanks t 
                        WHERE t.Site_id = ? AND t.uid = ? AND t.client_id = ?
                        ORDER BY t.tank_id;";
                $sqlexec = $conn->prepare($sql);
                $sqlexec->bind_param("iii", $siteid_tank, $uid_tank, $companyId);
            } else {
                // FMS: only enabled tanks
                $sql = "SELECT t.tank_id, t.Tank_name, t.capacity, t.product_id 
                        FROM Tanks t 
                        WHERE t.Site_id = ? AND t.uid = ? AND t.client_id = ? AND t.enabled = 1
                        ORDER BY t.tank_id;";
                $sqlexec = $conn->prepare($sql);
                $sqlexec->bind_param("iii", $siteid_tank, $uid_tank, $companyId);
            }
        }
        
        if ($sqlexec) {
            $sqlexec->execute();
            $result = $sqlexec->get_result();
            $tankRows = array();
            
            // Store all tank rows first
            while ($row = $result->fetch_assoc()) {
                $tankRows[] = $row;
            }
            $sqlexec->close();
            
            // Create a map of existing tanks for quick lookup
            $tankMap = array();
            foreach ($tankRows as $tankRow) {
                $tankMap[$tankRow['tank_id']] = $tankRow;
            }
            
            // For gateways, always return all 4 tanks (1-4), even if they don't exist yet
            // For FMS, only return existing enabled tanks
            if ($isGateway) {
                // Gateway: return all 4 slots
                for ($tank_id = 1; $tank_id <= 4; $tank_id++) {
                    if (isset($tankMap[$tank_id])) {
                        $tankRow = $tankMap[$tank_id];
                        $tank_name = $tankRow['Tank_name'];
                        $capacity = $tankRow['capacity'];
                        $product_id = $tankRow['product_id'];
                        $enabled = isset($tankRow['enabled']) ? $tankRow['enabled'] : 0;
                    } else {
                        // Tank doesn't exist yet - create default entry
                        $tank_name = null;
                        $capacity = 0;
                        $product_id = 0;
                        $enabled = 0;
                    }
                    
                    $tankData = array(
                        "tank_id" => $tank_id,
                        "tank_name" => $tank_name ? $tank_name : "Tank " . $tank_id,
                        "capacity" => $capacity,
                        "product_id" => $product_id ? $product_id : 0,
                        "enabled" => $enabled,
                        "pumps" => array() // Gateways don't have pumps
                    );
                    
                    $tanks[] = $tankData;
                }
            } else {
                // FMS: process only existing tanks
                foreach ($tankRows as $tankRow) {
                    $tank_id = $tankRow['tank_id'];
                    $tank_name = $tankRow['Tank_name'];
                    $capacity = $tankRow['capacity'];
                    $product_id = $tankRow['product_id'];
                    $enabled = isset($tankRow['enabled']) ? $tankRow['enabled'] : 1;
                    
                    $tankData = array(
                        "tank_id" => $tank_id,
                        "tank_name" => $tank_name ? $tank_name : "Tank " . $tank_id,
                        "capacity" => $capacity,
                        "product_id" => $product_id ? $product_id : 0,
                        "enabled" => $enabled
                    );
                    
                    // Fetch pumps for FMS devices
                    $pumps = array();
                    $pumpSql = "SELECT pump_id, Nozzle_Number, Pulse_Rate 
                                FROM pumps 
                                WHERE uid = ? AND tank_id = ? 
                                ORDER BY Nozzle_Number;";
                    $pumpStmt = $conn->prepare($pumpSql);
                    if ($pumpStmt) {
                        $pumpStmt->bind_param("ii", $uid_tank, $tank_id);
                        $pumpStmt->execute();
                        $pumpResult = $pumpStmt->get_result();
                        
                        while ($pumpRow = $pumpResult->fetch_assoc()) {
                            $pumps[] = array(
                                "pump_id" => $pumpRow['pump_id'],
                                "nozzle_number" => $pumpRow['Nozzle_Number'],
                                "pulse_rate" => $pumpRow['Pulse_Rate']
                            );
                        }
                        $pumpStmt->close();
                    }
                    $tankData["pumps"] = $pumps;
                    
                    $tanks[] = $tankData;
                }
            }
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
