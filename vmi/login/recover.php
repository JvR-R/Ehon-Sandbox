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
    <script src="script.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

</head>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
            <div class="log" style="background:#eaeefc">
                <main class="table">
                    <div class="dashboard-content">
                        <div class="password-recovery-form">
                            <form class="form" id="recoveryForm">
                                <p class="title">Login Info</p>
                                <p class="message">Contact Ehon support for assistance</p>
                                <label>
                                    <input required="" placeholder="" type="text" class="inputlog" name="username" id="username" autocomplete="username" style="min-width:14rem;">
                                    <span>Username</span>
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
</body>
</html>
<script>
$(document).ready(function() {
    $('#recoveryForm').on('submit', function(e) {
        e.preventDefault();  // Prevent default form submission
        var username = $('#username').val();  // Get the username from the input
        var $submitBtn = $(this).find('input[type="submit"]');  // Find the submit button within the form

        $submitBtn.prop('disabled', true).val('Sending...');  // Disable the button and change the button text

        // Perform AJAX POST request
        $.ajax({
            url: 'login_recovery.php',  // Endpoint for the PHP script
            type: 'POST',
            data: {username: username},  // Data sent to the server
            dataType: 'json',  // Expect JSON response
            success: function(response) {
                if (response.status === 'success') {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr, status, error) {
                // Handle errors
                console.log(xhr.responseText);  // Log the response for debugging
                var errorMessage = 'Failed to send email. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastr.error(errorMessage);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).val('Send');  // Re-enable the button and reset the button text
            }
        });
    });
});

</script>
