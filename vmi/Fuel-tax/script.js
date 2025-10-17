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
    var currentfilters = {}; // Keep track of the current filters
    var sortColumn = null; // Column to sort by
    var sortOrder = 'asc'; // Sorting order (asc or desc)

    function loadTransactions(page, filters) {
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
            success: function (response) {
                if (response.error) {
                    console.error('Error:', response.error);
                    return;
                }
                console.log(response); // Debug: Log the data to ensure it matches the expected format
                populateTable(response.data, response.sums);
                updatePageIndicator(page);

                // Show the table and sums section
                $('#tableSection').show();
                $('#sumsSection').show();
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error loading transactions:', textStatus, errorThrown);
            }
        });
    }

    function populateTable(data, sums) {
        var tbody = $('#bodtest');
        tbody.empty();

        data.forEach(function (row) {
            // Convert volume and total to numbers and format with commas
            var formattedVolume = parseFloat(row.volume).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            var formattedTotal = parseFloat(row.total).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            var tr = $('<tr></tr>');
            tr.append('<td>' + row.card_number + '</td>');
            tr.append('<td>' + row.registration + '</td>');
            tr.append('<td class="volume-cell">' + formattedVolume + '</td>');
            tr.append('<td contenteditable="true" data-card-number="' + row.card_number + '" data-registration="' + row.registration + '">' + row.tax_value + '</td>');
            tr.append('<td class="total-cell">' + formattedTotal + '</td>');
            tr.data('volume', row.volume); // Store volume in row data
            tbody.append(tr);
        });

        // Update the sums under the table
        updateSums(sums);
    }

    function updatePageIndicator(page) {
        $('#pageIndicator').text('Page ' + page);
    }

    // New function to update sums
    function updateSums(sums) {
        $('#totalVolume').text(
          sums.total_volume.toLocaleString(undefined, {
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
          })
        );
        
        $('#totalSum').text(
          sums.total_sum.toLocaleString(undefined, {
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
          })
        );
      }

    // Add an event listener to the "Apply" button using jQuery
    $('.button-div .btn-primary').on('click', function () {
        currentfilters = {
            // Update these filters based on your actual filter input fields
            card_number: $('#filter_card_number').val(),
            registration: $('#filter_registration').val(),
            startDate: $('#start_date').val(),
            endDate: $('#end_date').val(),
        };

        // Reset the current page to 1 when applying filters
        currentPage = 1;

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

    // Attach event listeners to export buttons using jQuery
    $('#exportToExcel').on('click', function () {
        // Construct the query string from currentfilters
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_excel.php?' + queryString;

        // Redirect to the URL to initiate the download
        window.location.href = exportUrl;
    });

    $('#exportTocsv').on('click', function () {
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_csv.php?' + queryString;
        window.location.href = exportUrl;
    });

    $('#exportTopdf').on('click', function () {
        var queryString = $.param({ filters: currentfilters });
        var exportUrl = 'export_to_pdf.php?' + queryString;
        window.location.href = exportUrl;
    });

    // This function hides all tab panes
    function hideAllTabPanes() {
        $('.tab-pane').removeClass('active');
    }

    // This function shows the selected tab pane
    function showTabPane(paneId) {
        $(paneId).addClass('active');
    }

    // Attach click event listeners to each tab
    $('.nav-link').on('click', function (e) {
        e.preventDefault(); // Prevent default action

        // Hide all tab panes first
        hideAllTabPanes();

        // Show the tab pane associated with the clicked tab
        var targetPane = $(this).attr('data-bs-target');
        showTabPane(targetPane);
    });

    var filterCardNumberInput = $('#filter_card_number');
    var filterRegistrationInput = $('#filter_registration');

    // Example logic to disable one filter if the other is used
    filterCardNumberInput.on('input', function () {
        if (filterCardNumberInput.val()) {
            filterRegistrationInput.prop('disabled', true);
        } else {
            filterRegistrationInput.prop('disabled', false);
        }
    });

    filterRegistrationInput.on('input', function () {
        if (filterRegistrationInput.val()) {
            filterCardNumberInput.prop('disabled', true);
        } else {
            filterCardNumberInput.prop('disabled', false);
        }
    });

    function resetFilters() {
        $('#filter_card_number').val('');
        $('#filter_registration').val('');
        $('#start_date').val('');
        $('#end_date').val('');
        // Add any other form controls that need resetting here

        // Reset current filters
        currentfilters = {};

        // Reload transactions with no filters
        currentPage = 1;
        loadTransactions(currentPage, currentfilters);
    }

    // Bind the function to the Reset button using jQuery
    $('#resetFilters').on('click', resetFilters);

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

    // Function to update tax value
    function updateTaxValue(cell) {
        var newTaxValue = parseFloat(cell.text());
        if (isNaN(newTaxValue)) {
            alert('Please enter a valid number for Tax Value.');
            // Optionally, reset the cell content to the previous value
            return;
        }
        var cardNumber = cell.data('card-number');
        var registration = cell.data('registration');
        var row = cell.closest('tr');
        var volume = parseFloat(row.data('volume'));
        var newTotal = ((volume / 100) * newTaxValue).toFixed(2);

        // Update the total cell
        row.find('.total-cell').text(newTotal);

        // Send AJAX request to update the tax value in the database
        $.ajax({
            url: 'update_tax_value.php',
            type: 'POST',
            dataType: 'json',
            data: {
                card_number: cardNumber,
                registration: registration,
                tax_value: newTaxValue
            },
            success: function(response) {
                if(response.success) {
                    // Optionally, show a success message
                    console.log('Tax value updated successfully');
                } else {
                    alert('Failed to update tax value: ' + response.message);
                }
            },
            error: function() {
                alert('Error updating tax value');
            }
        });
    }

    // Event listener for when tax_value cell loses focus (blur event)
    $(document).on('blur', 'td[contenteditable="true"]', function() {
        updateTaxValue($(this));
    });

    // Event listener for when user presses Enter key while editing tax_value cell
    $(document).on('keypress', 'td[contenteditable="true"]', function(e) {
        if (e.which == 13) { // Enter key pressed
            e.preventDefault(); // Prevent inserting a newline
            updateTaxValue($(this));
            $(this).blur(); // Remove focus from the cell
        }
    });

    // Load transactions with no filters on page load
    loadTransactions(currentPage, currentfilters);
});
