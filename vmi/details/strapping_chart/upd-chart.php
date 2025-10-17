<?php
    include('../../db/dbh2.php');
    include('../../db/log.php');  
    include('../../db/border.php'); 
    $i=1; 
    $group_id = $_POST['upd'];
?>
<!DOCTYPE html>
<html lang="en" title="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="style.css">
    <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vmi/clients/details/menu.css">
    <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
    <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <title>EHON - Strapping Chart</title>

</head>
<body style="padding-top: 5rem;">
    <main class="table">
        <div class="back-row">
            <a href="/vmi/details/strapping_chart/" class="back-btn" aria-label="Back to Strapping Charts">← Back to Strapping Charts</a>
        </div>
        <?php
            if($companyId==15100){
                $query = "SELECT chart_name FROM strapping_chart WHERE chart_id = $group_id";
            }
            else{
                $query = "SELECT chart_name FROM strapping_chart WHERE client_id = $companyId AND chart_id = $group_id";
            }
           
            $result = $conn->query($query);

            if ($result) {
                $row = $result->fetch_assoc();
                $name = $row['chart_name'];
            }
        ?>
            <div class="page-title">
                <h1>Strapping Chart Management</h1>
            </div>
            <div class="top-config">
                <div class="table-cont">
                    <table class="full-size">
                        <tbody id="strap-rows">
                    <form action="submit_upd" method="post">
                        <tr><th colspan="4">Strapping Chart</th></tr>
                        <tr>
                            <th colspan="3">Name: </th>
                            <th colspan="1"><?php                             
                                echo '<input type="text" class="input" name="strname" id="strname" value="' . $name . '" maxlength="12" placeholder="Max 12 chars">'
                            ?></th>
                        </tr>
                        <tr><th> </th><th>Level(mm)</th><th>Volume(L)</th><th>Action</th></tr>
                             
                            <?php
                             if($companyId==15100){
                                $stmt = $conn->prepare("SELECT json_data FROM strapping_chart WHERE chart_id = ?");
                                $stmt->bind_param("i", $group_id);
                             }
                             else{
                             $stmt = $conn->prepare("SELECT json_data FROM strapping_chart WHERE client_id = ? AND chart_id = ?");
                             $stmt->bind_param("ii", $companyId, $group_id);
                             }

                             // Execute the prepared statement
                             $stmt->execute();
                             
                             // Bind the result variables
                             $stmt->bind_result($json_data);
                             echo "<script>console.log('JSON Data1: ', " . $json_data . ");</script>";
                             echo "<script>console.log('JSON Data2: ', " . $companyId . ");</script>";
                             echo "<script>console.log('JSON Data3: ', " . $group_id . ");</script>";

                             if ($stmt->fetch()) {
                                // Decode the JSON data to an array
                                $data = json_decode($json_data, true);
                                foreach ($data as $entry) {
                            ?>
                                <tr>
                                <td><?php echo $i?></td>
                                <td>
                            <?php                             
                                echo '<input type="number" class="input" name="level' . $i . '" id="level' . $i . '" value="' .  $entry['height'] . '">';
                                ?></td>                               
                                 <td><?php echo '<input type="text" class="input" name="volume' . $i . '" id="volume' . $i . '" value="' . $entry['volume'] . '">'
                                 ?></td>
                                 <td><button type="button" class="del-row">Delete</button></td>
                                <?php 
                                echo "</tr>";   
                                $i = $i + 1;
                                }
                            }

                                $stmt->close();
                                ?>  
                    </table>   
                </div>
            </div>
              
                    <div class="buttonc">
                        <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                        <input type="hidden" name="groupid" value="<?php echo $group_id; ?>">
                        <input type="hidden" name="chart" value="<?php echo  $i; ?>">
                        <button type="button" id="add-row-btn">Add Row</button>
                        <input type="submit" value="Apply Changes">
                    </form>
                    </div>                
        </main>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <script>
        (function(){
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                timeOut: 5000,
                newestOnTop: true,
                preventDuplicates: true
            };

            const tbody = document.getElementById('strap-rows');
            const addBtn = document.getElementById('add-row-btn');
            const chartInput = document.querySelector('input[name="chart"]');
            const nameInput = document.getElementById('strname');
            const form = document.querySelector('form');

            function getDataRows(){
                return Array.from(tbody.querySelectorAll('tr'))
                  .filter(tr => tr.querySelector('input[name^="level"]') && tr.querySelector('input[name^="volume"]'));
            }

            function reindexRows(){
                const rows = getDataRows();
                rows.forEach((tr, idx) => {
                    const n = idx + 1;
                    const numCell = tr.children[0];
                    if (numCell) numCell.textContent = String(n);
                    const level = tr.querySelector('input[name^="level"]');
                    const volume = tr.querySelector('input[name^="volume"]');
                    if (level){ level.name = 'level' + n; level.id = 'level' + n; }
                    if (volume){ volume.name = 'volume' + n; volume.id = 'volume' + n; }
                });
                if (chartInput) chartInput.value = String(rows.length + 1);
            }

            function createRow(levelVal = '', volumeVal = ''){
                const n = getDataRows().length + 1;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${n}</td>
                    <td><input type="number" class="input" name="level${n}" id="level${n}" value="${levelVal}"></td>
                    <td><input type="text" class="input" name="volume${n}" id="volume${n}" value="${volumeVal}"></td>
                    <td><button type="button" class="del-row">Delete</button></td>
                `;
                return tr;
            }

            if (addBtn){
                addBtn.addEventListener('click', () => {
                    const currentRows = getDataRows().length;
                    if (currentRows >= 50) {
                        toastr.error('Maximum number of rows is 50');
                        return;
                    }
                    const tr = createRow();
                    tbody.appendChild(tr);
                    reindexRows();
                });
            }

            tbody.addEventListener('click', (ev) => {
                const btn = ev.target.closest('.del-row');
                if (!btn) return;
                const tr = btn.closest('tr');
                if (tr){ tr.remove(); reindexRows(); }
            });

            // Form validation on submit
            form.addEventListener('submit', function(e) {
                // Validate chart name length (max 12 characters)
                if (nameInput.value.length > 12) {
                    e.preventDefault();
                    toastr.error('Chart name can only have 12 characters maximum');
                    return false;
                }

                if (nameInput.value.trim() === '') {
                    e.preventDefault();
                    toastr.error('Chart name is required');
                    return false;
                }

                // Validate max 50 rows
                const currentRows = getDataRows().length;
                if (currentRows > 50) {
                    e.preventDefault();
                    toastr.error('Maximum number of rows is 50');
                    return false;
                }
            });

            reindexRows();
        })();
        </script>
    </body>
    </html>
