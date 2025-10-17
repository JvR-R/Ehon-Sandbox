# mqtt_client/management/commands/monitor_queues.py

from django.core.management.base import BaseCommand
import json
import logging
import redis
import time
from concurrent.futures import ThreadPoolExecutor
from mqtt_client.mqtt_utils import publish_mqtt_message, check_console_state

logger = logging.getLogger('mqtt_client')

class Command(BaseCommand):
    help = 'Monitor queued MQTT messages and flush them when consoles are idle.'

    def handle(self, *args, **options):
        logger.info("Starting queue monitor.")
        redis_host = 'localhost'
        redis_port = 6379
        redis_db = 0
        redis_password = None  # Ensure this matches your Redis configuration

        # Initialize Redis connection
        try:
            redis_client = redis.StrictRedis(
                host=redis_host,
                port=redis_port,
                db=redis_db,
                password=redis_password,
                decode_responses=True
            )
            redis_client.ping()
            logger.info("Connected to Redis successfully.")
        except redis.ConnectionError as e:
            logger.error(f"Could not connect to Redis: {e}")
            return

        # Subscribe to the 'queue_notifications' channel
        pubsub = redis_client.pubsub()
        pubsub.subscribe('queue_notifications')
        logger.info("Subscribed to 'queue_notifications' channel.")

        check_interval = 5  # seconds between periodic checks
        executor = ThreadPoolExecutor(max_workers=20)  # Adjust based on server capacity

        try:
            while True:
                message = pubsub.get_message(timeout=check_interval)
                if message and message['type'] == 'message':
                    uid = message['data']
                    logger.info(f"Received queue notification for UID: {uid}")
                    # Submit the queue processing to the thread pool
                    executor.submit(self.process_queue, uid, redis_client)
                else:
                    # Periodically check all queues
                    self.check_all_queues(redis_client, executor)
        except KeyboardInterrupt:
            logger.info("Queue monitor stopped by user.")
        except Exception as e:
            logger.exception(f"Error in queue monitor: {e}")
        finally:
            executor.shutdown(wait=True)
            logger.info("Queue monitor terminated.")

    def process_queue(self, uid, redis_client):
        """
        Flush all messages for a specific UID if the console is idle.
        """
        queue_key = f"queue:{uid}"
        dead_letter_key = f"dead_letter:{uid}"
        max_retries = 5  # Define maximum retry attempts

        logger.info(f"Processing queue for UID: {uid}")

        while True:
            message_json = redis_client.lpop(queue_key)
            if not message_json:
                logger.info(f"No more messages in queue for UID: {uid}")
                break  # Queue is empty

            message_data = json.loads(message_json)
            topic = message_data['topic']
            message = message_data['message']
            retry_count = message_data.get('retry_count', 0)

            # Check console state before sending
            current_state = check_console_state(uid)
            if current_state == 'STATE_IDLE':
                success, resp = publish_mqtt_message(topic, message)
                if success:
                    logger.info(f"Flushed message for UID {uid}: {message}")
                else:
                    logger.error(f"Failed to flush message for UID {uid}: {message}, Error: {resp}")
                    retry_count += 1
                    if retry_count >= max_retries:
                        # Move to dead-letter queue
                        redis_client.rpush(dead_letter_key, json.dumps(message_data))
                        logger.error(f"Moved message to dead-letter queue for UID {uid}: {message}")
                    else:
                        # Re-enqueue with updated retry count
                        message_data['retry_count'] = retry_count
                        redis_client.lpush(queue_key, json.dumps(message_data))
                        logger.info(f"Re-enqueued message for UID {uid} with retry_count={retry_count}: {message}")
                    break  # Exit to prevent rapid retries
            else:
                logger.info(f"Console {uid} not idle (state={current_state}), re-queuing message.")
                # Re-enqueue the message for later processing
                redis_client.lpush(queue_key, message_json)
                break  # Exit to prevent infinite loop

    def check_all_queues(self, redis_client, executor):
        """
        Periodically check all queues to ensure no messages are missed.
        """
        queue_keys = redis_client.keys('queue:*')
        logger.debug(f"Checking all queues: {queue_keys}")
        for key in queue_keys:
            uid = key.split(':', 1)[1]  # Split only on the first ':'
            executor.submit(self.process_queue, uid, redis_client)
