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
log_file = "upd_vehicle.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("upd_vehicle_logger")
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
    parser = argparse.ArgumentParser(description='vehicle Processor')
    parser.add_argument('--data', help='vehicle data in JSON format')
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
    uid = data.get('UID') or data.get('uid')

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

    vehicle_data = data.get('vehicle_data')

    if vehicle_data is None:
        logger.error("Missing vehicle_data in the data.")
        sys.exit(1)
    vehicle_data = str(vehicle_data)
    logger.debug(f"Received vehicle_data from data: {vehicle_data}")

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

             # Fetch Client_id and Site_id from Sites table based on uid
            info_query = "SELECT Client_id FROM Sites WHERE uid = %s;"
            cursor.execute(info_query, (uid,))
            info_result = cursor.fetchone()
            if info_result:
                Client_id = info_result[0]  # Extract the first element from the tuple
                logger.debug(f"Fetched Client_id from database: {Client_id}")
            else:
                logger.error(f"No Client_id found for UID {uid} in Sites table.")
                sys.exit(1)

            # Split the vehicle_data into individual rows
            vehicle_data_rows = vehicle_data.strip().split('\n')

            for row in vehicle_data_rows:
                # Split each row into vehicle_id, capacity, product_name
                parts = row.split(',')
                if len(parts) != 3:
                    logger.error(f"Invalid vehicle data format: '{row}'. Expected format 'vehicle_id,capacity,product_name'.")
                    continue

                vehicle_id_str, vehicle_name_str, vehicle_rego_str = parts
                try:
                    vehicle_id = int(vehicle_id_str)
                except ValueError:
                    logger.error(f"Invalid vehicle_id '{vehicle_id_str}' in row: '{row}'. Skipping.")
                    continue

                # try:
                #     capacity = float(capacity_str)
                # except ValueError:
                #     logger.error(f"Invalid capacity '{capacity_str}' in row: '{row}'. Skipping.")
                #     continue

                # Insert or update vehicle information in the vehicles table
                try:
                    insert_update_query = """
                        INSERT INTO vehicles (vehicle_id, Client_id, vehicle_name, vehicle_rego, updated_at)
                        VALUES (%s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            vehicle_id = VALUES(vehicle_id),
                            Client_id = VALUES(Client_id),
                            vehicle_name = VALUES(vehicle_name),
                            vehicle_rego = VALUES(vehicle_rego),
                            updated_at = VALUES(updated_at)
                    """
                    cursor.execute(insert_update_query, (vehicle_id, Client_id, vehicle_name_str, vehicle_rego_str, data_date))
                    logger.debug(f"Inserted/Updated vehicle ID {vehicle_id} successfully.")
                except Error as e:
                    logger.error(f"Error inserting/updating vehicle ID {vehicle_id}: {e}", exc_info=True)

            # Commit the transaction to save changes
            connection.commit()
            logger.info(f"All vehicle records for UID {uid} have been processed and committed to the database.")

            # Update the crc_vehicle value in the console table
            update_crc_query = "UPDATE console SET crc_vehicle = %s WHERE uid = %s;"
            cursor.execute(update_crc_query, (data_crc32, uid))
            connection.commit()
            logger.info(f"Updated crc_vehicle in database for UID {uid}")

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
