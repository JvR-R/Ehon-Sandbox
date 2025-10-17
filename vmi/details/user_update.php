<?php
  include('../db/dbh2.php');
  include('../db/log.php');

    // Check if the form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Update or delete rows based on selected option value
        if (isset($_POST['edit_username']) && isset($_POST['edit_user'])) {
            $selectedValue = $_POST['edit_user'];
            $userId = $_POST['edit_usernameid'];
            $user_mail = $_POST['edit_username'];
          
            // Update the user's access level with the selected value
            $sql = "UPDATE login SET access_level = $selectedValue WHERE user_id = $userId and username like '$user_mail'";
            if ($conn->query($sql) === TRUE) {
                // echo "User updated successfully: SET access_level = $selectedValue WHERE user_id = $userId and client_id = $companyId and username = '$user_mail";
            } else {
                echo "Error updating user: " . $conn->error;
            }
            
        }

        $conn->close();
        header("Location: user-management.php");
        exit();
    }
?>
