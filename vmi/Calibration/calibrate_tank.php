<?php
// Include the database connection
include("../db/dbh2.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tankId = $_POST['tank_id'];
    $tankUid = $_POST['tank_uid'];
    $newVolume = $_POST['current_volume'];

    // Select the current volume, volume height, json_data, and current offset from the database
    $sqlsel = "SELECT current_volume, volume_height, json_data, offset_tank, offset_flag, uid FROM Tanks ts JOIN strapping_chart sc ON ts.chart_id = sc.chart_id WHERE tank_id = ? AND tank_uid = ?";
    $stmtsel = $conn->prepare($sqlsel);
    $stmtsel->bind_param("ii", $tankId, $tankUid); // Correct bind_param type
    $stmtsel->execute();
    $stmtsel->bind_result($currentVolume, $volumeHeight, $jsonData, $currentOffset, $offset_flag, $uid);
    $stmtsel->fetch();
    $stmtsel->close();

    // Parse the JSON data to get the volume-height pairs
    $strappingData = json_decode($jsonData, true);

    // Initialize variables for interpolation
    $lowerVolume = 0;
    $upperVolume = 0;
    $lowerHeight = 0;
    $upperHeight = 0;
    if($offset_flag == 0){
        // Find the two closest points for interpolation
        foreach ($strappingData as $index => $dataPoint) {
            if ($newVolume <= $dataPoint['volume']) {
                $upperVolume = $dataPoint['volume'];
                $upperHeight = $dataPoint['height'];
                if ($index > 0) {
                    $lowerVolume = $strappingData[$index - 1]['volume'];
                    $lowerHeight = $strappingData[$index - 1]['height'];
                }
                break;
            }
        }

        // Calculate the correct height using linear interpolation
        if ($upperVolume != $lowerVolume) {
            $correctHeight = $lowerHeight + (($newVolume - $lowerVolume) * ($upperHeight - $lowerHeight) / ($upperVolume - $lowerVolume));
        } else {
            $correctHeight = $lowerHeight;
        }

        // Adjust the correct height based on the current offset
        $correctHeight += $currentOffset;

        // Calculate the new offset
        $newOffset = $correctHeight - $volumeHeight;
        $newOffset = round($newOffset);
        $flagoff = 1;
        // Update the tank's offset in the database
        $sqlUpdate = "UPDATE Tanks SET offset_tank = ?, offset_flag = ? WHERE tank_id = ? AND tank_uid = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("diii", $newOffset, $flagoff, $tankId, $tankUid); // Correct bind_param type

        if ($stmt->execute()) {
            echo "Tank $tankId successfully calibrated. The new offset is $newOffset mm. Please allow 15 minutes for the changes to take effect.";
            $stmt->close();
            $updQuery = "UPDATE console SET cfg_flag = 1 WHERE uid = ?";
            $updStmt = $conn->prepare($updQuery);
            $updStmt->bind_param("s", $uid);
            $updStmt->execute();
            $updStmt->close();
        }
        else {
            echo "Error updating tank";
        }
    } else{
        echo "Offset already update, wait for the next update or update the value on the portal";
    } 
} else {
    echo "Invalid request.";
}
?>
