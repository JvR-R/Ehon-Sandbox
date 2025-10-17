import paho.mqtt.client as mqtt
import os

# Define your topic
MQTT_TOPIC = "test/server"

# Define the message filter function
def msg_filter(msg):
    # Decode the message payload to determine the type (PS or PT)
    message_payload = msg.payload.decode("utf-8")  # Decode payload (assuming UTF-8 encoding)
    
    # Split the payload to check for message type
    message_type = message_payload.split('|')[0]  # Split by '|' and get the first part (PS or PT)

    if message_type == "PS":
        handle_ping(message_payload)
    elif message_type == "PT":
        handle_piusi_transaction(message_payload)
    else:
        handle_unknown(message_payload)

# Define handlers for each message type
def handle_ping(payload):
    print(f"Ping message received: {payload}")

def handle_piusi_transaction(payload):
    print(f"Piusi Transaction message received: {payload}")

# Define a handler for unknown message types
def handle_unknown(payload):
    print(f"Unknown message type received: {payload}")
    log_file_path = os.path.join(os.path.dirname(__file__), 'unknown_messages.log')
    
    # Append the unknown message to the log file
    with open(log_file_path, 'a') as log_file:
        log_file.write(f"Unknown message: {payload}\n")

# The callback for when the client receives a CONNACK response from the server
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected successfully to the broker")
        # Subscribing to the topic after connecting
        client.subscribe(MQTT_TOPIC)  # Use the defined topic
    else:
        print(f"Failed to connect, return code {rc}")

# The callback for when a message is received
def on_message(client, userdata, msg):
    print(f"Message received on topic {msg.topic}")
    msg_filter(msg)

# Main function to run the MQTT client
def run_mqtt_client():
    client = mqtt.Client()

    # Set username and password for the MQTT broker
    client.username_pw_set("ehon_fms", "6MrQludp]EfY")

    client.on_connect = on_connect
    client.on_message = on_message

    # Connect to your MQTT broker (replace with your broker's address)
    client.connect("ehonenergytech.com.au", 1883, 60)

    # Blocking loop to process network traffic
    client.loop_forever()

if __name__ == "__main__":
    run_mqtt_client()
