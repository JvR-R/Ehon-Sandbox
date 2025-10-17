import sys
import json
import mysql.connector
from mysql.connector import Error
import os
import configparser
import argparse
import logging
from logging.handlers import RotatingFileHandler
from datetime import datetime
import paho.mqtt.client as mqtt

# Set up logging
log_file = "cs_registration.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("cs_registration_logger")
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

def publish_uid_to_mqtt(broker_config, device_id, uid):
    mqtt_broker = broker_config.get('broker_host')
    mqtt_port = int(broker_config.get('broker_port', 1883))
    mqtt_username = broker_config.get('username', None)
    mqtt_password = broker_config.get('password', None)
    mqtt_topic = f"fms/{device_id}"

    client = mqtt.Client()
    if mqtt_username and mqtt_password:
        client.username_pw_set(mqtt_username, mqtt_password)

    try:
        client.connect(mqtt_broker, mqtt_port)
        payload = json.dumps({'uid': uid, 'country': "Australia", "city": "Brisbane"})
        client.publish(mqtt_topic, payload)
        client.disconnect()
        logger.info(f"Published UID {uid} to MQTT topic {mqtt_topic}")
    except Exception as e:
        logger.error(f"Failed to publish to MQTT: {e}")

def main():
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description='Registration Processor')
    parser.add_argument('--data', help='Registration data in JSON format')
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

    # Extract database configuration
    db_config = config['database']
    db_host = db_config.get('host')
    db_name = db_config.get('database')
    db_user = db_config.get('user')
    db_password = db_config.get('password')

    # Extract MQTT broker configuration
    if 'mqtt' in config:
        mqtt_config = config['mqtt']
    else:
        logger.error("MQTT configuration not found in the config file.")
        sys.exit(1)

    # Insert the data into the database
    try:
        # Establish a database connection
        connection = mysql.connector.connect(
            host=db_host,
            database=db_name,
            user=db_user,
            password=db_password
        )

        if connection.is_connected():
            cursor = connection.cursor()

            device_id = data.get('device_id')
            device_type = data.get('device_type')
            firmware = data.get('firmware')

            if not device_id or not device_type or not firmware:
                logger.error("Missing required registration data.")
                sys.exit(1)

            # Check if the device is already registered
            check_query = "SELECT uid FROM console WHERE device_id = %s"
            cursor.execute(check_query, (device_id,))
            result = cursor.fetchone()

            if result:
                # Device is already registered
                uid = result[0]
                logger.info(f"Device {device_id} is already registered with UID {uid}.")
            else:
                # Create an INSERT statement
                insert_query = """
                INSERT INTO console (
                    device_id, device_type, man_data, console_status, firmware
                )
                VALUES (%s, %s, %s, %s, %s)
                """

                # Prepare the data tuple
                man_data = datetime.now().strftime('%Y-%m-%d')  # current date
                console_status = 'In Stock'

                data_tuple = (
                    device_id,
                    device_type,
                    man_data,
                    console_status,
                    firmware
                )

                # Execute the INSERT statement
                cursor.execute(insert_query, data_tuple)

                # Get the last inserted id
                uid = cursor.lastrowid

                # Commit the transaction
                connection.commit()

                logger.info(f"Registration data inserted successfully. UID: {uid}")

            # Publish UID to MQTT topic
            publish_uid_to_mqtt(mqtt_config, device_id, uid)

    except Error as e:
        logger.error(f"Error while connecting to database: {e}")
    except Exception as e:
        logger.error(f"An error occurred: {e}")
    finally:
        if 'connection' in locals() and connection.is_connected():
            cursor.close()
            connection.close()
            logger.info("Database connection closed.")

if __name__ == "__main__":
    main()
