/**
 * Transaction Reports - Enterprise Edition
 * Clean, professional reporting for large organizations
 */

let currentfilters = {};

function Filters(companyId) {
    var element = document.getElementById('filter__div');
    if (element.style.display === "none" || element.style.display === "") {
        element.style.display = "block";
    } else {
        element.style.display = "none";
    }
}

$(document).ready(function () {
    var currentPage = 1;

    // Initialize DataTable - clean enterprise configuration
    var table = $('#customers_table').DataTable({
        paging: false,
        searching: false,
        info: false,
        order: [[0, 'desc'], [1, 'desc']], // Sort by Date, then Time descending
        language: {
            emptyTable: "No transactions found for the selected criteria"
        }
    });

    // Load transactions from server
    function loadTransactions(page, filters) {
        // Show loading state
        table.clear().draw();
        
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
                
                if (data && data.length > 0) {
                    data.forEach(function (tx) {
                        // Format volume with 2 decimal places
                        var volume = parseFloat(tx.dispensed_volume || 0).toFixed(2);
                        
                        table.row.add([
                            tx.transaction_date,
                            tx.transaction_time,
                            tx.site_name || '-',
                            tx.tank_id || '-',
                            tx.pump_id || '-',
                            tx.card_holder_name || '-',
                            tx.card_number || '-',
                            tx.registration || '-',
                            tx.odometer || '-',
                            volume
                        ]);
                    });
                }
                
                table.draw();
                updatePageIndicator(page, data ? data.length : 0);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error loading transactions:', textStatus, errorThrown);
                table.clear().draw();
                updatePageIndicator(page, 0);
            }
        });
    }
      
    // Initial load
    loadTransactions(currentPage);
    
    // Pagination
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
    
    // Update page indicator with record count
    function updatePageIndicator(page, count) {
        var text = 'Page ' + page;
        if (count !== undefined) {
            text += ' (' + count + ' records)';
        }
        $('#pageIndicator').text(text);
    }

    // Apply Filters
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

        // Include company filter for admin users
        if (companyId == 15100 && document.getElementById('filter_company')) {
            currentfilters.company = $('#filter_company').val();
        }

        currentPage = 1;
        loadTransactions(currentPage, currentfilters);
    });
    
    // Export handlers
    document.getElementById('exportToExcel').addEventListener('click', function() {
        var queryString = $.param({ filters: currentfilters });
        window.location.href = 'export_to_excel.php?' + queryString;
    });

    document.getElementById('exportTocsv').addEventListener('click', function() {
        var queryString = $.param({ filters: currentfilters });
        window.location.href = 'export_to_csv.php?' + queryString;
    });

    document.getElementById('exportTopdf').addEventListener('click', function() {
        var queryString = $.param({ filters: currentfilters });
        window.location.href = 'export_to_pdf.php?' + queryString;
    });
});

// Tab Navigation
document.addEventListener("DOMContentLoaded", function() {
    function hideAllTabPanes() {
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
    }

    function showTabPane(paneId) {
        var pane = document.querySelector(paneId);
        if (pane) pane.classList.add('active');
    }

    document.querySelectorAll('.nav-link').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            hideAllTabPanes();
            showTabPane(tab.getAttribute('data-bs-target'));
        });
    });
});

// Site/Group filter mutual exclusion
document.addEventListener("DOMContentLoaded", function() {
    var filterSitesSelect = document.getElementById('filter_sites');
    var filterGroupSelect = document.getElementById('filter_group');

    filterSitesSelect.addEventListener('change', function() {
        filterGroupSelect.disabled = !!filterSitesSelect.value;
    });

    filterGroupSelect.addEventListener('change', function() {
        filterSitesSelect.disabled = !!filterGroupSelect.value;
    });
});

// Reset Filters
document.addEventListener('DOMContentLoaded', function() {
    function resetFilters() {
        document.getElementById('filter_cardholder').value = '';
        document.getElementById('filter_cardnumber').value = '';
        document.getElementById('filter_registration').value = '';
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('filter_sites').selectedIndex = 0;
        document.getElementById('filter_group').selectedIndex = 0;
        document.getElementById('filter_sites').disabled = false;
        document.getElementById('filter_group').disabled = false;
        
        // Clear quick filter active state
        document.querySelectorAll('.quick-filter-btn').forEach(btn => btn.classList.remove('active'));
    }

    document.getElementById('resetFilters').onclick = resetFilters;
});

// Quick Date Filters
document.addEventListener('DOMContentLoaded', function() {
    const quickFilterBtns = document.querySelectorAll('.quick-filter-btn');
    
    quickFilterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const range = this.dataset.range;
            const today = new Date();
            let startDate = new Date();
            let endDate = new Date();
            
            const formatDate = (date) => date.toISOString().split('T')[0];
            
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
                    quickFilterBtns.forEach(b => b.classList.remove('active'));
                    document.querySelector('.button-div .btn-primary').click();
                    return;
            }
            
            document.getElementById('start_date').value = formatDate(startDate);
            document.getElementById('end_date').value = formatDate(endDate);
            
            quickFilterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelector('.button-div .btn-primary').click();
        });
    });
});
