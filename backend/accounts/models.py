from django.db import models
from django.contrib.auth.models import User
import uuid
from django.contrib.auth import get_user_model
from rest_framework import viewsets

User = get_user_model()

class AuthEvent(models.Model):
    user        = models.ForeignKey(get_user_model(), null=True, on_delete=models.SET_NULL)
    event       = models.CharField(max_length=24)   # login_success / logout / refresh / login_failed / 401
    ip          = models.GenericIPAddressField(null=True)
    user_agent  = models.TextField(null=True)
    created_at  = models.DateTimeField(auto_now_add=True)

    class Meta:
        indexes = [models.Index(fields=["event", "created_at"])]

class Console(models.Model):
    uid = models.AutoField(primary_key=True)
    device_id = models.CharField(unique=True, max_length=60, blank=True, null=True)
    device_type = models.IntegerField()
    man_data = models.DateField(blank=True, null=True)
    console_status = models.CharField(max_length=30)
    firmware = models.CharField(max_length=45, blank=True, null=True)
    uart1 = models.CharField(max_length=10)
    uart1_id = models.CharField(max_length=15)
    uart3_fms = models.IntegerField()
    uart3 = models.IntegerField()
    uart5_fms = models.IntegerField()
    uart5 = models.IntegerField()
    uart6 = models.IntegerField()
    CAN1 = models.IntegerField()
    fw_flag = models.IntegerField()
    cfg_flag = models.IntegerField()
    restart_flag = models.IntegerField()
    logs_flag = models.IntegerField()
    last_conndate = models.DateField(blank=True, null=True)
    last_conntime = models.TimeField(blank=True, null=True)
    console_ip = models.CharField(max_length=45, blank=True, null=True)
    console_imei = models.CharField(max_length=45, blank=True, null=True)
    console_coordinates = models.CharField(max_length=45, blank=True, null=True)
    cs_signal = models.IntegerField(blank=True, null=True)
    dv_flag = models.IntegerField()
    bootup = models.CharField(max_length=45, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'console'

    def __str__(self):
        return f"Console {self.device_id or self.uid}"


class Client(models.Model):
    client_id = models.AutoField(primary_key=True)
    reseller = models.ForeignKey('Reseller', on_delete=models.CASCADE)
    client_name = models.CharField(unique=True, max_length=60)
    client_address = models.CharField(max_length=255, blank=True, null=True)
    client_email = models.CharField(max_length=60)
    client_phone = models.CharField(max_length=20, blank=True, null=True)
    mcs_clientid = models.FloatField(blank=True, null=True)
    mcs_liteid = models.FloatField(blank=True, null=True)

    class Meta:
        db_table = 'Clients'
        managed = False

    def __str__(self):
        return self.client_name


class Distributor(models.Model):
    dist_id = models.AutoField(primary_key=True)
    dist_name = models.CharField(unique=True, max_length=60)
    dist_email = models.CharField(max_length=60, blank=True, null=True)
    dist_address = models.CharField(max_length=255, blank=True, null=True)
    dist_phone = models.CharField(max_length=20, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'Distributor'

    def __str__(self):
        return self.dist_name


class Reseller(models.Model):
    reseller_id = models.AutoField(primary_key=True)
    dist = models.ForeignKey(Distributor, on_delete=models.CASCADE)
    reseller_name = models.CharField(max_length=60)
    reseller_email = models.CharField(max_length=70)
    reseller_address = models.CharField(max_length=70, blank=True, null=True)
    reseller_phone = models.CharField(max_length=20)

    class Meta:
        managed = False
        db_table = 'Reseller'
        unique_together = (('reseller_name', 'dist'),)

    def __str__(self):
        return self.reseller_name


class ConsoleAssociation(models.Model):
    cs_ascid = models.AutoField(primary_key=True)

    uid = models.ForeignKey(
        Console,
        on_delete=models.CASCADE,
        db_column="uid",
        blank=True, null=True,
    )

    dist = models.ForeignKey(             # <── rename for clarity
        Distributor,
        on_delete=models.SET_NULL,
        db_column="dist_id",               #  ← tell Django the exact column
        blank=True, null=True,
    )

    reseller = models.ForeignKey(         # <── rename for clarity
        Reseller,
        on_delete=models.SET_NULL,
        db_column="reseller_id",           #  ← exact column name
        blank=True, null=True,
    )

    client = models.ForeignKey(
        Client,
        on_delete=models.SET_NULL,
        db_column="Client_id",             # already correct
        blank=True, null=True,
    )

    sales_date = models.DateField(blank=True, null=True)
    sales_time = models.TimeField()

    class Meta:
        managed = False                   # existing table, no migration
        db_table = "Console_Asociation"



class Customers(models.Model):
    customer_id = models.AutoField(primary_key=True)
    client = models.ForeignKey(Client, models.DO_NOTHING)
    customer_name = models.CharField(max_length=60)
    customer_email = models.CharField(max_length=70)
    customer_address = models.CharField(max_length=80)
    customer_city = models.CharField(max_length=60)
    customer_zip = models.IntegerField()
    customer_country = models.CharField(max_length=60)
    customer_phone = models.IntegerField()
    blocked_sites = models.CharField(max_length=150, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'Customers'
        unique_together = (('client', 'customer_name'),)


# ─────────────────────────────────────────────────────────────────────────────
#  S I T E S
# ─────────────────────────────────────────────────────────────────────────────
class Sites(models.Model):
    """
    A single physical (or logical) site that belongs to a client/console.
    """
    # primary key -------------------------------------------------------------
    site_id = models.AutoField(primary_key=True, db_column="Site_id")

    # foreign keys ------------------------------------------------------------
    client = models.ForeignKey(
        "Client",
        on_delete=models.CASCADE,
        db_column="Client_id",
        related_name="sites",
    )
    uid = models.ForeignKey(
        "Console",
        on_delete=models.DO_NOTHING,
        db_column="uid",
        related_name="sites",
    )

    # core columns ------------------------------------------------------------
    name       = models.CharField(  # renamed from `site_name`
        max_length=60,
        db_column="Site_name",
        blank=True,
        null=True,
    )
    site_info  = models.CharField(max_length=255, db_column="Site_Info",
                                  blank=True, null=True)
    last_date  = models.DateField(blank=True, null=True)
    last_time  = models.TimeField(blank=True, null=True)
    site_country  = models.CharField(max_length=60, blank=True, null=True)
    site_address  = models.CharField(max_length=60, blank=True, null=True)
    site_city     = models.CharField(max_length=60, blank=True, null=True)
    postcode      = models.IntegerField(blank=True, null=True)
    phone         = models.CharField(max_length=10, blank=True, null=True)
    email         = models.CharField(max_length=60, db_column="Email",
                                     blank=True, null=True)
    mcs_id        = models.IntegerField(blank=True, null=True)
    site_status   = models.CharField(max_length=45, blank=True, null=True)
    time_zone     = models.CharField(max_length=45, blank=True, null=True)

    # meta --------------------------------------------------------------------
    class Meta:
        managed = False            # table already exists
        db_table = "Sites"
        unique_together = (("mcs_id", "client", "uid"),)

    def __str__(self) -> str:
        return self.name or f"Site {self.site_id}"


class Tanks(models.Model):
    tank_uid = models.AutoField(primary_key=True)
    tank_id = models.IntegerField()
    uid = models.ForeignKey('Console', models.DO_NOTHING, db_column='uid')
    client = models.ForeignKey(Client, models.DO_NOTHING)
    site_id = models.IntegerField(db_column='Site_id')  # Field name made lowercase.
    tank_name = models.CharField(db_column='Tank_name', max_length=60, blank=True, null=True)  # Field name made lowercase.
    dipr_date0 = models.DateField(blank=True, null=True)
    dipr_time0 = models.TimeField(blank=True, null=True)
    dipr_date = models.DateField(blank=True, null=True)
    dipr_time = models.TimeField(blank=True, null=True)
    product_id = models.IntegerField()
    capacity = models.IntegerField()
    current_volume = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    ullage = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    current_percent = models.FloatField(blank=True, null=True)
    tank_gauge_type = models.CharField(max_length=50)                          # Added max_length
    tank_gauge_uart = models.CharField(max_length=50, blank=True, null=True)  # Added max_length
    tank_gauge_id = models.CharField(max_length=50, blank=True, null=True)    # Added max_length
    chart_id = models.IntegerField(blank=True, null=True)
    fms_type = models.CharField(max_length=50)                                 # Added max_length
    fms_uart = models.CharField(max_length=50, blank=True, null=True)         # Added max_length
    fms_id = models.CharField(max_length=50, blank=True, null=True)           # Added max_length
    relay_type = models.IntegerField(blank=True, null=True)
    relay_uart = models.IntegerField(blank=True, null=True)
    volume_height = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    water_volume = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    water_height = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    temperature = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    tc_volume = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    alert_type = models.IntegerField(blank=True, null=True)
    level_alert = models.IntegerField(blank=True, null=True)
    alert_flag = models.IntegerField(blank=True, null=True)
    offset_tank = models.FloatField(blank=True, null=True)
    offset_flag = models.IntegerField(blank=True, null=True)
    recon_time = models.TimeField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'Tanks'
        unique_together = (('tank_id', 'uid', 'site_id'),)


class UserAcceslevel(models.Model):
    access_level = models.IntegerField(primary_key=True)
    user_type = models.CharField(max_length=30)

    class Meta:
        managed = False
        db_table = 'User_acceslevel'


class ActiveAlerts(models.Model):
    alert_id = models.AutoField(primary_key=True)
    uid = models.IntegerField()
    alert_type = models.IntegerField()
    message_id = models.IntegerField()
    alert_timestamp = models.DateTimeField()
    aa_active = models.IntegerField()

    class Meta:
        managed = False
        db_table = 'active_alerts'


class AlarmsConfig(models.Model):
    alarm_id = models.AutoField(primary_key=True)
    client_id = models.IntegerField()
    uid = models.IntegerField()
    site_id = models.IntegerField(db_column='Site_id')  # Field name made lowercase.
    tank_id = models.IntegerField()
    high_alarm = models.IntegerField()
    low_alarm = models.IntegerField()
    crithigh_alarm = models.IntegerField()
    critlow_alarm = models.IntegerField()
    alarm_enable = models.IntegerField()
    relay1 = models.IntegerField(blank=True, null=True)
    relay2 = models.IntegerField(blank=True, null=True)
    relay3 = models.IntegerField(blank=True, null=True)
    relay4 = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'alarms_config'
        unique_together = (('uid', 'tank_id', 'client_id', 'site_id'), ('uid', 'tank_id', 'site_id'),)


class AppProduct(models.Model):
    id = models.BigAutoField(primary_key=True)
    name = models.CharField(max_length=255)
    description = models.TextField()
    price = models.DecimalField(max_digits=10, decimal_places=2)

    class Meta:
        managed = False
        db_table = 'app_product'


class AuthGroup(models.Model):
    name = models.CharField(unique=True, max_length=150)

    class Meta:
        managed = False
        db_table = 'auth_group'


class AuthGroupPermissions(models.Model):
    id = models.BigAutoField(primary_key=True)
    group = models.ForeignKey(AuthGroup, models.DO_NOTHING)
    permission = models.ForeignKey('AuthPermission', models.DO_NOTHING)

    class Meta:
        managed = False
        db_table = 'auth_group_permissions'
        unique_together = (('group', 'permission'),)


class AuthPermission(models.Model):
    name = models.CharField(max_length=255)
    content_type = models.ForeignKey('DjangoContentType', models.DO_NOTHING)
    codename = models.CharField(max_length=100)

    class Meta:
        managed = False
        db_table = 'auth_permission'
        unique_together = (('content_type', 'codename'),)


class AuthUser(models.Model):
    password = models.CharField(max_length=128)
    last_login = models.DateTimeField(blank=True, null=True)
    is_superuser = models.IntegerField()
    username = models.CharField(unique=True, max_length=150)
    first_name = models.CharField(max_length=150)
    last_name = models.CharField(max_length=150)
    email = models.CharField(max_length=254)
    is_staff = models.IntegerField()
    is_active = models.IntegerField()
    date_joined = models.DateTimeField()

    class Meta:
        managed = False
        db_table = 'auth_user'


class AuthUserGroups(models.Model):
    id = models.BigAutoField(primary_key=True)
    user = models.ForeignKey(AuthUser, models.DO_NOTHING)
    group = models.ForeignKey(AuthGroup, models.DO_NOTHING)

    class Meta:
        managed = False
        db_table = 'auth_user_groups'
        unique_together = (('user', 'group'),)


class AuthUserUserPermissions(models.Model):
    id = models.BigAutoField(primary_key=True)
    user = models.ForeignKey(AuthUser, models.DO_NOTHING)
    permission = models.ForeignKey(AuthPermission, models.DO_NOTHING)

    class Meta:
        managed = False
        db_table = 'auth_user_user_permissions'
        unique_together = (('user', 'permission'),)


class ClientCard(models.Model):
    card_id = models.IntegerField(primary_key=True)  # The composite primary key (card_id, client_id) found, that is not supported. The first column is selected.
    client = models.OneToOneField(Client, models.DO_NOTHING)
    card_name = models.CharField(max_length=30)
    card_number = models.IntegerField()
    card_type = models.IntegerField()
    expire_date = models.DateField(blank=True, null=True)
    pin_number = models.IntegerField(blank=True, null=True)
    pin_change = models.IntegerField(blank=True, null=True)
    prompt_vehicle = models.IntegerField(blank=True, null=True)
    assist_number = models.IntegerField(blank=True, null=True)
    prompt_driver = models.IntegerField(blank=True, null=True)
    ad_info1 = models.CharField(max_length=80, blank=True, null=True)
    ad_info2 = models.CharField(max_length=80, blank=True, null=True)
    ad_info3 = models.CharField(max_length=80, blank=True, null=True)
    active = models.IntegerField()

    class Meta:
        managed = False
        db_table = 'client_card'
        unique_together = (('card_id', 'client'),)


class ClientSiteGroups(models.Model):
    mapping_id = models.AutoField(primary_key=True)
    group_id = models.IntegerField()
    client_id = models.IntegerField()
    site_no = models.IntegerField()
    site_name = models.CharField(max_length=60)

    class Meta:
        managed = False
        db_table = 'client_site_groups'


# ─────────────────────────────────────────────────────────────────────────────
#  C L I E N T   T R A N S A C T I O N
# ─────────────────────────────────────────────────────────────────────────────
class ClientTransaction(models.Model):
    transaction_id = models.AutoField(primary_key=True)

    uid = models.ForeignKey(
        "Console",
        on_delete=models.DO_NOTHING,
        db_column="uid",
        related_name="transactions",
    )

    # proper FK — points at *Sites* via existing `site_id` column
    site = models.ForeignKey(
        "Sites",
        on_delete=models.DO_NOTHING,
        db_column="site_id",
        related_name="transactions",
        blank=True,
        null=True,
    )

    # ------------------------------------------------------------------------
    # the remaining columns are unchanged – trimmed for brevity
    # ------------------------------------------------------------------------
    piusi_transaction_id = models.IntegerField(blank=True, null=True)
    fms_id               = models.IntegerField(blank=True, null=True)
    transaction_date     = models.DateField()
    transaction_time     = models.TimeField()
    transaction_date_utc0 = models.DateField(blank=True, null=True)
    transaction_time_utc0 = models.TimeField(blank=True, null=True)
    card_number          = models.CharField(max_length=60)
    card_holder_name     = models.CharField(max_length=60, blank=True, null=True)
    customer_name        = models.CharField(max_length=60, blank=True, null=True)
    odometer             = models.IntegerField(blank=True, null=True)
    registration         = models.CharField(max_length=11)
    tank_id              = models.IntegerField()
    tank_name            = models.CharField(max_length=65, blank=True, null=True)
    pump_id              = models.IntegerField()
    dispensed_volume     = models.FloatField()
    actions              = models.CharField(max_length=20)
    product              = models.CharField(max_length=45, blank=True, null=True)
    mcs_transaction_id   = models.IntegerField(blank=True, null=True)
    
    # Additional FMS fields
    stop_method          = models.IntegerField(blank=True, null=True, db_column='stop_method')
    pulses               = models.IntegerField(blank=True, null=True)
    startDateTime        = models.CharField(max_length=30, blank=True, null=True)  # Store as string to avoid timezone conversion
    endDateTime          = models.CharField(max_length=30, blank=True, null=True)  # Store as string to avoid timezone conversion
    startDip             = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    endDip               = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = "client_transaction"
        unique_together = (("fms_id", "piusi_transaction_id", "uid"),)
        indexes = [
            models.Index(fields=["uid", "transaction_date"], name="uid_date_idx"),
            models.Index(fields=["-transaction_date", "-transaction_time"],
                         name="date_time_idx"),
        ]



class ClientsRecconciliation(models.Model):
    idclients_recconciliation = models.AutoField(primary_key=True)
    client_id = models.IntegerField()
    uid = models.IntegerField()
    site_id = models.IntegerField(db_column='Site_id')  # Field name made lowercase.
    tank_id = models.IntegerField(db_column='Tank_id')  # Field name made lowercase.
    opening_balance = models.DecimalField(db_column='Opening_balance', max_digits=10, decimal_places=2, blank=True, null=True)  # Field name made lowercase.
    closing_balance = models.DecimalField(db_column='Closing_balance', max_digits=10, decimal_places=2, blank=True, null=True)  # Field name made lowercase.
    total_transaction = models.IntegerField(db_column='Total_transaction', blank=True, null=True)  # Field name made lowercase.
    total_volume = models.DecimalField(db_column='Total_volume', max_digits=10, decimal_places=2, blank=True, null=True)  # Field name made lowercase.
    total_deliveries = models.DecimalField(db_column='Total_Deliveries', max_digits=10, decimal_places=2, blank=True, null=True)  # Field name made lowercase.
    delta = models.DecimalField(db_column='Delta', max_digits=10, decimal_places=2, blank=True, null=True)  # Field name made lowercase.
    reconciliation = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    reconciliation_flag = models.IntegerField(blank=True, null=True)
    date = models.DateField(db_column='Date', blank=True, null=True)  # Field name made lowercase.

    class Meta:
        managed = False
        db_table = 'clients_recconciliation'


class DeliveryHistoric(models.Model):
    delivery_id = models.AutoField(primary_key=True)
    uid = models.IntegerField()
    transaction_date = models.DateField()
    transaction_time = models.TimeField()
    transaction_date_utc0 = models.DateField(blank=True, null=True)
    transaction_time_utc0 = models.TimeField(blank=True, null=True)
    tank_id = models.IntegerField()
    current_volume = models.DecimalField(max_digits=10, decimal_places=0)
    delivery = models.IntegerField()
    mcs_transaction_id = models.IntegerField(blank=True, null=True)
    site_name = models.CharField(max_length=45, blank=True, null=True)
    site_id = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'delivery_historic'


class DipreadHistoric(models.Model):
    dipread_id = models.AutoField(primary_key=True)
    uid = models.IntegerField()
    transaction_date = models.DateField()
    transaction_time = models.TimeField()
    tank_id = models.IntegerField()
    current_volume = models.DecimalField(max_digits=10, decimal_places=2)
    ullage = models.DecimalField(max_digits=10, decimal_places=2)
    temperature = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    tc_volume = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    volume_height = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    water_volume = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    water_height = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    mcs_transaction_id = models.IntegerField(unique=True, blank=True, null=True)
    site_name = models.CharField(max_length=70, blank=True, null=True)
    site_id = models.IntegerField(blank=True, null=True)
    transaction_date_utc0 = models.DateField(blank=True, null=True)
    transaction_time_utc0 = models.TimeField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'dipread_historic'


class EmailHistoric(models.Model):
    """
    Historic record of emails sent from the system
    Matches the actual email_historic table structure
    """
    idemail_historic = models.AutoField(primary_key=True)
    email_date = models.DateField()
    email_time = models.TimeField()
    receiver_email = models.CharField(max_length=85)

    class Meta:
        managed = False  # Table already exists in database
        db_table = 'email_historic'


class DjangoAdminLog(models.Model):
    action_time = models.DateTimeField()
    object_id = models.TextField(blank=True, null=True)
    object_repr = models.CharField(max_length=200)
    action_flag = models.PositiveSmallIntegerField()
    change_message = models.TextField()
    content_type = models.ForeignKey('DjangoContentType', models.DO_NOTHING, blank=True, null=True)
    user = models.ForeignKey('AuthUser', models.DO_NOTHING)

    class Meta:
        managed = False
        db_table = 'django_admin_log'


class DjangoContentType(models.Model):
    app_label = models.CharField(max_length=100)
    model = models.CharField(max_length=100)

    class Meta:
        managed = False
        db_table = 'django_content_type'
        unique_together = (('app_label', 'model'),)


class DjangoMigrations(models.Model):
    id = models.BigAutoField(primary_key=True)
    app = models.CharField(max_length=255)
    name = models.CharField(max_length=255)
    applied = models.DateTimeField()

    class Meta:
        managed = False
        db_table = 'django_migrations'


class DjangoSession(models.Model):
    session_key = models.CharField(primary_key=True, max_length=40)
    session_data = models.TextField()
    expire_date = models.DateTimeField()

    class Meta:
        managed = False
        db_table = 'django_session'


class Drivers(models.Model):
    driver_id = models.AutoField(primary_key=True)
    customer_id = models.IntegerField()
    first_name = models.CharField(max_length=30)
    surname = models.CharField(max_length=30, blank=True, null=True)
    driver_pinnumber = models.IntegerField()
    driver_phone = models.IntegerField(blank=True, null=True)
    external_id = models.CharField(max_length=30, blank=True, null=True)
    license_number = models.CharField(max_length=30, blank=True, null=True)
    license_expire = models.DateField(blank=True, null=True)
    license_type = models.CharField(max_length=30, blank=True, null=True)
    driver_email = models.CharField(max_length=60, blank=True, null=True)
    driver_enabled = models.IntegerField()
    driver_addinfo = models.CharField(max_length=80, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'drivers'


class FuelQuality(models.Model):
    if_fq = models.AutoField(primary_key=True)
    client_id = models.IntegerField(blank=True, null=True)
    fq_date = models.DateField(blank=True, null=True)
    fq_time = models.TimeField(blank=True, null=True)
    uid = models.IntegerField(blank=True, null=True)
    tank_id = models.IntegerField(blank=True, null=True)
    particle_4um = models.IntegerField(blank=True, null=True)
    particle_6um = models.IntegerField(blank=True, null=True)
    particle_14um = models.IntegerField(blank=True, null=True)
    fq_bubbles = models.IntegerField(blank=True, null=True)
    fq_cutting = models.IntegerField(blank=True, null=True)
    fq_sliding = models.IntegerField(blank=True, null=True)
    fq_fatigue = models.IntegerField(blank=True, null=True)
    fq_fibre = models.IntegerField(blank=True, null=True)
    fq_air = models.IntegerField(blank=True, null=True)
    fq_unknown = models.IntegerField(blank=True, null=True)
    fq_temp = models.FloatField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'fuel_quality'
        db_table_comment = '\t\t\t'


class Login(models.Model):
    user_id = models.AutoField(primary_key=True)
    username = models.CharField(unique=True, max_length=60)
    password = models.CharField(max_length=60)
    access_level = models.ForeignKey('UserAcceslevel', models.DO_NOTHING, db_column='access_level')
    client = models.ForeignKey(Client, models.DO_NOTHING)
    name = models.CharField(max_length=60)
    last_name = models.CharField(max_length=60)
    active = models.IntegerField()
    last_date = models.DateField(blank=True, null=True)
    last_time = models.TimeField(blank=True, null=True)
    token = models.CharField(max_length=256, blank=True, null=True)
    token_expiry = models.DateTimeField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'login'


class Messages(models.Model):
    message_id = models.AutoField(primary_key=True)
    message_lang = models.IntegerField()
    message_type = models.IntegerField()
    message_content = models.CharField(max_length=160)

    class Meta:
        managed = False
        db_table = 'messages'


class MyapiExamplemodel(models.Model):
    id = models.BigAutoField(primary_key=True)
    name = models.CharField(max_length=100)
    description = models.TextField()

    class Meta:
        managed = False
        db_table = 'myapi_examplemodel'


class NewTable(models.Model):
    id_fq = models.IntegerField(primary_key=True)
    client_id = models.IntegerField(blank=True, null=True)
    uid = models.IntegerField(blank=True, null=True)
    tank_id = models.IntegerField(blank=True, null=True)
    date = models.DateField(blank=True, null=True)
    time = models.TimeField(blank=True, null=True)
    number_4 = models.IntegerField(db_column='4', blank=True, null=True)  # Field renamed because it wasn't a valid Python identifier.
    number_6 = models.IntegerField(db_column='6', blank=True, null=True)  # Field renamed because it wasn't a valid Python identifier.
    number_14 = models.IntegerField(db_column='14', blank=True, null=True)  # Field renamed because it wasn't a valid Python identifier.
    number_21 = models.IntegerField(db_column='21', blank=True, null=True)  # Field renamed because it wasn't a valid Python identifier.

    class Meta:
        managed = False
        db_table = 'new_table'


class Products(models.Model):
    product_id = models.IntegerField(primary_key=True)
    product_name = models.CharField(max_length=60)
    product_colour = models.CharField(max_length=60)
    product_density = models.FloatField()
    product_cte = models.FloatField(blank=True, null=True)
    product_basetemp = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'products'


class Pumps(models.Model):
    pump_id = models.AutoField(primary_key=True)  # The composite primary key (pump_id, tank_id, uid) found, that is not supported. The first column is selected.
    uid = models.ForeignKey(Console, models.DO_NOTHING, db_column='uid')
    tank_id = models.IntegerField()
    nozzle_number = models.IntegerField(db_column='Nozzle_Number', blank=True, null=True)  # Field name made lowercase.
    nozzle_walk_time = models.IntegerField(db_column='Nozzle_Walk_Time', blank=True, null=True)  # Field name made lowercase.
    nozzle_auth_time = models.IntegerField(db_column='Nozzle_Auth_Time', blank=True, null=True)  # Field name made lowercase.
    nozzle_max_run_time = models.IntegerField(db_column='Nozzle_Max_Run_Time', blank=True, null=True)  # Field name made lowercase.
    nozzle_no_flow = models.IntegerField(db_column='Nozzle_No_Flow', blank=True, null=True)  # Field name made lowercase.
    nozzle_product = models.CharField(db_column='Nozzle_Product', max_length=255, blank=True, null=True)  # Field name made lowercase.
    pulse_rate = models.DecimalField(db_column='Pulse_Rate', max_digits=10, decimal_places=2, blank=True, null=True)  # Field name made lowercase.

    class Meta:
        managed = False
        db_table = 'pumps'
        unique_together = (('pump_id', 'tank_id', 'uid'),)


class ReportCron(models.Model):
    idreport_cron = models.AutoField(primary_key=True)
    client = models.OneToOneField(Client, models.DO_NOTHING)
    group_id = models.IntegerField(blank=True, null=True)
    email_list = models.CharField(max_length=1000, blank=True, null=True)
    start_time = models.IntegerField(blank=True, null=True)
    finish_time = models.IntegerField(blank=True, null=True)
    cron = models.CharField(max_length=45, blank=True, null=True)
    report_interval = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'report_cron'


class SiteGroups(models.Model):
    group_id = models.AutoField(primary_key=True)
    client_id = models.IntegerField()
    group_name = models.CharField(max_length=60)

    class Meta:
        managed = False
        db_table = 'site_groups'


class StockNoti(models.Model):
    noti_id = models.AutoField(primary_key=True)
    client_id = models.IntegerField()
    email = models.CharField(max_length=60)
    cron = models.IntegerField()
    notification_type = models.IntegerField()
    start_time = models.IntegerField()
    finish_time = models.IntegerField()
    interval_time = models.IntegerField()
    active = models.IntegerField()

    class Meta:
        managed = False
        db_table = 'stock_noti'


class StrappingChart(models.Model):
    chart_id = models.AutoField(primary_key=True)
    client_id = models.IntegerField()
    chart_name = models.CharField(max_length=60)
    json_data = models.TextField(db_collation='utf8mb4_general_ci', blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'strapping_chart'


class TankgaugeType(models.Model):
    tank_gauge_id = models.IntegerField(primary_key=True)
    device_name = models.CharField(max_length=60)

    class Meta:
        managed = False
        db_table = 'tankgauge_type'


class Timezones(models.Model):
    time_zone = models.CharField(max_length=255, blank=True, null=True)
    example_city = models.CharField(max_length=255, blank=True, null=True)
    utc_offset = models.CharField(max_length=50, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'timezones'


class TransactionDuplicates(models.Model):
    transaction_id = models.AutoField(primary_key=True)
    uid = models.IntegerField()
    piusi_transaction_id = models.IntegerField(blank=True, null=True)
    fms_id = models.IntegerField(blank=True, null=True)
    transaction_date = models.DateField()
    transaction_time = models.TimeField()
    transaction_date_utc0 = models.DateField(blank=True, null=True)
    transaction_time_utc0 = models.TimeField(blank=True, null=True)
    card_number = models.IntegerField(blank=True, null=True)
    card_holder_name = models.CharField(max_length=60, blank=True, null=True)
    customer_name = models.CharField(max_length=60, blank=True, null=True)
    odometer = models.IntegerField(blank=True, null=True)
    registration = models.CharField(max_length=11, blank=True, null=True)
    tank_id = models.IntegerField()
    tank_name = models.CharField(max_length=65, blank=True, null=True)
    site_id = models.IntegerField(blank=True, null=True)
    pump_id = models.IntegerField()
    dispensed_volume = models.FloatField()
    actions = models.CharField(max_length=20)
    product = models.CharField(max_length=45, blank=True, null=True)
    mcs_transaction_id = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'transaction_duplicates'


class Vehicles(models.Model):
    vehicle_id = models.AutoField(primary_key=True)
    customer = models.ForeignKey(Customers, models.DO_NOTHING)
    vehicle_assetnumber = models.CharField(max_length=30)
    odometer_type = models.IntegerField()
    allowed_products = models.CharField(max_length=150)
    odometer_prompt = models.IntegerField()
    last_odometer = models.IntegerField()
    vehicle_brand = models.CharField(max_length=30)
    vehicle_model = models.CharField(max_length=30)
    vehicle_type = models.CharField(max_length=30)
    vehicle_tanksize = models.IntegerField()
    vehicle_rego = models.CharField(max_length=30)
    vehicle_rego_date = models.DateField()
    vehicle_service = models.DateField()
    vehicle_service_km = models.IntegerField()
    vehicle_addinfo = models.CharField(max_length=80)
    requires_service = models.IntegerField()
    vehicle_enabled = models.IntegerField()

    class Meta:
        managed = False
        db_table = 'vehicles'

class UserScope(models.Model):
    ROLES = [
        ("OWNER", "Head office"),
        ("DIST", "Distributor"),
        ("RESELLER", "Reseller"),
        ("CLIENT", "Client"),
    ]
    user = models.OneToOneField(User, on_delete=models.CASCADE)
    role = models.CharField(max_length=10, choices=ROLES)
    company_id = models.CharField(max_length=10)

    class Meta:
        db_table = "user_scope"

class ExportJob(models.Model):
    """
    Tracks async export generation for large datasets.
    """
    id          = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    user        = models.ForeignKey(User, on_delete=models.CASCADE)
    file_type   = models.CharField(max_length=8, choices=[("pdf", "PDF"), ("xlsx", "XLSX"), ("csv", "CSV")])
    params      = models.JSONField()
    file_path   = models.TextField(null=True, blank=True)
    state       = models.CharField(max_length=12, default="PENDING")   # PENDING / STARTED / SUCCESS / FAILURE
    progress    = models.PositiveSmallIntegerField(default=0)          # 0‑100
    created_at  = models.DateTimeField(auto_now_add=True)
    finished_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        db_table = "export_job"        # keeps naming consistent

class ConsoleEnv(models.Model):
    env_id = models.AutoField(primary_key=True)
    uid    = models.ForeignKey("Console",
                               on_delete=models.CASCADE,
                               db_column="uid")

    tcpu = models.DecimalField(max_digits=5, decimal_places=2)   # °C
    vcpu = models.DecimalField(max_digits=5, decimal_places=2)   # V
    vin  = models.DecimalField(max_digits=5, decimal_places=2)   # V
    rssi = models.PositiveSmallIntegerField(null=True, blank=True)  # signal percent (0-100)
    gps_lat = models.CharField(max_length=32, null=True, blank=True)
    gps_lon = models.CharField(max_length=32, null=True, blank=True)

    recorded_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "console_env"      # change if you prefer another name
        indexes  = [models.Index(fields=["uid", "-recorded_at"])]
        managed  = False              # ← keep False if you’ll create the table by hand
        # managed  = True             # ← switch to True if you want migrations

class TankDevice(models.Model):
    id        = models.AutoField(primary_key=True)   # existing table
    tank_uid  = models.IntegerField()
    uid       = models.IntegerField()
    role      = models.CharField(max_length=16, default="other")
    port      = models.CharField(max_length=12, null=True, blank=True)

    class Meta:
        db_table = "tank_device"


class ConfigEhonGateway(models.Model):
    # PK is the FK to tank_device.id
    tank_device = models.OneToOneField(
        TankDevice,
        db_column="tank_device_id",
        primary_key=True,
        on_delete=models.CASCADE,
        related_name="gateway_cfg",
    )

    # NB: tanks.tank_uid is *not* the same as tanks.uid_id (console)
    tank_uid  = models.ForeignKey(
        "Tanks",
        to_field="tank_uid",
        db_column="tank_uid",
        on_delete=models.CASCADE,
        related_name="gateway_cfgs",
    )
    # Console uid (int), not FK name 'uid_id'
    uid       = models.ForeignKey(
        "Console",
        to_field="uid",
        db_column="uid",
        on_delete=models.CASCADE,
        related_name="gateway_cfgs",
    )

    tank_id   = models.IntegerField()                # logical tank number 1..4
    shape     = models.PositiveSmallIntegerField()   # tinyint
    height    = models.IntegerField(default=0)
    width     = models.IntegerField(default=0)
    depth     = models.IntegerField(default=0)
    probe_id  = models.IntegerField(null=True, blank=True)
    probe_conn = models.PositiveSmallIntegerField() 
    enabled   = models.BooleanField(default=False)
    offset    = models.DecimalField(max_digits=10, decimal_places=0, default=0)
    raw_bias_counts = models.IntegerField(default=0)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = "config_ehon_gateway"
