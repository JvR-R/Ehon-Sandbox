<?php
  include('../../db/dbh2.php');
  include('../../db/log.php');
  include('../../db/border.php');
?>
<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

    <link rel="stylesheet" href="/vmi/details/style.css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Account for fixed nav-w 
         * nav-w position: top: 64px (below topborder)
         * nav-w padding: 30px top + 30px bottom = 60px
         * nav-w content height: ~60-80px
         * Total space needed: 64px + 60px + ~70px = ~194px
         */
        main.table {
            padding-top: 4rem !important; /* Space for nav-w above content */
        }
        
        /* Page Header */
        .page-header {
            margin-top: 20px; /* Add some space after nav-w */
            margin-bottom: 30px;
            padding: 24px 32px;
            background-color: var(--bg-card);
            border-radius: 12px;
            border-bottom: 3px solid var(--accent-primary);
            box-shadow: 0 2px 4px var(--shadow-sm);
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }
        
        .page-header h1 i {
            color: var(--accent-primary);
            margin-right: 12px;
            font-size: 28px;
            vertical-align: middle;
        }
        
        .page-header p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Icon Styling */
        .icon-wrapper {
            position: relative;
        }
        
        .icon-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
            transition: color 0.2s ease;
        }
        
        .icon-wrapper input,
        .icon-wrapper select {
            padding-left: 40px;
        }
        
        .icon-wrapper:focus-within i {
            color: var(--accent-primary);
        }
        
        /* Button Icons */
        .btn-primary i,
        input[type="submit"] i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        /* Card Header Icons */
        .form-header i,
        .card-header i {
            margin-right: 8px;
            color: var(--accent-primary);
        }
        
        /* Stats Info Icon */
        .stats-info i {
            margin-right: 8px;
            color: var(--accent-primary);
        }
        
        /* Loading Spinner */
        .spinner i {
            color: var(--accent-primary);
        }
        
        /* Modern styling for DataTables with theme support */
        #sitesTable_wrapper {
            padding: 0;
            background-color: var(--bg-card);
        }
        
        #sitesTable {
            width: 100% !important;
            border-collapse: collapse;
            background-color: var(--table-body-bg);
        }
        
        #sitesTable thead th {
            background-color: var(--bg-darker);
            color: var(--text-inverse);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: none;
        }
        
        #sitesTable thead th i {
            margin-right: 6px;
            opacity: 0.9;
        }
        
        #sitesTable tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--bg-card);
            color: var(--text-primary);
        }
        
        #sitesTable tbody tr:hover td {
            background-color: var(--table-row-hover);
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 10px;
            color: var(--text-primary);
        }
        
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background-color: var(--input-bg);
            color: var(--input-text);
            border: 1px solid var(--input-border);
            border-radius: 4px;
            padding: 6px 12px;
            margin-left: 8px;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: var(--input-shadow-focus);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 4px 10px;
            margin: 0 2px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--bg-darker) !important;
            color: var(--text-inverse) !important;
            border-color: var(--bg-darker);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--table-row-hover) !important;
            border-color: var(--accent-primary) !important;
            color: var(--text-primary) !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: var(--text-secondary) !important;
        }
        
        .dt-checkboxes {
            margin: 0;
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: var(--accent-primary);
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--overlay-bg);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            border: 4px solid var(--bg-secondary);
            border-top: 4px solid var(--bg-darker);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats-info {
            padding: 10px;
            background: var(--table-row-hover);
            border-left: 4px solid var(--bg-darker);
            margin-bottom: 15px;
            border-radius: 4px;
            color: var(--text-primary);
        }
        
        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* DataTables processing overlay */
        .dataTables_processing {
            background-color: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-color) !important;
        }
        
        /* Sorting icons */
        table.dataTable thead .sorting:before,
        table.dataTable thead .sorting:after,
        table.dataTable thead .sorting_asc:before,
        table.dataTable thead .sorting_asc:after,
        table.dataTable thead .sorting_desc:before,
        table.dataTable thead .sorting_desc:after {
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <main class="table">
    <?php include('../top_menu.php');?>
        <div class="page-header">
            <h1><i class="fas fa-layer-group"></i> Site Groups</h1>
            <p>Create and manage groups of sites for easier organization and reporting</p>
        </div>
        
        <div class="dashboard-content">
            <div class="dashboard-main-content">
                <div class="container-default w-container" style="text-align: -webkit-center;">
                    <!-- Create New Group Section -->
                    <div class="mg-bottom-16px" style="max-width: 420px;">
                        <form class="small_group-details-card-grid" action="newgroup.php" method="post">
                            <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card_group top-details icon-wrapper">
                                <i class="fas fa-tag"></i>
                                <input class="input top-details" type="text" name="groupname" id="groupname" placeholder="Enter group name" required>
                            </div>
                            <div class="">
                                <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                                <button type="submit" style="font-weight: bold; font-size: 24px; color:white; background-color: var(--accent-primary);border-radius: 8px;cursor: pointer;padding: 12px 24px;border: none; transition: all 0.2s ease; box-shadow: 0 2px 4px var(--shadow-sm);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px var(--shadow-sm)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px var(--shadow-sm)'">
                                    <i class="fas fa-plus-circle"></i> Create Group
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Select Group Section -->
                    <div class="mg-bottom-24px" style="max-width: 100%; width: 100%;">
                        <div>
                            <div class="card overflow-hidden">
                                <div class="_2-items-wrap-container pd-32px---28px">
                                    <?php
                                        $sel = "SELECT group_id, group_name FROM site_groups WHERE client_id = ?";
                                        $stmt = $conn->prepare($sel);
                                        $stmt->bind_param("i", $companyId);
                                        $stmt->execute();
                                        $stmt->store_result();
                                        if($stmt->num_rows > 0) {
                                            $stmt->bind_result($group_id, $group_name);
                                    ?>
                                            <div class="text-300 medium color-neutral-100"><i class="fas fa-edit"></i> Edit your Group</div>
                                            <div class="_2-items-wrap-container gap-12px">
                                                <div class="icon-wrapper" style="flex: 1;">
                                                    <select id="groupDropdown" name="selected_group" class="small-dropdown-link w-dropdown-link" style="font-size: 14px; padding: 8px;">
                                                        <option value="">Select Group</option>
                                                        <?php
                                                        while ($stmt->fetch()) {
                                                            echo '<option value="' . $group_id . '">' . htmlspecialchars($group_name) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>                                   
                                    <?php                                  
                                        } else {
                                            echo '<div class="text-300 medium color-neutral-100">Create your first group above</div>';
                                        }
                                        $stmt->close();
                                    ?>                                                                               
                                </div>
                                
                                <!-- Sites Table Section -->
                                <div class="card pd-30px---36px">
                                    <form id="groupForm" action="group_updt.php" method="post">
                                        <input type="hidden" name="selected_group" id="selectedGroupInput" value="">
                                        <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($companyId); ?>">
                                        
                                        <div class="stats-info" id="statsInfo" style="display:none;">
                                            <i class="fas fa-check-circle"></i> <strong>Selected:</strong> <span id="selectedCount">0</span> sites
                                        </div>
                                        
                                        <table id="sitesTable" class="display" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th width="50">
                                                        <input type="checkbox" id="selectAll" class="dt-checkboxes">
                                                    </th>
                                                    <th><i class="fas fa-hashtag"></i> Site Number</th>
                                                    <th><i class="fas fa-building"></i> Company Name</th>
                                                    <th><i class="fas fa-map-marker-alt"></i> Site Name</th>
                                                </tr>
                                            </thead>
                                        </table>
                                        
                                        <div class="btn-container">
                                            <button type="submit" name="submit" class="btn-primary small w-inline-block" style="cursor:pointer; margin:0;">
                                                <i class="fas fa-save"></i> Update Group
                                            </button>
                                            <button type="button" id="clearSelection" class="btn-primary small w-inline-block" style="cursor:pointer; background-color: #6b7280; margin:0;">
                                                <i class="fas fa-times"></i> Clear Selection
                                            </button>
                                        </div>
                                    </form>
                                </div>                                                                                                                                                       
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- jQuery (required for DataTables) -->
    <script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=65014a9e5ea5cd2c6534f1c8" type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    
    <!-- <script src="/js/webflow_rep.js" type="text/javascript"></script> -->

    <script>
    $(document).ready(function() {
        const companyId = <?php echo json_encode($companyId); ?>;
        let currentGroupId = null;
        let sitesTable = null;
        let selectedSites = new Set(); // Track selected sites across pages
        
        // Initialize DataTable with server-side processing
        function initializeDataTable(groupId = 0) {
            if (sitesTable) {
                sitesTable.destroy();
            }
            
            sitesTable = $('#sitesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/vmi/details/groups/groups_data.php',
                    type: 'POST',
                    xhrFields: {
                        withCredentials: true
                    },
                    data: function(d) {
                        d.companyId = companyId;
                        d.groupId = groupId;
                    },
                    error: function(xhr, error, code) {
                        console.error('DataTables error:', error, code);
                        if (xhr.responseText && xhr.responseText.includes('<html')) {
                            alert('Session expired. Please refresh the page to login.');
                            window.location.href = '/vmi/login/';
                        } else {
                            alert('Error loading data. Please refresh the page.');
                        }
                    }
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        className: 'dt-body-center',
                        render: function(data, type, row) {
                            // Check if site is in the selectedSites Set
                            const isChecked = selectedSites.has(row.site_id);
                            return '<input type="checkbox" class="dt-checkboxes site-checkbox" ' +
                                   'data-siteid="' + row.site_id + '" ' +
                                   'data-sitename="' + row.site_name + '" ' +
                                   (isChecked ? 'checked' : '') + '>';
                        }
                    },
                    { 
                        data: 'site_id',
                        render: function(data) {
                            return '#' + data;
                        }
                    },
                    { data: 'client_name' },
                    { data: 'site_name' }
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[1, 'asc']],
                language: {
                    processing: 'Loading sites...',
                    emptyTable: 'No sites available',
                    zeroRecords: 'No matching sites found',
                    paginate: {
                        first: 'First',
                        last: 'Last',
                        next: 'Next',
                        previous: 'Previous'
                    }
                },
                drawCallback: function() {
                    updateSelectedCount();
                    updateSelectAllCheckbox();
                }
            });
        }
        
        // Initialize table on page load
        initializeDataTable();
        
        // Handle group selection change
        $('#groupDropdown').on('change', function() {
            currentGroupId = $(this).val();
            $('#selectedGroupInput').val(currentGroupId);
            
            if (currentGroupId) {
                $('#loadingOverlay').addClass('active');
                selectedSites.clear(); // Clear previous selections
                
                // Fetch ALL sites in the selected group to pre-populate selectedSites
                $.ajax({
                    url: 'fetch_data.php',
                    type: 'POST',
                    data: { 
                        groupId: currentGroupId, 
                        companyId: companyId 
                    },
                    dataType: 'json',
                    success: function(data) {
                        // Pre-populate selectedSites with all sites in the group
                        $.each(data, function(index, item) {
                            selectedSites.add(item.siteId);
                        });
                        
                        // Now initialize the table with pre-populated selections
                        initializeDataTable(parseInt(currentGroupId));
                        $('#statsInfo').show();
                        updateSelectedCount();
                        
                        // Hide loading after table is drawn
                        sitesTable.one('draw', function() {
                            $('#loadingOverlay').removeClass('active');
                        });
                    },
                    error: function(xhr, error, code) {
                        console.error('Error fetching group data:', error);
                        // Still initialize table even if fetch fails
                        initializeDataTable(parseInt(currentGroupId));
                        $('#statsInfo').show();
                        sitesTable.one('draw', function() {
                            $('#loadingOverlay').removeClass('active');
                        });
                    }
                });
            } else {
                selectedSites.clear();
                initializeDataTable(0);
                $('#statsInfo').hide();
            }
        });
        
        // Handle individual checkbox changes
        $('#sitesTable').on('change', '.site-checkbox', function() {
            const siteId = parseInt($(this).data('siteid'));
            
            if ($(this).is(':checked')) {
                selectedSites.add(siteId);
            } else {
                selectedSites.delete(siteId);
            }
            
            updateSelectedCount();
            updateSelectAllCheckbox();
        });
        
        // Handle "Select All" checkbox
        $('#selectAll').on('change', function() {
            const isChecked = $(this).is(':checked');
            
            // Get all checkboxes on current page
            $('.site-checkbox:visible').each(function() {
                const siteId = parseInt($(this).data('siteid'));
                $(this).prop('checked', isChecked);
                
                if (isChecked) {
                    selectedSites.add(siteId);
                } else {
                    selectedSites.delete(siteId);
                }
            });
            
            updateSelectedCount();
        });
        
        // Update selected count display
        function updateSelectedCount() {
            $('#selectedCount').text(selectedSites.size);
        }
        
        // Update "Select All" checkbox state
        function updateSelectAllCheckbox() {
            const visibleCheckboxes = $('.site-checkbox:visible');
            const checkedCheckboxes = $('.site-checkbox:visible:checked');
            
            if (visibleCheckboxes.length === 0) {
                $('#selectAll').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes.length === 0) {
                $('#selectAll').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes.length === visibleCheckboxes.length) {
                $('#selectAll').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#selectAll').prop('checked', false).prop('indeterminate', true);
            }
        }
        
        // Clear selection button
        $('#clearSelection').on('click', function() {
            selectedSites.clear();
            $('.site-checkbox').prop('checked', false);
            $('#selectAll').prop('checked', false).prop('indeterminate', false);
            updateSelectedCount();
        });
        
        // Handle form submission
        $('#groupForm').on('submit', function(e) {
            if (!currentGroupId) {
                e.preventDefault();
                alert('Please select a group first.');
                return false;
            }
            
            if (selectedSites.size === 0) {
                if (!confirm('No sites selected. This will remove all sites from the group. Continue?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Clear existing hidden inputs
            $('input[name="selected_checkboxes[]"]').remove();
            
            // Add hidden inputs for all selected sites
            selectedSites.forEach(function(siteId) {
                // Find the site name from the table
                const checkbox = $('.site-checkbox[data-siteid="' + siteId + '"]').first();
                const siteName = checkbox.data('sitename') || '';
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'selected_checkboxes[]',
                    value: siteId + '|' + siteName
                }).appendTo('#groupForm');
            });
            
            $('#loadingOverlay').addClass('active');
            return true;
        });
    });
    </script>
</body>
</html>
