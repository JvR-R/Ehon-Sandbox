<?php
// Include necessary files and check access level
include('../db/dbh2.php');
include('../db/log.php');   
include('../db/border.php');  

if($accessLevel !== 1){
  header("Location: /vmi/reports");
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>EHON Energy Tech - Service</title>
  <!-- Include CSS stylesheets -->
  <link rel="stylesheet" href="/vmi/css/normalize.css">
  <link rel="stylesheet" href="style.css"> 
  <link rel="stylesheet" href="/vmi/css/style_rep.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">   
  <!-- Include JavaScript libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="datatables.min.js"></script>
  <script src="client_v2.js"></script>
</head>
<body>
  <main class="table">
    <section class="table__header">       
      <h1>
        <!-- Display company logo -->
        <img src="/vmi/images/company_<?php echo $companyId; ?>.png" onerror="handleImageError(this)" alt="" style="max-height: 4.5rem">
        Service Portal
      </h1>
    </section>
    <section class="table__body">
      <!-- Filter dropdown -->
      <div class="filter" style="display: flex; left: 30px; top:15px;">
        <?php
        // Prepare and execute SQL statement to fetch groups
        $sel = "SELECT group_id, group_name FROM site_groups WHERE client_id = ? ORDER BY group_name";
        $stmt = $conn->prepare($sel);
        $stmt->bind_param("d", $companyId);
        $stmt->execute();
        $stmt->store_result();
        echo '<select name="group_filter" id="group_filter" class="group_filter">';
          echo '<option value="">Select Group</option>'; 
          echo '<option value="def">Show All</option>'; // Default option
          if($stmt->num_rows > 0) {
              // Bind the columns to variables
              $stmt->bind_result($group_id, $group_name);
              while($stmt->fetch()) {
                  echo '<option value="' . htmlspecialchars($group_id) . '">' . htmlspecialchars($group_name) . '</option>';
              }
          }
        echo '</select>';
        ?>  
      </div>
      <div class="test" id="test">
        <!-- Data table -->
        <table id="customers_table">
          <thead>
            <tr>
              <th style="border-top-left-radius: 0.5rem;"></th>
              <th>Company Name</th>
              <th>Date</th>
              <th>Time</th>
              <th>UID</th>
              <th>Distributor Name</th>
              <th>Reseller Name</th>
              <th>Site Name</th>
              <th>Signal</th>
              <th>Phone</th>
              <th style="border-top-right-radius: 0.5rem;">Email</th>
            </tr>
          </thead>
          <tbody id="bodtest">
            <?php
            // SQL query to fetch data
            $sql = "
            SELECT
              cos.uid AS uid,
              clc.Client_name,
              dr.Dist_name,
              rs.reseller_name,
              cos.device_type,
              cos.last_conndate,
              cos.last_conntime,
              cs.Site_name AS sname,
              cs.Site_id,
              clc.client_id,
              cos.cs_signal,
              clc.Client_phone,
              clc.Client_email
            FROM Sites AS cs
            JOIN Tanks AS ts ON (cs.client_id, cs.uid, cs.Site_id) = (ts.client_id, ts.uid, ts.Site_id)
            JOIN products AS ps ON ps.product_id = ts.product_id
            JOIN Console_Asociation AS ca ON (ca.uid, ca.Client_id) = (ts.uid, ts.Client_id)
            JOIN console AS cos ON cos.uid = cs.uid
            JOIN Clients AS clc ON clc.client_id = cs.client_id
            JOIN Reseller AS rs ON rs.reseller_id = ca.reseller_id
            JOIN Distributor AS dr ON dr.dist_id = ca.dist_id
            WHERE cos.device_type in (10, 20, 30)
            GROUP BY cs.Site_id, clc.client_id
            ORDER BY Client_name ASC, Site_name ASC;
            ";
            $result = $conn->query($sql);
            // Check if results are returned
            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                // Sanitize data before output
                $subid = htmlspecialchars($row["client_id"]);
                $site_id = htmlspecialchars($row["Site_id"]);
                $uid = htmlspecialchars($row["uid"]);
                $cs_type = htmlspecialchars($row["device_type"]);
                $client_name = htmlspecialchars($row['Client_name']);
                $last_conndate = htmlspecialchars($row["last_conndate"] ?? '');
                $last_conntime = htmlspecialchars($row["last_conntime"] ?? '');
                $dist_name = htmlspecialchars($row["Dist_name"]);
                $reseller_name = htmlspecialchars($row["reseller_name"]);
                $sname = htmlspecialchars($row["sname"]);
                $cs_signal = htmlspecialchars((string)($row["cs_signal"] ?? 0), ENT_QUOTES, 'UTF-8');
                $client_phone = htmlspecialchars($row["Client_phone"] ?? '');
                $client_email = htmlspecialchars($row["Client_email"] ?? '');
                // Output table row
                echo "<tr data-uid='{$uid}' data-cs_type='{$cs_type}' data-site_id='{$site_id}' data-client_id='{$subid}'>";
                  echo "<td class='dt-control'></td>";                                
                  echo "<td>{$client_name}</td>";
                  echo "<td>{$last_conndate}</td>";
                  echo "<td>{$last_conntime}</td>";
                  echo "<td>{$uid}</td>";
                  echo "<td>{$dist_name}</td>";                          
                  echo "<td>{$reseller_name}</td>";
                  echo "<td>{$sname}</td>"; 
                  echo "<td>{$cs_signal} / 31</td>";       
                  echo "<td class='editable' data-column='Client_phone'>{$client_phone}</td>";
                  echo "<td class='editable' data-column='Client_email'>{$client_email}</td>";                                                                 
                echo "</tr>";
              }
            } else {
              // Display message if no data is found
              echo "<tr><td colspan='11'>No data found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
<!-- Pass companyId to JavaScript -->
<script>
  var companyId = <?php echo json_encode($companyId); ?>;
</script>
</html>
