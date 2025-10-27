#!/usr/bin/env bash
set -Eeuo pipefail

# Activate virtualenv
source /home/ehon/virtualenv/public_html/backend/3.12/bin/activate

# Change to backend directory
cd /home/ehon/public_html/backend

# Create logs directory
mkdir -p logs

# Optional: load env vars from .env if you keep secrets here
if [ -f .env ]; then set -a; . ./.env; set +a; fi

# Use virtualenv's Python and Celery
# Single-instance lock (prevents accidental double starts)
exec /usr/bin/flock -n logs/celery.lock \
  /home/ehon/virtualenv/public_html/backend/3.12/bin/python3 -m celery -A backend_project worker \
  --loglevel=INFO \
  --concurrency=3 \
  --max-tasks-per-child=500 \
  --max-memory-per-child=200 \
  --logfile=/home/ehon/public_html/backend/logs/celery_worker.log \
  --pidfile=/home/ehon/public_html/backend/logs/celery_worker.pid
