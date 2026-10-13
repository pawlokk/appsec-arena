const express = require('express');
const { Pool } = require('pg');

const app = express();
app.use(express.json());

const pool = new Pool({
    host: '127.0.0.1',
    port: 5432,
    database: 'warehouse',
    user: 'postgres',
});

const rulePattern = /^(?:[^']*'|[^"]*"|[^`]*`)[\s\v]*;/;

function wafMiddleware(req, res, next) {
    const body = req.body;
    if (body && typeof body === 'object') {
        for (const val of Object.values(body)) {
            if (typeof val === 'string' && rulePattern.test(val)) {
                return res.status(403).send('');
            }
        }
    }
    next();
}

app.use('/v1/warehouse/pending-events', wafMiddleware);

app.post('/v1/warehouse/pending-events', async (req, res) => {
    const sourceId = req.body?.source_id;
    if (!sourceId) {
        return res.status(400).send('empty source id');
    }
    const q = `SELECT MAX(end_staging_file_id) FROM wh_uploads WHERE wh_uploads.source_id = '${sourceId}';`;
    try {
        const result = await pool.query(q);
        const max = result.rows[0].max;
        res.json({ pending_events: max !== null });
    } catch (err) {
        res.status(500).send(`error getting pending staging file count : ${err.message}`);
    }
});

const eventLog = [];

app.get('/hook/ingest', (req, res) => {
    const data = req.query.data;
    if (data) eventLog.push(data);
    res.sendStatus(200);
});

app.post('/hook/ingest', (req, res) => {
    const data = req.body?.data || req.query.data;
    if (data) eventLog.push(data);
    res.sendStatus(200);
});

app.get('/api/hook/data', (req, res) => {
    res.json({ results: eventLog });
});

app.get('/', (req, res) => {
    res.send(`<html><body>
<h2>DataPipe Warehouse API</h2>
<p>Endpoint: POST /v1/warehouse/pending-events</p>
<p>Body: {"source_id": "src_01HXKJ2MNP"}</p>
<p>Returns pending event count for the given source.</p>
</body></html>`);
});

const PORT = process.env.PORT || 5000;
app.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on port ${PORT}`);
});
