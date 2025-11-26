<?php
include('../../db/dbh.php');
include('../../db/log.php');
include('../borderipay.php');

// Check if the user is logged in
if (isset($_SESSION['loggedin'])) {
    $accessLevel = $_SESSION['accessLevel'];
    if ($accessLevel > 3) {
        echo "Contact the Administrator.";
    } else {
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Upload Excel File</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastr@latest/toastr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastr@latest/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="style.css">

    <script>
    $(document).ready(function() {
        // (Optional) Check for success or error messages
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            var status = urlParams.get('status');
            var message = urlParams.get('message');

            if (status === 'success') {
                toastr.success(message || 'File processed successfully!');
            } else if (status === 'error') {
                toastr.error(message || 'An error occurred while processing the file.');
            }
        }

        // --- AJAX call to check refresh_act_time ---
        $.ajax({
            url: 'get_active_token.php', // The PHP file that returns refresh_act_time
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.refresh_act_time) {
                    // Convert "YYYY-MM-DD HH:MM:SS" into a Date object
                    var refreshTime = new Date(response.refresh_act_time.replace(/-/g, '/'));
                    var now = new Date();
                    var diffMs = now - refreshTime; // difference in milliseconds
                    var diffDays = diffMs / (1000 * 60 * 60 * 24);

                    // If refresh_act_time is less than 6 days ago, disable button
                    if (diffDays < 6) {
                        $('.refresh_token button').prop('disabled', true);
                    }
                }
            },
            error: function() {
                console.error("Could not retrieve refresh_act_time from server.");
            }
        });
    });
    </script>
    <style>
        /* Gray out disabled buttons */
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
</head>
<body>
    <main class="table">
         <?php
            $stmt = $conn->prepare("SELECT * FROM missing_transaction");
            $stmt->execute();
            
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                echo '<div class="missingt-cont">';
                    echo '<div class="missingt-cont">';
                        $stmt->bind_result($transaction_number, $transaction_date, $total_price);
                        echo "<ul class='missing-transactions'>";
                        while ($stmt->fetch()) {
                            echo "<li>#".htmlspecialchars($transaction_number). "  /   " .htmlspecialchars($transaction_date). "       " .htmlspecialchars($total_price)."</li>"; 
                        }
                        echo "</ul>";
                    echo '</div>';
                echo '</div>';
            }
            
            $stmt->close();
        ?>
        <div class="uploadfiles">
            <form class="formc" action="update.php" method="post" enctype="multipart/form-data">
                <input type="file" name="file" />
                <input class="buttonupd" type="submit" value="Upload" />
            </form>
        </div>

        <div class="refresh_token">
            <button class="buttonupd"
                onclick="window.location.href='https://9250724.app.netsuite.com/app/login/oauth2/authorize.nl?scope=restlets+rest_webservices&redirect_uri=https%3A%2F%2Fehonenergy.com.au%2Fvmi%2Fipetropay%2Fpayment%2Fnetsuite_callback&response_type=code&client_id=46a1a15630b197588ae747634a0d70b826edbc10e6738adede0d03a2cc931302&state=ykv2XLx1BpT5Q0F3MRPHb94j&code_challenge=7_S8pn8hD9yYKAKNDOz-xrxe4mu_n8uzDjWn_JJzWxk&code_challenge_method=S256'">
                Refresh Token
            </button>
        </div>
    </main>
</body>
</html>
<?php
    }
} else {
    header("Location: /login/");
    exit;
}
?>
