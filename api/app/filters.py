# app/filters.py - Custom filters for API endpoints

import django_filters as df
from .models import ClientTransaction, Sites, Tanks, ClientSiteGroups, DipreadHistoric
from django_filters import rest_framework as filters


class ClientTransactionFilter(df.FilterSet):
    """
    Custom filter for ClientTransaction model.
    
    Provides date range filtering and text search capabilities.
    """
    start_date = df.DateFilter(field_name='transaction_date', lookup_expr='gte', 
                                help_text="Filter transactions from this date (YYYY-MM-DD)")
    end_date = df.DateFilter(field_name='transaction_date', lookup_expr='lte',
                              help_text="Filter transactions until this date (YYYY-MM-DD)")
    uid = df.NumberFilter(field_name='uid', 
                           help_text="Filter by console UID")
    tank_id = df.NumberFilter(field_name='tank_id',
                               help_text="Filter by tank ID")
    card = df.CharFilter(field_name='card_number', lookup_expr='icontains',
                          help_text="Search by card number (partial match)")
    rego = df.CharFilter(field_name='registration', lookup_expr='icontains',
                          help_text="Search by vehicle registration (partial match)")
    site_name = df.CharFilter(field_name="site_name", lookup_expr="icontains",
                               help_text="Search by site name (partial match)")

    class Meta:
        model = ClientTransaction
        fields = []

    def filter_by_site_name(self, qs, field_name, value):
        """Filter by site name using relationship."""
        return qs.filter(site__site_name__icontains=value)


class VmiFilterSet(df.FilterSet):
    """
    Custom filter for VMI (Tanks) model.
    
    Provides filtering by site, product, group, and tank properties.
    """
    site_name = df.CharFilter(
        field_name="site_name",
        lookup_expr="icontains",
        help_text="Filter by site name (partial match)"
    )
    
    group = df.NumberFilter(
        method="filter_by_group",
        help_text="Filter by site group ID"
    )
    
    tank_id = df.NumberFilter(
        field_name="tank_id", 
        lookup_expr="exact",
        help_text="Filter by exact tank ID"
    )
    
    product_id = df.NumberFilter(
        field_name="product_id", 
        lookup_expr="exact",
        help_text="Filter by product/fuel type ID"
    )
    
    enabled = df.BooleanFilter(
        field_name="enabled",
        help_text="Filter by enabled status (true/false)"
    )
    
    Tank_name = df.CharFilter(
        field_name="Tank_name",
        lookup_expr="icontains",
        help_text="Search by tank name (partial match)"
    )

    class Meta:
        model = Tanks
        fields = ["site_name", "tank_id", "group", "product_id", "enabled", "Tank_name"]

    def filter_by_group(self, qs, name, value):
        """
        Filter tanks by site group.
        
        Uses ClientSiteGroups mapping table to find sites in the specified group.
        """
        site_ids = (
            ClientSiteGroups.objects
            .filter(group_id=value)
            .values_list("site_no", flat=True)
        )
        return qs.filter(site_id__in=site_ids)


class DipreadHistoricFilter(df.FilterSet):
    """
    Custom filter for DipreadHistoric model.
    
    Provides date range filtering and tank/site search capabilities.
    """
    start_date = df.DateFilter(field_name='transaction_date', lookup_expr='gte',
                                help_text="Filter dipread from this date (YYYY-MM-DD)")
    end_date = df.DateFilter(field_name='transaction_date', lookup_expr='lte',
                              help_text="Filter dipread until this date (YYYY-MM-DD)")
    uid = df.NumberFilter(field_name='uid',
                           help_text="Filter by console UID")
    tank_id = df.NumberFilter(field_name='tank_id',
                               help_text="Filter by tank ID")
    site_id = df.NumberFilter(field_name='site_id',
                               help_text="Filter by site ID")
    site_name = df.CharFilter(field_name="site_name", lookup_expr="icontains",
                               help_text="Search by site name (partial match)")
    tank_name = df.CharFilter(field_name="tank_name", lookup_expr="icontains",
                               help_text="Search by tank name (partial match)")

    class Meta:
        model = DipreadHistoric
        fields = []

