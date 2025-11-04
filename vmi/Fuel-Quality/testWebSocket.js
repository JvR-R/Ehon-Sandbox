const WebSocket = require('ws');

const ws = new WebSocket('ws://localhost:8001/ws/fuel-quality/');

ws.on('open', function open() {
  console.log('WebSocket connection established');
});

ws.on('message', function incoming(data) {
  console.log(`Received data: ${data}`);
});

ws.on('close', function close() {
  console.log('WebSocket connection closed');
});

ws.on('error', function error(error) {
  console.error('WebSocket error:', error);
});
