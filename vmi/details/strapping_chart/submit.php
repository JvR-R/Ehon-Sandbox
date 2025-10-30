<?php
  include('../../db/dbh2.php');
  include('../../db/log.php');

if (!function_exists('sc_write_log')){
  function sc_write_log($message){
    $logDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/vmi/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $logFile = $logDir.'/strapping_chart_export.log';
    $ts = date('Y-m-d H:i:s');
    $entry = '['.$ts.'] '.$message.PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
  }
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve the companyId and feeType values from the POST data
    $companyId = $_POST['companyId'] ?? '';
    $chart = $_POST['chart'];
    $strname = $_POST['strname'];
    
    // Validate chart name is not blank
    if (trim($strname) === '') {
        sc_write_log('Chart name validation failed: blank name');
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=name_blank');
        exit();
    }
    
    // Validate chart name - no spaces allowed
    if (strpos($strname, ' ') !== false) {
        sc_write_log('Chart name validation failed: contains spaces');
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=name_spaces');
        exit();
    }
    
    // Validate chart name length (max 12 characters)
    if (strlen($strname) > 12) {
        sc_write_log('Chart name validation failed: ' . strlen($strname) . ' characters');
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=name_length');
        exit();
    }
    
    // Validate max 40 rows
    if ($chart > 41) { // chart value is rows + 1
        sc_write_log('Chart rows validation failed: ' . ($chart - 1) . ' rows');
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=max_rows');
        exit();
    }
    
    $data = array(); // Initialize an empty array

    for ($i = 1; $i <= $chart; $i++) {
        $level = isset($_POST['level' . $i]) ? $_POST['level' . $i] : null; // Get the 'level' value from POST, default to null if not set
        $volume = isset($_POST['volume' . $i]) ? $_POST['volume' . $i] : null; // Get the 'volume' value from POST, default to null if not set
    
        // Only add to the array if both values are provided
        if ($level !== null && $volume !== null) {
            $data[] = array('volume' => $volume, 'height' => $level);
        }
    }
    

        $jsonData = json_encode($data);
            
    
         // Prepare and execute the SQL insert statement
        $sql = "INSERT INTO strapping_chart (client_id, chart_name, json_data) 
        VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $companyId, $strname, $jsonData);

        if ($stmt->execute()) {
            $newChartId = $conn->insert_id;
            sc_write_log('DB insert OK for chart_id=' . $newChartId . ', name=' . $strname);

            $fileWriteOk = false;
            $permIssue = false;

            // Build export payload
            $rowsArray = json_decode($jsonData, true);
            if (!is_array($rowsArray)) { $rowsArray = array(); }
            $exportPayload = array(
              'name' => $strname,
              'rows' => $rowsArray
            );
            $exportJson = json_encode($exportPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Prepare target dir and filename
            $targetDir = '/home/ehon/files/Charts';
            $parentDir = dirname($targetDir);
            $dirExists = is_dir($targetDir);
            sc_write_log('Target dir pre-check: ' . $targetDir . ', exists=' . ($dirExists ? 'yes' : 'no') . ', parent=' . $parentDir . ', parent_writable=' . (is_writable($parentDir) ? 'yes' : 'no'));
            $dirOk = $dirExists ? true : mkdir($targetDir, 0775, true);
            sc_write_log('Ensure dir result: dirOk=' . ($dirOk ? 'true' : 'false') . ', now_exists=' . (is_dir($targetDir) ? 'yes' : 'no') . ', writable=' . (is_dir($targetDir) && is_writable($targetDir) ? 'yes' : 'no'));

            // Sanitize filename
            $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $strname);
            if ($safeName === '' || $safeName === null) {
              $safeName = 'chart_' . $newChartId;
            }

            if ($dirOk) {
              $filePath = rtrim($targetDir, '/').'/'.$safeName.'.json';
              $dirWritable = is_writable($targetDir);
              $exists = file_exists($filePath);
              $fileWritable = $exists ? is_writable($filePath) : null;
              sc_write_log('Pre-write: dirWritable=' . ($dirWritable ? 'yes' : 'no') . ', exists=' . ($exists ? 'yes' : 'no') . ', fileWritable=' . ($exists ? ($fileWritable ? 'yes' : 'no') : 'n/a'));

              if ($exists && !$fileWritable) {
                $chmodOk = @chmod($filePath, 0664);
                sc_write_log('Tried chmod 0664 on existing file: ' . ($chmodOk ? 'ok' : 'fail'));
              }

              $tmpPath = $filePath . '.tmp';
              $oldUmask = umask(002);
              $bytesTmp = false;
              if ($dirWritable) {
                $bytesTmp = file_put_contents($tmpPath, $exportJson, LOCK_EX);
              }
              umask($oldUmask);

              if ($bytesTmp !== false) {
                $renamed = @rename($tmpPath, $filePath);
                if (!$renamed) {
                  sc_write_log('Rename failed, attempting copy+unlink');
                  $copied = @copy($tmpPath, $filePath);
                  @unlink($tmpPath);
                  $renamed = $copied;
                }
                if ($renamed) {
                  @chmod($filePath, 0664);
                  $fileWriteOk = true;
                  sc_write_log('Wrote ' . $bytesTmp . ' bytes (tmp) and replaced ' . $filePath);
                } else {
                  $permIssue = true;
                  $err = error_get_last();
                  sc_write_log('Failed to replace file. err=' . (isset($err['message']) ? $err['message'] : 'n/a'));
                }
              } else {
                $permIssue = true;
                $err = error_get_last();
                sc_write_log('Failed to write tmp file at ' . $tmpPath . ' err=' . (isset($err['message']) ? $err['message'] : 'n/a') . ', dirWritable=' . ($dirWritable ? 'yes' : 'no'));
              }
            }

            $stmt->close();
            $conn->close();
            $qs = 'status=created&file=' . ($fileWriteOk ? 'ok' : 'fail');
            if ($permIssue) { $qs .= '&perm=1'; }
            header('Location: /vmi/details/strapping_chart/?' . $qs);
            exit();
        } else {
            sc_write_log('DB insert ERROR: ' . $stmt->error);
            $stmt->close();
            $conn->close();
            header('Location: /vmi/details/strapping_chart/?status=error');
            exit();
        }
    
} else {
    // Handle the case where the form is not submitted via POST
    // echo "<script>alert('Post Error');</script>";
}
?>
