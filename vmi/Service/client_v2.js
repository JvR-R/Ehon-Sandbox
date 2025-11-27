$(document).ready(function () {
    $('#customers_table').DataTable({
        "paging": false,
        "rowCallback": function(row, data, index) {
        // Assuming data[2] is the Date and data[3] is the Time
        var dateText = data[2];
        var timeText = data[3];
        var rowDate = new Date(dateText + " " + timeText);
        var currentTime = new Date();
        
        if (!isNaN(rowDate.getTime())) {
            var hoursDiff = (currentTime - rowDate) / (1000 * 60 * 60);
            if (hoursDiff > 24) {
                $(row).css('background-color', '#CDDC39');
            }
        }
    }
});

    var table = $('#customers_table').DataTable();

    // Add event listener for opening and closing details
    $('#customers_table tbody').on('click', 'td.dt-control', function () {
        var tr = $(this).closest('tr');
        var uid = tr.data('uid');
        var cs_type = tr.data('cs_type');
        var site_id = tr.data('site_id');
        var client_id = tr.data('client_id');
        console.log("Retrieved:", uid, cs_type, site_id, client_id);
        var row = table.row(tr);
        var rowIndex = row.index();
        if (row.child.isShown()) {
            // Row is already open
            row.child.hide();
            tr.removeClass('shown');
        } else {
            getDbData(uid)
            .done(function(dbdata) {
                // Open this row with the data
                row.child(format(row.data(), uid, cs_type, site_id, client_id, rowIndex, dbdata)).show();
                tr.next().addClass('expanded-details');
                tr.addClass('shown');
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Error fetching row data:", textStatus, errorThrown);
            });
        }
    });

    // GROUP FILTER ON LEFT CORNER
    $('#group_filter').change(function() {
        var selectedGroup = $(this).val();
        // console.log(selectedGroup);
        if (selectedGroup === "def") {
            // Clear the search filter to show all rows
            table.column(7).search('').draw();
        } else {
            $.ajax({
                url: 'update_table.php',
                method: 'POST',
                data: { group_id: selectedGroup },
                success: function(response) {
                    var sites = response.response; 
                    
                    const escapedList = sites.map(site =>
                        '^' + site.site_name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'
                      );
                      // Join them into a single alternation group:
                      const searchStrName = escapedList.join('|');
                    // Construct regex search strings for both columns
                    // var searchStrName = sites.map(site => site.site_name).join('|');
                    
                    // Apply the search filters
                    table.column(7).search(searchStrName, true, false).draw();
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error: " + status + ": " + error);
                }
            });
        }
    });
    $(document).on("click", ".btn-SC-upgrade", function () {
    const mac  = $(this).data("mac");                 // <- here
    const file = $("#sc_file_" + mac).val()?.trim();  
    if (!file) return alert("Enter a file name");

    const url = `https://ehonenergytech.com.au/api-v1/download.php?f=Charts/${encodeURI(file)}`;

    $.ajax({
        url:  "/backend/gateway/command/",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
        device_id: mac,
        cmd:       "chart",   // top-level key will become {"firmware": url}
        value:     url           // ← just a string
        }),
        headers: { "X-CSRFToken": getCookie("csrftoken") }
    })
    .done(() => alert("Command sent"))
    .fail(xhr =>
        alert("Error: " + (xhr.responseText || xhr.statusText)));
    });

    /* Gateway Restart button */
    $(document).on("click", ".btn-gw-command", function () {
    const mac = $(this).data("mac");                 // <- here
    const cmmd = $(this).data("cmmd"); 

    $.ajax({
        url:  "/backend/gateway/command/",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
        device_id: mac,
        payload_raw: cmmd   // Send RESTART command
        }),
        headers: { "X-CSRFToken": getCookie("csrftoken") }
    })
    .done(() => alert(cmmd + " Command sent"))
    .fail(xhr =>
        alert("Error: " + (xhr.responseText || xhr.statusText)));
    });

    /* Gateway Custom Command button */
    $(document).on("click", ".btn-gw-command-custom", function () {
    const mac = $(this).data("mac");
    const cmmd = $("#custom_cmd_" + mac).val()?.trim();
    
    if (!cmmd) return alert("Enter a command");

    $.ajax({
        url:  "/backend/gateway/command/",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
        device_id: mac,
        payload_raw: cmmd   // Send custom command from input
        }),
        headers: { "X-CSRFToken": getCookie("csrftoken") }
    })
    .done(() => alert(cmmd + " Command sent"))
    .fail(xhr =>
        alert("Error: " + (xhr.responseText || xhr.statusText)));
    });


    /* helper for Django's CSRF cookie (only needed for session auth) */
    function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
    }
    /*  Service ▸ Firmware upgrade button  (client_v2.js)  */
    $(document).on("click", ".btn-fw-upgrade", function () {
    const mac  = $(this).data("mac");                 // <- here
    const file = $("#fw_file_" + mac).val()?.trim();  
    if (!file) return alert("Enter a file name");

    const url = `https://ehonenergytech.com.au/api-v1/download/${encodeURIComponent(file)}`;

    $.ajax({
        url:  "/backend/gateway/command/",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
        device_id: mac,
        cmd:       "fw",   // top-level key will become {"firmware": url}
        value:     url           // ← just a string
        }),
        headers: { "X-CSRFToken": getCookie("csrftoken") }
    })
    .done(() => alert("Command sent"))
    .fail(xhr =>
        alert("Error: " + (xhr.responseText || xhr.statusText)));
    });


    /* helper for Django’s CSRF cookie (only needed for session auth) */
    function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
    }



    // Helper function to get CS Type friendly name
    function getCSTypeName(cs_type) {
        const csTypeMap = {
            20: 'Ehon - Link',
            30: 'Ehon - Gateway',
            200: 'MCS - Lite',
            201: 'MCS - PRO'
        };
        return csTypeMap[cs_type] || cs_type; // Return the friendly name or the number if not found
    }

    // Format the row details content
    function format(data, uid, cs_type, site_id, client_id, rowIndex, dbdata) {
        var menuContainer = $('<div class="menu_items"></div>'); // Create a container to hold the details
        var child_details = $('<div class="child_details"></div>');
        var infoContainer = $('<div class="binfo"></div>');
        var detailsContainer = $('<div class="minfo" style="display:none;"></div>');
        var transactionContainer = $('<div class="tinfo" style="display:none;"></div>');
        var controlContainer = $('<div class="ctrlinfo" style="display:none;"></div>');
        var ticket_id = dbdata.ticket_id || '';
        var ticket_comment = dbdata.ticket_comment || '';
        var fw_flag = dbdata.fw_flag || '';
        var restart_flag = dbdata.restart_flag || '';
        var logs_flag = dbdata.logs_flag || '';
        var bootup = dbdata.bootup || '';
        var console_imei = dbdata.console_imei || '';
        var firmware = dbdata.firmware || '';
        var mac = dbdata.device_id || '';
        // Navigation items with rowIndex in class names
        var nav_items = $(
            '<nav class="nav-items" role="navigation">' +
            '<button class="navigation-item1' + rowIndex + '">Information</button>' +
            '<button class="navigation-item4' + rowIndex + '">Flags</button>' +
            (cs_type == 10 ? '<button class="navigation-item2' + rowIndex + '">FMS</button>' : '') +
            '<button class="navigation-item3' + rowIndex + '">Transaction</button>' +
            (cs_type == 30 ? '<button class="navigation-item5' + rowIndex + '">Gateway</button>' : '') +
            (cs_type == 30 || cs_type == 10 ? '<button class="navigation-item6' + rowIndex + '">Logs</button>' : '') +
            '</nav>'
        );

        // Append navigation items directly to menuContainer
        menuContainer.append(nav_items);

        var infoDiv = document.createElement('div');
        infoDiv.className = 'basic-info';

        // First Division: Existing Info and Lock/Unlock Buttons
        var InfoDivhtml = `
            <div class="additional-info" style="display: flex;">
                <div class="existing-info" style="margin-right: 5rem;">
                    <div class="info_text">
                        <div><p><strong>Serial:</strong> ${uid}${mac}</p></div>  
                        <div><p><strong>CS Type:</strong> ${getCSTypeName(cs_type)}</p></div>
                        <div><p><strong>Site ID:</strong> ${site_id}</p></div>          
                        <div><p><strong>Firmware:</strong> ${firmware}</p></div>      
                        <div><p><strong>IMEI:</strong> ${console_imei}</p></div>      
                        <div><p><strong>Last reboot:</strong> ${bootup}</p></div>          
                    </div>
                </div>
                <div class="info_text">
                    <label style="color:#a10303"><strong>Offline Alert</strong></label>
                    <br>
                    <div>
                        <label for="ticket_input_${uid}"><strong>Ticket ID:</strong></label>
                        <input class="input" type="text" id="ticket_input_${uid}" name="Ticket ID" value="${ticket_id}">
                    </div>
                    <div>
                        <label for="comment_input_${uid}"><strong>Additional Comment:</strong></label>
                        <textarea class="input" id="comment_input_${uid}" name="Comment" rows="4" cols="50">${ticket_comment}</textarea>
                    </div>
                    <button class="button-js btn-ticket-update" id="pulse_upd" data-uid="${uid}">Update</button>
                </div>
            </div>
        `;

        // Create a div for the existing information
        var BaseInfoDiv = document.createElement('div');
        BaseInfoDiv.className = 'info-div';
        BaseInfoDiv.innerHTML = InfoDivhtml;
        // Append both divisions to the infoDiv
        infoDiv.append(BaseInfoDiv);

        // Append the entire infoDiv to the detailsContainer
        infoContainer.append(infoDiv);


        // FMS Tab Content - Similar to Gateway Tab ***********************************************
        var fmsContainer = $('<div class="fmsinfo"></div>');
        var fmsHtml = `
        <div class="fms-tab-layout">
            <!-- Left Column: Input Sections -->
            <div class="fms-left-column">
                <div class="fms-card">
                    <div class="fms-card-header">
                        <span class="fms-card-icon">⚙️</span>
                        <h4>Firmware Update</h4>
                    </div>
                    <div class="fms-card-body">
                        <label>Firmware file:</label>
                        <div class="fms-input-group">
                            <input type="text" id="fms_fw_file_${uid}" placeholder="fms_v2.3.bin">
                            <button class="button-js btn-fms-fw-upgrade fms-btn-primary" data-uid="${uid}">Send</button>
                        </div>
                    </div>
                </div>
                <div class="fms-card">
                    <div class="fms-card-header">
                        <span class="fms-card-icon">💻</span>
                        <h4>Custom Command</h4>
                    </div>
                    <div class="fms-card-body">
                        <label>Command:</label>
                        <div class="fms-input-group">
                            <input type="text" id="fms_custom_cmd_${uid}" placeholder="Enter command">
                            <button class="button-js btn-fms-command-custom fms-btn-primary" data-uid="${uid}">Send</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Command Buttons -->
            <div class="fms-right-column">
                <div class="fms-card">
                    <div class="fms-card-header">
                        <span class="fms-card-icon">🔧</span>
                        <h4>System Commands</h4>
                    </div>
                    <div class="fms-card-body">
                        <div class="fms-btn-grid">
                            <button class="button-js btn-fms-command fms-btn-system" data-uid="${uid}" data-cmmd="RESTART">Restart</button>
                            <button class="button-js btn-fms-command fms-btn-system" data-uid="${uid}" data-cmmd="PING">Ping</button>
                            <button class="button-js btn-fms-command fms-btn-system" data-uid="${uid}" data-cmmd="STATE">State</button>
                            <button class="button-js btn-fms-command fms-btn-system" data-uid="${uid}" data-cmmd="ENV">Env</button>
                            <button class="button-js btn-fms-command fms-btn-lock" data-uid="${uid}" data-cmmd="LOCK">🔒 Lock</button>
                            <button class="button-js btn-fms-command fms-btn-unlock" data-uid="${uid}" data-cmmd="UNLOCK">🔓 Unlock</button>
                        </div>
                    </div>
                </div>
                <div class="fms-card">
                    <div class="fms-card-header">
                        <span class="fms-card-icon">📡</span>
                        <h4>Data Sync</h4>
                    </div>
                    <div class="fms-card-body">
                        <div class="fms-btn-grid">
                            <button class="button-js btn-fms-url-command fms-btn-data" data-uid="${uid}" data-cmmd="auth">Auth</button>
                            <button class="button-js btn-fms-url-command fms-btn-data" data-uid="${uid}" data-cmmd="vehicles">Vehicles</button>
                            <button class="button-js btn-fms-url-command fms-btn-data" data-uid="${uid}" data-cmmd="drivers">Drivers</button>
                            <button class="button-js btn-fms-url-command fms-btn-data" data-uid="${uid}" data-cmmd="tanks">Tanks</button>
                            <button class="button-js btn-fms-url-command fms-btn-data" data-uid="${uid}" data-cmmd="pumps">Pumps</button>
                            <button class="button-js btn-fms-config fms-btn-config" data-uid="${uid}">⚡ Config</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
        fmsContainer.html(fmsHtml);
        detailsContainer.append(fmsContainer);

        // Build contents of tinfo (Transaction tab)
        var transactionContent = document.createElement('div');
        transactionContent.className = 'transaction-content';
        const transactioncontentDivhtml = `
            <div class="info_text">
                <div>
                    <label for="pin_label_${uid}"><strong>Pin:</strong></label>
                    <input class="input" type="text" id="pin_input_${uid}" name="pin" value="">
                </div>
                <div>
                    <label for="pump_label_${uid}"><strong>Pump Number:</strong></label>
                    <input class="input" type="text" id="pump_number_input_${uid}" name="pump_number" value="">
                </div>
            </div>
            <button class="button-js btn-start-transaction" data-uid="${uid}" data-site_id="${site_id}">Start</button>
        `;

        // Create a div for the additional information
        var transactionInfoDiv = document.createElement('div');
        transactionInfoDiv.className = 'transactionInfoDiv';
        transactionInfoDiv.innerHTML = transactioncontentDivhtml;
        transactionContent.append(transactionInfoDiv);
        transactionContainer.append(transactionContent);

        // Build contents of ctrlinfo (Control tab)
        var controlContent = document.createElement('div');
        controlContent.className = 'control-content';
        const controlContentDivhtml = `
             <div class="existing-info">
                <button class="button-js btn-cfg" data-uid="${uid}">Config</button>
                <button class="button-js btn-restart" data-uid="${uid}">Restart</button>
            </div>
            <div>
                <button class="button-js btn-logs" data-uid="${uid}">Logs</button>
                <button class="button-js btn-fw" data-uid="${uid}">Firmware</button>
            </div>
        `;

        // Create a div for the additional information
        var controlInfoDiv = document.createElement('div');
        controlInfoDiv.className = 'controlInfoDiv';
        controlInfoDiv.innerHTML = controlContentDivhtml;
        controlContent.append(controlInfoDiv);
        controlContainer.append(controlContent);

        var gatewayContainer = $('<div class="gwinfo"></div>');
        var logsContainer = $('<div class="logsinfo" style="display:none;"></div>');
        var gwHtml = `
        <div class="gw-tab-layout">
            <!-- Left Column: Input Sections -->
            <div class="gw-left-column">
                <div class="gw-card">
                    <div class="gw-card-header">
                        <span class="gw-card-icon">⚙️</span>
                        <h4>Firmware Update</h4>
                    </div>
                    <div class="gw-card-body">
                        <label>Firmware file:</label>
                        <div class="gw-input-group">
                            <input type="text" id="fw_file_${mac}" placeholder="gw_v2.3.bin">
                            <button class="button-js btn-fw-upgrade gw-btn-primary" data-mac="${mac}">Send</button>
                        </div>
                    </div>
                </div>
                <div class="gw-card">
                    <div class="gw-card-header">
                        <span class="gw-card-icon">📊</span>
                        <h4>Chart Update</h4>
                    </div>
                    <div class="gw-card-body">
                        <label>Chart file:</label>
                        <div class="gw-input-group">
                            <input type="text" id="sc_file_${mac}" placeholder="sc_v2.3.json">
                            <button class="button-js btn-SC-upgrade gw-btn-primary" data-mac="${mac}">Send</button>
                        </div>
                    </div>
                </div>
                <div class="gw-card">
                    <div class="gw-card-header">
                        <span class="gw-card-icon">💻</span>
                        <h4>Custom Command</h4>
                    </div>
                    <div class="gw-card-body">
                        <label>Command:</label>
                        <div class="gw-input-group">
                            <input type="text" id="custom_cmd_${mac}" placeholder="Enter command">
                            <button class="button-js btn-gw-command-custom gw-btn-primary" data-mac="${mac}">Send</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Command Buttons -->
            <div class="gw-right-column">
                <div class="gw-card">
                    <div class="gw-card-header">
                        <span class="gw-card-icon">🔧</span>
                        <h4>System Commands</h4>
                    </div>
                    <div class="gw-card-body">
                        <div class="gw-btn-grid">
                            <button class="button-js btn-gw-command gw-btn-system" data-mac="${mac}" data-cmmd="RESTART">Restart</button>
                            <button class="button-js btn-gw-command gw-btn-system" data-mac="${mac}" data-cmmd="PING">Ping</button>
                            <button class="button-js btn-gw-command gw-btn-system" data-mac="${mac}" data-cmmd="DIPS">DIPS</button>
                            <button class="button-js btn-gw-command gw-btn-system" data-mac="${mac}" data-cmmd="STATUS">Status</button>
                            <button class="button-js btn-gw-command gw-btn-system" data-mac="${mac}" data-cmmd="ENV">Env</button>
                            <button class="button-js btn-gw-command gw-btn-system" data-mac="${mac}" data-cmmd="LISTSD">List SD</button>
                        </div>
                    </div>
                </div>
                <div class="gw-card">
                    <div class="gw-card-header">
                        <span class="gw-card-icon">🔄</span>
                        <h4>Firmware Actions</h4>
                    </div>
                    <div class="gw-card-body">
                        <div class="gw-btn-grid gw-btn-grid-3">
                            <button class="button-js btn-gw-command gw-btn-fw" data-mac="${mac}" data-cmmd="UPDATEFW">Update FW</button>
                            <button class="button-js btn-gw-command gw-btn-rollback" data-mac="${mac}" data-cmmd="ROLLBACKFW">Rollback FW</button>
                            <button class="button-js btn-gw-command gw-btn-danger" data-mac="${mac}" data-cmmd="FORMAT">⚠️ Format</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
        gatewayContainer.html(gwHtml);

        var logsHtml = `
        <div class="logs-content">
            <div class="logs-card">
                <div class="logs-card-header">
                    <div class="logs-card-header-left">
                        <div class="logs-card-icon">📋</div>
                        <div>
                            <h3>Device Logs</h3>
                            <div class="logs-device-id">${mac}</div>
                        </div>
                    </div>
                    <button class="button-js btn-refresh-logs logs-btn-refresh" data-mac="${mac}" data-cs-type="${cs_type}">
                        <span class="refresh-icon">🔄</span>
                        Refresh
                    </button>
                </div>
                <div class="logs-toolbar">
                    <div class="logs-filter-group">
                        <button class="logs-filter-btn active" data-filter="all">All</button>
                        <button class="logs-filter-btn filter-error" data-filter="error">Error</button>
                        <button class="logs-filter-btn filter-info" data-filter="info">Info</button>
                    </div>
                    <div class="logs-count">
                        Showing <span class="logs-count-badge" id="logs_count_${mac}">0</span> entries
                    </div>
                </div>
                <div class="logs-display" id="logs_display_${mac}" data-cs-type="${cs_type}">
                    <div class="logs-loading">
                        <div class="logs-loading-spinner"></div>
                        <div class="logs-loading-text">Loading logs...</div>
                    </div>
                </div>
                <div class="logs-footer">
                    <div class="logs-footer-info">
                        <div class="logs-footer-stat">📊 Last 25 entries</div>
                        <div class="logs-footer-stat" id="logs_updated_${mac}">⏱️ --</div>
                    </div>
                    <div class="logs-auto-scroll">
                        <span>Auto-scroll</span>
                        <div class="logs-auto-scroll-toggle active" id="logs_autoscroll_${mac}"></div>
                    </div>
                </div>
            </div>
        </div>
        `;
        logsContainer.html(logsHtml);

        // Append all elements to child_details
        child_details.append(menuContainer, infoContainer, fmsContainer, transactionContainer, controlContainer, gatewayContainer, logsContainer);
        return child_details;
    }

    // Event handlers using attribute selectors to match classes starting with 'navigation-item1' or 'navigation-item2'
    $(document).on('click', 'button[class^="navigation-item1"]', function () {
        var $childDetails = $(this).closest('.child_details');
        $childDetails.find('.binfo').show();
        $childDetails.find('.fmsinfo, .tinfo, .ctrlinfo, .gwinfo, .logsinfo').hide();
    });
    $(document).on('click', 'button[class^="navigation-item2"]', function () {
        var $childDetails = $(this).closest('.child_details');
        $childDetails.find('.fmsinfo').show();
        $childDetails.find('.binfo, .tinfo, .ctrlinfo, .gwinfo, .logsinfo').hide();
    });

    $(document).on('click', 'button[class^="navigation-item3"]', function () {
        var $childDetails = $(this).closest('.child_details');
        $childDetails.find('.tinfo').show();
        $childDetails.find('.binfo, .fmsinfo, .ctrlinfo, .gwinfo, .logsinfo').hide();
    });
    $(document).on('click', 'button[class^="navigation-item4"]', function () {
        var $childDetails = $(this).closest('.child_details');
        $childDetails.find('.ctrlinfo').show();
        $childDetails.find('.binfo, .fmsinfo, .tinfo, .gwinfo, .logsinfo').hide();
    });
    $(document).on('click', 'button[class^="navigation-item5"]', function () {
        var $child = $(this).closest('.child_details');
        $child.find('.binfo, .fmsinfo, .tinfo, .ctrlinfo, .logsinfo').hide();
        $child.find('.gwinfo').show();
    });

     $(document).on('click', 'button[class^="navigation-item6"]', function () {
        var $child = $(this).closest('.child_details');
        $child.find('.binfo, .fmsinfo, .tinfo, .ctrlinfo, .gwinfo').hide();
        $child.find('.logsinfo').show();
        
        // Trigger logs loading when the tab is shown
        var mac = $(this).closest('tr').data('device_id') || $(this).closest('.child_details').find('.btn-refresh-logs').data('mac');
        var cs_type = $(this).closest('tr').data('cs_type') || $(this).closest('.child_details').find('.btn-refresh-logs').data('cs-type');
        if (mac) {
            loadDeviceLogs(mac, cs_type);
        }
    });
    
    // Make the cell editable when clicked
    $(document).on('click', '.editable', function () {
        var $this = $(this);

        // Prevent multiple instances of contenteditable
        if (!$this.attr('contenteditable')) {
            $this.attr('contenteditable', 'true');
            $this.focus();
            // Store original value in case the user cancels
            $this.data('original-text', $this.text());
        }
    });

    // Detect when the user presses Enter or Escape
    $(document).on('keydown', '.editable', function (e) {
        var $this = $(this);

        if (e.key === 'Enter') {
            e.preventDefault();

            var newValue = $this.text().trim(); // Get the new value from the cell
            var column = $this.data('column');  // Which column we are editing (Phone or Email)
            var clientId = $this.closest('tr').data('client_id');  // Get the client ID from the row

            // Validate input before sending
            if (!newValue) {
                alert('Value cannot be empty.');
                $this.focus();
                return;
            }

            // Remove focus and make cell non-editable
            $this.removeAttr('contenteditable');

            // Send AJAX request to update the value in the database
            $.ajax({
                url: 'update.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    client_id: clientId,
                    column: column,
                    case: 1,
                    value: newValue
                },
                success: function (response) {
                    if (response.success) {
                        alert('Updated successfully!');
                    } else {
                        alert('Failed to update. Try again.');
                        // Revert to original value
                        $this.text($this.data('original-text'));
                    }
                },
                error: function () {
                    alert('Error while updating. Try again.');
                    // Revert to original value
                    $this.text($this.data('original-text'));
                }
            });
        } else if (e.key === 'Escape') {
            // User cancelled editing
            e.preventDefault();
            $this.removeAttr('contenteditable');
            // Revert to original value
            $this.text($this.data('original-text'));
        }
    });

    const BASE_URL = '/vmi/cs_fms/';
    const UPDATE_ESTOP_URL = BASE_URL + 'update_estop/';
    const UPDATE_SYSSTATE_URL = BASE_URL + 'system_state/';
    const UPDATE_TAGS_URL = BASE_URL + 'update_tags/';
    const UPDATE_TANKS_URL = BASE_URL + 'update_tanks/';
    const UPDATE_PUMPS_URL = BASE_URL + 'update_pumps/';
    const UPDATE_VEHICLES_URL = BASE_URL + 'update_vehicles/';
    const UPDATE_DRIVERS_URL = BASE_URL + 'update_drivers/';
    const UPDATE_PULSE_RATE_URL = BASE_URL + 'update_pulse_rate/';
    const START_TRANSACTION_URL = BASE_URL + 'start_transaction/';  
    const UPDATE_TG_URL = BASE_URL + 'update_tg/'; 

    // 1. Lock Button
    $(document).on('click', '.btn-lock', function() {
        var uid = $(this).data('uid');
        var estop = 1; // Lock action
    
        $.ajax({
            url: UPDATE_ESTOP_URL, // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, estop: estop },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    
    // 2. Unlock Button
    $(document).on('click', '.btn-unlock', function() {
        var uid = $(this).data('uid');
        var estop = 0; // Unlock action
    
        $.ajax({
            url: UPDATE_ESTOP_URL, // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, estop: estop },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    
    // 3. Tag Update Button
    $(document).on('click', '.btn-tag-update', function() {
        var uid = $(this).data('uid');
        var site_id = $(this).data('site_id');
        var client_id = $(this).data('client_id');

        $.ajax({
            url: UPDATE_TAGS_URL, // Updated URL
            method: 'POST',        // Changed to POST
            data: { uid: uid, site_id: site_id, client_id: client_id },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, refresh tags or update UI
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });

    // 4. Vehicle Update Button
    $(document).on('click', '.btn-vehicle-update', function() {
        var uid = $(this).data('uid');
        var site_id = $(this).data('site_id');
        var client_id = $(this).data('client_id');

        $.ajax({
            url: UPDATE_VEHICLES_URL, // Updated URL
            method: 'POST',            // Changed to POST
            data: { uid: uid, site_id: site_id, client_id: client_id },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, refresh vehicles or update UI
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });

    // 5. Driver Update Button
    $(document).on('click', '.btn-driver-update', function() {
        var uid = $(this).data('uid');
        var site_id = $(this).data('site_id');
        var client_id = $(this).data('client_id');

        $.ajax({
            url: UPDATE_DRIVERS_URL, // Updated URL
            method: 'POST',           // Changed to POST
            data: { uid: uid, site_id: site_id, client_id: client_id },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, refresh drivers or update UI
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });

    // 6. Pulse Update Button
    $(document).on('click', '.btn-pulse-update', function() {
        var uid = $(this).data('uid');
        var tank = $(`#tank_input_${uid}`).val();
        var pulse_rate = $(`#pulse_rate_input_${uid}`).val();

        // Validate the input values
        if (!tank || !pulse_rate) {
            alert('Both Tank and Pulse Rate fields must be filled out.');
            return;
        }

        $.ajax({
            url: UPDATE_PULSE_RATE_URL, // Updated URL
            method: 'POST',              // Changed to POST
            data: {
                uid: uid,
                tank: tank,
                pulse_rate: pulse_rate
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    // 7. Tanks Update Button
    $(document).on('click', '.btn-tanks', function() {
        var uid = $(this).data('uid');
        $.ajax({
            url: UPDATE_TANKS_URL, // Updated URL
            method: 'POST',        // Changed to POST
            data: { uid: uid },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, refresh tags or update UI
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });

     // 8. Tank Gauge Update Button
     $(document).on('click', '.btn-tg-update', function() {
        var uid = $(this).data('uid');
        $.ajax({
            url: UPDATE_TG_URL, // Updated URL
            method: 'POST',        // Changed to POST
            data: { uid: uid },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, refresh tags or update UI
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    // 9. Pumps Update Button
    $(document).on('click', '.btn-pumps', function() {
        var uid = $(this).data('uid');
        $.ajax({
            url: UPDATE_PUMPS_URL, // Updated URL
            method: 'POST',        // Changed to POST
            data: { uid: uid },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, refresh tags or update UI
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    // 10. S.State Button
    $(document).on('click', '.btn-state', function() {
        var uid = $(this).data('uid');
        var system_state = 1; // Lock action

        $.ajax({
            url: UPDATE_SYSSTATE_URL, // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, system_state: system_state },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    $(document).on('click', '.btn-fw', function() {
        var uid = $(this).data('uid');
        var flag = 'fw';

        $.ajax({
            url: 'flags_update.php', // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, flag: flag },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    $(document).on('click', '.btn-cfg', function() {
        var uid = $(this).data('uid');
        var flag = 'cfg';

        $.ajax({
            url: 'flags_update.php', // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, flag: flag },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    $(document).on('click', '.btn-restart', function() {
        var uid = $(this).data('uid');
        var flag = 'restart';

        $.ajax({
            url: 'flags_update.php', // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, flag: flag },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    $(document).on('click', '.btn-logs', function() {
        var uid = $(this).data('uid');
        var flag = 'logs';

        $.ajax({
            url: 'flags_update.php', // Updated URL
            method: 'POST', // Changed to POST
            data: { uid: uid, flag: flag },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // Optionally, update UI elements here
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
            }
        });
    });
    // Event listener for the Start button in the Transaction tab
    $(document).on('click', '.btn-start-transaction', function() {
        var uid = $(this).data('uid');
        var site_id = $(this).data('site_id');
        var pin = $(`#pin_input_${uid}`).val().trim();
        var pump_number = $(`#pump_number_input_${uid}`).val().trim();

        // Validate inputs
        if (!pin || !pump_number) {
            alert('Both Pin and Pump Number fields must be filled out.');
            return;
        }

        // Open a small popup window to monitor the transaction
        var monitorWindow = window.open(
            `/vmi/rt-test?uid=${uid}`,
            'TransactionMonitor',
            'width=800,height=600,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes'
        );

        // Check if the popup was blocked
        if (!monitorWindow) {
            alert('Popup was blocked by the browser. Please allow popups for this site.');
            return;
        }

        // Send AJAX request to server
        $.ajax({
            url: START_TRANSACTION_URL,  // Updated URL
            method: 'POST',               // Confirmed as POST
            data: {
                uid: uid,
                site_id: site_id,
                pin: pin,
                pump_number: pump_number
                // No need to include csrfmiddlewaretoken here if @csrf_exempt is used
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert('Transaction started successfully!');
                    // Optionally, you can interact with the monitorWindow here if needed
                } else {
                    alert('Error: ' + response.message);
                    // Close the popup if the transaction failed
                    if (monitorWindow && !monitorWindow.closed) {
                        monitorWindow.close();
                    }
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + (xhr.responseJSON?.message || error));
                // Close the popup in case of error
                if (monitorWindow && !monitorWindow.closed) {
                    monitorWindow.close();
                }
            }
        });
    });

    $(document).on('click', '.btn-ticket-update', function() {
        var uid = $(this).data('uid');
        var ticket = $(`#ticket_input_${uid}`).val();
        var comment = $(`#comment_input_${uid}`).val();

        $.ajax({
            url: 'update.php',
            method: 'POST',
            dataType: 'json',
            data: {
                uid: uid,
                ticket: ticket,
                comment: comment,
                case: 2
            },
            success: function (response) {
                if (response.success) {
                    alert('Updated successfully!');
                } else {
                    alert('Failed to update. Try again.');
                    // Revert to original value
                    $this.text($this.data('original-text'));
                }
            },
            error: function () {
                alert('Error while updating. Try again.');
                // Revert to original value
                $this.text($this.data('original-text'));
            }
        });
    });

    function getDbData(uid) {
        return $.ajax({
            url: 'get_row_data', // Adjust the URL to match your server endpoint
            method: 'GET',
            data: { uid: uid }
        });
    }
    // Function to parse log level from log line
    function parseLogLevel(logLine) {
        var lowerLine = logLine.toLowerCase();
        
        // Error indicators
        if (lowerLine.includes('error') || 
            lowerLine.includes('fail') || 
            lowerLine.includes('exception') ||
            lowerLine.includes('busy') ||
            lowerLine.includes('timeout') ||
            lowerLine.includes('denied') ||
            lowerLine.includes('invalid') ||
            lowerLine.includes('refused') ||
            lowerLine.includes('disconnect')) {
            return 'error';
        }
        
        // Debug indicators
        if (lowerLine.includes('debug')) {
            return 'debug';
        }
        
        // Success indicators
        if (lowerLine.includes('success') || 
            lowerLine.includes('connected') ||
            lowerLine.includes('complete') ||
            lowerLine.includes('updated')) {
            return 'success';
        }
        
        return 'info';
    }

    // Function to format a single log entry with styling
    function formatLogEntry(logLine, lineNumber) {
        var level = parseLogLevel(logLine);
        var levelClass = 'log-level-' + level;
        var levelLabel = level.toUpperCase();
        
        // Escape HTML to prevent XSS and display issues
        var message = logLine
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        
        return '<div class="log-entry" data-level="' + level + '">' +
                   '<div class="log-line-number">' + lineNumber + '</div>' +
                   '<div class="log-level ' + levelClass + '">' + levelLabel + '</div>' +
                   '<div class="log-message">' + message + '</div>' +
               '</div>';
    }

    // Function to load device logs
    function loadDeviceLogs(device_id, cs_type) {
        var $logsDisplay = $('#logs_display_' + device_id);
        var $logsCount = $('#logs_count_' + device_id);
        var $logsUpdated = $('#logs_updated_' + device_id);
        var $refreshBtn = $('.btn-refresh-logs[data-mac="' + device_id + '"]');
        
        if ($logsDisplay.length === 0) {
            console.error('Logs display element not found for device:', device_id);
            return;
        }
        
        // Determine device type based on cs_type
        var device_type = '';
        if (cs_type == 10) {
            device_type = 'fms';
        } else if (cs_type == 30) {
            device_type = 'gateway';
        }
        
        // Show loading state
        $refreshBtn.addClass('loading');
        $logsDisplay.html(`
            <div class="logs-loading">
                <div class="logs-loading-spinner"></div>
                <div class="logs-loading-text">Loading logs...</div>
            </div>
        `);
        
        $.ajax({
            url: 'get_device_logs.php',
            method: 'GET',
            data: { 
                device_id: device_id,
                device_type: device_type
            },
            dataType: 'json',
            success: function(response) {
                $refreshBtn.removeClass('loading');
                
                if (response.success && response.logs) {
                    if (response.logs.length === 0) {
                        $logsDisplay.html(`
                            <div class="logs-empty">
                                <div class="logs-empty-icon">📭</div>
                                <div class="logs-empty-text">No logs found</div>
                                <div class="logs-empty-subtext">This device hasn't generated any log entries yet.</div>
                            </div>
                        `);
                        $logsCount.text('0');
                    } else {
                        var logsHtml = '';
                        response.logs.forEach(function(log, index) {
                            logsHtml += formatLogEntry(log, index + 1);
                        });
                        $logsDisplay.html(logsHtml);
                        $logsCount.text(response.logs.length);
                        
                        // Check auto-scroll toggle
                        var $autoScroll = $('#logs_autoscroll_' + device_id);
                        if ($autoScroll.hasClass('active')) {
                            $logsDisplay.scrollTop($logsDisplay[0].scrollHeight);
                        }
                    }
                    
                    // Update timestamp
                    var now = new Date();
                    $logsUpdated.text('⏱️ Updated ' + now.toLocaleTimeString());
                } else {
                    $logsDisplay.html(`
                        <div class="logs-error">
                            <div class="logs-error-icon">⚠️</div>
                            <div class="logs-error-text">${response.error || 'Failed to load logs'}</div>
                            <button class="logs-error-retry btn-refresh-logs" data-mac="${device_id}" data-cs-type="${cs_type}">
                                Try Again
                            </button>
                        </div>
                    `);
                    $logsCount.text('0');
                }
            },
            error: function(xhr, status, error) {
                $refreshBtn.removeClass('loading');
                $logsDisplay.html(`
                    <div class="logs-error">
                        <div class="logs-error-icon">❌</div>
                        <div class="logs-error-text">Error loading logs: ${error}</div>
                        <button class="logs-error-retry btn-refresh-logs" data-mac="${device_id}" data-cs-type="${cs_type}">
                            Try Again
                        </button>
                    </div>
                `);
                $logsCount.text('0');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    }
    
    // Log filter functionality
    $(document).on('click', '.logs-filter-btn', function() {
        var $btn = $(this);
        var filter = $btn.data('filter');
        var $logsDisplay = $btn.closest('.logs-card').find('.logs-display');
        var $allBtns = $btn.closest('.logs-filter-group').find('.logs-filter-btn');
        
        // Update active state
        $allBtns.removeClass('active');
        $btn.addClass('active');
        
        // Filter log entries
        var $entries = $logsDisplay.find('.log-entry');
        if (filter === 'all') {
            $entries.show();
        } else {
            $entries.hide();
            $entries.filter('[data-level="' + filter + '"]').show();
        }
        
        // Update visible count
        var visibleCount = $entries.filter(':visible').length;
        var $countBadge = $btn.closest('.logs-card').find('.logs-count-badge');
        $countBadge.text(visibleCount);
    });
    
    // Auto-scroll toggle
    $(document).on('click', '.logs-auto-scroll-toggle', function() {
        $(this).toggleClass('active');
    });

    // Event handler for refresh logs button
    $(document).on('click', '.btn-refresh-logs', function() {
        var device_id = $(this).data('mac');
        var cs_type = $(this).data('cs-type');
        if (device_id) {
            loadDeviceLogs(device_id, cs_type);
        }
    });

    /* FMS Firmware upgrade button */
    $(document).on("click", ".btn-fms-fw-upgrade", function () {
        const uid = $(this).data("uid");
        const file = $("#fms_fw_file_" + uid).val()?.trim();
        if (!file) return alert("Enter a file name");

        const url = `https://ehonenergytech.com.au/api-v1/download/${encodeURIComponent(file)}`;

        $.ajax({
            url: "/backend/fms/command/",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                uid: uid,
                cmd: "fw",
                value: url
            }),
            headers: { "X-CSRFToken": getCookie("csrftoken") }
        })
        .done(() => alert("FMS Firmware command sent"))
        .fail(xhr =>
            alert("Error: " + (xhr.responseText || xhr.statusText)));
    });


    /* FMS Command button */
    $(document).on("click", ".btn-fms-command", function () {
        const uid = $(this).data("uid");
        const cmmd = $(this).data("cmmd");

        $.ajax({
            url: "/backend/fms/command/",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                uid: uid,
                payload_raw: cmmd
            }),
            headers: { "X-CSRFToken": getCookie("csrftoken") }
        })
        .done(() => alert(cmmd + " Command sent"))
        .fail(xhr =>
            alert("Error: " + (xhr.responseText || xhr.statusText)));
    });

    /* FMS Custom Command button */
    $(document).on("click", ".btn-fms-command-custom", function () {
        const uid = $(this).data("uid");
        const cmmd = $("#fms_custom_cmd_" + uid).val()?.trim();
        
        if (!cmmd) return alert("Enter a command");

        $.ajax({
            url: "/backend/fms/command/",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                uid: uid,
                payload_raw: cmmd
            }),
            headers: { "X-CSRFToken": getCookie("csrftoken") }
        })
        .done(() => alert(cmmd + " Command sent"))
        .fail(xhr =>
            alert("Error: " + (xhr.responseText || xhr.statusText)));
    });

    /* FMS URL Command button (Auth, Vehicles, Drivers) */
    $(document).on("click", ".btn-fms-url-command", function () {
        const uid = $(this).data("uid");
        const cmmd = $(this).data("cmmd");
        
        // Build URL based on command type
        const url = `https://ehonenergytech.com.au/api-v1/download.php?f=fms/cfg/${uid}/${cmmd.toUpperCase()}.CSV`;

        $.ajax({
            url: "/backend/fms/command/",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                uid: uid,
                cmd: cmmd,
                value: url
            }),
            headers: { "X-CSRFToken": getCookie("csrftoken") }
        })
        .done(() => alert(cmmd.toUpperCase() + " Command sent"))
        .fail(xhr =>
            alert("Error: " + (xhr.responseText || xhr.statusText)));
    });

    /* FMS Configuration Modal */
    // Create the modal HTML and append to body if it doesn't exist
    if (!$('#fms-config-modal').length) {
        const modalHtml = `
        <div id="fms-config-modal" class="fms-modal-overlay" style="display: none;">
            <div class="fms-modal-content">
                <div class="fms-modal-header">
                    <h3>FMS Configuration</h3>
                    <button class="fms-modal-close">&times;</button>
                </div>
                <div class="fms-modal-body">
                    <input type="hidden" id="fms_config_uid">
                    <div class="fms-config-field fms-checkbox-field">
                        <label class="fms-checkbox-label">
                            <input type="checkbox" id="fms_sound_enabled" value="1">
                            <span>Sound Enabled</span>
                        </label>
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_nozzle_trigger_timeout">Nozzle Trigger Timeout (ms):</label>
                        <input type="number" id="fms_nozzle_trigger_timeout" value="30000">
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_pulse_inactive_timeout">Pulse Inactive Timeout (ms):</label>
                        <input type="number" id="fms_pulse_inactive_timeout" value="30000">
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_max_pulse_duration_timeout">Max Pulse Duration Timeout (ms):</label>
                        <input type="number" id="fms_max_pulse_duration_timeout" value="900000">
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_driver_auth_timeout">Driver Auth Timeout (ms):</label>
                        <input type="number" id="fms_driver_auth_timeout" value="30000">
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_pump_selection_timeout">Pump Selection Timeout (ms):</label>
                        <input type="number" id="fms_pump_selection_timeout" value="30000">
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_tank_gauging_method">Tank Gauging Method:</label>
                        <select id="fms_tank_gauging_method">
                            <option value="MODBUS">MODBUS</option>
                            <option value="OCIO">OCIO</option>
                            <option value="NONE">NONE</option>
                        </select>
                    </div>
                    <div class="fms-config-field">
                        <label for="fms_tank_ocio_number">Tank OCIO Number:</label>
                        <input type="number" id="fms_tank_ocio_number" value="0">
                    </div>
                </div>
                <div class="fms-modal-footer">
                    <button class="button-js fms-modal-cancel">Cancel</button>
                    <button class="button-js fms-modal-save">Save Configuration</button>
                </div>
            </div>
        </div>`;
        $('body').append(modalHtml);
    }

    /* FMS Configuration button click - open modal */
    $(document).on("click", ".btn-fms-config", function () {
        const uid = $(this).data("uid");
        $('#fms_config_uid').val(uid);
        
        // Load existing configuration from database
        $.ajax({
            url: "/vmi/Service/fms_config.php",
            type: "GET",
            data: { uid: uid },
            dataType: "json"
        })
        .done(function(response) {
            if (response.success && response.data) {
                $('#fms_sound_enabled').prop('checked', response.data.sound_enabled == 1 || response.data.sound_enabled === true);
                $('#fms_nozzle_trigger_timeout').val(response.data.nozzle_trigger_timeout_ms || 30000);
                $('#fms_pulse_inactive_timeout').val(response.data.pulse_inactive_timeout_ms || 30000);
                $('#fms_max_pulse_duration_timeout').val(response.data.max_pulse_duration_timeout_ms || 900000);
                $('#fms_driver_auth_timeout').val(response.data.driver_auth_timeout_ms || 30000);
                $('#fms_pump_selection_timeout').val(response.data.pump_selection_timeout_ms || 30000);
                $('#fms_tank_gauging_method').val(response.data.tank_gauging_method || 'MODBUS');
                $('#fms_tank_ocio_number').val(response.data.tank_ocio_number || 0);
            }
        })
        .fail(function() {
            // Use default values if load fails
            console.log("No existing configuration found, using defaults");
        });
        
        $('#fms-config-modal').fadeIn(200);
    });

    /* FMS Configuration modal close */
    $(document).on("click", ".fms-modal-close, .fms-modal-cancel, .fms-modal-overlay", function (e) {
        if (e.target === this) {
            $('#fms-config-modal').fadeOut(200);
        }
    });

    /* Prevent modal content click from closing modal */
    $(document).on("click", ".fms-modal-content", function (e) {
        e.stopPropagation();
    });

    /* FMS Configuration save */
    $(document).on("click", ".fms-modal-save", function () {
        const uid = $('#fms_config_uid').val();
        const configData = {
            uid: uid,
            sound_enabled: $('#fms_sound_enabled').is(':checked') ? 1 : 0,
            nozzle_trigger_timeout_ms: parseInt($('#fms_nozzle_trigger_timeout').val()) || 30000,
            pulse_inactive_timeout_ms: parseInt($('#fms_pulse_inactive_timeout').val()) || 30000,
            max_pulse_duration_timeout_ms: parseInt($('#fms_max_pulse_duration_timeout').val()) || 900000,
            driver_auth_timeout_ms: parseInt($('#fms_driver_auth_timeout').val()) || 30000,
            pump_selection_timeout_ms: parseInt($('#fms_pump_selection_timeout').val()) || 30000,
            tank_gauging_method: $('#fms_tank_gauging_method').val() || 'MODBUS',
            tank_ocio_number: parseInt($('#fms_tank_ocio_number').val()) || 0
        };

        $.ajax({
            url: "/vmi/Service/fms_config.php",
            type: "POST",
            data: configData,
            dataType: "json"
        })
        .done(function(response) {
            // Only proceed if both database save AND file creation succeeded
            if (response.success) {
                // Sync the configuration file after successful save and file creation
                const syncUrl = `https://ehonenergytech.com.au/api-v1/download.php?f=fms/cfg/${uid}/CONFIG.CSV`;
                
                $.ajax({
                    url: "/backend/fms/command/",
                    type: "POST",
                    contentType: "application/json",
                    data: JSON.stringify({
                        uid: uid,
                        cmd: "config",
                        value: syncUrl
                    }),
                    headers: { "X-CSRFToken": getCookie("csrftoken") }
                })
                .done(() => {
                    alert("Configuration saved and synced successfully!");
                    $('#fms-config-modal').fadeOut(200);
                })
                .fail(function(xhr) {
                    alert("Configuration saved but sync failed: " + (xhr.responseText || xhr.statusText));
                    $('#fms-config-modal').fadeOut(200);
                });
            } else {
                alert("Error saving configuration: " + (response.error || "Unknown error"));
            }
        })
        .fail(function(xhr) {
            alert("Error saving configuration: " + (xhr.responseText || xhr.statusText));
        });
    });
});
