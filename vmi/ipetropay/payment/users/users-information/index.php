<?php
include('../../../../db/dbh.php');
include('../../../../db/logpriv.php'); 
include('../../../borderipay.php');
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <title>Company Information</title>
</head>
<body>
<main class="table">
<table class="full-size">
    <tbody>
        <form action="submit-form.php" method="post">
            <tr>
                <th> Company Name</th>
                <td><?php   
                    $companyId = $_GET['companyid'] ?? '';                 
                    $search_query = "";

                    if (!empty($companyId)) {
                        $search_query = $_GET["companyid"];
                        $companyName = "SELECT company_name FROM ipetroco_ehon_tsm.users WHERE id LIKE '%$search_query%'";
                        $resulttest = $conn->query($companyName);
                    }
                    if ($resulttest->num_rows > 0) {
                        $row = $resulttest->fetch_assoc();
                        echo $row["company_name"];
                    }
                ?></td>
            </tr>
            <tr>
            <th>Company Number</th>
                <td><?php

                    $search_query = "";

                    if (!empty($companyId)) {
                        $search_query = $_GET["companyid"];
                        $companyName = "SELECT company_number FROM ipetroco_ehon_tsm.users WHERE id LIKE '%$search_query%'";
                        $resulttest = $conn->query($companyName);
                    }
                    if ($resulttest->num_rows > 0) {
                        $row = $resulttest->fetch_assoc();
                        echo $row["company_number"];
                    }
                    ?>
                </td>
            </tr>
            <tr>
            <th>Fee Type</th>
                <td><?php
                    $search_query = "";

                    if (!empty($companyId)) {
                        $search_query = $_GET["companyid"];
                        $companyName = "SELECT id_fee FROM ipetroco_ehon_tsm.users WHERE id LIKE '%$search_query%'";
                        $resulttest = $conn->query($companyName);

                        if ($resulttest->num_rows > 0) {
                            $row = $resulttest->fetch_assoc();
                            $feeType = $row["id_fee"];

                            $sql = "SELECT fee_type FROM ipetroco_ehon_tsm.fees WHERE id_fee LIKE '%$feeType%'";
                            $result = $conn->query($sql);
                            if($result->num_rows > 0){
                                $rowfee = $result->fetch_assoc();
                                echo $rowfee["fee_type"];
                            }
                    }
                }
                ?>
                </td>
            </tr>
            <tr>
                <th>New Fee Type</th>
                <td>
                <select name="feeType" id="feeType">
                    <option value="">Select Fee Type</option>
                    <?php           

                    $sql = "SELECT * FROM ipetroco_ehon_tsm.fees"; // Assuming your table name is "fees"
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $id = $row["id_fee"];
                            $feeType = $row["fee_type"];
                            echo "<option value='$id'>$feeType</option>";
                        }
                    }

                    $conn->close();
                    ?>
                </select>
            </td>
            </tr>
            <tr>
                <th>Last Transaction Date</th>
                <td><?php
                    $search_query = "";

                    if (!empty($companyId)) {
                        $search_query = $_GET["companyid"];
                        $companyName = "SELECT max(transaction_date) FROM ipetroco_ehon_tsm.card_transaction WHERE company_id LIKE '%$search_query%'";
                        $resulttest = $conn->query($companyName);

                        if ($resulttest->num_rows > 0) {
                            $row = $resulttest->fetch_assoc();
                            echo $row["max(transaction_date)"];
                            }
                    }
                
                    ?>
                </td>
            </tr>
            <tr>
            <?php
            if ($accessLevel == 1) {
                ?>
                <th>Close Company</th>
                <td>
                <select name="closeCompany" id="closeCompany">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
            </td>
            </tr>
                <div class = buttonc2>
                <form2 action="submit-close.php" method="post">
                <span><br><input type="submit" value="Apply Change" style="font-weight: bold; font-size: 24px; color:white; background-color: #002F60;border-radius: 4px;cursor: pointer;padding: 5px 10px;border: none;"></span>
                </form2>
                </div>
            <?php
            }
            ?>
            <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
            <div class = buttonc>
            <span><br><br><input type="submit" value="Submit" style="font-weight: bold; font-size: 24px; color:white; background-color: #002F60;border-radius: 4px;cursor: pointer;padding: 5px 10px;border: none;"></span>
            </form>
            </div>
            </tbody>
</table>
</main>
</body>
</html>