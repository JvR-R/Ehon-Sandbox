<?php
  include('../db/dbh2.php');
  include('../db/log.php');
  include('../db/border.php');
?>
<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Vendor Managed Inventory</title>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Toastr for notifications -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- DataTables -->
    <link  rel="stylesheet"
           href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

    <!-- Chart.js (unrelated, keep if you need it) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <!-- Local CSS files -->
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="menu.css">

    <!-- Your own script (loaded after dependencies) -->
    <script src="script.js"></script>
    
    <style>
        /* Modern User Management Styling - Clean & Professional */
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
        }
        
        /* Account for fixed nav-w 
         * nav-w position: top: 64px (below topborder)
         * nav-w padding: 30px top + 30px bottom = 60px
         * nav-w content height: ~60-80px
         * Total space needed: 64px + 60px + ~70px = ~194px
         */
        main.table {
            background-color: var(--bg-secondary);
            padding: 30px;
            padding-top: 4rem !important; /* Space for nav-w above content */
            border-radius: 12px;
            box-shadow: 0 4px 6px var(--shadow-sm);
        }
        
        /* Page Header */
        .page-header {
            margin-top: 20px; /* Add some space after nav-w */
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .page-header p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Filters Section */
        .filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background-color: var(--bg-card);
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow-sm);
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters input[type="text"],
        .filters select {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 14px;
            background-color: var(--input-bg);
            color: var(--input-text);
            transition: all 0.2s;
        }
        
        .filters input[type="text"]:focus,
        .filters select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(108, 114, 255, 0.1);
        }
        
        .filters input[type="text"]::placeholder {
            color: var(--text-secondary);
        }
        
        /* Company Filter - Monospace for hierarchy visualization */
        #companyFilter {
            font-family: 'Courier New', monospace;
            min-width: 250px;
        }
        
        /* Table Container */
        .table_cust {
            background-color: var(--bg-card);
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 4px var(--shadow-sm);
            overflow-x: auto;
        }
        
        /* DataTables Wrapper */
        .dataTables_wrapper {
            padding: 0;
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 0;
            color: var(--text-primary);
        }
        
        .dataTables_wrapper .dataTables_length select {
            padding: 6px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            background-color: var(--input-bg);
            color: var(--input-text);
            margin: 0 8px;
        }
        
        /* Modern Table Styling */
        #users {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--bg-card);
        }
        
        #users thead {
            background-color: var(--bg-darker);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        #users thead th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-inverse);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            background-color: var(--bg-darker);
        }
        
        #users thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        #users thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        #users tbody tr {
            background-color: var(--bg-card);
            transition: all 0.2s;
            border-bottom: 1px solid var(--border-color);
        }
        
        #users tbody tr:hover {
            background-color: var(--table-row-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px var(--shadow-sm);
        }
        
        #users tbody tr:last-child {
            border-bottom: none;
        }
        
        #users tbody td {
            padding: 16px 20px;
            color: var(--text-primary);
            font-size: 14px;
            vertical-align: middle;
            border: none;
        }
        
        /* Form Elements in Table */
        #users select {
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            background-color: var(--input-bg);
            color: var(--input-text);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 120px;
        }
        
        #users select:hover {
            border-color: var(--accent-primary);
        }
        
        #users select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(108, 114, 255, 0.1);
        }
        
        /* Submit Button - Modern Clean Design */
        .submit_delete {
            padding: 8px 20px;
            background-color: var(--btn-primary-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(108, 114, 255, 0.2);
        }
        
        .submit_delete:hover {
            background-color: var(--btn-primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(108, 114, 255, 0.3);
        }
        
        .submit_delete:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(108, 114, 255, 0.2);
        }
        
        /* Role Badges */
        #users tbody td:nth-child(4) {
            font-weight: 500;
        }
        
        /* User column (first) - make it stand out */
        #users tbody td:first-child {
            font-weight: 600;
            color: var(--accent-primary);
        }
        
        /* DataTables Pagination */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin: 0 4px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.2s;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: var(--table-row-hover);
            border-color: var(--accent-primary);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Empty State */
        #users tbody tr td[colspan] {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            main.table {
                left: 76px !important;
                width: calc(100% - 96px);
            }
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filters input[type="text"],
            .filters select {
                width: 100%;
                min-width: 100%;
            }
            
            #users {
                font-size: 12px;
            }
            
            #users thead th,
            #users tbody td {
                padding: 12px;
            }
        }
    </style>
    
    <!-- Pass user access level to JavaScript -->
    <script>
        const USER_ACCESS_LEVEL = <?php echo isset($_SESSION['accessLevel']) ? intval($_SESSION['accessLevel']) : 0; ?>;
        const ADMIN_LEVELS = [1, 2, 3, 4, 6, 8];
    </script>
</head>

<body>
<main class="table">

<?php include('top_menu.php'); ?>

<!-- Page Header -->
<div class="page-header">
    <h1>User Management</h1>
    <p>Manage user accounts, roles, and permissions for your organization</p>
</div>

<!-- Filter Bar -->
<div class="filters">
    <input type="text" id="globalSearch" placeholder="Search by name, username, or role...">
    <select id="roleFilter">
        <option value="">All Roles</option>
        <option value="Admin">Admin</option>
        <option value="User">User</option>
        <option value="Petro">Petro</option>
    </select>
    <select id="companyFilter">
        <option value="">All Companies</option>
        <?php
        // Build hierarchical company list based on access level
        if ($companyId == 15100) {
            // Owner sees all companies
            $compSql = "SELECT DISTINCT c.client_id, c.client_name, c.reseller_id, c.Dist_id 
                        FROM Clients c 
                        ORDER BY 
                            CASE 
                                WHEN c.Dist_id IS NULL AND c.reseller_id IS NULL THEN 1
                                WHEN c.reseller_id IS NULL THEN 2
                                ELSE 3
                            END,
                            c.client_name";
        } else {
            // Get current company's role in hierarchy
            $roleCheck = $conn->query("SELECT Dist_id, reseller_id FROM Clients WHERE client_id = $companyId");
            $roleRow = $roleCheck->fetch_assoc();
            
            if ($roleRow && $roleRow['Dist_id'] === null && $roleRow['reseller_id'] === null) {
                // This is a distributor - show all under them
                $compSql = "SELECT DISTINCT c.client_id, c.client_name 
                            FROM Clients c 
                            WHERE c.Dist_id = $companyId OR c.client_id = $companyId
                            ORDER BY c.client_name";
            } elseif ($roleRow && $roleRow['reseller_id'] === null) {
                // This is a reseller - show all under them
                $compSql = "SELECT DISTINCT c.client_id, c.client_name 
                            FROM Clients c 
                            WHERE c.reseller_id = $companyId OR c.client_id = $companyId
                            ORDER BY c.client_name";
            } else {
                // Regular client - only show themselves
                $compSql = "SELECT client_id, client_name FROM Clients WHERE client_id = $companyId";
            }
        }
        
        $compResult = $conn->query($compSql);
        if ($compResult && $compResult->num_rows > 0) {
            while ($comp = $compResult->fetch_assoc()) {
                $prefix = '';
                if ($companyId == 15100) {
                    // Add visual hierarchy for owner view
                    if ($comp['Dist_id'] === null && $comp['reseller_id'] === null) {
                        $prefix = '🏢 '; // Distributor
                    } elseif ($comp['reseller_id'] === null) {
                        $prefix = '  └ 📦 '; // Reseller
                    } else {
                        $prefix = '    └ 🏪 '; // Client
                    }
                }
                echo '<option value="' . $comp['client_id'] . '">' . $prefix . htmlspecialchars($comp['client_name']) . '</option>';
            }
        }
        ?>
    </select>
</div>

<div class="table_cust">
<table id="users" class="customers_table">
<thead>
    <tr>
        <th>User</th>
        <th>Name</th>
        <th>Surname</th>
        <?php if ($companyId == 15100 || isset($roleRow)) { ?>
        <th>Company</th>
        <?php } ?>
        <th>Role</th>
        <th>Last Login</th>
        <th>Edit User</th>
        <th>Apply Change</th>
    </tr>
</thead>
<tbody>
<?php
    /* -------------------------------------------------
       Pull users based on hierarchical access
       15100 (Owner) -> Distributor -> Reseller -> Client
    --------------------------------------------------*/
    if ($companyId == 15100) {
        // Owner sees ALL users globally
        $sql = "SELECT l.*, c.client_name, c.Dist_id, c.reseller_id 
                FROM login l 
                LEFT JOIN Clients c ON l.client_id = c.client_id 
                WHERE l.access_level != 999 
                ORDER BY c.client_name, l.username";
    } else {
        // Check the company's position in the hierarchy
        $roleCheck = $conn->query("SELECT Dist_id, reseller_id FROM Clients WHERE client_id = $companyId");
        $roleRow = $roleCheck->fetch_assoc();
        
        if ($roleRow && $roleRow['Dist_id'] === null && $roleRow['reseller_id'] === null) {
            // This is a Distributor - see all users under their resellers and clients
            $sql = "SELECT l.*, c.client_name, c.Dist_id, c.reseller_id 
                    FROM login l 
                    LEFT JOIN Clients c ON l.client_id = c.client_id 
                    WHERE (c.Dist_id = $companyId OR l.client_id = $companyId) 
                    AND l.access_level != 999 
                    ORDER BY c.client_name, l.username";
        } elseif ($roleRow && $roleRow['reseller_id'] === null) {
            // This is a Reseller - see all users under their clients
            $sql = "SELECT l.*, c.client_name, c.Dist_id, c.reseller_id 
                    FROM login l 
                    LEFT JOIN Clients c ON l.client_id = c.client_id 
                    WHERE (c.reseller_id = $companyId OR l.client_id = $companyId) 
                    AND l.access_level != 999 
                    ORDER BY c.client_name, l.username";
        } else {
            // Regular Client - only see their own users
            $sql = "SELECT l.*, c.client_name, c.Dist_id, c.reseller_id 
                    FROM login l 
                    LEFT JOIN Clients c ON l.client_id = c.client_id 
                    WHERE l.client_id = $companyId 
                    AND l.access_level != 999 
                    ORDER BY l.username";
        }
    }
    
    $result = $conn->query($sql);

    /* map each level to its paired level (Admin ↔ User) */
    $pair = [
        '4' => '5', '5' => '4',
        '6' => '7', '7' => '6',
        '8' => '9', '9' => '8'
    ];

    if ($result && $result->num_rows) {
        while ($row = $result->fetch_assoc()) {

            echo "<tr data-company-id='{$row['client_id']}'>";
            echo "<td>{$row['username']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['last_name']}</td>";
            
            /* Company column (for hierarchical views) */
            if ($companyId == 15100 || isset($roleRow)) {
                $companyName = isset($row['client_name']) ? $row['client_name'] : 'N/A';
                echo "<td>" . htmlspecialchars($companyName) . "</td>";
            }

            /* role column */
            if (in_array($row['access_level'], ['4','6','8'])) {
                echo "<td>Admin</td>";
            } elseif (in_array($row['access_level'], ['5','7','9'])) {
                echo "<td>User</td>";
            } else {
                echo "<td>Petro</td>";
            }

            echo "<td>{$row['last_date']}</td>";

            /* Petro levels (1‑3) get no edit controls */
            if (in_array($row['access_level'], ['1','2','3'])) {
                echo "<td></td><td></td></tr>";
                continue;
            }
            
            /* Check if current user can edit this user (prevent editing higher hierarchy) */
            $canEdit = true;
            if ($companyId != 15100 && $row['client_id'] != $companyId) {
                // Only allow editing if this user is under the current company's hierarchy
                if (isset($roleRow)) {
                    if ($roleRow['Dist_id'] === null && $roleRow['reseller_id'] === null) {
                        // Distributor can edit resellers/clients under them
                        $canEdit = ($row['Dist_id'] == $companyId);
                    } elseif ($roleRow['reseller_id'] === null) {
                        // Reseller can edit clients under them
                        $canEdit = ($row['reseller_id'] == $companyId);
                    } else {
                        $canEdit = false;
                    }
                }
            }

            /* build list with current level + its pair */
            $allowed = [$row['access_level']];
            if (isset($pair[$row['access_level']])) {
                $allowed[] = $pair[$row['access_level']];
            }
?>
<td>
<?php if ($canEdit) { ?>
    <form action="user_update.php" method="post">
        <select name="edit_user" id="edit_user">
<?php
            foreach ($allowed as $lvl) {
                $selected = ($row['access_level'] == $lvl) ? 'selected' : '';
                $label    = in_array($lvl, ['4','6','8']) ? 'Admin' : 'User';
                echo "<option value=\"$lvl\" $selected>$label</option>";
            }
?>
            <option value="999">Delete</option>
        </select>
        <input type="hidden" name="edit_username"  value="<?php echo $row['username']; ?>">
        <input type="hidden" name="edit_usernameid" value="<?php echo $row['user_id']; ?>">
</td>
<td><button class="submit_delete" type="submit">Submit</button></td>
    </form>
<?php } else { ?>
        <td colspan="2" style="text-align: center; color: var(--text-secondary);">Read Only</td>
<?php } ?>
</tr>
<?php
        } // while
    } else {
        $colspan = ($companyId == 15100 || isset($roleRow)) ? '8' : '7';
        echo "<tr><td colspan='$colspan'>0 results</td></tr>";
    }
?>
</tbody>
</table>
</div>

</main>

<!-- DataTable and filter logic moved to script.js to avoid reinitialization -->

</body>
</html>
