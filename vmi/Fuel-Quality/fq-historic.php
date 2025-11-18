<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

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
    <title>EHON - Fuel Quality</title>
    <link rel="stylesheet" href="/vmi/css/normalize.css">
    <link rel="stylesheet" href="/vmi/clients/style.css">
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/vmi/clients/datatables.min.js"></script>
    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>
  <main class="table" style="overflow: overlay;">
    <section class="table__header">       
      <h1><img src="/vmi/images/company_15100.png" alt="">Fuel Quality</h1>            
    </section>
    <section class="table__filters">
      <div  class="filter__button">
        <div class="d-grid gap-2">
          <button
            type="button"
            name="filter_btn"
            id="filter_btn"
            class="btn btn-secondary"
            onclick="Filters(<?php echo $companyId; ?>)">
            Filters
          </button>
        </div>
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
                  $sites = $conn->prepare("SELECT group_id, group_name FROM `site_groups` where client_id = ?;");
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
          </div>
          <div class="tab-pane" 
            id="messages" 
            role="tabpanel" 
            aria-labelledby="messages-tab">
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
    <section class="table__body" >
      <div class="trans" id="trans">
        <table id="customers_table">
          <thead>
            <tr>
              <th style = "border-top-left-radius: 0.5rem;"></th>
              <th>Console ID</th>
              <th>Date</th>
              <th>Time</th>
              <th>Site Name</th>
              <th>Tank Number</th>
              <th>4um</th>     
              <th>6um</th>   
              <th>14um</th>
              <th>Bubbles</th>
              <th>Cutting</th>
              <th>Sliding</th>
              <th>Fatigue</th>
              <th>Fibre</th>
              <th>Air</th>
              <th>Unknown</th>
              <th>Temp</th>           
              <th style="border-top-right-radius: 0.5rem;">ISO</th>
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

