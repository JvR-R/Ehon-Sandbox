<?php
  include('../../db/dbh2.php');
  include('../../db/log.php');


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the "selected_checkboxes" and "site_no" keys exist in the POST data
    if (isset($_POST["selected_checkboxes"])) {
        $selectedCheckboxes = $_POST["selected_checkboxes"];
        // Assuming you have previously defined $selected_group and $companyId
        $selected_group = $_POST["selected_group"];
        $companyId = $_POST["company_id"];
        // echo $selected_group . "<br>" . $companyId . "<br>";
        $sqldel = "DELETE FROM client_site_groups WHERE client_id = ? and group_id = ?";
        $stmt = $conn->prepare($sqldel);
        $stmt->bind_param("ii", $companyId, $selected_group);
        $stmt->execute();
        $stmt->close();

        foreach ($selectedCheckboxes as $item) {
            $parts = explode('|', $item);
            // echo $item . "<br>";
            if (count($parts) == 2) {
                list($siteId,$cname) = $parts;
                // echo "Site ID: " . $siteId . "-  Site Name: " . $cname . "<br>";
                $sqlupd = "INSERT INTO client_site_groups (group_id, client_id, site_no, site_name) VALUES (?, ?, ?, ?)";
                $stmt1 = $conn->prepare($sqlupd);
                $stmt1->bind_param("diis", $selected_group, $companyId, $siteId, $cname);
                $stmt1->execute();
                $stmt1->close();
            } else {
                // echo "Unexpected format for checkbox value: " . $item . "<br>";
            }
        }
        header("Location: index.php");
       
    } else {
        // echo "Invalid or missing data in the form submission.";
    }

    
}



?>