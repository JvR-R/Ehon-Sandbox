<?php
include('../../db/dbh2.php');
include('../../db/log.php');
include('../../db/border.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Company</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Existing CSS -->
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="/vmi/details/menu.css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
  <link href="/vmi/images/favicon.ico" rel="shortcut icon" type="image/x-icon">

  <!-- Toastr CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet" />
</head>
<body>
<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="dashboard-content">
      <div class="sidebar-spacer"></div>
      <div class="sidebar-spacer2"></div>
      <div class="dashboard-main-content">
        <?php include('../../details/top_menu.php');?>

        <form action="key_verification.php" method="post" class="container-default w-container" style="padding-top: 24px; max-width: 960px;" autocomplete="off">
          <!-- Division for input fields -->
          <div class="mg-bottom-24px">
            <div class="card pd-28px">                    
              <h1 class="display-4 mg-bottom-4px">Console Verification</h1>
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
                        <div>Add Console</div>
                    </div>
            </button>
           </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

<!-- Optional: configure Toastr's position, duration, etc. -->
<script>
  toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "timeOut": "8000",
    "positionClass": "toast-top-right"
  };
</script>

<!-- Check status in URL and show toast messages -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get("status");

    if (status === "success") {
        // Show success toast with two button links:
        toastr.success(
            "Console was successfully added.<br><br>" +
            "<button style='margin-right: 8px; color: black; padding: 5px; border-radius: 4px;' onclick=\"window.location.href='new_console.php'\">Add Another</button>" +
            "<button style='color: black; padding: 5px; border-radius: 4px;' onclick=\"window.location.href='new_site.php'\">Create New Site</button>"
        );
    } 
    else if (status === "invalid") {
        toastr.error("Please enter a valid Code");
    } 
    else if (status === "error") {
        toastr.error("An error occurred. Please try again.");
    }
});
</script>

</body>
</html>
