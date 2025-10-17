# app/serializers.py

from rest_framework import serializers
from .models import (
    Client, Reseller, Distributor, Console, ConsoleAssociation,
    Customers, Vehicles, Pumps, AlarmsConfig, Sites, Tanks, ClientTransaction, SiteGroups,
    Products, StrappingChart, StopMethod, DipreadHistoric
)
from datetime import timedelta
from django.utils import timezone

class ResellerSerializer(serializers.ModelSerializer):
    class Meta:
        model = Reseller
        fields = '__all__'

class DistributorSerializer(serializers.ModelSerializer):
    class Meta:
        model = Distributor
        fields = '__all__'

class ClientSerializer(serializers.ModelSerializer):
    reseller = ResellerSerializer(read_only=True)

    class Meta:
        model = Client
        fields = '__all__'

class ConsoleSerializer(serializers.ModelSerializer):
    class Meta:
        model = Console
        fields = '__all__'

class SitesSerializer(serializers.ModelSerializer):
    class Meta:
        model = Sites
        fields = '__all__'

class TanksSerializer(serializers.ModelSerializer):
    class Meta:
        model = Tanks
        fields = '__all__'

class ConsoleAssociationSerializer(serializers.ModelSerializer):
    association_id = serializers.IntegerField(source='cs_ascid')
    client = ClientSerializer(read_only=True)
    dist = DistributorSerializer(read_only=True)
    reseller = ResellerSerializer(read_only=True)

    class Meta:
        model = ConsoleAssociation
        fields = [
            'association_id', 'uid', 'dist', 'reseller', 'client',
            'sales_date', 'sales_time'
        ]

class CustomerSerializer(serializers.ModelSerializer):
    client = ClientSerializer(read_only=True)

    class Meta:
        model = Customers
        fields = '__all__'

class VehicleSerializer(serializers.ModelSerializer):
    customer = CustomerSerializer(read_only=True)

    class Meta:
        model = Vehicles
        fields = '__all__'

class PumpsSerializer(serializers.ModelSerializer):
    uid = ConsoleSerializer(read_only=True)

    class Meta:
        model = Pumps
        fields = '__all__'

class AlarmsConfigSerializer(serializers.ModelSerializer):
    class Meta:
        model = AlarmsConfig
        fields = '__all__'

class StopMethodSerializer(serializers.ModelSerializer):
    """Serializer for stop method reference data."""
    class Meta:
        model = StopMethod
        fields = ['id']  # Only include id since 'name' doesn't exist in database

class ClientTransactionSerializer(serializers.ModelSerializer):
    site_name = serializers.CharField(read_only=True)
    product = serializers.SerializerMethodField()  # maps resolved name

    class Meta:
        model = ClientTransaction
        fields = "__all__"
        # All new fields (tank_name, stop_method, pulses, startDateTime, 
        # endDateTime, startDip, endDip) are included automatically

    def get_product(self, obj):
        return getattr(obj, "product_resolved", None) or obj.product

class VmiRecordSerializer(serializers.ModelSerializer):
    console_device_id = serializers.CharField(source='uid.device_id', read_only=True)
    site_name = serializers.CharField(read_only=True)
    product_name = serializers.CharField(read_only=True)
    state = serializers.SerializerMethodField()    
    state_icon = serializers.SerializerMethodField()
    
    class Meta:
        model = Tanks
        # List fields needed for VMI dashboard
        fields = [
            'tank_uid', 'tank_id', 'uid',         # PKs
            'console_device_id',                  # from Console.device_id
            'site_id', 'site_name',               # raw + annotated
            'Tank_name',                          # Tank name
            'capacity',                           # static info
            'current_volume', 'ullage',           # live values
            'current_percent',                    # %
            'dipr_date', 'dipr_time',             # last read
            'product_name', 'state', 'state_icon', # status
            'enabled',                            # tank enabled flag
            'water_volume', 'water_height',       # water detection
            'temperature',                        # temperature
            'updated_at',                         # last update timestamp
        ]
    
    def get_state(self, obj):
        """Return a canonical state code matching the old PHP ladder."""
        pct = obj.current_percent
        # 1. device disconnected
        if obj.dv_flag == 1:
            return "disconnected"

        # 2. console offline (>2 days)
        if ((not obj.last_conndate and obj.device_type != 201) or
                (obj.last_conndate and obj.last_conndate <= timezone.now().date() - timedelta(days=2))):
            return "offline"

        # 3. dip out‑of‑sync (>3 days)
        if not obj.dipr_date or obj.dipr_date <= timezone.now().date() - timedelta(days=3):
            return "dip_offline"

        # 4. alarms (only if enabled)
        if getattr(obj, "alarm_enable", 0) == 1:
            vol = float(obj.current_volume or 0)
            if getattr(obj, "crithigh_alarm", 0) and vol >= obj.crithigh_alarm:
                return "critical_high"
            if getattr(obj, "critlow_alarm", 0) and vol <= obj.critlow_alarm:
                return "critical_low"
            if getattr(obj, "high_alarm", 0) and vol >= obj.high_alarm:
                return "high"
            if getattr(obj, "low_alarm", 0) and vol <= obj.low_alarm:
                return "low"

        # 5. default
        if pct is None:
            return "offline"
        if pct < 5:
            return "critical_low"   # keep "critical" consistent
        if pct < 20:
            return "low"
        return "ok"

    ICONS = {
        "disconnected":  ("flag_dv_icon.png",   "A device has been disconnected"),
        "offline":       ("console_offline.png","Console Offline"),
        "dip_offline":   ("dip_offline.png",    "Dip Out‑of‑Sync"),
        "critical_high": ("crithigh_icon.png",  "Critical High Alarm"),
        "critical_low":  ("critlow_icon.png",   "Critical Low Alarm"),
        "high":          ("higha_icon.png",     "High Alarm"),
        "low":           ("lowa_icon.png",      "Low Alarm"),
        # "ok":            ("ok_icon.png",        "Normal"),
    }

    def get_state_icon(self, obj):
        code = self.get_state(obj)
        icon, tip = self.ICONS.get(code, ("", ""))
        return {"icon": icon, "tooltip": tip}

class SiteGroupSerializer(serializers.ModelSerializer):
    class Meta:
        model = SiteGroups
        fields = ("group_id", "group_name")

class ProductSerializer(serializers.ModelSerializer):
    id = serializers.IntegerField(source="product_id")
    name = serializers.CharField(source="product_name")

    class Meta:
        model = Products
        fields = ("id", "name", "product_colour")

class StrappingChartSerializer(serializers.ModelSerializer):
    class Meta:
        model = StrappingChart
        fields = ("chart_id", "client_id", "chart_name", "json_data")

class StrappingChartSlim(serializers.ModelSerializer):
    class Meta:
        model = StrappingChart
        fields = ("chart_id", "chart_name")          # list view

class StrappingChartFull(serializers.ModelSerializer):
    class Meta:
        model = StrappingChart
        fields = ("chart_id", "chart_name", "json_data")  # detail, create, update

class DipreadHistoricSerializer(serializers.ModelSerializer):
    """Serializer for historical dipread data."""
    site_name = serializers.CharField(read_only=True)
    tank_name = serializers.CharField(read_only=True)
    product_name = serializers.CharField(read_only=True)
    console_device_id = serializers.CharField(read_only=True)
    
    class Meta:
        model = DipreadHistoric
        fields = [
            'dipread_id',
            'uid',
            'console_device_id',
            'tank_id',
            'tank_name',
            'site_id',
            'site_name',
            'transaction_date',
            'transaction_time',
            'transaction_date_utc0',
            'transaction_time_utc0',
            'current_volume',
            'ullage',
            'temperature',
            'tc_volume',
            'volume_height',
            'water_volume',
            'water_height',
            'product_name',
        ]
