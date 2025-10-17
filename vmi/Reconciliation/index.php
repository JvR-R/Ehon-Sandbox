<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('DB_PATH', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');

// Include files using defined paths
include(DB_PATH);
include(LOG_PATH);
include(BORDER_PATH);

$companyId = $_SESSION['companyId'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>EHON - Reconciliation</title>
    <link rel="stylesheet" href="/vmi/css/normalize.css">
    <link rel="stylesheet" href="/vmi/clients/style.css">
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="recon.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="script.js"></script>
</head>
<body>
  <main class="table" style="overflow: overlay;">
    <section class="table__header">       
      <h1><img src="/vmi/images/company_15100.png" alt="">Reconciliation</h1>            
    </section>
    <section class="table__filters">
      <div class="filter-div" id="filter-div" style="padding: 0.5rem 1rem 0rem 2rem;">
        <!-- Active Filters Badge -->
        <div id="activeFiltersBadge" class="active-filters-badge" style="display: none;">
          <span id="activeFiltersText">0 filters active</span>
        </div>
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sites-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Sites</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="date-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab" aria-controls="messages" aria-selected="false">Date</button>
          </li>
        </ul>       
        <!-- Tab panes -->
        <div class="tab-content">
          <div class="tab-pane active" id="home" role="tabpanel" aria-labelledby="home-tab">
            <div class="mb-3" style="display: flex; align-items: center;">
              <label for="" class="form-label" style="margin-right: 1rem;">Sites</label>
              <div style="flex: 1;">
                <div class="multi-select-actions" style="margin-bottom: 0.5rem;">
                  <button type="button" id="selectAllSites" class="btn-select-action">Select All</button>
                  <button type="button" id="clearAllSites" class="btn-select-action">Clear All</button>
                  <span id="selectedSitesCount" class="selection-count">0 selected</span>
                </div>
                <select multiple class="form-select form-select-lg" name="filter_sites" id="filter_sites" style="min-height: 12rem; z-index: 1080;">
                  <option value="" selected disabled>Select one</option>
                  <?php
                    $sites = $conn->prepare("SELECT DISTINCT Site_name, Site_id FROM Sites as st JOIN Console_Asociation as ca 
                    on (st.uid, st.Client_id) = (ca.uid, ca.Client_id) JOIN console as cs on cs.uid = st.uid
                    WHERE (ca.Client_id = ? or ca.reseller_id = ? or ca.dist_id = ?) and cs.device_type != 999 and st.Site_name IS NOT NULL and st.Site_name != '' Order by Site_name;");
                    $sites->bind_param("iii", $companyId, $companyId, $companyId);
                    $sites->execute();
                    $sites->bind_result($site_name, $site_id);
                    while($sites->fetch()){
                  ?>
                    <option value="<?php echo htmlspecialchars($site_id); ?>"><?php echo htmlspecialchars($site_name); ?></option>
                  <?php
                    } ?>
                </select>
                <span class="tooltip-text">Press Ctrl/Cmd to select multiple sites</span>
              </div>
            </div>
            <div class="mb-3">
              <label for="" class="form-label">Group</label>
              <select class="form-select form-select-lg" name="filter_group" id="filter_group">
                  <option selected disabled>Select one</option>
                  <?php
                  $sites = $conn->prepare("SELECT group_id, group_name FROM `site_groups` where client_id = ?;");
                  $sites->bind_param("i", $companyId);
                  $sites->execute();
                  $sites->bind_result($group_id, $group_name);
                  while($sites->fetch()){
                ?>
                  <option value="<?php echo $group_id; ?>"><?php echo $group_name; ?></option>
                <?php
                  } ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="filter_tank" class="form-label" style="padding: 0.5rem;">Tank </label>
              <input type="number" class="input" placeholder="Number" name="filter_tank" id="filter_tank" aria-describedby="startHelpId" />
            </div>
            <?php if ($companyId == 15100): ?>
            <div class="mb-3">
              <label for="" class="form-label">Company</label>
              <select class="form-select form-select-lg" name="filter_company" id="filter_company">
                  <option selected disabled>Select one</option>
                  <?php
                  $companies = $conn->prepare("SELECT client_id, Client_name FROM clients ORDER BY Client_name;");
                  $companies->execute();
                  $companies->bind_result($client_id, $client_name);
                  while($companies->fetch()){
                ?>
                  <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                <?php
                  } ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <div class="tab-pane" id="messages" role="tabpanel" aria-labelledby="messages-tab">
            <div class="mb-3" style="max-width: 18rem;">
              <label for="start_date" class="form-label">Start Date</label>
              <input type="date" name="start_date" id="start_date" aria-describedby="startHelpId" />
              <small id="startHelpId" class="form-text text-muted">Pick a start date</small>
            </div>
            <div class="mb-3" style="max-width: 18rem;">
              <label for="end_date" class="form-label">End Date</label>
              <input type="date" name="end_date" id="end_date" aria-describedby="endHelpId" />
              <small id="endHelpId" class="form-text text-muted">Pick an end date</small>
            </div>
          </div>         
        </div>
        <div class="button-div" style="text-align: center; padding: 1rem;">
          <button type="button" class="btn btn-primary btn-apply" id="applyFilters">
            <span class="btn-icon">🔍</span> Apply Filters
          </button>
          <button type="button" id="resetFilters" class="btn btn-secondary btn-reset">
            <span class="btn-icon">↻</span> Reset
          </button>
        </div>
      </div>
    </section>
    <section class="table__body" style="display: none;" id="tableSection">
      <!-- Loading Spinner -->
      <div id="loadingSpinner" class="loading-spinner" style="display: none;">
        <div class="spinner"></div>
        <p>Loading data...</p>
      </div>
      <div class="trans" id="trans">
        <!-- Record Count -->
        <div class="record-info">
          <span id="recordCount" class="record-count">Total Records: 0</span>
        </div>
        <table id="customers_table" class="data-table">
          <thead>
            <tr>
              <th>Transaction Date</th>
              <th>Site Name</th>
              <th>Tank ID</th>
              <th>Opening DIP</th>
              <th>Closing DIP</th>
              <th>DIP</th>
              <th>Total Transactions</th>
              <th>Total Deliveries</th>   
              <th>Reconciliation</th>
            </tr>
          </thead>
          <tbody id="bodtest">
            <!-- Data will be loaded here -->
          </tbody>
          <tfoot>
            <tr class="footer-totals">
              <th colspan="3" class="footer-label">Totals:</th>
              <th id="totalOpeningBalance" class="footer-value"></th>
              <th id="totalClosingBalance" class="footer-value"></th>
              <th id="totalVariance" class="footer-value"></th>
              <th id="totalTransactions" class="footer-value"></th>
              <th id="totalDeliveries" class="footer-value"></th>
              <th id="reconciliation" class="footer-value"></th>
            </tr>
          </tfoot>
        </table>
        <div id="pagination" class="pagination-controls">
          <div class="pagination-nav">
            <button id="prevPage" class="btn-secondary btn-nav">← Previous</button>
            <span id="pageIndicator" class="page-indicator"></span>
            <button id="nextPage" class="btn-secondary btn-nav">Next →</button>
          </div>
          <div class="export-buttons">
            <button id="exportToExcel" class="btn btn-primary btn-export">
              <span class="btn-icon">📊</span> Excel
            </button>
            <button id="exportTocsv" class="btn btn-primary btn-export">
              <span class="btn-icon">📄</span> CSV
            </button>
            <button id="exportTopdf" class="btn btn-primary btn-export">
              <span class="btn-icon">📑</span> PDF
            </button>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
