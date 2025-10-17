# app/urls.py - URL routing configuration

from rest_framework.routers import DefaultRouter
from django.urls import path, include
from .views import (
    # Company Management
    ConsoleAssociationViewSet, ClientViewSet,
    ResellerViewSet, DistributorViewSet,
    
    # Hardware
    ConsoleViewSet, SitesViewSet, TanksViewSet, 
    PumpsViewSet, AlarmsConfigViewSet,
    
    # Fleet Management
    CustomerViewSet, VehicleViewSet,
    
    # Monitoring & Analytics
    VmiViewSet, ClientTransactionList, DipreadHistoricList,
    
    # Reference Data
    SiteGroupViewSet, ProductViewSet,
    
    # Calibration
    StrappingChartViewSet,
    
    # Custom Views
    ConsoleAssociationDetail,
    AllConsolesSiteInfo,
    ClientDetailsByConsoleUID,
    central_oauth_callback,
)
from .auth_views import LoginView, RefreshTokenView, LogoutView

# Initialize router
router = DefaultRouter()

# Company Management
router.register(r'console-associations', ConsoleAssociationViewSet, basename='console-association')
router.register(r'clients', ClientViewSet, basename='client')
router.register(r'resellers', ResellerViewSet, basename='reseller')
router.register(r'distributors', DistributorViewSet, basename='distributor')

# Hardware
router.register(r'consoles', ConsoleViewSet, basename='console')
router.register(r'sites', SitesViewSet, basename='site')
router.register(r'tanks', TanksViewSet, basename='tank')
router.register(r'pumps', PumpsViewSet, basename='pump')
router.register(r'alarms-config', AlarmsConfigViewSet, basename='alarm-config')

# Fleet Management
router.register(r'customers', CustomerViewSet, basename='customer')
router.register(r'vehicles', VehicleViewSet, basename='vehicle')

# Monitoring & Analytics (NEW)
router.register(r'vmi', VmiViewSet, basename='vmi')

# Reference Data (NEW)
router.register(r'groups', SiteGroupViewSet, basename='group')
router.register(r'products', ProductViewSet, basename='product')

# Calibration (NEW)
router.register(r'strapping-charts', StrappingChartViewSet, basename='strapping-chart')

# Custom URL patterns
urlpatterns = [
    # Router URLs (all ViewSets)
    path('', include(router.urls)),
    
    # Authentication Endpoints (NEW)
    path('auth/login/', LoginView.as_view(), name='api-login'),
    path('auth/refresh/', RefreshTokenView.as_view(), name='api-refresh'),
    path('auth/logout/', LogoutView.as_view(), name='api-logout'),
    
    # OAuth Callback
    path('oauth/callback/', central_oauth_callback, name='central_oauth_callback'),
    
    # Console Info
    path('all-consoles/', AllConsolesSiteInfo.as_view(), name='all-consoles-site-info'),
    path('console-association/<int:uid>/', ConsoleAssociationDetail.as_view(), name='console-association-detail'),
    path('client-details/<int:uid>/', ClientDetailsByConsoleUID.as_view(), name='client-details-by-console'),
    
    # Transactions (NEW) - Using ListAPIView, not ViewSet
    path('transactions/', ClientTransactionList.as_view(), name='transaction-list'),
    
    # Dipread Historic (NEW) - Historical tank level readings
    path('dipread/', DipreadHistoricList.as_view(), name='dipread-list'),
]
