<?php
include('../db/dbh2.php');
include('../db/log.php');   
include('../db/border.php');  

?>
<!DOCTYPE html><!--   -->

<html data-wf-page="65014a9e5ea5cd2c6534f24f" data-wf-site="65014a9e5ea5cd2c6534f1c8">
<head>
  <meta charset="utf-8">
  <title>Reports</title>
  <meta property="og:type" content="website">
  <meta content="summary_large_image" name="twitter:card">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <link rel="icon" href="/vmi/images/favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="/vmi/images/favicon.ico" type="image/x-icon">
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>
     <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>
  <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
</head>
<body>
  <?php 
    $currentDate = date('Y-m-d');
    $subtractedDate1 = date('Y-m-d', strtotime('-1 days', strtotime($currentDate)));
    $subtractedDate30 = date('Y-m-d', strtotime('-1 month', strtotime($currentDate)));
    $subtractedDate60 = date('Y-m-d', strtotime('-2 month', strtotime($currentDate)));
    $subtractedDate12m = date('Y-m-d', strtotime('-12 month', strtotime($currentDate)));
  ?> 
<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="sidebar-spacer"></div>
    <div class="sidebar-spacer2"></div>
    <div class="dashboard-content">
      <div class="dashboard-main-content">
        <div class="container-default w-container">
          <div class="mg-bottom-32px">
            <div class="_2-items-wrap-container">
              <div id="w-node-_4e606362-eabc-753a-260a-8d85f152b3ca-6534f24f">
                <h1 class="display-4 mg-bottom-4px" style="color: #EC1C1C">Client Reports</h1>
                <p class="mg-bottom-0"></p>
              </div>
            </div>
          </div>
          <div class="mg-bottom-16px">
            <div class="small-details-card-grid">
              <a href="/vmi/clients/" class="card-link">
                <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card top-details">
                  <div class="flex-horizontal justify-space-between">
                    <div class="flex align-center gap-column-4px"><img src="/vmi/images/pageviews-icon-dashdark-webflow-template.svg" loading="eager" alt="Pageviews - Dashdark X Webflow Template" class="max-h-16px">
                      <div class="text-100 medium mg-top-2px" style="color:white;">Active Sites(Last 30 Days)</div>
                    </div>
                  </div>
                  <div class="flex align-center gap-column-6px">
                    <div class="display-4"> 
                      <?php
                        //total sites
                        if (!empty($companyId) && $companyId != 15100) {
                          $companyName = "SELECT COUNT(*) as count2 FROM (SELECT DISTINCT(ct.uid), ct.tank_id, MAX(transaction_date) as last_transaction_date, capacity,current_volume, ullage, current_percent, ts.client_id FROM Tanks as ts join client_transaction as ct ON (ts.uid, ts.tank_id) = (ct.uid, ct.tank_id) JOIN Clients as cls on cls.client_id = ts.client_id WHERE ts.client_id = $companyId or cls.reseller_id = $companyId GROUP BY ts.Site_id) AS last_transactions WHERE last_transaction_date >= '$subtractedDate30'";
                          $resulttest = $conn->query($companyName);
                        }
                        elseif ($companyId == 15100) {
                          $companyName = "SELECT COUNT(*) as count2 FROM (SELECT DISTINCT(ct.uid), ct.tank_id, MAX(transaction_date) as last_transaction_date, capacity,current_volume, ullage, current_percent, client_id FROM Tanks as ts join client_transaction as ct ON (ts.uid, ts.tank_id) = (ct.uid, ct.tank_id) GROUP BY uid, tank_id, client_id) AS last_transactions WHERE last_transaction_date >= '$subtractedDate30'";
                          $resulttest = $conn->query($companyName);
                        }
                        //print the result
                        if ($resulttest->num_rows > 0) {
                          $row = $resulttest->fetch_assoc();
                          echo $row["count2"];
                          $count2 = $row["count2"];
                        }
                        //percentage
                        if (!empty($companyId) && $companyId != 15100) {
                          $companyName = "SELECT COUNT(*) as countprev FROM (SELECT DISTINCT(ct.uid), ct.tank_id, MAX(transaction_date) as last_transaction_date, capacity,current_volume, ullage, current_percent, client_id FROM Tanks as ts join client_transaction as ct ON (ts.uid, ts.tank_id) = (ct.uid, ct.tank_id) WHERE client_id = $companyId GROUP BY uid, tank_id, client_id) AS last_transactions WHERE last_transaction_date BETWEEN '$subtractedDate60' and  '$subtractedDate30'";
                          $resulttest = $conn->query($companyName);
                        }
                        elseif ($companyId == 15100) {
                          $companyName = "SELECT COUNT(*) as countprev FROM (SELECT DISTINCT(ct.uid), ct.tank_id, transaction_date as last_transaction_date, capacity,current_volume, ullage, current_percent, client_id FROM Tanks as ts join client_transaction as ct ON (ts.uid, ts.tank_id) = (ct.uid, ct.tank_id) GROUP BY uid, tank_id, client_id) AS last_transactions WHERE last_transaction_date BETWEEN '$subtractedDate60' and  '$subtractedDate30'";
                          $resulttest = $conn->query($companyName);
                        }
                        //print the result
                        if ($resulttest->num_rows > 0) {
                          $row = $resulttest->fetch_assoc();
                          $countprev = $row["countprev"];
                        }
                        else{
                          $countprev = 0;
                        }     
                        //calc        
                        if($countprev > 0){
                          $countres = round(((($count2 - $countprev)/ $countprev)*100), 1);
                        }
                        elseif (!empty($count2)) {
                          $countres = 100;
                        }
                        else{
                          $countres = 0;
                        }
                      ?>
                    </div>
                    <div>
                      <?php if($countres>=0)
                        {
                      ?>
                      <div class="status-badge green">
                        <?php } 
                          else {
                        ?>
                        <div class="status-badge red">
                          <?php
                            }
                          ?>
                          <div class="flex align-center gap-column-4px">
                            <div class="paragraph-small">
                              <?php
                                echo "$countres%";
                              ?>
                            </div>
                          </div>
                          <?php if($countres>=0)
                            {
                          ?>
                          <div class="dashdark-custom-icon icon-size-9px">↗</div>
                          <?php } 
                            else {
                          ?>
                          <div class="dashdark-custom-icon icon-size-9px">↘</div>
                          <?php
                            }
                          ?>
                        </div>
                    </div>
                  </div>
                </div>
              </a>
              <a href="/vmi/reports/transactions" class="card-link">
                <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card top-details">
                  <div class="flex-horizontal justify-space-between">
                    <div class="flex align-center gap-column-4px"><img src="/vmi/images/monthly-users-icon-dashdark-webflow-template.svg" loading="eager" alt="Monthly Users - Dashdark X Webflow Template" class="max-h-16px">
                      <div class="text-100 medium mg-top-2px" style="color:white;">Total Transactions(Last 30 Days)</div>
                    </div>
                  </div>
                  <div class="flex align-center gap-column-6px">
                    <div class="display-4">
                      <?php
                        if (!empty($companyId) && $companyId != 15100) {
                          $companyName = "SELECT count(*) as tran FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid WHERE (client_id = $companyId or reseller_id = $companyId) and transaction_date >= '$subtractedDate30';";
                          $resulttest = $conn->query($companyName);
                        }
                        elseif ($companyId == 15100) {
                            $companyName = "SELECT count(*) as tran FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid WHERE transaction_date >= '$subtractedDate30';";
                            $resulttest = $conn->query($companyName);
                        }
                        if ($resulttest->num_rows > 0) {
                            $row = $resulttest->fetch_assoc();
                            $tran = $row["tran"];
                            echo $tran;
                        }
                      ?>
                    </div>
                    <div>
                      <?php
                        if (!empty($companyId) && $companyId != 15100) {
                            $companyName = "SELECT count(*) as tran2 FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid WHERE client_id = $companyId and transaction_date BETWEEN '$subtractedDate60' AND '$subtractedDate30';";
                            $resulttest = $conn->query($companyName);
                        }
                        elseif ($companyId == 15100) {
                          $companyName = "SELECT count(*) as tran2 FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid WHERE transaction_date BETWEEN '$subtractedDate60' AND '$subtractedDate30';";
                          $resulttest = $conn->query($companyName);
                        }
                        if ($resulttest->num_rows > 0) {
                          $row = $resulttest->fetch_assoc();
                          $tran2 = $row["tran2"];
                          if ($tran2 > 0) {
                            // Correctly calculating percentage increase
                            $rest = number_format((($tran / $tran2 - 1) * 100), 1);
                          } elseif (!empty($tran)) {
                          // If there were no sales in the earlier period, the increase is 100%
                          $rest = 100;
                          } else {
                            // No sales in either period
                            $rest = 0;
                          }
                        }
                      ?>
                      <?php if($rest>=0)
                        {
                      ?>
                      <div class="status-badge green">
                        <?php } 
                          else {
                        ?>
                        <div class="status-badge red">
                          <?php
                            }
                          ?>
                          <div class="flex align-center gap-column-4px">
                            <div class="paragraph-small">
                              <?php
                                echo $rest . "%";
                              ?>
                            </div>
                          </div>
                          <?php if($rest>=0)
                            {
                          ?>
                          <div class="dashdark-custom-icon icon-size-9px">↗</div>
                          <?php } 
                            else {
                          ?>
                          <div class="dashdark-custom-icon icon-size-9px">↘</div>
                          <?php
                            }
                          ?>
                        </div>
                      </div>
                    </div>
                  </div>  
              </a>                  
              <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card top-details">
                  <?php
                    if (!empty($companyId) && $companyId != 15100) {
                      $companyName = "SELECT sum(dispensed_volume) as sum FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid where (client_id = $companyId or reseller_id = $companyId) and transaction_date >= '$subtractedDate30';";
                      $resulttest = $conn->query($companyName);
                    }
                    elseif ($companyId == 15100) {
                      $companyName = "SELECT sum(dispensed_volume) as sum FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid where transaction_date >= '$subtractedDate30';";
                      $resulttest = $conn->query($companyName);
                    }
                    if ($resulttest->num_rows > 0) {
                        $row = $resulttest->fetch_assoc();
                        $sum = $row["sum"];
                    }                   
                  ?>
                  <div class="flex-horizontal justify-space-between">
                    <div class="flex align-center gap-column-4px"><img src="/vmi/images/new-sign-ups-icon-dashdark-webflow-template.svg" loading="eager" alt="New Sign Ups - Dashdark X Webflow Template" class="max-h-16px">
                      <div class="text-100 medium mg-top-2px" style="color:white;">Total Volume(Last 30 Days)</div>
                    </div>
                    <div class="dashdark-custom-icon details-icon">...</div>
                  </div>
                  <div class="flex align-center gap-column-6px">
                    <div class="display-4">
                        <?php
                        if(!empty($sum)){
                           echo number_format($sum,1) . "L";
                        }
                        else{
                          $sum = 0;
                          echo $sum . "L";
                        }
                        ?>
                    </div>
                    <?php
                      if (!empty($companyId) && $companyId != 15100) {
                        $companyName = "SELECT sum(dispensed_volume) as sum2 FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid where (client_id = $companyId or reseller_id = $companyId) and transaction_date BETWEEN '$subtractedDate60' AND '$subtractedDate30';";
                        $resulttest = $conn->query($companyName);
                      }
                      elseif ($companyId == 15100) {
                        $companyName = "SELECT sum(dispensed_volume) as sum2 FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid where transaction_date BETWEEN '$subtractedDate60' AND '$subtractedDate30';";
                        $resulttest = $conn->query($companyName);
                      }
                      if ($resulttest->num_rows > 0) {
                        $row = $resulttest->fetch_assoc();
                        $sum2 = $row["sum2"];
                        if($sum2>0){
                        $res = (($sum/$sum2 - 1)*100);
                        }
                        elseif(!empty($sum)){
                          $res = 100;
                        }
                        else{
                          $res = 0;
                        }
                      }
                      else{
                        $res = 0;
                      }
                    ?>
                    <div>
                      <?php if($res>=0)
                        {
                      ?>
                      <div class="status-badge green">
                      <?php } 
                      else {
                        ?>
                        <div class="status-badge red">
                          <?php
                      }
                      ?>
                        <div class="flex align-center gap-column-4px">
                          <div class="paragraph-small">
                          <?php
                          if(!empty($res)){
                            echo number_format($res,1) . "%";
                          }
                          else{
                            echo "0%";
                          }
                        ?>
                          </div>
                        </div>
                        <?php if($rest>=0)
                        {
                      ?>
                      <div class="dashdark-custom-icon icon-size-9px">↗</div>
                      <?php } 
                      else {
                        ?>
                        <div class="dashdark-custom-icon icon-size-9px">↘</div>
                          <?php
                      }
                      ?>
                      </div>
                    </div>
                  </div>
              </div>
              <a href="/vmi/reports/total_deliveries" class="card-link">
                <div id="w-node-_9745c905-0e47-203d-ac6e-d1bee1ec357d-e1ec357d" class="card top-details">
                  <div class="flex-horizontal justify-space-between">
                    <div class="flex align-center gap-column-4px"><img src="/vmi/images/subscriptions-icon-dashdark-webflow-template.svg" loading="eager" alt="Subscriptions - Dashdark X Webflow Template" class="max-h-16px">
                      <div class="text-100 medium mg-top-2px" style="color:white;">Total deliveries(Last 30 Days)</div>
                    </div>
                    <div class="dashdark-custom-icon details-icon">...</div>
                  </div>
                  <?php
                    if (!empty($companyId) && $companyId != 15100) {
                        $companyName = "SELECT sum(delivery) as del FROM delivery_historic as dth JOIN Console_Asociation as ca ON dth.uid = ca.uid where ((client_id = $companyId or reseller_id = $companyId)) and transaction_date >= '$subtractedDate30' and tank_id != 99;";
                        $resulttest = $conn->query($companyName);

                        if ($resulttest->num_rows > 0) {
                            $row = $resulttest->fetch_assoc();
                            $del = $row["del"];
                        }

                        $companyName2 = "SELECT sum(delivery) as del2 FROM delivery_historic as dth JOIN Console_Asociation as ca ON dth.uid = ca.uid where ((client_id = $companyId or reseller_id = $companyId)) and transaction_date BETWEEN '$subtractedDate60' and '$subtractedDate30' and tank_id != 99;";
                        $resulttest2 = $conn->query($companyName2);

                        if ($resulttest2->num_rows > 0) {
                            $row = $resulttest2->fetch_assoc();
                            $del2 = $row["del2"];
                        }
                    }
                    if ($companyId == 15100) {
                        $companyName = "SELECT sum(delivery) as del FROM delivery_historic as dth JOIN Console_Asociation as ca ON dth.uid = ca.uid where transaction_date >= '$subtractedDate30' and tank_id != 99;";
                        $resulttest = $conn->query($companyName);

                        if ($resulttest->num_rows > 0) {
                            $row = $resulttest->fetch_assoc();
                            $del = $row["del"];                                    
                        }

                        $companyName2 = "SELECT sum(delivery) as del2 FROM delivery_historic as dth JOIN Console_Asociation as ca ON dth.uid = ca.uid where transaction_date BETWEEN '$subtractedDate60' and '$subtractedDate30' and tank_id != 99;";
                        $resulttest2 = $conn->query($companyName2);

                        if ($resulttest2->num_rows > 0) {
                            $row = $resulttest2->fetch_assoc();
                            $del2 = $row["del2"];                                   
                        }
                    }
                    if($del2>0){
                      $resul = $del - $del2;
                      $del2sum = (($resul)/$del2)*100;
                      }
                    else{
                      $del2sum = ($del - 1);
                    }                
                  ?>
                  <div class="flex align-center gap-column-6px">
                    <div class="display-4">
                      <?php
                        if(!empty($del)){
                            echo number_format($del,1). "L";
                        }
                        else{
                          echo "0L";
                          $del = 0; //need to check
                          $del2sum = 0; //need to check
                        }
                      ?>
                    </div>
                    <div>
                      <?php if($del2sum>=0)
                        {
                      ?>
                      <div class="status-badge green">
                        <?php } 
                          else {
                            ?>
                            <div class="status-badge red">
                              <?php
                          }
                        ?>
                        <div class="flex align-center gap-column-4px">
                          <div class="paragraph-small">
                            <?php
                              if(!empty($del2sum)){
                                echo number_format($del2sum,1) . "%";
                              }
                              else{
                                echo "0%";
                              }
                            ?>
                          </div>
                        </div>
                        <?php if($del2sum>=0)
                          {
                        ?>
                        <div class="dashdark-custom-icon icon-size-9px">↗</div>
                        <?php } 
                          else {
                        ?>
                        <div class="dashdark-custom-icon icon-size-9px">↘</div>
                        <?php
                          }
                        ?>
                      </div>
                    </div>
                  </div>
                </div>
              </a>
            </div>
          </div>
          <div class="mg-bottom-40px">
            <div class="card">
              <div class="grid-2-columns _1-82fr---1fr gap-0">
                <div id="w-node-_75f748dc-9ded-293d-f0a3-80b0ff3921a6-6534f24f" class="border-right-6px---secondary-4 border-bottom-6px---secondary-4-mbl">
                  <div id="w-node-e4606960-bd5c-8b3f-1d6d-99e18036d7a5-8036d7a5" class="graph-large-section-container">
                    <div class="_2-items-wrap-container mg-bottom-24px">
                      <div>
                        <div class="text-200 medium mg-bottom-10px">Total Volume</div>
                        <div class="flex align-center gap-column-6px">
                          <div class="display-4">
                            <?php 
                            if($companyId == 15100)
                            {
                              $sql = "SELECT sum(dispensed_volume) as totsum FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid WHERE transaction_date > $subtractedDate12m ";
                            }
                            else{
                              $sql = "SELECT sum(dispensed_volume) as totsum FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid where (client_id =  $companyId or reseller_id = $companyId) AND transaction_date > $subtractedDate12m";
                            }
                              $resultsql = $conn->query($sql);
                              if ($resultsql->num_rows > 0) {
                                  $row = $resultsql->fetch_assoc();
                                  if(!empty($row["totsum"])){
                                  $sum2 = number_format($row["totsum"], 1);
                                  echo $sum2 . "L";
                                  }
                                  else{
                                    echo "0L";
                                  }
                            }
                            ?>
                      </div>
                        </div>
                      </div>
                    </div>
                    <div>
                      <?php 
                      if($companyId == 15100)
                      {
                        $sql = "SELECT SUM(dispensed_volume) as total_dispensed_volume, DATE_FORMAT(transaction_date, '%Y-%m') AS month_year
                        FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid  
                        GROUP BY month_year";
                      }
                      else{
                      $sql = "SELECT SUM(dispensed_volume) as total_dispensed_volume, DATE_FORMAT(transaction_date, '%Y-%m') AS month_year
                              FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid  WHERE (client_id = $companyId or reseller_id = $companyId)
                              GROUP BY month_year";
                      }
                      $result = $conn->query($sql);

                      $data = [];
                      while ($row = $result->fetch_assoc()) {
                          $data[] = [
                              'month_year' => $row['month_year'],
                              'total_dispensed_volume' => $row['total_dispensed_volume'],
                          ];
                      }


                      // Convert PHP array to JSON for JavaScript
                      $data_json = json_encode($data);
                      ?>
                      <canvas id="myChart" width="400" height="200"></canvas>
                    </div>
                  </div>
                </div>
                <div id="w-node-_270ba7dd-4cbf-fe59-201e-ecc80a9bb262-6534f24f" class="grid-1-column _2-small-sections-grid">
                  <div class="graph-small-section-container">
                    <div class="mg-bottom-4px">
                      <div class="flex align-center gap-column-4px"><img src="/vmi/images/total-sessions-icon-left-dashdark-webflow-ecommerce-template.svg" loading="eager" alt="Total Sessions - Dashdark X Webflow Template" class="max-h-16px">
                        <div class="text-100 medium mg-top-2px" style="color:white;">Total Transactions</div>
                      </div>
                    </div>
                    <div class="mg-bottom-24px">
                      <div class="flex align-center gap-column-6px">
                        <div class="display-4">
                        <?php 
                          if($companyId == 15100)
                          {
                          $sql = "SELECT count(*) as tottrans FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid ";
                          }
                          else{
                              $sql = "SELECT count(*) as tottrans FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid  where client_id =  $companyId or reseller_id = $companyId";
                          }
                              $resultsql = $conn->query($sql);
                              if ($resultsql->num_rows > 0) {
                                  $row = $resultsql->fetch_assoc();
                                  $transactions = $row["tottrans"];
                                  echo $transactions;
                            }
                            ?>
                        </div>
                      </div>
                    </div>
                    <div>
                    <?php 
                    if($companyId == 15100)
                    {
                      $sql2 = "SELECT count(*) as total_transactions, DATE_FORMAT(transaction_date, '%Y-%m') AS mth_year
                      FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid 
                      GROUP BY mth_year";
                    }else{
                      $sql2 = "SELECT count(*) as total_transactions, DATE_FORMAT(transaction_date, '%Y-%m') AS mth_year
                              FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid  WHERE (client_id = $companyId or reseller_id = $companyId)
                              GROUP BY mth_year";
                    }
                      $result2 = $conn->query($sql2);

                      $data2 = [];
                      while ($row = $result2->fetch_assoc()) {
                          $data2[] = [
                              'mth_year' => $row['mth_year'],
                              'total_transactions' => $row['total_transactions'],
                          ];
                      }


                      // Convert PHP array to JSON for JavaScript
                      $data_json2 = json_encode($data2);
                      ?>
                      <canvas id="myChart2" width="400" height="200"></canvas>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div> 
          <div class="mg-bottom-40px">
            <div class="card">
              <div class="grid-2-columns _1fr---1fr gap-0">
                <div id="w-node-_75f748dc-9ded-293d-f0a3-80b0ff3921a6-6534f24f" class="border-right-6px---secondary-4 border-bottom-6px---secondary-4-mbl" style="padding-left: 1rem; padding-right:1rem">
                  <a href="/vmi/Fuel-tax" style="text-decoration: none; color: inherit;">
                    <div class="graph-small-section-container">
                      <div class="mg-bottom-4px">
                        <div class="flex align-center gap-column-4px">
                          <div class="text-100 medium mg-top-2px" style="color:white;">Fuel Tax</div>
                        </div>
                      </div>
                    </div>
                    <div>
                      <div class="_2-items-wrap-container">
                        <div class="flex align-center gap-column-8px">
                          <div class="text-200 medium">Tax Value</div>
                        </div>
                        <div class="text-200 medium">Volume</div>
                        <div class="text-200 medium">Total Tax</div>
                      </div>
                      <div class="divider"></div>
                      <?php 
                        $sql2 = "SELECT 
                                      DISTINCT(ct.card_number), 
                                      ROUND(SUM(dispensed_volume), 2) AS volume, 
                                      ct.registration,
                                      ctb.tax_value, ctb.client_id,
                                      ROUND(ROUND(SUM(dispensed_volume), 2)/100 * ctb.tax_value, 2) as total
                                  FROM 
                                      client_transaction ct 
                                  JOIN 
                                      Console_Asociation ca 
                                  ON 
                                      ct.uid = ca.uid  
                                  JOIN 
                                      client_tasbax ctb 
                                  ON 
                                      (ctb.card_number) = (ct.card_number)
                                  WHERE 
                                      ct.uid IN (SELECT uid FROM console WHERE device_type != 999) 
                                      AND ct.dispensed_volume > 0 
                                      AND (ca.client_id = $companyId OR ca.reseller_id = $companyId OR ca.dist_id = $companyId)
                                      AND ct.transaction_date > $subtractedDate12m
                                  GROUP BY 
                                      tax_value
                                  ORDER BY 
                                      volume DESC;";
                        $result2 = $conn->query($sql2);

                        if ($result2->num_rows > 0) {
                          while ($row = $result2->fetch_assoc()) {
                            $tax       = htmlspecialchars(number_format($row['tax_value'], 2, '.', ','));
                            $tot_volume = htmlspecialchars(number_format($row['volume'], 2, '.', ','));
                            $total_tax = htmlspecialchars(number_format($row['total'], 2, '.', ','));
                      ?>
                      <div class="_2-items-wrap-container">
                        <div class="flex align-center gap-column-8px">
                          <div class="small-dot"></div>
                          <div class="text-200"><?php echo $tax; ?></div>
                        </div>
                        <div class="text-200"><?php echo $tot_volume . "L"; ?></div>
                        <div class="text-300 medium color-neutral-200"><?php echo "$" . $total_tax; ?></div>
                      </div>
                      <div class="divider"></div>
                      <?php
                          }
                        } else {
                          echo "<p>No data found.</p>";
                        }
                      ?>
                    </div>
                  </a>
                </div>
                <?php
                  $sql3 = "SELECT SUM(cr.Opening_balance) AS opening,
                  SUM(cr.Closing_balance) AS closing,
                  SUM(cr.Delta) AS dip
                  FROM clients_recconciliation cr
                  JOIN Sites st ON (st.uid = cr.uid AND st.Site_id = cr.Site_id)
                  JOIN Console_Asociation ca ON cr.uid = ca.uid
                  WHERE 1=1 AND (cr.Client_id = $companyId OR ca.reseller_id = $companyId OR ca.dist_id = $companyId)";
          
                  $result3 = $conn->query($sql3);
                  
                  if ($result3->num_rows > 0) {
                      $row = $result3->fetch_assoc();
                      $opening = htmlspecialchars($row['opening']);
                      $closing = htmlspecialchars($row['closing']);
                      $dip = htmlspecialchars($row['dip']);
                  } else {
                      $opening = "N/A";
                      $closing = "N/A";
                      $dip = "N/A";
                  }
                ?>
                <div id="w-node-_270ba7dd-4cbf-fe59-201e-ecc80a9bb262-6534f24f" class="grid-1-column _2-small-sections-grid" style="padding-left: 1rem; padding-right:1rem;">
                  <a href="/vmi/Reconciliation" style="text-decoration: none; color: inherit;">
                    <div class="graph-small-section-container">
                      <div class="mg-bottom-4px">
                        <div class="flex align-center gap-column-4px">
                          <div class="text-100 medium mg-top-2px" style="color:white;">Reconciliation</div>
                        </div>
                      </div>
                    </div>
                    <div>
                      <div class="_2-items-wrap-container">
                        <div class="flex align-center gap-column-8px">
                          <div class="text-200 medium">Opening Balance</div>
                        </div>
                        <div class="text-200 medium">Closing Balance</div>
                        <div class="text-200 medium">Dip</div>
                      </div>
                      <div class="divider"></div>
                      <div class="_2-items-wrap-container">
                        <div class="flex align-center gap-column-8px">
                          <div class="text-200"><?php echo number_format($opening, 2); ?></div>
                        </div>
                        <div class="text-200"><?php echo number_format($closing, 2); ?></div>
                        <div class="text-300 medium color-neutral-200"><?php echo number_format($dip, 2) ?></div>
                      </div>
                      <div class="divider"></div>
                      <?php
                        //   }
                        // } else {
                        //   echo "<p>No data found.</p>";
                        // }
                      ?>
                    </div>
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div class="mg-bottom-24px">
            <div class="_2-items-wrap-container align-end">
            </div>
          </div>
          <div class="mg-bottom-24px">
                  <div class="grid-2-columns gap-20px">
                    <div id="w-node-afd54bfe-8a78-7961-b11b-7a8bd9f9254d-d9f9254d" class="card pd-30px---36px">
                      <div class="mg-bottom-40px">
                        <div class="flex-horizontal justify-space-between">
                          <div><h2 class="display-4" style="color: White">Consumption (Last 30 Days)</h2></div>        
                          <div class="dashdark-custom-icon details-icon">...</div>             
                        </div>                    
                      </div>
                      <?php
                              if (!empty($companyId) && $companyId != 15100) {
                                  $sql = "SELECT sum(dispensed_volume) as tot, card_holder_name FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid  
                                  where (client_id = $companyId or reseller_id = $companyId) group by card_holder_name order by tot desc LIMIT 6;";
                              } elseif ($companyId == 15100) {
                                  $sql = "SELECT sum(dispensed_volume) as tot, card_holder_name FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid  
                                  group by card_holder_name order by tot desc LIMIT 6;";
                              }

                              $resultsql = $conn->query($sql);
                              $i = 1; // Initialize the counter outside the loop

                              while ($row = $resultsql->fetch_assoc()) {
                                  $holder = $row["card_holder_name"];
                                  if(!empty($row["tot"])){
                                  $tot = number_format($row["tot"],1);
                                  }
                                  else{
                                    $tot = 0;
                                  }
                                  ?>
                                    <div class="_2-items-wrap-container">
                                      <div class="flex align-center gap-column-8px">
                                        <div class="small-dot"></div>
                                        <div class="text-200"><?php echo $holder ?></div>
                                      </div>
                                      <div class="text-300 medium color-neutral-200"><?php echo $tot ?></div>
                                    </div>
                                    <div class="divider"></div>
                                  <?php
                                  $i++; // Increment the counter
                              }
                              ?>
                      
                    </div>
                    <div id="w-node-_38b4a65e-7f82-4f13-d3fc-261e38ea4042-6534f24f">
                      <div id="w-node-_28f58b49-e289-bfad-3672-3ddb30359092-30359092" class="grid-1-column">
                        <div class="card overflow-hidden">
                          <div class="_2-items-wrap-container pd-32px---28px">
                            <div class="text-300 medium color-neutral-100">Recent orders</div>
                          </div>
                          <div class="table-main-container">
                            <div class="recent-orders-table-row table-header">
                              <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px"><img src="/vmi/images/order-table-header-icon-dashdark-webflow-template.svg" loading="eager" alt="">
                                <div class="text-50 semibold color-neutral-100">Order</div>
                              </div>
                              <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px"><img src="/vmi/images/date-table-header-icon-dashdark-webflow-template.svg" loading="eager" alt="">
                                <div class="text-50 semibold color-neutral-100">Date</div>
                              </div>
                              <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px"><img src="/vmi/images/status-table-header-icon-dashdark-webflow-template.svg" loading="eager" alt="">
                                <div class="text-50 semibold color-neutral-100">Site</div>
                              </div>
                              <div id="w-node-e477ce2d-e7d4-4ff4-1cc7-a3854c37ad1b-30359092">
                                <div id="w-node-_81082dec-aeb1-55cb-ca1b-8966496bda6e-496bda6e" class="flex align-center gap-column-6px">
                                  <div class="text-50 semibold color-neutral-100">dispensed_volume</div>
                                </div>
                              </div>
                            </div>
                            <?php
                              if (!empty($companyId) && $companyId != 15100) {
                                  $sql = "SELECT transaction_date, transaction_time, ct.uid, dispensed_volume, st.Site_name FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid JOIN Sites as st 
                                  on st.uid = ct.uid  where ca.client_id = $companyId or ca.reseller_id = $companyId ORDER BY CONCAT(transaction_date, ' ', transaction_time) DESC LIMIT 6;";
                              } elseif ($companyId == 15100) {
                                  $sql = "SELECT transaction_date, transaction_time, ct.uid, dispensed_volume, st.Site_name FROM client_transaction as ct JOIN Console_Asociation as ca on ct.uid = ca.uid JOIN Sites as st 
                                  on st.uid = ct.uid  ORDER BY CONCAT(transaction_date, ' ', transaction_time) DESC LIMIT 6";
                              }

                              $resultsql = $conn->query($sql);
                              $i = 1; // Initialize the counter outside the loop

                              while ($row = $resultsql->fetch_assoc()) {
                                  $date = $row["transaction_date"];
                                  $time = $row["transaction_time"];
                                  $site = $row["Site_name"];
                                  $dispensed_volume = $row["dispensed_volume"];
                                  $dateString = $date . ' ' . $time;
                                  $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
                                  $formattedDate = $dateTime->format('M jS, h:i a');
                                  ?>
                                  <div class="recent-orders-table-row">
                                      <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a5f-1b609a5a" class="flex align-center">
                                          <div class="paragraph-small color-neutral-100"><?php echo "#" . $i ?></div>
                                      </div>
                                      <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a72-1b609a5a" class="paragraph-small color-neutral-100"><?php echo $formattedDate ?></div>
                                      <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a78-1b609a5a">
                                          <div>
                                              <div class="status-badge green">
                                                  <div class="flex align-center gap-column-4px">
                                                      <div class="small-dot _4px bg-green-300"></div>
                                                      <div class="paragraph-small"><?php echo $site ?></div>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                      <div id="w-node-_0b909741-5d5c-5abc-5f7b-bcb01b609a7e-1b609a5a" class="paragraph-small color-neutral-100"><?php echo $dispensed_volume . "L" ?></div>
                                  </div>
                                  <?php
                                  $i++; // Increment the counter
                              }
                              ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
          </div>
          <div class="card">
            <div class="grid-2-columns _1fr---2-05fr gap-0">
              <div id="w-node-b4227586-6384-6fdc-9144-f788b42bc8e8-b42bc8e6" class="percentage-bars-small-section-container">
                <div class="_2-items-wrap-container mg-bottom-24px">
                  <div class="grid-1-column gap-row-4px">
                    <div class="text-300 medium color-neutral-100">Volume by Site</div>
                  </div>
                </div>
                <div class="grid-1-column gap-row-24px">
                  <div id="w-node-b4227586-6384-6fdc-9144-f788b42bc8f3-b42bc8e6">
                    <?php
                      if (!empty($companyId) && $companyId != 15100) {
                          $sql = "SELECT sum(dispensed_volume) as tot1, ct.uid, st.Site_name FROM client_transaction as ct JOIN Sites as st ON ct.uid = st.uid JOIN Console_Asociation as ca on st.uid = ca.uid where st.client_id = $companyId or ca.reseller_id = $companyId group by uid order by tot1 desc LIMIT 5;";
                      } elseif ($companyId == 15100) {
                          $sql = "SELECT sum(dispensed_volume) as tot1, ct.uid, st.Site_name FROM client_transaction as ct JOIN Sites as st ON ct.uid = st.uid group by uid order by tot1 desc LIMIT 5;";
                      }

                      $resultsql = $conn->query($sql);
                      $i = 1; // Initialize the counter outside the loop

                      while ($row = $resultsql->fetch_assoc()) {
                        $uid = $row["Site_name"];
                        if(!empty($row["tot1"])){
                        $tot1 = number_format($row["tot1"],1);
                        }
                        else{
                          $tot1 = 0;
                        }
                        ?>
                          <div class="text-100"><?php echo $uid ?></div>
                            <div class="flex align-center gap-column-20px" style="justify-content: end;">                                    
                              <div class="text-100"><?php echo $tot1 . "L" ?></div>
                            </div>
                            <div class="divider"></div>
                        <?php
                        $i++; // Increment the counter
                      }
                    ?>
                  </div>
                </div>
              </div>
              <div id="w-node-b4227586-6384-6fdc-9144-f788b42bc91b-b42bc8e6" class="pd-20px---52px">
                <div class="inner-container _562px">
                  <div  id="map" style="height: 21rem"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>    
    </div>                      
  </div>
</div>
<div class="loading-bar-wrapper">
    <div class="loading-bar"></div>
  </div>
  <script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=65014a9e5ea5cd2c6534f1c8" type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
  <script src="/vmi/js/webflow.js" type="text/javascript"></script>
</body>
</html>
<script>
   var data = <?php echo $data_json; ?>;
    
    // Get the canvas element
    var ctx = document.getElementById('myChart').getContext('2d');
    
    // Create the combined chart (bar and line)
    var myChart = new Chart(ctx, {
        type: 'bar', // Use a bar chart as the base chart
        data: {
            labels: data.map(item => item.month_year),
            datasets: [
              {
                label: '',
                data: data.map(item => item.total_dispensed_volume),
                type: 'line',
                fill: true, // Fill the area under the line
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                cubicInterpolationMode: 'default', // Use 'monotone' for alternative smoothing
                lineTension: 0.4 // Increase this value for rounder curves (adjust as needed)              
            },
            {
                label: '',
                data: data.map(item => item.total_dispensed_volume),
                backgroundColor: '#6c72ff',
                borderColor: '#57c3ff',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total dispensed_volume'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month-Year'
                    }
                }
            }
        }
    });

    // Parse the JSON data generated by PHP
    var data2 = <?php echo $data_json2; ?>;
    
    // Get the canvas element
    var ctx = document.getElementById('myChart2').getContext('2d');
    
    // Create the combined chart (bar and line)
    var myChart2 = new Chart(ctx, {
      type: 'line', // Use a line chart type
    data: {
        labels: data2.map(item => item.mth_year),
        datasets: [{
            label: 'Total Transactions',
            data: data2.map(item => item.total_transactions),
            fill: false, // Fill the area under the line
            borderColor: '#57c3ff',
            borderWidth: 2,
        }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total dispensed_volume'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month-Year'
                    }
                }
            }
        }
    });

var map = L.map('map').setView([-27.5, 136], 4);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

// Define the URL of the server endpoint that returns the coordinates
var url = 'gps_call';
var markers = [];
// AJAX request to get the coordinates from your database
$.ajax({
    type: 'POST',    // Type of request (GET or POST)
    url: url,        // Endpoint URL
    data: {},        // Any necessary data you might need to send
    dataType: 'json', // Type of data expected back from the server
    success: function(response) {
        response.locations.forEach(function(location) {
            var marker = L.marker([location.lat, location.lng]).addTo(map)
                .bindPopup(location.name || 'No name provided');
            markers.push(marker);
        });

        // Create a new bounds object
        var bounds = new L.LatLngBounds();
        markers.forEach(function(marker) {
            bounds.extend(marker.getLatLng());
        });

        // Automatically adjust the map to show all markers
        map.fitBounds(bounds, { padding: [50, 50] }); // Adjust padding as needed
    },
    error: function(xhr, status, error) {
        console.error('Error: ' + error.message);
    }
});

</script>
