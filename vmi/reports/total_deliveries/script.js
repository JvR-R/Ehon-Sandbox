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
  var currentPage = 1;
  var table = $('#customers_table').DataTable({
      paging: false,
      order: [[2, 'desc'], [3, 'desc']],
      columnDefs: [
          {
              targets: 0,  // First column for expand button
              className: 'dt-control',
              orderable: false,
              data: null,
              defaultContent: ''
          }
      ]
  });

  // 1) Click on the Estimated Delivery cell to edit
  $(document).on('click', '.editable-delivery', function () {
      if ($(this).find('input').length > 0) return; // Already editing

      let currentValue = $(this).text().trim();
      $(this).data('original-value', currentValue);

      // Replace text with input
      $(this).html(`
        <input type="text"
               class="delivery-input"
               style="width: 100%;"
               value="${currentValue}" />
      `);

      $(this).find('input').focus();
  });

  // 2) On blur, finalize changes
  $(document).on('blur', '.delivery-input', function () {
      let newValue = $(this).val().trim();
      let td = $(this).closest('.editable-delivery');
      let originalValue = td.data('original-value');
      
      // Restore newValue as text
      td.html(newValue);

      // If nothing changed, do nothing
      if (newValue === originalValue) return;

      // Access row data — which includes delivery_id
      let tr = td.closest('tr');
      let transaction = tr.data('transaction');
      let delivery_id = transaction.delivery_id;  // <— Now using delivery_id!

      // Send AJAX to update
      $.ajax({
          url: 'update_delivery.php',
          method: 'POST',
          data: {
              delivery_id: delivery_id,
              delivery: newValue
          },
          success: function(response) {
              try {
                  let data = JSON.parse(response);
                  if (!data.success) {
                      alert('Error: ' + data.error);
                      // Revert
                      td.html(originalValue);
                  }
              } catch(e) {
                  alert('Unexpected server response');
                  td.html(originalValue);
              }
          },
          error: function() {
              alert('AJAX error updating delivery.');
              td.html(originalValue);
          }
      });
  });

  // 3) Pressing Enter triggers blur
  $(document).on('keyup', '.delivery-input', function(e) {
      if (e.key === 'Enter') {
          $(this).blur();
      }
  });

 // ========== LOADING THE DATA (delivery_id included) ==========

      function loadTransactions(page, filters) {
        $.ajax({
          url: 'get_deliveries.php', // Adjust to your path
          type: 'GET',
          dataType: 'json',
          data: { page: page, filters: filters },
          success: function (data) {
            // Clear existing rows
            table.clear();
      
            data.forEach(function (transaction) {
              // Wrap the delivery value in a <span> for inline editing:
              let editableDeliveryCell = `<span class="editable-delivery">${transaction.delivery}</span>`;
      
              var newRow = table.row.add([
                '', // dt-control column
                transaction.uid,
                transaction.transaction_date,
                transaction.transaction_time,
                transaction.site_name,
                transaction.tank_id,
                editableDeliveryCell, // <— Editable span
                transaction.current_volume
              ]);
      
              // Attach the entire transaction object to this row's DOM element
              $(newRow.node()).data('transaction', transaction);
            });
      
            table.draw(); 
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

    // Add an event listener to the "Apply" button
    document.querySelector('.button-div .btn-primary').addEventListener('click', function() {
        currentfilters = {
            sites: $('#filter_sites').val(),
            group: $('#filter_group').val(),
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
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('filter_sites').selectedIndex = 0;
        document.getElementById('filter_group').selectedIndex = 0;

        // Add any other form controls that need resetting here
    }

    // Bind the function to the Reset button
    document.getElementById('resetFilters').onclick = resetFilters;

});

