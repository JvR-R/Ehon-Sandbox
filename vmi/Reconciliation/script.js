$(document).ready(function () {
    var currentPage = 1; // Start from the first page
    var currentfilters = {}; // Keep track of the current filters
    var sortColumn = null; // Column to sort by
    var sortOrder = 'asc'; // Sorting order (asc or desc)
    var totalRecords = 0; // Track total records

    function loadTransactions(page, filters) {
        // Show loading spinner
        showLoading();
        
        $.ajax({
            url: 'get_transactions.php',
            type: 'GET',
            dataType: 'json',
            data: {
                page: page,
                filters: filters,
                sortColumn: sortColumn,
                sortOrder: sortOrder
            },
            success: function (data) {
                console.log(data); // Debug: Log the data to ensure it matches the expected format
                populateTable(data);
                updatePageIndicator(page);
                updateRecordCount(data.length);
                
                // Hide loading and show the table section
                hideLoading();
                document.getElementById('tableSection').style.display = 'block';
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error loading transactions:', textStatus, errorThrown);
                hideLoading();
                alert('Error loading data. Please try again.');
            }
        });
    }

    function showLoading() {
        $('#loadingSpinner').show();
        $('#applyFilters').prop('disabled', true);
        $('#prevPage, #nextPage').prop('disabled', true);
    }

    function hideLoading() {
        $('#loadingSpinner').hide();
        $('#applyFilters').prop('disabled', false);
        $('#prevPage, #nextPage').prop('disabled', false);
    }

    function updateRecordCount(count) {
        totalRecords = count;
        $('#recordCount').text('Records on page: ' + count);
    }

    function populateTable(data) {
        var tbody = $('#bodtest');
        tbody.empty();
    
        data.forEach(function (row) {
            tbody.append(
                '<tr>' +
                '<td>' + row.Date + '</td>' +
                '<td>' + row.Site_name + '</td>' +
                '<td>' + row.Tank_id + '</td>' +
                '<td>' + formatNumber(row.Opening_balance) + '</td>' +
                '<td>' + formatNumber(row.Closing_balance) + '</td>' +
                '<td>' + formatNumber(row.Delta) + '</td>' +
                '<td>' + row.Total_transaction + '</td>' +
                '<td>' + formatNumber(row.Total_Deliveries) + '</td>' +
                '<td>' + formatNumber(row.reconciliation) + '</td>' +
                '</tr>'
            );
        });
    
        updateTotals(data);
    }
    function formatNumber(number) {
        // Ensure the input is a number
        var num = parseFloat(number);
        if (isNaN(num)) {
            return number;
        }
        // Format the number with commas as thousands separators
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    

    function updateTotals(data) {
        var totalOpeningBalance = 0;
        var reconciliation = 0;
        var totalTransactions = 0;
        var totalDeliveries = 0;
        var totalClosingBalance = 0;
        var totalVariance = 0;
    
        data.forEach(function (row) {
            totalOpeningBalance += parseFloat(row.Opening_balance);
            reconciliation += parseFloat(row.reconciliation);
            totalTransactions += parseInt(row.Total_transaction);
            totalDeliveries += parseFloat(row.Total_Deliveries);
            totalClosingBalance += parseFloat(row.Closing_balance);
            totalVariance += parseFloat(row.Delta);
        });
    
        $('#totalOpeningBalance').text(formatNumber(totalOpeningBalance));
        $('#reconciliation').text(formatNumber(reconciliation));
        $('#totalTransactions').text(totalTransactions);
        $('#totalDeliveries').text(formatNumber(totalDeliveries));
        $('#totalClosingBalance').text(formatNumber(totalClosingBalance));
        $('#totalVariance').text(formatNumber(totalVariance));
    }
    

    function updatePageIndicator(page) {
        $('#pageIndicator').text('Page ' + page);
    }

    // Select All Sites
    $('#selectAllSites').on('click', function() {
        $('#filter_sites option').not(':disabled').prop('selected', true);
        updateSelectedCount();
    });

    // Clear All Sites
    $('#clearAllSites').on('click', function() {
        $('#filter_sites option').prop('selected', false);
        updateSelectedCount();
    });

    // Update selected count when sites change
    $('#filter_sites').on('change', function() {
        updateSelectedCount();
    });

    function updateSelectedCount() {
        var count = $('#filter_sites').val() ? $('#filter_sites').val().filter(v => v !== '').length : 0;
        $('#selectedSitesCount').text(count + ' selected');
    }

    function countActiveFilters() {
        var count = 0;
        if ($('#filter_sites').val() && $('#filter_sites').val().filter(v => v !== '').length > 0) count++;
        if ($('#filter_group').val() && $('#filter_group').val() !== 'Select one') count++;
        if ($('#filter_tank').val()) count++;
        if ($('#filter_company').val() && $('#filter_company').val() !== 'Select one') count++;
        if ($('#start_date').val() && $('#end_date').val()) count++;
        return count;
    }

    function updateActiveFiltersBadge() {
        var count = countActiveFilters();
        if (count > 0) {
            $('#activeFiltersText').text(count + ' filter' + (count > 1 ? 's' : '') + ' active');
            $('#activeFiltersBadge').fadeIn();
        } else {
            $('#activeFiltersBadge').fadeOut();
        }
    }

    // Add an event listener to the "Apply" button
    $('#applyFilters').on('click', function () {
        currentfilters = {
            sites: $('#filter_sites').val(),
            group: $('#filter_group').val(),
            tank: $('#filter_tank').val(),
            company: $('#filter_company').val(),
            startDate: $('#start_date').val(),
            endDate: $('#end_date').val(),
        };

        // Reset the current page to 1 when applying filters
        currentPage = 1;
        
        updateActiveFiltersBadge();
        loadTransactions(currentPage, currentfilters);
    });

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

    document.getElementById('exportToExcel').addEventListener('click', function () {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_excel.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });

    document.getElementById('exportTocsv').addEventListener('click', function () {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_csv.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });

    document.getElementById('exportTopdf').addEventListener('click', function () {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_pdf.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });

    // This function hides all tab panes
    function hideAllTabPanes() {
        var tabPanes = document.querySelectorAll('.tab-pane');
        tabPanes.forEach(function (pane) {
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
    document.querySelectorAll('.nav-link').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault(); // Prevent default action

            // Hide all tab panes first
            hideAllTabPanes();

            // Show the tab pane associated with the clicked tab
            var targetPane = tab.getAttribute('data-bs-target');
            showTabPane(targetPane);
        });
    });

    var filterSitesSelect = document.getElementById('filter_sites');
    var filterGroupSelect = document.getElementById('filter_group');
    var filterCompanySelect = document.getElementById('filter_company');

    filterSitesSelect.addEventListener('change', function () {
        var selectedValues = Array.from(filterSitesSelect.selectedOptions).map(option => option.value).filter(value => value !== '');
        if (selectedValues.length > 0) {
            filterGroupSelect.disabled = true;
        } else {
            filterGroupSelect.disabled = false;
        }
    });

    filterGroupSelect.addEventListener('change', function () {
        if (filterGroupSelect.value) {
            filterSitesSelect.disabled = true;
        } else {
            filterSitesSelect.disabled = false;
        }
    });

    // Company filter interaction (if it exists)
    if (filterCompanySelect) {
        filterCompanySelect.addEventListener('change', function () {
            // When company changes, you might want to refresh available sites
            // This is optional and depends on your business logic
        });
    }

    function resetFilters() {
        // Reset sites filter properly for multi-select
        var sitesSelect = document.getElementById('filter_sites');
        for (var i = 0; i < sitesSelect.options.length; i++) {
            sitesSelect.options[i].selected = false;
        }
        sitesSelect.selectedIndex = 0;
        
        document.getElementById('filter_group').selectedIndex = 0;
        document.getElementById('filter_tank').value = '';
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        
        // Reset company filter if it exists
        var companySelect = document.getElementById('filter_company');
        if (companySelect) {
            companySelect.selectedIndex = 0;
        }
        
        // Re-enable both site and group selects
        document.getElementById('filter_sites').disabled = false;
        document.getElementById('filter_group').disabled = false;
        
        // Reset current filters
        currentfilters = {};
        
        // Reset counters and badges
        updateSelectedCount();
        updateActiveFiltersBadge();
        
        // Hide the table section
        document.getElementById('tableSection').style.display = 'none';
    }

    // Bind the function to the Reset button
    document.getElementById('resetFilters').onclick = resetFilters;

    // Function to sort table columns
    function sortTable(columnIndex, order) {
        var rows = $('#bodtest tr').get();

        rows.sort(function (a, b) {
            var A = $(a).children('td').eq(columnIndex).text();
            var B = $(b).children('td').eq(columnIndex).text();

            // Check if the content is a number and parse it
            if ($.isNumeric(A) && $.isNumeric(B)) {
                A = parseFloat(A);
                B = parseFloat(B);
            } else {
                A = A.toUpperCase();
                B = B.toUpperCase();
            }

            if (A < B) {
                return order === 'asc' ? -1 : 1;
            }
            if (A > B) {
                return order === 'asc' ? 1 : -1;
            }
            return 0;
        });

        $.each(rows, function (index, row) {
            $('#bodtest').append(row);
        });
    }

    // Attach click event listeners to headers for sorting
    $('#customers_table th').each(function (index) {
        $(this).append(' <span class="sort-arrow">&#9650;&#9660;</span>');
        $(this).on('click', function () {
            var order = $(this).data('order') === 'asc' ? 'desc' : 'asc';
            $(this).data('order', order);
            sortTable(index, order);
        });
    });
});
