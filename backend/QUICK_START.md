# Level Alerts - Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Install Redis
```bash
sudo apt update
sudo apt install redis-server -y
sudo systemctl start redis
sudo systemctl enable redis

# Test
redis-cli ping  # Should return: PONG
```

### Step 2: Start Celery Worker
```bash
cd /home/ehon/public_html/backend
./start_celery.sh
```

### Step 3: Configure a Test Tank
```sql
-- Enable alerts
UPDATE Tanks 
SET alert_type = 1,      -- Must be non-zero (0 = alerts disabled)
    alert_flag = 1,      -- 1 = enabled, 2 = already alerted
    level_alert = 500    -- threshold in liters
WHERE uid = 123 AND tank_id = 1;

-- Set email
UPDATE Sites
SET Email = 'your-email@example.com'
WHERE uid = 123;
```

### Step 4: Test It!
```python
# Python shell
python manage.py shell

from accounts.tasks import send_level_alert_email

send_level_alert_email.delay(
    site_name="Test Site",
    tank_id=1,
    current_volume="450",
    current_percent="35.5",
    ullage="1550",
    receiver_email="your-email@example.com"
)
```

Check your inbox! 📧

---

## 📋 How It Works

```
MQTT Message → Update Tank → Check Threshold → Queue Email → Send Alert
   (instant)    (< 10ms)      (< 10ms)          (< 1ms)      (async)
```

**Total time from reading to email queued: < 50ms**

---

## 🎯 Key Commands

### Start/Stop Celery
```bash
# Start
./start_celery.sh

# Stop
pkill -f "celery.*worker"

# Restart
pkill -f "celery.*worker" && ./start_celery.sh
```

### Check Status
```bash
# View active tasks
celery -A backend_project inspect active

# View workers
celery -A backend_project inspect stats

# View logs
tail -f logs/celery_worker.log
```

### Monitor Emails
```sql
-- Recent emails
SELECT * FROM email_historic 
ORDER BY email_date DESC, email_time DESC 
LIMIT 10;

-- Alert status
SELECT 
    s.name, t.tank_id, t.current_volume, t.level_alert,
    CASE t.alert_flag 
        WHEN 1 THEN 'Enabled' 
        WHEN 2 THEN 'Alerted' 
    END as status
FROM Tanks t
JOIN Sites s ON t.uid = s.uid AND t.Site_id = s.Site_id
WHERE t.level_alert IS NOT NULL;
```

---

## ⚙️ Configuration

### Enable Alert
```sql
UPDATE Tanks 
SET alert_type = 1,    -- Configure alert type (non-zero)
    alert_flag = 1     -- Enable alerts
WHERE uid = ? AND tank_id = ?;
```

### Disable Alert
```sql
UPDATE Tanks 
SET alert_type = 0     -- Disable alerts (0 = not configured)
WHERE uid = ? AND tank_id = ?;
```

### Reset After Refill
```sql
UPDATE Tanks SET alert_flag = 1 
WHERE uid = ? AND tank_id = ? 
  AND current_volume > level_alert;
```

---

## 🐛 Troubleshooting

### No Email Sent?

**Check 1:** Is Celery running?
```bash
ps aux | grep celery
```

**Check 2:** Is Redis running?
```bash
redis-cli ping
```

**Check 3:** Is alert enabled?
```sql
SELECT alert_flag, level_alert, current_volume 
FROM Tanks WHERE uid = ? AND tank_id = ?;
```

**Check 4:** Is email configured?
```sql
SELECT Email FROM Sites WHERE uid = ?;
```

**Check 5:** Check logs
```bash
tail -50 logs/celery_worker.log | grep -i error
```

---

## 📊 Monitoring Dashboard (Optional)

Install Flower for web-based monitoring:

```bash
pip install flower
celery -A backend_project flower --port=5555
```

Visit: http://localhost:5555

Features:
- Real-time task monitoring
- Worker statistics
- Task history
- Retry graphs

---

## 🔄 Production Deployment

### Using systemd (Recommended)

Create `/etc/systemd/system/celery-worker.service`:

```ini
[Unit]
Description=Celery Worker
After=redis.service

[Service]
Type=forking
User=www-data
WorkingDirectory=/home/ehon/public_html/backend
ExecStart=/usr/local/bin/celery -A backend_project worker \
    --loglevel=info \
    --logfile=/var/log/celery/worker.log
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable:
```bash
sudo systemctl daemon-reload
sudo systemctl enable celery-worker
sudo systemctl start celery-worker
sudo systemctl status celery-worker
```

---

## 📚 Documentation Files

- **QUICK_START.md** (this file) - Get started fast
- **IMPLEMENTATION_SUMMARY.md** - What was built and why
- **LEVEL_ALERTS_SETUP.md** - Comprehensive setup guide
- **COMPARISON.md** - PHP vs Django comparison

---

## 🎓 Learn More

### Django Email
https://docs.djangoproject.com/en/5.2/topics/email/

### Celery
https://docs.celeryq.dev/en/stable/

### Redis
https://redis.io/docs/

---

## ✅ Checklist

Before going to production:

- [ ] Redis installed and running
- [ ] Celery worker started (via systemd)
- [ ] Test email sent successfully
- [ ] Alert thresholds configured
- [ ] Site emails configured
- [ ] Logs being written
- [ ] Monitoring set up (optional: Flower)
- [ ] PHP cron disabled (after testing)

---

## 🆘 Support

**Issue:** Emails not sending  
**Solution:** Check Celery logs and Redis connection

**Issue:** Alerts not triggering  
**Solution:** Verify alert_flag=1 and threshold set

**Issue:** Duplicate emails  
**Solution:** Ensure PHP cron is disabled

**Issue:** Worker crashes  
**Solution:** Check logs, increase memory if needed

---

## 🎉 Success!

Once you see this in Celery logs:

```
[INFO] Task send_level_alert_email succeeded
[INFO] ✅ Level alert email sent to customer@example.com
```

You're all set! Your system is now sending **real-time** tank level alerts! 🚀

---

**Quick Links:**
- Start Celery: `./start_celery.sh`
- View logs: `tail -f logs/celery_worker.log`
- Check emails: `SELECT * FROM email_historic ORDER BY email_date DESC LIMIT 10;`

