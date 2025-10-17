<?php
include('../db/dbh2.php');
include('../db/log.php');   
include('../db/border.php');  

$query = "
    WITH max_fq AS (
    SELECT 
        uid, 
        tank_id,
        MAX(CONCAT(fq_date, ' ', fq_time)) AS max_fq_datetime
    FROM 
        fuel_quality
    GROUP BY 
        uid, 
        tank_id
)
SELECT 
    fq.uid, 
    fq.tank_id,
    DATE(fq.fq_date) AS last_fq_date, 
    TIME(fq.fq_time) AS last_fq_time,
    fq.particle_4um,
    fq.particle_6um,
    fq.particle_14um
FROM 
    fuel_quality fq
JOIN 
    max_fq mf ON fq.uid = mf.uid 
             AND fq.tank_id = mf.tank_id 
             AND CONCAT(fq.fq_date, ' ', fq.fq_time) = mf.max_fq_datetime
JOIN 
    Console_Asociation ca ON ca.uid = fq.uid
WHERE 
    ca.client_id = ? OR 
    ca.reseller_id = ? OR 
    ca.dist_id = ?;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $companyId, $companyId, $companyId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html data-wf-page="65014a9e5ea5cd2c6534f24f" data-wf-site="65014a9e5ea5cd2c6534f1c8">
<head>
  <meta charset="utf-8">
  <title>Reports</title>
  <meta property="og:type" content="website">
  <meta content="summary_large_image" name="twitter:card">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <link href="fq.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
</head>
<body>

<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="sidebar-spacer"></div>
    <div class="sidebar-spacer2"></div>
    <div class="dashboard-content">
      <div class="dashboard-main-content">
        <div class="container-default w-container">
          <div class="mg-top-16px">
            <div class="details-grid">
              <?php while($row = $result->fetch_assoc()): ?>
              <div class="fqcard top-details" onclick="window.location.href='fq-historic'" style="cursor:pointer;">
                <div class="flex-horizontal justify-space-between">
                  <div class="flex align-center gap-column-4px">
                    <div class="text-100 medium mg-top-2px" style="color:white; font-size: 24px;">
                      Tank: <?php echo htmlspecialchars($row['tank_id']); ?> - ID: <?php echo htmlspecialchars($row['uid']); ?>
                    </div>
                  </div>
                </div>
                <div class="flex align-center gap-column-6px" style="align-self:center;">
                  <div class="display-64">
                    <?php echo htmlspecialchars($row['particle_4um']); ?>/<?php echo htmlspecialchars($row['particle_6um']); ?>/<?php echo htmlspecialchars($row['particle_14um']); ?>
                  </div>
                </div>
                <div class="flex align-center gap-column-6px" style="align-self:center;">
                  <div class="display-4">
                    <?php echo htmlspecialchars($row['last_fq_date']); ?> <?php echo htmlspecialchars($row['last_fq_time']); ?>
                  </div>
                </div> 
              </div>   
              <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>    
    </div>                      
  </div>
</div>

<script>
    // Establish WebSocket connection
    var socket = new WebSocket('ws://' + window.location.hostname + ':8001/ws/fuel-quality/');

    socket.onopen = function() {
        console.log('WebSocket connection established');
    };

    socket.onmessage = function(event) {
    console.log('Received data:', event.data); // Add this line
    try {
        var data = JSON.parse(event.data);
        console.log('Parsed data:', data); // Add this line

        if (data && Array.isArray(data)) {
            var detailsGrid = document.querySelector('.details-grid');
            detailsGrid.innerHTML = '';

            data.forEach(function(row) {
                var card = document.createElement('div');
                card.classList.add('fqcard', 'top-details');
                card.onclick = function() {
                    window.location.href = 'fq-historic';
                };
                card.style.cursor = 'pointer';
                card.innerHTML = `
                    <div class="flex-horizontal justify-space-between">
                        <div class="flex align-center gap-column-4px">
                            <div class="text-100 medium mg-top-2px" style="color:white; font-size: 24px;">
                                Tank: ${row.tank_id} - ID: ${row.uid}
                            </div>
                        </div>
                    </div>
                    <div class="flex align-center gap-column-6px" style="align-self:center;">
                        <div class="display-64">
                            ${row.particle_4um}/${row.particle_6um}/${row.particle_14um}
                        </div>
                    </div>
                    <div class="flex align-center gap-column-6px" style="align-self:center;">
                        <div class="display-4">
                            ${row.last_fq_date} ${row.last_fq_time}
                        </div>
                    </div>
                `;
                detailsGrid.appendChild(card);
            });
        } else {
            console.error('Received unexpected data format:', data);
        }
    } catch (error) {
        console.error('Error parsing message data:', error);
    }
};


    socket.onclose = function(event) {
        console.log('WebSocket connection closed');
    };

    socket.onerror = function(error) {
        console.error('WebSocket Error: ', error);
    };
</script>


</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
