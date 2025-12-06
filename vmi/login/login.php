<?php
include('../db/dbh2.php');
ini_set('session.gc_maxlifetime', 21600);
session_set_cookie_params(21600); 
session_start();

// Set timezone and define date/time variables
date_default_timezone_set('Australia/Brisbane');
$date = date('Y-m-d');
$time = date('H:i:s');


if (isset($_POST['username'], $_POST['password'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, password, access_level, client_id, active FROM login WHERE username = ?");
    
    if (!$stmt) {
        die("Error in query preparation."); // Better to log this error instead of displaying it.
    }

    $stmt->bind_param("s", $username);

    if (!$stmt->execute()) {
        error_log("DB Error in " . __FILE__ . ": " . $stmt->error);
        die("Error in query execution."); // Again, better to log this error.
    }

    $stmt->bind_result($userId, $storedPassword, $accessLevel, $companyId, $active);
    // echo "TEST33 $companyId, $accessLevel, $username, $password";
    if ($stmt->fetch()) {
        if (password_verify($password, $storedPassword)) {
            if($active == 1){
                // Prevent session hijacking and fixation
                session_regenerate_id(true);
                
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['accessLevel'] = $accessLevel;
                $_SESSION['companyId'] = $companyId;
                $_SESSION['userId'] = (int)$userId;
                $stmt->close();
                // check for sites
                $sqld = "SELECT * FROM Sites WHERE client_id = ?";
                $stmtd = $conn->prepare($sqld);

                // Bind the parameter
                $stmtd->bind_param('i', $companyId);

                // Execute the statement
                $stmtd->execute();

                // Fetch all the results
                $result = $stmtd->fetch();
                $stmtd->close();
                // Update last login date/time
                $stmupd= $conn->prepare("UPDATE login SET last_date = ?, last_time = ? WHERE username = ?");
                $stmupd->bind_param("sss", $date, $time, $username);
                $stmupd->execute();
                $stmupd->close();

                // Update dark_mode preference if provided (from browser detection)
                if (isset($_POST['dark_mode'])) {
                    $darkMode = (int)$_POST['dark_mode']; // 0 or 1
                    $stmupdDark = $conn->prepare("UPDATE login SET dark_mode = ? WHERE username = ?");
                    $stmupdDark->bind_param("is", $darkMode, $username);
                    $stmupdDark->execute();
                    $stmupdDark->close();
                }

                header("Location: /vmi/reports");
                exit;
            } else {
                echo "<script>alert('Login Not Active.'); window.location.href = '/vmi/login';</script>";
            }
        }
        else{    
            // Generic error message for either wrong username or password
            echo "<script>alert('Incorrect Password.'); window.location.href = '/vmi/login';</script>";
            }
    }
    else{    
    // Generic error message for either wrong username or password
    echo "<script>alert('Incorrect login credentials.'); window.location.href = '/vmi/login';</script>";
    }
    $conn->close();
}
else{
    header("Location: /vmi/login/"); 
    exit;
}
?>
