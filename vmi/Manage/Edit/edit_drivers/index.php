<?php
// Define the root path based on the server's document root or a fixed path
define('ROOT_PATH', dirname(dirname(__DIR__, 2)));  // Goes up one directory from the current directory

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
    <title>Edit Drivers</title>
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link rel="stylesheet" href="../edit_common.css">
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="modern_style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Override Bootstrap _borders.scss and _dropdown.scss - must load after Bootstrap */
        .border {
            border: none !important;
            border-color: transparent !important;
        }
        
        .dropdown-item.active,
        .dropdown-item:active {
            color: var(--text-primary) !important;
            text-decoration: none !important;
            background-color: rgba(108, 114, 255, 0.15) !important;
        }
        
        .dropdown-item:hover {
            color: var(--accent-primary) !important;
            background-color: rgba(108, 114, 255, 0.1) !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/vmi/clients/datatables.min.js"></script>
    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>
<?php include('../../../details/top_menu.php');?>
  <main class="table">
    <section class="table__header">       
      <h1>Edit Drivers</h1>            
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
                  Customer</label>
                <select multiple
                  class="form-select form-select-lg"
                  name="filter_sites"
                  id="filter_sites"
                  style="min-height: 12rem; z-index: 1080;">
                  <option selected disabled>Select one</option>
                  <?php
                    $sites = $conn->prepare("SELECT customer_name, customer_id FROM Customers where client_id = ?;");
                    $sites->bind_param("i", $companyId);
                    $sites->execute();
                    $sites->bind_result($site_name, $site_id);
                    while($sites->fetch()){
                  ?>
                    <option value=<?php echo $site_id ?>><?php echo $site_name ?></option>
                    <?php
                    } ?>
                </select>
                <span class="tooltip">Press Control to select multiple Customers</span>
              </div>
          </div>
          <div class="tab-pane"
            id="profile"
            role="tabpanel"
            aria-labelledby="profile-tab">
            <label for="" class="form-label">Card Holder Name</label>
            <input type="text"
              name="filter_cardholder"
              id="filter_cardholder"
              style= "background: #011a37; color: white; border-radius: 0.5rem;"
              aria-describedby="filter_cardholder"/>
            <label for="" class="form-label">Card Number</label>
            <input type="text"
              name="filter_cardnumber"
              id="filter_cardnumber"
              style= "background: #011a37; color: white; border-radius: 0.5rem;"
              aria-describedby="filter_cardnumber"/>
            <label for="" class="form-label">Registration</label>
            <input type="text"
              name="filter_registration"
              id="filter_registration"
              style= "background: #011a37; color: white; border-radius: 0.5rem;"
              aria-describedby="filter_registration"/>
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
              <th style = "border-top-left-radius: 0.5rem;">Driver ID</th>
              <th>Name</th>
              <th>Last Name</th>
              <th>Customer</th>
              <th>Phone</th>
              <th>License Date</th>
              <th>Last Modification</th>
              <th style="border-top-right-radius: 0.5rem;">Actions</th>
            </tr>
          </thead>
          <tbody id="bodtest">
            <!-- Data will be loaded here -->
          </tbody>
        </table>
        <div id="pagination">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button id="prevPage" class="btn-secondary">Previous</button>
                <span id="pageIndicator"></span>
                <button id="nextPage" class="btn-secondary">Next</button>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button id="exportToExcel" class="btn btn-primary">Excel</button>
                <button id="exportTocsv" class="btn btn-primary">CSV</button>
                <button id="exportTopdf" class="btn btn-primary">PDF</button>
            </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />

<script>
    $(document).ready(function() {
        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        
        // Display success notification
        if (urlParams.has('success') && urlParams.get('success') === 'true') {
            toastr.success('Data updated successfully!');
        }

        // Display error notifications based on error type
        if (urlParams.has('error')) {
            const error = urlParams.get('error');
            switch (error) {
                case 'validation_failed':
                    toastr.error('Validation failed. Please check your inputs.');
                    break;
                case 'prepare_failed':
                    toastr.error('Database error while preparing the statement.');
                    break;
                case 'execute_failed':
                    toastr.error('Failed to update data in the database.');
                    break;
                case 'invalid_method':
                    toastr.error('Invalid request method.');
                    break;
                default:
                    toastr.error('An unexpected error occurred.');
                    break;
            }
        }
    });
</script>
