<?php
session_start();

if (isset($_POST['logout'])) {
    // Destroy the session and redirect to the login page
    session_destroy();
    header("Location: /login/");
    exit;
}
// Check if the user is logged in
if (isset($_SESSION['loggedin'])) {
    $accessLevel = $_SESSION['accessLevel'];
    $companyId = $_SESSION['companyId'];
    // User is logged in, display the content
    if ($accessLevel == 3){
    echo "Contact the Administrator.";
    }
    else{
    ?>
<!DOCTYPE html>
<html lang="en" title="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Call List</title>
    <script src="script.js"></script>
      <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
  <script src="/vmi/js/theme-init.js"></script>
  <!-- THEME CSS - MUST BE FIRST -->
  <link rel="stylesheet" href="/vmi/css/theme.css">
  <!-- Other CSS files -->
<link rel="stylesheet" href="style.css">
    
</head>
<body>
<section class = "topborder">
        <div class="logo">
            <a href="/">
            <img src="/images/EHON Energy logo-2023-02.png" alt="Logo" class="logo">
            </a>
        </div>
        <div class= "home">
            <a href="/" class="button-link">
            <br><br><span class="button-text2">&#8962;</span><br><br>
            </a>
        </div>
        <div class = "support">
        <a href="https://ipetro.com.au/contact-the-ipetro-fluids-management-experts/">
        <button class="buttonbell">
            <svg viewBox="0 0 448 512" class="bell"><path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"></path></svg>
        </button>
        </a>
        </div>
    </section>
    <section class = "border">
        <a href="/clients/" class="button-link">
            <br><br><span class="button-text">&#127898;</span><br><br>
        </a>
        <a href="/clients/details/" class="button-link">
            <br><br><span class="button-text">&#128712;</span><br><br>
        </a>
        <a href="/Contactlist" class="button-link">
		<br><br><span class="button-text" style="color:orange;">&#128383;</span><br><br>
		</a>
        <form method="post" action="">
            <button type="submit" name="logout" class="button-link" style="cursor:pointer;">
            <div class="sign"><svg viewBox="0 0 512 512"><path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path></svg></div>
            </button>
        </form>
    </section>
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
<?php
    
}
} else {
    // User is not logged in, redirect to the login page
    header("Location: /login/");
    exit;
}
?>