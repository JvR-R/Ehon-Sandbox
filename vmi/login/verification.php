<?php
include('../db/dbh2.php');
include('../db/border_nopriv.php');
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
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="/vmi/images/favicon.ico" rel="shortcut icon" type="image/x-icon">
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
                <form action="key_verification" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
                    <!-- Division for input fields -->
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">                    
                            <h1 class="display-4 mg-bottom-4px">User Verification</h1>
                            <div class="grid-2-columns _1-82fr---1fr gap-0" style="grid-template-columns: 0.82fr 2fr; align-items: center;">
                                <label style="margin-bottom: 15px">Security code:</label>
                                <input class="input" type="text" style="margin-bottom: 3px" placeholder="Enter the provided code" name="Client_key" required>                               
                            </div>                          
                            <br>
                        </div>
                    </div>
                    <div id="w-node-_2a4873d0-6574-1dad-be43-8662a1f2809d-6534f24f" class="buttons-row">
                    <button type="submit" class="btn-primary w-inline-block">
                        <div class="flex-horizontal gap-column-6px">
                                <div>Create Company</div>
                            </div>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
