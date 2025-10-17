<?php
    include('../db/dbh2.php');
    include('../db/border_nopriv.php');
   
?>
<!DOCTYPE html>
<html>
<head>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Recover - Ehon Energy Tech</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="style.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

</head>
</head>
<body>
    <?php
     if (isset($_GET['token'])) {
        $token = $_GET['token'];
        // Now you can use the $token variable in your script
        $query = "SELECT username, token_expiry FROM login WHERE token = ?";

        // Prepare the statement
        $tokench = $conn->prepare($query);

        // Bind the parameter
        $tokench->bind_param('s', $token);

        // Execute the statement
        $tokench->execute();

        // Store the result to get properties like num_rows
        $tokench->store_result();

        // Check if any rows are returned
        if ($tokench->num_rows == 1) {
            $tokench->bind_result($username, $tokenExpiry);

            // Fetch the result of the query
            $tokench->fetch();
            $tokenExpiryDate = new DateTime($tokenExpiry);

            // Get the current DateTime
            $currentDate = new DateTime();
            
            // Calculate the difference between the current date and the expiry date
            $interval = $currentDate->diff($tokenExpiryDate);
        
            // Convert the date interval to total minutes
            $minutes = $interval->days * 24 * 60; // Convert days to minutes
            $minutes += $interval->h * 60; // Add hours to minutes
            $minutes += $interval->i; // Add interval minutes

            // Check if the difference is less than 30 minutes
            if ($minutes < 30) {
                ?>
                <div style="opacity:1" class="page-wrapper">
                    <div class="dashboard-main-section">
                        <div class="dashboard-content">
                            <div class="log" style="background:#eaeefc">
                                <main class="table">
                                    <div class="dashboard-content">
                                        <div class="password-recovery-form">
                                            <form class="form">
                                                <p class="title">Login Info</p>
                                                <p class="message">Contact Ehon support for assistance</p>
                                                <label>
                                                <input required="" placeholder="" type="text" class="inputlog" name="username" id="username" autocomplete="username" value="<?php echo htmlspecialchars($username); ?>" readonly onfocus="this.blur();" style="min-width:14rem; background-color: #f3f4f6; cursor: not-allowed;">
                                                </label>    
                                                <label>
                                                    <input required="" placeholder="" type="password" class="inputlog" name="password" id="password" style="min-width:14rem;">
                                                    <span>Password</span>
                                                </label>       
                                                <label>
                                                    <input required="" placeholder="" type="password" class="inputlog" name="password_conf" id="password_conf" style="min-width:14rem;">
                                                    <span>Confirm Password</span>
                                                </label>  
                                                <span>
                                                    <br>
                                                    <input type="submit" value="Send" class="submit" style="margin-bottom:1rem;">
                                                    <p class="signin">Login <a href="/vmi/login/">Login</a></p>
                                                </span>
                                            </form>
                                        </div>
                                    </div>
                                </main>
                            </div>
                        </div>
                    </div>
                </div>
           
            <?php
             } else {
                ?>
                <script type="text/javascript">
                    alert("Token Expired");
                    window.location.href = '/vmi/login'; // Redirect to the homepage
                </script>
                <?php
             }
        } else {
            ?>
            <script type="text/javascript">
                alert("Wrong Token");
                window.location.href = '/vmi/login'; // Redirect to the homepage
            </script>
            <?php
        }
        $tokench->close();
    } else {
        echo "Token not provided!";
    }
    ?>
</body>
</html>
<script>
$(document).ready(function() {
    // Handler for form submission
    $('.form').on('submit', function(e) {
        // Prevent the default form submission
        e.preventDefault();

        // Get the values from the form
        var username = $('#username').val();
        var password = $('#password').val();
        var passwordConf = $('#password_conf').val();
        var token = '<?php echo htmlspecialchars($token); ?>';

        // Check if passwords match
        if (password !== passwordConf) {
            toastr.error('Passwords do not match.');
            return; // Stop the function if passwords do not match
        }

        // If passwords match, proceed with AJAX POST
        $.ajax({
            url: 'password_recovery.php',
            type: 'POST',
            data: {
                username: username,
                password: password,
                token: token
            },
            success: function(response) {
                // Assuming the server responds with a message for success or redirect
                toastr.success('Password updated successfully');
                // Optionally redirect or update UI
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
            }
        });
    });
});
</script>
