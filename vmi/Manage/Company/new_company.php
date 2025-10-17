<?php
session_start(); 
include('../../db/dbh2.php');
include('../../db/border_nopriv.php');
$uid = $_SESSION['uid'];
    $deviceid = $_SESSION['deviceid'];
// Check if uid and deviceid are set in the session
if(isset($_SESSION['uid']) && isset($_SESSION['deviceid'])) {
    $uid = $_SESSION['uid'];
    $deviceid = $_SESSION['deviceid'];
    $idcheck = "SELECT uid, device_id, console_status FROM console WHERE uid = ? and device_id = ?";
    
    $stmt = $conn->prepare($idcheck);
    $stmt->bind_param("ss", $uid, $deviceid); // Bind parameters
    $stmt->execute();

    // Bind result variables
    $bound_uid = null;
    $bound_deviceid = null;
    $bound_console_status = null;
    $stmt->bind_result($bound_uid, $bound_deviceid, $bound_console_status); 

    if ($stmt->fetch()) {
        // Now you can use $bound_uid, $bound_deviceid, $bound_console_status
        if ($bound_console_status !== "Dispatched to Client") {
            echo "<script type='text/javascript'>alert('Code already used or invalid');
            window.location.href='/vmi/login/verification';
            </script>";
            exit();
        }
        // Continue with the rest of the page if status is Dispatched
    } else {
        echo "<script type='text/javascript'>alert('Invalid UID or Device ID');
        window.location.href='/vmi/login/verification';
        </script>";
        exit();
    }
} 
else {
    echo "<script type='text/javascript'>alert('Please use a valid code');
    window.location.href='/vmi/login/';
    </script>";
    exit();
}
// HTML content here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Company</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
                <form id="company_info" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Company Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Company Name:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter the Company Name" name="Client_name" required>                           
                                <label>Company Address:</label>
                                <input class="input" type="text" placeholder="Enter Company address" name="Client_address" required>                
                                <label>Company Phone:</label>
                                <input class="input" type="tel" placeholder="Enter Company phone number" name="Client_phone" required>
                            </div>
                            <br>
                            <h1 class="display-4 mg-bottom-4px">Personal Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label>First Name:</label>
                                <input class="input" type="text" placeholder="Enter your name" name="firstname" autocomplete="off" required>
                                <label>Last Name:</label>
                                <input class="input" type="text" placeholder="Enter your Last Name" name="lastname" autocomplete="off" required>
                                <label>Email:</label>
                                <input class="input" type="email" placeholder="Enter contact email" name="Client_email" autocomplete="off" required>
                                <label>Password:</label>
                                <input class="input" type="password" placeholder="Enter Password" name="Client_password" autocomplete="off" required>
                                <label>Confirm Password:</label>
                                <input class="input" type="password" placeholder="Confirm Password" name="Client_confirm_password" autocomplete="off" required>
                                <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid); ?>">
                                <input type="hidden" name="deviceid" value="<?php echo htmlspecialchars($deviceid); ?>">
                            </div>
                            <br>                            
                            
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block">
                        <div class="flex-horizontal gap-column-6px">
                                <div>Create company</div>
                            </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('company_info').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent form submission

        var password = document.getElementsByName('Client_password')[0].value;
        var confirmPassword = document.getElementsByName('Client_confirm_password')[0].value;

        if (password !== confirmPassword) {
            toastr.error('Passwords do not match.');
            return;
        }

        $.ajax({
            url: 'new_company_sbmt.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'error') {
                    toastr.error(response.message);
                } else if (response.status === 'success') {
                    toastr.success(response.message);
                    setTimeout(function() {
                        window.location.href = 'new_site.php';
                    }, 2000); // Adjust the timeout as needed
                }
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
            }
        });
    });
</script>
</body>
</html>
