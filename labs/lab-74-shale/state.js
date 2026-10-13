const RENDER_API_KEY = 'SBzrCb94o9JOWALBvDAZLnHo3s90smjC';
const RENDER_CONSUMER_ID = 'a8c78b54-1d08-43f8-acd2-fb2c7be9e893';
const RENDER_KEY_ID = 'c34c38b6-4589-4a1e-a8f7-d2277f9fe405';
const RENDER_CREATED_AT = 1613767827;

const services = new Map();
const serviceRoutes = new Map();
const servicePlugins = new Map();
const routeToService = new Map();
const httpLogEndpoint = { url: null };

const uploadedAssets = new Map();
const eventLog = [];

module.exports = {
  RENDER_API_KEY, RENDER_CONSUMER_ID, RENDER_KEY_ID, RENDER_CREATED_AT,
  services, serviceRoutes, servicePlugins, routeToService, httpLogEndpoint,
  uploadedAssets, eventLog,
};
