$(document).ready(function () {
    // Initialize DataTable without paging
    var table = $('#customers_table').DataTable({
        paging: false
    });
    // Define the allowed access levels here
    var allowedAccessLevels = [1, 4, 6, 8];
    var numericAccessLevel = Number(userAccessLevel);
    // console.log('User Access Level:', numericAccessLevel, typeof numericAccessLevel);
    // console.log('Allowed Access Levels:', allowedAccessLevels);

    // Add event listener for opening and closing details
    $('#customers_table tbody').on('click', 'td.dt-control', function () {
      var tr = $(this).closest('tr');
      var uid = tr.data('uid');
      var cs_type = tr.data('cs_type'); // Ensure this is capturing the correct uid
      var site_id = tr.data('site_id');
      var mcs_id = tr.data('mcs_id');
      var mcs_idpro = tr.data('mcs_idpro');
      var mcs_idlite = tr.data('mcs_idlite');
      var client_id = tr.data('client_id');
      // console.log("Retrieved:", uid, cs_type, site_id,"Mcs ID:", mcs_id, client_id);
      var row = table.row(tr);
      var rowIndex = row.index(); 
      if (row.child.isShown()) {
          // Row is already open
          row.child.hide();
      } else {
        row.child(format(row.data(), uid, cs_type, site_id, mcs_id, mcs_idpro, mcs_idlite, client_id, rowIndex)).show();
        tr.next().addClass('expanded-details');
    
        // Now that the child row is shown, fetch fms_number and fmsData
        var data = row.data();
        var tank_no = data[6]; // Adjust the index based on your data structure
    
       // Fetch fms_number and fmsData from the server
        $.ajax({
            url: 'dropdowns_config',
            method: 'GET',
            data: {
                uid: uid,
                tank_no: tank_no,
                case: '4' // Fetch existing data
            },
            success: function(response) {
                console.log(response);
                // Parse the response data
                const initialFmsNumber = parseInt(response.fms_number) || 0;
                const fmsData = response.fmsData || [];

                // Set the fms_number select value
                $('#fms_number-' + rowIndex).val(initialFmsNumber);

                if (initialFmsNumber > 0) {
                    // Generate FMS sections
                    generateFMSSections(rowIndex, uid, initialFmsNumber, fmsData);

                    // Populate each FMS section with existing data
                    fmsData.forEach((fms, index) => {
                        const fmsIndex = index + 1;
                        $(`#fms_port-${rowIndex}-${fmsIndex}`).val(fms.fms_port);
                        $(`#fms_type-${rowIndex}-${fmsIndex}`).val(fms.fms_type);
                        $(`#fms_id-${rowIndex}-${fmsIndex}`).val(fms.fms_id);
                    });
                } else {
                    // If fms_number is 0 or not set, ensure no FMS sections are displayed
                    $(`.fms-container-${rowIndex}`).empty();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching fms_number:', status, error);
                // Handle error appropriately
            }
        });

    }
    });     
    //GROUP FILTER ON LEFT CORNER *******************************************
$('#group_filter').change(function() {
    var selectedGroup = $(this).val();
    // console.log(selectedGroup);
    if (selectedGroup === "def") {
        // Clear the search filter to show all rows
        table.column(4).search('').draw();
        table.column(5).search('').draw();
    } else {
        $.ajax({
            url: 'updte_table',
            method: 'POST',
            data: { group_id: selectedGroup },
            success: function(response) {
                var sites = response.response; 
                
                // Construct regex search strings for both columns
                // var searchStrId = sites.map(site => site.site_id).join('|');
                const escapedList = sites.map(site =>
                    '^' + site.site_name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'
                  );
                  // Join them into a single alternation group:
                  const searchStrName = escapedList.join('|');
                
                // Apply the search filters
                table.column(4).search(searchStrName, true, false).draw();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Status Code:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                console.error('Response Headers:', xhr.getAllResponseHeaders());
            }
        });
    }
  });
  
    // Format the row details content  *************** */
        function format(data, uid, cs_type, site_id, mcs_id, mcs_idpro, mcs_idlite, client_id, rowIndex) {
           
            var sitename = data[4];
            var tank_name = data[5];
            var tank_no = data[6];
            var capacity = data[8].replace(/,/g, ''); 
            var child_details = $('<div class="child_details"></div>');
            var detailsContainer = $('<div class="minfo"></div>');
            var alert_info = $('<div class="alert_info' + rowIndex + '"></div>');
            var tanks_info = $('<div class="tank_info' + rowIndex + '" style=display:none;></div>'); // Use row index to create unique class names
            var temp_info = $('<div class="temp_info' + rowIndex + '" style=display:none;></div>');
            var menuContainer = $('<div class="menu_items"></div>'); // Create a container to hold the details
            // var nav_items = $('<nav class="nav-items" role="navigation"><button class="navigation-item1' + rowIndex + '" style="color:red;" onclick="showInfo(' + rowIndex + ')">Information</button><button class="navigation-item2' + rowIndex + '" onclick="showAlerts(' + rowIndex + ')">Alerts</button><button class="navigation-item3' + rowIndex + '" onclick="showConfig(' + rowIndex + ', \'' + uid + '\', \'' + JSON.stringify(data).replace(/'/g, "\\'") + '\')">Configuration</button></nav>');
            var nav_items = $(
                '<nav class="nav-items" role="navigation">' +
                '<button style="color:red;" class="navigation-item1' + rowIndex + '">Information</button>' +
                '<button class="navigation-item2' + rowIndex + '">Alerts</button>' +
                '<button class="navigation-item3' + rowIndex + '">Configuration</button>' +
                '<button class="navigation-item4' + rowIndex + '">Temperature</button>' +
                '</nav>'
            );
            detailsContainer.append(nav_items);
            if(cs_type == 'MCS_PRO' || cs_type == 'MCS_LITE'){
                // console.log("MCS PRO Console.\n");
                fetchChartDataMCS(uid, tank_no, cs_type, tank_name, sitename, site_id, rowIndex);
            }
            else{
                fetchChartData(uid, tank_no, rowIndex); 
            }
            $(document).on('click', '.navigation-item1' + rowIndex, function() { showInfo(rowIndex); });
            $(document).on('click', '.navigation-item2' + rowIndex, function() { showAlerts(rowIndex); });
            $(document).on('click', '.navigation-item3' + rowIndex, function() { showConfig(rowIndex, uid, tank_no); });
            $(document).on('click', '.navigation-item4' + rowIndex, function() { showTemp(rowIndex, uid, tank_no); });
            
            //Info HTML Display ***********************************************
                var infoDiv = document.createElement('div');
                infoDiv.className = 'left-info';
                const infoDivhtml = `
                    <div class="info_text">
                        <div>Company Name:<strong> ` + data[1] + `</strong></div>
                        <div id="estimatedDaysLeft-${rowIndex}">Estimated days left: <strong>N/A </strong></div>
                        ${cs_type != 'MCS_PRO' && cs_type != 'MCS_LITE' ? `<div id="last-conn-${rowIndex}">Device Date: <strong>N/A </strong></div>` : ''}
                        ${cs_type != 'MCS_PRO' && cs_type != 'MCS_LITE' ? `<div id="last-conntime-${rowIndex}">Device Time: <strong>N/A </strong></div>` : ''}
                        ${cs_type == 'MCS_PRO' ? `<div><a href="https://new.mcstsm.com/sites/${mcs_idpro}/${mcs_id}" target="_blank">More Information</a></div>` : ''}        
                        ${cs_type == 'MCS_LITE' ? `<div><a href="https://mcs-connect.com/sites/${mcs_idlite}/details/${mcs_id}/" target="_blank">More Information</a></div>` : ''}              
                    </div>
                    <div class="alert-inputs">
                        <div class="alert-upd">
                            <label>Alert Type: </label>
                            <select class="recip" type="number" id="alert_type-${rowIndex}" value="0" name="alert_type-${rowIndex}">
                                <option value = 0>None</option>
                                <option value = 1>Falling level</option>
                                <option value = 2>Raising level</option>
                            </select>
                            <label>Alert level: </label>
                            <input class="recip" type="number" id="vol_alert-${rowIndex}" value="0" name="vol_alert-${rowIndex}">
                            <label>Email: </label>
                            <input class="recip" type="email" id="email-${rowIndex}" name="email-${rowIndex}">
                        </div>
                    </div>
                    <button class="button-js" id="button_info" data-uid="${uid}" data-tank_no="${tank_no}" data-site_id="${site_id}">Update</button>
                    `;
                // console.log(site_id);
                infoDiv.innerHTML = infoDivhtml;
                detailsContainer.append(infoDiv);

                var infoChart = document.createElement('div');
                infoChart.className = 'right-info';
                const infoCharthtml = `
                    <div class="chart1" id="chart-container-${rowIndex}">
                        <canvas id="chart-${rowIndex}"></canvas>
                    </div>
                    `;
                    
                infoChart.innerHTML = infoCharthtml;
                detailsContainer.append(infoChart);

            //Alerts HTML Display********************************************************************* */
                var alerts_div = document.createElement('div');
                alerts_div.className = 'alerts_division';
                const alerts_divhtml = `
                    <div class="alerts_div">
                        <div class="grid-container alert_info${rowIndex}">
                            <div class="grid-2-columns" style="display: grid; grid-template-columns: 3fr 3.5fr 1.5fr; justify-items: start; margin-top: 0.5rem;">
                                <label class="lab1"><img src="/vmi/images/crithigh_icon.png" alt="">Critical High Alarm: </label>
                                <input class="recip" type="number" id="chigha-${rowIndex}" name="chigha" style="width:90%; margin-top: 5px;">
                                <select class="relay-select" id="relay-hh-${rowIndex}" name="relay-hh">
                                    <option value="1">Relay 1</option>
                                    <option value="2">Relay 2</option>
                                    <option value="3">Relay 3</option>
                                    <option value="4">Relay 4</option>
                                </select>
                                <label class="lab1"><img src="/vmi/images/higha_icon.png" alt="">High Alarm: </label>
                                <input class="recip" type="number" id="higha-${rowIndex}" name="higha" style="width:90%; margin-top: 5px;">
                                <select class="relay-select" id="relay-h-${rowIndex}" name="relay-h">
                                    <option value="1">Relay 1</option>
                                    <option value="2">Relay 2</option>
                                    <option value="3">Relay 3</option>
                                    <option value="4">Relay 4</option>
                                </select>
                                <label class="lab1"><img src="/vmi/images/lowa_icon.png" alt="">Low Alarm: </label>
                                <input class="recip" type="number" id="lowa-${rowIndex}" name="lowa" style="width:90%; margin-top: 5px;">
                                <select class="relay-select" id="relay-l-${rowIndex}" name="relay-l">
                                    <option value="1">Relay 1</option>
                                    <option value="2">Relay 2</option>
                                    <option value="3">Relay 3</option>
                                    <option value="4">Relay 4</option>
                                </select>
                                <label class="lab1"><img src="/vmi/images/critlow_icon.png" alt="">Critical Low Alarm: </label>
                                <input class="recip" type="number" id="clowa-${rowIndex}" name="clowa" style="width:90%; margin-top: 5px;">
                                <select class="relay-select" id="relay-ll-${rowIndex}" name="relay-ll">
                                    <option value="1">Relay 1</option>
                                    <option value="2">Relay 2</option>
                                    <option value="3">Relay 3</option>
                                    <option value="4">Relay 4</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button class="button-js3" id="button_info2" data-uid="${uid}" data-tank_no="${tank_no}" data-site_id="${site_id}" data-client_id="${client_id}"  data-capacity="${capacity}">Update</button>       
                    `;          
                    // console.log("client_id: ", client_id);
                alerts_div.innerHTML = alerts_divhtml;
                alert_info.append(alerts_div);

            //Configuration HTML display   tank CONFIGURATION   ********************************************** */
            product_select(data[7],rowIndex, 1);
                var tankgaugeDivClass = 'tankgauge-div-' + rowIndex;
                var tanksDiv = document.createElement('div');
                tanksDiv.className = 'tanks_div';
                var tankgaugeDiv = document.createElement('div');
                tankgaugeDiv.className = tankgaugeDivClass; // Unique class for each row
                const tankHtml = `
                    <div style="display: flex;">  
                        <div class="info_text" style="max-width: 20rem; max-height: 13.5rem;">
                            <div class="card pd-28px">                    
                                <div class="grid-2-columns" style="display: grid; grid-template-columns: 0.82fr 1fr;">
                                    <label style="margin-bottom:3px">Tank Number:</label>
                                    <input class="recip" style="margin-bottom:3px" type="number" value="` + tank_no + `" name="tank_number">
                                    <label style="margin-bottom:3px">Tank Name:</label>
                                    <input class="recip" style="margin-bottom:3px" type="text" value="` + tank_name + `" name="tank_name">
                                    <label style="margin-bottom:3px">Capacity:</label>
                                    <input class="recip" style="margin-bottom:3px" type="number" value="` + capacity + `" name="capacity" id="capacity-${rowIndex}">
                                    <label style="margin-bottom:3px">Select Product:</label>
                                    <select class="recip" style="margin-bottom:3px" id="product_name-${rowIndex}" name="product_name">
                                        <option value="">` + data[7] + `</option>                          
                                    </select>   
                                    <label style="margin-bottom:3px">FMS Number:</label>
                                    <select 
                                        class="recip" 
                                        style="margin-bottom:3px" 
                                        id="fms_number-${rowIndex}" 
                                        name="fms_number"
                                        data-uid="${uid}" 
                                        data-tank_no="${tank_no}"
                                    >
                                        <option value="0">No FMS</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                    </select>          
                                </div>
                            </div>
                        </div>
                        ${cs_type != 'MCS_PRO' && cs_type != 'MCS_LITE' ?`
                        <div class="right-info" >
                            <div class="tankginfo_text" id="tg-${rowIndex}" style="display:block;">
                                <div class="card pd-28px">                    
                                    <div class="grid-2-columns" style="display: grid; grid-template-columns: 0.82fr 1fr;">
                                        <label>TG Port:</label>
                                        <select class="recip" id="tg_port-${rowIndex}" name="tg_port" data-uid="${uid}"></select>
                                        <label>TG Device:</label>
                                        <select class="recip" id="tg_type-${rowIndex}" name="tg_type" disabled></select>
                                        <label style="margin-bottom: 10px">Tank Gauge ID:</label>
                                        <input class="recip" type="number" id="tg_id-${rowIndex}" value="0" name="tg_id" style="margin-bottom: 10px" disabled>
                                        <label>Strapping Chart:</label>
                                        <select class="recip" id="chart_id-${rowIndex}" name="chart_id"></select>
                                        <label style="margin-bottom: 10px">Offset:</label>
                                        <input class="recip" type="number" id="tg_offset-${rowIndex}" value="0" name="tg_offset" style="margin-bottom: 10px">
                                    </div>
                                </div>
                            </div>
                        </div>`
                        : ''}                
                    </div>
                    `;
                    // Set the inner HTML of the new div
                tanksDiv.innerHTML = tankHtml;
                tanks_info.append(tanksDiv);
                const fmsContainer = $('<div>', {
                    class: `fms-container-${rowIndex}`,
                    id: `fms-container-${rowIndex}`
                });
                const updatebtn = `<div style="margin-bottom: 4rem;">
                                        <button class="button-js2" aria-label="Update Information" id="button_info2-${rowIndex}" data-uid="${uid}" data-tank_no="${tank_no}" data-site_id="${site_id}" data-row-index="${rowIndex}">Update</button>
                                    </div>`;

                // Append the FMS container to the tanks_info section
                tanks_info.append(fmsContainer, updatebtn);
                
            //Temperature HTML display ******************************************************************* */
                var tempinfo = document.createElement('div');
                tempinfo.className = 'left-info';
                const tempinfohtml = `
                    <div class="info_text" style="max-width: 285px">
                        <div>Temperature:<strong> ` + data[11] + ` ºC</strong></div>
                        <div id="tc-vol-${rowIndex}">Temperature Corrected Vol: <strong>N/A</strong></div>
                    </div>
                    `;
                    
                    
                tempinfo.innerHTML = tempinfohtml;
                temp_info.append(tempinfo);

                var tempChart = document.createElement('div');
                tempChart.className = 'right-info';
                const tempCharthtml = `
                    <div class="chart1" id="tempchart-container-${rowIndex}">
                        <canvas id="tempchart-${rowIndex}"></canvas>
                    </div>
                    `;
                    
                tempChart.innerHTML = tempCharthtml;
                temp_info.append(tempChart);
            //**********************************************************************************************
            menuContainer.append(nav_items);

            child_details.append(menuContainer, detailsContainer, alert_info, tanks_info, temp_info);

            return child_details;
            
        }
        
        $(document).on('change', 'select[id^="fms_port-"]', function() {
            var rowIndex = this.id.split('-')[1]; // Extract the row index
            var uid = $('#fms_port-' + rowIndex).data('uid'); // Attempt to retrieve uid
            // console.log('Retrieved uid:', uid); // Check what uid is retrieved
            var selectedValue = $(this).val(); // Get the selected value
            if(selectedValue>0){
                // Perform the AJAX call
                $.ajax({
                    url: 'dropdowns_config', // Your endpoint URL
                    type: 'GET', // or 'POST', depending on your requirements
                    data: {
                        selectedValue: selectedValue,
                        case: 3,
                        uid: uid
                    },
                    success: function(response) {
                        // console.log(response);
                        if(response.newValue<200){
                            $('#fms_type-' + rowIndex).val(response.newValue); // Update the second select with the response
                        }
                        else{
                            $('#fms_type-' + rowIndex).val(0);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle errors here
                        console.error("Error: " + status + " - " + error);
                        console.error('AJAX Error:', status, error);
                        console.error('Status Code:', xhr.status);
                        console.error('Response Text:', xhr.responseText);
                        console.error('Response Headers:', xhr.getAllResponseHeaders());
                    }
                });
            }
        });
        $(document).on('change', 'select[id^="tg_port-"]', function() {
            var rowIndex = this.id.split('-')[1]; // Extract the row index
            var uid = $('#tg_port-' + rowIndex).data('uid'); // Attempt to retrieve uid
            var selectedValue = $(this).val(); // Get the selected value
        
            if (selectedValue == '1_1') {
                selectedValue = 11;
                $('#tg_id-' + rowIndex).val(1); // Update tg_id to 1
            }
            if (selectedValue == '1_2') {
                selectedValue = 12;
                $('#tg_id-' + rowIndex).val(2); // Update tg_id to 2
            }
            
            // console.log('Retrieved uid:', uid, selectedValue);
        
            // Perform the AJAX call
            if (selectedValue > 0) {
                $.ajax({
                    url: 'dropdowns_config', // Your endpoint URL
                    type: 'GET', // or 'POST', depending on your requirements
                    data: {
                        selectedValue: selectedValue,
                        case: 3,
                        uid: uid
                    },
                    success: function(response) {
                        // console.log(response);
                        if (response.newValue > 200 && response.newValue < 300) {
                            $('#tg_type-' + rowIndex).val(response.newValue); // Update the second select with the response
                        } else {
                            $('#tg_type-' + rowIndex).val(0);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle errors here
                        console.error("Error: " + status + " - " + error);
                        console.error('AJAX Error:', status, error);
                        console.error('Status Code:', xhr.status);
                        console.error('Response Text:', xhr.responseText);
                        console.error('Response Headers:', xhr.getAllResponseHeaders());
                    }
                });
            }
        });        
        $(document).on('click', '.button-js', function() {
            var uid = $(this).data('uid'); 
            var tank_no = $(this).data('tank_no'); 
            var site_id = $(this).data('site_id'); 

            if (!allowedAccessLevels.includes(numericAccessLevel)) {
                alert('Not enough privileges to perform this action');
                return false;  // Prevent further execution
            }
            // console.log(site_id);
            // Use .closest to find the nearest ancestor which is the container of all relevant inputs.
            var $detailsContainer = $(this).closest('.child_details');
        
            // Now find the inputs within this container. Assuming the inputs have specific IDs or classes that include the rowIndex.
            var vol_alert = $detailsContainer.find('input[id^="vol_alert-"]').val();
            // var phone = $detailsContainer.find('input[id^="phone-"]').val();
            var email = $detailsContainer.find('input[id^="email-"]').val();
            var alert_type = $detailsContainer.find('select[id^="alert_type-"]').val();
        
            // Construct the data object to be sent
            var data = {
                vol_alert: vol_alert,
                case: 1,
                uid,
                tank_no,
                site_id: site_id,
                alert_type: alert_type,
                email: email
            };
            // console.log(data);
            // Send the data using AJAX to update.php
            $.ajax({
                type: 'POST',
                url: 'update', // Your endpoint
                data: data,
                success: function(response) {
                    // Handle success response
                    // console.log(response);
                    alert('Update successful!');
                },
                error: function(xhr, status, error) {
                    // Handle error
                    console.error("Error: " + status + " - " + error);
                    alert('Update failed!');
                }
            });
        });
        $(document).on('click', '.button-js2', function() {
            var uid = $(this).data('uid'); 
            var tank_no = $(this).data('tank_no'); 
            var site_id = $(this).data('site_id'); 
            var rowIndex = $(this).data('row-index'); // Retrieve rowIndex here
        
            if (!allowedAccessLevels.includes(numericAccessLevel)) {
                alert('Not enough privileges to perform this action');
                return false;
            }
        
            // Use .closest to find the nearest ancestor which is the container of all relevant inputs
            var $detailsContainer = $(this).closest('.child_details');
        
            // Collect basic tank information
            var product_name = $detailsContainer.find('select[name="product_name"]').val();
            var tank_number = $detailsContainer.find('input[name="tank_number"]').val();
            var tank_name = $detailsContainer.find('input[name^="tank_name"]').val();
            var capacity = $detailsContainer.find('input[name^="capacity"]').val().replace(/,/g, '');
            var tg_port = $detailsContainer.find('select[name="tg_port"]').val();
            var tg_type = $detailsContainer.find('select[name^="tg_type"]').val();
            var tg_id = $detailsContainer.find('input[name^="tg_id"]').val();
            var tg_offset = $detailsContainer.find('input[name^="tg_offset"]').val();
            var chart_id = $detailsContainer.find('select[name="chart_id"]').val();
            var relaybox_port = $detailsContainer.find('select[name="relaybox_port"]').val();
            var relaybox_type = $detailsContainer.find('select[name="relaybox_type"]').val();
        
            // Handle special case for tg_port
            if (tg_port == '1_1') {
                tg_port = 1;
                tg_id = 1;
            }
            if (tg_port == '1_2') {
                tg_port = 1;
                tg_id = 2;
            }
        
            // Collect FMS data from all sections
            var fmsData = [];
        
            // Loop through each FMS section
            $('.fms-container-' + rowIndex).find('[id^="fms-' + rowIndex + '-"]').each(function() {
                var fmsIndex = $(this).attr('id').split('-')[2];
                var fmsEntry = {
                    fms_port: $(`#fms_port-${rowIndex}-${fmsIndex}`).val(),
                    fms_type: $(`#fms_type-${rowIndex}-${fmsIndex}`).val(),
                    fms_id: $(`#fms_id-${rowIndex}-${fmsIndex}`).val()
                };
                fmsData.push(fmsEntry);
            });
        
            // Construct the data object to be sent
            var data = {
                product_name: product_name,
                capacity: capacity,
                case: 2,
                uid: uid,
                tank_no: tank_no,
                tank_name: tank_name,
                tank_number: tank_number,
                tg_port: tg_port,
                tg_type: tg_type,
                tg_id: tg_id,
                tg_offset: tg_offset,
                chart_id: chart_id,
                site_id: site_id,
                relaybox_port: relaybox_port,
                relaybox_type: relaybox_type,
                fms_data: fmsData  // Array of FMS entries
            };
            console.log(data);
        
            // Send the data using AJAX
            $.ajax({
                type: 'POST',
                url: 'update',
                data: data,
                success: function(response) {
                    console.log(response);
                    if (response.idduplicate) {
                        alert('Error, duplicate ID');
                    }
                    else if (response.dvduplicate) {
                        alert('Error, Port is associated to a different tank!');
                    }
                    else {
                        alert('Update successful!');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error: " + status + " - " + error);
                    alert('Update failed!');
                }
            });
        });
        
        
        $(document).on('click', '.button-js3', function() {
            var uid = $(this).data('uid'); 
            var tank_no = $(this).data('tank_no'); 
            var site_id = $(this).data('site_id'); 
            var client_id = $(this).data('client_id'); 
            var capacity = $(this).data('capacity'); 

            if (!allowedAccessLevels.includes(numericAccessLevel)) {
                alert('Not enough privileges to perform this action');
                return false;  // Prevent further execution
            }
            // var capacity = $detailsContainer.find('input[name^="capacity"]').val().replace(/,/g, ''); // Remove commas
            // Use .closest to find the nearest ancestor which is the container of all relevant inputs.
            var $detailsContainer = $(this).closest('.child_details');
        
            // Now find the inputs within this container. Assuming the inputs have specific IDs or classes that include the rowIndex.
            var higha = $detailsContainer.find('input[name^="higha"]').val();
            var lowa = $detailsContainer.find('input[name^="lowa"]').val();
            var chigha = $detailsContainer.find('input[name^="chigha"]').val();
            var clowa = $detailsContainer.find('input[name^="clowa"]').val();
        
            // Capture the values of the relay selects
            var relay_hh = $detailsContainer.find('select[name="relay-hh"]').val();
            var relay_h = $detailsContainer.find('select[name="relay-h"]').val();
            var relay_l = $detailsContainer.find('select[name="relay-l"]').val();
            var relay_ll = $detailsContainer.find('select[name="relay-ll"]').val();
        
            // Construct the data object to be sent
            var data = {
                higha: higha,
                lowa: lowa,
                case: 3,
                uid,
                chigha: chigha,
                clowa: clowa,
                site_id: site_id,
                capacity: capacity,
                client_id: client_id,
                tank_no: tank_no,
                relay_hh: relay_hh, // Include the relay values
                relay_h: relay_h,
                relay_l: relay_l,
                relay_ll: relay_ll
            };
            console.log(relay_hh, relay_h, relay_l, relay_ll);
            // Send the data using AJAX to update.php
            $.ajax({
                type: 'POST',
                url: 'update', // Your endpoint
                data: data,
                success: function(response) {
                    // Handle success response
                    console.log(response);
                    alert('Update successful!');
                },
                error: function(xhr, status, error) {
                    // Handle error
                    console.error("Error: " + status + " - " + error);
                    alert('Update failed!');
                }
            });
        });        
    // ***************  */
});


//menu colors and block hide when not active *********************************** */
    function showInfo(rowIndex) {
        var alert_info = $('.alert_info' + rowIndex);
        var tank_config = $('.tank_info' + rowIndex);
        var temp_info = $('.temp_info' + rowIndex);
        alert_info.css('display', 'none');
        tank_config.css('display', 'none');
        temp_info.css('display', 'none');
        var navigation_item1 = $('.navigation-item1' + rowIndex);
        var navigation_item2 = $('.navigation-item2' + rowIndex);
        var navigation_item3 = $('.navigation-item3' + rowIndex);
        var navigation_item4 = $('.navigation-item4' + rowIndex);
        navigation_item1.css('color', 'red');
        navigation_item2.css('color', '#222222');
        navigation_item3.css('color', '#222222');
        navigation_item4.css('color', '#222222');
    }

    function showAlerts(rowIndex) {
        var alert_info = $('.alert_info' + rowIndex);
        var tank_config = $('.tank_info' + rowIndex);
        var temp_info = $('.temp_info' + rowIndex);
        var navigation_item1 = $('.navigation-item1' + rowIndex);
        var navigation_item2 = $('.navigation-item2' + rowIndex);
        var navigation_item3 = $('.navigation-item3' + rowIndex);
        var navigation_item4 = $('.navigation-item4' + rowIndex);
        alert_info.css('display', 'block');
        tank_config.css('display', 'none');
        temp_info.css('display', 'none');
        navigation_item2.css('color', 'red');
        navigation_item1.css('color', '#222222');
        navigation_item3.css('color', '#222222');
        navigation_item4.css('color', '#222222');
    }  

    function showConfig(rowIndex, uid, tank_no) {
        var alert_info = $('.alert_info' + rowIndex);
        var tank_config = $('.tank_info' + rowIndex);
        var temp_info = $('.temp_info' + rowIndex);
        var navigation_item1 = $('.navigation-item1' + rowIndex);
        var navigation_item2 = $('.navigation-item2' + rowIndex);
        var navigation_item3 = $('.navigation-item3' + rowIndex);
        var navigation_item4 = $('.navigation-item4' + rowIndex);
        alert_info.css('display', 'none');
        tank_config.css('display', 'block');
        temp_info.css('display', 'none');
        navigation_item2.css('color', '#222222');
        navigation_item1.css('color', '#222222');
        navigation_item3.css('color', 'red');
        navigation_item4.css('color', '#222222');
    }
    function showTemp(rowIndex) {
        var alert_info = $('.alert_info' + rowIndex);
        var tank_config = $('.tank_info' + rowIndex);
        var temp_info = $('.temp_info' + rowIndex);
        alert_info.css('display', 'none');
        tank_config.css('display', 'none');
        temp_info.css('display', 'flex');
        var navigation_item1 = $('.navigation-item1' + rowIndex);
        var navigation_item2 = $('.navigation-item2' + rowIndex);
        var navigation_item3 = $('.navigation-item3' + rowIndex);
        var navigation_item4 = $('.navigation-item4' + rowIndex);
        navigation_item1.css('color', '222222');
        navigation_item2.css('color', '#222222');
        navigation_item3.css('color', '#222222');
        navigation_item4.css('color', 'red');

    }

//Database feedback call *************************************************** */
    function fetchChartData(uid, tank_no, rowIndex) {
        $.ajax({
            url: 'objects',
            method: 'GET',
            data: { uid: uid, tank_no: tank_no },
            success: function(response) {
                // console.log('Success fetchChartData: ', response);
                // Assuming 'response' is the data needed for the chart
                fillinfo(response, rowIndex);
                drawChart(response.response2, rowIndex);
                drawtempChart(response.response3, rowIndex);
                
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch chart data');
                console.error('AJAX Error:', status, error);
                console.error('Status Code:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                console.error('Response Headers:', xhr.getAllResponseHeaders());
              }
        });
    }
    //Database feedback call *************************************************** */
    function fetchChartDataMCS(uid, tank_no, cs_type, tank_name, sitename, site_id, rowIndex) {
        $.ajax({
            url: 'objectsap',
            method: 'GET',
            data: { uid: uid, tank_no: tank_no, cs_type: cs_type,  tank_name: tank_name, sitename: sitename, site_id: site_id},
            success: function(response) {
                // console.log('Success fetchChartDataMCS: ', response);
                // Assuming 'response' is the data needed for the chart
                drawChart(response.response2, rowIndex);
                // drawtempChart(response.response3, rowIndex);
                fillinfo_mcs(response, rowIndex);
            },
            error: function() {
                console.error('Failed to fetch chart data');
            }
        });
    }


//Function to draw the Chart for the info tab***************************************************** */
    function drawChart(data, rowIndex) {
        // console.log(data);
        // Assuming data is the response object from your AJAX call
        let transactiondate = [];
        let average_volume = [];
        let transactiondatetc = [];
        let tcav_volume = [];
        let transactionDateDel = [];
        let deliverySum = [];

        // Assuming response.response2 contains the needed arrays
        if(data){
            if (Array.isArray(data.averageVolumeData)) {
                transactiondate = data.averageVolumeData.map(dataItem => dataItem.transaction_date);
                average_volume = data.averageVolumeData.map(dataItem => parseFloat(dataItem.average_volume));
            }
            if (Array.isArray(data.averagetcData)) {
                transactiondatetc = data.averagetcData.map(dataItem => dataItem.transaction_date);
                tcav_volume = data.averagetcData.map(dataItem => parseFloat(dataItem.tc_volume));
            }
            else {
                // console.log('deliveryData is empty or not present.');
            }
            if (Array.isArray(data.deliveryData) && data.deliveryData.length > 0) {
                transactionDateDel = data.deliveryData.map(dataItem => dataItem.transaction_datedel);
                deliverySum = data.deliveryData.map(dataItem => dataItem.delivery_sum);
            } else {
                // console.log('deliveryData is empty or not present.');
            }
        } else{
            // console.log('deliveryData is empty or not present.');
        }

        // Combine and sort dates
        const allDates = [...new Set([...transactiondate, ...transactiondatetc, ...transactionDateDel])].sort();
        const averageVolumePlaceholders = Array(allDates.length).fill(null);
        const tc_volume = Array(allDates.length).fill(null);
        const deliverySumPlaceholders = Array(allDates.length).fill(null);

        // Place data in correct positions based on the date
        transactiondate.forEach((date, index) => {
            const position = allDates.indexOf(date);
            averageVolumePlaceholders[position] = average_volume[index];
        });
        // Place data in correct positions based on the date
        transactiondatetc.forEach((date, index) => {
            const position = allDates.indexOf(date);
            tc_volume[position] = tcav_volume[index];
        });

        transactionDateDel.forEach((date, index) => {
            const position = allDates.indexOf(date);
            deliverySumPlaceholders[position] = deliverySum[index];
        });

        // Ensure the canvas for the chart is already in the DOM
        const ctx = document.getElementById(`chart-${rowIndex}`).getContext('2d');
        var chart  = new Chart(ctx, {
            type: 'line',
            data: {
                labels: allDates, // The dates for the x-axis
                datasets: [{
                    label: 'Minimum Volume',
                    data: averageVolumePlaceholders, // Data for the Minimum Volume
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }, {
                    label: 'Corrected Volume',
                    data: tc_volume, // Data for the Deliveries
                    backgroundColor: 'rgba(255, 54, 54, 0.2)',
                    borderColor: 'rgba(255, 0, 0, 1)',
                    borderWidth: 1
                }, {
                    label: 'Deliveries',
                    data: deliverySumPlaceholders, // Data for the Deliveries
                    backgroundColor: 'rgba(255, 206, 86, 0.6)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

//Function to draw the Chart for the Temperature tab***************************************************** */
    function drawtempChart(data, rowIndex) {

        if (!data || !data.averagetempData || !Array.isArray(data.averagetempData)) {
            console.error('Invalid data for drawtempChart:', data);
            return;
        }
            // Sort data by date
            const sortedData = data.averagetempData.sort((a, b) => {
                return new Date(a.transaction_date) - new Date(b.transaction_date);
            });
    
            // Map sorted data to their respective transaction dates and average temperatures
            const allDates = sortedData.map(item => item.transaction_date);
            const temperaturePlaceholders = sortedData.map(item => parseFloat(item.average_temperature));
    
            // Ensure the canvas for the chart is already in the DOM
            const ctx = document.getElementById(`tempchart-${rowIndex}`).getContext('2d');
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: allDates,
                    datasets: [{
                        label: 'Temperature',
                        data: temperaturePlaceholders,
                        backgroundColor: 'rgba(255, 54, 54, 0.2)',
                        borderColor: 'rgba(255, 0, 0, 1)',
                        borderWidth: 1
                    }]
                },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false, // It might be more visually informative to not start at zero for temperature data
                        title: {
                            display: true,
                            text: 'Temperature (°C)' // Assuming temperature is in Celsius
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date and Time'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true, // Show the legend
                        position: 'top', // Position the legend on top
                    }
                }
            }
        });
          // console.log('Temperature Data for Chart:', temperaturePlaceholders);
        
    }

//Function to fill the values from the db***************************************************** */
    function fillinfo(response, rowIndex) {
        // console.log('Tank Gauge UART:', response.tank_gauge_uart);
        // console.log('Tank Gauge Type:', response.tank_gauge_type);

        // console.log(response);
        // Directly update the email, phone, and volume alert with the response data
        document.getElementById('email-' + rowIndex).value = response.mail || '';
        // document.getElementById('phone-' + rowIndex).value = response.phone || '';
        if(response.volal){
            document.getElementById('vol_alert-' + rowIndex).value = response.volal || '';
        }
        if(response.volal_type){
            var selectElement = document.getElementById('alert_type-' + rowIndex);
            var valueToSet = response.volal_type || '';

            // Find the option that matches the value and select it
            var options = selectElement.options;
            var valueFound = false;

            for (var i = 0; i < options.length; i++) {
                if (options[i].value == valueToSet) {
                    selectElement.selectedIndex = i;
                    valueFound = true;
                    break;
                }
            }

            // Optionally handle the case where no matching value was found
            if (!valueFound) {
                console.warn('No option found with value:', valueToSet);
            }
        }
        // if(response.fms_id){
        //     document.getElementById('fms_id-' + rowIndex).value = response.fms_id || '';
        // }
        // console.log("dev", response.responsedev);
        if (response.responsedev && response.responsedev.devices) {
            response.responsedev.devices.forEach(function(device) {
                if ((device.device_id > 400 && device.device_id < 500) || response.relay_uart > 0) {
                    var cardDiv = document.querySelector('.tank_info' + rowIndex + ' .tanks_div');
                    if (cardDiv && !document.getElementById('relaybox-' + rowIndex)) {
                        var relayboxdiv = document.createElement('div');
                        relayboxdiv.innerHTML = `
                            <div class="tankginfo_text" id="relaybox-${rowIndex}" style="display:block;"> 
                                <div class="card pd-28px">                    
                                    <div class="grid-2-columns" style="display: grid; grid-template-columns: 0.82fr 1fr;">
                                        <label>Relaybox Port:</label>
                                        <select class="recip" id="relaybox_port-${rowIndex}" name="relaybox_port">
                                            <option value="0">NO DEVICE</option>
                                        </select>
                                        <label>Relaybox Device:</label>
                                        <select class="recip" id="relaybox_type-${rowIndex}" name="relaybox_type">
                                        </select>
                                    </div>
                                </div>
                            </div>`;
                        cardDiv.appendChild(relayboxdiv);
                    }
                }
            });
        }
        
        if (response.relay_uart == 0) {
            var gridContainer = document.querySelector('.alert_info' + rowIndex + ' .grid-2-columns');
            if (gridContainer) {
                gridContainer.style.gridTemplateColumns = '3fr 3.5fr 0';
            }
            $('.alert_info' + rowIndex).find('select').css('visibility', 'hidden');
        }        
        if(response.tank_gauge_id){
            document.getElementById('tg_id-' + rowIndex).value = response.tank_gauge_id || '';
        }
        if(response.tank_gauge_offset){
            document.getElementById('tg_offset-' + rowIndex).value = response.tank_gauge_offset || '';
        }
        if(response.high_alarmr){
            document.getElementById('higha-' + rowIndex).value = response.high_alarmr || '';
        }
        if(response.capacity){
            document.getElementById('capacity-' + rowIndex).value = response.capacity || '';
        }
        if(response.crithigh_alarmr){
            document.getElementById('chigha-' + rowIndex).value = response.crithigh_alarmr || '';
        }
        if(response.low_alarmr){
            document.getElementById('lowa-' + rowIndex).value = response.low_alarmr || '';
        }
        if(response.critlow_alarmr){
            document.getElementById('clowa-' + rowIndex).value = response.critlow_alarmr || '';
        }    
        if(response.estimatedDays){
            // console.log("Capacity: ", response);
            var estimatedDays = parseFloat(response.estimatedDays).toFixed(2);
            var cap = parseFloat(response.current_volume).toFixed(2);
            var remaining = parseFloat(cap/estimatedDays).toFixed(2);
            document.getElementById('estimatedDaysLeft-' + rowIndex).innerHTML = `Estimated days left: <strong>${remaining}</strong>`;
        }
        if(response.lastconn){
            // console.log("Capacity: ", response);
            var lastconn_d = response.lastconn;
            var lastconn_t = response.lastconn_time;
            document.getElementById('last-conn-' + rowIndex).innerHTML = `Device Date: <strong>${lastconn_d}</strong>`;
            document.getElementById('last-conntime-' + rowIndex).innerHTML = `Device Time: <strong>${lastconn_t}</strong>`;
        }
        if(response.tc_volume > 0){
            var tc_volume = parseFloat(response.tc_volume).toFixed(2);
            document.getElementById('tc-vol-' + rowIndex).innerHTML = `Temperature Corrected Vol: <strong>${tc_volume}L</strong>`;
        }
        if(response.tank_gauge_type){
            var selectElementtg = document.getElementById('tg_type-' + rowIndex);
            // Clear existing options
            selectElementtg.innerHTML = '';

            // Iterate over the devices and append them as options
            if (response.responsedev && response.responsedev.devices) {
                response.responsedev.devices.forEach(function(device) {
                    if((device.device_id>200 && device.device_id < 300)|| device.device_id == 0){
                        var optionElement = document.createElement('option');
                        optionElement.value = device.device_id;
                        optionElement.textContent = device.device_name; // Adjust as needed
                        if (device.device_id == response.tank_gauge_type) {
                            optionElement.selected = true;
                        }
                        selectElementtg.appendChild(optionElement);
                    }
                });
            } else {
                console.error('Invalid structure for responsedev', response.responsedev);
            }
        }
        if (response.tank_gauge_uart) {
            var selectElementtguart = document.getElementById('tg_port-' + rowIndex);
            var tg_uart = response.tank_gauge_uart;
            var tg_id = response.tank_gauge_id;
            // console.log("tguart1", tg_uart);
            // Check if tg_uart is 1 and update it
            if (tg_uart == 1) {
                tg_uart = tg_uart + '_' + tg_id;
            }
            // console.log("tguart2", tg_uart);
            // Clear existing options
            selectElementtguart.innerHTML = '';
        
            // Add options to the select element
            selectElementtguart.innerHTML += '<option value="0">NO DEVICE...</option>';
            selectElementtguart.innerHTML += '<option value="5">Port A</option>';
            selectElementtguart.innerHTML += '<option value="6">Port B</option>';
            selectElementtguart.innerHTML += '<option value="3">Port C</option>';
            selectElementtguart.innerHTML += '<option value="1_1">Port D</option>';
            selectElementtguart.innerHTML += '<option value="1_2">Port E</option>';
        
            // Iterate over options to set the selected one
            Array.from(selectElementtguart.options).forEach(function(option) {
                if (option.value == tg_uart) {
                    option.selected = true;
                }
            });
        } 
        if(response.relay_uart){
            var selectElementrelay = document.getElementById('relaybox_port-' + rowIndex);
            // console.log(selectElementrelay);
            if(selectElementrelay){
            // Clear existing options
            selectElementrelay.innerHTML = '';

            // Add options to the select element
            selectElementrelay.innerHTML += '<option value="0">NO DEVICE...</option>';
            selectElementrelay.innerHTML += '<option value="5">Port A</option>';
            selectElementrelay.innerHTML += '<option value="6">Port B</option>';
            selectElementrelay.innerHTML += '<option value="3">Port C</option>';

            // Iterate over options to set the selected one
            Array.from(selectElementrelay.options).forEach(function(option) {
                if(option.value == response.relay_uart) {
                    option.selected = true;
                }
            });
        }
        }
        if(response.relay_type){
            var selectElement2 = document.getElementById('relaybox_type-' + rowIndex);
            if(selectElement2){
                // Clear existing options
                selectElement2.innerHTML = '';

                // Iterate over the devices and append them as options
                if (response.responsedev && response.responsedev.devices) {
                    // // console.log(response.responsedev, response.relay_type);
                    response.responsedev.devices.forEach(function(device) {
                        if((device.device_id>399 && device.device_id<500) || device.device_id == 0){
                            var optionElement2 = document.createElement('option');
                            optionElement2.value = device.device_id;
                            optionElement2.textContent = device.device_name; // Adjust as needed
                            if (device.device_id == response.relay_type) {
                                optionElement2.selected = true;
                            }
                            selectElement2.appendChild(optionElement2);
                        } 
                    });
                } else {
                    console.error('Invalid structure for responsedev', response.responsedev);
                }
            }
        } 
        if (response.relay1 > 0) {
            var relay1 = response.relay1;
            $('#relay-ll-' + rowIndex + ' option[value=' + relay1 + ']').prop('selected', true);
        }
        if (response.relay2 > 0) {
            var relay2 = response.relay2;
            $('#relay-l-' + rowIndex + ' option[value=' + relay2 + ']').prop('selected', true);
        }
        if (response.relay3 > 0) {
            var relay3 = response.relay3;
            $('#relay-h-' + rowIndex + ' option[value=' + relay3 + ']').prop('selected', true);
        }
        if (response.relay4 > 0) {
            var relay4 = response.relay4;
            $('#relay-hh-' + rowIndex + ' option[value=' + relay4 + ']').prop('selected', true);
        }   
        if(response.chart_id){
            product_select(response.chart_id, rowIndex, 2);
        } 
        else{
            product_select(response.chart_id, rowIndex, 2);
        }
    }

    function fillinfo_mcs(response, rowIndex) {
        // console.log(response);
        // Directly update the email, phone, and volume alert with the response data
        document.getElementById('email-' + rowIndex).value = response.mail || '';
        // document.getElementById('phone-' + rowIndex).value = response.phone || '';
        var gridContainer = document.querySelector('.alert_info' + rowIndex + ' .grid-2-columns');
            if (gridContainer) {
                gridContainer.style.gridTemplateColumns = '3fr 3.5fr 0';
            }
        $('.alert_info' + rowIndex).find('select').css('visibility', 'hidden');
        if(response.volal){
            document.getElementById('vol_alert-' + rowIndex).value = response.volal || '';
        }

        if(response.high_alarmr){
            document.getElementById('higha-' + rowIndex).value = response.high_alarmr || '';
        }
        if(response.crithigh_alarmr){
            document.getElementById('chigha-' + rowIndex).value = response.crithigh_alarmr || '';
        }
        if(response.low_alarmr){
            document.getElementById('lowa-' + rowIndex).value = response.low_alarmr || '';
        }
        if(response.critlow_alarmr){
            document.getElementById('clowa-' + rowIndex).value = response.critlow_alarmr || '';
        }    
        if(response.estimatedDays){
            // console.log("Capacity: ", response);
            var estimatedDays = parseFloat(response.estimatedDays).toFixed(2);
            var cap = parseFloat(response.current_volume).toFixed(2);
            var remaining = parseFloat(cap/estimatedDays).toFixed(2);
            document.getElementById('estimatedDaysLeft-' + rowIndex).innerHTML = `Estimated days left: <strong>${remaining}</strong>`;
        }

    }
//***************************************************** */

function product_select(data, rowIndex, cs){
    $.ajax({
        url: 'dropdowns_config',
        method: 'GET',
        data: { rowIndex: rowIndex, case: cs},
        success: function(response) {
            // console.log('Success: ', response.schart);
            if(response.products){
                product_dd(data, response.products, rowIndex);
            }
            else if(response.schart){
                chart_dd(data, response.schart, rowIndex);
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch chart data');
            console.error('AJAX Error:', status, error);
            console.error('Status Code:', xhr.status);
            console.error('Response Text:', xhr.responseText);
            console.error('Response Headers:', xhr.getAllResponseHeaders());
        }
    });  
}

function product_dd(data, products, rowIndex){
    var selectElementtg = document.getElementById('product_name-' + rowIndex);
    // Clear existing options
    selectElementtg.innerHTML = '';
    products.forEach(function(device) {
        if(device.product_id){
            var optionElement = document.createElement('option');
            optionElement.value = device.product_id;
            optionElement.textContent = device.product_name; // Adjust as needed
            if (device.product_name == data) {
                optionElement.selected = true;
            }
            selectElementtg.appendChild(optionElement);
        }
    });
}
function chart_dd(data, products, rowIndex){
    var selectElementtg = document.getElementById('chart_id-' + rowIndex);
    // Clear existing options
    selectElementtg.innerHTML = '';
    products.forEach(function(schart) {
        if(schart.chart_id){
            var optionElement = document.createElement('option');
            optionElement.value = schart.chart_id;
            optionElement.textContent = schart.chart_name; // Adjust as needed
            if (schart.chart_id == data) {
                optionElement.selected = true;
            }
            selectElementtg.appendChild(optionElement);
        }
    });
}
$(document).on('change', 'select[id^="tg_type-"]', function() {
    var rowIndex = this.id.split('-')[1]; // Extract the row index from the ID
    var selectedType = $(this).val(); // Get the selected value of the tg_type dropdown
    $('#tg_id-' + rowIndex).prop('disabled', true);
    // Assuming you want to disable tg_id when a specific tg_type is selected
    // Replace 'your_specific_type_value' with the actual value you want to check against
    if (selectedType === '201') {
        $('#chart_id-' + rowIndex).prop('disabled', false);
    }else if (selectedType === '202') {
        $('#chart_id-' + rowIndex).prop('disabled', true);
    } else if (selectedType !== '202' || selectedType !== '201') {
        $('#chart_id-' + rowIndex).prop('disabled', false);
    }
    
});
function handleImageError(image) {
    image.src = '/vmi/images/company_nologo.png'; // Fallback image
}
document.addEventListener('DOMContentLoaded', function() {
    var tooltips = document.querySelectorAll('.tooltip');

    tooltips.forEach(function(tooltip) {
        tooltip.addEventListener('mouseover', function() {
            var tooltipText = tooltip.querySelector('.tooltiptext');
            tooltipText.style.visibility = 'visible';
            tooltipText.style.opacity = '1';
        });

        tooltip.addEventListener('mouseout', function() {
            var tooltipText = tooltip.querySelector('.tooltiptext');
            tooltipText.style.visibility = 'hidden';
            tooltipText.style.opacity = '0';
        });
    });
});
$(document).on('change', 'select[name="fms_number"]', function() {
    var selectedValue = $(this).val();
    var uid = $(this).data('uid');
    var tank_no = $(this).data('tank_no');
    var rowIndex = this.id.split('-')[1]; // Extract the row index from the ID

    // Validate the selected value
    if (selectedValue === "" || selectedValue === "0") {
        // Clear the FMS sections if no valid option is selected
        $(`.fms-container-${rowIndex}`).empty();
        return;
    }

    // Disable the select to prevent multiple requests
    $(this).prop('disabled', true);

    // Perform the AJAX call
    $.ajax({
        url: 'dropdowns_config',
        type: 'GET',
        data: {
            case: 5,
            uid: uid,
            tank_no: tank_no,
            fms_number: selectedValue
        },
        dataType: 'json', // Expecting JSON response from the server
        success: function(response) {
            console.log('AJAX Response:', response);
        
            // Re-enable the select
            $(`select#fms_number-${rowIndex}`).prop('disabled', false);
        
            // Use the selectedValue instead of response.fms_number
            const initialFmsNumber = parseInt(selectedValue) || 0;
            const fmsData = response.fmsData || [];
        
            if (initialFmsNumber > 0) {
                // Generate FMS sections based on the new selected value
                generateFMSSections(rowIndex, uid, initialFmsNumber, fmsData);
            } else {
                // If fms_number is 0 or not set, ensure no FMS sections are displayed
                $(`.fms-container-${rowIndex}`).empty();
            }
        },        
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
            alert('An error occurred while processing your request.');

            // Re-enable the select
            $(`select#fms_number-${rowIndex}`).prop('disabled', false);
        }
    });
});
function generateFMSSections(rowIndex, uid, numberOfFMS, fmsData) {
    const fmsContainer = $(`.fms-container-${rowIndex}`);
    fmsContainer.empty(); // Clear existing FMS sections

    for (let i = 1; i <= numberOfFMS; i++) {
        const fmsSection = `
            <div class="tankginfo_text" id="fms-${rowIndex}-${i}" style="display:block; margin-bottom: 10px;">
                <div class="card pd-28px">
                    <div class="grid-2-columns" style="display: grid; grid-template-columns: 0.82fr 1fr;">
                        <label>FMS Port ${i}:</label>
                        <select class="recip" style="max-width: 10rem;" id="fms_port-${rowIndex}-${i}" name="fms_port" data-uid="${uid}"></select>
                        <label>FMS Device ${i}:</label>
                        <select class="recip" style="max-width: 10rem;" id="fms_type-${rowIndex}-${i}" name="fms_type"></select>
                        <label style="margin-bottom: 10px">FMS ID ${i}:</label>
                        <input class="recip" style="max-width: 10rem;" type="number" id="fms_id-${rowIndex}-${i}" value="0" name="fms_id" style="margin-bottom: 10px">
                    </div>
                </div>
            </div>
        `;
        fmsContainer.append(fmsSection);

        // Initialize FMS ports
        populateFMSPorts(rowIndex, i);

        // Get the existing data for this FMS (if any)
        let fms = fmsData[i - 1];

        // Initialize FMS devices and set the selected value in the callback
        populateFMSDevices(rowIndex, i, function() {
            if (fms) {
                $(`#fms_type-${rowIndex}-${i}`).val(fms.fms_type);
            }
        });

        // Set the fms_port and fms_id immediately
        if (fms) {
            $(`#fms_port-${rowIndex}-${i}`).val(fms.fms_port);
            $(`#fms_id-${rowIndex}-${i}`).val(fms.fms_id);
        }
    }
}



function populateFMSPorts(rowIndex, fmsIndex) {
    const fmsPortSelect = $(`#fms_port-${rowIndex}-${fmsIndex}`);
    fmsPortSelect.empty();
    fmsPortSelect.append('<option value="0">NO DEVICE...</option>');
    fmsPortSelect.append('<option value="5">Port A</option>');
    fmsPortSelect.append('<option value="6">Port B</option>');
    fmsPortSelect.append('<option value="3">Port C</option>');
    // Add more ports as needed
}

function populateFMSDevices(rowIndex, fmsIndex, callback) {
    const fmsTypeSelect = $(`#fms_type-${rowIndex}-${fmsIndex}`);
    fmsTypeSelect.empty();

    // Fetch devices from the server
    $.ajax({
        url: 'dropdowns_config',
        type: 'GET',
        data: {
            case: 'get_fms_devices' // Define a case to get FMS devices
        },
        success: function(response) {
            const devices = response.devices || [];
            devices.forEach(function(device) {
                if (device.device_id < 200) { // Adjust condition based on your device IDs
                    var optionElement = `<option value="${device.device_id}">${device.device_name}</option>`;
                    fmsTypeSelect.append(optionElement);
                }
            });
            // Call the callback function after options are populated
            if (typeof callback === 'function') {
                callback();
            }
        },
        error: function(xhr, status, error) {
            console.error("Error fetching FMS devices:", status, error);
        }
    });
}

