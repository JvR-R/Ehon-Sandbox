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
log_file = "ping_req.log"  # Adjust the path as needed

# Configure the logger
logger = logging.getLogger("ping_req_logger")
logger.setLevel(logging.DEBUG)

# Create a rotating file handler
handler = RotatingFileHandler(log_file, maxBytes=10_000_000, backupCount=5)
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

def load_configuration(config_file_path):
    """Load and return the configuration from the specified file."""
    if not os.path.isfile(config_file_path):
        logger.error(f"Config file not found at: {config_file_path}")
        raise Exception(f"Config file not found at: {config_file_path}")
    config = configparser.ConfigParser()
    config.read(config_file_path)
    return config

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

    # Get the current date and time
    now = datetime.now()
    current_date_str = now.strftime("%Y-%m-%d")
    current_time_str = now.strftime("%H:%M:%S")

    # Extract database configuration
    db_config = config['database']
    db_host = db_config.get('host')
    db_name = db_config.get('database')
    db_user = db_config.get('user')
    db_password = db_config.get('password')

    # Connect to the database and execute the query
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(
            host=db_host,
            database=db_name,
            user=db_user,
            password=db_password
        )

        if connection.is_connected():
            cursor = connection.cursor()

            # Update last_conndate and last_conntime for the given UID in the Console table
            try:
                update_query = """
                    UPDATE Console 
                    SET last_conndate = %s, last_conntime = %s 
                    WHERE uid = %s;
                """
                cursor.execute(update_query, (current_date_str, current_time_str, uid))
                logger.debug(f"Updated Console table for uid {uid} successfully.")
            except Error as e:
                logger.error(f"Error updating uid {uid}: {e}", exc_info=True)

            # Commit the transaction
            connection.commit()
            logger.info(f"All records for UID {uid} have been processed and committed to the database.")

    except Error as e:
        logger.error(f"Error while connecting to or querying the database: {e}")
        sys.exit(1)
    except Exception as e:
        logger.error(f"An unexpected error occurred: {e}")
        sys.exit(1)
    finally:
        # Close cursor and connection if they were opened
        if cursor is not None:
            cursor.close()
        if connection is not None and connection.is_connected():
            connection.close()

if __name__ == "__main__":
    main()
