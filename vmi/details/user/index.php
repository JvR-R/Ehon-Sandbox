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
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Page Header Enhancement */
        .page-header {
            margin-bottom: 40px;
            padding: 24px 32px;
            background-color: var(--bg-card);
            border-radius: 12px;
            border-bottom: 3px solid var(--accent-primary);
            box-shadow: 0 2px 4px var(--shadow-sm);
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }
        
        .page-header p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Select label styling */
        .select-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Enhanced form contrast */
        .form {
            background-color: var(--bg-card);
        }
        
        .form label .input + span {
            background-color: var(--bg-card);
            font-weight: 600;
        }
        
        /* Icon Styling */
        .icon-wrapper {
            position: relative;
        }
        
        .icon-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
            transition: color 0.2s ease;
        }
        
        .form .icon-wrapper .input,
        .form .icon-wrapper select {
            padding-left: 48px !important;
        }
        
        .icon-wrapper:focus-within i {
            color: var(--accent-primary);
        }
        
        .select-icon-wrapper {
            position: relative;
        }
        
        .select-icon-wrapper i {
            position: absolute;
            left: 16px;
            bottom: 14px;
            color: var(--text-secondary);
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
            transition: color 0.2s ease;
        }
        
        .select-icon-wrapper:focus-within i {
            color: var(--accent-primary);
        }
        
        .select-icon-wrapper select {
            padding-left: 48px;
        }
        
        .page-header i {
            color: var(--accent-primary);
            margin-right: 12px;
            font-size: 28px;
            vertical-align: middle;
        }
        
        .form .title i {
            color: var(--accent-primary);
            margin-right: 10px;
            font-size: 24px;
            vertical-align: middle;
        }
        
        .submit i {
            margin-right: 8px;
            font-size: 16px;
            vertical-align: middle;
        }
        
        #loading i {
            margin-right: 8px;
            color: var(--accent-primary);
        }
    </style>
</head>
<body>
    <main class="table">
    <?php include('../top_menu.php'); ?>
        
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> User Invitation</h1>
            <p>Send invitations to new users to join your organization</p>
        </div>
        
        <div class="log">
            <form class="form" id="inviteForm">
                <p class="title"><i class="fas fa-user-edit"></i> Register New User</p>
                <p class="message">Enter the email address and access level for the new user</p>
                
                <label class="icon-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input required="" placeholder="" type="email" class="input" name="email" id="email" autocomplete="off">
                    <span>Email Address</span>
                </label>
                
                <div class="select-icon-wrapper">
                    <i class="fas fa-shield-alt"></i>
                    <label class="select-label">Access Level</label>
                    <select name="level" id="level">
                        <option value="2">User</option>
                        <option value="1">Admin</option>
                    </select>
                </div>
                
                <button class="submit" id="send_invite"><i class="fas fa-paper-plane"></i> Send Invitation</button>
                <div id="loading" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Sending invitation...</div>
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
