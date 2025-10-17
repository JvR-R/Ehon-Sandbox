# mqtt_client/models.py

from django.db import models

class ClientTag(models.Model):
    client_id = models.PositiveIntegerField()
    customer_id = models.PositiveIntegerField()
    card_name = models.CharField(max_length=255)
    card_number = models.CharField(max_length=50)
    card_type = models.PositiveIntegerField()
    list_driver = models.PositiveIntegerField()
    list_vehicle = models.PositiveIntegerField()
    expiry_date = models.DateField()
    enabled_prompt = models.BooleanField(default=False)
    pin_number = models.CharField(max_length=10)
    pin_prompt = models.BooleanField(default=False)
    prompt_vehicle = models.PositiveIntegerField(default=0)
    driver_prompt = models.PositiveIntegerField(default=0)
    projectnum_prompt = models.BooleanField(default=False)
    odo_prompt = models.BooleanField(default=False)
    additional_info = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'client_tags'  # Explicitly define the table name
        managed = False
        verbose_name = 'Client Tag'
        verbose_name_plural = 'Client Tags'

    def __str__(self):
        return f"ClientTag {self.client_id} - {self.card_name}"
    
class Driver(models.Model):
    driver_id = models.PositiveIntegerField(max_length=255, primary_key=True)
    client_id = models.PositiveIntegerField()
    first_name = models.CharField(max_length=255)
    surname = models.CharField(max_length=255)

    class Meta:
        db_table = 'drivers'  # Explicitly define the table name
        managed = False
        verbose_name = 'Driver'
        verbose_name_plural = 'Drivers'

    def __str__(self):
        return f"ClientTag {self.client_id} - {self.card_name}"
    
class Vehicle(models.Model):
    vehicle_id = models.PositiveIntegerField(max_length=255, primary_key=True)
    client_id = models.PositiveIntegerField()
    vehicle_name = models.CharField(max_length=60)
    vehicle_rego = models.CharField(max_length=7)


    class Meta:
        db_table = 'vehicles'  # Explicitly define the table name
        managed = False
        verbose_name = 'Vehicle'
        verbose_name_plural = 'Vehicles'

    def __str__(self):
        return f"ClientTag {self.client_id} - {self.card_name}"
    
class Console(models.Model):
    uid = models.CharField(max_length=255, primary_key=True)  # Set uid as primary key
    crc_auth = models.CharField(max_length=255)
    crc_driver = models.CharField(max_length=255)
    crc_vehicle = models.CharField(max_length=255)
    crc_tank = models.CharField(max_length=255)
    crc_pumps = models.CharField(max_length=255)

    class Meta:
        db_table = 'console'  # Adjust this if your table name is different
        managed = False  # Set to False if you're not managing the table via Django migrations
        verbose_name = 'Console'
        verbose_name_plural = 'Consoles'

    def __str__(self):
        return f"Console UID: {self.uid}"

class Product(models.Model):
    product_id = models.PositiveIntegerField(primary_key=True)
    product_name = models.CharField(max_length=255)

    # Add product_density if your DB table has that column:
    product_density = models.FloatField(null=True, blank=True)

    class Meta:
        db_table = 'Products'  # Match your actual table name
        managed = False

    def __str__(self):
        return self.product_name

class Tank(models.Model):
    uid = models.CharField(max_length=255, primary_key=True)
    tank_gauge_id = models.PositiveIntegerField()
    tank_id = models.PositiveIntegerField()
    capacity = models.PositiveIntegerField()
    product = models.ForeignKey(
        Product, 
        to_field='product_id',    # This ensures it links via the product_id field in Product
        db_column='product_id',   # The column in Tanks that references Products
        on_delete=models.CASCADE
    )

    # Additional columns in tanks
    chart_id = models.PositiveIntegerField(null=True, blank=True)
    offset_tank = models.FloatField(null=True, blank=True)
    current_volume = models.FloatField(null=True, blank=True)
    dipr_date = models.DateField(null=True, blank=True)
    dipr_time = models.TimeField(null=True, blank=True)
    recon_time = models.TimeField(null=True, blank=True)

    class Meta:
        db_table = 'Tanks'  # Match your table name
        managed = False
        # If in your database you logically treat (uid, tank_id) as unique, you can do:
        # unique_together = (("uid","tank_id"),)

    def __str__(self):
        return f"Tank(uid={self.uid}, tank_id={self.tank_id})"

        
class Pumps(models.Model):
    uid = models.CharField(max_length=255, primary_key=True)
    Pulse_rate = models.FloatField(null=True, blank=True)
    tank_id = models.PositiveIntegerField()
    Nozzle_number = models.PositiveIntegerField()

    class Meta:
        db_table = 'pumps'  # Match your table name
        managed = False
        # If in your database you logically treat (uid, tank_id) as unique, you can do:
        # unique_together = (("uid","tank_id"),)

    def __str__(self):
        return f"Pump(uid={self.uid}, tank_id={self.tank_id})"


class AlarmConfig(models.Model):
    # Composite relationship to Tank based on (uid, tank_id)
    alarm_id = models.PositiveIntegerField(primary_key=True)
    uid = models.CharField(max_length=255)
    tank_id = models.PositiveIntegerField()

    # Alarms fields
    crithigh_alarm = models.FloatField(null=True, blank=True)
    high_alarm     = models.FloatField(null=True, blank=True)
    low_alarm      = models.FloatField(null=True, blank=True)
    critlow_alarm  = models.FloatField(null=True, blank=True)

    class Meta:
        db_table = 'alarms_config'
        managed = False
        unique_together = (('uid', 'tank_id'),)

    def __str__(self):
        return f"AlarmConfig(uid={self.uid}, tank_id={self.tank_id})"
