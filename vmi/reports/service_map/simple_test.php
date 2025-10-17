<?php
/**
 * Simple test endpoint to verify basic console data
 */

require_once dirname(__DIR__, 2) . '/db/pdo_boot.php';
require_once dirname(__DIR__, 2) . '/db/log.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $cid = (int)($_SESSION['companyId'] ?? 0);
    $isGlobal = ($cid === 15100);
    
    // Very simple query first
    $sql = "SELECT 
        ca.uid,
        cs.console_coordinates,
        st.Site_name
    FROM console cs
    JOIN Console_Asociation ca ON ca.uid = cs.uid
    JOIN Sites st ON st.uid = cs.uid
    WHERE cs.console_coordinates <> '' 
      AND cs.console_coordinates IS NOT NULL
    LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $locations = [];
    foreach ($rows as $row) {
        $coords = explode(',', $row['console_coordinates']);
        if (count($coords) === 2) {
            $lat = (float)trim($coords[0]);
            $lng = (float)trim($coords[1]);
            if ($lat && $lng) {
                $locations[] = [
                    'uid' => $row['uid'],
                    'name' => $row['Site_name'],
                    'lat' => $lat,
                    'lng' => $lng,
                    'status' => 'ok',
                    'tanks' => []
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($locations),
        'locations' => $locations,
        'debug' => [
            'company_id' => $cid,
            'is_global' => $isGlobal,
            'raw_rows' => count($rows)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
?>
