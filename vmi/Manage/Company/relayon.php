<?php
include('../../db/dbh2.php'); 

// $vol = 7000;
$sql = "SELECT crithigh_alarm, high_alarm, low_alarm, critlow_alarm, relay1, relay2, relay3, relay4, current_volume
        FROM alarms_config ac JOIN Tanks ts on (ts.uid, ts.tank_id ) = (ac.uid, ac.tank_id)
        WHERE ts.uid = 398312 AND ts.tank_id = 1";

$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->bind_result($hh, $h, $l, $ll, $r1, $r2, $r3, $r4, $vol);

if ($stmt->fetch()) {
    echo "High High: $hh<br>High: $h<br>Low: $l<br>LowLow: $ll<br>rel1: $r1<br>rel2: $r2<br>rel3: $r3<br>rel4: $r4<br>";
    echo "Current Vol: $vol<br>";
    // Create an array of relay values
    $relays = array($r1, $r2, $r3, $r4);
    
    for ($i = 1; $i <= 4; $i++) {
        // Get the relay value from the array
        $crel = $relays[$i-1];
        
        $relay = level($crel, $vol, $hh, $h, $l, $ll);
        if ($relay == 1) {
            echo "Relay $i is on<br>";
        } else {
            echo "Relay $i is off<br>";
        }
    }
} else {
    echo "error";
}

$stmt->close();
$conn->close();

function level($lvl, $vol, $hh, $h, $l, $ll) {
    if ($lvl == 4) {
        if ($vol >= $hh) {
            return 1;
        } else {
            return 0;
        }
    } elseif ($lvl == 3) {
        if ($vol >= $h && $vol <= $hh) {
            return 1;
        } else {
            return 0;
        }
    } elseif ($lvl == 2) {
        if ($vol < $h && $vol > $ll) {
            return 1;
        } else {
            return 0;
        }
    } elseif ($lvl == 1) {
        if ($vol <= $l) {
            return 1;
        } else {
            return 0;
        }
    }
}
?>
