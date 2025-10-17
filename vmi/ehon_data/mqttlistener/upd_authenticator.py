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

# Set up logging
log_file = "upd_authenticator.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("upd_authenticator_logger")
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
    parser = argparse.ArgumentParser(description='Authenticator Processor')
    parser.add_argument('--data', help='Authenticator data in JSON format')
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
    uid = data.get('uid')  # Changed to match the JSON key 'UID'

    if not uid:
        logger.error("Missing required UID in the data.")
        sys.exit(1)

    # Extract CRC from data
    data_crc32 = data.get('crc32')
    if data_crc32 is None:
        logger.error("Missing crc32 in the data.")
        sys.exit(1)
    data_crc32 = f"0x{str(data_crc32)}"  # Add '0x' prefix
    logger.debug(f"Received crc32 from data: {data_crc32}")

    # Extract savetime from data
    data_date = data.get('last_saved')  # Changed to match the JSON key 'savetime'

    if data_date is None:
        logger.error("Missing savetime in the data.")
        sys.exit(1)
    data_date = str(data_date)
    logger.debug(f"Received savetime from data: {data_date}")

    authenticator_data = data.get('authenticator_data')  # Changed to match the JSON key 'data'

    if authenticator_data is None:
        logger.error("Missing data in the data payload.")
        sys.exit(1)
    authenticator_data = str(authenticator_data)
    logger.debug(f"Received authenticator_data from data: {authenticator_data}")

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

            # Fetch Client_id from Sites table based on UID
            info_query = "SELECT Client_id FROM Sites WHERE uid = %s;"
            cursor.execute(info_query, (uid,))
            info_result = cursor.fetchone()
            if info_result:
                Client_id = info_result[0]  # Correctly extract the value from the tuple
                logger.debug(f"Fetched Client_id from database: {Client_id}")
            else:
                logger.error(f"No Client_id found for UID {uid} in Sites table.")
                sys.exit(1)

            # Split the authenticator_data into individual rows
            authenticator_data_rows = authenticator_data.strip().split('\n')

            for row in authenticator_data_rows:
                row = row.strip()
                if not row:
                    continue  # Skip empty lines

                # Split each row into 11 fields
                parts = row.split(',')
                if len(parts) != 11:
                    logger.error(f"Invalid authenticator data format: '{row}'. Expected 11 fields separated by commas.")
                    continue

                (
                    authenticator_id_str, 
                    card_number_str, 
                    card_type_str, 
                    list_driver_str, 
                    list_vehicle_str, 
                    driver_prompt_str, 
                    prompt_vehicle_str, 
                    projectnum_prompt_str, 
                    odo_prompt_str, 
                    pin_number_str, 
                    enabled_prompt_str
                ) = parts

                # Convert each field to the appropriate type
                try:
                    authenticator_id = int(authenticator_id_str)
                except ValueError:
                    logger.error(f"Invalid authenticator_id '{authenticator_id_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    card_number = int(card_number_str)
                except ValueError:
                    logger.error(f"Invalid card_number '{card_number_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    card_type = int(card_type_str)
                except ValueError:
                    logger.error(f"Invalid card_type '{card_type_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    list_driver = int(list_driver_str)
                except ValueError:
                    logger.error(f"Invalid list_driver '{list_driver_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    list_vehicle = int(list_vehicle_str)
                except ValueError:
                    logger.error(f"Invalid list_vehicle '{list_vehicle_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    driver_prompt = int(driver_prompt_str)
                except ValueError:
                    logger.error(f"Invalid driver_prompt '{driver_prompt_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    prompt_vehicle = int(prompt_vehicle_str)
                except ValueError:
                    logger.error(f"Invalid prompt_vehicle '{prompt_vehicle_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    projectnum_prompt = int(projectnum_prompt_str)
                except ValueError:
                    logger.error(f"Invalid projectnum_prompt '{projectnum_prompt_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    odo_prompt = int(odo_prompt_str)
                except ValueError:
                    logger.error(f"Invalid odo_prompt '{odo_prompt_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    pin_number = int(pin_number_str)
                    if pin_number == 0:
                        pin_number = None  # Set to None to insert NULL in MySQL
                except ValueError:
                    logger.error(f"Invalid pin_number '{pin_number_str}' in row: '{row}'. Skipping.")
                    continue
                try:
                    enabled_prompt = int(enabled_prompt_str)
                except ValueError:
                    logger.error(f"Invalid enabled_prompt '{enabled_prompt_str}' in row: '{row}'. Skipping.")
                    continue

                # Insert or update authenticator information in the client_tags table
                try:
                    insert_update_query = """
                        INSERT INTO client_tags (
                            id, client_id, card_number, card_type, list_driver, 
                            list_vehicle, driver_prompt, prompt_vehicle, 
                            projectnum_prompt, odo_prompt, pin_number, enabled_prompt, updated_at
                        )
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            client_id = VALUES(client_id),
                            card_number = VALUES(card_number),
                            card_type = VALUES(card_type),
                            list_driver = VALUES(list_driver),
                            list_vehicle = VALUES(list_vehicle),
                            driver_prompt = VALUES(driver_prompt),
                            prompt_vehicle = VALUES(prompt_vehicle),
                            projectnum_prompt = VALUES(projectnum_prompt),
                            odo_prompt = VALUES(odo_prompt),
                            pin_number = VALUES(pin_number),
                            enabled_prompt = VALUES(enabled_prompt),
                            updated_at = VALUES(updated_at)
                    """
                    cursor.execute(insert_update_query, (
                        authenticator_id, Client_id, card_number, card_type, list_driver,
                        list_vehicle, driver_prompt, prompt_vehicle, projectnum_prompt,
                        odo_prompt, pin_number, enabled_prompt, data_date
                    ))
                    logger.debug(f"Inserted/Updated authenticator ID {authenticator_id} successfully.")
                except Error as e:
                    logger.error(f"Error inserting/updating authenticator ID {authenticator_id}: {e}", exc_info=True)

            # Commit the transaction to save changes
            connection.commit()
            logger.info(f"All authenticator records for UID {uid} have been processed and committed to the database.")

            # Update the crc_authenticator value in the console table
            try:
                update_crc_query = "UPDATE console SET crc_auth = %s WHERE uid = %s;"
                cursor.execute(update_crc_query, (data_crc32, uid))
                connection.commit()
                logger.info(f"Updated crc_auth in database for UID {uid}")
            except Error as e:
                logger.error(f"Error updating crc_auth for UID {uid}: {e}", exc_info=True)

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
