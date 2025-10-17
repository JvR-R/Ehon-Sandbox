<?php
    include('../db/dbh2.php');
    include('../db/email_conf.php');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $hour = date("H");
    $croninfo = $conn->prepare("SELECT client_id, group_id, email_list, cron FROM report_cron;");
    $croninfo->execute();
    
    // Correct method to bind results
    // Add a variable for group_id if it's a column in your table
    $croninfo->bind_result($company_id, $group_id, $email_list, $cron_string);
    
    // Fetch all rows
    while ($croninfo->fetch()) { // Use a while loop to iterate through all results
        echo "Client ID: " . $company_id . "<br>";
        echo "Group ID: " . $group_id . "<br>"; // Print group_id
        echo "Emails: " . $email_list . "<br>";
    
        $cronArray = explode(", ", $cron_string); // Assuming $cron_string contains your comma-separated data

        foreach ($cronArray as $cron) { // Correct foreach loop
            if($cron == $hour){
            // echo "Cron: " . $cron . " current hour: " . $hour . "<br>"; // Echo current element
            $emailarray = explode(", ", $email_list);
            foreach ($emailarray as $email){
                fetch_email($email, $company_id, $group_id);
            }
            }
        }
        echo "<br><br>"; // Add a line break for readability between rows
    }
$croninfo->close();


    function fetch_email($email, $company_id, $group_id) {
        include('../db/dbh2.php');
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", "$username", "$password");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected successfully<br>"; 
        } catch(PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }
    
        // Define table headers
        $table_headers = ['Company Name', 'Site Name', 'Tank No', 'Date', 'Time', 'Capacity', 'Current Volume', 'Ullage', 'Current Percentage', 'Last Sync'];
        
        if ($group_id == 0) {
            $query = "SELECT Client_name, Site_name, tank_id, dipr_date, dipr_time, capacity, current_volume, ullage, current_percent, last_conndate 
                      FROM Tanks as ts JOIN Clients as cs ON ts.client_id = cs.client_id
                      JOIN Sites as st on ts.uid = st.uid 
                      JOIN console cos on cos.uid = ts.uid
                      WHERE cos.uid in (SELECT uid FROM Console_Asociation WHERE reseller_id = :company_id or dist_id = :company_id or client_id = :company_id)
                      ORDER BY current_percent DESC";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
        } else {
            $query = "SELECT Client_name, Site_name, tank_id, dipr_date, dipr_time, capacity, current_volume, ullage, current_percent, last_conndate 
                      FROM Tanks as ts JOIN Clients as cs ON ts.client_id = cs.client_id JOIN Sites st on st.uid = ts.uid
                      JOIN console cos on cos.uid = ts.uid
                      WHERE ts.Site_id IN 
                      (SELECT site_no FROM client_site_groups WHERE group_id = :group_id AND client_id = :company_id) 
                      ORDER BY current_percent DESC";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
        }
    
        $stmt->execute();
    
        // Start building the email content
        $email_content = "
        <html>
        <head>
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                }
                th, td {
                    padding: 10px;
                    text-align: center;
                }
                th {
                    background-color: #003366;
                    color: white;
                    border-radius: 5px;
                }
                td {
                    background-color: #f0f8ff;
                    color: #003366;
                    border-radius: 5px;
                }
                tr:last-child td {
                    border-bottom: 1px solid #003366;
                }
                table, th, td {
                    border-radius: 8px;
                }
            </style>
        </head>
        <body>
            User: $email<br><br>
            <table>
                <tr><th>" . implode("</th><th>", $table_headers) . "</th></tr>";
    
        // Fetch and populate each row of data
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $email_content .= "<tr>
                    <td>" . htmlspecialchars($row['Client_name'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['Site_name']   ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['tank_id']     ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['dipr_date']   ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['dipr_time']   ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['capacity']    ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['current_volume'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['ullage']      ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars(
                        isset($row['current_percent']) 
                            ? number_format($row['current_percent'], 2) 
                            : '', 
                        ENT_QUOTES, 
                        'UTF-8'
                    ) . "%</td>
                    <td>" . htmlspecialchars($row['last_conndate'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                </tr>";

        }
    
        $email_content .= "
            </table>
        </body>
        </html>";
    
        // Debugging
        // echo $email_content . "<br>"; // For testing purposes
        send_email($email, "EHON VMI Stock Report", $email_content); // Sending the email
        // echo "$email, EHON VMI Stock Report, $email_content<br>";
    }
    


?>
