<?php
// 1. Include your DB connection and email config/PHPMailer script
include('../db/dbh2.php');         // Adjust path as needed
include('../db/email_conf.php');   // Adjust path as needed

session_start(); // optional

/**
 * This script:
 *  - Reads offline_reportcron for (client_id, off_timer, group_id, off_email_list)
 *  - Looks up client_name from Clients (so we can use it in the email subject)
 *  - Retrieves all Tanks (joined with console, sites, etc.) for that client
 *    filtered by group_id if needed
 *  - For each row, check if (today - last_conndate) > off_timer
 *  - Accumulate those “offline” rows in an array
 *  - If any offline rows exist, build an HTML table (with 5 columns) and email it
 */

function checkOfflineAndSendEmail()
{
    global $conn;  // Use the DB connection from dbh2.php
    $today = new DateTime(date('Y-m-d'));

    // Step A: Get the offline_reportcron rows
    $cronSql = "SELECT client_id, off_timer, group_id, off_email_list 
                FROM offline_reportcron";
    $cronResult = $conn->query($cronSql);
    if (!$cronResult) {
        error_log("Error running offline_reportcron query: " . $conn->error);
        return;
    }

    while ($cronRow = $cronResult->fetch_assoc()) {
        $client_id  = (int) $cronRow['client_id'];
        $off_timer  = (int) $cronRow['off_timer'];
        $group_id   = (int) $cronRow['group_id'];
        $email_list = $cronRow['off_email_list']; // comma-separated emails

        // 1) Fetch the client's name for a nicer email subject
        $client_name = '';
        $clientNameSql = "SELECT Client_name FROM Clients 
                          WHERE client_id = '{$client_id}' 
                          LIMIT 1";
        $nameRes = $conn->query($clientNameSql);
        if ($nameRes && $nameRes->num_rows > 0) {
            $rowName     = $nameRes->fetch_assoc();
            $client_name = $rowName['Client_name'];
        }

        // 2) Build the Tanks query depending on group_id
        if ($group_id === 0) {
            // No group filtering – get all tanks for this client
            $sitesSql = "
                SELECT
                    cs.Client_name,
                    st.Site_name,
                    cos.uid,
                    cos.last_conndate
                FROM Tanks AS ts
                JOIN Clients AS cs  ON ts.client_id = cs.client_id
                JOIN Sites   AS st  ON ts.uid       = st.uid
                JOIN console AS cos ON cos.uid      = ts.uid
                WHERE ts.client_id = '{$client_id}'
                GROUP BY uid
                ORDER BY ts.current_percent DESC
            ";
        } else {
            // Filter by group_id in client_site_groups
            $sitesSql = "
                SELECT
                    cs.Client_name,
                    st.Site_name,
                    cos.uid,
                    cos.last_conndate
                FROM Tanks AS ts
                JOIN Clients AS cs  ON ts.client_id = cs.client_id
                JOIN Sites   AS st  ON ts.uid       = st.uid
                JOIN console AS cos ON cos.uid      = ts.uid
                WHERE ts.Site_id IN (
                    SELECT site_no
                    FROM client_site_groups
                    WHERE group_id = '{$group_id}'
                      AND client_id = '{$client_id}'
                )
                GROUP BY uid
                ORDER BY ts.current_percent DESC
            ";
        }

        $sitesResult = $conn->query($sitesSql);
        if (!$sitesResult) {
            // Query error or no results
            continue;
        }

        // 3) For each row, check if it's "offline"
        $offlineRows = [];
        while ($row = $sitesResult->fetch_assoc()) {
            $last_conndate = $row['last_conndate'] ?? null;
            if (!$last_conndate) {
                // If there's no last_conndate, you might skip or consider it offline
                continue;
            }

            $connDateObj = new DateTime($last_conndate);
            $diffDays    = $today->diff($connDateObj)->days;

            if ($diffDays > $off_timer) {
                // The row is offline; store it
                $offlineRows[] = $row;
            }
        }

        // If no offline rows, skip sending an email
        if (empty($offlineRows)) {
            continue;
        }

        // 4) Build the HTML table of offline rows
        //        1) Site_name => "Site Name"
        //        2) dipr_date => "Dip Read Date"
        //        3) dipr_time => "Dip Read Time"
        //        4) current_percent => "Current Volume"
        //        5) last_conndate => "Last Sync"
        //
        // We'll create an array that maps the DB column to a user-friendly label:
        $columnMap = [
            'Site_name'       => 'Site Name',
            'uid'       => 'ID',
            'last_conndate'   => 'Last Sync'
        ];

        // Start HTML
        $emailBody = "
        <html>
        <head>
            <style>
                table {
                    
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                }
                th, td {
                    padding: 10px;
                    text-align: center;
                    border: 1px solid #003366;
                }
                th {
                    background-color: #003366;
                    color: white;
                }
                td {
                    background-color: #f0f8ff;
                    color: #003366;
                }
            </style>
        </head>
        <body>
            <h3>Offline Alert for Client: " . htmlspecialchars($client_name) . "</h3>
            <p>The following sites appear to be offline.</p>
            <table>
                <tr>
        ";

        // Table headers
        foreach ($columnMap as $dbCol => $headerLabel) {
            $emailBody .= "<th>" . htmlspecialchars($headerLabel) . "</th>";
        }
        $emailBody .= "</tr>";

        // Populate rows
        foreach ($offlineRows as $oRow) {
            $emailBody .= "<tr>";
            foreach ($columnMap as $dbCol => $headerLabel) {
                $value = $oRow[$dbCol] ?? '';
                $emailBody .= "<td>" . htmlspecialchars($value) . "</td>";
            }
            $emailBody .= "</tr>";
        }

        $emailBody .= "
            </table>
        </body>
        </html>";

        // 5) Send email to each address in off_email_list
        if (!empty($email_list)) {
            $addresses = explode(',', $email_list);

            // Use client name in subject
            // Fallback if client_name is empty => use "Client #{$client_id}"
            $subjectLabel = $client_name ? $client_name : "Client #{$client_id}";
            $subject      = "Offline Alert: {$subjectLabel}";

            foreach ($addresses as $addr) {
                $addr = trim($addr);
                if (!empty($addr)) {
                    send_email($addr, $subject, $emailBody);
                }
            }
        }
    } // end while (reading offline_reportcron)
}

// Finally, run the function
checkOfflineAndSendEmail();
?>
