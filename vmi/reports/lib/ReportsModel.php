<?php
class ReportsModel
{
    /** @var PDO */
    private $db;
    public function __construct(PDO $pdo) { $this->db = $pdo; }

    /* ---------- TRANSACTIONS ---------- */
    public function listTransactions(int $companyId, string $fromDate): array
    {
        if (isGlobalAccount($companyId)) {
            $sql = <<<SQL
                SELECT transaction_date, transaction_time, ct.uid, dispensed_volume, st.Site_name
                  FROM client_transaction ct
                  JOIN Console_Asociation ca ON ct.uid = ca.uid
                  JOIN Sites               st ON st.uid = ct.uid
                 WHERE transaction_date >= :from
                 ORDER BY CONCAT(transaction_date,' ',transaction_time) DESC
                SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['from' => $fromDate]);
        } else {
            $sql = <<<SQL
                SELECT transaction_date, transaction_time, ct.uid, dispensed_volume, st.Site_name
                  FROM client_transaction ct
                  JOIN Console_Asociation ca ON ct.uid = ca.uid
                  JOIN Sites               st ON st.uid = ct.uid
                 WHERE (ca.client_id   = :cid OR
                        ca.reseller_id = :cid)
                   AND transaction_date >= :from
                 ORDER BY CONCAT(transaction_date,' ',transaction_time) DESC
                SQL;
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['cid' => $companyId, 'from' => $fromDate]);
        }
        return $stmt->fetchAll();
    }

    /* ---------- TOTAL DELIVERIES ---------- */
    public function totalDeliveries(int $companyId, string $fromDate): float
    {
        $base = 'SELECT SUM(delivery) FROM delivery_historic dth
                 JOIN Console_Asociation ca ON dth.uid = ca.uid
                 WHERE dth.tank_id != 99 AND dth.transaction_date >= :from';
        $where = isGlobalAccount($companyId)
               ? ''
               : ' AND (ca.client_id = :cid OR ca.reseller_id = :cid)';
        $stmt = $this->db->prepare($base.$where);
        $params = ['from' => $fromDate];
        if (!isGlobalAccount($companyId)) $params['cid'] = $companyId;
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }
}
