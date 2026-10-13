const net = require('net');

function createPgSim() {
  const server = net.createServer((socket) => {
    socket.once('data', () => {
      socket.write(Buffer.from([0x00, 0x00, 0x00, 0x08, 0x04, 0xd2, 0x16, 0x2f]));
      socket.destroy();
    });
  });
  server.listen(5432, '127.0.0.1', () => console.log('pg-sim :5432'));
}

function createRedisSim() {
  const server = net.createServer((socket) => {
    socket.on('data', () => {
      socket.write('+PONG\r\n');
    });
  });
  server.listen(6379, '127.0.0.1', () => console.log('redis-sim :6379'));
}

module.exports = { createPgSim, createRedisSim };
