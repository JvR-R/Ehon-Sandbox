<?php
// Retrieve form data
$username = $_POST['username'];
$password = $_POST['password'];
$accessLevel = 3;

// Validate form inputs (e.g., check for uniqueness, complexity)

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Connect to the database
$servername = "localhost";
$dbusername = "ipetroco_dev_admin_mysql";
$dbpassword = '$_i_dev789mysql';
$dbname = "ipetroco_ehon_tsm";
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the latest user_id from the login table
$sql = "SELECT MAX(user_id) AS max_user_id FROM ipetroco_ehon_tsm.login";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$maxUserId = $row['max_user_id'];

// Increment the user_id
$maxUserId++;

// Prepare and execute the insert query
$stmt = $conn->prepare("INSERT INTO ipetroco_ehon_tsm.login (user_id, username, password, access_level, company_id) VALUES (?, ?, ?, ?,?)");
$stmt->bind_param("issss", $maxUserId, $username, $hashedPassword, $accessLevel,$maxUserId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Data inserted successfully!";
    header("Location: /");
} else {
    echo "Error inserting data: " . $stmt->error;
}
// Close the statement and database connection
$stmt->close();
$conn->close();

// Redirect the user to a success page or perform any other desired actions
exit();
?>
