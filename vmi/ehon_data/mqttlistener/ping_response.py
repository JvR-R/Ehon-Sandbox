import sys
import json
from mysql.connector import Error
import os
import configparser
import argparse
import logging
from logging.handlers import RotatingFileHandler
import paho.mqtt.client as mqtt

# Set up logging
log_file = "ping_response.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("ping_response_logger")
logger.setLevel(logging.DEBUG)

# Create a rotating file handler
handler = RotatingFileHandler(log_file, maxBytes=10000000, backupCount=5)
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

def load_configuration(config_file_path):
    if not os.path.isfile(config_file_path):
        logger.error(f"Config file not found at: {config_file_path}")
        raise Exception(f"Config file not found at: {config_file_path}")
    config = configparser.ConfigParser()
    config.read(config_file_path)
    return config

def publish_uid_to_mqtt(broker_config, uid, data_to_publish):
    mqtt_broker = broker_config.get('broker_host')
    mqtt_port = int(broker_config.get('broker_port', 1883))
    mqtt_username = broker_config.get('username', None)
    mqtt_password = broker_config.get('password', None)
    mqtt_topic = f"fms/{uid}"

    client = mqtt.Client()
    if mqtt_username and mqtt_password:
        client.username_pw_set(mqtt_username, mqtt_password)

    try:
        client.connect(mqtt_broker, mqtt_port)
        payload = json.dumps(data_to_publish)
        client.publish(mqtt_topic, payload)
        client.disconnect()
        logger.info(f"Published data for UID {uid} to MQTT topic {mqtt_topic}")
    except Exception as e:
        logger.error(f"Failed to publish to MQTT: {e}")


def main():
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description='Ping Processor')
    parser.add_argument('--data', help='Ping data in JSON format')
    parser.add_argument('--config', required=True, help='Path to configuration file')
    args = parser.parse_args()

    # Load configuration
    try:
        config = load_configuration(args.config)
    except Exception as e:
        logger.error(f"Error loading configuration: {e}")
        sys.exit(1)

    # Read data from --data argument or stdin
    if args.data:
        data_json = args.data
    else:
        # Read JSON data from stdin
        data_json = sys.stdin.read()

    # Parse the JSON string into a dictionary
    try:
        data = json.loads(data_json)
    except json.JSONDecodeError as e:
        logger.error(f"Error decoding JSON data: {e}")
        sys.exit(1)

    # Extract UID from data
    uid = data.get('uid')

    if not uid:
        logger.error("Missing required UID in the data.")
        sys.exit(1)

    try:
        uid = int(uid)
    except ValueError:
        logger.error(f"UID '{uid}' is not a valid integer.")
        sys.exit(1)

    # Extract MQTT broker configuration
    if 'mqtt' in config:
        mqtt_config = config['mqtt']
    else:
        logger.error("MQTT configuration not found in the config file.")
        sys.exit(1)

    # Connect to the database and execute the query
    try:
        logger.info("Database date is not newer than last_saved. Asking console for update.")
        Update = {
            "type": "PING",
            "Conn": "Ok"
        }
        publish_uid_to_mqtt(mqtt_config, uid, Update)

    except Error as e:
        logger.error(f"Error while connecting to database: {e}")
        sys.exit(1)
    except Exception as e:
        logger.error(f"An error occurred: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
