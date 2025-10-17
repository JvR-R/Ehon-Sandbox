<?php
    include('../../../db/log.php');   
    include('../../../db/logpriv.php'); 
    include('../../borderipay.php');
?>
<!DOCTYPE html>
<html lang="en" title="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Call List</title>
    <script src="script.js"></script>
    <link rel="stylesheet" href="style.css">
    
</head>

<body>
    <main class="table">
        <section class="table__header">       
        <h1> Call List</h1>
            <div class="input-group">
                <input type="search" placeholder="Search Company name or Site name...">
                <img src="/images/search.png" alt="">
            </div>
        </section>
        <section class="table__body">
            <table>
                <thead>
                    <tr>
                        <th> Name <span class="icon-arrow">&UpArrow;</span></th>
                        <th> Position <span class="icon-arrow">&UpArrow;</span></th>
                        <th> Mobile <span class="icon-arrow">&UpArrow;</span></th>
                        <th> Extension <span class="icon-arrow">&UpArrow;</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td> Herbert Few</td>
                        <td> Director </td>
                        <td> 0409 601 354 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Andre Mitchell </td>
                        <td> Director </td>
                        <td> 0488 118 297 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Alan Donovan</td>
                        <td> iPETRO Support </td>
                        <td> 0417 455 608 </td>
                        <td> 451</td>
                    </tr>
                    <tr>
                        <td> Aston Sweeney</td>
                        <td> iPETRO Support </td>
                        <td> 0491 975 487 </td>
                        <td> 431</td>
                    </tr>
                    <tr>
                        <td> Alex Salta</td>
                        <td> Key Accouts </td>
                        <td> 0472 877 276 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Anthony Bosco</td>
                        <td> Service Technician -SA </td>
                        <td> 0423 325 768 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Anton Braun</td>
                        <td> WHS Manager </td>
                        <td> 0408 347 181 </td>
                        <td> 405</td>
                    </tr>
                    <tr>
                        <td> Charne JvVuuren</td>
                        <td> Marketing Manager </td>
                        <td>  </td>
                        <td> 471</td>
                    </tr>
                    <tr>
                        <td> David Brady</td>
                        <td> Company Accountant </td>
                        <td>  </td>
                        <td> 412</td>
                    </tr>
                    <tr>
                        <td> Dustin Jackson</td>
                        <td> Service Technician - QLD </td>
                        <td> 0488 217 666 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Dylan Thomas</td>
                        <td> Sales Manager | Capital Equipment </td>
                        <td> 0488 010 310 </td>
                        <td> 431</td>
                    </tr>
                    <tr>
                        <td> Igor Da Rocha</td>
                        <td> Design Engineer </td>
                        <td> </td>
                        <td> 434</td>
                    </tr>
                    <tr>
                        <td> Isaac Considine</td>
                        <td> Sales </td>
                        <td> </td>
                        <td> 421</td>
                    </tr>
                    <tr>
                        <td> Jaiden Heijn</td>
                        <td> Sales Manager – Equipment & Parts </td>
                        <td> 0488 162 136 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> James Swart</td>
                        <td> Design Engineer </td>
                        <td>  </td>
                        <td> 411</td>
                    </tr>
                    <tr>
                        <td> Jaydan McDonnell</td>
                        <td> Capital Equipment </td>
                        <td> 0472 878 324 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Julie Dawson</td>
                        <td> Finance Officer </td>
                        <td>  </td>
                        <td> 474</td>
                    </tr>
                    <tr>
                        <td> Jules Morris</td>
                        <td> Project Coordinator </td>
                        <td> 0497 879 557 </td>
                        <td> 435</td>
                    </tr>
                    <tr>
                        <td> Kat Collins</td>
                        <td> Service Coordinator </td>
                        <td> 0428 570 583 </td>
                        <td> 413</td>
                    </tr>
                    <tr>
                        <td> Lisa Gallo</td>
                        <td> Work Order Coordinator </td>
                        <td> 0472 864 737 </td>
                        <td> 406</td>
                    </tr>
                    <tr>
                        <td> Louis Swanepoel</td>
                        <td> Operations Manager </td>
                        <td> 0472 847 852 </td>
                        <td> 441</td>
                    </tr>
                    <tr>
                        <td> Mark Salta</td>
                        <td> Head of Major Projects | PIT Director </td>
                        <td> 0459 164 747 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Morgan Bryan</td>
                        <td> Site & Service Consultant </td>
                        <td> 0438 962 562 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Michael Jones</td>
                        <td> Stock Control - Lead </td>
                        <td> 0472706914 </td>
                        <td> 472</td>
                    </tr>
                    <tr>
                        <td> Melissa Johnson</td>
                        <td> Store </td>
                        <td> </td>
                        <td> 445</td>
                    </tr>
                    <tr>
                        <td> Patricia Sastre</td>
                        <td> Design Engineer </td>
                        <td> </td>
                        <td> 404</td>
                    </tr>
                    <tr>
                        <td> Paul Corcoran</td>
                        <td> Head of iPETRO</td>
                        <td> 0419 737 968</td>
                        <td> 403</td>
                    </tr>
                    <tr>
                        <td> Riaan de Villiers</td>
                        <td> Marketing/Graphic designer </td>
                        <td> +27 83 651 7086</td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Roman Palek</td>
                        <td> Head of Electrical </td>
                        <td> 0407 062 254</td>
                        <td> 435</td>
                    </tr>
                    <tr>
                        <td> Rob Brands</td>
                        <td> Service Technician QLD </td>
                        <td> 0477 733 168 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Scott Maguire</td>
                        <td> Workshop & Commissioning Manager </td>
                        <td> 0437 669 609 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Susie Brook</td>
                        <td> Sales </td>
                        <td> </td>
                        <td>473 </td>
                    </tr>
                    <tr>
                        <td>Tim Forward</td>
                        <td> Equipment Specialist </td>
                        <td> 0448 177 137 </td>
                        <td> </td>
                    </tr>
                    <tr>
                        <td> Tony Sweeney</td>
                        <td> Equipment Sales </td>
                        <td>  </td>
                        <td> 437</td>
                    </tr>
                    <tr>
                        <td> Trisha Casey</td>
                        <td> Purchasing </td>
                        <td> 0417 870 733 </td>
                        <td> </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
