---
name: add-tutorial
description: Add a new tutorial (Tutorial 2, 3, …) or add questions / videos / PDFs / notes to an existing tutorial on the MCEN2003 Machine Dynamics website, in the same format as Tutorial 1, test it locally, then commit and push to GitHub main automatically. Use when the user says things like "Tutorial 2 banaw", "Tutorial 1 e aro question add koro", "add questions on projectile motion", or invokes /add-tutorial.
argument-hint: "<Tutorial N> <topic> [number of questions] — e.g. \"Tutorial 2: Relative motion, 6 questions\" or \"Tutorial 1: add 3 more on projectile motion\""
---

# /add-tutorial — add tutorial content and push it live

The user (Dr. Raju Ahamed's site owner) speaks Bangla/Banglish. Reply in the same
mix they use, keep it short, and do the whole job without asking unless the request
is genuinely unclear (e.g. no topic given for a brand-new tutorial).

Request: `$ARGUMENTS`

Work out from the request:
- **Which tutorial** — a new one (`Tutorial N` that does not exist yet) or an existing one.
- **Topic** — what the questions are about.
- **How many questions** — default 5 for a new tutorial, 3 when adding to an existing one.
- Any **videos / PDFs / notes** the user gave (URLs, titles).

## How the site stores content (read this first)

- The live site (Vercel) uses a throwaway SQLite DB that is rebuilt from files in git on
  every cold start. **Anything added only through the admin panel disappears.** All content
  must live in files in the repo.
- Tutorial 1's original 5 questions: `seed/questions_seed.json` (legacy — do not edit).
- Everything else: **one JSON file per tutorial in `seed/tutorials/`**, loaded by
  `seedTutorialFiles()` in `server.js`:
  - `seed/tutorials/tutorial-2.json` → creates/updates Tutorial 2.
  - `seed/tutorials/tutorial-1.json` with `"slug": "tutorial-1"` and no title → adds extra
    questions to Tutorial 1 (append to its `questions` array if the file already exists).
  - Questions are upserted by `id`; videos/PDFs/notes are added when their `title` is new.
- The tutorial page is always `tutorial1.html?tutorial=<db id>`. The subject page lists
  tutorials from the API, so a new tutorial appears there automatically. No new HTML page.
- Per-question teaching extras live in `workbook.js`, in objects keyed by question id:
  `TRAPS`, `CONCEPT`, `WHY`, `GRAPHS` (search for `const TRAPS = {` etc.).

## Steps

### 1. Sync and look

```bash
git checkout main && git pull --ff-only origin main
ls seed/tutorials/ 2>/dev/null
```

Read the existing questions so the new ones match the style and do not repeat them:
`seed/questions_seed.json` (skip the huge `fig` base64 strings — read with a script that
drops `fig`) and any `seed/tutorials/*.json`. Collect every question `id` and `code` in use.

### 2. Ids and codes

- `code`: `T<N>.<k>` continuing the numbering (Tutorial 1 has T1.1–T1.5, so the next is T1.6;
  a new Tutorial 2 starts at T2.1).
- `id`: `t<N><k>` (e.g. `t16`, `t21`). If N ≥ 10 or k ≥ 10 use `t<N>_<k>` (e.g. `t2_10`).
  Ids must be unique across **all** tutorials because `workbook.js` keys on them.

### 3. Write the questions (same format as Tutorial 1)

Create or extend `seed/tutorials/tutorial-<N>.json`:

```json
{
  "slug": "tutorial-2",
  "title": "Tutorial 2 — Relative Motion",
  "description": "Interactive tutorial questions with diagrams & step-by-step reasoning.",
  "sort_order": 2,
  "questions": [ { ...question... } ],
  "videos": [ { "title": "...", "url": "https://www.youtube.com/watch?v=...", "description": "..." } ],
  "pdfs":   [ { "title": "...", "url": "https://...", "description": "..." } ],
  "notes":  [ { "title": "...", "description": "short tag", "content": "plain text, \n for new lines" } ],
  "formula_sheet": { "heading": "...", "subheading": "...", "boxes": [ { "title": "...", "items": ["HTML string", "..."] } ] }
}
```

`title`/`description`/`sort_order` only for a new tutorial (omit them in `tutorial-1.json`).
`formula_sheet` is optional; without it the global sheet (`seed/formula_sheet_seed.json`) shows.
Same shape as that file.

**Always give a new tutorial at least one note, and add videos/PDFs only if the user supplied
real URLs.** If a tutorial has no videos/PDFs, the page falls back to Tutorial 1's defaults.
Never invent YouTube or PDF links. If none were given, tell the user so in the report.

Each question, matching Tutorial 1 exactly:

| field | meaning |
| --- | --- |
| `id`, `code` | from step 2 |
| `topic` | short topic label, e.g. "Relative velocity" |
| `title` | one-line title |
| `statement` | full problem text, as on a tutorial sheet. Use Unicode (v₀, m/s², −, θ, ω) |
| `given` | `[["symbol", "value with unit"], ...]` |
| `hint` | `{ "approach": "...", "formulas": ["..."], "plan": ["step", ...], "tip": "..." }` — approach and plan, **no numbers from the answer** |
| `seed` | optional working-pad starter, e.g. `"u = 50\na = -10\n"` |
| `parts` | `[{ "label": "(a) Velocity at t = 8 s", "value": 10, "unit": "m/s" }, ...]` — numeric answers |
| `steps` | `[{ "t": "step heading", "d": "working, \n for new lines" }, ...]` — the worked solution |
| `original` | the whole solution as one plain-text block (like the "Original solution" sheet) |
| `sketch` | optional: one line telling the student what to draw first |
| `fig`, `figCap` | optional figure: `fig` is an HTML string, use an inline `<svg ...>` diagram (no external images); `figCap` like "Figure 1 — Belt-pulley arrangement" |

Quality rules:
- University 2nd-year engineering level, SI units, realistic numbers, same tone as Tutorial 1.
- Every question gets 2–5 parts, each with a single numeric answer.
- **Compute every `value` with a script, don't do it in your head.** Write a small Node
  script in the scratchpad that re-derives each part from the given data and asserts it
  matches the JSON (answers are marked correct within 1.5% relative tolerance, `TOL` in
  `workbook.js`). Fix any mismatch before going on.
- Steps must lead to exactly the numbers in `parts`, and `original` must agree with both.

### 4. Teaching extras in `workbook.js`

For every new question id, add an entry to each of these objects (insert before the
closing `};` of each, keep the existing style):

- `TRAPS[id]` — one array per part, in part order: `[[wrongValue, "why this wrong answer happens"], ...]`.
  2–3 realistic misconceptions per part (wrong sign, wrong phase, forgot constant, degrees vs radians …).
  Compute the wrong values too.
- `CONCEPT[id]` — `{ head: "...", body: "one paragraph", carry: "one sentence to carry forward" }`.
- `WHY[id]` — array of paragraphs, **exactly one per entry in `steps`**, index-matched.
- `GRAPHS[id]` — only for 1-D motion where a/v/s against time makes sense:
  `{ tmax, sym:["a","v","s"] (or ["α","ω","θ"]), units:[...], a:t=>..., v:t=>..., s:t=>..., marks:[key times] }`.
  Skip otherwise.

Then `node --check workbook.js`.

### 5. Test locally

```bash
node -e "JSON.parse(require('fs').readFileSync('seed/tutorials/tutorial-<N>.json','utf8'))"
```

Start the site with the preview tool (`.claude/launch.json` → `mcen2003-site`, port 3000), or
restart it if running so the seeder runs. Check the server log for the
`seed/tutorials/...: N question(s) added` line, then in the browser:
1. `subject.html?slug=mcen2003-machine-dynamics` lists the tutorial.
2. `POST api/demo_login.php` (fetch from the page) to log in as a demo student, then open
   `tutorial1.html?tutorial=<id>` and confirm the title, question count, every new question,
   its figure, the Idea panel, and that entering each correct answer marks it right.
3. No console errors.

### 6. Commit and push to main (automatic — the user asked for this)

Stage only the files you changed (never `app.db`, `node_modules`, `.claude/launch.json`):

```bash
git add seed/tutorials/tutorial-<N>.json workbook.js
git commit -m "Add Tutorial <N>: <topic> (<k> questions)"      # or "Tutorial <N>: add <k> questions on <topic>"
git pull --rebase origin main
git push origin main
```

End the commit message with the attribution line from the current system reminder, if one is given.
If the push is rejected or needs sign-in, stop and tell the user exactly what failed. Don't force-push.

### 7. Report (short, in the user's language)

- What was added: tutorial, question codes and titles.
- That it is pushed to `main` (commit hash) and the site redeploys from there.
- Anything missing: e.g. no video/PDF links given, so Tutorial 1's defaults show until they send links.
