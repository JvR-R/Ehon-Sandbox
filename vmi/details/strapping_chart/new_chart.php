<?php
  include('../../db/dbh2.php');
  include('../../db/log.php');
  include('../../db/border.php');
$chart = $_POST['chart'];
$i=1;   
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
            <title>Company Information</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

        </head>
        <body>
        <main class="table">
            <div class="top-config">
                <div class="table-cont" style = "position: relative; right: 0%;">
                    <table class="full-size">
                        <tbody>
                        <form action="submit" method="post">
                        <tr><th colspan="3">Strapping Chart</th></tr>
                        <tr>
                            <th colspan="2">Name: </th>
                            <th colspan="1"><?php                             
                                echo '<input  class="input"type="text" name="strname" id="strname" maxlength="12" placeholder="Max 12 chars">'?>
                            </th>
                        </tr>
                        <tr><th> </th><th>Level(mm)</th><th>Volume(L)</th></tr>
                        
                            <?php
                    
                            while($i<=$chart)
                            {
                            ?>
                                <tr>
                                <td><?php echo $i?></td>
                                <td>
                            <?php                             
                                echo '<input class="input" type="number" step="any" name="level' . $i . '" id="level' . $i . '">';
                                ?></td>                               
                                <td><?php echo '<input class="input" type="number" step="any" name="volume' . $i . '" id="volume' . $i . '">'?></td>
                                <?php 
                                echo "</tr>";   
                                $i = $i + 1;
                                }
                                ?>  
                    </table>   
                </div>
            </div>             
                    <div class="buttonc">
                        <input type="hidden" name="companyId" value="<?php echo htmlspecialchars($companyId); ?>">
                        <input type="hidden" name="chart" value="<?php echo $chart; ?>">
                        <input type="submit" value="Create Chart"
                            style="font-weight: bold; font-size: 24px; color:white; background-color: #002F60;border-radius: 4px;cursor: pointer;padding: 5px 10px;border: none;">
                        </form>
                    </div>                
        </main>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: 5000,
                    newestOnTop: true,
                    preventDuplicates: true
                };

                const form = document.querySelector('form');
                const nameInput = document.getElementById('strname');

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
                });
            });
        </script>
    </body>
    </html>