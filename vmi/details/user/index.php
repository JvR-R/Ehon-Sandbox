<?php
    include('../../db/dbh2.php');
    include('../../db/log.php');  
    include('../../db/border.php');
    
    // Check if user has admin access level (1, 4, 6, or 8)
    if (!in_array($accessLevel, [1, 4, 6, 8])) {
        header("Location: /vmi/login/?restricted=1");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="../menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <title>Company Information</title>
    <link rel="stylesheet" href="/vmi/css/theme.css">
</head>
<body>
    <main class="table">
    <?php include('../top_menu.php'); ?>
        <div class="log">
            <form class="form" id="inviteForm">
                <p class="title">Register</p>
                <p class="message">Enter email to send an invitation.</p>
                <label>
                    <input required="" placeholder="" type="email" class="input" name="email" id="email" autocomplete="off">
                    <span>Email</span>
                </label>
                <label>
                    <select name="level" id="level">
                        <option value="2">User</option>
                        <option value="1">Admin</option>
                    </select>                   
                </label>
                <button class="submit" id="send_invite">Send Invitation</button>
                <div id="loading" style="display: none;">Sending invitation...</div>
            </form>
        </div>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
    // Get the PHP session variables
    const accessLevel = "<?php echo $_SESSION['accessLevel']; ?>";
    const companyId = "<?php echo $_SESSION['companyId']; ?>";

    document.getElementById('send_invite').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default form submit behavior
        const email = document.getElementById('email').value;
        const level = document.getElementById('level').value;
        const data = { 
            email, 
            user_type: level,
            accessLevel: accessLevel,
            companyId: companyId
        };
        const sendInviteButton = document.getElementById('send_invite');
        const loadingMessage = document.getElementById('loading');
        console.log(data);
        sendInviteButton.disabled = true; // Disable the button
        loadingMessage.style.display = 'block'; // Show loading message

        fetch('send_invite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                toastr.success('Invitation sent successfully!');
            } else {
                toastr.error('Error sending invitation: ' + data.message);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            toastr.error('Error sending invitation!');
        })
        .finally(() => {
            sendInviteButton.disabled = false; // Re-enable the button
            loadingMessage.style.display = 'none'; // Hide loading message
        });
    });
    </script>
</body>
</html>
