# mqtt_client/admin.py

from django.contrib import admin
from .models import ClientTag

@admin.register(ClientTag)
class ClientTagAdmin(admin.ModelAdmin):
    list_display = ('id', 'client_id', 'customer_id', 'card_name', 'card_number', 'card_type', 'expiry_date')
    search_fields = ('client_id', 'customer_id', 'card_name', 'card_number')
    list_filter = ('card_type', 'enabled_prompt', 'pin_prompt', 'prompt_vehicle', 'driver_prompt', 'projectnum_prompt', 'odo_prompt')
