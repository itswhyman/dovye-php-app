// KRAL AGARİO Live WebSocket Multiplayer Server
const http = require('http');
const WebSocket = require('ws');

const server = http.createServer();
const wss = new WebSocket.Server({ noServer: true });

let clients = new Map();

wss.on('connection', (ws) => {
    const clientId = 'player_' + Math.random().toString(36).substr(2, 9);
    ws.id = clientId;
    clients.set(clientId, ws);

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);
            data.id = clientId;

            // Broadcast player data to all other connected friends
            wss.clients.forEach((client) => {
                if (client !== ws && client.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify(data));
                }
            });
        } catch (e) {}
    });

    ws.on('close', () => {
        clients.delete(clientId);
        wss.clients.forEach((client) => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify({ type: 'leave', id: clientId }));
            }
        });
    });
});

server.on('upgrade', (request, socket, head) => {
    wss.handleUpgrade(request, socket, head, (ws) => {
        wss.emit('connection', ws, request);
    });
});

const PORT = process.env.PORT || 8080;
server.listen(PORT, () => {
    console.log(`🎮 KRAL AGARİO WebSocket Server running on port ${PORT}`);
});
