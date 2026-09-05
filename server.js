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

  CREATE TABLE IF NOT EXISTS pdfs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutorial_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutorial_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
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

app.post('/api/ai_generate_question.php', async (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) {
    return res.status(401).json({ error: 'Not authorized' });
  }

  const { provider = 'gemini', api_key, prompt, topic, image_base64 } = req.body || {};
  const apiKey = (api_key || '').trim();
  const rawPrompt = (prompt || '').trim();

  if (!rawPrompt) {
    return res.status(400).json({ error: 'Please enter a prompt or question idea.' });
  }
  if (!apiKey) {
    return res.status(400).json({ error: 'API Key is required. Please enter your Gemini or DeepSeek API key.' });
  }

  const systemPrompt = `You are a professor in Machine Dynamics and Engineering Mechanics. Create a high-quality university practice question based on the user prompt and optional image. Output MUST be a single raw valid JSON object (DO NOT wrap in markdown or \`\`\`json codeblocks) matching this EXACT schema:

{
  "code": "T1.X",
  "topic": "Topic Name",
  "title": "Short Question Title",
  "statement": "Complete clear problem statement with numerical values and units.",
  "sketch": "Brief note on sketching/drawing before solving (or empty).",
  "given": [
    ["label", "value"]
  ],
  "hint": {
    "approach": "General physical approach and method",
    "formulas": ["formula 1", "formula 2"],
    "plan": ["Step 1 plan", "Step 2 plan"],
    "tip": "Useful tip for avoiding common mistakes"
  },
  "parts": [
    { "label": "(a) Question part 1", "value": 12.5, "unit": "m/s" }
  ],
  "steps": [
    { "t": "Step 1: Title", "d": "Step 1 detailed working with calculations." }
  ],
  "original": "Full worked solution text with all formulas and final numerical answers.",
  "seed": "Variables seed for built-in calculator"
}
${topic ? '\nTopic Context: ' + topic : ''}`;

  try {
    if (provider === 'gemini') {
      const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${encodeURIComponent(apiKey)}`;
      const parts = [{ text: systemPrompt + '\n\nUser Request: ' + rawPrompt }];

      if (image_base64 && image_base64.includes('base64,')) {
        const [meta, data] = image_base64.split('base64,');
        const mimeMatch = meta.match(/data:([^;]+);/);
        const mime = mimeMatch ? mimeMatch[1] : 'image/png';
        parts.push({
          inline_data: { mime_type: mime, data }
        });
      }

      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          contents: [{ parts }],
          generationConfig: { response_mime_type: 'application/json' }
        })
      });

      if (!response.ok) {
        const errText = await response.text();
        return res.status(500).json({ error: `Gemini API call failed (HTTP ${response.status}): ${errText.substring(0, 150)}` });
      }

      const resData = await response.json();
      const text = resData.candidates?.[0]?.content?.parts?.[0]?.text || '';
      const cleanJson = text.trim().replace(/^```(json)?|```$/gm, '').trim();
      const parsed = JSON.parse(cleanJson);

      return res.json({ success: true, data: parsed });
    } else {
      // DeepSeek API
      const url = 'https://api.deepseek.com/chat/completions';
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${apiKey}`
        },
        body: JSON.stringify({
          model: 'deepseek-chat',
          messages: [
            { role: 'system', content: systemPrompt },
            { role: 'user', content: rawPrompt }
          ],
          response_format: { type: 'json_object' }
        })
      });

      if (!response.ok) {
        const errText = await response.text();
        return res.status(500).json({ error: `DeepSeek API call failed (HTTP ${response.status}): ${errText.substring(0, 150)}` });
      }

      const resData = await response.json();
      const text = resData.choices?.[0]?.message?.content || '';
      const cleanJson = text.trim().replace(/^```(json)?|```$/gm, '').trim();
      const parsed = JSON.parse(cleanJson);

      return res.json({ success: true, data: parsed });
    }
  } catch (err) {
    return res.status(500).json({ error: 'AI Generation error: ' + err.message });
  }
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
        <small style="color: #64748B;"><a href="tel:${m.phone}">${m.phone}</a></small>
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

app.get('/admin/subjects.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const subjects = db.prepare(`
    SELECT s.*, 
           (SELECT COUNT(*) FROM tutorials t WHERE t.subject_id = s.id) AS tutorial_count,
           (SELECT COUNT(*) FROM questions q JOIN tutorials t ON q.tutorial_id = t.id WHERE t.subject_id = s.id) AS question_count,
           (SELECT COUNT(*) FROM videos v JOIN tutorials t ON v.tutorial_id = t.id WHERE t.subject_id = s.id) AS video_count,
           (SELECT COUNT(*) FROM pdfs p JOIN tutorials t ON p.tutorial_id = t.id WHERE t.subject_id = s.id) AS pdf_count,
           (SELECT COUNT(*) FROM notes n JOIN tutorials t ON n.tutorial_id = t.id WHERE t.subject_id = s.id) AS note_count
    FROM subjects s ORDER BY s.sort_order ASC, s.id ASC
  `).all();

  let cardsHtml = subjects.map(s => `
    <div onclick="window.location.href='/admin/tutorials.php?subject_id=${s.id}'" 
         style="background:#fff; border:1.5px solid #E2E8F0; border-radius:12px; padding:22px; cursor:pointer; transition:all 0.2s ease; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 10px rgba(0,0,0,0.03);"
         onmouseover="this.style.borderColor='#3B82F6'; this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(59,130,246,0.12)';"
         onmouseout="this.style.borderColor='#E2E8F0'; this.style.transform='none'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.03)';">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
          <span style="font-size:11px; font-weight:700; background:#EFF6FF; color:#1D4ED8; padding:3px 9px; border-radius:12px; text-transform:uppercase; letter-spacing:0.04em;">
            ${s.institution || 'Subject'}
          </span>
          <span style="font-size:12px; font-weight:700; background:#F1F5F9; color:#475569; padding:3px 9px; border-radius:12px;">
            ${s.tutorial_count} Tutorial${s.tutorial_count === 1 ? '' : 's'}
          </span>
        </div>

        <h3 style="margin:8px 0 6px 0; font-size:18px; font-weight:700; color:#0F172A; line-height:1.3;">${s.name}</h3>
        
        ${s.description ? `
          <p style="margin:0 0 16px 0; font-size:13px; color:#64748B; line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
            ${s.description}
          </p>
        ` : `<p style="margin:0 0 16px 0; font-size:13px; color:#94A3B8; font-style:italic;">No description added.</p>`}

        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;">
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">Questions: <strong>${s.question_count || 0}</strong></span>
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">Videos: <strong>${s.video_count || 0}</strong></span>
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">PDFs: <strong>${s.pdf_count || 0}</strong></span>
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">Notes: <strong>${s.note_count || 0}</strong></span>
        </div>
      </div>

      <div style="padding-top:14px; border-top:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center;" onclick="event.stopPropagation();">
        <a href="/admin/tutorial_form.php?subject_id=${s.id}" class="btn btn-primary" style="padding:6px 12px; font-size:12px; border-radius:6px; background:#102A56; color:#fff; text-decoration:none; font-weight:600;">+ Add Tutorial</a>
        
        <div style="display:flex; align-items:center; gap:10px;">
          <a href="/admin/tutorials.php?subject_id=${s.id}" style="color:#2563EB; font-weight:600; font-size:12.5px; text-decoration:none;">Open Tutorials &rarr;</a>
          <a href="/admin/subject_form.php?id=${s.id}" style="color:#64748B; font-size:12.5px; text-decoration:none; font-weight:500;">Edit</a>
          <form method="post" action="/admin/subject_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete ${s.name} and all its content?');">
            <input type="hidden" name="id" value="${s.id}">
            <button type="submit" style="color:#EF4444; background:transparent; border:none; cursor:pointer; font-size:12.5px; font-weight:500; padding:0;">Delete</button>
          </form>
        </div>
      </div>
    </div>
  `).join('');

  const bodyHtml = `
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">Subjects Overview</h2>
        <span class="meta" style="color:#64748B; font-size:13px;">${subjects.length} subject${subjects.length === 1 ? '' : 's'} active on website</span>
      </div>
      <a href="/admin/subject_form.php" class="btn btn-primary" style="background:#102A56; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New Subject</a>
    </div>
    ${subjects.length === 0 ? '<div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No subjects yet — click "+ New Subject" to create one.</div>' : `
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
        ${cardsHtml}
      </div>
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
  if (!subject) return res.redirect('/admin/subjects.php');

  const tutorials = db.prepare(`
    SELECT t.*,
           (SELECT COUNT(*) FROM questions q WHERE q.tutorial_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM videos v WHERE v.tutorial_id = t.id) AS video_count,
           (SELECT COUNT(*) FROM pdfs p WHERE p.tutorial_id = t.id) AS pdf_count,
           (SELECT COUNT(*) FROM notes n WHERE n.tutorial_id = t.id) AS note_count
    FROM tutorials t WHERE t.subject_id = ? ORDER BY t.sort_order ASC, t.id ASC
  `).all(subjectId);

  let tutorialsHtml = tutorials.map(t => `
    <div style="background:#fff; border:1.5px solid #E2E8F0; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div>
          <h3 style="margin:0 0 4px 0; font-size:17px; font-weight:700; color:#0F172A;">${t.title}</h3>
          ${t.description ? `<p style="margin:0; font-size:13px; color:#64748B;">${t.description}</p>` : ''}
        </div>
        <div style="display:flex; gap:8px;">
          <a href="/admin/tutorial_form.php?subject_id=${subjectId}&id=${t.id}" class="btn btn-outline" style="padding:5px 12px; font-size:12.5px; border-radius:6px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Edit Title</a>
          <form method="post" action="/admin/tutorial_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete ${t.title} and all its content?');">
            <input type="hidden" name="id" value="${t.id}">
            <input type="hidden" name="subject_id" value="${subjectId}">
            <button type="submit" style="padding:5px 12px; font-size:12.5px; border-radius:6px; border:1px solid #FECACA; background:#FEF2F2; color:#DC2626; font-weight:600; cursor:pointer;">Delete</button>
          </form>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; background:#F8FAFC; padding:14px; border-radius:10px; border:1px solid #F1F5F9;">
        <!-- 1. Questions -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">Questions</span>
            <span style="background:#EEF2FF; color:#4F46E5; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;">${t.question_count}</span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="/admin/question_form.php?tutorial_id=${t.id}" style="flex:1; text-align:center; background:#4F46E5; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add Question</a>
            <a href="/admin/questions.php?tutorial_id=${t.id}" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

        <!-- 2. Videos -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">Videos</span>
            <span style="background:#FEF3C7; color:#D97706; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;">${t.video_count}</span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="/admin/video_form.php?tutorial_id=${t.id}" style="flex:1; text-align:center; background:#D97706; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add Video</a>
            <a href="/admin/videos.php?tutorial_id=${t.id}" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

        <!-- 3. PDF Documents -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">PDF Documents</span>
            <span style="background:#ECFDF5; color:#059669; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;">${t.pdf_count}</span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="/admin/pdf_form.php?tutorial_id=${t.id}" style="flex:1; text-align:center; background:#059669; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add PDF</a>
            <a href="/admin/pdfs.php?tutorial_id=${t.id}" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

        <!-- 4. Text Notes -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">Text / Notes</span>
            <span style="background:#F3E8FF; color:#7C3AED; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;">${t.note_count}</span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="/admin/note_form.php?tutorial_id=${t.id}" style="flex:1; text-align:center; background:#7C3AED; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add Text Note</a>
            <a href="/admin/notes.php?tutorial_id=${t.id}" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>
      </div>
    </div>
  `).join('');

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/subjects.php" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to Subjects</a></div>
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">${subject.name} Tutorials</h2>
        <span class="meta" style="color:#64748B; font-size:13px;">${tutorials.length} tutorial${tutorials.length === 1 ? '' : 's'} active</span>
      </div>
      <a href="/admin/tutorial_form.php?subject_id=${subjectId}" class="btn btn-primary" style="background:#102A56; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New Tutorial</a>
    </div>
    ${tutorials.length === 0 ? '<div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No tutorials for this subject yet.</div>' : `
      <div style="display:flex; flex-direction:column; gap:16px;">
        ${tutorialsHtml}
      </div>
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
    ${isCreatedSubject ? '<div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-weight:600;">Subject created successfully! Now create your first tutorial for <strong>' + subject.name + '</strong> below.</div>' : ''}
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
    res.redirect(`/admin/tutorials.php?subject_id=${subjectId}`);
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
    const info = db.prepare('INSERT INTO tutorials (subject_id, slug, title, description, sort_order) VALUES (?, ?, ?, ?, ?)').run(subjectId, slug, title, description || null, maxOrder + 10);
    const newTutId = info.lastInsertRowid;
    res.redirect(`/admin/question_form.php?tutorial_id=${newTutId}&created_tutorial=1`);
  }
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

// 6. Questions Management
app.get('/admin/questions.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const tutorial = db.prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?').get(tutorialId);
  if (!tutorial) return res.redirect('/admin/subjects.php');

  const questions = db.prepare('SELECT id, code, topic, title, sort_order FROM questions WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);

  let tableRows = questions.map(q => `
    <tr style="border-bottom:1px solid #E2E8F0;">
      <td style="padding:12px;"><span style="background:#EEF2FF; color:#4F46E5; padding:3px 8px; border-radius:4px; font-weight:700; font-family:monospace;">${q.code}</span></td>
      <td style="padding:12px; color:#475569;">${q.topic}</td>
      <td style="padding:12px;"><strong>${q.title}</strong></td>
      <td class="row-actions" style="padding:12px; text-align:right;">
        <a href="/admin/question_form.php?tutorial_id=${tutorialId}&id=${q.id}" style="margin-right:10px; color:#475569; text-decoration:none;">Edit</a>
        <form method="post" action="/admin/question_delete.php" style="display:inline;" onsubmit="return confirm('Delete question ${q.code}?');">
          <input type="hidden" name="id" value="${q.id}">
          <input type="hidden" name="tutorial_id" value="${tutorialId}">
          <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-size:13px;">Delete</button>
        </form>
      </td>
    </tr>
  `).join('');

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/tutorials.php?subject_id=${tutorial.subject_id}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to ${tutorial.subject_name} tutorials</a></div>
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <span class="meta" style="font-weight:600;">${questions.length} question${questions.length === 1 ? '' : 's'} in ${tutorial.title}</span>
      <a href="/admin/question_form.php?tutorial_id=${tutorialId}" class="btn btn-primary" style="background:#102A56; color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px;">+ New Question</a>
    </div>
    ${questions.length === 0 ? '<div class="empty">No questions in this tutorial yet.</div>' : `
      <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #E2E8F0; border-radius:8px;">
        <thead>
          <tr style="text-align:left; border-bottom:2px solid #E2E8F0; font-size:12px; color:#64748B;">
            <th style="padding:10px;">Code</th>
            <th style="padding:10px;">Topic</th>
            <th style="padding:10px;">Title</th>
            <th style="padding:10px; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
    `}
  `;

  res.send(renderAdminLayout(`${tutorial.title} — Questions`, 'subjects', bodyHtml));
});

app.get('/admin/question_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const tutorial = db.prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?').get(tutorialId);
  if (!tutorial) return res.redirect('/admin/subjects.php');

  const id = parseInt(req.query.id, 10) || 0;
  let q = null;
  if (id) {
    q = db.prepare('SELECT * FROM questions WHERE id = ? AND tutorial_id = ?').get(id, tutorialId);
  }

  const given = q ? JSON.parse(q.given_json || '[]') : [['', '']];
  const hint = q ? JSON.parse(q.hint_json || '{}') : { approach: '', formulas: [''], plan: [''], tip: '' };
  const parts = q ? JSON.parse(q.parts_json || '[]') : [{ label: '', value: '', unit: '' }];
  const steps = q ? JSON.parse(q.steps_json || '[]') : [{ t: '', d: '' }];

  if (!given.length) given.push(['', '']);
  if (!hint.formulas || !hint.formulas.length) hint.formulas = [''];
  if (!hint.plan || !hint.plan.length) hint.plan = [''];
  if (!parts.length) parts.push({ label: '', value: '', unit: '' });
  if (!steps.length) steps.push({ t: '', d: '' });

  function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  const isCreatedTut = req.query.created_tutorial === '1';

  const givenRowsHtml = given.map(g => `
    <div class="rep-row" style="display:flex; gap:10px; margin-bottom:10px;">
      <input type="text" name="given_label[]" placeholder="Label (e.g. v₀)" value="${escapeHtml(g[0] || '')}" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
      <input type="text" name="given_value[]" placeholder="Value (e.g. 50 m/s)" value="${escapeHtml(g[1] || '')}" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
      <button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>
    </div>
  `).join('');

  const formulaRowsHtml = hint.formulas.map(f => `
    <div class="rep-row" style="display:flex; gap:10px; margin-bottom:10px;">
      <input type="text" name="formula_items[]" value="${escapeHtml(f)}" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
      <button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>
    </div>
  `).join('');

  const planRowsHtml = hint.plan.map(p => `
    <div class="rep-row" style="display:flex; gap:10px; margin-bottom:10px;">
      <textarea name="plan_items[]" rows="2" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">${escapeHtml(p)}</textarea>
      <button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>
    </div>
  `).join('');

  const quickUnits = ['m/s', 'km/h', 'm/s²', 'rad/s', 'rad/s²', 'rpm', 'Hz', 'N', 'kN', 'N·m', 'kg', 'm', 'mm', 's', 'kPa', 'J', 'W'];
  const unitChipsHtml = quickUnits.map(u => `
    <button type="button" class="chip" onclick="applyQuickUnit('${u}')" style="background:#F1F5F9; border:1px solid #CBD5E1; color:#0F172A; padding:3px 8px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; font-family:monospace;">${u}</button>
  `).join('');

  const partRowsHtml = parts.map(p => `
    <div class="rep-row" style="display:flex; gap:10px; margin-bottom:10px;">
      <input type="text" name="part_label[]" placeholder="(a) Velocity at t = 8 s" value="${escapeHtml(p.label || '')}" style="flex:2; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
      <input type="text" name="part_value[]" placeholder="Answer value (e.g. 10)" value="${escapeHtml(p.value !== undefined ? p.value : '')}" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
      <input type="text" name="part_unit[]" list="unitSuggestions" placeholder="Unit (e.g. m/s)" value="${escapeHtml(p.unit || '')}" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
      <button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>
    </div>
  `).join('');

  const stepRowsHtml = steps.map(s => `
    <div class="rep-row" style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px; background:#F8FAFC; padding:12px; border:1px solid #E2E8F0; border-radius:8px;">
      <input type="text" name="step_title[]" placeholder="Step title (e.g. Step 1: Integrate acceleration)" value="${escapeHtml(s.t || '')}" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;">
      <textarea name="step_desc[]" rows="3" placeholder="Step calculation and explanation" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;">${escapeHtml(s.d || '')}</textarea>
      <button type="button" onclick="this.parentElement.remove()" style="align-self:flex-start; background:none; border:1px solid #FCA5A5; color:#DC2626; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Remove step</button>
    </div>
  `).join('');

  const bodyHtml = `
    <datalist id="unitSuggestions">
      <option value="m/s">Velocity (m/s)</option>
      <option value="km/h">Speed (km/h)</option>
      <option value="m/s²">Acceleration (m/s²)</option>
      <option value="rad/s">Angular Velocity (rad/s)</option>
      <option value="rad/s²">Angular Acceleration (rad/s²)</option>
      <option value="rpm">Rotational Speed (rpm)</option>
      <option value="Hz">Frequency (Hz)</option>
      <option value="N">Force (N)</option>
      <option value="kN">Force (kN)</option>
      <option value="N·m">Torque/Moment (N·m)</option>
      <option value="kg">Mass (kg)</option>
      <option value="g">Mass (g)</option>
      <option value="m">Length (m)</option>
      <option value="mm">Length (mm)</option>
      <option value="cm">Length (cm)</option>
      <option value="km">Length (km)</option>
      <option value="s">Time (s)</option>
      <option value="ms">Time (ms)</option>
      <option value="Pa">Pressure (Pa)</option>
      <option value="kPa">Pressure (kPa)</option>
      <option value="MPa">Pressure (MPa)</option>
      <option value="J">Energy/Work (J)</option>
      <option value="kJ">Energy (kJ)</option>
      <option value="W">Power (W)</option>
      <option value="kW">Power (kW)</option>
      <option value="deg">Angle (deg)</option>
      <option value="rad">Angle (rad)</option>
    </datalist>

    <div style="margin-bottom:12px;"><a href="/admin/questions.php?tutorial_id=${tutorialId}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to ${tutorial.title} questions</a></div>
    ${isCreatedTut ? '<div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-weight:600;">Tutorial created successfully! Now add your first question for <strong>' + tutorial.title + '</strong> below.</div>' : ''}
    
    <div class="card" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid #334155; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex; align-items:center; gap:12px;">
          <div style="background:linear-gradient(135deg, #6366F1, #8B5CF6); width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:bold; color:#fff; box-shadow:0 2px 10px rgba(99,102,241,0.3);">AI</div>
          <div>
            <h2 style="font-size:17px; margin:0; color:#fff; font-weight:700;">Make Question with AI</h2>
            <p style="font-size:12.5px; margin:3px 0 0 0; color:#94A3B8;">Auto-generate complete question details, hints, parts & worked solution using Gemini or DeepSeek AI</p>
          </div>
        </div>
        <button type="button" id="aiToggleBtn" onclick="toggleAiBox()" style="background:#334155; border:1px solid #475569; color:#F8FAFC; padding:7px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Expand AI Generator</button>
      </div>

      <div id="aiBoxContent" style="display:none; margin-top:20px; padding-top:18px; border-top:1px solid #334155;">
        <div style="display:flex; gap:14px; margin-bottom:14px; flex-wrap:wrap;">
          <div style="flex:1; min-width:220px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#CBD5E1; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.03em;">AI Model</label>
            <select id="aiProvider" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #475569; background:#0F172A; color:#fff; font-size:13px; font-weight:500;" onchange="onAiProviderChange()">
              <option value="gemini">Google Gemini API (Free / Fast / Vision Supported)</option>
              <option value="deepseek">DeepSeek AI API</option>
            </select>
          </div>
          <div style="flex:2; min-width:280px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#CBD5E1; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.03em;">API Key (<span id="apiKeyHint" style="color:#A7F3D0; font-weight:400; text-transform:none;">Saved in browser</span>)</label>
            <input type="password" id="aiApiKey" placeholder="Enter Gemini or DeepSeek API key" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #475569; background:#0F172A; color:#fff; font-size:13px; box-sizing:border-box;" onchange="saveAiKey()">
          </div>
        </div>

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:12px; font-weight:700; color:#CBD5E1; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.03em;">Main Prompt / Question Requirements *</label>
          <textarea id="aiPrompt" rows="3" placeholder="e.g. Create a problem about a particle moving under variable acceleration a(t) = 6t - 4. Find velocity and displacement at t = 3 s. Include step-by-step working." style="width:100%; padding:11px; border-radius:8px; border:1.5px solid #475569; background:#0F172A; color:#fff; font-size:13.5px; box-sizing:border-box; line-height:1.5;"></textarea>
        </div>

        <div style="margin-bottom:18px;">
          <label style="display:block; font-size:12px; font-weight:700; color:#CBD5E1; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.03em;">Optional Diagram Photo (Image to Question)</label>
          <input type="file" id="aiImageFile" accept="image/*" style="font-size:13px; color:#CBD5E1;">
        </div>

        <div id="aiErrorMsg" style="display:none; background:#7F1D1D; color:#FECACA; border:1px solid #991B1B; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:500;"></div>

        <button type="button" id="aiGenBtn" onclick="generateQuestionWithAi()" style="background:linear-gradient(135deg, #4F46E5, #7C3AED); color:#fff; border:none; padding:12px 24px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:10px; box-shadow:0 4px 14px rgba(79,70,229,0.4); transition:all 0.2s;">
          <span id="aiBtnText">Generate Question & Auto-Fill Form</span>
        </button>
      </div>
    </div>

    <form method="post" action="/admin/question_form.php?tutorial_id=${tutorialId}${id ? '&id=' + id : ''}" style="max-width:800px;" id="qForm">
      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 12px 0;">Question Details</h2>
        <div style="display:flex; gap:12px; margin-bottom:14px;">
          <div style="flex:1;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Code * (e.g. T1.6)</label>
            <input type="text" name="code" value="${escapeHtml(q ? q.code : '')}" required style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
          </div>
          <div style="flex:2;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Topic *</label>
            <input type="text" name="topic" value="${escapeHtml(q ? q.topic : '')}" required style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
          </div>
        </div>
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Title *</label>
          <input type="text" name="title" value="${escapeHtml(q ? q.title : '')}" required style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Statement *</label>
          <textarea name="statement" rows="5" required style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${escapeHtml(q ? q.statement : '')}</textarea>
        </div>
        <div class="field">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Sketch note (optional)</label>
          <textarea name="sketch" rows="2" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${escapeHtml(q && q.sketch ? q.sketch : '')}</textarea>
        </div>
      </div>

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 12px 0;">Given Values</h2>
        <div id="givenRows">${givenRowsHtml}</div>
        <button type="button" onclick="addGiven()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px;">+ Add Given Value</button>
      </div>

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 12px 0;">Hint & Approach</h2>
        <div class="field" style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Approach</label>
          <textarea name="hint_approach" rows="3" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${escapeHtml(hint.approach || '')}</textarea>
        </div>
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Formulas</label>
        <div id="formulaRows">${formulaRowsHtml}</div>
        <button type="button" onclick="addFormula()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; margin-bottom:16px;">+ Add Formula</button>

        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Plan Steps</label>
        <div id="planRows">${planRowsHtml}</div>
        <button type="button" onclick="addPlan()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; margin-bottom:16px;">+ Add Plan Step</button>

        <div class="field">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Tip</label>
          <textarea name="hint_tip" rows="2" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${escapeHtml(hint.tip || '')}</textarea>
        </div>
      </div>

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 8px 0;">Answer Parts (Interactive Check)</h2>
        <p class="hint" style="font-size:13px; color:#64748B; margin:0 0 12px 0;">Each part is what the student types an answer for and gets checked against.</p>
        <div class="unit-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; align-items:center;">
          <span style="font-size:12px; font-weight:700; color:#475569; margin-right:4px;">Quick Units:</span>
          ${unitChipsHtml}
        </div>
        <div id="partRows">${partRowsHtml}</div>
        <button type="button" onclick="addPart()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px;">+ Add Answer Part</button>
      </div>

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 12px 0;">Worked Solution Steps</h2>
        <div id="stepRows">${stepRowsHtml}</div>
        <button type="button" onclick="addStep()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px;">+ Add Worked Step</button>
      </div>

      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h2 style="font-size:16px; margin:0 0 12px 0;">Full Solution (LaTeX / Text)</h2>
        <textarea name="original" rows="10" style="width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:6px; box-sizing:border-box;">${escapeHtml(q ? q.original : '')}</textarea>
      </div>

      <div class="save-bar" style="display:flex; gap:10px; margin-top:20px;">
        <a href="/admin/questions.php?tutorial_id=${tutorialId}" class="btn btn-outline" style="padding:10px 16px; border:1px solid #CBD5E1; border-radius:6px; text-decoration:none; color:#334155;">Cancel</a>
        <button type="submit" class="btn btn-primary" style="background:#102A56; color:#fff; border:none; padding:10px 24px; border-radius:6px; font-weight:600; cursor:pointer;">${q ? 'Save changes' : 'Create question'}</button>
      </div>
    </form>
    <script>
    function applyQuickUnit(unit) {
      var inputs = document.querySelectorAll('input[name="part_unit[]"]');
      if (inputs.length > 0) {
        var targetInput = inputs[inputs.length - 1];
        for (var i = inputs.length - 1; i >= 0; i--) {
          if (document.activeElement === inputs[i]) {
            targetInput = inputs[i];
            break;
          }
        }
        targetInput.value = unit;
        targetInput.focus();
      }
    }
    function addGiven() {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<input type="text" name="given_label[]" placeholder="Label" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><input type="text" name="given_value[]" placeholder="Value" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('givenRows').appendChild(div);
    }
    function addFormula() {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<input type="text" name="formula_items[]" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('formulaRows').appendChild(div);
    }
    function addPlan() {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<textarea name="plan_items[]" rows="2" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"></textarea><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('planRows').appendChild(div);
    }
    function addPart() {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<input type="text" name="part_label[]" placeholder="Label" style="flex:2; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><input type="text" name="part_value[]" placeholder="Value" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><input type="text" name="part_unit[]" list="unitSuggestions" placeholder="Unit (e.g. m/s)" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('partRows').appendChild(div);
    }
    function addStep() {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; flex-direction:column; gap:6px; margin-bottom:12px; background:#F8FAFC; padding:12px; border:1px solid #E2E8F0; border-radius:8px;';
      div.innerHTML = '<input type="text" name="step_title[]" placeholder="Step title" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;"><textarea name="step_desc[]" rows="3" placeholder="Step calculation and explanation" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;"></textarea><button type="button" onclick="this.parentElement.remove()" style="align-self:flex-start; background:none; border:1px solid #FCA5A5; color:#DC2626; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Remove step</button>';
      document.getElementById('stepRows').appendChild(div);
    }

    function toggleAiBox() {
      var content = document.getElementById('aiBoxContent');
      var btn = document.getElementById('aiToggleBtn');
      if (content.style.display === 'none') {
        content.style.display = 'block';
        btn.textContent = '– Collapse AI Generator';
        loadAiKey();
      } else {
        content.style.display = 'none';
        btn.textContent = '+ Expand AI Generator';
      }
    }

    function onAiProviderChange() {
      loadAiKey();
    }

    function loadAiKey() {
      var provider = document.getElementById('aiProvider').value;
      var key = localStorage.getItem('ai_key_' + provider) || '';
      document.getElementById('aiApiKey').value = key;
    }

    function saveAiKey() {
      var provider = document.getElementById('aiProvider').value;
      var key = document.getElementById('aiApiKey').value.trim();
      if (key) {
        localStorage.setItem('ai_key_' + provider, key);
      }
    }

    async function generateQuestionWithAi() {
      var provider = document.getElementById('aiProvider').value;
      var apiKey = document.getElementById('aiApiKey').value.trim();
      var prompt = document.getElementById('aiPrompt').value.trim();
      var topic = (document.querySelector('input[name="topic"]') ? document.querySelector('input[name="topic"]').value : '').trim();

      var errBox = document.getElementById('aiErrorMsg');
      errBox.style.display = 'none';

      if (!apiKey) {
        errBox.textContent = 'Please enter your ' + (provider === 'gemini' ? 'Google Gemini' : 'DeepSeek') + ' API key.';
        errBox.style.display = 'block';
        return;
      }
      if (!prompt) {
        errBox.textContent = 'Please enter a prompt or question requirement.';
        errBox.style.display = 'block';
        return;
      }

      saveAiKey();

      var btn = document.getElementById('aiGenBtn');
      var btnText = document.getElementById('aiBtnText');
      btn.disabled = true;
      btnText.textContent = 'Generating Question... Please wait';

      var imageBase64 = null;
      var fileInput = document.getElementById('aiImageFile');
      if (fileInput && fileInput.files && fileInput.files[0]) {
        try {
          imageBase64 = await new Promise(function(resolve, reject) {
            var reader = new FileReader();
            reader.onload = function(e) { resolve(e.target.result); };
            reader.onerror = reject;
            reader.readAsDataURL(fileInput.files[0]);
          });
        } catch(e) {}
      }

      try {
        var res = await fetch('/api/ai_generate_question.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            provider: provider,
            api_key: apiKey,
            prompt: prompt,
            topic: topic,
            image_base64: imageBase64
          })
        });

        var data = await res.json();

        if (!res.ok || data.error) {
          throw new Error(data.error || 'Failed to generate question with AI');
        }

        if (data.success && data.data) {
          applyAiDataToForm(data.data);
          alert('Success! Question generated and form fields auto-filled below. Please review and click Save.');
        } else {
          throw new Error('Invalid response received from AI service.');
        }
      } catch (err) {
        errBox.textContent = err.message || 'An error occurred during AI generation.';
        errBox.style.display = 'block';
      } finally {
        btn.disabled = false;
        btnText.textContent = 'Generate Question & Auto-Fill Form';
      }
    }

    function applyAiDataToForm(d) {
      if (d.code) document.querySelector('input[name="code"]').value = d.code;
      if (d.topic) document.querySelector('input[name="topic"]').value = d.topic;
      if (d.title) document.querySelector('input[name="title"]').value = d.title;
      if (d.statement) document.querySelector('textarea[name="statement"]').value = d.statement;
      if (d.sketch !== undefined) document.querySelector('textarea[name="sketch"]').value = d.sketch || '';

      if (d.given && Array.isArray(d.given)) {
        document.getElementById('givenRows').innerHTML = '';
        d.given.forEach(function(g) {
          addGivenWithValues(g[0] || '', g[1] || '');
        });
      }

      if (d.hint) {
        if (d.hint.approach) document.querySelector('textarea[name="hint_approach"]').value = d.hint.approach;
        if (d.hint.tip) document.querySelector('textarea[name="hint_tip"]').value = d.hint.tip;

        if (d.hint.formulas && Array.isArray(d.hint.formulas)) {
          document.getElementById('formulaRows').innerHTML = '';
          d.hint.formulas.forEach(function(f) { addFormulaWithValue(f); });
        }
        if (d.hint.plan && Array.isArray(d.hint.plan)) {
          document.getElementById('planRows').innerHTML = '';
          d.hint.plan.forEach(function(p) { addPlanWithValue(p); });
        }
      }

      if (d.parts && Array.isArray(d.parts)) {
        document.getElementById('partRows').innerHTML = '';
        d.parts.forEach(function(p) {
          addPartWithValues(p.label || '', p.value !== undefined ? p.value : '', p.unit || '');
        });
      }

      if (d.steps && Array.isArray(d.steps)) {
        document.getElementById('stepRows').innerHTML = '';
        d.steps.forEach(function(s) {
          addStepWithValues(s.t || '', s.d || '');
        });
      }

      if (d.original) document.querySelector('textarea[name="original"]').value = d.original;
      if (d.seed !== undefined) document.querySelector('textarea[name="seed"]').value = d.seed || '';
    }

    function addGivenWithValues(l, v) {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<input type="text" name="given_label[]" placeholder="Label" value="' + escapeAttr(l) + '" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><input type="text" name="given_value[]" placeholder="Value" value="' + escapeAttr(v) + '" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('givenRows').appendChild(div);
    }
    function addFormulaWithValue(f) {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<input type="text" name="formula_items[]" value="' + escapeAttr(f) + '" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('formulaRows').appendChild(div);
    }
    function addPlanWithValue(p) {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<textarea name="plan_items[]" rows="2" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">' + escapeHtml(p) + '</textarea><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('planRows').appendChild(div);
    }
    function addPartWithValues(l, v, u) {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
      div.innerHTML = '<input type="text" name="part_label[]" placeholder="Label" value="' + escapeAttr(l) + '" style="flex:2; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><input type="text" name="part_value[]" placeholder="Value" value="' + escapeAttr(v) + '" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><input type="text" name="part_unit[]" list="unitSuggestions" placeholder="Unit" value="' + escapeAttr(u) + '" style="flex:1; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"><button type="button" onclick="this.parentElement.remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:6px 12px; border-radius:6px; cursor:pointer;">Remove</button>';
      document.getElementById('partRows').appendChild(div);
    }
    function addStepWithValues(t, d) {
      var div = document.createElement('div');
      div.className = 'rep-row';
      div.style.cssText = 'display:flex; flex-direction:column; gap:6px; margin-bottom:12px; background:#F8FAFC; padding:12px; border:1px solid #E2E8F0; border-radius:8px;';
      div.innerHTML = '<input type="text" name="step_title[]" placeholder="Step title" value="' + escapeAttr(t) + '" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;"><textarea name="step_desc[]" rows="3" placeholder="Step calculation" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;">' + escapeHtml(d) + '</textarea><button type="button" onclick="this.parentElement.remove()" style="align-self:flex-start; background:none; border:1px solid #FCA5A5; color:#DC2626; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Remove step</button>';
      document.getElementById('stepRows').appendChild(div);
    }
    function escapeAttr(s) { return String(s || '').replace(/"/g, '&quot;'); }
    function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    </script>
  `;

  res.send(renderAdminLayout(q ? 'Edit Question' : 'New Question', 'subjects', bodyHtml));
});

app.post('/admin/question_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const id = parseInt(req.query.id, 10) || 0;
  const code = (req.body.code || '').trim();
  const topic = (req.body.topic || '').trim();
  const title = (req.body.title || '').trim();
  const statement = (req.body.statement || '').trim();
  const sketch = (req.body.sketch || '').trim();
  const figCaption = (req.body.fig_caption || '').trim();
  const seed = (req.body.seed || '').trim();
  const approach = (req.body.hint_approach || '').trim();
  const tip = (req.body.hint_tip || '').trim();
  const original = (req.body.original || '').trim();

  if (!code || !topic || !title || !statement || !tutorialId) {
    return res.redirect(`/admin/questions.php?tutorial_id=${tutorialId}`);
  }

  const givenLabels = Array.isArray(req.body.given_label) ? req.body.given_label : (req.body.given_label ? [req.body.given_label] : []);
  const givenValues = Array.isArray(req.body.given_value) ? req.body.given_value : (req.body.given_value ? [req.body.given_value] : []);
  const given = [];
  givenLabels.forEach((gl, i) => {
    const l = (gl || '').trim();
    const v = (givenValues[i] || '').trim();
    if (l || v) given.push([l, v]);
  });

  const rawFormulas = Array.isArray(req.body.formula_items) ? req.body.formula_items : (req.body.formula_items ? [req.body.formula_items] : []);
  const formulas = rawFormulas.map(x => (x || '').trim()).filter(x => x !== '');

  const rawPlan = Array.isArray(req.body.plan_items) ? req.body.plan_items : (req.body.plan_items ? [req.body.plan_items] : []);
  const plan = rawPlan.map(x => (x || '').trim()).filter(x => x !== '');

  const partLabels = Array.isArray(req.body.part_label) ? req.body.part_label : (req.body.part_label ? [req.body.part_label] : []);
  const partValues = Array.isArray(req.body.part_value) ? req.body.part_value : (req.body.part_value ? [req.body.part_value] : []);
  const partUnits = Array.isArray(req.body.part_unit) ? req.body.part_unit : (req.body.part_unit ? [req.body.part_unit] : []);
  const parts = [];
  partLabels.forEach((pl, i) => {
    const l = (pl || '').trim();
    const v = (partValues[i] || '').trim();
    const u = (partUnits[i] || '').trim();
    if (l || v) {
      parts.push({ label: l, value: !isNaN(v) && v !== '' ? Number(v) : v, unit: u });
    }
  });

  const stepTitles = Array.isArray(req.body.step_title) ? req.body.step_title : (req.body.step_title ? [req.body.step_title] : []);
  const stepDescs = Array.isArray(req.body.step_desc) ? req.body.step_desc : (req.body.step_desc ? [req.body.step_desc] : []);
  const steps = [];
  stepTitles.forEach((st, i) => {
    const t = (st || '').trim();
    const d = (stepDescs[i] || '').trim();
    if (t || d) steps.push({ t, d });
  });

  const givenJson = JSON.stringify(given);
  const hintJson = JSON.stringify({ approach, formulas, plan, tip });
  const partsJson = JSON.stringify(parts);
  const stepsJson = JSON.stringify(steps);

  if (id) {
    db.prepare(`
      UPDATE questions SET code = ?, topic = ?, title = ?, statement = ?, sketch = ?, fig_caption = ?, given_json = ?, hint_json = ?, seed = ?, parts_json = ?, steps_json = ?, original = ?
      WHERE id = ? AND tutorial_id = ?
    `).run(code, topic, title, statement, sketch || null, figCaption || null, givenJson, hintJson, seed || null, partsJson, stepsJson, original, id, tutorialId);
  } else {
    const qKey = 'q' + code.toLowerCase().replace(/[^a-z0-9]/g, '') + Math.random().toString(36).substring(2, 6);
    const maxOrderRow = db.prepare('SELECT COALESCE(MAX(sort_order), 0) as m FROM questions WHERE tutorial_id = ?').get(tutorialId);
    const maxOrder = maxOrderRow ? maxOrderRow.m : 0;

    db.prepare(`
      INSERT INTO questions (tutorial_id, q_key, code, topic, title, statement, sketch, fig_caption, given_json, hint_json, seed, parts_json, steps_json, original, sort_order)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(tutorialId, qKey, code, topic, title, statement, sketch || null, figCaption || null, givenJson, hintJson, seed || null, partsJson, stepsJson, original, maxOrder + 10);
  }

  res.redirect(`/admin/questions.php?tutorial_id=${tutorialId}`);
});

app.post('/admin/question_delete.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const id = parseInt(req.body.id, 10);
  const tutorialId = parseInt(req.body.tutorial_id, 10);

  if (id) {
    db.prepare('DELETE FROM questions WHERE id = ?').run(id);
  }

  res.redirect(`/admin/questions.php?tutorial_id=${tutorialId}`);
});

// PDF Documents Management Routes
app.get('/admin/pdfs.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const tutorial = db.prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?').get(tutorialId);
  if (!tutorial) return res.redirect('/admin/subjects.php');

  const pdfs = db.prepare('SELECT * FROM pdfs WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);

  let tableRows = pdfs.map(p => `
    <tr style="border-bottom:1px solid #F1F5F9;">
      <td style="padding:14px 16px; font-weight:600; color:#0F172A;">${p.title}</td>
      <td style="padding:14px 16px;"><a href="${p.url}" target="_blank" rel="noopener" style="color:#2563EB; font-weight:500; text-decoration:none;">Open PDF &rarr;</a></td>
      <td style="padding:14px 16px; color:#64748B; font-size:13px;">${p.description || '—'}</td>
      <td class="row-actions" style="padding:14px 16px; text-align:right;">
        <a href="/admin/pdf_form.php?tutorial_id=${tutorialId}&id=${p.id}" style="color:#475569; font-weight:600; text-decoration:none; margin-right:12px;">Edit</a>
        <form method="post" action="/admin/pdf_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this PDF?');">
          <input type="hidden" name="id" value="${p.id}">
          <input type="hidden" name="tutorial_id" value="${tutorialId}">
          <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-weight:600; font-size:13px; padding:0;">Delete</button>
        </form>
      </td>
    </tr>
  `).join('');

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/tutorials.php?subject_id=${tutorial.subject_id}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to ${tutorial.subject_name} tutorials</a></div>
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">PDF Documents for ${tutorial.title}</h2>
        <span class="meta" style="color:#64748B; font-size:13px;">${pdfs.length} PDF document${pdfs.length === 1 ? '' : 's'} attached</span>
      </div>
      <a href="/admin/pdf_form.php?tutorial_id=${tutorialId}" class="btn btn-primary" style="background:#059669; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New PDF</a>
    </div>
    ${pdfs.length === 0 ? '<div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No PDF documents added yet.</div>' : `
      <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead>
          <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; text-align:left; font-size:12.5px; color:#475569;">
            <th style="padding:12px 16px;">Title</th>
            <th style="padding:12px 16px;">Link / File</th>
            <th style="padding:12px 16px;">Description</th>
            <th style="padding:12px 16px; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
    `}
  `;

  res.send(renderAdminLayout('PDF Documents', 'subjects', bodyHtml));
});

app.get('/admin/pdf_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const tutorial = db.prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?').get(tutorialId);
  if (!tutorial) return res.redirect('/admin/subjects.php');

  const id = parseInt(req.query.id, 10) || 0;
  let p = null;
  if (id) {
    p = db.prepare('SELECT * FROM pdfs WHERE id = ? AND tutorial_id = ?').get(id, tutorialId);
  }

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/pdfs.php?tutorial_id=${tutorialId}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to PDF documents</a></div>
    <form method="post" action="/admin/pdf_form.php?tutorial_id=${tutorialId}${id ? '&id=' + id : ''}" style="max-width:640px;">
      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:20px;">
        <div class="field" style="margin-bottom:16px;">
          <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Document Title *</label>
          <input type="text" name="title" value="${p ? (p.title || '') : ''}" placeholder="e.g. Lecture Notes — Chapter 2 Dynamics PDF" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:16px;">
          <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">PDF Link / URL *</label>
          <input type="text" name="url" value="${p ? (p.url || '') : ''}" placeholder="https://example.com/document.pdf or file link" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:16px;">
          <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Description / Notes <span style="font-weight:400; color:#64748B;">(optional)</span></label>
          <textarea name="description" rows="3" placeholder="Brief summary of what this PDF covers..." style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box; line-height:1.5;">${p ? (p.description || '') : ''}</textarea>
        </div>
      </div>
      <div class="save-bar" style="display:flex; gap:12px;">
        <a href="/admin/pdfs.php?tutorial_id=${tutorialId}" class="btn btn-outline" style="padding:10px 20px; border-radius:8px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Cancel</a>
        <button type="submit" class="btn btn-primary" style="background:#059669; color:#fff; padding:10px 24px; border-radius:8px; border:none; font-weight:700; cursor:pointer;">${p ? 'Save changes' : 'Add PDF Document'}</button>
      </div>
    </form>
  `;

  res.send(renderAdminLayout(p ? 'Edit PDF' : 'New PDF', 'subjects', bodyHtml));
});

app.post('/admin/pdf_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const id = parseInt(req.query.id, 10) || 0;
  const title = (req.body.title || '').trim();
  const url = (req.body.url || '').trim();
  const description = (req.body.description || '').trim();

  if (!title || !url || !tutorialId) {
    return res.redirect(`/admin/pdfs.php?tutorial_id=${tutorialId}`);
  }

  if (id) {
    db.prepare('UPDATE pdfs SET title = ?, url = ?, description = ? WHERE id = ? AND tutorial_id = ?').run(title, url, description || null, id, tutorialId);
  } else {
    const maxOrderRow = db.prepare('SELECT COALESCE(MAX(sort_order), 0) as m FROM pdfs WHERE tutorial_id = ?').get(tutorialId);
    const maxOrder = maxOrderRow ? maxOrderRow.m : 0;
    db.prepare('INSERT INTO pdfs (tutorial_id, title, url, description, sort_order) VALUES (?, ?, ?, ?, ?)').run(tutorialId, title, url, description || null, maxOrder + 10);
  }

  res.redirect(`/admin/pdfs.php?tutorial_id=${tutorialId}`);
});

app.post('/admin/pdf_delete.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const id = parseInt(req.body.id, 10);
  const tutorialId = parseInt(req.body.tutorial_id, 10);

  if (id) {
    db.prepare('DELETE FROM pdfs WHERE id = ?').run(id);
  }

  res.redirect(`/admin/pdfs.php?tutorial_id=${tutorialId}`);
});

// Text Notes Management Routes
app.get('/admin/notes.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const tutorial = db.prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?').get(tutorialId);
  if (!tutorial) return res.redirect('/admin/subjects.php');

  const notes = db.prepare('SELECT * FROM notes WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);

  let notesHtml = notes.map(n => `
    <div style="background:#fff; border:1.5px solid #E2E8F0; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
        <div>
          <h3 style="margin:0 0 4px 0; font-size:16.5px; font-weight:700; color:#0F172A;">${n.title}</h3>
          ${n.description ? `<span style="font-size:12px; color:#64748B; background:#F1F5F9; padding:2px 8px; border-radius:4px;">${n.description}</span>` : ''}
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <a href="/admin/note_form.php?tutorial_id=${tutorialId}&id=${n.id}" style="color:#475569; font-weight:600; text-decoration:none; font-size:13px;">Edit</a>
          <form method="post" action="/admin/note_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this text note?');">
            <input type="hidden" name="id" value="${n.id}">
            <input type="hidden" name="tutorial_id" value="${tutorialId}">
            <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-weight:600; font-size:13px; padding:0;">Delete</button>
          </form>
        </div>
      </div>
      <div style="background:#F8FAFC; border:1px solid #F1F5F9; border-radius:8px; padding:14px 16px; font-size:13.5px; line-height:1.6; color:#334155; white-space:pre-wrap; max-height:220px; overflow-y:auto;">
        ${n.content}
      </div>
    </div>
  `).join('');

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/tutorials.php?subject_id=${tutorial.subject_id}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to ${tutorial.subject_name} tutorials</a></div>
    <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">Text Notes & Reading Material for ${tutorial.title}</h2>
        <span class="meta" style="color:#64748B; font-size:13px;">${notes.length} text note${notes.length === 1 ? '' : 's'} attached</span>
      </div>
      <a href="/admin/note_form.php?tutorial_id=${tutorialId}" class="btn btn-primary" style="background:#7C3AED; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New Text Note</a>
    </div>
    ${notes.length === 0 ? '<div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No text notes added yet.</div>' : `
      <div style="display:flex; flex-direction:column; gap:16px;">
        ${notesHtml}
      </div>
    `}
  `;

  res.send(renderAdminLayout('Text Notes', 'subjects', bodyHtml));
});

app.get('/admin/note_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const tutorial = db.prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?').get(tutorialId);
  if (!tutorial) return res.redirect('/admin/subjects.php');

  const id = parseInt(req.query.id, 10) || 0;
  let n = null;
  if (id) {
    n = db.prepare('SELECT * FROM notes WHERE id = ? AND tutorial_id = ?').get(id, tutorialId);
  }

  const bodyHtml = `
    <div style="margin-bottom:12px;"><a href="/admin/notes.php?tutorial_id=${tutorialId}" style="color:#102A56; text-decoration:none; font-size:13px;">&larr; Back to text notes</a></div>
    <form method="post" action="/admin/note_form.php?tutorial_id=${tutorialId}${id ? '&id=' + id : ''}" style="max-width:720px;">
      <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:20px;">
        <div class="field" style="margin-bottom:16px;">
          <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Note Title *</label>
          <input type="text" name="title" value="${n ? (n.title || '') : ''}" placeholder="e.g. Fundamental Concepts & Key Equations" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:16px;">
          <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Category / Tag <span style="font-weight:400; color:#64748B;">(optional)</span></label>
          <input type="text" name="description" value="${n ? (n.description || '') : ''}" placeholder="e.g. Summary, Formulas, Homework Tip" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
        </div>
        <div class="field" style="margin-bottom:16px;">
          <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Text / Content Body *</label>
          <textarea name="content" rows="10" placeholder="Write full text notes, explanations, formulas, or study instructions here..." required style="width:100%; padding:12px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box; line-height:1.6; font-family:inherit;">${n ? (n.content || '') : ''}</textarea>
        </div>
      </div>
      <div class="save-bar" style="display:flex; gap:12px;">
        <a href="/admin/notes.php?tutorial_id=${tutorialId}" class="btn btn-outline" style="padding:10px 20px; border-radius:8px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Cancel</a>
        <button type="submit" class="btn btn-primary" style="background:#7C3AED; color:#fff; padding:10px 24px; border-radius:8px; border:none; font-weight:700; cursor:pointer;">${n ? 'Save changes' : 'Add Text Note'}</button>
      </div>
    </form>
  `;

  res.send(renderAdminLayout(n ? 'Edit Text Note' : 'New Text Note', 'subjects', bodyHtml));
});

app.post('/admin/note_form.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const tutorialId = parseInt(req.query.tutorial_id, 10) || 0;
  const id = parseInt(req.query.id, 10) || 0;
  const title = (req.body.title || '').trim();
  const content = (req.body.content || '').trim();
  const description = (req.body.description || '').trim();

  if (!title || !content || !tutorialId) {
    return res.redirect(`/admin/notes.php?tutorial_id=${tutorialId}`);
  }

  if (id) {
    db.prepare('UPDATE notes SET title = ?, content = ?, description = ? WHERE id = ? AND tutorial_id = ?').run(title, content, description || null, id, tutorialId);
  } else {
    const maxOrderRow = db.prepare('SELECT COALESCE(MAX(sort_order), 0) as m FROM notes WHERE tutorial_id = ?').get(tutorialId);
    const maxOrder = maxOrderRow ? maxOrderRow.m : 0;
    db.prepare('INSERT INTO notes (tutorial_id, title, content, description, sort_order) VALUES (?, ?, ?, ?, ?)').run(tutorialId, title, content, description || null, maxOrder + 10);
  }

  res.redirect(`/admin/notes.php?tutorial_id=${tutorialId}`);
});

app.post('/admin/note_delete.php', (req, res) => {
  const user = getSessionUser(req);
  if (!user || !user.is_admin) return res.redirect('/login.html');

  const id = parseInt(req.body.id, 10);
  const tutorialId = parseInt(req.body.tutorial_id, 10);

  if (id) {
    db.prepare('DELETE FROM notes WHERE id = ?').run(id);
  }

  res.redirect(`/admin/notes.php?tutorial_id=${tutorialId}`);
});

// API Routes for PDFs and Text Notes
app.get('/api/pdfs.php', (req, res) => {
  const tutorialId = parseInt(req.query.tutorial, 10) || 0;
  if (!tutorialId) return res.json({ pdfs: [] });
  const rows = db.prepare('SELECT * FROM pdfs WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);
  res.json({ pdfs: rows });
});

app.get('/api/notes.php', (req, res) => {
  const tutorialId = parseInt(req.query.tutorial, 10) || 0;
  if (!tutorialId) return res.json({ notes: [] });
  const rows = db.prepare('SELECT * FROM notes WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC').all(tutorialId);
  res.json({ notes: rows });
});

// Serve static frontend files
app.use(express.static(__dirname));

app.listen(PORT, () => {
  console.log(`MCEN2003 Machine Dynamics Website running at http://localhost:${PORT}`);
});
