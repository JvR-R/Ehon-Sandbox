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
    <link rel="stylesheet" href="../menu.css">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../style.css">
    
    <style>
        /* Modern styling for DataTables */
        #sitesTable_wrapper {
            padding: 0;
        }
        
        #sitesTable {
            width: 100% !important;
            border-collapse: collapse;
        }
        
        #sitesTable thead th {
            background-color: #002F60;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: none;
        }
        
        #sitesTable tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        #sitesTable tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 10px;
            color: #374151;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 12px;
            margin-left: 8px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 4px 10px;
            margin: 0 2px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            background: white;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #002F60 !important;
            color: white !important;
            border-color: #002F60;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            border-color: #9ca3af;
            color: black !important;
        }
        
        .dt-checkboxes {
            margin: 0;
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #002F60;
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
            background: #f0f9ff;
            border-left: 4px solid #002F60;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <main class="table">
    <?php include('../top_menu.php');?>
        <div class="dashboard-content">
            <div class="dashboard-main-content">
                <div class="container-default w-container" style="text-align: -webkit-center;">
                    <!-- Create New Group Section -->
                    <div class="mg-bottom-16px" style="max-width: 420px;">
                        <form class="small_group-details-card-grid" action="newgroup.php" method="post">
                            <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card_group top-details">
                                <input class="input top-details" type="text" name="groupname" id="groupname" placeholder="Enter group name" required>
                            </div>
                            <div class="">
                                <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                                <input type="submit" value="Create Group"
                                    style="font-weight: bold; font-size: 24px; color:white; background-color: #002F60;border-radius: 4px;cursor: pointer;padding: 5px 10px;border: none;">
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
                                            <div class="text-300 medium color-neutral-100">Edit your Group</div>
                                            <div class="_2-items-wrap-container gap-12px">
                                                <select id="groupDropdown" name="selected_group" class="small-dropdown-link w-dropdown-link" style="font-size: 14px; padding: 8px;">
                                                    <option value="">Select Group</option>
                                                    <?php
                                                    while ($stmt->fetch()) {
                                                        echo '<option value="' . $group_id . '">' . htmlspecialchars($group_name) . '</option>';
                                                    }
                                                    ?>
                                                </select>
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
                                            <strong>Selected:</strong> <span id="selectedCount">0</span> sites
                                        </div>
                                        
                                        <table id="sitesTable" class="display" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th width="50">
                                                        <input type="checkbox" id="selectAll" class="dt-checkboxes">
                                                    </th>
                                                    <th>Site Number</th>
                                                    <th>Company Name</th>
                                                    <th>Site Name</th>
                                                </tr>
                                            </thead>
                                        </table>
                                        
                                        <div class="btn-container">
                                            <input type="submit" name="submit" value="Update Group" class="btn-primary small w-inline-block" style="cursor:pointer; margin:0;">
                                            <button type="button" id="clearSelection" class="btn-primary small w-inline-block" style="cursor:pointer; background-color: #6b7280; margin:0;">
                                                Clear Selection
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
                    url: 'groups_data.php',
                    type: 'POST',
                    data: function(d) {
                        d.companyId = companyId;
                        d.groupId = groupId;
                    },
                    error: function(xhr, error, code) {
                        console.error('DataTables error:', error, code);
                        alert('Error loading data. Please refresh the page.');
                    }
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        className: 'dt-body-center',
                        render: function(data, type, row) {
                            const isChecked = selectedSites.has(row.site_id) || row.is_checked == 1;
                            if (isChecked && !selectedSites.has(row.site_id)) {
                                selectedSites.add(row.site_id);
                            }
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
                initializeDataTable(parseInt(currentGroupId));
                $('#statsInfo').show();
                
                // Hide loading after table is drawn
                sitesTable.one('draw', function() {
                    $('#loadingOverlay').removeClass('active');
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
