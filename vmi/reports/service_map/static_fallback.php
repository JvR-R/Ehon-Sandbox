<?php
/**
 * Static fallback data - no database dependencies
 */

header('Content-Type: application/json; charset=utf-8');

// Return static demo data for the service map
echo json_encode([
    'success' => true,
    'mode' => 'static_fallback',
    'consoles' => [
        [
            'uid' => 1,
            'site_name' => 'Demo Site Brisbane',
            'coordinates' => ['lat' => -27.4698, 'lng' => 153.0251],
            'status' => 'ok',
            'tanks' => [
                ['tank_id' => 1, 'current_percent' => 75]
            ],
            'device_id' => 'DEMO001',
            'firmware' => 'v2.1.0',
            'last_connection' => [
                'date' => date('Y-m-d'),
                'time' => date('H:i:s')
            ],
            'network' => [
                'ip' => '192.168.1.100',
                'signal' => 85
            ]
        ],
        [
            'uid' => 2,
            'site_name' => 'Demo Site Sydney', 
            'coordinates' => ['lat' => -33.8688, 'lng' => 151.2093],
            'status' => 'critical_low',
            'tanks' => [
                ['tank_id' => 1, 'current_percent' => 15]
            ],
            'device_id' => 'DEMO002',
            'firmware' => 'v2.0.8',
            'last_connection' => [
                'date' => date('Y-m-d'),
                'time' => date('H:i:s', strtotime('-1 hour'))
            ],
            'network' => [
                'ip' => '192.168.1.101',
                'signal' => 62
            ]
        ],
        [
            'uid' => 3,
            'site_name' => 'Demo Site Melbourne',
            'coordinates' => ['lat' => -37.8136, 'lng' => 144.9631],
            'status' => 'offline',
            'tanks' => [
                ['tank_id' => 1, 'current_percent' => 50]
            ],
            'device_id' => 'DEMO003',
            'firmware' => 'v1.9.5',
            'last_connection' => [
                'date' => date('Y-m-d', strtotime('-3 days')),
                'time' => '14:30:22'
            ],
            'network' => [
                'ip' => '192.168.1.102',
                'signal' => 0
            ]
        ]
    ],
    'alerts' => [
        [
            'id' => 1,
            'console_uid' => 2,
            'site_name' => 'Demo Site Sydney',
            'alert_type' => 'volume_critical_low',
            'severity' => 'critical',
            'title' => 'Critical Low Level - Tank 1',
            'message' => 'Tank level at 15%',
            'timestamp' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'console_uid' => 3,
            'site_name' => 'Demo Site Melbourne',
            'alert_type' => 'console_offline',
            'severity' => 'critical',
            'title' => 'Console Offline',
            'message' => 'Console has been offline for more than 27 hours',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]
    ],
    'summary' => [
        'total_consoles' => 3,
        'online_consoles' => 1,
        'alert_consoles' => 2,
        'critical_alerts' => 2,
        'warning_alerts' => 0,
        'info_alerts' => 0
    ]
]);
?>
