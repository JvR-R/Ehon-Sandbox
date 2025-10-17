<?php
include('../../../../db/dbh.php');
include('../../../../db/logpriv.php');
include('../../../borderipay.php');

$companyId = $_GET['companyid'] ?? '';
$companyName = "";

if ($companyId) {
    $stmt = $conn->prepare("SELECT DISTINCT us.company_name FROM users us INNER JOIN card_transaction ct ON us.id = ct.company_id WHERE us.id = ?");
    $stmt->bind_param('s', $companyId);
    $stmt->execute();
    $stmt->bind_result($companyName);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Transactions</title>
    <link rel="stylesheet" href="style_v2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="script.js"></script>
</head>
<body>
    <main class="table">
        <section class="table__header">
            <h1>Archive: <?= htmlspecialchars($companyName); ?></h1>
        </section>
        <section class="table__body">
            <table>
                <thead>
                    <tr>
                        <th>Transaction Time</th>
                        <th>Transaction Date</th>
                        <th>Card Holder Name</th>
                        <th>Card Number</th>
                        <th>Site Price</th>
                        <th>Volume</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Reset</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $flag = "";
                    $stmt = $conn->prepare("
                        SELECT ct.transaction_id, ct.transaction_time, ct.transaction_date, ct.card_holder_name, ct.card_number,
                               ct.price_customer, ct.quantity, ct.total_price, ct.flag
                        FROM users us
                        INNER JOIN card_transaction ct ON us.id = ct.company_id
                        WHERE us.id = ?
                        ORDER BY ct.transaction_date DESC, ct.transaction_time DESC
                    ");
                    $stmt->bind_param('s', $companyId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            if($row["flag"] == 1){
                                $flag = "Paid";
                            } else {
                                $flag = "Pending";
                            }
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["transaction_time"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["transaction_date"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["card_holder_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["card_number"]) . "</td>";
                            echo "<td>" . ($row["price_customer"] !== null ? number_format($row["price_customer"], 2) : 'NA') . "</td>";
                            echo "<td>" . number_format($row["quantity"], 2) . "</td>";
                            echo "<td>" . number_format($row["total_price"], 2) . "</td>";
                            echo "<td>" . htmlspecialchars($flag). "</td>";
                            echo "<td>
                                    <button class='reset-btn' data-id='" . htmlspecialchars($row["transaction_id"]) . "'>
                                        Reset
                                    </button>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No transactions found.</td></tr>";
                    }

                    $stmt->close();
                    $conn->close();
?>
                </tbody>
            </table>
        </section>
    </main>

    <script src="script.js"></script>
    <script>
$(document).ready(function(){
    $('.reset-btn').click(function(){
        const transactionId = $(this).data('id');
        if(confirm('Are you sure you want to reset this transaction?')){
            $.ajax({
                url: 'reset_transaction.php',
                method: 'POST',
                data: { transaction_id: transactionId },
                dataType: 'json',
                success: function(response) {
                    if(response.success){
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(){
                    toastr.error('Error resetting transaction.');
                }
            });
        }
    });
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</body>
</html>
