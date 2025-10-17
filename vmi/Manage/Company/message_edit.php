<?php
  include('../db/dbh2.php');
  include('../db/log.php');
  include('../db/border.php');
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=0.5">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Vendor Managed Inventory</title>
  <link rel="stylesheet" href="style.css"> 
  <link rel="stylesheet" href="/vmi/css/style_rep.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">   
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="datatables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<!-- <?php echo "company_" . $companyId; ?> -->
  <main class="table">
    <section class="table__header">       
      <h1><img src="/vmi/images/company_15100.png" alt="">Vendor Managed Inventory</h1>            
      <div class="export__file">
        <label for="export-file" class="export__file-btn" title="Export File"></label>
        <input type="checkbox" id="export-file">
        <div class="export__file-options">
          <label>Export As &nbsp; &#10140;</label>
          <label for="export-file" id="toCSV">CSV <img src="/vmi/images/csv.png" alt=""></label>
        </div>
      </div>
    </section>
    <section class="table__body">
      <div class="filter" style="position: relative; display: flex; left: 30px; top:15px;">
        <?php
        $sel = "SELECT group_id, group_name FROM ehonener_ehon_vmi.site_groups where client_id = ?";
        $stmt = $conn->prepare($sel);
        $stmt->bind_param("d", $companyId);
        $stmt->execute();
        $stmt->store_result();
        echo '<select name="group_filter" id="group_filter" class="group_filter">';
          echo '<option value="">Select Group</option>'; 
          echo '<option value="def">Show All</option>';// Default option
          if($stmt->num_rows > 0) {
              // Bind the columns to variables
              $stmt->bind_result($group_id,$group_name);
              while($stmt->fetch()) {
                  echo '<option value="' . $group_id . '">' . $group_name . '</option>';
              }
          }
        echo '</select>';
        ?>  
      </div>
      <div class="test" id ="test">
        <table id="customers_table">
          <thead>
            <tr>
              <th> Language</th>
              <th> Type</th>
              <th> Message</th>
            </tr>
          </thead>
          <tbody id="bodtest">
            <?php
              // Retrieve data from  table
              if ($companyId == 15100) {
                $sql = "SELECT * FROM messages";
                $result = $conn->query($sql);
                $petro = 1;
              }
              // Display data in an HTML table with borders and search functionality
              if ($result->num_rows > 0) {
                $i = 0;
                while ($row = $result->fetch_assoc()) {
                  $message_lang = $row["message_lang"];
                  $message_type = $row["message_type"];
                  $message_content= $row["message_content"];
                  
                  echo "<tr data-uid='{$uid}'>";                              
                    echo "<td>". $message_lang ."</td>";
                    echo "<td>". $message_type ."</td>";
                    echo "<td>". $message_content ."</td>";
                  echo "</tr>";                        
                }
              } else {
                exit;
              }
            ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
