<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__)));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('db', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');
// Include files using defined paths
include(db);
include(LOG_PATH);
include(BORDER_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Vendor Managed Inventory</title>
      <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
  <script src="/vmi/js/theme-init.js"></script>
  <!-- THEME CSS - MUST BE FIRST -->
  <link rel="stylesheet" href="/vmi/css/theme.css">
  <!-- Other CSS files -->
  <link rel="stylesheet" href="/vmi/css/normalize.css">
  <!-- Bootstrap CSS - loaded early so theme overrides take precedence -->
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">
  <!-- VMI shared styles -->
  <link rel="stylesheet" href="/vmi/css/vmi-tables.css">
  <link rel="stylesheet" href="/vmi/css/style_rep.css">
  <!-- Page-specific styles (must be last to override) -->
  <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/vmi/clients/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>
  <main class="table" style="overflow: overlay;">
    <section class="table__header">       
      <h1><img src="/vmi/images/company_15100.png" alt="">Vendor Managed Inventory</h1>            
    </section>
    <section class="table__filters">
      <div class="filter__button">
        <button
          type="button"
          name="filter_btn"
          id="filter_btn"
          class="btn btn-secondary"
          onclick="Filters(<?php echo $companyId; ?>)">
          Filters
        </button>
      </div>
      <div class="filter__div" id="filter__div">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button
              class="nav-link active"
              id="sites-tab"
              data-bs-toggle="tab"
              data-bs-target="#home"
              type="button"
              role="tab"
              aria-controls="home"
              aria-selected="true"
            >
              Sites
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link"
              id="details-tab"
              data-bs-toggle="tab"
              data-bs-target="#profile"
              type="button"
              role="tab"
              aria-controls="profile"
              aria-selected="false"
            >
              Details
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link"
              id="date-tab"
              data-bs-toggle="tab"
              data-bs-target="#messages"
              type="button"
              role="tab"
              aria-controls="messages"
              aria-selected="false"
            >
              Date
            </button>
          </li>
        </ul>       
        <!-- Tab panes -->
        <div class="tab-content">
          <div class="tab-pane active"
            id="home"
            role="tabpanel"
            aria-labelledby="home-tab">
            <div class="mb-3" 
              style="display: flex; align-items: center;">
              <label for="" class="form-label"
                style="margin-right: 1rem;">
                Sites</label>
              <select multiple
                class="form-select form-select-lg"
                name="filter_sites"
                id="filter_sites"
                style="min-height: 12rem; z-index: 1080;">
                <option selected disabled>Select one</option>
                <?php
                  $sites = $conn->prepare("SELECT Site_name, Site_id FROM Sites as st JOIN Console_Asociation as ca 
                  on (st.uid, st.Client_id) = (ca.uid, ca.Client_id) JOIN console as cs on cs.uid = st.uid
                  WHERE (ca.Client_id = ? or ca.reseller_id = ? or ca.dist_id = ?) and cs.device_type != 999 Order by Site_name;");
                  $sites->bind_param("iii", $companyId, $companyId, $companyId);
                  $sites->execute();
                  $sites->bind_result($site_name, $site_id);
                  while($sites->fetch()){
                ?>
                  <option value=<?php echo $site_id ?>><?php echo $site_name ?></option>
                  <?php
                  } ?>
              </select>
              <span class="tooltip">Press Control to select multiple sites</span>
            </div>
            <div class="mb-3">
              <label for="" class="form-label">Group</label>
              <select class="form-select form-select-lg"
                name="filter_group"
                id="filter_group">
                  <option selected disabled>Select one</option>
                  <?php
                  $sites = $conn->prepare("SELECT group_id, group_name FROM `site_groups` where client_id = ? ORDER BY group_name;");
                  $sites->bind_param("i", $companyId);
                  $sites->execute();
                  $sites->bind_result($group_id, $group_name);
                  while($sites->fetch()){
                ?>
                  <option value=<?php echo $group_id ?>><?php echo $group_name ?></option>
                  <?php
                  } ?>
              </select>
            </div>
            <?php if ($companyId == 15100) { ?>
            <div class="mb-3">
                <label for="" class="form-label">Company</label>
                <select class="form-select form-select-lg"
                    name="filter_company"
                    id="filter_company">
                    <option selected disabled>Select Company</option>
                    <?php
                    // Prepare and execute your SQL query to fetch options for the new filter
                    $newFilterStmt = $conn->prepare("SELECT distinct(client_id), Client_name FROM clients WHERE client_id in (SELECT Client_id FROM console_asociation WHERE uid in (SELECT uid FROM console where device_type != ?))");
                    $nonexist = 999;
                    $newFilterStmt->bind_param("i", $nonexist);
                    $newFilterStmt->execute();
                    $newFilterStmt->bind_result($id, $name);
                    while($newFilterStmt->fetch()){
                    ?>
                    <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                    <?php
                    }
                    $newFilterStmt->close();
                    ?>
                </select>
            </div>
            <?php } ?>
          </div>
          <div class="tab-pane"
            id="profile"
            role="tabpanel"
            aria-labelledby="profile-tab">
            <label for="" class="form-label">Card Holder Name</label>
            <input type="text"
              name="filter_cardholder"
              id="filter_cardholder"
              class="form-control"
              aria-describedby="filter_cardholder"/>
            <label for="" class="form-label">Card Number</label>
            <input type="text"
              name="filter_cardnumber"
              id="filter_cardnumber"
              class="form-control"
              aria-describedby="filter_cardnumber"/>
            <label for="" class="form-label">Registration</label>
            <input type="text"
              name="filter_registration"
              id="filter_registration"
              class="form-control"
              aria-describedby="filter_registration"/>
          </div>
          <div class="tab-pane" 
            id="messages" 
            role="tabpanel" 
            aria-labelledby="messages-tab">
            <div class="quick-date-filters mb-3">
              <span class="quick-filter-label">Quick:</span>
              <button type="button" class="quick-filter-btn" data-range="today">Today</button>
              <button type="button" class="quick-filter-btn" data-range="1day">1 Day</button>
              <button type="button" class="quick-filter-btn" data-range="1week">1 Week</button>
              <button type="button" class="quick-filter-btn" data-range="1month">1 Month</button>
              <button type="button" class="quick-filter-btn quick-filter-clear" data-range="clear">Clear</button>
            </div>
            <div class="mb-3" style="max-width: 18rem;">
              <label for="start_date" class="form-label">Start Date</label>
              <input
                type="date"
                name="start_date"
                id="start_date"
                aria-describedby="startHelpId"
              />
              <small id="startHelpId" class="form-text text-muted">Pick a start date</small>
            </div>
            <div class="mb-3" style="max-width: 18rem;">
              <label for="end_date" class="form-label">End Date</label>
              <input
                type="date"
                name="end_date"
                id="end_date"
                aria-describedby="endHelpId"
              />
              <small id="endHelpId" class="form-text text-muted">Pick an end date</small>
            </div>
          </div>         
        </div>
        <div class="button-div"
          style="text-align: center; padding: 1rem;">
          <button type="button" class="btn btn-primary">Apply</button>
          <button type="button" id="resetFilters" class="btn btn-secondary">Reset</button>
        </div>
      </div>
    </section>
    <section class="table__body">
      <div class="trans" id="trans">
        <table id="customers_table">
          <thead>
            <tr>
              <th style="border-top-left-radius: 0.5rem;"></th>
              <th>Transaction ID</th>
              <th>Date</th>
              <th>Time</th>
              <th>Console ID</th>
              <th>Site Name</th>
              <th>FMS ID</th>
              <th>Tank Number</th>
              <th>Pump Number</th>
              <th>Card Number</th>
              <th>Card Holder Name</th>
              <th>Odometer</th>
              <th>Registration</th>
              <th style="border-top-right-radius: 0.5rem;">Volume</th>                     
            </tr>
          </thead>
          <tbody id="bodtest">
            <!-- Data will be loaded here -->
          </tbody>
        </table>
        <div id="pagination" style="padding:0rem 1rem; margin-bottom: 0.5rem;">
            <button id="prevPage" class="btn-secondary">Previous</button>
            <span id="pageIndicator"></span>
            <button id="nextPage" class="btn-secondary">Next</button>
            <button id="exportToExcel" class="btn btn-primary">Excel</button>
            <button id="exportTocsv" class="btn btn-primary">CSV</button>
            <button id="exportTopdf" class="btn btn-primary">PDF</button>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
<script>
    var companyId = <?php echo $companyId; ?>;
</script>
<script src="script.js"></script>
