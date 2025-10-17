let currentfilters = {};

function Filters(companyId) {
    var element = document.getElementById('filter__div');
    if (element.style.display === "none" || element.style.display === "") {
        element.style.display = "block";
    } else {
        element.style.display = "none";
    }
    console.log(companyId);
}

$(document).ready(function () {
    var currentPage = 1; // Start from the first page

    var table = $('#customers_table').DataTable({
        paging: false,
        order: [[2, 'desc'], [3, 'desc']],
        columnDefs: [
          {
            targets: 0,  // First column for our expand button
            className: 'dt-control',
            orderable: false,
            data: null,
            defaultContent: ''
          }
          // You can add more column definitions if needed.
        ]
      });

      function loadTransactions(page, filters) {
        $.ajax({
          url: 'get_transactions', // Adjust to your path
          type: 'GET',
          dataType: 'json', // Expect JSON from the server
          data: {
            page: page,
            filters: filters 
          },
          success: function (data) {
            // Clear existing rows
            table.clear();
      
            // For each transaction, add a new row and attach the transaction object to it.
            data.forEach(function (transaction) {
              // Add the row to the DataTable. The first column is left empty because it
              // will be replaced by our dt-control expand button.
              var newRow = table.row.add([
                '', // dt-control column (button is inserted automatically)
                transaction.transaction_id,
                transaction.transaction_date,
                transaction.transaction_time,
                transaction.uid,
                transaction.site_name,
                transaction.fms_id,
                transaction.tank_id,
                transaction.pump_id,
                transaction.card_number,
                transaction.card_holder_name,
                transaction.odometer,
                transaction.registration,
                transaction.dispensed_volume
                // Adjust the number of columns to match your table structure.
              ]);
              
              // Attach the entire transaction object to this row's DOM element.
              // This lets you access any property (like stop_method) later.
              $(newRow.node()).data('transaction', transaction);
            });
      
            table.draw(); // Redraw the table with new data
            updatePageIndicator(page);
          },
          error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error loading transactions:', textStatus, errorThrown);
          }
        });
      }
      
    
      // Initial load of transactions
      loadTransactions(currentPage);
    
      // Pagination event listeners
      $('#nextPage').on('click', function () {
        currentPage++;
        loadTransactions(currentPage, currentfilters);
      });
    
      $('#prevPage').on('click', function () {
        if (currentPage > 1) {
          currentPage--;
          loadTransactions(currentPage, currentfilters);
        }
      });
    
      // Update page indicator
      function updatePageIndicator(page) {
        $('#pageIndicator').text('Page ' + page);
      }
    
      // ========================
      // dt-control (Expand/Collapse) Code:
      $('#customers_table tbody').on('click', 'td.dt-control', function () {
        var tr = $(this).closest('tr');  // Get the parent row
        var row = table.row(tr);           // Get the DataTable row object
      
        if (row.child.isShown()) {
          // If already open, close the child row.
          row.child.hide();
          tr.removeClass('shown');
        } else {
          // Retrieve the transaction object attached to this row.
          var transaction = $(tr).data('transaction');
          var pulses = transaction.pulses ?? 0;
          var startDateTime = transaction.startDateTime ?? 'Unknown';
          var endDateTime = transaction.endDateTime ?? 'Unknown';
          var startDip = transaction.startDip ?? 'Unknown';
          var endDip = transaction.endDip ?? 'Unknown';
          // Build the child row HTML that displays the transaction's stop_method.
          var childContentHtml = `
            <div class="child-content" style="
              width: 100%;
              min-height: 10rem;
              padding: 1.5rem;
              background: white;
              border: 1px solid #e3e3e3;
              border-radius: 8px;
              box-shadow: 0 2px 4px rgba(0,0,0,0.05);
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
              color: #333;
              line-height: 1.6;
              margin: 1rem 0;
            ">
              <p><strong>Pulses:</strong> ${pulses}</p>
              <p><strong>Stop Method:</strong> ${transaction.description}</p>
              <p><strong>Start DateTime:</strong> ${startDateTime}</p>
              <p><strong>End DateTime:</strong> ${endDateTime}</p>
              <p><strong>Start Dip:</strong> ${startDip}</p>
              <p><strong>End Dip:</strong> ${endDip}</p>
              <!-- Add any other details you want to display -->
            </div>
          `;
      
          // Open the child row with the transaction details.
          row.child(childContentHtml).show();
          tr.addClass('shown');
        }
      });
      
      // ========================
      // End dt-control code.
    

    // Add an event listener to the "Apply" button
    document.querySelector('.button-div .btn-primary').addEventListener('click', function() {
        currentfilters = {
            sites: $('#filter_sites').val(),
            group: $('#filter_group').val(),
            cardholder: $('#filter_cardholder').val(),
            cardnumber: $('#filter_cardnumber').val(),
            registration: $('#filter_registration').val(),
            startDate: $('#start_date').val(),
            endDate: $('#end_date').val()
        };

        // Include the new filter if companyId equals 15100
        if (companyId == 15100 && document.getElementById('filter_company')) {
            currentfilters.company = $('#filter_company').val();
        }

        // Reset the current page to 1 when applying filters
        currentPage = 1;

        loadTransactions(currentPage, currentfilters);
    });
    

    document.getElementById('exportToExcel').addEventListener('click', function() {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_excel.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });

    document.getElementById('exportTocsv').addEventListener('click', function() {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_csv.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });

    document.getElementById('exportTopdf').addEventListener('click', function() {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_pdf.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });
});

document.addEventListener("DOMContentLoaded", function() {
    // This function hides all tab panes
    function hideAllTabPanes() {
        var tabPanes = document.querySelectorAll('.tab-pane');
        tabPanes.forEach(function(pane) {
            pane.classList.remove('active');
        });
    }

    // This function shows the selected tab pane
    function showTabPane(paneId) {
        var pane = document.querySelector(paneId);
        if (pane) {
            pane.classList.add('active');
        }
    }

    // Attach click event listeners to each tab
    document.querySelectorAll('.nav-link').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default action
            
            // Hide all tab panes first
            hideAllTabPanes();

            // Show the tab pane associated with the clicked tab
            var targetPane = tab.getAttribute('data-bs-target');
            showTabPane(targetPane);
        });
    });
});

document.addEventListener("DOMContentLoaded", function() {
    var filterSitesSelect = document.getElementById('filter_sites');
    var filterGroupSelect = document.getElementById('filter_group');

    filterSitesSelect.addEventListener('change', function() {
        if (filterSitesSelect.value) {
            filterGroupSelect.disabled = true;
        } else {
            filterGroupSelect.disabled = false;
        }
    });

    filterGroupSelect.addEventListener('change', function() {
        if (filterGroupSelect.value) {
            filterSitesSelect.disabled = true;
        } else {
            filterSitesSelect.disabled = false;
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    function resetFilters() {
        document.getElementById('filter_cardholder').value = '';
        document.getElementById('filter_cardnumber').value = '';
        document.getElementById('filter_registration').value = '';
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('filter_sites').selectedIndex = 0;
        document.getElementById('filter_group').selectedIndex = 0;

        // Add any other form controls that need resetting here
    }

    // Bind the function to the Reset button
    document.getElementById('resetFilters').onclick = resetFilters;

});

