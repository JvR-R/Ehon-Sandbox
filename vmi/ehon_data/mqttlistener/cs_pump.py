import sys
import json
import mysql.connector
from mysql.connector import Error
import os
import configparser
import argparse
import logging
from logging.handlers import RotatingFileHandler
import paho.mqtt.client as mqtt
from decimal import Decimal
from datetime import datetime

# Set up logging
log_file = "cs_Pump.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("cs_pump_logger")
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
    parser = argparse.ArgumentParser(description='Pump Processor')
    parser.add_argument('--data', help='Pump data in JSON format')
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

    data_crc32 = data.get('crc32')
    if data_crc32 is None:
        logger.error("Missing crc32 in the data.")
        sys.exit(1)
    data_crc32 = str(data_crc32)
    logger.debug(f"Received crc32 from data: {data_crc32}")

    # Extract last_saved from data
    data_date = data.get('last_saved')
    if data_date is None:
        logger.error("Missing last_saved in the data.")
        sys.exit(1)
    data_date = str(data_date)
    logger.debug(f"Received last_saved from data: {data_date}")

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

    # Connect to the database and execute the query
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

            # Add debug logging
            logger.debug(f"UID value: {uid}, Type: {type(uid)}")

            crc_query = "SELECT crc_pumps FROM console WHERE uid = %s;"
            cursor.execute(crc_query, (uid,))
            crc_result = cursor.fetchone()
            crc_value = crc_result[0] if crc_result else ""
            # Compare data_crc32 and crc_value
            if data_crc32.lower() == crc_value.lower():
                logger.info(f"CRC32 matches for UID {uid}. {data_crc32} == {crc_value} Skipping update.")
                sys.exit(0)
            else:
                logger.info(f"CRC32 does not match for UID {uid}. {data_crc32} != {crc_value} Proceeding with update.")

            # Fetch last update date from the database
            date_query = """
            SELECT MAX(updated_at) FROM pumps 
            WHERE uid = %s;
            """
            cursor.execute(date_query, (uid,))
            date_result = cursor.fetchone()
            date_value = date_result[0] if date_result else None

            if date_value is not None:
                date_value = str(date_value)
                logger.debug(f"Fetched date_value from database: {date_value}")

                # Convert date strings to datetime objects for comparison
                try:
                    data_date_dt = datetime.strptime(data_date, '%Y-%m-%d %H:%M:%S')
                    date_value_dt = datetime.strptime(date_value, '%Y-%m-%d %H:%M:%S')
                except ValueError as e:
                    logger.error(f"Date format error: {e}", exc_info=True)
                    sys.exit(1)

                # Compare dates: if the database date is not newer, ask the console for update.
                if date_value_dt <= data_date_dt:
                    logger.info("Database date is not newer than last_saved. Asking console for update.")
                    update_message = {
                        "req": 'PC'
                    }
                    publish_uid_to_mqtt(mqtt_config, uid, update_message)
                    sys.exit(0)
                else:
                    logger.info("Database date is newer than last_saved. Proceeding with update.")
            else:
                # If no date is found, proceed with the update.
                logger.info(f"No date information found for UID {uid} in the database, proceeding with update.")

            # Use the provided query to fetch Pump_id, capacity, product_name
            check_query = """
            SELECT Nozzle_Number, Pulse_Rate, tank_id 
            FROM `pumps` WHERE uid = %s;
            """
            cursor.execute(check_query, (uid,))
            results = cursor.fetchall()
            

            if results:
                # Create a list to hold all Pump data
                pumps_data = []
                for row in results:
                    Nozzle_Number = row[0]
                    Pulse_Rate = row[1]
                    tank_id = row[2]
                    inuse = 0
                    nozzle_switch= 0
                    keypad_start = 0

                    row_values = [
                        int(Nozzle_Number),
                        float(Pulse_Rate if Pulse_Rate is not None else 0),
                        int(tank_id),
                        int(inuse),
                        int(nozzle_switch),
                        int(keypad_start)
                    ]
                    # Convert all elements in row_values to strings before joining
                    row_string = ','.join(map(str, row_values))
                    pumps_data.append(row_string)

                # Join the rows with '\n' to create the 'authenticators' string
                pumps_list = '\n'.join(pumps_data) + '\n'
                # Publish all tank data
                 # Build the final data to publish
                data_to_publish = {
                    "is_update": False,
                    "CRC": crc_value,
                    "pumps": pumps_list
                }
                # Publish the data
                publish_uid_to_mqtt(mqtt_config, uid, data_to_publish)
            else:
                logger.error(f"No data found for UID {uid} in the database.")
                data_to_publish = {
                    "is_update": False,
                    "CRC": crc_value,
                    "pumps": ''
                }
                # Publish the data
                publish_uid_to_mqtt(mqtt_config, uid, data_to_publish)
                # sys.exit(1)


    except Error as e:
        logger.error(f"Error while connecting to database: {e}")
        sys.exit(1)
    except Exception as e:
        logger.error(f"An error occurred: {e}")
        sys.exit(1)
    finally:
        if 'connection' in locals() and connection.is_connected():
            cursor.close()
            connection.close()
            logger.info("Database connection closed.")

if __name__ == "__main__":
    main()
