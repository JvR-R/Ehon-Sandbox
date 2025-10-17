let currentfilters = {};
function Filters(companyId) {
  // Assuming companyId will be used in future, not needed for the toggle logic
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

    function loadTransactions(page, filters) {
  $.ajax({
      url: 'get_transactions', // Adjusted to a likely path
      type: 'GET',
      dataType: 'json', // Ensure jQuery expects JSON
      data: {
          page: page,
          filters: filters 
      },
      success: function(data) {
          var dtInstance = $('#customers_table').DataTable();
          dtInstance.clear(); // Clear current table rows without destroying the table

          data.forEach(function(transaction) { // Using forEach for clarity
            var concatenatedParticles = transaction.particle_4um + '/' + transaction.particle_6um + '/' + transaction.particle_14um;  
            dtInstance.row.add([ // Add new row
                  '', // Adjust columns as necessary
                  transaction.uid,
                  transaction.fq_date,
                  transaction.fq_time,
                  transaction.Site_name,
                  transaction.tank_id,
                  transaction.particle_4um,
                  transaction.particle_6um,
                  transaction.particle_14um,
                  concatenatedParticles
              ]);
          });

          dtInstance.draw(); // Redraw the table with new data
          updatePageIndicator(page);
      },
      error: function(jqXHR, textStatus, errorThrown) {
          console.error('Error loading transactions:', textStatus, errorThrown);
      }
  });
}

// Initialization of DataTable outside the AJAX call

  // Initialize DataTable here with your options
  $('#customers_table').DataTable({
    paging: false,
    searching: false,
    select: true,
    info: false,
    scrollY: 550,
  });

    loadTransactions(currentPage); // Initial load

    $('#nextPage').on('click', function() {
        currentPage++;
        loadTransactions(currentPage, currentfilters);
    });

    $('#prevPage').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadTransactions(currentPage, currentfilters);
        }
    });

    function updatePageIndicator(page) {
        $('#pageIndicator').text('Page ' + page);
    }
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
