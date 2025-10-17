<?php
    include('../db/dbh2.php'); 
    include('../db/border_nopriv.php');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Function to verify token and get user details
    function verifyToken($conn, $token) {
        $stmt = $conn->prepare("SELECT username FROM login WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }

    $token = $_GET['token'] ?? '';

    $user = verifyToken($conn, $token);

    if (!$token || !$user) {
        die('Invalid or expired token.');
    }

    $email = htmlspecialchars($user['username']);

    // HTML content here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHON Energy Tech</title>
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
            <div class="dashboard-main-content">
                <form id="user_info" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">Personal Information</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label>First Name:</label>
                                <input class="input" type="text" placeholder="Enter your name" name="firstname" autocomplete="given-name" required>
                                <label>Last Name:</label>
                                <input class="input" type="text" placeholder="Enter your Last Name" name="lastname" autocomplete="family-name"  required>
                                <label>Email:</label>
                                <input class="input" type="email" value="<?php echo $email; ?>" name="Client_email" autocomplete="username" readonly>
                                <label>Password:</label>
                                <input class="input" type="password" placeholder="Enter Password" name="Client_password" autocomplete="new-password"  required>
                                <label>Confirm Password:</label>
                                <input class="input" type="password" placeholder="Confirm Password" name="Client_confirm_password" autocomplete="new-password"  required>
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            </div>
                            <br>                            
                            
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block">
                        <div class="flex-horizontal gap-column-6px">
                                <div>Create User</div>
                            </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('user_info').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent form submission

        var password = document.getElementsByName('Client_password')[0].value;
        var confirmPassword = document.getElementsByName('Client_confirm_password')[0].value;

        if (password !== confirmPassword) {
            toastr.error('Passwords do not match.');
            return;
        }
        console.log($(this).serialize());
        $.ajax({
            url: 'new_user.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'error') {
                    toastr.error(response.message);
                } else if (response.status === 'success') {
                    toastr.success(response.message);
                    setTimeout(function() {
                        window.location.href = '/vmi/login';
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
