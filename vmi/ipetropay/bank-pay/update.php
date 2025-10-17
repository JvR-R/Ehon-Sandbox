<?php
    include('/home/ehonener/public_html/vmi/db/dbh.php');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'PhpSpreadsheet/vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\IOFactory;

    // Check if the form was submitted
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // echo "Form submitted.<br>";

        // Check if a file was uploaded successfully
        if (isset($_FILES["file"]) && $_FILES["file"]["error"] === UPLOAD_ERR_OK) {
            // echo "File uploaded successfully.<br>";

            // Process the uploaded file
            $file = $_FILES["file"]["tmp_name"];

            // Load PhpSpreadsheet
            $spreadsheet = IOFactory::load($file);
            if ($spreadsheet) {
                // echo "Spreadsheet loaded.<br>";
            } else {
                // echo "Failed to load spreadsheet.<br>";
                exit;
            }

            $sheet = $spreadsheet->getActiveSheet();
            $currentRow = 1;
            $columnLIndex = 12; // Assuming column L is the 12th column (index 11)
            $columnDIndex = 4;
            $columnBIndex = 2;
            $columnIIndex = 9;

            // Iterate through rows
            foreach ($sheet->getRowIterator() as $row) {
                if ($currentRow >= 8) {
                    // echo "Processing row {$row->getRowIndex()}.<br>";
                    
                    $cell = $sheet->getCellByColumnAndRow($columnLIndex, $row->getRowIndex());
                    $cell2 = $sheet->getCellByColumnAndRow($columnDIndex, $row->getRowIndex());
                    $cell3 = $sheet->getCellByColumnAndRow($columnBIndex, $row->getRowIndex());
                    $cell4 = $sheet->getCellByColumnAndRow($columnIIndex, $row->getRowIndex());

                    $valueFromExcel = $cell->getValue();
                    if ($valueFromExcel !== null) {
                        $formattedValue = number_format((float)$valueFromExcel, 2, '.', '');
                    } else {
                        // echo "No value found in column L for row {$row->getRowIndex()}, skipping.<br>";
                        continue;
                    }

                    $valueFromExcel2 = $cell2->getValue();
                    $valueFromExcel3 = $cell3->getValue();
                    $valueFromExcel4 = $cell4->getValue();
                    
                    if ($valueFromExcel4 == 'PURCHASE-F') {
                        // Date parsing and correction
                        $dateParts = explode('/', $valueFromExcel2);
                        if (count($dateParts) === 3) {
                            // Manually correct the year if it's two digits
                            if (strlen($dateParts[2]) == 2) {
                                $dateParts[2] = '20' . $dateParts[2]; // Convert 24 to 2024
                            }

                            // Join the corrected parts back into a string
                            $correctedDateString = implode('/', $dateParts);

                            // Now create the DateTime object
                            $dateObj = DateTime::createFromFormat('d/m/Y', $correctedDateString);

                            if ($dateObj === false) {
                                // Log detailed error for the date parsing
                                $dateErrors = DateTime::getLastErrors();
                                // echo "Error parsing date: $correctedDateString. Expected format: d/m/Y.<br>";
                                print_r($dateErrors['errors']);
                            } else {
                                // Format the date for display
                                $formattedDate = $dateObj->format('d-m-Y');
                                // echo "Formatted Date: " . $formattedDate . "<br>";

                                // Format the date as Y-m-d for database insertion (MySQL expects dates in this format)
                                $formattedDateForDB = $dateObj->format('Y-m-d');

                                // Define a small tolerance for price comparison
                                $epsilon = 0.01; // Adjust based on acceptable price difference

                                // Use the DATE() function to compare the date without the time part
                                $query = "SELECT * FROM card_transaction WHERE DATE(transaction_date) = ? AND ABS(total_price - ?) < ?";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("ssd", $formattedDateForDB, $formattedValue, $epsilon);
                                $stmt->execute();
                                $stmt->store_result();

                                if ($stmt->num_rows > 0 && $stmt->num_rows < 2) {
                                    $updatequery = "UPDATE card_transaction SET flag = 2 WHERE CAST(total_price AS CHAR) = ? and DATE(transaction_date) = ? and flag = 0";
                                    $updateStmt = $conn->prepare($updatequery);
                                    $updateStmt->bind_param("ss", $formattedValue, $formattedDateForDB);
                                    $updateStmt->execute();
                                } elseif ($stmt->num_rows >= 2) {
                                    $updatequery = "UPDATE card_transaction SET flag = 3 WHERE CAST(total_price AS CHAR) = ? and DATE(transaction_date) = ? and flag = 0";
                                    $updateStmt = $conn->prepare($updatequery);
                                    $updateStmt->bind_param("ss", $formattedValue, $formattedDateForDB);
                                    $updateStmt->execute();
                                } else {
                                    // Insert missing transaction with the corrected date format
                                    $insertQuery = "INSERT INTO missing_transaction (transaction_number, transaction_date, total_price) VALUES (?, ?, ?)";
                                    $inserstmt = $conn->prepare($insertQuery);
                                    $inserstmt->bind_param("sss", $valueFromExcel3, $formattedDateForDB, $formattedValue);
                                    $inserstmt->execute();

                                    if ($inserstmt->affected_rows > 0) {
                                        // echo "Insert successful for row {$row->getRowIndex()}.<br>";
                                    } else {
                                        // echo "Insert NOT successful for row {$row->getRowIndex()}.<br>";
                                    }
                                }
                            }
                        }
                    } else {
                        // echo "Skipping row {$row->getRowIndex()} as it doesn't match 'PURCHASE-F'.<br>";
                    }
                } else {
                    $currentRow++;
                }
            }

            // Close the database connection
            $conn->close();       
            header("Location: index.php?status=success&message=" . urlencode("File processed successfully!"));
            exit;
        } else {
            header("Location: index.php?status=error&message=" . urlencode("Error processing file: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: index.php?status=error&message=" . urlencode("File upload failed."));
        exit;
    }

    $stmt->close();
?>
