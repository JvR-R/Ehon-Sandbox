<?php
include('../db/dbh.php');
include('../db/logpriv.php'); 
?>
<!DOCTYPE html>
<html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>iPetro Pay</title>
    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <section class="topborder">
        <div class="logo">
            <a href="/">
                <img src="/images/EHON-VMI.png" alt="Logo" class="logo">
            </a>
        </div>
        <div class="home">
            <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/'">
                <img src="/images/EHON-VMI_icons-HOME.png" style="width:48px; height: 48px;">    
            </button>
        </div>
    </section>
    <section class="border">
        <button type="button" class="button-link" style="cursor:pointer; width:48px; height: 48px;" onclick="window.location.href='/clients/'">
            <img src="/images/EHON-VMI_icons-SITE.png" style="width:48px; height: 48px;">
        </button>
        <?php if($accessLevel===1){ ?>
          <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/ipetropay'">
            <img src="/images/ipetropay_icon_red.png" style="width:50px; height: 50px;">        
          </button>          
        <?php } ?>
        <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/ipetropay/payment/'">
            <img src="/images/ipetrotrans_icon_grey.png" style="width:48px; height: 48px;">  
        </button>
        <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/ipetropay/payment/users/'">
            <img src="/images/ipetrouser_icon_grey.png" style="width:48px; height: 48px;">  
        </button>
        <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/ipetropay/payment/historics/'">
            <img src="/images/ipetroarc_icon_grey.png" style="width:48px; height: 48px;">  
        </button>
        <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/ipetropay/payment/Contactlist/'">
            <img src="/images/ipetrocontact_icon_grey.png" style="width:48px; height: 48px;">  
        </button>
        <form method="post" action="">
            <button type="submit" name="logout" class="button-link" style="cursor:pointer;">
                <img src="/images/EHON-VMI_icons-LOGOUT.png" style="width:48px; height: 48px;">
            </button>
        </form>
    </section>
    <main class="table">
        <div class="text">
            <h2>The main types of sources include:</h2>
            <ul>
                <li>Solar Energy: Harnessing the power of the sun through photovoltaic cells or concentrated solar power.</li>
                <li>Wind Energy: Utilizing wind turbines to convert wind's kinetic energy into electricity.</li>
                <li>Hydropower: Generating electricity by capturing the energy of flowing or falling water through dams or turbines.</li>
                <li>Biomass Energy: Using organic materials such as wood, crops, or agricultural waste to produce heat or electricity.</li>
                <li>Geothermal Energy: Tapping into the heat stored within the Earth's crust to generate power or for direct heating and cooling.</li>
                <li>Tidal Energy: Converting the energy from tidal movements into electricity using tidal turbines.</li>
                <li>Wave Energy: Harnessing the power of ocean waves to generate electricity.</li>
                <li>These renewable energy sources are sustainable and have minimal impact on the environment compared to fossil fuels.</li>

            </ul>
        </div>
    </main>
    </body>
</html>
