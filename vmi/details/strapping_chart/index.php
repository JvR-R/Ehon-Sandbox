<?php
  include('../../db/dbh2.php');
  include('../../db/log.php');
  include('../../db/border.php');
?>
<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Information</title>
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Page Header Enhancement */
        .page-title {
            margin-bottom: 30px !important;
            padding: 24px 32px !important;
            background-color: var(--bg-card) !important;
            border-radius: 12px !important;
            border-bottom: 3px solid var(--accent-primary) !important;
            box-shadow: 0 2px 4px var(--shadow-sm) !important;
        }
        
        .page-title h1 {
            font-size: 32px !important;
            font-weight: 700 !important;
            color: var(--text-primary) !important;
            margin: 0 0 8px 0 !important;
            background: none !important;
            -webkit-text-fill-color: initial !important;
        }
        
        .page-title h1 i {
            color: var(--accent-primary);
            margin-right: 12px;
            font-size: 28px;
            vertical-align: middle;
        }
        
        .page-title p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Form Header Icons */
        .form-header i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        /* Form Input Icons */
        .form-input-wrapper {
            position: relative;
        }
        
        .form-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
            transition: color 0.2s ease;
        }
        
        .form-input-wrapper .form-input,
        .form-input-wrapper .form-select {
            padding-left: 48px;
        }
        
        .form-input-wrapper:focus-within i {
            color: var(--accent-primary);
        }
        
        /* Button Icons */
        .form-button i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        /* File Input Icon */
        .file-input-wrapper {
            position: relative;
        }
        
        .file-input-wrapper > i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-primary);
            font-size: 16px;
            z-index: 2;
            pointer-events: none;
        }
        
        .file-input-wrapper .file-input-label {
            padding-left: 48px;
        }
    </style>
</head>
<body>
    <?php include('../top_menu.php'); ?>
    <main class="table">
        <div class="page-title">
            <h1><i class="fas fa-chart-line"></i> Strapping Charts</h1>
            <p>Create and manage tank strapping charts for accurate volume measurements</p>
        </div>
        <div class="top-config">
            <!-- New Strapping Chart Form -->
            <div class="form-card">
                <form action="new_chart" method="post">
                    <div class="form-header"><i class="fas fa-plus-circle"></i> New Strapping Chart</div>
                    <div class="form-body">
                        <div class="form-row">
                            <label class="form-label">Number of points:</label>
                            <div class="form-field">
                                <div class="form-input-wrapper">
                                    <i class="fas fa-hashtag"></i>
                                    <input class="form-input" type="number" name="chart" id="chart" placeholder="Enter number of points (max 40)" min="1" max="40" required>
                                </div>
                                <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                                <button type="submit" class="form-button"><i class="fas fa-plus"></i> Create Chart</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- New Strapping Chart (CSV) Form -->
            <div class="form-card">
                <form action="new_chart2.php" method="post" enctype="multipart/form-data">
                    <div class="form-header"><i class="fas fa-file-csv"></i> New Strapping Chart (CSV)</div>
                    <div class="form-body">
                        <div class="form-row">
                            <label class="form-label">CSV File:</label>
                            <div class="form-field">
                                <div class="file-input-wrapper tooltip">
                                    <span class="tooltiptext">The file name will be used as the strapping chart name (max 12 chars, max 40 rows)</span>
                                    <i class="fas fa-file-upload"></i>
                                    <input type="file" name="csv_file" accept=".csv" class="file-input" required>
                                    <label class="file-input-label">Choose CSV file...</label>
                                </div>
                                <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                                <button type="submit" class="form-button"><i class="fas fa-upload"></i> Create Chart</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Edit Strapping Chart Form -->
            <div class="form-card">
                <form action="upd-chart" method="post">
                    <div class="form-header"><i class="fas fa-edit"></i> Edit Strapping Chart</div>
                    <div class="form-body">
                        <div class="form-row">
                            <label class="form-label">Chart Name:</label>
                            <div class="form-field">
                                <?php
                                    $sql_last_id = "SELECT count(distinct(chart_id)) as max_id FROM strapping_chart WHERE client_id = $companyId";
                                    $result = $conn->query($sql_last_id);
                                    if ($result->num_rows > 0) {
                                        $row = $result->fetch_assoc();
                                        $count_id = $row["max_id"];
                                    } else {
                                        $count_id = 1;
                                    }
                                    if ($companyId == 15100) {
                                        $groupname = "SELECT DISTINCT(chart_id) AS group_id, chart_name FROM strapping_chart";
                                    } else {
                                        $groupname = "SELECT DISTINCT(chart_id) AS group_id, chart_name FROM strapping_chart WHERE client_id = $companyId";
                                    }
                                    $resulttest = $conn->query($groupname);
                                    if ($resulttest->num_rows > 0) {
                                        ?>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-list"></i>
                                            <select name="upd" id="upd" class="form-select" required>
                                                <option value="">Select a chart to edit...</option>
                                                <?php
                                                while ($row = $resulttest->fetch_assoc()) {
                                                    $groupid = $row["group_id"];
                                                    $group_name = $row['chart_name'];
                                                    ?>
                                                    <option value="<?php echo $groupid; ?>"><?php echo $group_name; ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <?php
                                    }
                                ?>
                                <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                                <button type="submit" class="form-button"><i class="fas fa-save"></i> Update Chart</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Enhanced file input functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Toastr notifications from URL flags
            try {
                const params = new URLSearchParams(window.location.search);
                const status = params.get('status');
                const file = params.get('file');
                const perm = params.get('perm');
                if (status) {
                    toastr.options = {
                        closeButton: true,
                        progressBar: true,
                        positionClass: 'toast-top-right',
                        timeOut: 5000,
                        newestOnTop: true,
                        preventDuplicates: true
                    };
                    if (status === 'ok') {
                        const msg = 'Chart updated successfully' + (file === 'ok' ? ' and file saved.' : (file === 'fail' ? ', but file could not be saved.' : '.'));
                        toastr.success(msg);
                        if (file === 'fail' && perm === '1') {
                            toastr.warning('Permission issue writing to /home/ehon/files/Charts. Fix directory/file perms.');
                        }
                    } else if (status === 'error') {
                        const msg = params.get('msg');
                        const len = params.get('len');
                        const rows = params.get('rows');
                        if (msg === 'name_blank') {
                            toastr.error('Chart name cannot be blank');
                        } else if (msg === 'name_spaces') {
                            toastr.error('Chart name cannot contain spaces');
                        } else if (msg === 'name_length') {
                            const lengthMsg = len ? `Chart name can only have 12 characters maximum. Your filename is ${len} characters.` : 'Chart name can only have 12 characters maximum';
                            toastr.error(lengthMsg);
                        } else if (msg === 'max_rows') {
                            const rowsMsg = rows ? `Maximum number of rows is 40. Your CSV has ${rows} valid rows.` : 'Maximum number of rows is 40';
                            toastr.error(rowsMsg);
                        } else if (msg === 'csv_columns') {
                            toastr.error('CSV file must contain "volume" and "mm" columns');
                        } else if (msg === 'no_data') {
                            toastr.error('No valid data found in CSV file');
                        } else if (msg === 'csv_read') {
                            toastr.error('Error reading the CSV file');
                        } else if (msg === 'no_file') {
                            toastr.error('Please upload a valid CSV file');
                        } else if (msg === 'db_error') {
                            toastr.error('Database error: Unable to prepare statement');
                        } else {
                            toastr.error('There was an error updating the chart');
                        }
                    } else if (status === 'created') {
                        const msg = 'Strapping chart created' + (file === 'ok' ? ' and file saved.' : (file === 'fail' ? ', but file could not be saved.' : '.'));
                        toastr.success(msg);
                        if (file === 'fail' && perm === '1') {
                            toastr.warning('Permission issue writing to /home/ehon/files/Charts. Fix directory/file perms.');
                        }
                    }
                    // Clean URL so toast doesn't repeat on refresh
                    params.delete('status');
                    params.delete('file');
                    params.delete('perm');
                    params.delete('msg');
                    params.delete('len');
                    params.delete('rows');
                    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                    window.history.replaceState({}, '', newUrl);
                }
            } catch (e) {}

            const fileInputs = document.querySelectorAll('.file-input');
            
            fileInputs.forEach(input => {
                const label = input.nextElementSibling;
                const originalText = label.textContent;
                
                input.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name;
                        const fileNameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
                        
                        // Validate filename length (max 12 characters)
                        if (fileNameWithoutExt.length > 12) {
                            toastr.warning(`Filename "${fileNameWithoutExt}" is ${fileNameWithoutExt.length} characters. Maximum is 12 characters.`);
                        }
                        
                        // Validate filename - no spaces allowed
                        if (fileNameWithoutExt.includes(' ')) {
                            toastr.warning(`Filename "${fileNameWithoutExt}" contains spaces. Spaces are not allowed in chart names.`);
                        }
                        
                        label.textContent = fileName;
                        label.style.color = 'var(--brand-blue-600)';
                        label.style.borderColor = 'var(--brand-blue-600)';
                        label.style.backgroundColor = '#f0f9ff';
                    } else {
                        label.textContent = originalText;
                        label.style.color = '#6b7280';
                        label.style.borderColor = '#d1d5db';
                        label.style.backgroundColor = '#f9fafb';
                    }
                });
            });
            
            // Form validation enhancements
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Validate number of points (max 40)
                    const chartInput = form.querySelector('input[name="chart"]');
                    if (chartInput) {
                        const numPoints = parseInt(chartInput.value);
                        if (numPoints > 40) {
                            e.preventDefault();
                            toastr.error('Maximum number of points is 40');
                            return false;
                        }
                        if (numPoints < 1) {
                            e.preventDefault();
                            toastr.error('Number of points must be at least 1');
                            return false;
                        }
                    }

                    const submitBtn = form.querySelector('.form-button');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Processing...';
                        submitBtn.style.opacity = '0.7';
                        
                        // Re-enable after 3 seconds in case of error
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.textContent = submitBtn.textContent.replace('Processing...', 
                                submitBtn.textContent.includes('Create') ? 'Create Chart' : 'Update Chart');
                            submitBtn.style.opacity = '1';
                        }, 3000);
                    }
                });
            });
        });
    </script>
</body>
</html>
