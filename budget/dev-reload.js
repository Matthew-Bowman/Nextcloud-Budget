const WebSocket = require("ws");
const chokidar = require("chokidar");
const path = require("path");

const ROOT = __dirname; // directory where dev-reload.js lives (budget/)

const wss = new WebSocket.Server({ port: 35729 });
console.log("Dev reload server running on port 35729");

const watcher = chokidar.watch(
    [
        'css/style.css',
        'css/icons.css',
        'css/budget-main.css',
        'css/budget-main.css.map',
    ],
    { ignoreInitial: true }
);

watcher.on("all", (event, filePath) => {
    console.log("Changed:", event, filePath);
    wss.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) client.send(JSON.stringify({ message: 'reload', path: filePath }));
    });
});
