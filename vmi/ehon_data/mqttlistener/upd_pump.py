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
log_file = "upd_pump.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("upd_pump_logger")
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

def main():
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description='pump Processor')
    parser.add_argument('--data', help='pump data in JSON format')
    parser.add_argument('--config', required=True, help='Path to configuration file')
    args = parser.parse_args()

    # Load configuration
    try:
        config = load_configuration(args.config)
    except Exception as e:
        logger.error(f"Error loading configuration: {e}", exc_info=True)
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
        logger.error(f"Error decoding JSON data: {e}", exc_info=True)
        sys.exit(1)

    # Extract UID from data
    uid = data.get('uid')

    if not uid:
        logger.error("Missing required UID in the data.")
        sys.exit(1)

    # Extract data_crc32 from data
    data_crc32 = data.get('crc32')
    if data_crc32 is None:
        logger.error("Missing crc32 in the data.")
        sys.exit(1)
    data_crc32 = f"0x{str(data_crc32)}"  # Add '0x' prefix
    logger.debug(f"Received crc32 from data: {data_crc32}")

    # Extract last_saved from data
    data_date = data.get('last_saved')

    if data_date is None:
        logger.error("Missing last_saved in the data.")
        sys.exit(1)
    data_date = str(data_date)
    logger.debug(f"Received last_saved from data: {data_date}")

    pump_data = data.get('pump_data')

    if pump_data is None:
        logger.error("Missing pump_data in the data.")
        sys.exit(1)
    pump_data = str(pump_data)
    logger.debug(f"Received pump_data from data: {pump_data}")

    # Extract database configuration
    db_config = config['database']
    db_host = db_config.get('host')
    db_name = db_config.get('database')
    db_user = db_config.get('user')
    db_password = db_config.get('password')

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
            
            # Split the pump_data into individual rows
            pump_data_rows = pump_data.strip().split('\n')

            for row in pump_data_rows:
                # Split each row into pump_id, capacity, product_name
                parts = row.split(',')
                if len(parts) != 6:
                    logger.error(f"Invalid pump data format: '{row}'. Expected format 'pump_id,capacity,product_name'.")
                    continue

                pump_id_str, pulse_rate_str, tank_id_str, ignore1, ignore2, ignore3 = parts
                try:
                    pump_id = int(pump_id_str)
                except ValueError:
                    logger.error(f"Invalid pump_id '{pump_id_str}' in row: '{row}'. Skipping.")
                    continue

                try:
                    pulse_rate = float(pulse_rate_str)
                except ValueError:
                    logger.error(f"Invalid pulse_rate '{pulse_rate_str}' in row: '{row}'. Skipping.")
                    continue

                try:
                    tank_id = float(tank_id_str)
                except ValueError:
                    logger.error(f"Invalid tank_id '{tank_id_str}' in row: '{row}'. Skipping.")
                    continue



                try:
                    insert_update_query = """
                        INSERT INTO pumps (Nozzle_Number, uid, tank_id, pulse_rate, updated_at)
                        VALUES (%s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            uid = VALUES(uid),
                            Nozzle_Number = VALUES(Nozzle_Number),
                            tank_id = VALUES(tank_id),
                            pulse_rate = VALUES(pulse_rate),
                            updated_at = VALUES(updated_at)
                    """
                    cursor.execute(insert_update_query, (pump_id, uid, tank_id, pulse_rate, data_date))
                    logger.debug(f"Inserted/Updated pump ID {pump_id} successfully.")
                except Error as e:
                    logger.error(f"Error inserting/updating pump ID {pump_id}: {e}", exc_info=True)

            # Commit the transaction to save changes
            connection.commit()
            logger.info(f"All pump records for UID {uid} have been processed and committed to the database.")

            # Update the crc_pump value in the console table
            update_crc_query = "UPDATE console SET crc_pumps = %s WHERE uid = %s;"
            cursor.execute(update_crc_query, (data_crc32, uid))
            connection.commit()
            logger.info(f"Updated crc_pumps in database for UID {uid}")

    except Error as e:
        logger.error(f"Error while connecting to database: {e}", exc_info=True)
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
