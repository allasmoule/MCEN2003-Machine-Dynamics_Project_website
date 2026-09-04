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

  <div class="card">
    <h2>Answer Parts</h2>
    <p class="hint" style="margin:-8px 0 12px 0">Each part is what the student types an answer for and gets checked against.</p>
    <div id="partRows">
      <?php foreach ($parts as $p): ?>
      <div class="rep-row">
        <input type="text" name="part_label[]" placeholder="(a) Velocity at t = 8 s" value="<?= h($p['label'] ?? '') ?>">
        <input type="text" name="part_value[]" placeholder="Correct value, e.g. 10" value="<?= h($p['value'] ?? '') ?>">
        <input type="text" name="part_unit[]" placeholder="Unit, e.g. m/s" value="<?= h($p['unit'] ?? '') ?>">
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
  addRow('partRows', '<div class="rep-row"><input type="text" name="part_label[]" placeholder="Label"><input type="text" name="part_value[]" placeholder="Value"><input type="text" name="part_unit[]" placeholder="Unit"><button type="button" class="rm-btn" onclick="this.parentElement.remove()">Remove</button></div>');
}
function addStep() {
  addRow('stepRows', '<div class="rep-row" style="flex-direction:column"><input type="text" name="step_title[]" placeholder="Step title"><textarea name="step_desc[]" rows="2" placeholder="Step working"></textarea><button type="button" class="rm-btn" onclick="this.parentElement.remove()" style="align-self:flex-start">Remove step</button></div>');
}
</script>
<?php admin_page_end(); ?>
