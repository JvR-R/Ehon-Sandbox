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

    function loadTransactions(page, filters) {
        $.ajax({
            url: 'get_drivers', // Adjusted to a likely path
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
                    dtInstance.row.add([ // Add new row
                        '', // Adjust columns as necessary
                        transaction.driver_id,
                        transaction.first_name,
                        transaction.surname,
                        transaction.customer_name,
                        transaction.driver_phone,
                        transaction.license_expire,
                        transaction.updated_at,
                        '<button class="btn btn-primary edit-btn" data-id="' + transaction.driver_id + '">Edit</button>'
                        // Adjust according to your actual data structure
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

document.addEventListener('DOMContentLoaded', function() {
    function resetFilters() {
        document.getElementById('filter_cardholder').value = '';
        document.getElementById('filter_cardnumber').value = '';
        document.getElementById('filter_registration').value = '';
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('filter_sites').selectedIndex = 0;

        // Add any other form controls that need resetting here
    }

    // Bind the function to the Reset button
    document.getElementById('resetFilters').onclick = resetFilters;
    $(document).on('click', '.edit-btn', function() {
        var driverId = $(this).data('id');

        $.ajax({
            url: 'driver_edit.php',
            type: 'POST',
            data: { id: driverId },
            success: function(response) {
                console.log('driver edit request successful');
                // Optionally, you can redirect or display a message
                window.location.href = 'driver_edit.php?id=' + driverId; // Redirect if needed
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error sending edit request:', textStatus, errorThrown);
            }
        });
    });
});
