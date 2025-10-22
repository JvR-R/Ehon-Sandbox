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
                url: 'update_table',
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

    const url = `https://ehon.com.au/api-v1/download.php?f=Charts/${encodeURI(file)}`;

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

    const url = `https://ehon.com.au/api-v1/download/${encodeURIComponent(file)}`;

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
        <div class="fms-actions-container" style="display: flex; gap: 20px;">
            <div class="fms-actions-left" style="flex: 1; display: flex; flex-direction: column;">
                <div class="fms-actions" style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <h3 style="margin-top: 0;">FMS Firmware</h3>
                    <label><strong>Firmware file:</strong></label>
                    <input type="text" id="fms_fw_file_${uid}" placeholder="fms_v2.3.bin" style="width: 100%; padding: 5px; margin: 5px 0;">
                    <button class="button-js btn-fms-fw-upgrade" data-uid="${uid}">Send</button>
                </div>
            </div>
            <div class="fms-custom-cmd-actions" style="flex: 1; background: #e8f5e9; padding: 15px; border-radius: 5px; max-height: 10rem;">
                <h3 style="margin-top: 0;">Custom Command</h3>
                <label><strong>Command:</strong></label>
                <input type="text" id="fms_custom_cmd_${uid}" placeholder="Enter command" style="width: 100%; padding: 5px; margin: 5px 0;">
                <button class="button-js btn-fms-command-custom" data-uid="${uid}">Send</button>
            </div>
        </div>
        <div class="fms-cmd-buttons">
            <button class="button-js btn-fms-command" data-uid="${uid}" data-cmmd="${'RESTART'}">Restart</button>
            <button class="button-js btn-fms-command" data-uid="${uid}" data-cmmd="${'PING'}">Ping</button>
            <button class="button-js btn-fms-command" data-uid="${uid}" data-cmmd="${'STATE'}">STATE</button>
            <button class="button-js btn-fms-command" data-uid="${uid}" data-cmmd="${'ENV'}">ENV</button>
            <button class="button-js btn-fms-command" data-uid="${uid}" data-cmmd="${'LOCK'}">Lock</button>
            <button class="button-js btn-fms-command" data-uid="${uid}" data-cmmd="${'UNLOCK'}">Unlock</button>
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
        <div class="gw-actions-container" style="display: flex; gap: 20px;">
            <div class="gw-actions-left" style="flex: 1; display: flex; flex-direction: column;">
                <div class="gw-actions" style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <h3 style="margin-top: 0;">Gateway actions</h3>
                    <label><strong>Firmware file:</strong></label>
                    <input type="text" id="fw_file_${mac}" placeholder="gw_v2.3.bin" style="width: 100%; padding: 5px; margin: 5px 0;">
                    <button class="button-js btn-fw-upgrade" data-mac="${mac}">Send</button>
                </div>
                <div class="sc-actions" style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <h3 style="margin-top: 0;">Chart actions</h3>
                    <label><strong>Chart file:</strong></label>
                    <input type="text" id="sc_file_${mac}" placeholder="sc_v2.3.json" style="width: 100%; padding: 5px; margin: 5px 0;">
                    <button class="button-js btn-SC-upgrade" data-mac="${mac}">Send</button>
                </div>
            </div>
            <div class="custom-cmd-actions" style="flex: 1; background: #e8f5e9; padding: 15px; border-radius: 5px; max-height: 10rem;">
                <h3 style="margin-top: 0;">Custom Command</h3>
                <label><strong>Command:</strong></label>
                <input type="text" id="custom_cmd_${mac}" placeholder="Enter command" style="width: 100%; padding: 5px; margin: 5px 0;">
                <button class="button-js btn-gw-command-custom" data-mac="${mac}">Send</button>
            </div>
        </div>
        <div class="cmd-buttons">
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'RESTART'}">Restart</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'PING'}">Ping</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'DIPS'}">DIPS</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'STATUS'}">STATUS</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'ENV'}">ENV</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'FORMAT'}">Format</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'ROLLBACKFW'}">ROLLBACKFW</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'UPDATEFW'}">UPDATEFW</button>
            <button class="button-js btn-gw-command" data-mac="${mac}" data-cmmd="${'LISTSD'}">LISTSD</button>
        </div>
        `;
        gatewayContainer.html(gwHtml);

        var logsHtml = `
        <div class="logs-content">
            <h3>Device Logs</h3>
            <div class="logs-header">
                <p>Last 25 log entries for device: ${mac}</p>
                <button class="button-js btn-refresh-logs" data-mac="${mac}" data-cs-type="${cs_type}">Refresh</button>
            </div>
            <div class="logs-display" id="logs_display_${mac}" style="
                background: #f5f5f5; 
                border: 1px solid #ccc; 
                padding: 10px; 
                height: 400px; 
                overflow-y: auto; 
                font-family: 'Courier New', monospace; 
                font-size: 12px;
                white-space: pre-wrap;
            " data-cs-type="${cs_type}">
                <div class="loading">Loading logs...</div>
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
     // Function to load device logs
    function loadDeviceLogs(device_id, cs_type) {
        var $logsDisplay = $('#logs_display_' + device_id);
        
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
        
        $logsDisplay.html('<div class="loading">Loading logs...</div>');
        
        $.ajax({
            url: 'get_device_logs.php',
            method: 'GET',
            data: { 
                device_id: device_id,
                device_type: device_type
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.logs) {
                    if (response.logs.length === 0) {
                        $logsDisplay.html('<div class="no-logs">No logs found for this device.</div>');
                    } else {
                        var logsText = response.logs.join('\n');
                        $logsDisplay.html(logsText);
                        // Scroll to bottom to show most recent logs
                        $logsDisplay.scrollTop($logsDisplay[0].scrollHeight);
                    }
                } else {
                    $logsDisplay.html('<div class="error">Error: ' + (response.error || 'Failed to load logs') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $logsDisplay.html('<div class="error">Error loading logs: ' + error + '</div>');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    }

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

        const url = `https://ehon.com.au/api-v1/download/${encodeURIComponent(file)}`;

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
});
