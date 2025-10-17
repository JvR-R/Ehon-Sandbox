import paho.mqtt.client as mqtt
import time

# Define the MQTT broker details
broker_address = "ehonenergytech.com.au"  # Replace with your broker's IP or hostname
broker_port = 1883  # Replace with your broker's port if different
topic = "test/fms"

# Global variable to track if the message is received
message_received = False

# Callback function for when the client receives a CONNACK response from the server
def on_connect(client, userdata, flags, rc):
    print("Connected with result code " + str(rc))
    # Subscribing to the topic with QoS level 2
    client.subscribe(topic, qos=2)
    # Publishing a test message to the topic with QoS level 2
    client.publish(topic, "Test message to Link", qos=2)

# Callback function for when a PUBLISH message is received from the server
def on_message(client, userdata, msg):
    global message_received
    print(f"Message received on topic {msg.topic}: {msg.payload.decode()}")
    message_received = True  # Set the flag when a message is received

# Create a new MQTT client instance
client = mqtt.Client()

# Attach the callback functions
client.on_connect = on_connect
client.on_message = on_message

# Connect to the MQTT broker
client.connect(broker_address, broker_port, 60)

# Start the loop in a non-blocking way
client.loop_start()

# Wait for the message or timeout
start_time = time.time()
timeout = 10  # seconds

while not message_received:
    if time.time() - start_time > timeout:
        print("Timeout: No message received.")
        break
    time.sleep(0.1)  # Small sleep to avoid busy-waiting

# Stop the loop and disconnect
client.loop_stop()
client.disconnect()
print("MQTT client disconnected.")
