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
from datetime import datetime  # Import for date comparison

def fix_date_string(date_str):
    # Expected format: '%Y-%m-%d %H:%M:%S'
    try:
        parts = date_str.strip().split(' ')
        date_part = parts[0]
        time_part = parts[1] if len(parts) > 1 else '00:00:00'
        year, month, day = date_part.split('-')

        # Replace '00' with '01' for month and day
        month = '01' if month == '00' else month
        day = '01' if day == '00' else day

        fixed_date_part = '-'.join([year, month, day])
        fixed_date_str = f"{fixed_date_part} {time_part}"
        return fixed_date_str
    except Exception as e:
        logger.error(f"Error fixing date string '{date_str}': {e}")
        raise

# Set up logging
log_file = "cs_auth.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("cs_auth_logger")
logger.setLevel(logging.DEBUG)

# Create a rotating file handler
handler = RotatingFileHandler(log_file, maxBytes=10000000, backupCount=5)
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

# Add a StreamHandler to output logs to stderr (optional)
stream_handler = logging.StreamHandler()
stream_handler.setFormatter(formatter)
logger.addHandler(stream_handler)

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
    parser = argparse.ArgumentParser(description='Authenticator Processor')
    parser.add_argument('--data', help='Data in JSON format')
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
        logger.debug(f"Parsed data: {data}")
    except json.JSONDecodeError as e:
        logger.error(f"Error decoding JSON data: {e}")
        sys.exit(1)

    # Extract UID from data
    uid = data.get('uid')

    if not uid:
        logger.error("Missing required UID in the data.")
        sys.exit(1)

    # Convert UID to the correct data type
    try:
        uid = int(uid)
        logger.debug(f"Converted UID to integer: {uid}")
    except ValueError:
        logger.error(f"UID '{uid}' is not a valid integer.")
        sys.exit(1)

    # Extract data_crc32 from data
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

    # Connect to the database and execute the queries
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

            # Fetch CRC value
            crc_query = "SELECT crc_auth FROM console WHERE uid = %s;"
            cursor.execute(crc_query, (uid,))
            crc_result = cursor.fetchone()
            crc_value = crc_result[0] if crc_result else ""
            crc_value = str(crc_value)
            logger.debug(f"Fetched crc_value from database: {crc_value}")

            # Compare data_crc32 and crc_value
            if data_crc32.lower() == crc_value.lower():
                logger.info(f"CRC32 matches for UID {uid}. Skipping update.")
                sys.exit(0)
            else:
                logger.info(f"CRC32 does not match for UID {uid}. Proceeding with update.")

                # Fetch last update date from the database
                date_query = """
                SELECT MAX(updated_at) FROM client_tags 
                WHERE client_id IN (SELECT client_id FROM Console_Asociation WHERE uid = %s);
                """
                cursor.execute(date_query, (uid,))
                date_result = cursor.fetchone()
                date_value = date_result[0] if date_result else None
                # Preprocess date strings to replace '00' months and days with '01'
                data_date_fixed = fix_date_string(data_date)

                if date_value is None:
                    logger.error(f"No date information found for UID {uid} in the database.")
                    sys.exit(1)
                else:
                    date_value = str(date_value)
                    logger.debug(f"Fetched date_value from database: {date_value}")

                # Convert date strings to datetime objects for comparison
                try:
                    data_date_dt = datetime.strptime(data_date_fixed, '%Y-%m-%d %H:%M:%S')
                    date_value_dt = datetime.strptime(date_value, '%Y-%m-%d %H:%M:%S')
                except ValueError as e:
                    logger.error(f"Date format error: {e}")
                    sys.exit(1)

                # Compare dates
                if date_value_dt > data_date_dt:
                    logger.info("Database date is newer than last_saved. Proceeding with update.")
                else:
                    logger.info("Database date is not newer than last_saved. Asking console for update.")
                    Update = {
                        "req": 'AC'
                    }
                    publish_uid_to_mqtt(mqtt_config, uid, Update)
                    sys.exit(0)

            # Fetch authenticators data
            auth_query = """
            SELECT id, card_number, card_type, list_driver, list_vehicle, driver_prompt, prompt_vehicle, projectnum_prompt, odo_prompt, pin_number, enabled_prompt 
            FROM client_tags 
            WHERE client_id IN (SELECT client_id FROM Console_Asociation WHERE uid = %s);
            """

            cursor.execute(auth_query, (uid,))
            results = cursor.fetchall()

            if results:
                authenticators_list = []
                for row in results:
                    id = row[0]
                    card_number = row[1]
                    card_type = row[2]
                    list_driver = row[3]
                    list_vehicle = row[4]
                    driver_prompt = row[5]
                    prompt_vehicle = row[6]
                    projectnum_prompt = row[7]
                    odo_prompt = row[8]
                    pin_number = row[9]
                    enabled_prompt = row[10]
                    if driver_prompt == 999:
                        driver_prompt = 0
                    if prompt_vehicle == 999:
                        prompt_vehicle = 0
                    # Build the row values in the required order
                    row_values = [
                        str(id),
                        str(card_number if card_number is not None else 0),
                        str(card_type if card_type is not None else 0),
                        str(list_driver if list_driver is not None else 0),
                        str(list_vehicle if list_vehicle is not None else 0),
                        str(driver_prompt if driver_prompt is not None else 0),
                        str(prompt_vehicle if prompt_vehicle is not None else 0),
                        str(projectnum_prompt if projectnum_prompt is not None else 0),
                        str(odo_prompt if odo_prompt is not None else 0),
                        str(pin_number if pin_number is not None else 0),
                        str(enabled_prompt if enabled_prompt is not None else 0)
                    ]
                    row_string = ','.join(row_values)
                    authenticators_list.append(row_string)

                # Join the rows with '\n' to create the 'authenticators' string
                authenticators_data = '\n'.join(authenticators_list) + '\n'

                # Build the final data to publish
                data_to_publish = {
                    "is_update": False,
                    "CRC": crc_value,
                    "authenticators": authenticators_data
                }

                # Publish the data
                publish_uid_to_mqtt(mqtt_config, uid, data_to_publish)
            else:
                logger.error(f"No authenticators data found for UID {uid} in the database.")
                sys.exit(1)

    except Error as e:
        logger.error(f"Error while connecting to database: {e}")
        sys.exit(1)
    except Exception as e:
        logger.error(f"An error occurred: {e}", exc_info=True)
        sys.exit(1)
    finally:
        if 'connection' in locals() and connection.is_connected():
            cursor.close()
            connection.close()
            logger.info("Database connection closed.")

if __name__ == "__main__":
    main()
