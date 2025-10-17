# mqtt_client/urls.py
from django.urls import path
from . import views

urlpatterns = [
    path('', views.home, name='home'),
    path('update_pulse_rate/', views.update_pulse_rate, name='update_pulse_rate'),
    path('update_estop/', views.update_estop, name='update_estop'),
    path('system_state/', views.system_state, name='system_state'),
    path('update_tags/', views.update_tags_view, name='update_tags'),
    path('update_drivers/', views.update_drivers_view, name='update_drivers'),
    path('update_vehicles/', views.update_vehicles_view, name='update_vehicles'),
    path('start_transaction/', views.start_transaction, name='start_transaction'),
    path('update_tanks/', views.update_tanks_view, name='update_tanks'),
    path('update_pumps/', views.update_pumps_view, name='update_pumps'),
    path('update_tg/', views.update_tg_view, name='update_tg')
    # Add any other necessary URL patterns here
]
