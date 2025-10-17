<?php
    include('../../../db/dbh.php');
    include('../../../db/logpriv.php');
    include('../../../ipetropay/borderipay.php');
?>

<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Card Transactions</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
</head>
<body>
    <main class="table">
            <section class="table__header">
                <h1>Card Transactions: <?php                                               
                                                $companyId = $_GET['companyid'] ?? '';
                                                $search_query = "";
                                                if (!empty($companyId)) {
                                                    $search_query = $_GET["companyid"];
                                                    $companyName = "SELECT DISTINCT(us.company_name) FROM users AS us INNER JOIN card_transaction AS ct ON us.id = ct.company_id WHERE ct.flag in (0,2,3) AND us.id LIKE '%$search_query%'";
                                                    $resulttest = $conn->query($companyName);
                                                }
                                                if ($resulttest->num_rows > 0) {
                                                    $row = $resulttest->fetch_assoc();
                                                    echo $row["company_name"];
                                                }
                                            ?>
                                            </h1>
            <form method="post" action="export2" id="exportForm">
                <button type="submit" name="export" style="background: none; border: none; padding: 0; cursor: pointer;">
                    <img src="/vmi/images/csv.png" alt="Export Selected" style="width: 50px; height: 50px;">
                </button>
                <br>
            </section>
            <section class="table__body">
                <table>
                    <thead>
                        <tr>
                            <th> Transaction Time <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Transaction Date <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Card Holder Name <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Card Number <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Site Name <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Product <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Customer Price <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Volume <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Total Price <span class="icon-arrow">&UpArrow;</span></th>
                            <th> Fee Type </th>
                            <th>
                                Paid
                            </th>


                        </tr>
                    </thead>
    <?php
    $companyId = $_GET['companyid'] ?? '';
    // Retrieve data from fullmsg table
    $sql = "SELECT * FROM users AS us INNER JOIN card_transaction AS ct ON us.id = ct.company_id INNER JOIN fees as tf on us.id_fee = tf.id_fee WHERE ct.flag in (0,2,3)";
    $result = $conn->query($sql);
    $search_query = "";

    if (!empty($companyId)) {
    $search_query = $_GET["companyid"];
    $companyName = "SELECT distinct(us.company_name) FROM users AS us INNER JOIN card_transaction AS ct ON us.id = ct.company_id WHERE ct.flag in (0,2,3) AND us.id LIKE '%$search_query%'";
    $sql3 = "SELECT * FROM users AS us INNER JOIN card_transaction AS ct ON us.id = ct.company_id INNER JOIN fees as tf on us.id_fee = tf.id_fee WHERE ct.flag in (0,2,3) AND us.id LIKE '%$search_query%'";
    $result = $conn->query($sql3);
    $resulttest = $conn->query($companyName);
    }


    // Display data in an HTML table with borders and search functionality
    if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                
                echo "<tr style= color: black;'>";
                echo "<form method='post' action=''>";
                echo "<td>" . $row["transaction_time"] . "</td>";
                echo "<td>" . $row["transaction_date"] . "</td>";
                echo "<td>" . $row["card_holder_name"] . "</td>";
                echo "<td>" . $row["card_number"] . "</td>";
                echo "<td>" . $row["site_name"] . "</td>";
                echo "<td>" . $row["product_name"] . "</td>";
                echo "<td class='price-customer'>" . number_format($row["price_customer"] ?? 0, 2) . "</td>";
                echo "<td data-quantity='" . $row["quantity"] . "'>" . number_format($row["quantity"], 2) . "</td>";
                echo "<td contenteditable='true' class='editable' data-transaction-id='" . $row["transaction_id"] . "'>" . number_format($row["total_price"] ?? 0, 2) . "</td>";
                echo "<td>" . $row["fee_type"] . "</td>";
                if($row["flag"] == 2){
                    echo "<td class='checkbox-column' style='padding: 8px;'>
                            <input type='checkbox' name='selected_checkboxes[]' value='" . $row["transaction_id"] . "' checked>
                            </td>";
                }
                elseif($row["flag"] == 0 || $row["flag"] == 3){
                    echo "<td class='checkbox-column' style='padding: 8px;'><input type='checkbox' name='selected_checkboxes[]' value='" . $row["transaction_id"] . "'></td>";
                
                }
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
            echo "<br>";
            echo "</form>";
        echo "</main>";
    echo "</body>";
} else {
    echo "0 results";
}

$conn->close();

?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to all cells with class 'editable'
    const editableCells = document.querySelectorAll('.editable');

    editableCells.forEach(function(cell) {
        cell.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.blur();
            }
        });

        cell.addEventListener('blur', function(e) {
            const newValue = this.textContent.trim();
            const transactionId = this.getAttribute('data-transaction-id');
            const row = this.parentElement;
            const quantityCell = row.querySelector('td[data-quantity]');
            const quantity = parseFloat(quantityCell.getAttribute('data-quantity'));

            // Validate newValue and quantity
            const newTotalPrice = parseFloat(newValue);
            if (!isNaN(newTotalPrice) && !isNaN(quantity) && quantity !== 0) {
                // Calculate price_local and price_customer
                const newPrice = parseFloat((newTotalPrice / quantity).toFixed(2));

                // Send AJAX request to update database
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_total_price.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        // Update the 'Customer Price' cell
                        const priceCustomerCell = row.querySelector('.price-customer');
                        priceCustomerCell.textContent = newPrice.toFixed(2);
                    } else {
                        alert('Update failed. Please try again.');
                    }
                };
                const params = 'transaction_id=' + encodeURIComponent(transactionId)
                    + '&total_price=' + encodeURIComponent(newTotalPrice)
                    + '&price_local=' + encodeURIComponent(newPrice)
                    + '&price_customer=' + encodeURIComponent(newPrice);
                xhr.send(params);
            } else {
                alert('Invalid input. Please enter a valid number.');
            }
        });
    });
});
</script>
