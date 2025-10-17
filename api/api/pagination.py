# api/pagination.py - Custom pagination classes

from rest_framework.pagination import PageNumberPagination
from rest_framework.response import Response


class StandardResultsSetPagination(PageNumberPagination):
    """
    Standard pagination for API list endpoints.
    
    Default: 100 items per page
    Max: 1000 items per page
    
    Query parameters:
    - page: Page number (default: 1)
    - page_size: Items per page (default: 100, max: 1000)
    
    Response format:
    {
        "count": 1250,
        "next": "http://api.example.com/endpoint/?page=2",
        "previous": null,
        "results": [...]
    }
    """
    page_size = 100
    page_size_query_param = 'page_size'
    max_page_size = 1000
    page_query_param = 'page'

    def get_paginated_response(self, data):
        """
        Custom paginated response format.
        
        Includes count, next/previous links, and results.
        """
        return Response({
            'count': self.page.paginator.count,
            'next': self.get_next_link(),
            'previous': self.get_previous_link(),
            'results': data
        })


class LargeResultsSetPagination(PageNumberPagination):
    """
    Pagination for endpoints with large datasets.
    
    Default: 1000 items per page
    Max: 10000 items per page
    
    Use for: bulk data exports, administrative interfaces
    """
    page_size = 1000
    page_size_query_param = 'page_size'
    max_page_size = 10000
    page_query_param = 'page'


class SmallResultsSetPagination(PageNumberPagination):
    """
    Pagination for lightweight/mobile endpoints.
    
    Default: 20 items per page
    Max: 100 items per page
    
    Use for: mobile apps, real-time dashboards
    """
    page_size = 20
    page_size_query_param = 'page_size'
    max_page_size = 100
    page_query_param = 'page'

