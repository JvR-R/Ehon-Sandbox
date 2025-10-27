# Level Alert Email System - Setup Guide

## Overview

This system sends real-time email alerts when tank levels drop below configured thresholds. It's **event-driven** (not polling), meaning alerts are sent immediately when new readings arrive via MQTT.

## Key Improvements over PHP Cron Approach

### PHP Cron Job (Old)
- ❌ **Polling-based**: Checks every N minutes (delay in alerts)
- ❌ **Blocking**: Email sending blocks execution
- ❌ **No retry**: If email fails, it's lost
- ❌ **Hardcoded config**: Email credentials in code

### Django + Celery (New)
- ✅ **Event-driven**: Alerts sent immediately on reading
- ✅ **Non-blocking**: Celery handles emails asynchronously
- ✅ **Auto-retry**: Failed emails retry with exponential backoff
- ✅ **Environment config**: Settings in Django settings.py
- ✅ **Better logging**: Structured logging with context
- ✅ **Type-safe**: Django ORM with type hints

## Architecture

```
MQTT Message → dips.py → Update Tanks
                       ↓
                Check Level Alert
                       ↓
              Queue Celery Task
                       ↓
              Send Email (async)
                       ↓
           Save to email_historic
```

## Setup Instructions

### 1. Install Redis (Celery Message Broker)

```bash
# On Ubuntu/Debian
sudo apt update
sudo apt install redis-server

# Start Redis
sudo systemctl start redis
sudo systemctl enable redis

# Verify Redis is running
redis-cli ping
# Should return: PONG
```

### 2. Update Environment Variables (Optional)

For production, move email credentials to environment variables:

Create `.env` file in `/home/ehon/public_html/backend/`:

```env
EMAIL_HOST=mail.ehon.com.au
EMAIL_PORT=465
EMAIL_HOST_USER=vmi@ehon.com.au
EMAIL_HOST_PASSWORD=VMIEHON2023
DEFAULT_FROM_EMAIL=vmi@ehon.com.au
```

Then update `settings.py`:

```python
import environ
env = environ.Env()

EMAIL_HOST = env('EMAIL_HOST', default='mail.ehon.com.au')
EMAIL_HOST_USER = env('EMAIL_HOST_USER', default='vmi@ehon.com.au')
EMAIL_HOST_PASSWORD = env('EMAIL_HOST_PASSWORD')
```

### 3. Start Celery Worker

In the backend directory, run:

```bash
cd /home/ehon/public_html/backend

# Start Celery worker (development)
celery -A backend_project worker --loglevel=info

# For production (with auto-reload and better logging)
celery -A backend_project worker \
    --loglevel=info \
    --concurrency=4 \
    --max-tasks-per-child=1000 \
    --logfile=/var/log/celery/worker.log
```

### 4. Configure Alert Settings in Database

For each tank that should send alerts:

```sql
-- Enable alerts for a tank
UPDATE Tanks 
SET alert_type = 1,        -- Must be non-zero (0 = alerts not configured)
    alert_flag = 1,        -- 1 = enabled, 2 = already alerted
    level_alert = 500      -- threshold in liters
WHERE uid = 123 AND tank_id = 1;

-- Configure site email
UPDATE Sites
SET Email = 'customer@example.com'
WHERE uid = 123;
```

### 5. Test the System

#### Test Email Sending (Django Shell)

```bash
cd /home/ehon/public_html/backend
python manage.py shell
```

```python
from accounts.tasks import send_level_alert_email

# Send test email
send_level_alert_email.delay(
    site_name="Test Site",
    tank_id=1,
    current_volume="450",
    current_percent="35.5",
    ullage="1550",
    receiver_email="your-email@example.com"
)
```

#### Monitor Celery Logs

```bash
# Watch Celery worker output
celery -A backend_project worker --loglevel=debug

# You should see:
# [INFO] Task accounts.tasks.send_level_alert_email[...] succeeded
```

## How It Works

### 1. MQTT Message Arrives

```json
{
  "1": {
    "vol": 450,
    "ull": 1550,
    "h": 450,
    "date": "2025-10-26",
    "time": "14:30:00"
  }
}
```

### 2. dips.py Processes Message

- Updates `Tanks` table with new volume
- Calls `check_and_send_level_alert()`

### 3. Alert Check Logic

```python
if (tank.level_alert is not None and 
    tank.alert_flag == 1 and 
    current_volume < tank.level_alert):
    # Send alert!
```

### 4. Email Queued to Celery

```python
send_level_alert_email.delay(...)
```

### 5. Alert Flag Updated

```python
# Prevent spam - set to 2 (alerted)
Tanks.objects.filter(...).update(alert_flag=2)
```

### 6. Email Sent Asynchronously

Celery worker sends the email in the background

## Email Template

Emails are sent in HTML format with this structure:

```
Subject: Ehon Alert for [Site Name], Tank [ID] is Low

⚠️ Low Level Alert
Site: Customer Site
Tank: 1
─────────────────────────
Current Percent: 35.50%
Volume: 450 L
Ullage: 1,550 L
─────────────────────────
This is an automated alert from Ehon VMI System.
Alert sent at: 2025-10-26 14:30:15 AEST
```

## Resetting Alerts

To allow a tank to send alerts again (after refill):

```sql
-- Reset alert flag back to enabled
UPDATE Tanks 
SET alert_flag = 1
WHERE uid = 123 AND tank_id = 1;
```

You can automate this by creating a cron job that resets flags when volume goes back above threshold + buffer.

## Production Deployment

### Using systemd for Celery

Create `/etc/systemd/system/celery-worker.service`:

```ini
[Unit]
Description=Celery Worker for Ehon Backend
After=network.target redis.service

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/home/ehon/public_html/backend
ExecStart=/usr/local/bin/celery -A backend_project worker \
    --loglevel=info \
    --concurrency=4 \
    --pidfile=/var/run/celery/worker.pid \
    --logfile=/var/log/celery/worker.log
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable celery-worker
sudo systemctl start celery-worker
sudo systemctl status celery-worker
```

## Monitoring

### Check Email History

```sql
SELECT * FROM email_historic 
ORDER BY email_date DESC, email_time DESC 
LIMIT 10;
```

### Check Alert Status

```sql
SELECT 
    s.name AS site_name,
    t.tank_id,
    t.current_volume,
    t.level_alert,
    t.alert_flag,
    t.current_percent,
    s.Email
FROM Tanks t
JOIN Sites s ON t.uid = s.uid AND t.Site_id = s.Site_id
WHERE t.level_alert IS NOT NULL
ORDER BY t.alert_flag DESC, t.current_volume;
```

## Troubleshooting

### Emails Not Sending

1. **Check Celery is running:**
   ```bash
   ps aux | grep celery
   ```

2. **Check Redis connection:**
   ```bash
   redis-cli ping
   ```

3. **Check Django logs:**
   ```bash
   tail -f /var/log/django/django.log
   ```

4. **Test SMTP connection:**
   ```python
   from django.core.mail import send_mail
   
   send_mail(
       'Test Subject',
       'Test message',
       'vmi@ehon.com.au',
       ['test@example.com'],
       fail_silently=False,
   )
   ```

### Alert Not Triggered

1. **Check alert_flag:**
   - Should be `1` (enabled), not `2` (already alerted)

2. **Check level_alert threshold:**
   - Must be set and greater than current_volume

3. **Check email configured:**
   - Sites table must have Email field populated

4. **Check logs:**
   ```bash
   grep "Level alert" /var/log/django/gateway.log
   ```

## Configuration Reference

### Database Fields

**Tanks table:**
- `alert_type`: Alert configuration (0 = not set up, non-zero = configured) **REQUIRED**
- `level_alert`: Threshold volume in liters (INT)
- `alert_flag`: 1 = enabled, 2 = alerted, NULL = disabled
- `current_volume`: Current tank volume (DECIMAL)
- `current_percent`: Current fill percentage (FLOAT)

**Sites table:**
- `Email`: Recipient email address (VARCHAR)
- `phone`: Optional phone number for future SMS alerts

**email_historic table:**
- `email_date`: Date email sent
- `email_time`: Time email sent
- `receiver_email`: Recipient
- `site_name`: Site name (for reference)
- `tank_id`: Tank ID (for reference)

## Future Enhancements

1. **SMS Alerts**: Add Twilio integration for SMS
2. **Multiple Recipients**: Support CC/BCC lists per site
3. **Alert Templates**: Customizable email templates per client
4. **Escalation**: Send to manager if not acknowledged
5. **Webhooks**: POST to external systems (e.g., Slack, Teams)
6. **Alert Cooldown**: Prevent re-alerting for X hours
7. **Daily Summaries**: Daily digest of all low tanks

## Files Modified

- `backend/ingest/gateway/dips.py` - Added alert checking
- `backend/accounts/tasks.py` - Celery tasks for emails
- `backend/accounts/models.py` - EmailHistoric model
- `backend/backend_project/settings.py` - Email & Celery config
- `backend/backend_project/celery.py` - Celery app setup
- `backend/backend_project/__init__.py` - Celery auto-import

## Support

For issues or questions, check logs in:
- Django: `/var/log/django/gateway.log`
- Celery: `/var/log/celery/worker.log`
- Email historic: `email_historic` table

