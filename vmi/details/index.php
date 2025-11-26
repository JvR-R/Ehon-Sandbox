<?php
  include('../db/dbh2.php');
  include('../db/log.php');
  include('../db/border.php');

  // Fetch current configuration from the database
  $stmt = $conn->prepare("SELECT email_list, group_id, start_time, finish_time, report_interval FROM report_cron WHERE client_id = ?");
  $stmt->bind_param("i", $companyId);
  $stmt->execute();
  $stmt->bind_result($emailList, $currentGroupId, $currentStartHour, $currentFinishHour, $currentInterval);
  $stmt->fetch();
  $stmt->close();
  // Try per-user row first
  $stmt_off = $conn->prepare("SELECT group_id, email_list, weekday FROM transaction_reportcron WHERE client_id = ? AND user_id = ?");
  $uidParam = isset($userId) && $userId ? (int)$userId : 0;
  $stmt_off->bind_param("ii", $companyId, $uidParam);
  $stmt_off->execute();
  $stmt_off->bind_result($off_currentGroupId, $off_emailList, $tr_weekday);
  $stmt_off->fetch();
  $stmt_off->close();
  // Fallback to client-level (NULL user) row if no per-user config
  if (!isset($off_currentGroupId) || $off_currentGroupId === null) {
    $stmt_off = $conn->prepare("SELECT group_id, email_list, weekday FROM transaction_reportcron WHERE client_id = ? AND user_id IS NULL");
    $stmt_off->bind_param("i", $companyId);
    $stmt_off->execute();
    $stmt_off->bind_result($off_currentGroupId, $off_emailList, $tr_weekday);
    $stmt_off->fetch();
    $stmt_off->close();
  }
  // Attempt to load advanced scheduling fields if columns exist
  $tr_schedule_type = 'weekly';
  $tr_month_day = null;
  $hasScheduleType = false;
  $hasMonthDay = false;
  if ($result = $conn->query("SHOW COLUMNS FROM transaction_reportcron LIKE 'schedule_type'")) {
    $hasScheduleType = ($result->num_rows > 0);
    $result->close();
  }
  if ($result = $conn->query("SHOW COLUMNS FROM transaction_reportcron LIKE 'month_day'")) {
    $hasMonthDay = ($result->num_rows > 0);
    $result->close();
  }
  if ($hasScheduleType || $hasMonthDay) {
    // Try per-user row first
    $uidParam2 = isset($userId) && $userId ? (int)$userId : 0;
    $stmt_adv = $conn->prepare("SELECT schedule_type, month_day FROM transaction_reportcron WHERE client_id = ? AND user_id = ?");
    if ($stmt_adv) {
      $stmt_adv->bind_param("ii", $companyId, $uidParam2);
      if ($stmt_adv->execute()) {
        $stmt_adv->bind_result($tmp_schedule_type, $tmp_month_day);
        if ($stmt_adv->fetch()) {
          if (!empty($tmp_schedule_type)) { $tr_schedule_type = $tmp_schedule_type; }
          if (isset($tmp_month_day)) { $tr_month_day = (int)$tmp_month_day; }
        }
      }
      $stmt_adv->close();
    }
    // Fallback to client-level (NULL user)
    if ($tr_schedule_type === 'weekly' && $tr_month_day === null) {
      $stmt_adv2 = $conn->prepare("SELECT schedule_type, month_day FROM transaction_reportcron WHERE client_id = ? AND user_id IS NULL");
      if ($stmt_adv2) {
        $stmt_adv2->bind_param("i", $companyId);
        if ($stmt_adv2->execute()) {
          $stmt_adv2->bind_result($tmp_schedule_type2, $tmp_month_day2);
          if ($stmt_adv2->fetch()) {
            if (!empty($tmp_schedule_type2)) { $tr_schedule_type = $tmp_schedule_type2; }
            if (isset($tmp_month_day2)) { $tr_month_day = (int)$tmp_month_day2; }
          }
        }
        $stmt_adv2->close();
      }
    }
  }
?>
<!DOCTYPE html><!--   -->
<html data-wf-page="65014a9e5ea5cd2c6534f24f" data-wf-site="65014a9e5ea5cd2c6534f1c8">
<head>
  <meta charset="utf-8">
  <title>Reports</title>
  <meta property="og:type" content="website">
  <meta content="summary_large_image" name="twitter:card">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
  <script src="/vmi/js/theme-init.js"></script>
  <!-- THEME CSS - MUST BE FIRST -->
  <link href="/vmi/css/theme.css" rel="stylesheet" type="text/css">
  <!-- Other CSS files -->
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="menu.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="script.js"></script>
</head>
<body>
<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="sidebar-spacer"></div>
    <div class="sidebar-spacer2"></div>
    <div class="dashboard-content">
      <div class="dashboard-main-content">
      <?php include('top_menu.php');?>
        <div class="container-default w-container" style="max-width: 920px;">
            <div class="mg-bottom-32px">
              <div class="_2-items-wrap-container">
                <div id="w-node-_4e606362-eabc-753a-260a-8d85f152b3ca-6534f24f">
                  <h1 class="display-4 mg-bottom-4px" style="color: #EC1C1C">Client configuration</h1>
                  <p class="mg-bottom-0"></p>
                </div>
              </div>
            </div>
            <div class="mg-bottom-24px" style="display: flex; justify-content: center;">
              <div class="upload-container">
                <h2>Upload Your Logo</h2>
                <input class="input" type="file" id="logoInput" accept="image/*">
                <input type="hidden" id="companyId" value="<?php echo $companyId; ?>">
                <button class="btn-primary" type="button" onclick="checkFile()">Upload</button>
              </div>
            </div>
            <div class="mg-bottom-24px">
              <div class="grid-2-columns gap-20px" style="justify-items: center; grid-template-columns: 1fr;">
                <div id="w-node-afd54bfe-8a78-7961-b11b-7a8bd9f9254d-d9f9254d" class="card pd-30px---36px">
                  <div class="mg-bottom-40px">
                    <div class="flex-horizontal">
                      <div><h2 class="display-4" style="color: White">Tank Volume Report</h2></div>                    
                    </div>                    
                  </div>
                  <?php
                    // Generate a comma-separated string of email addresses
                    $emailsString = $emailList;
                  ?>
                  <div class="_2-items-wrap-container">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Email List</div>
                    </div>
                    <textarea class="input" name="userInput" id="userInputField" style="min-width: 550px; height: 130px; font-size: 20px;"><?php echo $emailsString;  ?></textarea>
                  </div>
                  <div class="divider"></div>     
                  <div class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Group List</div>
                    </div>
                    <?php
                      $sel = "SELECT group_id, group_name FROM site_groups where client_id = ?";
                      $stmt = $conn->prepare($sel);
                      $stmt->bind_param("i", $companyId);
                      $stmt->execute();
                      $stmt->store_result();
                      echo '<select class="small-dropdown-list" id="groupList" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">';
                        echo '<option value="">Select Group</option>'; 
                        echo '<option value="def">Show All</option>';// Default option
                        if($stmt->num_rows > 0) {
                            // Bind the columns to variables
                            $stmt->bind_result($group_id,$group_name);
                            while($stmt->fetch()) {
                                echo '<option value="' . $group_id . '"' . ($group_id == $currentGroupId ? ' selected' : '') . '>' . $group_name . '</option>';
                            }
                        }
                      echo '</select>';
                    ?>  
                  </div>
                  <div class="divider"></div>    
                  <div class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Start Hour</div>
                    </div>
                    <select class="small-dropdown-list" id="startHour" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">
                      <?php
                      for ($i = 0; $i < 24; $i++) {
                          $selected = ($i == $currentStartHour) ? ' selected' : '';
                          echo "<option value=\"$i\"$selected>" . sprintf("%02d:00 %s", $i % 12 == 0 ? 12 : $i % 12, $i < 12 ? 'am' : 'pm') . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <div class="divider"></div>      
                  <div class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Finish Hour</div>
                    </div>
                    <select class="small-dropdown-list" id="finishHour" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">
                      <?php
                      for ($i = 0; $i < 24; $i++) {
                          $selected = ($i == $currentFinishHour) ? ' selected' : '';
                          echo "<option value=\"$i\"$selected>" . sprintf("%02d:00 %s", $i % 12 == 0 ? 12 : $i % 12, $i < 12 ? 'am' : 'pm') . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <div class="divider"></div> 
                  <div class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Frequency</div>
                    </div>
                    <select class="small-dropdown-list" id="interval" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">
                      <option value="1" <?php echo ($currentInterval == 1) ? 'selected' : ''; ?>>1 hour</option>
                      <option value="3" <?php echo ($currentInterval == 3) ? 'selected' : ''; ?>>3 hours</option>
                      <option value="6" <?php echo ($currentInterval == 6) ? 'selected' : ''; ?>>6 hours</option>
                      <option value="12" <?php echo ($currentInterval == 12) ? 'selected' : ''; ?>>12 hours</option>
                      <option value="24" <?php echo ($currentInterval == 24) ? 'selected' : ''; ?>>Once a Day</option>
                    </select>
                  </div>
                  <div class="divider"></div> 
                  <div class="mg-bottom-24px">
                    <div class="flex align-center" style="justify-content: center">
                      <button class="btn-primary" id="submitButton">UPDATE</button>
                    </div>
                  </div>               
                </div>
              </div>
            </div>
            <div class="mg-bottom-24px">
              <div class="grid-2-columns gap-20px" style="justify-items: center; grid-template-columns: 1fr;">
                <div class="card pd-30px---36px">
                  <div class="mg-bottom-40px">
                    <div class="flex-horizontal">
                      <div><h2 class="display-4" style="color: White">Transaction Report</h2></div>
                    </div>
                  </div>
                  <?php
                    // Generate a comma-separated string of email addresses
                    $off_emailsString = $off_emailList;
                  ?>
                  <div class="_2-items-wrap-container">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Email List</div>
                    </div>
                    <textarea
                      class="input"
                      name="off_userInput"
                      id="off_userInputField"
                      style="min-width: 550px; height: 130px; font-size: 20px;"
                    ><?php echo $off_emailsString; ?></textarea>
                  </div>
                  <div class="divider"></div>
                  <div class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Group List</div>
                    </div>
                    <?php
                      $sel = "SELECT group_id, group_name FROM site_groups WHERE client_id = ?";
                      $stmt = $conn->prepare($sel);
                      $stmt->bind_param("i", $companyId);
                      $stmt->execute();
                      $stmt->store_result();
                      
                      echo '<select class="small-dropdown-list" id="off_groupList" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">';
                        echo '<option value="">Select Group</option>'; 
                        echo '<option value="def">Show All</option>';
                        if ($stmt->num_rows > 0) {
                            $stmt->bind_result($group_id, $group_name);
                            while($stmt->fetch()) {
                                echo '<option value="' . $group_id . '"' . ($group_id == $off_currentGroupId ? ' selected' : '') . '>' . $group_name . '</option>';
                            }
                        }
                      echo '</select>';
                    ?>
                  </div>
                  <div class="divider"></div>
                  <div class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Schedule</div>
                    </div>
                    <select class="small-dropdown-list" id="off_schedule_type" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">
                      <option value="weekly" <?php echo ($tr_schedule_type === 'monthly') ? '' : 'selected'; ?>>Weekly</option>
                      <option value="monthly" <?php echo ($tr_schedule_type === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                  </div>
                  <div class="divider"></div>
                  <div id="off_weekly_wrap" class="_2-items-wrap-container" style="justify-content: space-around;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Send on (weekday)</div>
                    </div>
                    <select class="small-dropdown-list" id="off_weekday_select" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">
                      <option value="">Select day</option>
                      <option value="mon" <?php echo (isset($tr_weekday) && $tr_weekday === 'mon') ? 'selected' : ''; ?>>Monday</option>
                      <option value="tue" <?php echo (isset($tr_weekday) && $tr_weekday === 'tue') ? 'selected' : ''; ?>>Tuesday</option>
                      <option value="wed" <?php echo (isset($tr_weekday) && $tr_weekday === 'wed') ? 'selected' : ''; ?>>Wednesday</option>
                      <option value="thu" <?php echo (isset($tr_weekday) && $tr_weekday === 'thu') ? 'selected' : ''; ?>>Thursday</option>
                      <option value="fri" <?php echo (isset($tr_weekday) && $tr_weekday === 'fri') ? 'selected' : ''; ?>>Friday</option>
                      <option value="sat" <?php echo (isset($tr_weekday) && $tr_weekday === 'sat') ? 'selected' : ''; ?>>Saturday</option>
                      <option value="sun" <?php echo (isset($tr_weekday) && $tr_weekday === 'sun') ? 'selected' : ''; ?>>Sunday</option>
                    </select>
                  </div>
                  <div class="divider"></div>
                  <div id="off_monthly_wrap" class="_2-items-wrap-container" style="justify-content: space-around; display: none;">
                    <div class="flex align-center gap-column-8px">
                      <div class="small-dot"></div>
                      <div class="text-200">Send on (day of month)</div>
                    </div>
                    <select class="small-dropdown-list" id="off_month_day_select" style="color: #fff; background-color: #101935; border: 0.6px solid #343b4f; border-radius: 4px; max-width: 200px">
                      <?php
                        for ($d = 1; $d <= 31; $d++) {
                          $sel = ($tr_month_day === $d) ? ' selected' : '';
                          echo '<option value="' . $d . '"' . $sel . '>' . $d . '</option>';
                        }
                      ?>
                    </select>
                  </div>
                  <div class="divider"></div>
                  <div class="mg-bottom-24px">
                    <div class="flex align-center" style="justify-content: center">
                      <button class="btn-primary" id="off_submitButton">UPDATE</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>    
    </div>                      
  </div>
</div>
<div class="loading-bar-wrapper">
    <div class="loading-bar"></div>
</div>
  <script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=65014a9e5ea5cd2c6534f1c8" type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>

</body>
</html>
