<section class="topborder">
<div class="logo">
    <a href="/">
        <img src="/vmi/images/EHON-VMI.png" alt="Logo" class="logo">
    </a>
</div>
<div class="home">
    <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/'">
        <img src="/vmi/images/EHON-VMI_icons-HOME.png" style="width:48px; height: 48px;">    
    </button>
</div>
</section>
<section class="border">
<button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/vmi/ipetropay/payment/users/'">
    <img src="/vmi/images/ipetrouser_icon_grey.png" style="width:48px; height: 48px;">  
</button>
<button type="button" class="button-link" style="cursor:pointer; width:48px; height: 48px;" onclick="window.location.href='/vmi/clients/'">
    <img src="/vmi/images/EHON-VMI_icons-SITE.png" style="width:48px; height: 48px;">
</button>
<button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/vmi/ipetropay/payment/'">
    <img src="/vmi/images/ipetrotrans_icon_grey.png" style="width:48px; height: 48px;">  
</button>
<?php if($accessLevel===1){ ?>
  <button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/vmi/ipetropay/bank-pay'">
    <img src="/vmi/images/ipetropay_icon.png" style="width:50px; height: 50px;">        
  </button>          
<?php } ?>
<button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/vmi/ipetropay/payment/historics/'">
    <img src="/vmi/images/ipetroarc_icon_grey.png" style="width:48px; height: 48px;">  
</button>
<button type="button" class="button-link" style="cursor:pointer;" onclick="window.location.href='/vmi/ipetropay/payment/Contactlist/'">
    <img src="/vmi/images/ipetrocontact_icon_grey.png" style="width:48px; height: 48px;">  
</button>
<form method="post" action="">
    <button type="submit" name="logout" class="button-link" style="cursor:pointer;">
        <img src="/vmi/images/EHON-VMI_icons-LOGOUT.png" style="width:48px; height: 48px;">
    </button>
</form>
</section>
