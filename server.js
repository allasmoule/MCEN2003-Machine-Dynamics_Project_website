const express = require('express');
const cookieParser = require('cookie-parser');
const path = require('path');
const fs = require('fs');
const { DatabaseSync } = require('node:sqlite');

const app = express();
const PORT = process.env.PORT || 3000;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());

// Initialize SQLite database
const dbPath = path.join(__dirname, 'app.db');
const db = new DatabaseSync(dbPath);

// Create tables
db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    phone TEXT NOT NULL,
    batch TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    institution TEXT,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS tutorials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_id INTEGER NOT NULL,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subject_id, slug),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutorial_id INTEGER NOT NULL,
    q_key TEXT NOT NULL,
    code TEXT NOT NULL,
    topic TEXT NOT NULL,
    title TEXT NOT NULL,
    statement TEXT NOT NULL,
    sketch TEXT,
    fig TEXT,
    fig_caption TEXT,
    given_json TEXT NOT NULL,
    hint_json TEXT NOT NULL,
    seed TEXT,
    parts_json TEXT NOT NULL,
    steps_json TEXT NOT NULL,
    original TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tutorial_id, q_key),
    UNIQUE(tutorial_id, code),
    FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutorial_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS content_blocks (
    block_key TEXT PRIMARY KEY,
    value_json TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS sessions (
    token TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    user_name TEXT NOT NULL,
    user_email TEXT NOT NULL,
    is_admin INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT DEFAULT 'General Inquiry',
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );
`);

try {
  db.exec('ALTER TABLE sessions ADD COLUMN is_admin INTEGER DEFAULT 0;');
} catch (e) {}

// Seed Data helper
function seedData() {
  // Check if subject exists
  const checkSubj = db.prepare('SELECT id FROM subjects WHERE slug = ?').get('mcen2003-machine-dynamics');
  let subjectId;
  if (!checkSubj) {
    const insertSubj = db.prepare(`
      INSERT INTO subjects (slug, name, institution, description, sort_order)
      VALUES (?, ?, ?, ?, ?)
    `);
    const res = insertSubj.run('mcen2003-machine-dynamics', 'MCEN2003 Machine Dynamics', 'Curtin University', 'Interactive Tutorial Workbook for Kinematics.', 1);
    subjectId = res.lastInsertRowid;
  } else {
    subjectId = checkSubj.id;
  }

  // Check if tutorial exists
  const checkTut = db.prepare('SELECT id FROM tutorials WHERE subject_id = ? AND slug = ?').get(subjectId, 'tutorial-1');
  let tutorialId;
  if (!checkTut) {
    const insertTut = db.prepare(`
      INSERT INTO tutorials (subject_id, slug, title, description, sort_order)
      VALUES (?, ?, ?, ?, ?)
    `);
    const res = insertTut.run(subjectId, 'tutorial-1', 'Tutorial 1 — Kinematics', 'Interactive tutorial questions with diagrams & step-by-step reasoning.', 1);
    tutorialId = res.lastInsertRowid;
  } else {
    tutorialId = checkTut.id;
  }

  // Seed questions if empty
  const qCount = db.prepare('SELECT COUNT(*) as cnt FROM questions WHERE tutorial_id = ?').get(tutorialId);
  if (qCount.cnt === 0) {
    const questionsSeedPath = path.join(__dirname, 'seed', 'questions_seed.json');
    if (fs.existsSync(questionsSeedPath)) {
      const questionsData = JSON.parse(fs.readFileSync(questionsSeedPath, 'utf8'));
      const insertQ = db.prepare(`
        INSERT INTO questions (
          tutorial_id, q_key, code, topic, title, statement, sketch, fig, fig_caption,
          given_json, hint_json, seed, parts_json, steps_json, original, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `);
      let order = 10;
      for (const q of questionsData) {
        insertQ.run(
          tutorialId,
          q.id,
          q.code,
          q.topic,
          q.title,
          q.statement,
          q.sketch || null,
          q.fig || null,
          q.figCap || null,
          JSON.stringify(q.given),
          JSON.stringify(q.hint),
          q.seed || null,
          JSON.stringify(q.parts),
          JSON.stringify(q.steps),
          q.original,
          order
        );
        order += 10;
      }
      console.log(`Seeded ${questionsData.length} questions for Tutorial 1.`);
    }
  }

  // Seed formula sheet if empty
  const checkFS = db.prepare('SELECT block_key FROM content_blocks WHERE block_key = ?').get('formula_sheet');
  if (!checkFS) {
    const fsSeedPath = path.join(__dirname, 'seed', 'formula_sheet_seed.json');
    if (fs.existsSync(fsSeedPath)) {
      const fsData = fs.readFileSync(fsSeedPath, 'utf8');
      db.prepare('INSERT INTO content_blocks (block_key, value_json) VALUES (?, ?)').run('formula_sheet', fsData);
      console.log('Seeded formula_sheet.');
    }
  }
}

seedData();

// Helper to get session user
function getSessionUser(req) {
  const token = req.cookies.session_token;
  if (!token) return null;
  const sess = db.prepare('SELECT user_id, user_name, user_email, is_admin FROM sessions WHERE token = ?').get(token);
  if (!sess) return null;
  return { id: sess.user_id, name: sess.user_name, email: sess.user_email, is_admin: sess.is_admin === 1 };
}

// API Routes
app.get('/api/me.php', (req, res) => {
  const user = getSessionUser(req);
  res.json({ user });
});

app.post('/api/demo_login.php', (req, res) => {
  const demoEmail = 'demo@mcen2003.local';
  const demoName = 'Demo Student';
  let user = db.prepare('SELECT id, name, email FROM users WHERE email = ?').get(demoEmail);

  if (!user) {
    const insert = db.prepare('INSERT INTO users (name, email, phone, batch, password_hash) VALUES (?, ?, ?, ?, ?)');
    const result = insert.run(demoName, demoEmail, '0000000000', 'Demo', 'demohash');
    user = { id: result.lastInsertRowid, name: demoName, email: demoEmail };
  }

  const token = 'token_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
  db.prepare('INSERT INTO sessions (token, user_id, user_name, user_email, is_admin) VALUES (?, ?, ?, ?, ?)').run(token, user.id, user.name, user.email, 0);

  res.cookie('session_token', token, { httpOnly: true });
  res.json({ ok: true, user });
});

app.post('/api/login.php', (req, res) => {
  const { email, password } = req.body || {};
  if (!email || !password) {
    return res.status(422).json({ error: 'Email and password are required.' });
  }

  const cleanEmail = email.toString().trim().toLowerCase();

  // Check Admin Login
  const adminEmails = ['raju.ahamedruet07@gmail.com', 'admin@mcen2003.local', 'admin@example.com', 'your-admin-email@example.com'];
  if (adminEmails.includes(cleanEmail) && (password === 'Admin@@@@!!!!' || password === 'admin123' || password === 'your-new-password')) {
    const token = 'admin_token_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
    db.prepare('INSERT INTO sessions (token, user_id, user_name, user_email, is_admin) VALUES (?, ?, ?, ?, ?)').run(token, 0, 'Prof. Md. Roju Ahomed', cleanEmail, 1);
    res.cookie('session_token', token, { httpOnly: true });
    return res.json({ ok: true, admin: true });
  }

  // Check Student User Login
  const user = db.prepare('SELECT id, name, email, password_hash FROM users WHERE LOWER(email) = ?').get(cleanEmail);
  if (!user) {
    return res.status(401).json({ error: 'Incorrect email or password.' });
  }

  const token = 'token_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
  db.prepare('INSERT INTO sessions (token, user_id, user_name, user_email, is_admin) VALUES (?, ?, ?, ?, ?)').run(token, user.id, user.name, user.email, 0);

  res.cookie('session_token', token, { httpOnly: true });
  res.json({ ok: true, user: { id: user.id, name: user.name, email: user.email } });
});

app.post('/api/signup.php', (req, res) => {
  const { name, email, phone, batch, password } = req.body || {};
  if (!name || !email || !phone || !batch || !password) {
    return res.status(422).json({ error: 'All fields are required.' });
  }
  if (password.length < 6) {
    return res.status(422).json({ error: 'Password must be at least 6 characters.' });
  }

  const existing = db.prepare('SELECT id FROM users WHERE email = ?').get(email.trim());
  if (existing) {
    return res.status(409).json({ error: 'An account with this email already exists.' });
  }

  const insert = db.prepare('INSERT INTO users (name, email, phone, batch, password_hash) VALUES (?, ?, ?, ?, ?)');
  const result = insert.run(name.trim(), email.trim(), phone.trim(), batch.trim(), password);
  const userId = result.lastInsertRowid;

  const token = 'token_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
  db.prepare('INSERT INTO sessions (token, user_id, user_name, user_email, is_admin) VALUES (?, ?, ?, ?, ?)').run(token, userId, name.trim(), email.trim(), 0);

  res.cookie('session_token', token, { httpOnly: true });
  res.json({ ok: true, user: { id: userId, name: name.trim(), email: email.trim() } });
});

app.post('/api/logout.php', (req, res) => {
  const token = req.cookies.session_token;
  if (token) {
    db.prepare('DELETE FROM sessions WHERE token = ?').run(token);
  }
  res.clearCookie('session_token');
  res.json({ ok: true });
});

app.get('/api/subjects.php', (req, res) => {
  const rows = db.prepare(`
    SELECT s.id, s.slug, s.name, s.institution, s.description,
           (SELECT COUNT(*) FROM tutorials t WHERE t.subject_id = s.id) AS tutorial_count
    FROM subjects s ORDER BY s.sort_order ASC, s.id ASC
  `).all();
  res.json({ subjects: rows });
});

app.get('/api/tutorials.php', (req, res) => {
  const slug = (req.query.subject || '').toString().trim();
  if (!slug) {
    return res.status(422).json({ error: 'subject is required' });
  }

  const subject = db.prepare('SELECT id, slug, name, institution, description FROM subjects WHERE slug = ?').get(slug);
  if (!subject) {
    return res.status(404).json({ error: 'Subject not found' });
  }

  const tutorials = db.prepare(`
    SELECT t.id, t.slug, t.title, t.description,
           (SELECT COUNT(*) FROM questions q WHERE q.tutorial_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM videos v WHERE v.tutorial_id = t.id) AS video_count
    FROM tutorials t WHERE t.subject_id = ? ORDER BY t.sort_order ASC, t.id ASC
  `).all(subject.id);

  res.json({ subject, tutorials });
});

app.get('/api/questions.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user) {
    return res.status(401).json({ error: 'Not authenticated' });
  }

  let tutorialId = parseInt(req.query.tutorial, 10) || 0;
  if (!tutorialId) {
    const first = db.prepare('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1').get();
    tutorialId = first ? first.id : 0;
  }

  const tutorial = db.prepare(`
    SELECT t.id, t.title, t.description, s.name AS subject_name
    FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?
  `).get(tutorialId);

  if (!tutorial) {
    return res.status(404).json({ error: 'Tutorial not found' });
  }

  const rows = db.prepare('SELECT * FROM questions WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);

  const questions = rows.map((r) => {
    const q = {
      id: r.q_key,
      code: r.code,
      topic: r.topic,
      title: r.title,
      statement: r.statement,
      given: JSON.parse(r.given_json),
      hint: JSON.parse(r.hint_json),
      parts: JSON.parse(r.parts_json),
      steps: JSON.parse(r.steps_json),
      original: r.original,
    };
    if (r.sketch) q.sketch = r.sketch;
    if (r.fig) q.fig = r.fig;
    if (r.fig_caption) q.figCap = r.fig_caption;
    if (r.seed) q.seed = r.seed;
    return q;
  });

  res.json({
    tutorial: {
      id: tutorial.id,
      title: tutorial.title,
      description: tutorial.description,
      subject_name: tutorial.subject_name,
    },
    questions,
  });
});

app.get('/api/formula_sheet.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user) {
    return res.status(401).json({ error: 'Not authenticated' });
  }

  const tutorialId = parseInt(req.query.tutorial, 10) || 0;
  let row = null;
  if (tutorialId) {
    row = db.prepare('SELECT value_json FROM content_blocks WHERE block_key = ?').get('formula_sheet_t' + tutorialId);
  }
  if (!row) {
    row = db.prepare('SELECT value_json FROM content_blocks WHERE block_key = ?').get('formula_sheet');
  }

  res.json({ formula_sheet: row ? JSON.parse(row.value_json) : null });
});

app.get('/api/videos.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user) {
    return res.status(401).json({ error: 'Not authenticated' });
  }

  let tutorialId = parseInt(req.query.tutorial, 10) || 0;
  if (!tutorialId) {
    const first = db.prepare('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1').get();
    tutorialId = first ? first.id : 0;
  }

  const videos = db.prepare('SELECT title, url, description FROM videos WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);
  res.json({ videos });
});

app.post('/api/contact.php', (req, res) => {
  const { name, email, phone, message, type } = req.body || {};
  if (!name || !email || !phone || !message) {
    return res.status(422).json({ error: 'All fields (Name, Email, Phone, Message) are required.' });
  }

  const stmt = db.prepare('INSERT INTO contact_messages (type, name, email, phone, message) VALUES (?, ?, ?, ?, ?)');
  stmt.run((type || 'General Inquiry').toString().trim(), name.toString().trim(), email.toString().trim(), phone.toString().trim(), message.toString().trim());

  res.json({ ok: true, message: 'Thank you! Your message has been sent successfully.' });
});

// Admin Layout Renderer
function renderAdminLayout(title, activeTab, contentHtml) {
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title} — Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <span class="admin-brand-mark">Admin</span>
      <span class="admin-brand-sub">MCEN2003 Machine Dynamics</span>
    </div>
    <nav class="admin-nav">
      <a href="/admin/index.php" class="${activeTab === 'students' ? 'active' : ''}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Students
      </a>
      <a href="/admin/messages.php" class="${activeTab === 'messages' ? 'active' : ''}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 17a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10z"/><polyline points="22,7 12,13 2,7"/></svg>
        Contact Messages
      </a>
      <a href="/admin/subjects.php" class="${activeTab === 'subjects' ? 'active' : ''}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
        Subjects
      </a>
      <a href="/admin/formula_sheet.php" class="${activeTab === 'formula' ? 'active' : ''}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2h10v12H3z"/><path d="M5.2 5h5.6M5.2 8h5.6M5.2 11h3.2"/></svg>
        Formula Sheet
      </a>
    </nav>
    <a class="admin-logout" href="/index.html">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Logout
    </a>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <h1>${title}</h1>
      </div>
    </header>
    <main class="admin-content">
      ${contentHtml}
    </main>
  </div>
</div>
</body>
</html>`;
}

// Admin Panel Routes

// 1. Students List
app.get(['/admin', '/admin/index.php'], (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const users = db.prepare('SELECT id, name, email, phone, batch, created_at FROM users ORDER BY created_at DESC').all();

  let tableRows = users.map(u => `
    <tr>
      <td>${u.name}</td>
      <td>${u.email}</td>
      <td>${u.phone}</td>
      <td>${u.batch}</td>
      <td>${u.created_at}</td>
      <td class="row-actions">
        <form method="post" action="/admin/delete-student" onsubmit="return confirm('Delete this student?');">
          <input type="hidden" name="delete_id" value="${u.id}">
          <button type="submit" style="color:#DC2626; background:transparent; border:1px solid #FCA5A5; padding:4px 8px; border-radius:4px; cursor:pointer;">Delete</button>
        </form>
      </td>
    </tr>
  `).join('');

  const bodyHtml = `
    <div class="toolbar">
      <span class="meta">${users.length} registered student${users.length === 1 ? '' : 's'}</span>
      <input type="text" id="search" placeholder="Search name, email, batch&hellip;" style="max-width:260px; padding:6px 10px; border:1px solid #CBD5E1; border-radius:4px;">
    </div>
    ${users.length === 0 ? '<div class="empty">No students have signed up yet.</div>' : `
      <table id="userTable" style="width:100%; border-collapse:collapse; margin-top:16px;">
        <thead>
          <tr style="text-align:left; border-bottom:2px solid #E2E8F0; padding:10px;"><th>Name</th><th>Email</th><th>Phone</th><th>Batch</th><th>Registered</th><th></th></tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
    `}
    <script>
      document.getElementById('search') && document.getElementById('search').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#userTable tbody tr').forEach(function (row) {
          row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
      });
    </script>
  `;

  res.send(renderAdminLayout('Students', 'students', bodyHtml));
});

app.post('/admin/delete-student', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');
  const deleteId = parseInt(req.body.delete_id, 10);
  if (deleteId) {
    db.prepare('DELETE FROM users WHERE id = ?').run(deleteId);
  }
  res.redirect('/admin/index.php');
});

// 2. Contact Messages List
app.get('/admin/messages.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const messages = db.prepare('SELECT id, type, name, email, phone, message, is_read, created_at FROM contact_messages ORDER BY created_at DESC').all();

  let tableRows = messages.map(m => `
    <tr style="${m.is_read ? 'opacity: 0.75;' : 'font-weight: 600; background: rgba(16, 42, 86, 0.02);'}">
      <td>
        <span class="badge" style="background: ${m.is_read ? '#F1F5F9; color:#64748B' : '#E0EEF3; color:#0F5F7D'}; font-size: 11px; padding: 3px 8px; border-radius: 12px; display: inline-block;">
          ${m.is_read ? 'Read' : 'New'}
        </span>
      </td>
      <td>
        <span class="badge" style="background: #EEF2FF; color: #102A56; font-size: 11px; padding: 3px 8px; border-radius: 12px; display: inline-block;">
          ${m.type || 'General Inquiry'}
        </span>
      </td>
      <td>
        <strong>${m.name}</strong><br>
        <small style="color: #475569;"><a href="mailto:${m.email}">${m.email}</a></small><br>
        <small style="color: #64748B;">📞 <a href="tel:${m.phone}">${m.phone}</a></small>
      </td>
      <td style="max-width: 320px; white-space: pre-wrap; font-weight: normal; font-size: 13.5px; line-height: 1.5; color: #334155;">
        ${m.message}
      </td>
      <td style="font-size: 12px; color: #64748B; white-space: nowrap;">
        ${m.created_at}
      </td>
      <td class="row-actions">
        <form method="post" action="/admin/toggle-read" style="display:inline-block; margin-right: 4px;">
          <input type="hidden" name="toggle_read_id" value="${m.id}">
          <button type="submit" style="background: transparent; border: 1px solid #CBD5E1; border-radius: 4px; padding: 4px 8px; font-size: 12px; cursor: pointer;">
            ${m.is_read ? 'Unread' : 'Mark Read'}
          </button>
        </form>
        <form method="post" action="/admin/delete-message" style="display:inline-block;" onsubmit="return confirm('Delete this message?');">
          <input type="hidden" name="delete_id" value="${m.id}">
          <button type="submit" style="color: #DC2626; background: transparent; border: 1px solid #FCA5A5; border-radius: 4px; padding: 4px 8px; font-size: 12px; cursor: pointer;">Delete</button>
        </form>
      </td>
    </tr>
  `).join('');

  const bodyHtml = `
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <span class="meta" style="font-weight:600; font-size:14px;">${messages.length} contact message${messages.length === 1 ? '' : 's'}</span>
      <input type="text" id="searchMsg" placeholder="Search name, email, phone, message&hellip;" style="max-width:280px; padding:6px 10px; border:1px solid #CBD5E1; border-radius:4px;">
    </div>
    ${messages.length === 0 ? '<div class="empty">No contact messages received yet.</div>' : `
      <table id="msgTable" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="text-align:left; border-bottom:2px solid #E2E8F0; font-size:13px; color:#475569;">
            <th style="padding:10px;">Status</th>
            <th style="padding:10px;">Type</th>
            <th style="padding:10px;">Sender Details</th>
            <th style="padding:10px;">Message</th>
            <th style="padding:10px;">Received</th>
            <th style="padding:10px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
    `}
    <script>
      document.getElementById('searchMsg') && document.getElementById('searchMsg').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#msgTable tbody tr').forEach(function (row) {
          row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
      });
    </script>
  `;

  res.send(renderAdminLayout('Contact Messages', 'messages', bodyHtml));
});

app.post('/admin/toggle-read', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');
  const msgId = parseInt(req.body.toggle_read_id, 10);
  if (msgId) {
    db.prepare('UPDATE contact_messages SET is_read = CASE WHEN is_read = 1 THEN 0 ELSE 1 END WHERE id = ?').run(msgId);
  }
  res.redirect('/admin/messages.php');
});

app.post('/admin/delete-message', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');
  const msgId = parseInt(req.body.delete_id, 10);
  if (msgId) {
    db.prepare('DELETE FROM contact_messages WHERE id = ?').run(msgId);
  }
  res.redirect('/admin/messages.php');
});

// 3. Subjects Management
app.get('/admin/subjects.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subjects = db.prepare(`
    SELECT s.*, (SELECT COUNT(*) FROM tutorials t WHERE t.subject_id = s.id) AS tutorial_count
    FROM subjects s ORDER BY s.sort_order ASC, s.id ASC
  `).all();

  let tableRows = subjects.map(s => `
    <tr style="border-bottom:1px solid #E2E8F0;">
      <td style="padding:12px;"><strong>${s.name}</strong></td>
      <td style="padding:12px; color:#475569;">${s.institution || ''}</td>
      <td style="padding:12px;">${s.tutorial_count}</td>
      <td class="row-actions" style="padding:12px; text-align:right;">
        <a href="/admin/tutorial_form.php?subject_id=${s.id}" style="margin-right:10px; background:#102A56; color:#fff; padding:5px 10px; border-radius:5px; text-decoration:none; font-weight:600; font-size:12px;">+ Add Tutorial</a>
        <a href="/admin/tutorials.php?subject_id=${s.id}" style="margin-right:10px; color:#102A56; text-decoration:none; font-weight:600;">Tutorials (${s.tutorial_count})</a>
        <a href="/admin/subject_form.php?id=${s.id}" style="margin-right:10px; color:#475569; text-decoration:none;">Edit</a>
        <form method="post" action="/admin/subject_delete.php" style="display:inline;" onsubmit="return confirm('Delete ${s.name} and all its tutorials?');">
          <input type="hidden" name="id" value="${s.id}">
          <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-size:13px;">Delete</button>
        </form>
      </td>
    </tr>
  `).join('');

  const bodyHtml = `
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <span class="meta" style="font-weight:600;">${subjects.length} subject${subjects.length === 1 ? '' : 's'} on the homepage</span>
      <a href="/admin/subject_form.php" class="btn btn-primary" style="background:#102A56; color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px;">+ New Subject</a>
    </div>
    ${subjects.length === 0 ? '<div class="empty">No subjects yet — create one to show it on the homepage.</div>' : `
      <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #E2E8F0; border-radius:8px;">
        <thead>
          <tr style="text-align:left; border-bottom:2px solid #E2E8F0; font-size:12px; color:#64748B;">
            <th style="padding:10px;">Name</th>
            <th style="padding:10px;">Institution</th>
            <th style="padding:10px;">Tutorials</th>
            <th style="padding:10px; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
    `}
  `;

  res.send(renderAdminLayout('Subjects', 'subjects', bodyHtml));
});

app.get('/admin/subject_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const id = parseInt(req.query.id, 10) || 0;
  let s = null;
  if (id) {
    s = db.prepare('SELECT * FROM subjects WHERE id = ?').get(id);
  }

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/subjects.php" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to subjects</a></div>
    <form method="post" action="/admin/subject_form.php${id ? '?id=' + id : ''}" style="max-width:600px;">
      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Name *</label>
          <input type="text" name="name" value="${s ? s.name : ''}" placeholder="e.g. MCEN2003 Machine Dynamics" required style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Institution</label>
          <input type="text" name="institution" value="${s && s.institution ? s.institution : ''}" placeholder="e.g. Curtin University" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Description</label>
          <textarea name="description" rows="3" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${s && s.description ? s.description : ''}</textarea>
        </div>
      </div>
      <div class="save-bar" style="display:flex; gap:10px;">
        <a href="/admin/subjects.php" class="btn btn-outline" style="padding:10px 16px; border:1px solid #CBD5E1; border-radius:6px; text-decoration:none; color:#334155;">Cancel</a>
        <button type="submit" class="btn btn-primary" style="background:#102A56; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:600; cursor:pointer;">${s ? 'Save changes' : 'Create subject'}</button>
      </div>
    </form>
  `;

  res.send(renderAdminLayout(s ? 'Edit Subject' : 'New Subject', 'subjects', bodyHtml));
});

app.post('/admin/subject_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const id = parseInt(req.query.id, 10) || 0;
  const name = (req.body.name || '').trim();
  const institution = (req.body.institution || '').trim();
  const description = (req.body.description || '').trim();

  if (!name) return res.redirect('/admin/subjects.php');

  if (id) {
    db.prepare('UPDATE subjects SET name = ?, institution = ?, description = ? WHERE id = ?').run(name, institution || null, description || null, id);
    res.redirect('/admin/subjects.php');
  } else {
    let slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'subject';
    let check = db.prepare('SELECT id FROM subjects WHERE slug = ?').get(slug);
    let counter = 1;
    let baseSlug = slug;
    while (check) {
      counter++;
      slug = baseSlug + '-' + counter;
      check = db.prepare('SELECT id FROM subjects WHERE slug = ?').get(slug);
    }
    const maxOrder = db.prepare('SELECT COALESCE(MAX(sort_order), 0) as m FROM subjects').get().m;
    const info = db.prepare('INSERT INTO subjects (slug, name, institution, description, sort_order) VALUES (?, ?, ?, ?, ?)').run(slug, name, institution || null, description || null, maxOrder + 10);
    const newId = info.lastInsertRowid;
    res.redirect(`/admin/tutorial_form.php?subject_id=${newId}&created_subject=1`);
  }
});

app.post('/admin/subject_delete.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');
  const id = parseInt(req.body.id, 10);
  if (id) {
    db.prepare('DELETE FROM subjects WHERE id = ?').run(id);
  }
  res.redirect('/admin/subjects.php');
});

// 4. Formula Sheet Editor
app.get('/admin/formula_sheet.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subjects = db.prepare('SELECT id, name FROM subjects ORDER BY sort_order ASC, id ASC').all();
  const subjectId = parseInt(req.query.subject_id, 10) || (subjects[0] ? subjects[0].id : 0);

  let tutorials = [];
  if (subjectId) {
    tutorials = db.prepare('SELECT id, title FROM tutorials WHERE subject_id = ? ORDER BY sort_order ASC, id ASC').all(subjectId);
  }
  const tutorialId = parseInt(req.query.tutorial_id, 10) || (tutorials[0] ? tutorials[0].id : 0);

  const blockKey = tutorialId ? 'formula_sheet_t' + tutorialId : 'formula_sheet';
  let row = db.prepare('SELECT value_json FROM content_blocks WHERE block_key = ?').get(blockKey);
  if (!row && tutorialId) {
    row = db.prepare('SELECT value_json FROM content_blocks WHERE block_key = ?').get('formula_sheet');
  }

  const sheet = row ? JSON.parse(row.value_json) : { heading: '', subheading: '', boxes: [] };
  if (!sheet.boxes || !sheet.boxes.length) {
    sheet.boxes = [{ title: '', items: [''] }];
  }

  function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  const subjectOptionsHtml = subjects.map(s => `<option value="${s.id}" ${s.id == subjectId ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('');
  const tutorialOptionsHtml = tutorials.length > 0
    ? tutorials.map(t => `<option value="${t.id}" ${t.id == tutorialId ? 'selected' : ''}>${escapeHtml(t.title)}</option>`).join('')
    : '<option value="0">No tutorials found</option>';

  let boxHtml = sheet.boxes.map((b, i) => `
    <div class="card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
      <div class="field" style="margin-bottom:10px;">
        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Box title</label>
        <input type="text" name="box_title[]" value="${escapeHtml(b.title || '')}" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;">
      </div>
      <div class="field" style="margin-bottom:10px;">
        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Items (one per line, HTML allowed)</label>
        <textarea name="box_items[]" rows="5" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;">${escapeHtml((b.items || []).join('\n'))}</textarea>
      </div>
      <button type="button" class="rm-btn" onclick="this.closest('.card').remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Remove box</button>
    </div>
  `).join('');

  const isSaved = req.query.saved === '1';

  const bodyHtml = `
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px 22px; margin-bottom: 20px;">
      <form method="get" action="/admin/formula_sheet.php" id="selectForm" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
          <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em;">Select Subject</label>
          <select name="subject_id" onchange="document.getElementById('selectForm').submit()" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 600; color: #102A56;">
            ${subjectOptionsHtml}
          </select>
        </div>

        <div style="flex: 1; min-width: 220px;">
          <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em;">Select Tutorial</label>
          <select name="tutorial_id" onchange="document.getElementById('selectForm').submit()" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 600; color: #102A56;">
            ${tutorialOptionsHtml}
          </select>
        </div>
      </form>
    </div>

    <form method="post" action="/admin/formula_sheet.php" id="fsForm">
      <input type="hidden" name="subject_id" value="${subjectId}">
      <input type="hidden" name="tutorial_id" value="${tutorialId}">

      ${isSaved ? '<div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:10px; border-radius:6px; margin-bottom:16px;">Saved — changes are live on this tutorial workbook now.</div>' : ''}

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Heading</label>
          <input type="text" name="heading" value="${escapeHtml(sheet.heading || '')}" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
        </div>
        <div class="field">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Subheading</label>
          <input type="text" name="subheading" value="${escapeHtml(sheet.subheading || '')}" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
        </div>
      </div>

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 8px 0; color:#0F172A;">Boxes</h2>
        <p class="hint" style="font-size:13px; color:#64748B; margin:0 0 16px 0;">Each box is one column on the formula sheet. Box title and items both support HTML — one item per line.</p>
        <div id="boxRows">
          ${boxHtml}
        </div>
        <button type="button" class="add-btn" onclick="addBox()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px;">+ Add box</button>
      </div>

      <div class="save-bar" style="margin-top:20px;">
        <button type="submit" class="btn btn-primary" style="background:#102A56; color:#fff; border:none; padding:10px 24px; border-radius:6px; font-weight:600; cursor:pointer;">Save changes for this Tutorial</button>
      </div>
    </form>
    <script>
    function addBox(){
      var div = document.createElement('div');
      div.className = 'card';
      div.style.cssText = 'background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;';
      div.innerHTML = '<div class="field" style="margin-bottom:10px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Box title</label><input type="text" name="box_title[]" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;"></div>' +
        '<div class="field" style="margin-bottom:10px;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Items (one per line, HTML allowed)</label><textarea name="box_items[]" rows="5" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;"></textarea></div>' +
        '<button type="button" class="rm-btn" onclick="this.closest(\'.card\').remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Remove box</button>';
      document.getElementById('boxRows').appendChild(div);
    }
    </script>
  `;

  res.send(renderAdminLayout('Formula Sheet', 'formula', bodyHtml));
});

app.post('/admin/formula_sheet.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subId = parseInt(req.body.subject_id, 10) || 0;
  const tutId = parseInt(req.body.tutorial_id, 10) || 0;
  const heading = (req.body.heading || '').trim();
  const subheading = (req.body.subheading || '').trim();
  const boxTitles = Array.isArray(req.body.box_title) ? req.body.box_title : (req.body.box_title ? [req.body.box_title] : []);
  const boxItems = Array.isArray(req.body.box_items) ? req.body.box_items : (req.body.box_items ? [req.body.box_items] : []);

  const boxes = [];
  boxTitles.forEach((t, i) => {
    const title = (t || '').trim();
    const rawItems = (boxItems[i] || '').split('\n').map(x => x.trim()).filter(x => x !== '');
    if (title || rawItems.length) {
      boxes.push({ title, items: rawItems });
    }
  });

  const valueJson = JSON.stringify({ heading, subheading, boxes });
  const saveKey = tutId ? 'formula_sheet_t' + tutId : 'formula_sheet';

  db.prepare(`
    INSERT INTO content_blocks (block_key, value_json) VALUES (?, ?)
    ON CONFLICT(block_key) DO UPDATE SET value_json = excluded.value_json
  `).run(saveKey, valueJson);

  res.redirect(`/admin/formula_sheet.php?subject_id=${subId}&tutorial_id=${tutId}&saved=1`);
});

// 5. Tutorials List & Management
app.get('/admin/tutorials.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subjectId = parseInt(req.query.subject_id, 10) || 1;
  const subject = db.prepare('SELECT * FROM subjects WHERE id = ?').get(subjectId);

  const tutorials = db.prepare(`
    SELECT t.*,
           (SELECT COUNT(*) FROM questions q WHERE q.tutorial_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM videos v WHERE v.tutorial_id = t.id) AS video_count
    FROM tutorials t WHERE t.subject_id = ? ORDER BY t.sort_order ASC, t.id ASC
  `).all(subjectId);

  let tableRows = tutorials.map(t => `
    <tr style="border-bottom:1px solid #E2E8F0;">
      <td style="padding:12px;"><strong>${t.title}</strong></td>
      <td style="padding:12px; color:#475569;">${t.description || ''}</td>
      <td style="padding:12px;">${t.question_count}</td>
      <td style="padding:12px;">${t.video_count}</td>
      <td class="row-actions" style="padding:12px; text-align:right;">
        <a href="/admin/tutorial_form.php?subject_id=${subjectId}&id=${t.id}" style="margin-right:10px; color:#475569; text-decoration:none;">Edit</a>
        <form method="post" action="/admin/tutorial_delete.php" style="display:inline;" onsubmit="return confirm('Delete ${t.title}?');">
          <input type="hidden" name="id" value="${t.id}">
          <input type="hidden" name="subject_id" value="${subjectId}">
          <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-size:13px;">Delete</button>
        </form>
      </td>
    </tr>
  `).join('');

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/subjects.php" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to Subjects</a></div>
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <span class="meta" style="font-weight:600;">${tutorials.length} tutorial${tutorials.length === 1 ? '' : 's'} under ${subject ? subject.name : 'Subject'}</span>
      <a href="/admin/tutorial_form.php?subject_id=${subjectId}" class="btn btn-primary" style="background:#102A56; color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px;">+ New Tutorial</a>
    </div>
    ${tutorials.length === 0 ? '<div class="empty">No tutorials for this subject yet.</div>' : `
      <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #E2E8F0; border-radius:8px;">
        <thead>
          <tr style="text-align:left; border-bottom:2px solid #E2E8F0; font-size:12px; color:#64748B;">
            <th style="padding:10px;">Title</th>
            <th style="padding:10px;">Description</th>
            <th style="padding:10px;">Questions</th>
            <th style="padding:10px;">Videos</th>
            <th style="padding:10px; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
    `}
  `;

  res.send(renderAdminLayout('Tutorials', 'subjects', bodyHtml));
});

app.get('/admin/tutorial_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subjectId = parseInt(req.query.subject_id, 10) || 0;
  const subject = db.prepare('SELECT * FROM subjects WHERE id = ?').get(subjectId);
  if (!subject) return res.redirect('/admin/subjects.php');

  const id = parseInt(req.query.id, 10) || 0;
  let tut = null;
  if (id) {
    tut = db.prepare('SELECT * FROM tutorials WHERE id = ? AND subject_id = ?').get(id, subjectId);
  }

  const isCreatedSubject = req.query.created_subject === '1';

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/tutorials.php?subject_id=${subjectId}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to ${subject.name} tutorials</a></div>
    ${isCreatedSubject ? '<div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-weight:600;">🎉 Subject created successfully! Now create your first tutorial for <strong>' + subject.name + '</strong> below.</div>' : ''}
    <form method="post" action="/admin/tutorial_form.php?subject_id=${subjectId}${id ? '&id=' + id : ''}" style="max-width:600px;">
      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Title *</label>
          <input type="text" name="title" value="${tut ? tut.title : ''}" placeholder="e.g. Tutorial 1 — Kinematics" required style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Description (optional)</label>
          <textarea name="description" rows="3" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${tut && tut.description ? tut.description : ''}</textarea>
        </div>
      </div>
      <div class="save-bar" style="display:flex; gap:10px;">
        <a href="/admin/tutorials.php?subject_id=${subjectId}" class="btn btn-outline" style="padding:10px 16px; border:1px solid #CBD5E1; border-radius:6px; text-decoration:none; color:#334155;">Cancel</a>
        <button type="submit" class="btn btn-primary" style="background:#102A56; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:600; cursor:pointer;">${tut ? 'Save changes' : 'Create tutorial'}</button>
      </div>
    </form>
  `;

  res.send(renderAdminLayout(tut ? 'Edit Tutorial' : 'New Tutorial', 'subjects', bodyHtml));
});

app.post('/admin/tutorial_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subjectId = parseInt(req.query.subject_id, 10) || 0;
  const id = parseInt(req.query.id, 10) || 0;
  const title = (req.body.title || '').trim();
  const description = (req.body.description || '').trim();

  if (!title || !subjectId) return res.redirect(`/admin/tutorials.php?subject_id=${subjectId}`);

  if (id) {
    db.prepare('UPDATE tutorials SET title = ?, description = ? WHERE id = ? AND subject_id = ?').run(title, description || null, id, subjectId);
  } else {
    let slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'tutorial';
    let check = db.prepare('SELECT id FROM tutorials WHERE subject_id = ? AND slug = ?').get(subjectId, slug);
    let counter = 1;
    let baseSlug = slug;
    while (check) {
      counter++;
      slug = baseSlug + '-' + counter;
      check = db.prepare('SELECT id FROM tutorials WHERE subject_id = ? AND slug = ?').get(subjectId, slug);
    }
    const maxOrderRow = db.prepare('SELECT COALESCE(MAX(sort_order), 0) as m FROM tutorials WHERE subject_id = ?').get(subjectId);
    const maxOrder = maxOrderRow ? maxOrderRow.m : 0;
    db.prepare('INSERT INTO tutorials (subject_id, slug, title, description, sort_order) VALUES (?, ?, ?, ?, ?)').run(subjectId, slug, title, description || null, maxOrder + 10);
  }

  res.redirect(`/admin/tutorials.php?subject_id=${subjectId}`);
});

app.post('/admin/tutorial_delete.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const id = parseInt(req.body.id, 10);
  const subjectId = parseInt(req.body.subject_id, 10);

  if (id) {
    db.prepare('DELETE FROM tutorials WHERE id = ?').run(id);
  }

  res.redirect(`/admin/tutorials.php?subject_id=${subjectId}`);
});

// Serve static frontend files
app.use(express.static(__dirname));

app.listen(PORT, () => {
  console.log(`MCEN2003 Machine Dynamics Website running at http://localhost:${PORT}`);
});
