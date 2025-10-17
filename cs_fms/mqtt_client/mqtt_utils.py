from __future__ import absolute_import, unicode_literals
import json
import logging
import re
import os
import threading
import paho.mqtt.client as mqtt
from .models import Console

logger = logging.getLogger('mqtt_client')

def is_valid_uid(uid):
    return re.match("^[A-Za-z0-9_-]+$", uid) is not None

def publish_mqtt_message(topic, message):
    broker_address = os.getenv('MQTT_BROKER_ADDRESS', 'ehonenergytech.com.au')
    broker_port = int(os.getenv('MQTT_BROKER_PORT', 1883))
    mqtt_username = os.getenv('MQTT_USERNAME', 'ehon_fms')
    mqtt_password = os.getenv('MQTT_PASSWORD', '6MrQludp]EfY')
    try:
        client = mqtt.Client()
        client.username_pw_set(username=mqtt_username, password=mqtt_password)
        client.connect(broker_address, broker_port)
        client.loop_start()
        serialized_message = json.dumps(message, default=str)
        result = client.publish(topic, serialized_message)
        client.loop_stop() 
        client.disconnect()

        if result[0] == mqtt.MQTT_ERR_SUCCESS:
            return True, "Message published successfully."
        else:
            return False, f"MQTT publish failed with error code {result[0]}"
    except Exception as e:
        logger.exception(f"Error publishing MQTT message: {e}")
        return False, str(e)

def publish_mqtt_message_with_response(topic, message, response_topic, expected_uid, timeout=2):
    broker_address = os.getenv('MQTT_BROKER_ADDRESS', 'ehonenergytech.com.au')
    broker_port = int(os.getenv('MQTT_BROKER_PORT', 1883))
    mqtt_username = os.getenv('MQTT_USERNAME', 'ehon_fms')
    mqtt_password = os.getenv('MQTT_PASSWORD', '6MrQludp]EfY')
    logger.info("Sending system_state message for UID %s", expected_uid)

    response_received = threading.Event()
    response_payload = {}

    def on_connect(client, userdata, flags, rc):
        if rc == 0:
            logger.info("Connected to MQTT broker for system state check.")
            client.subscribe(response_topic)
            serialized_message = json.dumps(message, default=str)
            client.publish(topic, serialized_message)
        else:
            logger.error(f"Failed to connect to MQTT broker, return code {rc}")
            response_received.set()

    def on_message(client, userdata, msg):
        try:
            payload = json.loads(msg.payload.decode('utf-8'))
            logger.debug(f"Received response on topic '{msg.topic}': {payload}")
            if payload.get('uid') == expected_uid:
                current_state = payload.get('data', {}).get('current_state')
                if current_state:
                    response_payload.update(payload)
                    response_received.set()
        except Exception as e:
            logger.error(f"Unexpected error in on_message: {e}")
            response_received.set()

    client = mqtt.Client()
    client.username_pw_set(username=mqtt_username, password=mqtt_password)
    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(broker_address, broker_port)
    except Exception as e:
        logger.error(f"MQTT connection error: {e}")
        return False, f"MQTT connection error: {e}"

    client.loop_start()
    if not response_received.wait(timeout=timeout):
        logger.error("Timeout waiting for response from console.")
        client.loop_stop()
        client.disconnect()
        return False, "Timeout waiting for response from console."

    client.loop_stop()
    client.disconnect()
    return True, response_payload

def check_console_state(uid):
    success, response = publish_mqtt_message_with_response(
        topic=f"fms/{uid}",
        message={"system_state": 1},
        response_topic="fms_server",
        expected_uid=uid,
        timeout=2
    )
    if success:
        return response.get('data', {}).get('current_state')
    else:
        logger.error(f"Failed to get console state for UID {uid}: {response}")
        return None
