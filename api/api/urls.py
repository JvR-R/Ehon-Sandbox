# project/urls.py

from django.contrib import admin
from django.urls import path, include
from oauth2_provider import views as oauth2_views

urlpatterns = [
    path('admin/', admin.site.urls),
    path('o/', include('oauth2_provider.urls', namespace='oauth2_provider')),
    path('', include('app.urls')),  # Replace 'app' with your actual app name
    # ... other URL patterns ...
]
