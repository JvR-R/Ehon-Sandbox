<?php
    include('../db/dbh2.php');
    include('../db/log.php'); 
    include('../db/border.php'); 
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Login</title>
      <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
  <script src="/vmi/js/theme-init.js"></script>
  <!-- THEME CSS - MUST BE FIRST -->
  <link rel="stylesheet" href="/vmi/css/theme.css">
  <!-- Other CSS files -->
<link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <body>
    <main class="table">
        <div class="log">
            <form class="form" action="login" method="POST">
                <p class="title">Update Password</p>
                <p class="message">Contact Ehon support for assistance</p>
                <label>
                    <input required="" placeholder="" type="email" class="input" name="username">
                    <span>Email</span>
                </label>
                <label>
                    <input required="" placeholder="" type="password" class="input" name="password">
                    <span>Current Password</span>
                </label>
                <label>
                    <input required="" placeholder="" type="password" class="input" name="newpassword">
                    <span>New Password</span>
                </label>
                <button class="submit">Submit</button>
            </form>
        </div>
    </main>
</body>
</html>