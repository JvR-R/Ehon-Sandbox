from channels.generic.websocket import AsyncWebsocketConsumer
import json

class FuelQualityConsumer(AsyncWebsocketConsumer):
    async def connect(self):
        print("WebSocket connected")  # Debug output
        await self.channel_layer.group_add("fuel_quality_updates", self.channel_name)
        print("Added to group: fuel_quality_updates")  # Confirm addition to group
        await self.accept()

    async def disconnect(self, close_code):
        print("WebSocket disconnected")
        await self.channel_layer.group_discard("fuel_quality_updates", self.channel_name)

    async def receive(self, text_data):
        print("Received WebSocket message:", text_data)

    async def send_update(self, event):
        print("Sending update to WebSocket client:", event)  # Debug output
        await self.send(text_data=json.dumps(event["message"]))
