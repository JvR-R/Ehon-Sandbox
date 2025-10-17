import logging
import os
import json
from celery import shared_task
from .mqtt_utils import publish_mqtt_message, check_console_state
import redis

logger = logging.getLogger('mqtt_client')

redis_host = os.getenv('REDIS_HOST', 'localhost')
redis_port = int(os.getenv('REDIS_PORT', 6379))
redis_db = int(os.getenv('REDIS_DB', 0))
redis_password = os.getenv('REDIS_PASSWORD', None)

redis_client = redis.StrictRedis(
    host=redis_host,
    port=redis_port,
    db=redis_db,
    password=redis_password,
    decode_responses=True
)

@shared_task(bind=True, max_retries=None)
def manager_task(self, uid):
    # Guard: if manager is already running, exit
    running_flag = redis_client.get(f"uid:{uid}:manager_running")
    if running_flag == "1":
        logger.info(f"[manager_task] Already running for UID {uid}, so exiting.")
        return

    # Otherwise, mark as running
    redis_client.set(f"uid:{uid}:manager_running", "1")
    logger.info(f"[manager_task] Set manager_running=1 for UID {uid}.")

    try:
        # 1. Are there any messages left?
        msg_count = redis_client.llen(f"uid:{uid}:messages")
        if msg_count == 0:
            # No messages left, stop manager
            redis_client.set(f"uid:{uid}:manager_running", "0")
            logger.info(f"No messages left for UID {uid}, manager stopping.")
            return

        # 2. Check console state
        state = check_console_state(uid)
        logger.info("Console state for UID %s: %s", uid, state)
        if state != "STATE_IDLE":
            logger.warning(f"Console for UID {uid} not idle, retrying in 10s.")
            redis_client.set(f"uid:{uid}:manager_running", "0")
            raise self.retry(countdown=10, exc=Exception("Console not idle."))

        # 3. Pop one message
        message_json = redis_client.lpop(f"uid:{uid}:messages")
        if not message_json:
            # Race condition safety
            redis_client.set(f"uid:{uid}:manager_running", "0")
            logger.info(f"No messages to send for UID {uid}. Manager stopping.")
            return

        # 4. Send message
        message_data = json.loads(message_json)
        topic = f"fms/{uid}"
        success, resp = publish_mqtt_message(topic, message_data)
        if not success:
            logger.error(f"Failed to send {message_data} for UID {uid}, retrying...")
            redis_client.lpush(f"uid:{uid}:messages", message_json)
            redis_client.set(f"uid:{uid}:manager_running", "0")
            raise self.retry(countdown=10, exc=Exception(resp))
        else:
            logger.info(f"Message sent for UID {uid}: {message_data}")

        # 5. If more messages, schedule next run in 5s
        remaining_count = redis_client.llen(f"uid:{uid}:messages")
        if remaining_count > 0:
            logger.info(f"{remaining_count} messages remain for UID {uid}, retrying in 5s.")
            # Unlock here so the next iteration won't bail out
            redis_client.set(f"uid:{uid}:manager_running", "0")
            raise self.retry(countdown=5)
        else:
            # No more messages
            redis_client.set(f"uid:{uid}:manager_running", "0")
            logger.info(f"All messages sent for UID {uid}, manager stopping.")

    except Exception as e:
        logger.exception(f"Error in manager_task for UID {uid}: {e}")
        raise self.retry(exc=e)


@shared_task
def test_task():
    logger.info("test_task executed")