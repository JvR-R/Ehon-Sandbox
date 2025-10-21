SELECT
    DISTINCT(ts.tank_uid),
    cs.uid,
    cs.client_id          AS client_id,
    cs.Site_id,
    cs.Site_name        AS site_name,
    clc.Client_name     AS client_name,
    clc.mcs_clientid,
    clc.mcs_liteid,
    cs.mcs_id           AS mcs_id,
    cos.device_type,
    cos.internalslave,
    cos.UART1,
	td.id AS tank_device_id,
	ceg.probe_conn,
    cos.dv_flag         AS flagdv,
    cos.last_conndate,
    cos.last_conntime,
    ts.tank_id,
    ts.Tank_name        AS tank_name,
    ts.current_volume,
    ts.capacity,
    ts.ullage,
    ts.current_percent,
    ts.temperature,
	ts.dipr_date,
	ts.dipr_time,
    ps.product_name,
    al.high_alarm,
    al.low_alarm,
    al.crithigh_alarm,
    al.critlow_alarm,
    CASE
        WHEN cos.dv_flag = 1 THEN 1
        WHEN ((cos.last_conndate IS NULL AND cos.device_type <> 999)
              OR (cos.last_conndate IS NOT NULL AND TIMESTAMP(cos.last_conndate, cos.last_conntime) <= NOW() - INTERVAL 27 HOUR)) THEN 2
        -- Skipped devices: make sure they sort after all real statuses
        WHEN ((cos.device_type = 30 AND COALESCE(ceg.probe_conn, 0) = 0)
              OR (cos.device_type = 20 AND (cos.UART1 IS NULL OR cos.UART1 = '0' OR cos.UART1 = 0))) THEN 99
        WHEN (ts.dipr_date IS NULL OR ts.dipr_date <= CURDATE() - INTERVAL 3 DAY) THEN 3
        WHEN (al.crithigh_alarm IS NOT NULL AND ts.current_volume >= al.crithigh_alarm) THEN 4
        WHEN (al.critlow_alarm  IS NOT NULL AND ts.current_volume <= al.critlow_alarm)  THEN 5
        WHEN (al.high_alarm     IS NOT NULL AND ts.current_volume >= al.high_alarm)     THEN 6
        WHEN (al.low_alarm      IS NOT NULL AND ts.current_volume <= al.low_alarm)      THEN 7
        ELSE 9
    END AS status_rank
FROM   Sites            cs
JOIN   Clients          clc ON clc.client_id = cs.client_id
JOIN   Tanks            ts  ON ts.client_id = cs.client_id
                           AND ts.uid       = cs.uid
                           AND ts.Site_id   = cs.Site_id
JOIN   products         ps  ON ps.product_id = ts.product_id
JOIN   Console_Asociation ca ON ca.uid       = ts.uid
JOIN   console          cos ON cos.uid        = cs.uid
LEFT   JOIN alarms_config al ON al.uid       = ts.uid
                            AND al.tank_id   = ts.tank_id
                            AND al.Site_id   = ts.Site_id
LEFT   JOIN tank_device td
       ON  td.tank_uid = ts.tank_uid
       AND td.uid      = ts.uid
LEFT   JOIN config_ehon_gateway ceg
       ON  ceg.tank_device_id = td.id
WHERE  (cs.client_id   = :cid1
        OR clc.reseller_id = :cid2
        OR ca.dist_id      = :cid3
        OR :cid1 = 15100)
  AND  cos.device_type <> 999
  AND ts.enabled = 1
  -- {{SITE_FILTER}}  
ORDER  BY ts.current_percent DESC