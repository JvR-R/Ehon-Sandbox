from django.contrib import admin
from django.urls import path, include

urlpatterns = [
    path('admin/', admin.site.urls),  # Handles /admin/
    path('', include('mqtt_client.urls')),  # Replace 'app' with your actual app name
]
