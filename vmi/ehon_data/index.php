<head>
  <link rel="stylesheet" href="stylesheet.css">
</head>

<?php
// Connect to MySQL server
$servername = "localhost"; // Replace with your MySQL server IP address or hostname
$username = "ipetroco_dev_admin_mysql"; // Replace with your MySQL login username
$password = '$_i_dev789mysql'; // Replace with your MySQL login password
$dbname = "ipetroco_ehon"; // Replace with the name of your MySQL database

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve data from fullmsg table
$sql = "SELECT * FROM ipetroco_ehon.fullmsg";
$result = $conn->query($sql);
// Retrieve data from Card_List_Updated table
$sql2 = "SELECT * FROM ipetroco_ehon.Card_List_Updated";
$result3 = $conn->query($sql2);
$search_query = "";

if (isset($_POST["search"])) {
    $search_query = $_POST["search_query"];
		$sql3 = "SELECT * FROM ipetroco_ehon.Card_List_Updated WHERE Serial LIKE '%$search_query%'";
        $result3 = $conn->query($sql3);	
}


// Display data in an HTML table with borders
if ($result->num_rows > 0) {
	echo "<div style='display: flex; justify-content: flex-start; align-items: center;'>";
	echo "<button class='my-button' onclick=\"window.location.href='/'\">Home</button>";
	echo "<div style='display: flex; justify-content: center; align-items: center; flex-grow: 1;'>";
	echo "<h2>Full Message</h2>";
	echo "</div>";
	echo "</div>";
	echo "<div style='display: flex; justify-content: center;'>";
	echo "<button class='my-button' onclick=\"window.location.href='/console-test/ehon_lite/Config-hist'\">Config Hist</button>";
	echo "<span style='margin: 0 10px;'></span>"; // Adding spacing between the buttons
	echo "<button class='my-button' onclick=\"window.location.href='/console-test/ehon_lite/Card-List-Hist'\">Card List Historical</button>";
	echo "</div>";
	echo "<br><table style='border-collapse: collapse;'>";
    echo "<tbody>";
    echo "<tr style='background-color: #3B5998; color: white;'>";
    echo "<th style='border: 1px solid black; padding: 8px;'>ID</th>";
    echo "<th style='border: 1px solid black; padding: 8px;'>Full Message</th>";
    echo "</tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr style='background-color: #FBFBFB; color: black;'>";
        echo "<td style='border: 1px solid black; padding: 8px;'>" . $row["id"]. "</td>";
        echo "<td style='border: 1px solid black; padding: 8px;'>" . $row["fullmsg"]. "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "0 results";
}
// Display data in an HTML table with borders and search functionality

		

$conn->close();

?>

