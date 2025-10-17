<?php
  include('../../db/dbh2.php');
  include('../../db/log.php');

ob_start();


function sc_write_log($message){
  $logDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/vmi/logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
  $logFile = $logDir.'/strapping_chart_export.log';
  $ts = date('Y-m-d H:i:s');
  $entry = '['.$ts.'] '.$message.PHP_EOL;
  @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve the companyId and feeType values from the POST data
    $companyId = $_POST['companyId'] ?? '';
    $strname = $_POST['strname'];
    $group_id = $_POST['groupid'];
    $chart = $_POST['chart'];

    sc_write_log('Update request: chart_id=' . $group_id . ', name=' . $strname . ', points=' . $chart);
    
    // Validate chart name length (max 12 characters)
    if (strlen($strname) > 12) {
        sc_write_log('Chart name validation failed: ' . strlen($strname) . ' characters');
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=name_length');
        exit();
    }
    
    // Validate max 50 rows
    if ($chart > 51) { // chart value is rows + 1
        sc_write_log('Chart rows validation failed: ' . ($chart - 1) . ' rows');
        $conn->close();
        header('Location: /vmi/details/strapping_chart/?status=error&msg=max_rows');
        exit();
    }

    $data = array();
    for ($i = 1; $i <= $chart; $i++) {
        $level = isset($_POST['level' . $i]) ? $_POST['level' . $i] : null; // Get the 'level' value from POST, default to null if not set
        $volume = isset($_POST['volume' . $i]) ? $_POST['volume' . $i] : null; // Get the 'volume' value from POST, default to null if not set
    
        // Only add to the array if both values are provided
        if ($level !== null && $volume !== null) {
            $data[] = array('volume' => $volume, 'height' => $level);
        }
    }
    $jsonData = json_encode($data);
            
    
    // Update chart name and json data
   $sql = "UPDATE strapping_chart SET chart_name = ?, json_data = ? WHERE chart_id = ?";
   $stmt = $conn->prepare($sql);
   $stmt->bind_param("ssi", $strname, $jsonData, $group_id);

   if ($stmt->execute()) {
       sc_write_log('DB update OK for chart_id=' . $group_id);
       // After successful DB update, fetch the saved values to build the export file
       $fileWriteOk = false;
       $fetchSql = "SELECT chart_name, json_data FROM strapping_chart WHERE chart_id = ?";
       $fetchStmt = $conn->prepare($fetchSql);
       $fetchStmt->bind_param("i", $group_id);
       if ($fetchStmt->execute()) {
         $fetchStmt->bind_result($chart_name_db, $json_data_db);
         if ($fetchStmt->fetch()) {
           $rowsArray = json_decode($json_data_db, true);
           if (!is_array($rowsArray)) { $rowsArray = array(); }

           $exportPayload = array(
             'name' => $chart_name_db,
             'rows' => $rowsArray
           );

           $exportJson = json_encode($exportPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

           $targetDir = '/home/ehon/files/Charts';
           $parentDir = dirname($targetDir);
           $dirExists = is_dir($targetDir);
           sc_write_log('Target dir pre-check: ' . $targetDir . ', exists=' . ($dirExists ? 'yes' : 'no') . ', parent=' . $parentDir . ', parent_writable=' . (is_writable($parentDir) ? 'yes' : 'no'));
           $dirOk = $dirExists ? true : mkdir($targetDir, 0775, true);
           sc_write_log('Ensure dir result: dirOk=' . ($dirOk ? 'true' : 'false') . ', now_exists=' . (is_dir($targetDir) ? 'yes' : 'no') . ', writable=' . (is_dir($targetDir) && is_writable($targetDir) ? 'yes' : 'no'));

           // Sanitize filename derived from chart name
           $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $chart_name_db);
           if ($safeName === '' || $safeName === null) {
             $safeName = 'chart_' . $group_id;
           }
           if ($dirOk) {
             $filePath = rtrim($targetDir, '/').'/'.$safeName.'.json';
             $dirWritable = is_writable($targetDir);
             $exists = file_exists($filePath);
             $fileWritable = $exists ? is_writable($filePath) : null;
             sc_write_log('Pre-write: dirWritable=' . ($dirWritable ? 'yes' : 'no') . ', exists=' . ($exists ? 'yes' : 'no') . ', fileWritable=' . ($exists ? ($fileWritable ? 'yes' : 'no') : 'n/a'));

             $permIssue = false;
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
                 sc_write_log('Failed to replace existing file. err=' . (isset($err['message']) ? $err['message'] : 'n/a'));
               }
             } else {
               $permIssue = true;
               $err = error_get_last();
               sc_write_log('Failed to write tmp file at ' . $tmpPath . ' err=' . (isset($err['message']) ? $err['message'] : 'n/a') . ', dirWritable=' . ($dirWritable ? 'yes' : 'no'));
             }
           }
         }
       } else {
         sc_write_log('Fetch execute failed: ' . $fetchStmt->error);
       }
       if (isset($fetchStmt) && $fetchStmt) { $fetchStmt->close(); }
       if (isset($stmt) && $stmt) { $stmt->close(); }
       if (isset($conn) && $conn) { $conn->close(); }
       $qs = 'status=ok&file=' . ($fileWriteOk ? 'ok' : 'fail');
       if (isset($permIssue) && $permIssue) { $qs .= '&perm=1'; }
       header('Location: /vmi/details/strapping_chart/?' . $qs);
       exit();
   } else {
       sc_write_log('DB update ERROR for chart_id=' . $group_id . ' msg=' . $stmt->error);
       if (isset($stmt) && $stmt) { $stmt->close(); }
       if (isset($conn) && $conn) { $conn->close(); }
       header('Location: /vmi/details/strapping_chart/?status=error');
       exit();
   }
   
    
// } else {
//     // Handle the case where the form is not submitted via POST
//     echo "Form submission error";
// }
?>
