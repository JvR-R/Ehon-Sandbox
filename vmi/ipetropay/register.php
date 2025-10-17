<?php
include('../db/dbh.php');
include('../db/log.php');  
// Retrieve form data
$username = $_POST['username'];
$password = $_POST['password'];
$accessLevel = 3;

// Validate form inputs (e.g., check for uniqueness, complexity)

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);


// Get the latest user_id from the login table
$sql = "SELECT MAX(user_id) AS max_user_id FROM login";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$maxUserId = $row['max_user_id'];

// Increment the user_id
$maxUserId++;

// Prepare and execute the insert query
$stmt = $conn->prepare("INSERT INTO login (user_id, username, password, access_level) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $maxUserId, $username, $hashedPassword, $accessLevel);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // echo "Data inserted successfully!";
    header("Location: /login");
} else {
    // echo "Error inserting data: " . $stmt->error;
}
// Close the statement and database connection
$stmt->close();
$conn->close();

// Redirect the user to a success page or perform any other desired actions
exit();
?>
