<?php
include('../../../db/dbh2.php');
include('../../../db/log.php');
include('../../../db/border.php');

// Get parameters from URL
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;

if ($site_id <= 0 || $uid <= 0) {
    echo "Invalid site ID or console UID.";
    exit;
}

// Verify console device_type is 10 (FMS)
$checkSql = "SELECT device_type FROM console WHERE uid = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $uid);
$checkStmt->execute();
$checkStmt->bind_result($device_type);
$checkStmt->fetch();
$checkStmt->close();

if ($device_type != 10) {
    echo "This page is only available for FMS consoles (device_type = 10).";
    exit;
}

// Get site information
$siteSql = "SELECT Site_name FROM Sites WHERE Site_id = ?";
if ($companyId != 15100) {
    $siteSql .= " AND Client_id = ?";
}
$siteStmt = $conn->prepare($siteSql);
if ($companyId != 15100) {
    $siteStmt->bind_param("ii", $site_id, $companyId);
} else {
    $siteStmt->bind_param("i", $site_id);
}
$siteStmt->execute();
$siteStmt->bind_result($site_name);
$siteStmt->fetch();
$siteStmt->close();

if (!$site_name) {
    echo "Site not found or access denied.";
    exit;
}

// Get tanks with capacity and pumps
$tanks = [];
$tankSql = "SELECT t.tank_id, t.Tank_name, t.capacity 
            FROM Tanks t 
            WHERE t.Site_id = ? AND t.uid = ? AND t.enabled = 1";
if ($companyId != 15100) {
    $tankSql .= " AND t.client_id = ?";
}
$tankSql .= " ORDER BY t.tank_id";
$tankStmt = $conn->prepare($tankSql);
if ($companyId != 15100) {
    $tankStmt->bind_param("iii", $site_id, $uid, $companyId);
} else {
    $tankStmt->bind_param("ii", $site_id, $uid);
}
$tankStmt->execute();
$tankStmt->bind_result($tank_id, $tank_name, $capacity);

while ($tankStmt->fetch()) {
    // Get pumps for this tank
    $pumps = [];
    $pumpSql = "SELECT pump_id, Nozzle_Number, Nozzle_Walk_Time, Nozzle_Auth_Time, Nozzle_Max_Run_Time, Nozzle_No_Flow, Nozzle_Product, Pulse_Rate 
                FROM pumps 
                WHERE uid = ? AND tank_id = ? 
                ORDER BY Nozzle_Number";
    $pumpStmt = $conn->prepare($pumpSql);
    $pumpStmt->bind_param("ii", $uid, $tank_id);
    $pumpStmt->execute();
    $pumpStmt->bind_result($pump_id, $nozzle_number, $nozzle_walk_time, $nozzle_auth_time, $nozzle_max_run_time, $nozzle_no_flow, $nozzle_product, $pulse_rate);
    
    while ($pumpStmt->fetch()) {
        $pumps[] = [
            'pump_id' => $pump_id,
            'nozzle_number' => $nozzle_number,
            'nozzle_walk_time' => $nozzle_walk_time,
            'nozzle_auth_time' => $nozzle_auth_time,
            'nozzle_max_run_time' => $nozzle_max_run_time,
            'nozzle_no_flow' => $nozzle_no_flow,
            'nozzle_product' => $nozzle_product,
            'pulse_rate' => $pulse_rate
        ];
    }
    $pumpStmt->close();
    
    $tanks[] = [
        'tank_id' => $tank_id,
        'tank_name' => $tank_name ? $tank_name : "Tank " . $tank_id,
        'capacity' => $capacity,
        'pumps' => $pumps
    ];
}
$tankStmt->close();

$success = isset($_GET['success']) && $_GET['success'] === 'true';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tanks & Pumps - Ehon Energy Tech</title>
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="modern_style.css">
    <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
    <style>
        .tank-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .tank-header {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        .pump-item {
            background: var(--bg-primary);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .pump-header {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
    </style>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
    <div class="dashboard-main-section">
        <div class="dashboard-content">
        <div class="sidebar-spacer"></div>
        <div class="sidebar-spacer2"></div>
            <div class = "dashboard-main-content">
            <?php include('../../../details/top_menu.php');?>
                <div class="container-default w-container" style="padding-top: 24px; max-width: 1200px;">
                    <div class="mg-bottom-24px">
                        <div class="card pd-28px">
                            <h1 class="display-4 mg-bottom-4px">Edit Tanks & Pumps</h1>
                            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Site: <strong><?php echo htmlspecialchars($site_name); ?></strong> | Console UID: <strong><?php echo $uid; ?></strong></p>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    Tanks and pumps updated successfully!
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-error">
                                    Error: <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form id="tankEditForm" method="post" action="edit_tank_sbmt.php">
                                <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                                <input type="hidden" name="site_id" value="<?php echo $site_id; ?>">
                                
                                <?php if (empty($tanks)): ?>
                                    <p style="color: var(--text-secondary);">No enabled tanks found for this site.</p>
                                <?php else: ?>
                                    <?php foreach ($tanks as $tank): ?>
                                        <div class="tank-card">
                                            <div class="tank-header"><?php echo htmlspecialchars($tank['tank_name']); ?></div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Capacity (Liters)</label>
                                                    <input type="number" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][capacity]" 
                                                           value="<?php echo htmlspecialchars($tank['capacity']); ?>" min="0" required>
                                                    <input type="hidden" name="tanks[<?php echo $tank['tank_id']; ?>][tank_id]" value="<?php echo $tank['tank_id']; ?>">
                                                </div>
                                            </div>
                                            
                                            <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; color: var(--text-primary);">Pumps</h4>
                                            
                                            <?php if (empty($tank['pumps'])): ?>
                                                <p style="color: var(--text-secondary); font-size: 0.875rem;">No pumps associated with this tank.</p>
                                            <?php else: ?>
                                                <?php foreach ($tank['pumps'] as $pump): ?>
                                                    <div class="pump-item">
                                                        <div class="pump-header">Nozzle <?php echo htmlspecialchars($pump['nozzle_number']); ?></div>
                                                        <input type="hidden" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][pump_id]" value="<?php echo $pump['pump_id']; ?>">
                                                        
                                                        <div class="form-row">
                                                            <div class="form-group">
                                                                <label>Nozzle Number</label>
                                                                <input type="number" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][nozzle_number]" 
                                                                       value="<?php echo htmlspecialchars($pump['nozzle_number']); ?>" min="1" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Walk Time (seconds)</label>
                                                                <input type="number" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][nozzle_walk_time]" 
                                                                       value="<?php echo htmlspecialchars($pump['nozzle_walk_time']); ?>" min="0">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Auth Time (seconds)</label>
                                                                <input type="number" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][nozzle_auth_time]" 
                                                                       value="<?php echo htmlspecialchars($pump['nozzle_auth_time']); ?>" min="0">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Max Run Time (seconds)</label>
                                                                <input type="number" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][nozzle_max_run_time]" 
                                                                       value="<?php echo htmlspecialchars($pump['nozzle_max_run_time']); ?>" min="0">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>No Flow Time (seconds)</label>
                                                                <input type="number" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][nozzle_no_flow]" 
                                                                       value="<?php echo htmlspecialchars($pump['nozzle_no_flow']); ?>" min="0">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Product</label>
                                                                <input type="text" class="input" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][nozzle_product]" 
                                                                       value="<?php echo htmlspecialchars($pump['nozzle_product']); ?>">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Pulse Rate</label>
                                                                <input type="number" class="input" step="0.01" name="tanks[<?php echo $tank['tank_id']; ?>][pumps][<?php echo $pump['pump_id']; ?>][pulse_rate]" 
                                                                       value="<?php echo htmlspecialchars($pump['pulse_rate']); ?>" min="0">
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="buttons-row">
                                    <button type="button" class="btn-primary" onclick="window.location.href='index.php'">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn-primary">
                                        Save Changes
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
</body>
</html>
