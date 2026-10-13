const pgp = require('pg-promise')();
const db = pgp(process.env.DATABASE_URL);

async function main() {
  await require('./v0.0.1')(db);
  await require('./v0.69.0')(db);
  pgp.end();
}

main().catch(e => { console.error(e); process.exit(1); });
