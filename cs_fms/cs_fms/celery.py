# import eventlet
# eventlet.monkey_patch()

import os
from celery import Celery
from django.conf import settings
from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'))

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'cs_fms.settings')

app = Celery('cs_fms')
app.config_from_object('django.conf:settings', namespace='CELERY')
app.autodiscover_tasks()
