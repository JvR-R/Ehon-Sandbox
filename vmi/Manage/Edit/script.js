function showTanks() {
    const dropdown = document.getElementById('tankCountDropdown');
    const numberOfTanks = parseInt(dropdown.value);
    const container = document.getElementById('inputFieldsContainer');

    // Clear existing fields
    container.innerHTML = '';

    // Generate desired number of input fields
    for (let i = 1; i <= numberOfTanks; i++) {
        const tankDiv = document.createElement('div');
        tankDiv.classList.add('tank-div');
        tankDiv.innerHTML = `
            <div class="mg-bottom-24px" style="margin-bottom:0px; margin-top:3px;">
                <div class="card pd-28px">                    
                    <h1 class="display-4 mg-bottom-4px">Tank ${i}</h1>
                    <div class="grid-2-columns" style="display: grid; grid-template-columns: 1.82fr 1fr;">
                        <label>Tank Number:</label>
                        <input class="input tank-number" type="number" min="1" max="6" placeholder="Enter Tank Number" name="tank_number${i}">
                        <label>Tank Name:</label>
                        <input class="input" type="text" placeholder="Enter Tank Name" name="tank_name${i}">
                        <label>Select Product:</label>
                        <select class="small-dropdown-toggle" id="product_name" name="product_name${i}">
                            <option value="000">Choose a product</option>
                        </select>
                        <label for="checkbox_tg${i}">Tank Gauge?</label>
                        <input type="checkbox" id="checkbox_tg${i}" name="checkbox_tg${i}" style="transform: scale(0.7);">
                        <div class="reorder-level-capacity" id="reorder_capacity${i}" style="display: none; margin-top: 10px;">
                            <div style="display: grid; grid-template-columns: 1.82fr 1fr;">
                                <label>Capacity:</label>
                                <input class="input" type="text" placeholder="Enter Capacity" name="capacity${i}">
                            </div>
                        </div>
                        <div id="spacer${i}" style="display:none;"></div>
                        <!-- Here is where we add the "Piusi?" checkbox -->
                        <label for="checkbox_piusi${i}">Piusi?</label>
                        <input type="checkbox" id="checkbox_piusi${i}" name="checkbox_piusi${i}" style="transform: scale(0.7);">
                        <!-- The piusi div starts here, and should be outside and after the grid-2-columns div -->
                        <div class="piusi" id="piusi${i}" style="display: none; margin-top: 10px;">
                            <div style="display: grid; grid-template-columns: 1.82fr 1fr;">
                                <label>Piusi:</label>
                                <input class="input" type="text" placeholder="Enter Piusi Serial" name="piusi_serial${i}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(tankDiv);
        loadProducts();

        // Event listeners for checkboxes
        let tgCheckbox = tankDiv.querySelector(`#checkbox_tg${i}`);
        let rcDiv = tankDiv.querySelector(`#reorder_capacity${i}`);
        let spacer = tankDiv.querySelector(`#spacer${i}`);
        tgCheckbox.addEventListener('change', function() {
            rcDiv.style.display = this.checked ? 'block' : 'none';
            spacer.style.display = this.checked ? 'block' : 'none';
        });

        let piusiCheckbox = tankDiv.querySelector(`#checkbox_piusi${i}`);
        let piusiDiv = tankDiv.querySelector(`#piusi${i}`);
        piusiCheckbox.addEventListener('change', function() {
            piusiDiv.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Add event listener to the "Next" button for validation
    const nextButton = document.getElementById('nextButton');
    nextButton.addEventListener('click', validateTankNumbers);

    function validateTankNumbers() {
        const tankNumbers = Array.from(document.querySelectorAll('.tank-number')).map(input => input.value);
        const uniqueTankNumbers = new Set(tankNumbers);
        let valid = true;

        tankNumbers.forEach((number, index) => {
            const input = document.querySelector(`input[name='tank_number${index + 1}']`);
            if (number < 1 || number > 6) {
                input.setCustomValidity("Tank number must be between 1 and 6.");
                toastr.error("Tank number must be between 1 and 6.");
                valid = false;
            } else if (tankNumbers.filter(n => n === number).length > 1) {
                input.setCustomValidity("Tank number must be unique.");
                toastr.error("Tank number must be unique.");
                valid = false;
            } else {
                input.setCustomValidity("");
            }
        });

        if (!valid) {
            toastr.error("Please correct the tank number inputs. Ensure they are unique and between 1 and 6.");
        } else {
            toastr.success("All tank numbers are valid!");
            // Proceed to the next step
        }
    }

    function loadProducts() {
        // Make AJAX request to server to get products
        fetch('product_sel.php') 
            .then(response => response.json())
            .then(response => {
                if (response.status === 'success') {
                    updateProductDropdowns(response.data);
                } else {
                    console.error('Error loading products:', response.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function updateProductDropdowns(products) {
        const dropdowns = document.querySelectorAll('[id^="product_name"]');
        dropdowns.forEach(dropdown => {
            // Clear existing options except the first one
            dropdown.length = 1;
    
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                dropdown.appendChild(option);
            });
        });
    }
}





function showPumps() {
    const dropdown = document.getElementById('pumpCountDropdown');
    const numberOfPumps = parseInt(dropdown.value);
    const container = document.getElementById('inputFieldsContainer');
    
    // Clear existing fields
    container.innerHTML = '';

    // Generate desired number of input fields
    for (let i = 1; i <= numberOfPumps; i++) {
        const pumpDiv = document.createElement('div');
        pumpDiv.innerHTML = `
            <div class="mg-bottom-24px" style="margin-bottom:0px; margin-top:3px;">
                <div class="card pd-28px">                    
                    <h1 class="display-4 mg-bottom-4px">Nozzle ${i}</h1>
                    <div class="grid-2-columns _1-82fr---1fr gap-0">
                        <label style="margin-bottom: 15px">Nozzle Number:</label>
                        <input class="input" type="number" style="margin-bottom: 3px" placeholder="Enter Nozzle Number" name="nozzle_number${i}">      
                        <label style="margin-bottom: 15px">Nozzle Walk Time:</label>
                        <input class="input" type="number" style="margin-bottom: 3px" placeholder="Enter Nozzle Walktime(seconds)" name="nozzle_walktime${i}">     
                        <label style="margin-bottom: 15px">Nozzle Auth Time:</label>
                        <input class="input" type="number" style="margin-bottom: 3px" placeholder="Enter Nozzle Auth Time" name="nozzle_authtime${i}">     
                        <label style="margin-bottom: 15px">Nozzle Max Run Time:</label>
                        <input class="input" type="number" style="margin-bottom: 3px" placeholder="Enter Nozzle Number" name="nozzle_maxruntime${i}">     
                        <label style="margin-bottom: 15px">Nozzle No Flow:</label>
                        <input class="input" type="number" style="margin-bottom: 3px" placeholder="Enter Nozzle No Flow" name="nozzle_noflow${i}">    
                        <label style="margin-bottom: 15px">Nozzle Product:</label>
                        <select class="small-dropdown-toggle" id="product_name" name="nozzle_product${i}">
                            <option value="000">Choose a product</option>
                        </select>  
                        <label>Linked Tank:</label>
                            <select class="small-dropdown-toggle" name="nozzle_tank${i}">
                                <option value='000'>No Tanks</option>
                            </select>
                            <label>Pulse Rate:</label>
                            <input class="input" type="number" step="0.01" placeholder="Enter Pulse Rate" name="nozzle_pulserate${i}">                         
                    </div>
                </div>
            </div>
        `;
        const nozzleProductSelect = pumpDiv.querySelector(`select[name='nozzle_product${i}']`);
        nozzleProductSelect.addEventListener('change', function() {
            handleNozzleProductChange(i);
        });
        container.appendChild(pumpDiv);
        loadProducts();
    }
    function loadProducts() {
        // Make AJAX request to server to get products
        fetch('product_sel.php') 
            .then(response => response.json())
            .then(response => {
                if (response.status === 'success') {
                    updateProductDropdowns(response.data);
                } else {
                    console.error('Error loading products:', response.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function updateProductDropdowns(products) {
        const dropdowns = document.querySelectorAll('[id^="product_name"]');
        dropdowns.forEach(dropdown => {
            // Clear existing options except the first one
            dropdown.length = 1;
    
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                dropdown.appendChild(option);
            });
        });
    }
    
}

function handleNozzleProductChange(nozzleNumber) {
    var nozzleProductSelect = document.querySelector(`select[name='nozzle_product${nozzleNumber}']`);
    var nozzleProduct = nozzleProductSelect.value;

    // Create an AJAX request
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "nozztank_link.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function() {
        if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
            // console.log(this.responseText);
            var response = JSON.parse(this.responseText);
            // console.log(response);
            if (response.status === 'success') {
                // Populate the nozzle_tank dropdown with the options from the response
                var nozzleTankSelect = document.querySelector(`select[name='nozzle_tank${nozzleNumber}']`);
                nozzleTankSelect.innerHTML = response.options;
            } else if (response.status === 'error') {
                // console.log("Error: " + response.message);
            }
        }
    }

    xhr.send("nozzleProduct=" + nozzleProduct + "&consoleId=" + consoleId + "&siteId=" + siteId);
    // console.log(nozzleProduct, consoleId, siteId);
}

function Showcompany() {
    const dropdown = document.getElementById('dispatch_type');
    const dispatch_type = parseInt(dropdown.value);
    const container = document.getElementById('select_cont');

    if (dispatch_type != 3) {
        // Show the container and clear existing fields
        container.style.display = ''; // Reset display to default
        container.innerHTML = `
            <label>Company Name:</label>
            <select class="small-dropdown-toggle" id="client_list" name="client_list">
            <option value="0">Select Company</option>
            </select>
        `;

        // Perform AJAX request to get data from the server
        fetch('com_list.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ dispatchType: dispatch_type })
        })
        .then(response => response.json())
        .then(data => {
            // Assuming data is an array of objects with 'id' and 'name' properties
            const clientList = document.getElementById('client_list');
            data.forEach(client => {
                const option = document.createElement('option');
                option.value = client.id;
                option.textContent = client.name;
                clientList.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error fetching client data:', error);
        });
    } else {
        // Hide the container
        container.style.display = 'none';
    }
}

function customerSelected(custId, companyid) {
    if (custId != "0") {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                // Parse the JSON response
                var response = JSON.parse(this.responseText);

                // Populate the additional dropdowns
                populateDropdown('assist_number', response.data1);
                populateDropdown('driver_number', response.data2);
            }
        };
        xhttp.open("POST", "customer_tag.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("cust_id=" + custId + "client_id="+ companyid);
    }
}

function populateDropdown(dropdownId, data) {
    var dropdown = document.getElementById(dropdownId);
    dropdown.innerHTML = ""; // Clear existing options

    // Add new options
    data.forEach(function(item) {
        var option = document.createElement("option");
        option.value = item.value;
        option.text = item.text;
        dropdown.appendChild(option);
    });
}
