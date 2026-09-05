<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();
$tutorialId = (int)($_GET['tutorial_id'] ?? 0);
$stmt = $pdo->prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?');
$stmt->execute([$tutorialId]);
$tutorial = $stmt->fetch();
if (!$tutorial) { header('Location: subjects.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ? AND tutorial_id = ?');
    $stmt->execute([$id, $tutorialId]);
    $q = $stmt->fetch();
    if (!$q) { header('Location: questions.php?tutorial_id=' . $tutorialId); exit; }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $statement = trim($_POST['statement'] ?? '');
    $sketch = trim($_POST['sketch'] ?? '');
    $figCaption = trim($_POST['fig_caption'] ?? '');
    $seed = trim($_POST['seed'] ?? '');
    $approach = trim($_POST['hint_approach'] ?? '');
    $tip = trim($_POST['hint_tip'] ?? '');
    $original = trim($_POST['original'] ?? '');

    if ($code === '' || $topic === '' || $title === '' || $statement === '') {
        $error = 'Code, topic, title and statement are required.';
    } else {
        $given = [];
        $gl = $_POST['given_label'] ?? [];
        $gv = $_POST['given_value'] ?? [];
        foreach ($gl as $i => $label) {
            $label = trim($label);
            $value = trim($gv[$i] ?? '');
            if ($label !== '' || $value !== '') $given[] = [$label, $value];
        }

        $formulas = array_values(array_filter(array_map('trim', $_POST['formula_items'] ?? []), fn($v) => $v !== ''));
        $plan = array_values(array_filter(array_map('trim', $_POST['plan_items'] ?? []), fn($v) => $v !== ''));

        $parts = [];
        $pl = $_POST['part_label'] ?? [];
        $pv = $_POST['part_value'] ?? [];
        $pu = $_POST['part_unit'] ?? [];
        foreach ($pl as $i => $label) {
            $label = trim($label);
            $value = trim($pv[$i] ?? '');
            $unit = trim($pu[$i] ?? '');
            if ($label === '' && $value === '') continue;
            $parts[] = ['label' => $label, 'value' => is_numeric($value) ? $value + 0 : $value, 'unit' => $unit];
        }

        $steps = [];
        $st = $_POST['step_title'] ?? [];
        $sd = $_POST['step_desc'] ?? [];
        foreach ($st as $i => $t) {
            $t = trim($t);
            $d = trim($sd[$i] ?? '');
            if ($t === '' && $d === '') continue;
            $steps[] = ['t' => $t, 'd' => $d];
        }

        $fig = $q['fig'] ?? null;
        if (!empty($_FILES['fig_file']['tmp_name']) && is_uploaded_file($_FILES['fig_file']['tmp_name'])) {
            $mime = mime_content_type($_FILES['fig_file']['tmp_name']);
            if (str_starts_with($mime, 'image/')) {
                $fig = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($_FILES['fig_file']['tmp_name']));
            }
        }
        if (!empty($_POST['remove_fig'])) $fig = null;

        $qKey = $q['q_key'] ?? ('q' . preg_replace('/[^a-z0-9]/', '', strtolower($code)) . substr(uniqid(), -4));

        if ($q) {
            $stmt = $pdo->prepare('UPDATE questions SET code=?, topic=?, title=?, statement=?, sketch=?, fig=?, fig_caption=?, given_json=?, hint_json=?, seed=?, parts_json=?, steps_json=?, original=? WHERE id=?');
            $stmt->execute([
                $code, $topic, $title, $statement, $sketch ?: null, $fig, $figCaption ?: null,
                json_encode($given), json_encode(['approach' => $approach, 'formulas' => $formulas, 'plan' => $plan, 'tip' => $tip]),
                $seed ?: null, json_encode($parts), json_encode($steps), $original, $q['id'],
            ]);
        } else {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM questions WHERE tutorial_id = ' . (int)$tutorialId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO questions (tutorial_id, q_key, code, topic, title, statement, sketch, fig, fig_caption, given_json, hint_json, seed, parts_json, steps_json, original, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $tutorialId, $qKey, $code, $topic, $title, $statement, $sketch ?: null, $fig, $figCaption ?: null,
                json_encode($given), json_encode(['approach' => $approach, 'formulas' => $formulas, 'plan' => $plan, 'tip' => $tip]),
                $seed ?: null, json_encode($parts), json_encode($steps), $original, $maxOrder + 10,
            ]);
        }
        header('Location: questions.php?tutorial_id=' . $tutorialId);
        exit;
    }
}

$given = $q ? json_decode($q['given_json'], true) : [['', '']];
$hint = $q ? json_decode($q['hint_json'], true) : ['approach' => '', 'formulas' => [''], 'plan' => [''], 'tip' => ''];
$parts = $q ? json_decode($q['parts_json'], true) : [['label' => '', 'value' => '', 'unit' => '']];
$steps = $q ? json_decode($q['steps_json'], true) : [['t' => '', 'd' => '']];
if (!$given) $given = [['', '']];
if (!($hint['formulas'] ?? [])) $hint['formulas'] = [''];
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();
$tutorialId = (int)($_GET['tutorial_id'] ?? 0);
$stmt = $pdo->prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?');
$stmt->execute([$tutorialId]);
$tutorial = $stmt->fetch();
if (!$tutorial) { header('Location: subjects.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ? AND tutorial_id = ?');
    $stmt->execute([$id, $tutorialId]);
    $q = $stmt->fetch();
    if (!$q) { header('Location: questions.php?tutorial_id=' . $tutorialId); exit; }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $statement = trim($_POST['statement'] ?? '');
    $sketch = trim($_POST['sketch'] ?? '');
    $figCaption = trim($_POST['fig_caption'] ?? '');
    $seed = trim($_POST['seed'] ?? '');
    $approach = trim($_POST['hint_approach'] ?? '');
    $tip = trim($_POST['hint_tip'] ?? '');
    $original = trim($_POST['original'] ?? '');

    if ($code === '' || $topic === '' || $title === '' || $statement === '') {
        $error = 'Code, topic, title and statement are required.';
    } else {
        $given = [];
        $gl = $_POST['given_label'] ?? [];
        $gv = $_POST['given_value'] ?? [];
        foreach ($gl as $i => $label) {
            $label = trim($label);
            $value = trim($gv[$i] ?? '');
            if ($label !== '' || $value !== '') $given[] = [$label, $value];
        }

        $formulas = array_values(array_filter(array_map('trim', $_POST['formula_items'] ?? []), fn($v) => $v !== ''));
        $plan = array_values(array_filter(array_map('trim', $_POST['plan_items'] ?? []), fn($v) => $v !== ''));

        $parts = [];
        $pl = $_POST['part_label'] ?? [];
        $pv = $_POST['part_value'] ?? [];
        $pu = $_POST['part_unit'] ?? [];
        foreach ($pl as $i => $label) {
            $label = trim($label);
            $value = trim($pv[$i] ?? '');
            $unit = trim($pu[$i] ?? '');
            if ($label === '' && $value === '') continue;
            $parts[] = ['label' => $label, 'value' => is_numeric($value) ? $value + 0 : $value, 'unit' => $unit];
        }

        $steps = [];
        $st = $_POST['step_title'] ?? [];
        $sd = $_POST['step_desc'] ?? [];
        foreach ($st as $i => $t) {
            $t = trim($t);
            $d = trim($sd[$i] ?? '');
            if ($t === '' && $d === '') continue;
            $steps[] = ['t' => $t, 'd' => $d];
        }

        $fig = $q['fig'] ?? null;
        if (!empty($_FILES['fig_file']['tmp_name']) && is_uploaded_file($_FILES['fig_file']['tmp_name'])) {
            $mime = mime_content_type($_FILES['fig_file']['tmp_name']);
            if (str_starts_with($mime, 'image/')) {
                $fig = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($_FILES['fig_file']['tmp_name']));
            }
        }
        if (!empty($_POST['remove_fig'])) $fig = null;

        $qKey = $q['q_key'] ?? ('q' . preg_replace('/[^a-z0-9]/', '', strtolower($code)) . substr(uniqid(), -4));

        if ($q) {
            $stmt = $pdo->prepare('UPDATE questions SET code=?, topic=?, title=?, statement=?, sketch=?, fig=?, fig_caption=?, given_json=?, hint_json=?, seed=?, parts_json=?, steps_json=?, original=? WHERE id=?');
            $stmt->execute([
                $code, $topic, $title, $statement, $sketch ?: null, $fig, $figCaption ?: null,
                json_encode($given), json_encode(['approach' => $approach, 'formulas' => $formulas, 'plan' => $plan, 'tip' => $tip]),
                $seed ?: null, json_encode($parts), json_encode($steps), $original, $q['id'],
            ]);
        } else {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM questions WHERE tutorial_id = ' . (int)$tutorialId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO questions (tutorial_id, q_key, code, topic, title, statement, sketch, fig, fig_caption, given_json, hint_json, seed, parts_json, steps_json, original, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $tutorialId, $qKey, $code, $topic, $title, $statement, $sketch ?: null, $fig, $figCaption ?: null,
                json_encode($given), json_encode(['approach' => $approach, 'formulas' => $formulas, 'plan' => $plan, 'tip' => $tip]),
                $seed ?: null, json_encode($parts), json_encode($steps), $original, $maxOrder + 10,
            ]);
        }
        header('Location: questions.php?tutorial_id=' . $tutorialId);
        exit;
    }
}

$given = $q ? json_decode($q['given_json'], true) : [['', '']];
$hint = $q ? json_decode($q['hint_json'], true) : ['approach' => '', 'formulas' => [''], 'plan' => [''], 'tip' => ''];
$parts = $q ? json_decode($q['parts_json'], true) : [['label' => '', 'value' => '', 'unit' => '']];
$steps = $q ? json_decode($q['steps_json'], true) : [['t' => '', 'd' => '']];
if (!$given) $given = [['', '']];
if (!($hint['formulas'] ?? [])) $hint['formulas'] = [''];
if (!($hint['plan'] ?? [])) $hint['plan'] = [''];
if (!$parts) $parts = [['label' => '', 'value' => '', 'unit' => '']];
if (!$steps) $steps = [['t' => '', 'd' => '']];

admin_page_start($q ? 'Edit ' . $q['code'] : 'New Question', 'subjects', 'questions.php?tutorial_id=' . $tutorialId, 'Back to ' . $tutorial['title']);
?>
<?php if (isset($_GET['created_tutorial'])): ?>
  <div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-weight:600;">
    🎉 Tutorial created successfully! Now add your first question for <strong><?= h($tutorial['title']) ?></strong> below.
  </div>
<?php endif; ?>

<div class="card" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid #334155; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <div style="display:flex; align-items:center; gap:12px;">
      <div style="background:linear-gradient(135deg, #6366F1, #8B5CF6); width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:bold; box-shadow:0 2px 10px rgba(99,102,241,0.3);">✨</div>
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
      <span id="aiBtnText">🚀 Generate Question & Auto-Fill Form</span>
    </button>
  </div>
</div>

<form method="post" enctype="multipart/form-data" id="qForm">
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <div class="card">
    <h2>Basics</h2>
    <div class="row">
      <div class="field"><label>Code (e.g. T1.6)</label><input type="text" name="code" value="<?= h($q['code'] ?? '') ?>" required></div>
      <div class="field"><label>Topic</label><input type="text" name="topic" value="<?= h($q['topic'] ?? '') ?>" required></div>
    </div>
    <div class="field"><label>Title</label><input type="text" name="title" value="<?= h($q['title'] ?? '') ?>" required></div>
    <div class="field"><label>Statement</label><textarea name="statement" rows="4" required><?= h($q['statement'] ?? '') ?></textarea></div>
    <div class="field"><label>Sketch note <span class="hint">(optional — a hint about drawing/labelling before starting)</span></label><textarea name="sketch" rows="2"><?= h($q['sketch'] ?? '') ?></textarea></div>
  </div>

  <div class="card">
    <h2>Figure (optional)</h2>
    <?php if (!empty($q['fig'])): ?>
      <img style="max-width:200px;border-radius:6px;border:1px solid #E2E8F0;display:block;margin-bottom:8px" src="<?= h($q['fig']) ?>" alt="Current figure">
      <label style="display:flex;align-items:center;gap:6px;margin-bottom:10px;font-size:12.5px;color:#64748B"><input type="checkbox" name="remove_fig" value="1" style="width:auto"> Remove current figure</label>
    <?php endif; ?>
    <div class="field"><label>Upload new figure image (replaces current)</label><input type="file" name="fig_file" accept="image/*"></div>
    <div class="field"><label>Figure caption</label><input type="text" name="fig_caption" value="<?= h($q['fig_caption'] ?? '') ?>" placeholder="e.g. Figure 1 — Accelerometer signal"></div>
  </div>

  <div class="card">
    <h2>Given</h2>
    <div id="givenRows">
      <?php foreach ($given as $g): ?>
      <div class="rep-row">
        <input type="text" name="given_label[]" placeholder="Label (e.g. v₀)" value="<?= h($g[0] ?? '') ?>">
        <input type="text" name="given_value[]" placeholder="Value (e.g. 50 m/s)" value="<?= h($g[1] ?? '') ?>">
        <button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addGiven()">+ Add given value</button>
  </div>

  <div class="card">
    <h2>Hint</h2>
    <div class="field"><label>Approach</label><textarea name="hint_approach" rows="3"><?= h($hint['approach'] ?? '') ?></textarea></div>
    <div class="field"><label>Formulas</label></div>
    <div id="formulaRows">
      <?php foreach ($hint['formulas'] as $f): ?>
      <div class="rep-row"><input type="text" name="formula_items[]" value="<?= h($f) ?>"><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addFormula()">+ Add formula</button>

    <div class="field" style="margin-top:16px"><label>Plan (step-by-step approach)</label></div>
    <div id="planRows">
      <?php foreach ($hint['plan'] as $p): ?>
      <div class="rep-row"><textarea name="plan_items[]" rows="2"><?= h($p) ?></textarea><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addPlan()">+ Add plan step</button>

    <div class="field" style="margin-top:16px"><label>Tip</label><textarea name="hint_tip" rows="2"><?= h($hint['tip'] ?? '') ?></textarea></div>
  </div>

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

  <div class="card">
    <h2>Answer Parts</h2>
    <p class="hint" style="margin:-8px 0 8px 0">Each part is what the student types an answer for and gets checked against.</p>
    <div class="unit-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; align-items:center;">
      <span style="font-size:12px; font-weight:700; color:#475569; margin-right:4px;">Quick Units:</span>
      <?php foreach (['m/s', 'km/h', 'm/s²', 'rad/s', 'rad/s²', 'rpm', 'Hz', 'N', 'kN', 'N·m', 'kg', 'm', 'mm', 's', 'kPa', 'J', 'W'] as $u): ?>
        <button type="button" class="chip" onclick="applyQuickUnit('<?= $u ?>')" style="background:#F1F5F9; border:1px solid #CBD5E1; color:#0F172A; padding:3px 8px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; font-family:monospace;"><?= $u ?></button>
      <?php endforeach; ?>
    </div>
    <div id="partRows">
      <?php foreach ($parts as $p): ?>
      <div class="rep-row">
        <input type="text" name="part_label[]" placeholder="(a) Velocity at t = 8 s" value="<?= h($p['label'] ?? '') ?>">
        <input type="text" name="part_value[]" placeholder="Correct value, e.g. 10" value="<?= h($p['value'] ?? '') ?>">
        <input type="text" name="part_unit[]" list="unitSuggestions" placeholder="Unit, e.g. m/s" value="<?= h($p['unit'] ?? '') ?>">
        <button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addPart()">+ Add answer part</button>
  </div>

  <div class="card">
    <h2>Worked Steps</h2>
    <p class="hint" style="margin:-8px 0 12px 0">Shown to students as the guided solution, step by step.</p>
    <div id="stepRows">
      <?php foreach ($steps as $s): ?>
      <div class="rep-row" style="flex-direction:column">
        <input type="text" name="step_title[]" placeholder="Step title" value="<?= h($s['t'] ?? '') ?>">
        <textarea name="step_desc[]" rows="2" placeholder="Step working (use \n for line breaks)"><?= h($s['d'] ?? '') ?></textarea>
        <button type="button" class="rm-btn" onclick="this.parentElement.remove()" style="align-self:flex-start">Remove step</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addStep()">+ Add step</button>
  </div>

  <div class="card">
    <h2>Full Worked Solution</h2>
    <p class="hint" style="margin:-8px 0 12px 0">The complete original-style solution text shown when a student reveals the full solution.</p>
    <textarea name="original" rows="12"><?= h($q['original'] ?? '') ?></textarea>
  </div>

  <div class="card">
    <h2>Calculator seed (optional, advanced)</h2>
    <p class="hint" style="margin:-8px 0 12px 0">Pre-fills the built-in calculator with these variable assignments when this question is open.</p>
    <textarea name="seed" rows="3" placeholder="u = 50&#10;a = -10&#10;t1 = 4"><?= h($q['seed'] ?? '') ?></textarea>
  </div>

  <div class="save-bar">
    <a href="questions.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-outline">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= $q ? 'Save changes' : 'Create question' ?></button>
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
function addRow(containerId, html) {
  var div = document.createElement('div');
  div.innerHTML = html;
  document.getElementById(containerId).appendChild(div.firstElementChild);
}
function addGiven() {
  addRow('givenRows', '<div class="rep-row"><input type="text" name="given_label[]" placeholder="Label"><input type="text" name="given_value[]" placeholder="Value"><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>');
}
function addFormula() {
  addRow('formulaRows', '<div class="rep-row"><input type="text" name="formula_items[]"><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>');
}
function addPlan() {
  addRow('planRows', '<div class="rep-row"><textarea name="plan_items[]" rows="2"></textarea><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>');
}
function addPart() {
  addRow('partRows', '<div class="rep-row"><input type="text" name="part_label[]" placeholder="Label"><input type="text" name="part_value[]" placeholder="Value"><input type="text" name="part_unit[]" list="unitSuggestions" placeholder="Unit, e.g. m/s"><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>');
}
function addStep() {
  addRow('stepRows', '<div class="rep-row" style="flex-direction:column"><input type="text" name="step_title[]" placeholder="Step title"><textarea name="step_desc[]" rows="2" placeholder="Step working"></textarea><button type="button" class="rm-btn" onclick="this.parentElement.remove()" style="align-self:flex-start">Remove step</button></div>');
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
  btnText.textContent = '⏳ Generating Question... Please wait';

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
      alert('🎉 Success! Question generated and form fields auto-filled below. Please review and click Save.');
    } else {
      throw new Error('Invalid response received from AI service.');
    }
  } catch (err) {
    errBox.textContent = err.message || 'An error occurred during AI generation.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btnText.textContent = '🚀 Generate Question & Auto-Fill Form';
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
  div.innerHTML = '<input type="text" name="step_title[]" placeholder="Step title" value="' + escapeAttr(t) + '" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;"><textarea name="step_desc[]" rows="3" placeholder="Step calculation" value="' + escapeAttr(d) + '" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px; box-sizing:border-box;">' + escapeHtml(d) + '</textarea><button type="button" onclick="this.parentElement.remove()" style="align-self:flex-start; background:none; border:1px solid #FCA5A5; color:#DC2626; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Remove step</button>';
  document.getElementById('stepRows').appendChild(div);
}
function escapeAttr(s) { return String(s || '').replace(/"/g, '&quot;'); }
function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
</script>
<?php admin_page_end(); ?>
