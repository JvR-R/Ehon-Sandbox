# mqtt_client/redis_client.py

import os
import redis
import urllib.parse
import logging

logger = logging.getLogger('mqtt_client')

redis_host = 'localhost'
redis_port = 6379
redis_db = 0
redis_password = os.getenv('REDIS_PASSWORD', None)

try:
    if redis_password:
        # Encode the password to handle special characters
        redis_password_encoded = urllib.parse.quote_plus(redis_password)
        redis_url = f'redis://:{redis_password_encoded}@{redis_host}:{redis_port}/{redis_db}'
    else:
        redis_url = f'redis://{redis_host}:{redis_port}/{redis_db}'

    # Initialize Redis client using the URL
    redis_client = redis.StrictRedis.from_url(redis_url, decode_responses=True)
    redis_client.ping()
    logger.info("Connected to Redis successfully in redis_client.py.")
except redis.ConnectionError as e:
    logger.error(f"Could not connect to Redis in redis_client.py: {e}")
    redis_client = None
