# Contamination Control (CC) Data Ingestion Implementation

## Summary

This implementation handles contamination control data from gateway devices and saves it to the `fuel_quality` database table.

## Files Modified/Created

### 1. `/home/ehon/public_html/backend/accounts/models.py`
Updated the `FuelQuality` model to match the actual database schema with fields for:
- Particle counts (iso4, iso6, iso14)
- Bubble counts
- Shape analysis (cutting, sliding, fatigue, fibre, air, unknown)
- Temperature readings

### 2. `/home/ehon/public_html/backend/ingest/gateway/cc.py` (NEW)
Created a new handler to process contamination control messages.

## MQTT Topic Pattern

```
gateway/<serial>/cc
```

## Expected Payload Format

```json
{
  "date": "2025-11-04",
  "time": "12:36:10",
  "readings": [
    {
      "tank": 1,
      "iso4": 11,
      "iso6": 9,
      "iso14": 3,
      "particles": 12,
      "bubbles": 25,
      "shapes": {
        "cutting": 0,
        "sliding": 0,
        "fatigue": 0,
        "fibre": 0,
        "air": 0,
        "unknown": 0
      },
      "tempC": 31.3
    }
  ]
}
```

## Field Mapping

| JSON Field | Database Column | Type |
|------------|----------------|------|
| date | fq_date | date |
| time | fq_time | time |
| tank | tank_id | int |
| iso4 | particle_4um | int |
| iso6 | particle_6um | int |
| iso14 | particle_14um | int |
| bubbles | fq_bubbles | int |
| shapes.cutting | fq_cutting | int |
| shapes.sliding | fq_sliding | int |
| shapes.fatigue | fq_fatigue | int |
| shapes.fibre | fq_fibre | int |
| shapes.air | fq_air | int |
| shapes.unknown | fq_unknown | int |
| tempC | fq_temp | double/float |

## How It Works

1. **Message Reception**: The gateway MQTT service receives a message on `gateway/<serial>/cc`
2. **Console Lookup**: The handler looks up the console by `device_id` (serial number)
3. **Client Association**: Retrieves the `client_id` from `Console_Asociation` table
4. **Data Processing**: For each reading in the `readings` array:
   - Validates required fields (tank, date, time)
   - Extracts all measurement data
   - Creates a new `FuelQuality` record with all fields populated
5. **Transaction Safety**: All database operations are wrapped in a transaction
6. **Logging**: Success and error messages are logged for monitoring

## Automatic Handler Registration

The handler is automatically discovered and registered by `/home/ehon/public_html/backend/ingest/gateway/__init__.py`, which scans all `.py` files in the gateway directory and imports their `TOPICS` dictionary.

## Error Handling

The handler includes comprehensive error handling:
- Missing serial number
- Unknown console
- Missing date/time in payload
- Invalid tank ID
- Database errors during insert

All errors are logged with full context for debugging.

## Testing

To test the implementation, publish a message to the MQTT broker:

```bash
# Example using mosquitto_pub
mosquitto_pub -h <broker_host> -p 8883 \
  --cafile /path/to/ca.crt \
  -u <username> -P <password> \
  -t "gateway/034368/cc" \
  -m '{
    "date":"2025-11-04",
    "time":"12:36:10",
    "readings":[{
      "tank":1,
      "iso4":11,
      "iso6":9,
      "iso14":3,
      "particles":12,
      "bubbles":25,
      "shapes":{
        "cutting":0,
        "sliding":0,
        "fatigue":0,
        "fibre":0,
        "air":0,
        "unknown":0
      },
      "tempC":31.3
    }]
  }'
```

## Monitoring

Check the logs for success messages:

```
✅ fuel_quality record saved: console=034368 uid=<uid> tank_id=1 date=2025-11-04 time=12:36:10
✅ cc data processed for console 034368: 1 reading saved
```

## Database Verification

Query the database to verify data was saved:

```sql
SELECT * FROM fuel_quality 
WHERE fq_date = '2025-11-04' 
  AND fq_time = '12:36:10' 
ORDER BY if_fq DESC 
LIMIT 1;
```

## Notes

- The handler supports multiple readings in a single message
- All fields are optional except `date`, `time`, and `tank`
- The `client_id` is automatically populated from the console association
- The `uid` is derived from the console serial number
- Records are inserted (not updated) - each message creates new rows

