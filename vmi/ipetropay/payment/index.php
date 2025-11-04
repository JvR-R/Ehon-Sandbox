<?php
	include('../../db/dbh.php');
	include('../../db/logpriv.php'); 
	include('../borderipay.php');
?>


<!DOCTYPE html>
<html>
<head>
	<title>Transactions Search Page</title>
	<!-- Include jQuery library -->
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<!-- Include jQuery UI library -->
	<link rel="stylesheet" href="style.css">
	<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
</head>
<body>
<main class = "table">

		<h2>New Transactions</h2>

	<!-- Search Container -->
	<div class="search-container">
		<!-- Search Bar 1 -->
		<select id="search-bar1" class="search-input">
			<option value="000">Select a company</option>
			<?php
			

			// Retrieve the company IDs and names from the users table
			$sql = "SELECT DISTINCT us.id, us.company_name 
			FROM users AS us 
			INNER JOIN card_transaction AS ct ON us.id = ct.company_id 
			WHERE ct.flag in (0,2,3)";
			$result = $conn->query($sql);

			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$id = $row["id"];
					$companyName = $row["company_name"];
					echo "<option value='$id'>$companyName</option>";
				}
			}

			$conn->close();
			?>
		</select>
		<!-- Search Button -->
		<span><br><button onclick="search()" class="search-button">Search</button></span>

	</div>


	<script>
		function search() {
			// Retrieve the values from the search bars
			var companyId = document.getElementById("search-bar1").value;

			if(companyId !== "000"){
			// Redirect to the PHP script with the search conditions as query parameters
			window.location.href = "show/index.php?companyid=" + companyId;
			}
		}
	</script>
</main>
</body>
</html>

<?php
