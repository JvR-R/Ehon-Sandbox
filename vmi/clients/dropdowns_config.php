<?php
    include('../db/dbh2.php');
    include('../db/log.php'); 
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    // $sitename = $_GET['sitename'];
    $case = $_GET['case'];
    $response=array();
    
    //Products db call*****************
        if($case == 1){
            $sqldev="SELECT * FROM products";
            $resultdev = $conn->query($sqldev);
            if ($resultdev->num_rows > 0) {
                while ($row = $resultdev->fetch_assoc()) {
                    $response['products'][] = array(
                        "product_id" => $row['product_id'],
                        "product_name" => $row['product_name'],
                    );
                }
            }
        }

    //Charts DB Call
        else if($case == 2){
            if($companyId == 15100){
                $sqlsch="SELECT * FROM strapping_chart;";
            }
            else{
            $sqlsch="SELECT * FROM strapping_chart as sc JOIN Console_Asociation as ca on ca.Client_id = sc.client_id 
            WHERE sc.client_id in ($companyId, 15100) group by chart_id";
            }
            $resultsch = $conn->query($sqlsch);
            if ($resultsch->num_rows > 0) {
                while ($row = $resultsch->fetch_assoc()) {
                    $response['schart'][] = array(
                        "chart_id" => $row['chart_id'],
                        "chart_name" => $row['chart_name'],
                    );
                }
            }
        }
        else if($case == 3){
            $uid = $_GET['uid'];
            $uart = $_GET['selectedValue'];
            if($uart == '11'){
                $sqluart="SELECT uart1 as uart, UART1_ID as id FROM console where uid = $uid";
                $resultuart = $conn->query($sqluart);
                if ($resultuart->num_rows > 0) {
                    while ($row = $resultuart->fetch_assoc()) {
                        $ids = explode(',', $row['id']);
                        $id1 = isset($ids[0]) ? $ids[0] : 0;
                        if($id1 == 1){
                            $response['newValue'] = $row['uart'];
                        }
                        else{
                            $response['newValue'] = 0;
                        }
                    }
                }
            }
            elseif($uart == '12'){
                $sqluart="SELECT uart1 as uart, UART1_ID as id FROM console where uid = $uid";
                $resultuart = $conn->query($sqluart);
                if ($resultuart->num_rows > 0) {
                    while ($row = $resultuart->fetch_assoc()) {
                        $ids = explode(',', $row['id']);
                        $id1 = isset($ids[0]) ? $ids[0] : 0;
                        $id2 = isset($ids[1]) ? $ids[1] : 0;
                        if($id2 == 2 || $id1 == 2){
                            $response['newValue'] = $row['uart'];
                        }
                        else{
                            $response['newValue'] = 0;
                        }
                    }
                }
            }
            else{
                $sqluart="SELECT uart$uart as uart FROM console where uid = $uid";  
                $resultuart = $conn->query($sqluart);
                if ($resultuart->num_rows > 0) {
                    while ($row = $resultuart->fetch_assoc()) {
                        $response['newValue'] = $row['uart'];
                    }
                }
            }
        }
        else if($case == 4){
            $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
            $tank_no = isset($_GET['tank_no']) ? intval($_GET['tank_no']) : 0;
        
            // Fetch fms_number and fms data from the database
            $sqlFmsData = "SELECT fms_number, fms_uart, fms_type, fms_id FROM Tanks WHERE uid = $uid AND tank_id = $tank_no";
            $resultFmsData = $conn->query($sqlFmsData);
            $fmsData = [];
            if ($resultFmsData && $resultFmsData->num_rows > 0) {
                $row = $resultFmsData->fetch_assoc();
                $fms_number = intval($row['fms_number']);
                $fms_uart_array = explode(',', $row['fms_uart']);
                $fms_type_array = explode(',', $row['fms_type']);
                $fms_id_array = explode(',', $row['fms_id']);
        
                // Now, construct the fmsData array
                for ($i = 0; $i < $fms_number; $i++) {
                    $fmsData[] = [
                        'fms_port' => isset($fms_uart_array[$i]) ? $fms_uart_array[$i] : '',
                        'fms_type' => isset($fms_type_array[$i]) ? $fms_type_array[$i] : '',
                        'fms_id'   => isset($fms_id_array[$i]) ? $fms_id_array[$i] : '',
                    ];
                }
            } else {
                $fms_number = 0; // Default value if not found
            }
        
            $response['fms_number'] = $fms_number;
            $response['fmsData'] = $fmsData;
        }
        else if($case == 5){
            $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
            $tank_no = isset($_GET['tank_no']) ? intval($_GET['tank_no']) : 0;
        
            // Fetch fms_number and fms data from the database
            $sqlFmsData = "SELECT fms_number, fms_uart, fms_type, fms_id FROM Tanks WHERE uid = $uid AND tank_id = $tank_no";
            $resultFmsData = $conn->query($sqlFmsData);
            $fmsData = [];
            if ($resultFmsData && $resultFmsData->num_rows > 0) {
                $row = $resultFmsData->fetch_assoc();
                $fms_number = intval($row['fms_number']);
                $fms_uart_array = explode(',', $row['fms_uart']);
                $fms_type_array = explode(',', $row['fms_type']);
                $fms_id_array = explode(',', $row['fms_id']);
        
                // Now, construct the fmsData array
                for ($i = 0; $i < $fms_number; $i++) {
                    $fmsData[] = [
                        'fms_port' => isset($fms_uart_array[$i]) ? $fms_uart_array[$i] : '',
                        'fms_type' => isset($fms_type_array[$i]) ? $fms_type_array[$i] : '',
                        'fms_id'   => isset($fms_id_array[$i]) ? $fms_id_array[$i] : '',
                    ];
                }
            } else {
                $fms_number = 0; // Default value if not found
            }
        
            $response['fms_number'] = $fms_number;
            $response['fmsData'] = $fmsData;
        }
        else if($case == 'get_fms_devices'){
            // Fetch devices where device_id < 200
            $sqldev = "SELECT * FROM tankgauge_type WHERE tank_gauge_id < 200";
            $resultdev = $conn->query($sqldev);
            $devices = [];
            if ($resultdev->num_rows > 0) {
                while ($row = $resultdev->fetch_assoc()) {
                    $devices[] = array(
                        'device_id' => $row['tank_gauge_id'],
                        'device_name' => $row['device_name'],
                    );
                }
            }
            $response['devices'] = $devices;
        }
        
        else    {
            $response['error'] = "Invalid";
        }
    $conn->close();
    // Return the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
?>