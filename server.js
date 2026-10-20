// KRAL AGARİO WebSocket Multiplayer Bridge Server
const http = require('http');
const WebSocket = require('ws');

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('KRAL AGARİO WebSocket Multiplayer Server Active\n');
});

const wss = new WebSocket.Server({ server });

wss.on('connection', (ws) => {
    ws.on('message', (message) => {
        wss.clients.forEach((client) => {
            if (client !== ws && client.readyState === WebSocket.OPEN) {
                client.send(message);
            }
        });
    });
});

const PORT = process.env.PORT || 8080;
server.listen(PORT, () => {
    console.log(`🎮 KRAL AGARİO WebSocket Bridge Server running on port ${PORT}`);
});
