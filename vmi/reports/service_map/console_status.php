<?php
/**
 * Console Status API for Service Map
 * Returns detailed console status and alert information
 */

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/db/pdo_boot.php';
require_once dirname(__DIR__, 2) . '/db/log.php';

header('Content-Type: application/json; charset=utf-8');

$cid = (int)($_SESSION['companyId'] ?? 0);
$isGlobal = ($cid === 15100);

/**
 * Get console status based on various conditions
 */
function getConsoleStatus($row, $scope = 'console') {
    // Use a fixed timezone to avoid false offline due to TZ mismatch
    $tz = new DateTimeZone('Australia/Brisbane');
    $now = new DateTime('now', $tz);
    
    // Fix DateTime creation issues - combine date and time for accurate 27-hour check
    $lastConnDateTime = null;
    $lastConnAgeHours = null;
    if (!empty($row['last_conndate']) && $row['last_conndate'] !== '0000-00-00') {
        try {
            $dateTimeStr = $row['last_conndate'];
            // Add time if available
            if (!empty($row['last_conntime']) && $row['last_conntime'] !== '00:00:00') {
                $dateTimeStr .= ' ' . $row['last_conntime'];
            }
            $lastConnDateTime = new DateTime($dateTimeStr, $tz);
            // Compute age in hours (clamped at 0 if in the future)
            $diffSeconds = $now->getTimestamp() - $lastConnDateTime->getTimestamp();
            if ($diffSeconds < 0) { $diffSeconds = 0; }
            $lastConnAgeHours = (int) floor($diffSeconds / 3600);
        } catch (Exception $e) {
            $lastConnDateTime = null;
        }
    }
    
    $diprDate = null;
    if (!empty($row['dipr_date']) && $row['dipr_date'] !== '0000-00-00' && $row['dipr_date'] !== '1970-01-01') {
        try {
            $diprDate = new DateTime($row['dipr_date'], $tz);
        } catch (Exception $e) {
            $diprDate = null;
        }
    }
    
    $status = 'ok';
    $alert_type = 'none';
    $severity = 'info';
    $message = 'Console operating normally';
    
    // Priority 1: Device disconnected (treated as OFFLINE)
    if (isset($row['dv_flag']) && $row['dv_flag'] == 1) {
        $status = 'offline';
        $alert_type = 'console_offline';
        $severity = 'critical';
        $message = 'Device has been disconnected';
        // Return immediately - ignore all other alerts when offline
        return [
            'status' => $status,
            'alert_type' => $alert_type,
            'severity' => $severity,
            'message' => $message,
            'debug' => [
                'last_conn_age_hours' => $lastConnAgeHours,
                'now' => $now->format('Y-m-d H:i:s'),
                'last_conn' => $lastConnDateTime ? $lastConnDateTime->format('Y-m-d H:i:s') : null
            ]
        ];
    }
    
    // Priority 2: Console offline/disconnected (SECOND HIGHEST PRIORITY - overrides everything except disconnected)
    if ((!$lastConnDateTime && $row['device_type'] != 999) || 
        ($lastConnAgeHours !== null && $lastConnAgeHours >= 27)) {
        $status = 'offline';
        $alert_type = 'console_offline';
        $severity = 'critical';
        $message = 'Console has been offline/disconnected for more than 27 hours';
        // Return immediately - ignore all other alerts when offline
        return [
            'status' => $status,
            'alert_type' => $alert_type,
            'severity' => $severity,
            'message' => $message,
            'debug' => [
                'last_conn_age_hours' => $lastConnAgeHours,
                'now' => $now->format('Y-m-d H:i:s'),
                'last_conn' => $lastConnDateTime ? $lastConnDateTime->format('Y-m-d H:i:s') : null
            ]
        ];
    }
    
    // Only check other alerts if console is online and connected
    
    // Priority 3: Dip out of sync - check for missing date OR date older than 3 days
    $dipOutOfSync = false;
    $dipMessage = '';
    
    if ($diprDate === null) {
        // No valid dip date found
        $dipOutOfSync = true;
        $dipMessage = 'No dip reading data available';
    } elseif ($diprDate <= (clone $now)->sub(new DateInterval('P3D'))) {
        // Dip date is older than 3 days
        $dipOutOfSync = true;
        $dipMessage = 'Dip reading is out of sync (>3 days)';
    }
    
    if ($dipOutOfSync) {
        // Skip for certain device types
        $probe_conn = $row['probe_conn'] ?? 0;
        $uart1 = $row['uart1'] ?? 0;
        
        if (!($row['device_type'] == 30 && $probe_conn == 0) && 
            !($row['device_type'] == 20 && $uart1 == 0)) {
            $status = 'dip_offline';
            $alert_type = 'dip_out_of_sync';
            $severity = 'critical';
            $message = $dipMessage;
        }
    }
    // Priority 4: Volume alarms/tank level warnings - only checked for tank scope
    elseif ($scope === 'tank' && isset($row['alarm_enable'])) {
        $volume = (float)($row['current_volume'] ?? 0);
        $percent = isset($row['current_percent']) ? (float)$row['current_percent'] : null;

        if ((int)$row['alarm_enable'] === 1) {
            // Use configured alarm thresholds - only critical levels
            if (!empty($row['crithigh_alarm']) && $volume >= $row['crithigh_alarm']) {
                $status = 'critical_high';
                $alert_type = 'volume_critical_high';
                $severity = 'warning';
                $message = "Volume critically high: {$volume}L (threshold: {$row['crithigh_alarm']}L)";
            }
            elseif (!empty($row['critlow_alarm']) && $volume <= $row['critlow_alarm']) {
                $status = 'critical_low';
                $alert_type = 'volume_critical_low';
                $severity = 'warning';
                $message = "Volume critically low: {$volume}L (threshold: {$row['critlow_alarm']}L)";
            }
        } elseif ($percent !== null) {
            // No alarms configured: derive warnings from percent - only critical levels
            if ($percent <= 10) {
                $status = 'critical_low';
                $alert_type = 'volume_critical_low';
                $severity = 'warning';
                $message = "Tank level at {$percent}%";
            }
        }
    }

    return [
        'status' => $status,
        'alert_type' => $alert_type,
        'severity' => $severity,
        'message' => $message,
        'debug' => [
            'last_conn_age_hours' => $lastConnAgeHours,
            'now' => $now->format('Y-m-d H:i:s'),
            'last_conn' => $lastConnDateTime ? $lastConnDateTime->format('Y-m-d H:i:s') : null
        ]
    ];
}

try {
    // Simplified query to avoid potential column issues
    $sql = $isGlobal ? <<<SQL
    SELECT 
        ca.uid,
        cs.device_id,
        cs.device_type,
        cs.console_coordinates,
        cs.console_status,
        cs.firmware,
        cs.last_conndate,
        cs.last_conntime,
        COALESCE(cs.dv_flag, 0) as dv_flag,
        COALESCE(cs.service_flag, 0) as service_flag,
        cs.uart1,
        cs.internalslave,
        cs.console_ip,
        cs.console_imei,
        cs.cs_signal,
        st.Site_name,
        st.site_address,
        st.site_city,
        t.tank_id,
        t.current_percent,
        t.current_volume,
        t.dipr_date,
        COALESCE(al.alarm_enable, 0) as alarm_enable,
        al.crithigh_alarm,
        al.high_alarm,
        al.low_alarm,
        al.critlow_alarm,
        srv.ticket_id,
        srv.ticket_comment,
        ceg.probe_conn
    FROM console cs
    JOIN Console_Asociation ca ON ca.uid = cs.uid
    JOIN Sites st ON st.uid = cs.uid
    LEFT JOIN Tanks t ON t.uid = cs.uid
    LEFT JOIN tank_device td ON td.tank_uid = t.tank_uid AND td.uid = t.uid
    LEFT JOIN config_ehon_gateway ceg ON ceg.tank_device_id = td.id
    LEFT JOIN alarms_config al ON al.uid = t.uid AND al.tank_id = t.tank_id AND al.Site_id = st.Site_id
    LEFT JOIN service_ticket srv ON srv.uid = cs.uid
    WHERE ca.client_id != 15100 and cs.device_type in (20, 30, 200, 201)
    ORDER BY st.Site_name, t.tank_id
    SQL
    : <<<SQL
    SELECT 
        ca.uid,
        cs.device_id,
        cs.device_type,
        cs.console_coordinates,
        cs.console_status,
        cs.firmware,
        cs.last_conndate,
        cs.last_conntime,
        COALESCE(cs.dv_flag, 0) as dv_flag,
        COALESCE(cs.service_flag, 0) as service_flag,
        cs.uart1,
        cs.internalslave,
        cs.console_ip,
        cs.console_imei,
        cs.cs_signal,
        st.Site_name,
        st.site_address,
        st.site_city,
        t.tank_id,
        t.current_percent,
        t.current_volume,
        t.dipr_date,
        COALESCE(al.alarm_enable, 0) as alarm_enable,
        al.crithigh_alarm,
        al.high_alarm,
        al.low_alarm,
        al.critlow_alarm,
        srv.ticket_id,
        srv.ticket_comment,
        ceg.probe_conn
    FROM console cs
    JOIN Console_Asociation ca ON ca.uid = cs.uid
    JOIN Sites st ON st.uid = cs.uid
    LEFT JOIN Tanks t ON t.uid = cs.uid
    LEFT JOIN tank_device td ON td.tank_uid = t.tank_uid AND td.uid = t.uid
    LEFT JOIN config_ehon_gateway ceg ON ceg.tank_device_id = td.id
    LEFT JOIN alarms_config al ON al.uid = t.uid AND al.tank_id = t.tank_id AND al.Site_id = st.Site_id
    LEFT JOIN service_ticket srv ON srv.uid = cs.uid
    WHERE (ca.client_id = ? OR ca.reseller_id = ? OR ca.dist_id = ?) and cs.device_type in (20, 30, 200, 201)
    ORDER BY st.Site_name, t.tank_id
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($isGlobal ? [] : [$cid, $cid, $cid]);

    $consoles = [];
    $alerts = [];
    $alertId = 1;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uid = $row['uid'];
        
        // Initialize console if not exists
        if (!isset($consoles[$uid])) {
            $consoleStatus = getConsoleStatus($row, 'console');
            
            // Parse coordinates if present
            $lat = null;
            $lng = null;
            if (!empty($row['console_coordinates'])) {
                $coords = explode(',', $row['console_coordinates']);
                if (count($coords) === 2) {
                    $lat = (float)trim($coords[0]);
                    $lng = (float)trim($coords[1]);
                }
            }
            
            $consoles[$uid] = [
                'uid' => $uid,
                'device_id' => $row['device_id'],
                'device_type' => $row['device_type'],
                'site_name' => $row['Site_name'],
                'coordinates' => ($lat !== null && $lng !== null) ? [ 'lat' => $lat, 'lng' => $lng ] : null,
                'console_status' => $row['console_status'],
                'firmware' => $row['firmware'],
                'last_connection' => [
                    'date' => $row['last_conndate'],
                    'time' => $row['last_conntime']
                ],
                'network' => [
                    'ip' => $row['console_ip'],
                    'imei' => $row['console_imei'],
                    'signal' => $row['cs_signal']
                ],
                'status' => $consoleStatus['status'],
                'alert_type' => $consoleStatus['alert_type'],
                'severity' => $consoleStatus['severity'],
                'message' => $consoleStatus['message'],
                'debug' => $consoleStatus['debug'],
                'service_flag' => (int)$row['service_flag'],
                'acknowledged' => (int)$row['service_flag'] === 1,
                'ticket_id' => $row['ticket_id'],
                'ticket_comment' => $row['ticket_comment'],
                'tanks' => []
            ];

            // Create console-level alert if needed (only once per console)
            if ($consoleStatus['alert_type'] !== 'none') {
                $alerts[] = [
                    'id' => $alertId++,
                    'console_uid' => $uid,
                    'site_name' => $row['Site_name'],
                    'alert_type' => $consoleStatus['alert_type'],
                    'severity' => $consoleStatus['severity'],
                    'title' => ucwords(str_replace('_', ' ', $consoleStatus['alert_type'])),
                    'message' => $consoleStatus['message'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'acknowledged' => (int)$row['service_flag'] === 1,
                    'ticket_id' => $row['ticket_id'],
                    'ticket_comment' => $row['ticket_comment']
                ];
            }
        }

        // Add tank information if available
        if ($row['tank_id']) {
            $tankStatus = getConsoleStatus($row, 'tank');
            
            $tank = [
                'tank_id' => $row['tank_id'],
                'current_percent' => (float)($row['current_percent'] ?? 0),
                'current_volume' => (float)($row['current_volume'] ?? 0),
                'alarms' => [
                    'enabled' => $row['alarm_enable'] == 1,
                    'critical_high' => $row['crithigh_alarm'],
                    'high' => $row['high_alarm'],
                    'low' => $row['low_alarm'],
                    'critical_low' => $row['critlow_alarm']
                ],
                'status' => $tankStatus['status'],
                'alert_type' => $tankStatus['alert_type']
            ];

            $consoles[$uid]['tanks'][] = $tank;

            // Create tank-level alert if needed
            if ($tankStatus['alert_type'] !== 'none') {
                // Skip tank alerts if:
                // 1. Console is offline/disconnected (no reliable data)
                // 2. Console has critical alert and tank alert is warning (critical takes priority)
                // 3. Tank alert is same type as console alert (avoid duplicates)
                $consoleIsOffline = in_array($consoles[$uid]['alert_type'], ['console_offline', 'device_disconnected']);
                $consoleHasCritical = $consoles[$uid]['severity'] === 'critical';
                $tankIsWarning = $tankStatus['severity'] === 'warning';
                $isDuplicateAlert = ($tankStatus['alert_type'] === $consoles[$uid]['alert_type']);
                
                // Skip warning-level tank alerts if console has any critical alert
                if (!$consoleIsOffline && !$isDuplicateAlert && !($consoleHasCritical && $tankIsWarning)) {
                    $alerts[] = [
                        'id' => $alertId++,
                        'console_uid' => $uid,
                        'tank_id' => $row['tank_id'],
                        'site_name' => $row['Site_name'],
                        'alert_type' => $tankStatus['alert_type'],
                        'severity' => $tankStatus['severity'],
                        'title' => "Tank {$row['tank_id']} - " . ucwords(str_replace('_', ' ', $tankStatus['alert_type'])),
                        'message' => $tankStatus['message'],
                        'timestamp' => date('Y-m-d H:i:s'),
                        'acknowledged' => (int)$row['service_flag'] === 1,
                        'ticket_id' => $row['ticket_id'],
                        'ticket_comment' => $row['ticket_comment']
                    ];
                }
            }
        }
    }

    // Calculate summary statistics
    $totalConsoles = count($consoles);
    $onlineConsoles = 0;
    $alertConsoles = 0;
    $criticalAlerts = 0;
    $warningAlerts = 0;
    $infoAlerts = 0;

    // Count consoles that have any alerts (console-level or tank-level)
    $consolesWithAlerts = [];
    
    foreach ($consoles as $console) {
        if ($console['status'] === 'ok' || $console['status'] === 'dip_offline') {
            // Count both 'ok' and 'dip_offline' as online consoles
            // dip_offline is a data issue, not a console connectivity issue
            $onlineConsoles++;
        } else {
            // Only count offline/disconnected consoles as "alert consoles"
            $consolesWithAlerts[$console['uid']] = true;
        }
    }
    
    // Also count consoles that have tank-level alerts
    foreach ($alerts as $alert) {
        if (isset($alert['console_uid'])) {
            $consolesWithAlerts[$alert['console_uid']] = true;
        }
    }
    
    $alertConsoles = count($consolesWithAlerts);

    foreach ($alerts as $alert) {
        switch ($alert['severity']) {
            case 'critical':
                $criticalAlerts++;
                break;
            case 'warning':
                $warningAlerts++;
                break;
            default:
                $infoAlerts++;
                break;
        }
    }

    // Debug information
    $debugInfo = [
        'consoles_by_status' => [],
        'alerts_by_severity' => [],
        'console_count_details' => [
            'total_from_array' => count($consoles),
            'online_counted' => $onlineConsoles,
            'alert_console_uids' => array_keys($consolesWithAlerts),
            'alert_consoles_count' => count($consolesWithAlerts)
        ]
    ];
    
    // Count consoles by status for debugging
    foreach ($consoles as $console) {
        $status = $console['status'];
        if (!isset($debugInfo['consoles_by_status'][$status])) {
            $debugInfo['consoles_by_status'][$status] = 0;
        }
        $debugInfo['consoles_by_status'][$status]++;
    }
    
    // Count alerts by severity for debugging
    foreach ($alerts as $alert) {
        $severity = $alert['severity'];
        if (!isset($debugInfo['alerts_by_severity'][$severity])) {
            $debugInfo['alerts_by_severity'][$severity] = 0;
        }
        $debugInfo['alerts_by_severity'][$severity]++;
    }

    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'summary' => [
            'total_consoles' => $totalConsoles,
            'online_consoles' => $onlineConsoles,
            'alert_consoles' => $alertConsoles,
            'critical_alerts' => $criticalAlerts,
            'warning_alerts' => $warningAlerts,
            'info_alerts' => $infoAlerts
        ],
        'consoles' => array_values($consoles),
        'alerts' => $alerts,
        'debug' => $debugInfo
    ];

    echo json_encode($response, JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], JSON_THROW_ON_ERROR);
}
?>
