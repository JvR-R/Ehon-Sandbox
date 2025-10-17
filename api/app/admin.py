# app/admin.py

from django.contrib import admin
from .models import (
    Client, Reseller, Distributor, Console, ConsoleAssociation,
    Customers, Vehicles, Pumps, AlarmsConfig, Sites, Tanks
    # ... other models
)

admin.site.register(Client)
admin.site.register(Reseller)
admin.site.register(Distributor)
admin.site.register(Console)
admin.site.register(ConsoleAssociation)
admin.site.register(Customers)
admin.site.register(Vehicles)
admin.site.register(Pumps)
admin.site.register(AlarmsConfig)
admin.site.register(Sites)
admin.site.register(Tanks)
# ... register other models
