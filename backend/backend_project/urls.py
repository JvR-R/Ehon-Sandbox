# backend_project/urls.py
from django.contrib import admin
from django.urls import path
from ingest.views_gateway import send_gateway_cmd

urlpatterns = [
    path("admin/", admin.site.urls),

    # ONE line is enough
    path("gateway/command/", send_gateway_cmd, name="gateway-command"),
]
