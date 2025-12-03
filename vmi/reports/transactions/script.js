let currentfilters = {};

// Multi-Input Tag System - stores values for each filter field
const multiInputData = {
    cardholder: [],
    cardnumber: [],
    registration: []
};

function Filters(companyId) {
    var element = document.getElementById('filter__div');
    if (element.style.display === "none" || element.style.display === "") {
        element.style.display = "block";
    } else {
        element.style.display = "none";
    }
}

// Initialize Multi-Input Tag System
function initMultiInputs() {
    const inputs = [
        { input: 'filter_cardholder_input', tags: 'cardholder_tags', key: 'cardholder' },
        { input: 'filter_cardnumber_input', tags: 'cardnumber_tags', key: 'cardnumber' },
        { input: 'filter_registration_input', tags: 'registration_tags', key: 'registration' }
    ];

    inputs.forEach(config => {
        const inputEl = document.getElementById(config.input);
        const tagsEl = document.getElementById(config.tags);
        
        if (!inputEl || !tagsEl) return;

        // Add tag on Enter key
        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.value.trim();
                if (value && !multiInputData[config.key].includes(value)) {
                    multiInputData[config.key].push(value);
                    renderTags(config.key, tagsEl);
                    this.value = '';
                }
            }
            // Remove last tag on Backspace if input is empty
            if (e.key === 'Backspace' && this.value === '' && multiInputData[config.key].length > 0) {
                multiInputData[config.key].pop();
                renderTags(config.key, tagsEl);
            }
        });

        // Focus input when clicking container
        inputEl.closest('.multi-input-container').addEventListener('click', function() {
            inputEl.focus();
        });
    });
}

// Render tags for a specific input
function renderTags(key, container) {
    container.innerHTML = multiInputData[key].map((value, index) => `
        <span class="filter-tag">
            ${escapeHtml(value)}
            <button type="button" class="tag-remove" data-key="${key}" data-index="${index}">&times;</button>
        </span>
    `).join('');

    // Add click handlers for remove buttons
    container.querySelectorAll('.tag-remove').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const key = this.dataset.key;
            const index = parseInt(this.dataset.index);
            multiInputData[key].splice(index, 1);
            renderTags(key, container);
        });
    });
}

// Helper to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Clear all multi-input tags
function clearMultiInputs() {
    multiInputData.cardholder = [];
    multiInputData.cardnumber = [];
    multiInputData.registration = [];
    
    const containers = ['cardholder_tags', 'cardnumber_tags', 'registration_tags'];
    containers.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '';
    });

    const inputs = ['filter_cardholder_input', 'filter_cardnumber_input', 'filter_registration_input'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

$(document).ready(function () {
    var currentPage = 1;

    var table = $('#customers_table').DataTable({
        paging: false,
        order: [[2, 'desc'], [3, 'desc']],
        columnDefs: [
          {
            targets: 0,
            className: 'dt-control',
            orderable: false,
            data: null,
            defaultContent: ''
          }
        ]
      });

    function loadTransactions(page, filters) {
        $.ajax({
          url: 'get_transactions',
          type: 'GET',
          dataType: 'json',
          data: {
            page: page,
            filters: filters 
          },
          success: function (data) {
            table.clear();
      
            data.forEach(function (transaction) {
              var newRow = table.row.add([
                '',
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
              ]);
              
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
    
      // dt-control (Expand/Collapse) Code
      $('#customers_table tbody').on('click', 'td.dt-control', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
      
        if (row.child.isShown()) {
          row.child.hide();
          tr.removeClass('shown');
        } else {
          var transaction = $(tr).data('transaction');
          var pulses = transaction.pulses ?? 0;
          var startDateTime = transaction.startDateTime ?? 'Unknown';
          var endDateTime = transaction.endDateTime ?? 'Unknown';
          var startDip = transaction.startDip ?? 'Unknown';
          var endDip = transaction.endDip ?? 'Unknown';
          
          var childContentHtml = `
            <div class="child-content">
              <p><strong>Pulses:</strong> ${pulses}</p>
              <p><strong>Stop Method:</strong> ${transaction.description}</p>
              <p><strong>Start DateTime:</strong> ${startDateTime}</p>
              <p><strong>End DateTime:</strong> ${endDateTime}</p>
              <p><strong>Start Dip:</strong> ${startDip}</p>
              <p><strong>End Dip:</strong> ${endDip}</p>
            </div>
          `;
      
          row.child(childContentHtml).show();
          tr.addClass('shown');
        }
      });
    

    // Initialize multi-input tag system
    initMultiInputs();

    // Add an event listener to the "Apply" button
    document.querySelector('.button-div .btn-primary').addEventListener('click', function() {
        currentfilters = {
            sites: $('#filter_sites').val(),
            group: $('#filter_group').val(),
            cardholder: multiInputData.cardholder,  // Array of values
            cardnumber: multiInputData.cardnumber,  // Array of values
            registration: multiInputData.registration,  // Array of values
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
        // Clear multi-input tags
        clearMultiInputs();
        
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('filter_sites').selectedIndex = 0;
        document.getElementById('filter_group').selectedIndex = 0;
        
        // Clear quick filter active state
        document.querySelectorAll('.quick-filter-btn').forEach(btn => btn.classList.remove('active'));

        // Add any other form controls that need resetting here
    }

    // Bind the function to the Reset button
    document.getElementById('resetFilters').onclick = resetFilters;

});

// Quick Date Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const quickFilterBtns = document.querySelectorAll('.quick-filter-btn');
    
    quickFilterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const range = this.dataset.range;
            const today = new Date();
            let startDate = new Date();
            let endDate = new Date();
            
            // Format date as YYYY-MM-DD for input[type="date"]
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            // Calculate date range based on button clicked
            switch(range) {
                case 'today':
                    startDate = today;
                    endDate = today;
                    break;
                case '1day':
                    startDate.setDate(today.getDate() - 1);
                    endDate = today;
                    break;
                case '1week':
                    startDate.setDate(today.getDate() - 7);
                    endDate = today;
                    break;
                case '1month':
                    startDate.setMonth(today.getMonth() - 1);
                    endDate = today;
                    break;
                case 'clear':
                    document.getElementById('start_date').value = '';
                    document.getElementById('end_date').value = '';
                    // Remove active class from all buttons
                    quickFilterBtns.forEach(b => b.classList.remove('active'));
                    // Trigger apply
                    document.querySelector('.button-div .btn-primary').click();
                    return;
            }
            
            // Set the date inputs
            document.getElementById('start_date').value = formatDate(startDate);
            document.getElementById('end_date').value = formatDate(endDate);
            
            // Update active state
            quickFilterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Auto-apply the filter
            document.querySelector('.button-div .btn-primary').click();
        });
    });
});

