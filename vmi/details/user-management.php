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

    <!-- Your own script (if it doesn’t depend on DataTables) -->
    <script src="script.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables -->
    <link  rel="stylesheet"
           href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

    <!-- Chart.js (unrelated, keep if you need it) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Local CSS files -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="menu.css">
</head>

<body>
<main class="table" style="height: 93%; left: 7rem;">

<?php include('top_menu.php'); ?>

<!-- =========================  FILTER BAR  ========================= -->
<div class="filters" style="justify-self: center;">
    <input  type="text"  id="globalSearch" placeholder="Search…">
    <select id="roleFilter">
        <option value="">All roles</option>
        <option value="Admin">Admin</option>
        <option value="User">User</option>
        <option value="Petro">Petro</option>
    </select>
</div>

<div class="table_cust">
<table id="users" class="customers_table">
<thead>
    <tr>
        <th>User</th>
        <th>Name</th>
        <th>Surname</th>
        <th>Role</th>
        <th>Last Login</th>
        <th>Edit User</th>
        <th>Apply Change</th>
    </tr>
</thead>
<tbody>
<?php
    /* -------------------------------------------------
       pull users for this company (except deleted 999)
    --------------------------------------------------*/
    $sql = ($companyId == 15100)
         ? "SELECT * FROM login WHERE access_level != 999"
         : "SELECT * FROM login WHERE client_id = $companyId AND access_level != 999";
    $result = $conn->query($sql);

    /* map each level to its paired level (Admin ↔ User) */
    $pair = [
        '4' => '5', '5' => '4',
        '6' => '7', '7' => '6',
        '8' => '9', '9' => '8'
    ];

    if ($result && $result->num_rows) {
        while ($row = $result->fetch_assoc()) {

            echo "<tr>";
            echo "<td>{$row['username']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['last_name']}</td>";

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

            /* build list with current level + its pair */
            $allowed = [$row['access_level']];
            if (isset($pair[$row['access_level']])) {
                $allowed[] = $pair[$row['access_level']];
            }
?>
<td>
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
</tr>
<?php
        } // while
    } else {
        echo "<tr><td colspan='7'>0 results</td></tr>";
    }
?>
</tbody>
</table>
</div>

</main>

<!-- DataTable and filter logic moved to script.js to avoid reinitialization -->

</body>
</html>
