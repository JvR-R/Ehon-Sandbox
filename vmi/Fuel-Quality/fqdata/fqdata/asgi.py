import os
from channels.routing import ProtocolTypeRouter, URLRouter
from channels.auth import AuthMiddlewareStack
from django.core.asgi import get_asgi_application
from django.urls import path
from updates.consumers import FuelQualityConsumer

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'fqdata.settings')

application = ProtocolTypeRouter({
    "http": get_asgi_application(),
    "websocket": AuthMiddlewareStack(
        URLRouter([
            path('ws/fuel-quality/', FuelQualityConsumer.as_asgi()),  # Ensure this matches the WebSocket URL
        ])
    ),
})
