# backend_project/urls.py
from django.contrib import admin
from django.urls import path
from ingest.views_gateway import send_gateway_cmd
from ingest.views_fms import send_fms_cmd

urlpatterns = [
    path("admin/", admin.site.urls),

    # Gateway and FMS command endpoints
    path("gateway/command/", send_gateway_cmd, name="gateway-command"),
    path("fms/command/", send_fms_cmd, name="fms-command"),
]
