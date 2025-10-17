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
log_file = "upd_Dip.log"  # Adjust the path as needed
logger = logging.getLogger("upd_Dip_logger")
logger.setLevel(logging.DEBUG)
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
    parser = argparse.ArgumentParser(description='Dip Processor')
    parser.add_argument('--data', help='Dip data in JSON format')
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
        data_json = sys.stdin.read()

    # Parse the JSON string into a dictionary
    try:
        data = json.loads(data_json)
        logger.debug(f"Parsed data: {data}")
    except json.JSONDecodeError as e:
        logger.error(f"Error decoding JSON data: {e}", exc_info=True)
        sys.exit(1)

    # Get the current datetime once
    now = datetime.now()

    # Format the date and time correctly
    current_date_str = now.strftime("%Y-%m-%d")
    current_time_str = now.strftime("%H:%M:%S")

    # print("Current date (formatted):", current_date_str)
    # print("Current time (formatted):", current_time_str)

    # Extract UID from data
    uid = data.get('UID') or data.get('uid')
    if not uid:
        logger.error("Missing required UID in the data.")
        sys.exit(1)

    dip_data = data.get('dip_data')
    if dip_data is None:
        logger.error("Missing dip_data in the data.")
        sys.exit(1)

    # Now dip_data is expected to be a list of dictionaries.
    logger.debug(f"Received dip_data from data: {dip_data}")

    # Extract database configuration
    db_config = config['database']
    db_host = db_config.get('host')
    db_name = db_config.get('database')
    db_user = db_config.get('user')
    db_password = db_config.get('password')

    # Connect to the database and execute the query
    try:
        connection = mysql.connector.connect(
            host=db_host,
            database=db_name,
            user=db_user,
            password=db_password
        )

        if connection.is_connected():
            cursor = connection.cursor()

            # Process each gauge in the dip_data list
            for gauge in dip_data:
                try:
                    tank_id = int(gauge.get('tank_num'))
                    volume = float(gauge.get('volume'))
                except (ValueError, TypeError) as e:
                    logger.error(f"Invalid gauge data {gauge}: {e}. Skipping.")
                    continue

                # Update dip information in the Tanks table
                try:
                    update_query = """
                        UPDATE Tanks SET current_volume = %s, dipr_date = %s, dipr_time = %s WHERE uid = %s AND tank_id = %s;
                    """
                    cursor.execute(update_query, (volume, current_date_str, current_time_str, uid, tank_id))
                    logger.debug(f"Updated dip for tank {tank_id} successfully.")
                except Error as e:
                    logger.error(f"Error updating tank {tank_id}: {e}", exc_info=True)

            # Commit the transaction
            connection.commit()
            logger.info(f"All dip records for UID {uid} have been processed and committed to the database.")

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
