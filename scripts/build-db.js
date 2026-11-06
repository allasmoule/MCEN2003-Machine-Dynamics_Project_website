// Rebuilds database/app.db from the seed files, with no users, sessions or
// messages in it — the clean copy that is committed to git.
//   npm run db:build
const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'database', 'app.db');
const tmp = target + '.build';
for (const f of [tmp, tmp + '-journal']) if (fs.existsSync(f)) fs.unlinkSync(f);

if (process.argv[2] === '--seed-only') {
  require('../server.js'); // creates tables and runs every seeder; does not listen when required
  process.exit(0);
}
// Seed in a child process so the server's DB handle is closed before we clean up.
const { spawnSync } = require('child_process');
const r = spawnSync(process.execPath, [__filename, '--seed-only'], {
  env: { ...process.env, DB_PATH: tmp }, stdio: 'inherit',
});
if (r.status !== 0) process.exit(r.status || 1);

const { DatabaseSync } = require('node:sqlite');
const db = new DatabaseSync(tmp);
for (const t of ['users', 'sessions', 'contact_messages']) {
  try { db.exec(`DELETE FROM ${t}`); } catch (e) {}
}
try { db.exec('DELETE FROM sqlite_sequence WHERE name IN (\'users\', \'sessions\', \'contact_messages\')'); } catch (e) {}
db.exec('VACUUM');
const count = (t) => db.prepare(`SELECT COUNT(*) AS n FROM ${t}`).get().n;
const summary = ['subjects', 'tutorials', 'questions', 'videos', 'pdfs', 'slides', 'notes'].map((t) => `${t}=${count(t)}`).join(' ');
db.close();

fs.renameSync(tmp, target);
console.log('Built database/app.db:', summary);
