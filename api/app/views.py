# app/views.py - VMI Fuel Management System API Views

from rest_framework import viewsets, status, generics, filters
from rest_framework.exceptions import ValidationError
from rest_framework.decorators import action
from django_filters.rest_framework import DjangoFilterBackend
from rest_framework.views import APIView
from rest_framework.response import Response
from rest_framework.permissions import IsAuthenticated
from django.shortcuts import redirect
from django.conf import settings
from django.utils import timezone
from django.http import HttpResponseBadRequest
from django.db.models import QuerySet, F, Subquery, OuterRef, Value, CharField
from django.db.models.functions import Coalesce
from oauth2_provider.models import AccessToken, Application, Grant
from datetime import timedelta
import secrets
import logging
from typing import Any, Optional

# Configure logger
logger = logging.getLogger(__name__)

from .filters import ClientTransactionFilter, VmiFilterSet, DipreadHistoricFilter
from .models import (
    ConsoleAssociation, Client, Reseller, Distributor,
    Console, Customers, Vehicles, Pumps, AlarmsConfig,
    Sites, Tanks, ClientTransaction, UserScope, DipreadHistoric, 
    SiteGroups, Products, StrappingChart
)
from .serializers import (
    ConsoleAssociationSerializer, ClientSerializer,
    ResellerSerializer, DistributorSerializer, ConsoleSerializer,
    CustomerSerializer, VehicleSerializer, PumpsSerializer,
    AlarmsConfigSerializer, SitesSerializer, TanksSerializer,
    ClientTransactionSerializer, VmiRecordSerializer, SiteGroupSerializer,
    ProductSerializer, StrappingChartSerializer, StrappingChartSlim, StrappingChartFull,
    DipreadHistoricSerializer
)
from api.pagination import StandardResultsSetPagination

# ===============================
# Console Association Views
# ===============================

class ConsoleAssociationViewSet(viewsets.ModelViewSet):
    """
    CRUD operations for console associations.
    Links consoles to distributors, resellers, and clients.
    """
    queryset = ConsoleAssociation.objects.all()
    serializer_class = ConsoleAssociationSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['uid__device_id', 'client__client_name']
    ordering_fields = ['sales_date', 'sales_time']


class ConsoleAssociationDetail(APIView):
    """
    Retrieve Console Association details by Console UID.
    
    **Endpoint:** `GET /api/console-association/{uid}/`
    **Authentication:** Required
    """
    permission_classes = [IsAuthenticated]

    def get(self, request, uid: str) -> Response:
        try:
            console_assoc = ConsoleAssociation.objects.select_related('client').get(uid=uid)
            serializer = ConsoleAssociationSerializer(console_assoc)
            logger.info(f"Retrieved console association for UID: {uid}")
            return Response(serializer.data, status=status.HTTP_200_OK)
        except ConsoleAssociation.DoesNotExist:
            logger.warning(f"Console association not found for UID: {uid}")
            return Response(
                {"error": "ConsoleAssociation with the given uid not found."},
                status=status.HTTP_404_NOT_FOUND
            )
        except Exception as e:
            logger.error(f"Error retrieving console association for UID {uid}: {str(e)}")
            return Response(
                {"error": "An error occurred while retrieving console association"},
                status=status.HTTP_500_INTERNAL_SERVER_ERROR
            )

# ===============================
# Company Management ViewSets
# ===============================

class ClientViewSet(viewsets.ModelViewSet):
    """Client company management."""
    queryset = Client.objects.all()
    serializer_class = ClientSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['client_name', 'client_email']
    ordering_fields = ['client_name']


class ResellerViewSet(viewsets.ModelViewSet):
    """Reseller company management."""
    queryset = Reseller.objects.all()
    serializer_class = ResellerSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['reseller_name', 'reseller_email']
    ordering_fields = ['reseller_name']


class DistributorViewSet(viewsets.ModelViewSet):
    """Distributor company management."""
    queryset = Distributor.objects.all()
    serializer_class = DistributorSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['dist_name', 'dist_email']
    ordering_fields = ['dist_name']

# ===============================
# Hardware ViewSets
# ===============================

class ConsoleViewSet(viewsets.ModelViewSet):
    """Console/gateway device management."""
    queryset = Console.objects.all()
    serializer_class = ConsoleSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['device_id', 'console_ip', 'console_imei']
    ordering_fields = ['device_id', 'console_status', 'last_conndate']

    def get_queryset(self) -> QuerySet:
        """Filter consoles based on user's role and company scope."""
        base = super().get_queryset()
        scope = getattr(self.request.user, "userscope", None)

        if scope is None:
            logger.warning(f"User {self.request.user.username} has no UserScope defined")
            return base.none()
        
        if scope.role in {"OWNER", "ADMIN"}:
            logger.debug(f"Full console access granted to {self.request.user.username}")
            return base

        # Filter by company association
        ca = ConsoleAssociation.objects
        match scope.role:
            case "DIST":
                allowed = ca.filter(dist=scope.company_id).values("uid_id")
            case "RESELLER":
                allowed = ca.filter(reseller=scope.company_id).values("uid_id")
            case "CLIENT":
                allowed = ca.filter(client=scope.company_id).values("uid_id")
            case _:
                logger.warning(f"Unknown role '{scope.role}' for user {self.request.user.username}")
                return base.none()

        filtered = base.filter(uid__in=Subquery(allowed))
        logger.debug(f"Filtered consoles for {self.request.user.username} (role: {scope.role}): {filtered.count()} consoles")
        return filtered


class SitesViewSet(viewsets.ModelViewSet):
    """Site/location management."""
    queryset = Sites.objects.all()
    serializer_class = SitesSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['site_name', 'site_city', 'site_address']
    ordering_fields = ['site_name', 'client']

    def get_queryset(self) -> QuerySet:
        """Filter sites based on user's role and company scope."""
        base = super().get_queryset()
        scope = getattr(self.request.user, "userscope", None)

        if scope is None:
            return base.none()
        
        if scope.role in {"OWNER", "ADMIN"}:
            return base

        # Filter by console associations
        ca = ConsoleAssociation.objects
        match scope.role:
            case "DIST":
                allowed = ca.filter(dist=scope.company_id).values("uid_id")
            case "RESELLER":
                allowed = ca.filter(reseller=scope.company_id).values("uid_id")
            case "CLIENT":
                allowed = ca.filter(client=scope.company_id).values("uid_id")
            case _:
                return base.none()

        return base.filter(uid_id__in=Subquery(allowed))


class TanksViewSet(viewsets.ModelViewSet):
    """Basic tank management (use VmiViewSet for monitoring)."""
    queryset = Tanks.objects.all()
    serializer_class = TanksSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['Tank_name', 'tank_id']
    ordering_fields = ['tank_id', 'capacity', 'enabled']

    def get_queryset(self) -> QuerySet:
        """Filter tanks based on user's role and company scope."""
        base = super().get_queryset()
        scope = getattr(self.request.user, "userscope", None)

        if scope is None:
            return base.none()
        
        if scope.role in {"OWNER", "ADMIN"}:
            return base

        # Filter by console associations
        ca = ConsoleAssociation.objects
        match scope.role:
            case "DIST":
                allowed = ca.filter(dist=scope.company_id).values("uid_id")
            case "RESELLER":
                allowed = ca.filter(reseller=scope.company_id).values("uid_id")
            case "CLIENT":
                allowed = ca.filter(client=scope.company_id).values("uid_id")
            case _:
                return base.none()

        return base.filter(uid_id__in=Subquery(allowed))


class PumpsViewSet(viewsets.ModelViewSet):
    """Pump/nozzle configuration management."""
    queryset = Pumps.objects.all()
    serializer_class = PumpsSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['nozzle_product']
    ordering_fields = ['nozzle_number', 'pulse_rate']

    def get_queryset(self) -> QuerySet:
        """Filter pumps based on user's role and company scope."""
        base = super().get_queryset()
        scope = getattr(self.request.user, "userscope", None)

        if scope is None:
            return base.none()
        
        if scope.role in {"OWNER", "ADMIN"}:
            return base

        # Filter by console associations
        ca = ConsoleAssociation.objects
        match scope.role:
            case "DIST":
                allowed = ca.filter(dist=scope.company_id).values("uid_id")
            case "RESELLER":
                allowed = ca.filter(reseller=scope.company_id).values("uid_id")
            case "CLIENT":
                allowed = ca.filter(client=scope.company_id).values("uid_id")
            case _:
                return base.none()

        return base.filter(uid_id__in=Subquery(allowed))


class AlarmsConfigViewSet(viewsets.ModelViewSet):
    """Tank alarm configuration management."""
    queryset = AlarmsConfig.objects.all()
    serializer_class = AlarmsConfigSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['alarm_id']
    ordering_fields = ['high_alarm', 'low_alarm']

    def get_queryset(self) -> QuerySet:
        """Filter alarm configs based on user's role and company scope."""
        base = super().get_queryset()
        scope = getattr(self.request.user, "userscope", None)

        if scope is None:
            return base.none()
        
        if scope.role in {"OWNER", "ADMIN"}:
            return base

        # Filter by console associations
        ca = ConsoleAssociation.objects
        match scope.role:
            case "DIST":
                allowed = ca.filter(dist=scope.company_id).values("uid_id")
            case "RESELLER":
                allowed = ca.filter(reseller=scope.company_id).values("uid_id")
            case "CLIENT":
                allowed = ca.filter(client=scope.company_id).values("uid_id")
            case _:
                return base.none()

        return base.filter(uid_id__in=Subquery(allowed))

# ===============================
# Fleet Management ViewSets
# ===============================

class CustomerViewSet(viewsets.ModelViewSet):
    """Customer management."""
    queryset = Customers.objects.all()
    serializer_class = CustomerSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['customer_name', 'customer_email', 'customer_city']
    ordering_fields = ['customer_name', 'customer_city']


class VehicleViewSet(viewsets.ModelViewSet):
    """Vehicle fleet management."""
    queryset = Vehicles.objects.all()
    serializer_class = VehicleSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ['vehicle_assetnumber', 'vehicle_rego', 'vehicle_brand', 'vehicle_model']
    ordering_fields = ['vehicle_rego_date', 'vehicle_service_km']

# ===============================
# VMI (Vendor Managed Inventory) ViewSet
# ===============================

class VmiViewSet(viewsets.ReadOnlyModelViewSet):
    """
    Vendor Managed Inventory - Tank monitoring and management.
    
    **Endpoints:**
    - `GET /api/vmi/` - List all tanks (filtered by user scope)
    - `GET /api/vmi/{id}/` - Retrieve specific tank details
    - `GET /api/vmi/{id}/details/?case={1-5}` - Get detailed information
    
    **Query Parameters:**
    - `site_name`: Filter by site name (partial match)
    - `tank_id`: Filter by specific tank ID
    - `product_id`: Filter by product/fuel type
    - `group`: Filter by site group ID
    - `enabled`: Filter by enabled status
    - `ordering`: Sort field (e.g., `-dipr_date`, `current_volume`)
    - `search`: Search across site names
    - `page`: Page number for pagination
    """

    queryset = (
        Tanks.objects.annotate(
            console_device_id=F("uid__device_id"),
            site_name=Coalesce(
                Subquery(
                    Sites.objects
                         .filter(uid=OuterRef("uid"),
                                 site_id=OuterRef("site_id"))
                         .values("site_name")[:1]
                ),
                Value("", output_field=CharField()),
            ),
            product_name=Subquery(
                Products.objects
                        .filter(product_id=OuterRef("product_id"))
                        .values("product_name")[:1]
            ),
            dv_flag=F("uid__dv_flag"),
            last_conndate=F("uid__last_conndate"),
            device_type=F("uid__device_type"),
        )
    )

    serializer_class = VmiRecordSerializer
    pagination_class = StandardResultsSetPagination
    filter_backends = [DjangoFilterBackend, filters.OrderingFilter, filters.SearchFilter]
    filterset_class = VmiFilterSet
    ordering_fields = ["current_volume", "site_name", "dipr_date", "current_percent", "Tank_name"]
    search_fields = ["site_name", "Tank_name"]

    def get_queryset(self) -> QuerySet:
        """Filter tanks based on user's role and company scope."""
        base = super().get_queryset()
        scope = getattr(self.request.user, "userscope", None)

        if scope is None:
            logger.warning(f"User {self.request.user.username} has no UserScope defined")
            return base.none()
        
        if scope.role in {"OWNER", "ADMIN"}:
            logger.debug(f"Full access granted to {self.request.user.username}")
            return base

        ca = ConsoleAssociation.objects
        match scope.role:
            case "DIST":
                allowed = ca.filter(dist=scope.company_id).values("uid_id")
            case "RESELLER":
                allowed = ca.filter(reseller=scope.company_id).values("uid_id")
            case "CLIENT":
                allowed = ca.filter(client=scope.company_id).values("uid_id")
            case _:
                logger.warning(f"Unknown role '{scope.role}' for user {self.request.user.username}")
                return base.none()

        filtered = base.filter(uid_id__in=Subquery(allowed))
        logger.debug(f"Filtered tanks for {self.request.user.username} (role: {scope.role})")
        return filtered

    @action(detail=True, methods=["get"], url_path="details")
    def details(self, request, pk=None):
        """
        Returns detailed information for one tank based on `case` parameter:
        
        - case=1: Basic tank info
        - case=2: Alarm configuration
        - case=3: Active alerts (coming soon)
        - case=4: Historical volume chart
        - case=5: Recent transactions
        """
        record = self.get_object()
        case = int(request.query_params.get("case", 1))

        # Case 1: Basic info
        if case == 1:
            payload = {
                "tank_id": record.tank_id,
                "Tank_name": record.Tank_name,
                "console_uid": record.uid_id,
                "capacity": record.capacity,
                "current_volume": str(record.current_volume),
                "ullage": str(record.ullage),
                "current_percent": record.current_percent,
                "enabled": record.enabled,
                "water_volume": str(record.water_volume) if record.water_volume else None,
                "water_height": str(record.water_height) if record.water_height else None,
                "last_read": {
                    "date": record.dipr_date,
                    "time": record.dipr_time,
                },
            }
            return Response(payload)

        # Case 2: Alarm config
        if case == 2:
            alarm = (AlarmsConfig.objects
                     .filter(uid=record.uid_id, tank_id=record.tank_id)
                     .first())
            if alarm:
                from .serializers import AlarmsConfigSerializer
            return Response(AlarmsConfigSerializer(alarm).data)
            return Response({"message": "No alarm configuration found"}, status=404)

        # Case 3: Active alerts
        if case == 3:
            logger.warning(f"Active alerts requested for tank {record.tank_id} - feature not yet implemented")
            return Response({"alerts": [], "message": "Active alerts feature coming soon"}, status=200)

        # Case 4: Volume history chart
        if case == 4:
            try:
                days = int(request.query_params.get("days", 365))
                days = max(1, min(days, 3650))  # Limit: 1-3650 days
                cutoff = timezone.now().date() - timedelta(days=days)

                raw = (DipreadHistoric.objects
                      .filter(uid=record.uid_id,
                              tank_id=record.tank_id,
                              transaction_date__gte=cutoff)
                      .values("transaction_date", "transaction_time", "current_volume")
                      .order_by("transaction_date"))

                day_data = {}
                for row in raw:
                    day = row["transaction_date"].isoformat()
                    ts = f"{day}T{row['transaction_time'].isoformat()}"
                    vol = float(row["current_volume"])

                    d = day_data.setdefault(day, {"low": (vol, ts), "high": (vol, ts)})
                    if vol < d["low"][0]:
                        d["low"] = (vol, ts)
                    if vol > d["high"][0]:
                        d["high"] = (vol, ts)

                labels, datapoints = [], []
                for day in sorted(day_data):
                    low_val, low_ts = day_data[day]["low"]
                    high_val, high_ts = day_data[day]["high"]

                    if low_ts == high_ts or low_val == high_val:
                        labels.append(low_ts)
                        datapoints.append(low_val)
                    else:
                        if low_ts < high_ts:
                            labels.extend([low_ts, high_ts])
                            datapoints.extend([low_val, high_val])
                        else:
                            labels.extend([high_ts, low_ts])
                            datapoints.extend([high_val, low_val])

                logger.info(f"Generated volume chart for tank {record.tank_id} with {len(datapoints)} points")
                return Response({
                    "labels": labels,
                    "datasets": [{
                        "label": "Level (L)",
                        "data": datapoints,
                        "borderColor": "#3b82f6",
                        "pointRadius": 0,
                        "cubicInterpolationMode": "monotone",
                    }],
                })
            except Exception as e:
                logger.error(f"Error generating chart for tank {record.tank_id}: {str(e)}")
                return Response({"error": "Failed to generate volume chart"}, status=500)

        # Case 5: Recent transactions
        if case == 5:
            qs = (ClientTransaction.objects
                .filter(uid=record.uid_id, tank_id=record.tank_id)
                 .order_by("-transaction_date", "-transaction_time")[:10])
            return Response(ClientTransactionSerializer(qs, many=True).data)

        return Response({"error": "Invalid case parameter"}, status=400)

# ===============================
# Transaction ViewSet
# ===============================

class ClientTransactionList(generics.ListAPIView):
    """
    List and filter fuel dispensing transactions.
    
    **Query Parameters:**
    - `start_date`: From date (YYYY-MM-DD)
    - `end_date`: To date (YYYY-MM-DD)
    - `uid`: Console UID
    - `tank_id`: Tank ID
    - `card`: Card number (partial)
    - `rego`: Registration (partial)
    - `site_name`: Site name (partial)
    - `ordering`: Sort field
    """
    serializer_class = ClientTransactionSerializer
    pagination_class = StandardResultsSetPagination
    filter_backends = [DjangoFilterBackend, filters.OrderingFilter, filters.SearchFilter]
    filterset_class = ClientTransactionFilter
    queryset = ClientTransaction.objects.all()

    def get_queryset(self) -> QuerySet:
        """Filter transactions based on user scope."""
        try:
            # Site name annotation - match on both uid and site_id
            site_sq = (Sites.objects
                      .filter(uid=OuterRef("uid"), site_id=OuterRef("site_id"))
                      .values("site_name")[:1])

            # Select related for uid and annotate with site name
            base = (ClientTransaction.objects
                   .select_related("uid")
                   .annotate(site_name=Coalesce(Subquery(site_sq), Value(""))))

            scope = getattr(self.request.user, "userscope", None)
            if scope is None:
                logger.warning(f"User {self.request.user.username} has no UserScope for transactions")
                return base.none()
            
            if scope.role in {"OWNER", "ADMIN"}:
                logger.debug(f"Full transaction access for {self.request.user.username}")
                return base

            ca = ConsoleAssociation.objects
            match scope.role:
                case "DIST":
                    allowed = ca.filter(dist=scope.company_id).values("uid_id")
                case "RESELLER":
                    allowed = ca.filter(reseller=scope.company_id).values("uid_id")
                case "CLIENT":
                    allowed = ca.filter(client=scope.company_id).values("uid_id")
                case _:
                    logger.warning(f"Unknown role {scope.role} for {self.request.user.username}")
                    return base.none()

            filtered = base.filter(uid_id__in=Subquery(allowed))
            logger.debug(f"Filtered transactions for {self.request.user.username} (role: {scope.role})")
            return filtered
            
        except Exception as e:
            logger.error(f"Error in ClientTransactionList.get_queryset: {str(e)}", exc_info=True)
            return ClientTransaction.objects.none()

# ===============================
# Dipread Historic ViewSet
# ===============================

class DipreadHistoricList(generics.ListAPIView):
    """
    List and filter historical dipread (tank level) data.
    
    **Query Parameters:**
    - `start_date`: From date (YYYY-MM-DD)
    - `end_date`: To date (YYYY-MM-DD)
    - `uid`: Console UID
    - `tank_id`: Tank ID
    - `site_id`: Site ID
    - `site_name`: Site name (partial)
    - `tank_name`: Tank name (partial)
    - `ordering`: Sort field (e.g., `-transaction_date`)
    """
    serializer_class = DipreadHistoricSerializer
    pagination_class = StandardResultsSetPagination
    filter_backends = [DjangoFilterBackend, filters.OrderingFilter, filters.SearchFilter]
    filterset_class = DipreadHistoricFilter
    ordering_fields = ['transaction_date', 'transaction_time', 'current_volume', 'tank_id']
    search_fields = ['site_name', 'tank_name']
    queryset = DipreadHistoric.objects.all()

    def get_queryset(self) -> QuerySet:
        """Filter dipread data based on user scope."""
        try:
            # Annotate with console device_id, tank_name, and product_name
            from .models import Console, Tanks, Products
            
            console_sq = (Console.objects
                         .filter(uid=OuterRef("uid"))
                         .values("device_id")[:1])
            
            tank_sq = (Tanks.objects
                      .filter(uid=OuterRef("uid"), tank_id=OuterRef("tank_id"))
                      .values("Tank_name")[:1])
            
            product_sq = (Products.objects
                         .filter(product_id=Subquery(
                             Tanks.objects
                             .filter(uid=OuterRef("uid"), tank_id=OuterRef("tank_id"))
                             .values("product_id")[:1]
                         ))
                         .values("product_name")[:1])
            
            base = (DipreadHistoric.objects
                   .annotate(
                       console_device_id=Coalesce(Subquery(console_sq), Value("")),
                       tank_name=Coalesce(Subquery(tank_sq), Value("")),
                       product_name=Coalesce(Subquery(product_sq), Value(""))
                   ))

            scope = getattr(self.request.user, "userscope", None)
            if scope is None:
                logger.warning(f"User {self.request.user.username} has no UserScope for dipread")
                return base.none()
            
            if scope.role in {"OWNER", "ADMIN"}:
                logger.debug(f"Full dipread access for {self.request.user.username}")
                return base

            ca = ConsoleAssociation.objects
            match scope.role:
                case "DIST":
                    allowed = ca.filter(dist=scope.company_id).values("uid_id")
                case "RESELLER":
                    allowed = ca.filter(reseller=scope.company_id).values("uid_id")
                case "CLIENT":
                    allowed = ca.filter(client=scope.company_id).values("uid_id")
                case _:
                    logger.warning(f"Unknown role {scope.role} for {self.request.user.username}")
                    return base.none()

            filtered = base.filter(uid__in=Subquery(allowed))
            logger.debug(f"Filtered dipread for {self.request.user.username} (role: {scope.role})")
            return filtered
            
        except Exception as e:
            logger.error(f"Error in DipreadHistoricList.get_queryset: {str(e)}", exc_info=True)
            return DipreadHistoric.objects.none()

# ===============================
# Reference Data ViewSets
# ===============================

class SiteGroupViewSet(viewsets.ReadOnlyModelViewSet):
    """Site group reference data for filtering."""
    queryset = SiteGroups.objects.order_by("group_name")
    serializer_class = SiteGroupSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ["group_name"]
    ordering_fields = ["group_name"]


class ProductViewSet(viewsets.ReadOnlyModelViewSet):
    """Product/fuel type reference data."""
    queryset = Products.objects.all().order_by("product_name")
    serializer_class = ProductSerializer
    permission_classes = [IsAuthenticated]
    filter_backends = [filters.SearchFilter, filters.OrderingFilter]
    search_fields = ["product_name"]
    ordering_fields = ["product_name"]
    pagination_class = None  # Return all products unpaginated

# ===============================
# Strapping Charts ViewSet
# ===============================

class StrappingChartViewSet(viewsets.ModelViewSet):
    """
    Tank calibration chart management (CRUD + CSV upload).
    
    **Endpoints:**
    - `GET /api/strapping-charts/` - List
    - `POST /api/strapping-charts/` - Create (JSON)
    - `POST /api/strapping-charts/manual/` - Create empty chart
    - `POST /api/strapping-charts/csv/` - Create from CSV
    - `PUT /api/strapping-charts/{id}/csv/` - Update from CSV
    """
    queryset = StrappingChart.objects.all()
    serializer_class = StrappingChartSerializer
    permission_classes = [IsAuthenticated]
    pagination_class = None

    def get_queryset(self):
        """Filter by user scope."""
        scope = getattr(self.request.user, "userscope", None)
        if scope is None or scope.role in {"OWNER", "ADMIN"}:
            return super().get_queryset()
        return super().get_queryset().filter(client_id=scope.company_id)

    def perform_create(self, serializer):
        """Set client_id on create."""
        scope = getattr(self.request.user, "userscope", None)
        company_id = getattr(scope, "company_id", None)
        if not company_id:
            raise ValidationError({"detail": "Unable to determine company scope"})
        serializer.save(client_id=company_id)

    @action(detail=False, methods=["post"])
    def manual(self, request):
        """Create empty chart with specified point count."""
        name = request.data.get("name")
        try:
            points = int(request.data.get("points", 0))
        except (ValueError, TypeError):
            return Response({"error": "Points must be a valid integer"}, status=400)

        if not name or points < 2:
            return Response({"error": "Name and points ≥ 2 required"}, status=400)

        try:
            chart = StrappingChart.objects.create(
                    client_id=request.user.userscope.company_id,
                    chart_name=name,
                    json_data="[]",
                )
            logger.info(f"Created manual chart {chart.chart_id}: {name}")
            return Response({"id": chart.chart_id}, status=201)
        except Exception as e:
            logger.error(f"Error creating manual chart: {str(e)}")
            return Response({"error": "Failed to create chart"}, status=500)

    @action(detail=False, methods=["post"])
    def csv(self, request):
        """Create chart from CSV upload."""
        f = request.FILES.get("file")
        if not f:
            return Response({"error": "CSV file missing"}, status=400)

        try:
            name = f.name.rsplit(".", 1)[0]
            chart = StrappingChart.objects.create(
                    client_id=request.user.userscope.company_id,
                    chart_name=name,
                    json_data=f.read().decode(),
                )
            logger.info(f"Created chart {chart.chart_id} from CSV: {name}")
            return Response({"id": chart.chart_id}, status=201)
        except Exception as e:
            logger.error(f"Error creating chart from CSV: {str(e)}")
            return Response({"error": "Failed to create chart from CSV"}, status=500)

    @action(detail=True, methods=["put"], url_path="csv")
    def update_csv(self, request, pk=None):
        """Update chart data from CSV upload."""
        chart = self.get_object()
        f = request.FILES.get("file")
        if not f:
            return Response({"error": "CSV file missing"}, status=400)

        try:
            chart.json_data = f.read().decode()
            chart.save(update_fields=["json_data"])
            logger.info(f"Updated chart {pk} with new CSV data")
            return Response(status=204)
        except Exception as e:
            logger.error(f"Error updating chart {pk}: {str(e)}")
            return Response({"error": "Failed to update chart"}, status=500)

    def get_serializer_class(self):
        """Use slim serializer for list, full for detail."""
        return StrappingChartSlim if self.action == "list" else StrappingChartFull

# ===============================
# Custom Helper Views
# ===============================

class AllConsolesSiteInfo(APIView):
    """List all consoles with associated site information."""
    permission_classes = [IsAuthenticated]

    def get(self, request, format=None):
        consoles = Console.objects.all()
        results = []

        for console in consoles:
            try:
                site = Sites.objects.filter(uid=console).first()
                site_name = site.site_name if site else None
            except Exception:
                site_name = None

            results.append({
                "console_id": console.uid,
                "device_id": console.device_id,
                "last_conndate": console.last_conndate,
                "last_conntime": console.last_conntime,
                "site_name": site_name,
                "console_status": console.console_status,
                "service_flag": console.service_flag,
            })

        return Response(results, status=status.HTTP_200_OK)


class ClientDetailsByConsoleUID(APIView):
    """Get client details associated with a console UID."""
    permission_classes = [IsAuthenticated]

    def get(self, request, uid, format=None):
        associations = ConsoleAssociation.objects.filter(uid=uid).select_related('client')
        if not associations.exists():
            return Response(
                {"error": "ConsoleAssociation with the given UID not found."},
                status=status.HTTP_404_NOT_FOUND
            )

        clients = []
        seen = set()
        for assoc in associations:
            if assoc.client and assoc.client.pk not in seen:
                seen.add(assoc.client.pk)
                clients.append({
                    "client_name": assoc.client.client_name,
                    "client_address": assoc.client.client_address,
                    "client_email": assoc.client.client_email,
                    "client_phone": assoc.client.client_phone,
                })

        if not clients:
            return Response(
                {"error": "No client associated with this console."},
                status=status.HTTP_404_NOT_FOUND
            )
        return Response(clients, status=status.HTTP_200_OK)

# ===============================
# OAuth Callback Handler
# ===============================

def central_oauth_callback(request):
    """
    OAuth2 callback handler for central authentication system.
    
    Handles the authorization code exchange and token generation.
    """
    client_id = request.GET.get('client_id')
    state = request.GET.get('state')
    code = request.GET.get('code')

    if not client_id or not code or not state:
        return HttpResponseBadRequest("Missing required parameters.")

    # Validate the state parameter (assumes it was stored in the session)
    stored_state = request.session.get('oauth_state')
    if state != stored_state:
        logger.warning(f"Invalid OAuth state parameter for client {client_id}")
        return HttpResponseBadRequest("Invalid state parameter.")

    try:
        application = Application.objects.get(client_id=client_id)
    except Application.DoesNotExist:
        logger.error(f"Invalid OAuth client_id: {client_id}")
        return redirect(f"{settings.CLIENT_ERROR_URL}?error=invalid_client")

    # Use the built-in Grant model to fetch the authorization code record
    try:
        grant = Grant.objects.get(code=code, application=application)
    except Grant.DoesNotExist:
        logger.warning(f"Invalid or expired authorization code for client {client_id}")
        return HttpResponseBadRequest("Invalid or expired authorization code.")

    # Check if the authorization code has expired
    if grant.expires < timezone.now():
        logger.warning(f"Authorization code expired for client {client_id}")
        return HttpResponseBadRequest("Authorization code expired.")

    # Delete the grant so it cannot be reused (or mark it as used)
    grant.delete()

    # Use the user associated with the grant (this should be set during the authorization step)
    user = grant.user
    if not user:
        logger.error(f"No user associated with authorization code for client {client_id}")
        return HttpResponseBadRequest("No user associated with the authorization code.")

    # Generate the access token
    access_token_str = secrets.token_hex(30)
    expires = timezone.now() + timedelta(seconds=settings.OAUTH2_PROVIDER['ACCESS_TOKEN_EXPIRE_SECONDS'])
    token = AccessToken.objects.create(
        user=user,
        token=access_token_str,
        application=application,
        expires=expires,
        scope='read write',
    )

    logger.info(f"OAuth token generated for user {user.username}, client {client_id}")

    # Redirect back to the client with the token (ensure CLIENT_REDIRECT_URL is securely configured)
    redirect_uri = settings.CLIENT_REDIRECT_URL
    redirect_url = f"{redirect_uri}?access_token={access_token_str}&token_type=Bearer&expires_in={settings.OAUTH2_PROVIDER['ACCESS_TOKEN_EXPIRE_SECONDS']}"
    return redirect(redirect_url)

# ===============================
# End of views.py
# ===============================
