<?php
  include('../db/dbh2.php');
  include('../db/log.php');

    // Check if the form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        // Update or delete rows based on selected option value
        if (isset($_POST['edit_username']) && isset($_POST['edit_user'])) {
            $selectedValue = intval($_POST['edit_user']);
            $userId = intval($_POST['edit_usernameid']);
            $user_mail = $conn->real_escape_string($_POST['edit_username']);
          
            // Update the user's access level with the selected value
            $sql = "UPDATE login SET access_level = $selectedValue WHERE user_id = $userId and username = '$user_mail'";
            
            if ($conn->query($sql) === TRUE) {
                $conn->close();
                
                // Return JSON for AJAX requests
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                    exit();
                } else {
                    // Redirect for regular form submissions
                    header("Location: user-management.php");
                    exit();
                }
            } else {
                $error = $conn->error;
                $conn->close();
                
                // Return JSON error for AJAX requests
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Error updating user: ' . $error]);
                    exit();
                } else {
                    echo "Error updating user: " . $error;
                    exit();
                }
            }
        }

        $conn->close();
        
        // If we get here, parameters were missing
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            exit();
        } else {
            header("Location: user-management.php");
            exit();
        }
    }
?>
