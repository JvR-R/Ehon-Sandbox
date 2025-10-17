<?php
include('../../db/dbh2.php');
include('../../db/check.php');
include('../../db/cs_msg.php');
include('../../db/Datetime.php');

$logOutput = ""; // Initialize a variable to store log messages
$logFilePath = "../Logs/Link-log.log"; // Define the log file path
$temporaryDir = "chunks/"; // Directory to temporarily store chunks
$assembledDir = "reassembled/"; // Directory to store assembled files

$date = date('Y-m-d'); // Assuming you have a $date variable to log the date
$time = date('H:i:s'); // Assuming you have a $time variable to log the time
$logOutput .= "\r\n$date, $time, ";

// Ensure directories exist
if (!is_dir($temporaryDir)) {
    mkdir($temporaryDir, 0777, true);
}
if (!is_dir($assembledDir)) {
    mkdir($assembledDir, 0777, true);
}

// Check if data is received via the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the value of the counter parameter
    $message = file_get_contents("php://input");
    $logOutput .= $message . "\r\n";
} else {
    $logOutput .= "Sv POST: Error\r\n";
    file_put_contents($logFilePath, $logOutput, FILE_APPEND);
    exit;
}

// Check if any data is received
if (!empty($message)) {
    $separatedParts = separateMessage($message);
    $uid = $separatedParts['uid'];
    $type = $separatedParts['msgtype'];
    $checksummsg = $separatedParts['checksum'];
    $msgdata = $separatedParts['data'];
    $messcheck = substr($message, 0, -3);
    $chk = checksum($messcheck);
    $wresp = ""; // Initialize $wresp for file assembly response
    $resp = ""; // Initialize $resp variable for chunk receipt

    if ($checksummsg == $chk) {
        if ($type == 'LG') {
            $msgdataArray = json_decode($msgdata, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Check the structure of the JSON data to determine what type of message it is
                if (isset($msgdataArray['files']) && isset($msgdataArray['totalFiles']) && isset($msgdataArray['totalChunks'])) {
                    // This is a file metadata message
                    $resp = "OK";
                    // Save metadata for future use
                    foreach ($msgdataArray['files'] as $file) {
                        $fileName = $file['name'];
                        $totalChunks = $file['chunks'];
                        // Store metadata in a way that it can be retrieved later (e.g., in a database, file, etc.)
                        file_put_contents($temporaryDir . $fileName . '.meta', $totalChunks);
                    }
                } elseif (isset($msgdataArray['file']) && isset($msgdataArray['chunkIndex']) && isset($msgdataArray['data'])) {
                    // This is a chunk data message
                    $fileName = $msgdataArray['file'];
                    $chunkIndex = $msgdataArray['chunkIndex'];
                    $chunkData = base64_decode($msgdataArray['data']); // Decode the chunk data

                    // Ensure $chunkData is not null
                    if ($chunkData !== false) {
                        // Create a temporary file for this chunk
                        $tempFilePath = $temporaryDir . $fileName . '.' . $chunkIndex;

                        // Check if the chunk already exists (duplicate check)
                        if (file_exists($tempFilePath)) {
                            $resp = "Duplicate"; // Mark as duplicate if chunk exists
                        } else {
                            // Save the chunk if it doesn't exist
                            file_put_contents($tempFilePath, $chunkData);
                            $resp = "OK"; // Mark as OK if chunk is successfully saved

                            // Retrieve total chunks from stored metadata
                            $metadataPath = $temporaryDir . $fileName . '.meta';
                            if (file_exists($metadataPath)) {
                                $totalChunks = (int)file_get_contents($metadataPath);

                                // Check if all chunks are received
                                if (allChunksReceived($fileName, $totalChunks)) {
                                    reassembleFile($fileName, $totalChunks);
                                    $wresp = "File Assembled"; // Set $wresp to indicate successful file assembly
                                }
                            } else {
                                $resp = "Error: Metadata for file $fileName not found.";
                            }
                        }
                    } else {
                        $resp = "Error decoding base64 data for chunk $chunkIndex of file $fileName.";
                    }
                } else {
                    $resp = "Error: JSON data structure does not match expected formats.";
                }
            } else {
                $resp = "Error decoding JSON data: " . json_last_error_msg();
            }
        } else {
            $resp = "Wrong Type\r\n";
        }
    } else {
        $resp = "Wrong Checksum\r\n";
    }
} else {
    $resp = "Sv EMPTY: $message\r\n";
}

$response = ['Response' => $resp];
response($response, $uid, $type);
$logOutput .= $resp;
$logOutput .= "\nLog file path: $logFilePath\n"; // Debugging line

if (file_put_contents($logFilePath, $logOutput, FILE_APPEND) === false) {
    error_log("Failed to write to log file: $logFilePath");
} else {
    error_log("Successfully wrote to log file: $logFilePath");
}

/**
 * Check if all chunks for a file are received.
 */
function allChunksReceived($fileName, $totalChunks) {
    global $temporaryDir;
    for ($i = 0; $i < $totalChunks; $i++) {
        if (!file_exists($temporaryDir . $fileName . '.' . $i)) {
            return false;
        }
    }
    return true;
}

/**
 * Reassemble the file from its chunks.
 */
function reassembleFile($fileName, $totalChunks) {
    global $temporaryDir, $assembledDir;
    $assembledFilePath = $assembledDir . $fileName;
    $fileHandle = fopen($assembledFilePath, 'w');

    if ($fileHandle === false) {
        echo "Error opening file $assembledFilePath for writing.";
        return;
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $temporaryDir . $fileName . '.' . $i;
        $chunkData = file_get_contents($chunkPath);
        fwrite($fileHandle, $chunkData);
        unlink($chunkPath); // Remove the chunk file after use
    }

    fclose($fileHandle);
    echo "File $fileName has been reassembled.";
}
?>
