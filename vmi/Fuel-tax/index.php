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

// Assuming $companyId is already defined somewhere before using it in the query
$companyId = $_SESSION['companyId'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>EHON - BAS Tax</title>
      <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
  <script src="/vmi/js/theme-init.js"></script>
  <!-- THEME CSS - MUST BE FIRST -->
  <link rel="stylesheet" href="/vmi/css/theme.css">
  <!-- Other CSS files -->
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
      <h1><img src="/vmi/images/company_15100.png" alt="">BAS Tax</h1>            
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
              class="nav-link"
              id="details-tab"
              data-bs-toggle="tab"
              data-bs-target="#home"
              type="button"
              role="tab"
              aria-controls="home"
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
            aria-labelledby="profile-tab">
            <!-- <label for="" class="form-label">Card Holder Name</label>
            <input type="text"
              name="filter_cardholder"
              id="filter_cardholder"
              style= "background: #011a37; color: white; border-radius: 0.5rem;"
              aria-describedby="filter_cardholder"/> -->
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
    <section class="table__body" style="display: none;" id="tableSection">
      <div class="trans" id="trans">
        <table id="customers_table" class="data-table">
          <thead>
            <tr>
              <th>Card Number</th>
              <th>Registration</th>
              <th>Volume</th>
              <th>Tax Value</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody id="bodtest">
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td></td>
              <td id="totalVolume">0</td>
              <td></td>
              <td id="totalSum">0</td>
            </tr>
          </tfoot>
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
