'use strict';

const mongoose  = require('mongoose');
const Handlebars = require('handlebars');
const fs        = require('fs');

const MONGO_URL = 'mongodb://127.0.0.1:27017/slag';
const POLL_MS   = parseInt(process.env.EMAIL_WORKER_INTERVAL_MS || '20000', 10);
const LOG_FILE  = '/tmp/slag_email.log';

function lg(m) {
  const l = `[${new Date().toISOString()}] ${m}\n`;
  process.stdout.write(l);
  try { fs.appendFileSync(LOG_FILE, l); } catch (e) {}
}

const notificationQueueSchema = new mongoose.Schema({
  userId:    String,
  type:      String,
  context:   mongoose.Schema.Types.Mixed,
  status:    { type: String, default: 'pending' },
  createdAt: { type: Date, default: Date.now },
});
const NotificationQueue = mongoose.model('NotificationQueue', notificationQueueSchema);

const userPreferenceSchema = new mongoose.Schema({
  userId:                String,
  notificationTemplate:        String,
  notificationFrequency: String,
  emailAddress:          String,
});
const UserPreference = mongoose.model('UserPreference', userPreferenceSchema);

async function pq() {
  const js = await NotificationQueue.find({ status: 'pending' }).limit(5);
  for (const j of js) {
    try {
      const pf = await UserPreference.findOne({ userId: j.userId });
      const ly = pf && pf.notificationTemplate
        ? pf.notificationTemplate
        : 'Hello, you have a new notification: {{type}}';
      const r = Handlebars.compile(ly)({ type: j.type, ...(j.context || {}) });
      lg(`rendered notification for ${j.userId}: ${r.substring(0, 120)}`);
      j.status = 'sent';
      await j.save();
    } catch (e) {
      lg(`error processing job ${j._id}: ${e.message}`);
      j.status = 'error';
      await j.save();
    }
  }
}

async function mn() {
  lg('email worker starting');
  await mongoose.connect(MONGO_URL);
  lg('connected to MongoDB');
  setInterval(() => {
    pq().catch(e => lg('poll error: ' + e.message));
  }, POLL_MS);
}

mn().catch(e => {
  lg('fatal: ' + e.message);
  process.exit(1);
});
