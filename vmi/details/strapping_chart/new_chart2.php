<?php
include('../../db/dbh2.php');
include('../../db/log.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $companyId = $_POST['companyId'] ?? '';

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        // Process the uploaded CSV file
        $csvFile = $_FILES['csv_file']['tmp_name'];
        $originalFileName = $_FILES['csv_file']['name'];

        // Extract the file name without extension to use as chart name
        $chartName = pathinfo($originalFileName, PATHINFO_FILENAME);
        
        // Validate chart name is not blank
        if (trim($chartName) === '') {
            $conn->close();
            header('Location: /vmi/details/strapping_chart/?status=error&msg=name_blank');
            exit();
        }
        
        // Validate chart name - no spaces allowed
        if (strpos($chartName, ' ') !== false) {
            $conn->close();
            header('Location: /vmi/details/strapping_chart/?status=error&msg=name_spaces');
            exit();
        }
        
        // Validate chart name length (max 12 characters)
        if (strlen($chartName) > 12) {
            $conn->close();
            header('Location: /vmi/details/strapping_chart/?status=error&msg=name_length&len=' . strlen($chartName));
            exit();
        }

        $data = array();

        if (($handle = fopen($csvFile, 'r')) !== false) {
            // Read the header row
            $headers = fgetcsv($handle, 1000, ",");

            // Remove BOM from the first header if present
            if (isset($headers[0])) {
                // Check if BOM is present at the beginning of the first header
                $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
                if (0 === strncmp($headers[0], $bom, 3)) {
                    $headers[0] = substr($headers[0], 3);
                }
            }

            // Map headers to column indices
            $headerMap = array();
            foreach ($headers as $index => $header) {
                $normalizedHeader = strtolower(trim($header));
                $headerMap[$normalizedHeader] = $index;
            }

            // Check if required columns exist
            if (!isset($headerMap['volume']) || !isset($headerMap['mm'])) {
                fclose($handle);
                $conn->close();
                header('Location: /vmi/details/strapping_chart/?status=error&msg=csv_columns');
                exit();
            }

            // Read data rows
            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                $volumeIndex = $headerMap['volume'];
                $levelIndex = $headerMap['mm'];
                $volume = $row[$volumeIndex];
                $level = $row[$levelIndex];

                // Validate and sanitize input
                if (is_numeric($volume) && is_numeric($level)) {
                    $data[] = array('volume' => $volume, 'height' => $level);
                }
            }
            fclose($handle);

            // Validate maximum 40 rows
            if (count($data) > 40) {
                $conn->close();
                header('Location: /vmi/details/strapping_chart/?status=error&msg=max_rows&rows=' . count($data));
                exit();
            }

            if (!empty($data)) {
                // Encode data to JSON
                $jsonData = json_encode($data);

                // Prepare and execute the SQL insert statement
                $sql = "INSERT INTO strapping_chart (client_id, chart_name, json_data) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    $conn->close();
                    header('Location: /vmi/details/strapping_chart/?status=error&msg=db_error');
                    exit();
                }
                $stmt->bind_param("iss", $companyId, $chartName, $jsonData);

                if ($stmt->execute()) {
                    header('Location: /vmi/details/strapping_chart/?status=created');
                } else {
                    header('Location: /vmi/details/strapping_chart/?status=error');
                }
                $stmt->close();
                $conn->close();
                exit();
            } else {
                $conn->close();
                header('Location: /vmi/details/strapping_chart/?status=error&msg=no_data');
                exit();
            }
        } else {
            // Error opening the CSV file
            $conn->close();
            header('Location: /vmi/details/strapping_chart/?status=error&msg=csv_read');
            exit();
        }
    } else {
        // No file uploaded or error occurred
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=no_file');
        exit();
    }
}
?>
