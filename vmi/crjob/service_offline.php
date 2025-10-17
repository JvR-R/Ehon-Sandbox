<?php
    include('../db/dbh2.php');
    include('../db/email_conf.php');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Assuming $date and $time are your reference values
    $dateTimeReference = "$date $time";

    $query = "SELECT DISTINCT cs.uid,
                clt.Client_id,
                cs.last_conndate,
                cs.last_conntime,
                clt.Client_name,
                clt.Client_email,
                clt.Client_phone
            FROM   console            AS cs
            JOIN   Console_Asociation AS ca  ON ca.uid  = cs.uid
            JOIN   Clients            AS clt ON clt.client_id = ca.Client_id
            JOIN   Tanks              AS ts  ON ts.uid = cs.uid
            WHERE  cs.device_type IN (20, 200, 201, 30)
            AND  clt.client_id   <> 15100
            AND  cs.service_flag  = 0
            AND (
                TIMESTAMPDIFF(
                    HOUR,
                    TIMESTAMP(cs.last_conndate, cs.last_conntime),
                    ?
                ) > 27
            AND IFNULL(                        -- if diff is NULL, fall back to huge value
                    TIMESTAMPDIFF(
                        HOUR,
                        TIMESTAMP(ts.dipr_date, ts.dipr_time),
                        ?
                    ),
                    999999
                ) > 27
            );";

    $execquery = $conn->prepare($query);
    $execquery->bind_param("ss", $datetime, $datetime); // Bind your reference datetime
    $execquery->execute();
    $execquery->bind_result($cliend_id, $uid, $conndate, $conntime, $client_name, $client_email, $client_phone);

    // Initialize the email content as an HTML table with styles
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
                    text-align: left;
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
            <table>
                <tr>
                    <th>Client ID</th>
                    <th>UID</th>
                    <th>Last Connection Date</th>
                    <th>Last Connection Time</th>
                    <th>Client Name</th>
                    <th>Client Email</th>
                    <th>Client Phone</th>
                </tr>";

    while ($execquery->fetch()) {
        // Add each row to the email content
        $email_content .= "
            <tr>
                <td>$cliend_id</td>
                <td>$uid</td>
                <td>$conndate</td>
                <td>$conntime</td>
                <td>$client_name</td>
                <td>$client_email</td>
                <td>$client_phone</td>
            </tr>";
    }

    // Close the table and body tags
    $email_content .= "
            </table>
        </body>
        </html>";

    // Email subject and recipient (replace with actual values or loop through multiple recipients if needed)
    $email_subject = "Client Connection Report";
    $receiver_email = "support@petroindustrial.com.au"; // Replace with actual recipient email

    // Call the send_email function
    $result = send_email($receiver_email, $email_subject, $email_content);

    // Check the result of the email sending function
    if ($result['status'] == 'success') {
        echo $result['message'];
    } else {
        echo $result['message'];
    }


?>
