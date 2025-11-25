let currentfilters = {};

function Filters(companyId) {
    var element = document.getElementById('filter__div');
    if (element.style.display === "none" || element.style.display === "") {
        element.style.display = "block";
        element.classList.add('active');
    } else {
        element.style.display = "none";
        element.classList.remove('active');
    }
}

$(document).ready(function () {
    var currentPage = 1; // Start from the first page

    function loadTransactions(page, filters) {
        $.ajax({
            url: 'get_tags.php', // Adjusted to a likely path
            type: 'GET',
            dataType: 'json', // Ensure jQuery expects JSON
            data: {
                page: page,
                filters: filters 
            },
            success: function(data) {
                var dtInstance = $('#customers_table').DataTable();
                dtInstance.clear(); // Clear current table rows without destroying the table

                if (data.length === 0) {
                    // Show empty state - don't add a row, just show message
                    $('#bodtest').html('<tr><td colspan="9" class="empty-state" style="text-align: center; padding: 3rem;">No tags found</td></tr>');
                    updatePageIndicator(page);
                    return;
                }

                data.forEach(function(tag) { // Using forEach for clarity
                    var actionButtons = '<div class="action-buttons">' +
                        '<button class="btn-edit edit-btn" data-id="' + tag.id + '">Edit</button>' +
                        '<button class="btn-delete delete-btn" data-id="' + tag.id + '" data-name="' + 
                        (tag.card_name || tag.card_number || 'this tag') + '">Delete</button>' +
                        '</div>';
                    
                    dtInstance.row.add([ // Add new row
                        tag.id || '',
                        tag.card_name || '',
                        tag.customer_name || '',
                        tag.card_number || '',
                        tag.expiry_date ? new Date(tag.expiry_date).toLocaleDateString() : '',
                        tag.list_driver || '',
                        tag.list_vehicle || '',
                        tag.updated_at ? new Date(tag.updated_at).toLocaleDateString() : '',
                        actionButtons
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
    
    // Edit button handler
    $(document).on('click', '.edit-btn', function() {
        var tagId = $(this).data('id');
        window.location.href = 'tag_edit.php?id=' + tagId;
    });

    // Delete button handler
    $(document).on('click', '.delete-btn', function() {
        var tagId = $(this).data('id');
        var tagName = $(this).data('name');
        var $btn = $(this);
        
        // Show confirmation dialog
        if (confirm('Are you sure you want to delete "' + tagName + '"? This action cannot be undone.')) {
            // Disable button to prevent double-click
            $btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: 'delete_tag.php',
                type: 'POST',
                data: { tag_id: tagId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success('Tag deleted successfully');
                        // Reload the current page data
                        loadTransactions(currentPage, currentfilters);
                    } else {
                        toastr.error(response.error || 'Failed to delete tag');
                        $btn.prop('disabled', false).text('Delete');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error deleting tag:', textStatus, errorThrown);
                    toastr.error('An error occurred while deleting the tag');
                    $btn.prop('disabled', false).text('Delete');
                }
            });
        }
    });
});
