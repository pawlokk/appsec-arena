const puppeteer = require('puppeteer-core');
const pgp = require('pg-promise')();
const db = pgp(process.env.DATABASE_URL);
const PORT = process.env.PORT || 5000;
const INTERVAL = parseInt(process.env.REVIEW_INTERVAL_MS || '15000', 10);
const AGENT_TOKEN = process.env.AGENT_TOKEN || '';

function log(msg) { console.log('[bot] ' + msg); }

async function processReview(visitedUrl) {
  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-features=SameSiteByDefaultCookies,CookiesWithoutSameSiteMustBeSecure'],
    });

    const page = await browser.newPage();
    const appBase = `http://localhost:${PORT}`;

    // Intercept requests to the app and inject auth header
    await page.setRequestInterception(true);
    page.on('request', req => {
      if (req.url().startsWith(appBase)) {
        const headers = { ...req.headers(), 'Authorization': AGENT_TOKEN };
        req.continue({ headers });
      } else {
        req.continue();
      }
    });

    log(`navigating to ${visitedUrl}`);
    await page.goto(visitedUrl, { waitUntil: 'networkidle2', timeout: 15000 });
    await new Promise(r => setTimeout(r, 6000));

  } catch (e) {
    log('error: ' + e.message);
  } finally {
    if (browser) await browser.close();
  }
}

async function processQueue() {
  try {
    const rows = await db.any('SELECT * FROM review_queue WHERE visited = false ORDER BY id LIMIT 5');
    for (const row of rows) {
      await db.none('UPDATE review_queue SET visited = true WHERE id = $1', [row.id]);
      log(`visiting ${row.url}`);
      await processReview(row.url).catch(() => {});
    }
  } catch (e) {}
}

setInterval(processQueue, INTERVAL);
log('started');
