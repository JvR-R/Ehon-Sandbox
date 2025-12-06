<?php
include('../db/dbh2.php');
include('../db/log.php');   
include('../db/border.php'); 

function safe_html($string) {
   return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf" content="<?= htmlspecialchars($_SESSION['csrf'] ?? bin2hex(random_bytes(16)), ENT_QUOTES) ?>">
  <title>EHON Energy Tech - VMI</title>
  <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
  <script src="/vmi/js/theme-init.js"></script>
  <!-- THEME CSS - MUST BE FIRST -->
  <link rel="stylesheet" href="/vmi/css/theme.css">
  <!-- Other CSS files -->
  <link rel="stylesheet" href="/vmi/css/normalize.css">
  <!-- Unified VMI Tables & Components -->
  <link rel="stylesheet" href="/vmi/css/vmi-tables.css">
  <!-- Tank Card View Styles -->
  <link rel="stylesheet" href="/vmi/css/tank-card-view.css">
  <!-- Tank Modal Styles -->
  <link rel="stylesheet" href="/vmi/css/tank-modal.css">
  <!-- Page-specific styles -->
  <link rel="stylesheet" href="style.css"> 
  <link rel="stylesheet" href="/vmi/css/style_rep.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/2.0.3/css/dataTables.dataTables.min.css">
  <script defer src="https://cdn.datatables.net/2.0.3/js/dataTables.min.js"></script>
</head>
<body>
  <div id="hidden-data"
     data-company-id="<?= $companyId ?>"
     data-access-level="<?= $accessLevel ?>"></div>
  <main class="table">
    <section class="table__header">       
    <h1>
      <img src="/vmi/images/company_<?php echo $companyId; ?>.png" onerror="this.onerror=null;this.src='/vmi/images/company_nologo.png';" alt="" style="max-height: 4.5rem">
      Vendor Managed Inventory
    </h1>
    <div class="alarms-div">
      <label for="alarms-div" class="">
          <img src="/vmi/images/flag_dv_icon.png" alt="">
      </label>
      <?php
          $selflag = "SELECT count(dv_flag) as flag FROM console as cs JOIN Console_Asociation as ca on ca.uid = cs.uid 
                      where dv_flag = 1 and (ca.Client_id = ? or ca.dist_id = ? or ca.reseller_id = ?)";
          $stmtflag = $conn->prepare($selflag);
          $stmtflag->bind_param("iii", $companyId, $companyId, $companyId);
          $stmtflag->execute();
          $stmtflag->bind_result($total_alarm);
          $stmtflag->fetch();
          $stmtflag->close();
      ?>
      <a href="/vmi/clients/alerts_viewer" class="alarm_count">
        <span><?php echo $total_alarm; ?></span>
      </a>
    </div>

      <!-- <div class="export__file">
        <label for="export-file" class="export__file-btn" title="Export File"></label>
        <input type="checkbox" id="export-file">
        <div class="export__file-options">
          <label>Export As &nbsp; &#10140;</label>
          <label for="export-file" id="toCSV">CSV <img src="/vmi/images/csv.png" alt=""></label>
        </div>
      </div> -->
    </section>
    <section class="table__body">
      <div class="filter" style="position: relative; display: flex; left: 30px; top:15px;">
        <?php
        $sel = "SELECT group_id, group_name FROM site_groups where client_id = ? ORDER BY group_name";
        $stmt = $conn->prepare($sel);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $stmt->store_result();
        echo '<select name="group_filter" id="group_filter" class="group_filter">';
          echo '<option value="">Select Group</option>'; 
          echo '<option value="def">Show All</option>';// Default option
          if($stmt->num_rows > 0) {
              // Bind the columns to variables
              $stmt->bind_result($group_id,$group_name);
              while($stmt->fetch()) {
                  echo '<option value="' . $group_id . '">' . $group_name . '</option>';
              }
          }
        echo '</select>';
        ?>  
      </div>
      <div class="test" id ="test">
        <table id="customers_table">
          <thead>
            <tr>
              <th style = "border-top-left-radius: 0.5rem;"></th>
              <th class="hide-on-mobile"> Company Name</th>
              <th> Date</th>
              <th class="hide-on-mobile"> Time</th>
              <th> Site Name</th>
              <!-- <th class="hide-on-mobile"> Tank Name</th> -->
              <th> Tank Number</th>
              <th class="hide-on-mobile"> Product</th>
              <th> Capacity</th>
              <th> Current Volume</th>
              <th class="hide-on-mobile"> State</th>
              <th> Ullage</th>
              <th style="border-top-right-radius: 0.5rem;"> % </th>
              <!-- <th> Order</th> -->
            </tr>
          </thead>
          <tbody id="bodtest">
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
<script type="module" src="/vmi/js/vmi-js/main.js"></script>