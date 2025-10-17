<?php
// ---------------------
// 1) Database connection
// ---------------------
define('ROOT_PATH', dirname(__DIR__));  // Goes up one directory from the current directory

// Define paths relative to the root path
define('DB_PATH', ROOT_PATH . '/db/dbh2.php');
define('LOG_PATH', ROOT_PATH . '/db/log.php');
define('BORDER_PATH', ROOT_PATH . '/db/border.php');

// Include files using defined paths
include(DB_PATH);



// --------------------------------------------------------------------
// 2) Build and run the SELECT query to get all candidate rows to insert
// --------------------------------------------------------------------

// NOTE: Adjust the query as needed
$sql = "
  SELECT
      ca.Client_id          AS client_id,
      ct.card_number        AS card_number,
      ct.registration       AS registration,
      20.80                AS tax_value
  FROM client_transaction ct
  JOIN console_asociation ca
      ON ca.uid = ct.uid
  WHERE ca.Client_id IN (
      SELECT DISTINCT(Client_id)
      FROM Console_Asociation
      WHERE uid IN (
          SELECT uid
          FROM console
      )
  )
  GROUP BY
      ca.Client_id,
      ct.card_number,
      ct.registration
";

$result = $conn->query($sql);
if (!$result) {
    die("Error retrieving data: " . $conn->error);
}

// ---------------------------------------------
// 3) Loop over rows & insert if not already in
// ---------------------------------------------
while ($row = $result->fetch_assoc()) {
    $client_id    = $row['client_id'];
    $card_number  = $row['card_number'];
    $registration = $row['registration'];
    $tax_value    = $row['tax_value'];

    // -- 3A) Check if (card_number, registration) already exists in client_tasbax
    $check_sql = "
        SELECT 1
        FROM client_tasbax
        WHERE card_number = ? AND registration = ?
        LIMIT 1
    ";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ss", $card_number, $registration);
    $stmt_check->execute();
    $stmt_check->store_result();

    // -- 3B) Only insert if that combination is NOT found
    if ($stmt_check->num_rows === 0) {
        $insert_sql = "
            INSERT INTO client_tasbax (client_id, card_number, registration, tax_value)
            VALUES (?, ?, ?, ?)
        ";
        $stmt_insert = $conn->prepare($insert_sql);
        // client_id = int, card_number & registration = string, tax_value = double
        $stmt_insert->bind_param("issd", $client_id, $card_number, $registration, $tax_value);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    $stmt_check->close();
}

// ----------------------------
// 4) Close everything
// ----------------------------
$result->free();
$conn->close();

echo "Done!";
?>
