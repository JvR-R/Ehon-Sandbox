"""
Proper Passenger entry-point for your Django project.
Place this file at:  /home/ehon/public_html/backend/passenger_wsgi.py
"""

import os
import sys

# ── 1.  Add the project root to PYTHONPATH ───────────────────────────────
PROJECT_ROOT = os.path.dirname(__file__)          # /home/ehon/public_html/backend
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

# ── 2.  Tell Django where settings live ──────────────────────────────────
os.environ.setdefault("DJANGO_SETTINGS_MODULE", "backend_project.settings")

# ── 3.  Boot Django and expose `application` for Passenger ───────────────
from django.core.wsgi import get_wsgi_application
application = get_wsgi_application()
