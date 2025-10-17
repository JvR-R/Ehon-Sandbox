import paho.mqtt.client as mqtt
import logging
from logging.handlers import RotatingFileHandler
import json
import subprocess
import os
import configparser
import argparse
from concurrent.futures import ThreadPoolExecutor

# Set up logging
log_file = "mqtt_subscriber.log"  # You can change the path as needed

# Configure the logger
logger = logging.getLogger("mqtt_logger")
logger.setLevel(logging.DEBUG)

# Create a rotating file handler
handler = RotatingFileHandler(log_file, maxBytes=10000000, backupCount=5)  # 10MB log size, keep up to 5 backups
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

# Add a StreamHandler to output logs to stderr (optional)
stream_handler = logging.StreamHandler()
stream_handler.setFormatter(formatter)
logger.addHandler(stream_handler)

# Initialize ThreadPoolExecutor
executor = ThreadPoolExecutor(max_workers=10)  # Adjust as needed

def load_configuration(config_file_path):
    if not os.path.isfile(config_file_path):
        logger.error(f"Config file not found at: {config_file_path}")
        raise Exception(f"Config file not found at: {config_file_path}")
    config = configparser.ConfigParser()
    config.read(config_file_path)
    return config

# Handler functions
def handle_PT(data):
    logger.info("Message type is 'PT', triggering cs_transactions.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_transactions.py")
    config_path = args.config  # Use the same config file path

    # Serialize the 'data' dictionary to a JSON string
    uid = data.get('UID')
    data_json = json.dumps({
        'uid': uid,
        'data': data['data']
    }, ensure_ascii=False)
    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_transactions.py executed successfully")
    else:
        logger.error(f"cs_transactions.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def handle_RG(data):
    logger.info("Message type is 'RG', triggering cs_registration.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_registration.py")
    config_path = args.config  # Use the same config file path

    # Serialize the 'data' dictionary to a JSON string
    data_json = json.dumps(data['data'], ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_registration.py executed successfully")
    else:
        logger.error(f"cs_registration.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def handle_Tank(data):
    logger.info("Message type is 'TN', triggering cs_tank.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_tank.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')
    crc32 = data.get('data', {}).get('crc32')
    last_saved = data.get('data', {}).get('last_saved')

    # Prepare data to send to cs_tank.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_tank.py executed successfully")
    else:
        logger.error(f"cs_tank.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def handle_Pump(data):
    logger.info("Message type is 'PM', triggering cs_pump.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_pump.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')
    crc32 = data.get('data', {}).get('crc32')
    last_saved = data.get('data', {}).get('last_saved')

    # Prepare data to send to cs_pump.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_pump.py executed successfully")
    else:
        logger.error(f"cs_pump.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def handle_Driver(data):
    logger.info("Message type is 'DR', triggering cs_driver.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_driver.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')
    crc32 = data.get('data', {}).get('crc32')
    last_saved = data.get('data', {}).get('last_saved')

    # Prepare data to send to cs_driver.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_driver.py executed successfully")
    else:
        logger.error(f"cs_driver.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def handle_Vehicle(data):
    logger.info("Message type is 'VH', triggering cs_vehicle.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_vehicle.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')
    crc32 = data.get('data', {}).get('crc32')
    last_saved = data.get('data', {}).get('last_saved')

    # Prepare data to send to cs_vehicle.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_vehicle.py executed successfully")
    else:
        logger.error(f"cs_vehicle.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def handle_Authenticator(data):
    logger.info("Message type is 'AU', triggering cs_auth.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "cs_auth.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')
    crc32 = data.get('data', {}).get('crc32')
    last_saved = data.get('data', {}).get('last_saved')

    # Prepare data to send to cs_auth.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("cs_auth.py executed successfully")
    else:
        logger.error(f"cs_auth.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def update_Tank(data):
    logger.info("Message type is 'TU', triggering upd_tank.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "upd_tank.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('UID')
    crc32 = data.get('CRC')
    last_saved = data.get('savetime')
    tank_data = data.get('data')

    # Prepare data to send to cs_tank.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved,
        'tank_data': tank_data
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("upd_tank.py executed successfully")
    else:
        logger.error(f"upd_tank.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def update_Pump(data):
    logger.info("Message type is 'PU', triggering upd_pump.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "upd_pump.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('UID')
    crc32 = data.get('CRC')
    last_saved = data.get('savetime')
    pump_data = data.get('data')

    # Prepare data to send to cs_tank.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved,
        'pump_data': pump_data
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("upd_pump.py executed successfully")
    else:
        logger.error(f"upd_pump.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def update_Driver(data):
    logger.info("Message type is 'DU', triggering upd_driver.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "upd_driver.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('UID')
    crc32 = data.get('CRC')
    last_saved = data.get('savetime')
    driver_data = data.get('data')

    # Prepare data to send to upd_driver.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved,
        'driver_data': driver_data
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("upd_driver.py executed successfully")
    else:
        logger.error(f"upd_driver.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def update_Vehicle(data):
    logger.info("Message type is 'VU', triggering upd_vehicle.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "upd_vehicle.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('UID')
    crc32 = data.get('CRC')
    last_saved = data.get('savetime')
    vehicle_data = data.get('data')

    # Prepare data to send to upd_vehicle.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved,
        'vehicle_data': vehicle_data
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("upd_vehicle.py executed successfully")
    else:
        logger.error(f"upd_vehicle.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def update_Authenticator(data):
    logger.info("Message type is 'AUU', triggering upd_authenticator.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "upd_authenticator.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('UID')
    crc32 = data.get('CRC')
    last_saved = data.get('savetime')
    authenticator_data = data.get('data')

    # Prepare data to send to upd_vehicle.py
    data_json = json.dumps({
        'uid': uid,
        'crc32': crc32,
        'last_saved': last_saved,
        'authenticator_data': authenticator_data
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("upd_authenticator.py executed successfully")
    else:
        logger.error(f"upd_authenticator.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")


def update_dipreading(data):
    logger.info("Message type is 'DIP', triggering upd_dip.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "upd_dip.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')
    dip_data = data.get('gauges')

    # Prepare data to send to cs_tank.py
    data_json = json.dumps({
        'uid': uid,
        'dip_data': dip_data
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("upd_dip.py executed successfully")
    else:
        logger.error(f"upd_dip.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def ping_response(data):
    logger.info("Message type is 'PING', triggering ping_response.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "ping_response.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')

    # Prepare data to send to cs_tank.py
    data_json = json.dumps({
        'uid': uid
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("ping_response.py executed successfully")
    else:
        logger.error(f"ping_response.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def ping_req(data):
    logger.info("Message type is 'OK', triggering ping_req.py")
    current_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(current_dir, "ping_req.py")
    config_path = args.config  # Use the same config file path

    # Extract uid, crc32, last_saved
    uid = data.get('uid')

    # Prepare data to send to cs_tank.py
    data_json = json.dumps({
        'uid': uid
    }, ensure_ascii=False)

    # Execute the script and pass the data via stdin
    process = subprocess.Popen(
        [
            'python3', script_path,
            '--config', config_path
        ],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    stdout, stderr = process.communicate(input=data_json)
    if process.returncode == 0:
        logger.info("ping_req.py executed successfully")
    else:
        logger.error(f"ping_req.py execution failed with return code {process.returncode}")
        logger.error(f"Error output: {stderr}")

def state(data):
    logger.info("Skipping message type 'state'")
    pass

# Dispatch table
message_handlers = {
    'PT': handle_PT,
    'RG': handle_RG,  # Uncomment if needed
    'TN': handle_Tank,
    'PM': handle_Pump,
    'DR': handle_Driver,
    'VH': handle_Vehicle,
    'AU': handle_Authenticator,
    'TU': update_Tank,
    'PU': update_Pump,
    'DU': update_Driver,
    'VU': update_Vehicle,
    'AUU': update_Authenticator,
    'DIP': update_dipreading,
    'PING': ping_response,
    'OK': ping_req,
    'state': state,
    # Add more handlers as needed
}

# Callback when the client receives a message from the broker
def on_message(client, userdata, message):
    try:
        payload = message.payload.decode('utf-8')
        log_msg = f"Received message on topic {message.topic}"
        logger.info(log_msg)

        data = json.loads(payload)

        # Check if message should be ignored
        # if should_ignore_message(data):
        #     uid = data.get('uid') or data.get('data', {}).get('uid')
        #     logger.info(f"Ignoring message with uid {uid}")
        #     return

        msg_type = data.get("type")
        handler = message_handlers.get(msg_type)

        if handler:
            # Submit the handler function to the executor
            executor.submit(handler, data)
        else:
            logger.warning(f"No handler found for message type '{msg_type}'")
    except json.JSONDecodeError as e:
        logger.error(f"Failed to decode JSON message: {e}")
    except Exception as e:
        logger.error(f"Error processing message: {e}")

# Callback when the client connects to the broker
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        logger.info("Connected to broker")
        if not flags.get('session present', 0):
            logger.info("No existing session. Subscribing to topic.")
            client.subscribe("fms_server", qos=1)
        else:
            logger.info("Session resumed. No need to resubscribe.")
    else:
        logger.error(f"Failed to connect, return code {rc}")

def on_disconnect(client, userdata, rc):
    logger.info("Disconnected from broker")

def mqtt_subscribe(config):
    global client  # Make client accessible in other functions
    # Extract MQTT settings from configuration
    mqtt_config = config['mqtt']
    broker_host = mqtt_config.get('broker_host')
    broker_port = int(mqtt_config.get('broker_port'))
    mqtt_username = mqtt_config.get('username')
    mqtt_password = mqtt_config.get('password')

    # Create a new MQTT client instance
    client_id = mqtt_config.get('client_name')  # Replace with a unique, consistent client ID
    client = mqtt.Client(client_id=client_id, clean_session=False)
    client.will_set("fms_server/status", payload="DISCONNECTED", qos=1, retain=True)

    # Set username and password (if required)
    client.username_pw_set(mqtt_username, mqtt_password)

    # Attach the callbacks
    client.on_connect = on_connect
    client.on_message = on_message
    client.on_disconnect = on_disconnect

    # Connect to the MQTT broker
    client.connect(broker_host, port=broker_port, keepalive=900)

    # Keep the client running
    client.loop_forever()

# def should_ignore_message(data):
#     uid = data.get('uid') or data.get('data', {}).get('uid')
#     return str(uid) == "398312"

def main():
    global args  # Make args accessible in other functions
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description='MQTT Subscriber')
    parser.add_argument('--config', required=True, help='Path to configuration file')
    args = parser.parse_args()

    # Load configuration
    config = load_configuration(args.config)

    # Start MQTT subscription
    mqtt_subscribe(config)

if __name__ == "__main__":
    main()
