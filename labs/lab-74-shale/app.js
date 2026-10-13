const gatewayApp = require('./services/gateway');
const filesApp = require('./services/files');
const renderApp = require('./services/render');
const adminApp = require('./services/admin');
const { createPgSim, createRedisSim } = require('./services/sims');

gatewayApp.listen(5000, () => console.log('gateway :5000'));
filesApp.listen(8055, '127.0.0.1', () => console.log('files :8055'));
renderApp.listen(9000, '127.0.0.1', () => console.log('render :9000'));
adminApp.listen(8001, '127.0.0.1', () => console.log('admin :8001'));
createPgSim();
createRedisSim();
