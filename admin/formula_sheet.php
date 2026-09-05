<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();

// Fetch all subjects for the dropdown
$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY sort_order ASC, id ASC')->fetchAll();

$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : ($subjects[0]['id'] ?? 0);

// Fetch tutorials under selected subject
$tutorials = [];
if ($subjectId) {
    $stmt = $pdo->prepare('SELECT id, title FROM tutorials WHERE subject_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$subjectId]);
    $tutorials = $stmt->fetchAll();
}

$tutorialId = isset($_GET['tutorial_id']) ? (int)$_GET['tutorial_id'] : ($tutorials[0]['id'] ?? 0);

// Fetch formula sheet for selected tutorial
$blockKey = $tutorialId ? 'formula_sheet_t' . $tutorialId : 'formula_sheet';
$stmt = $pdo->prepare('SELECT value_json FROM content_blocks WHERE block_key = ?');
$stmt->execute([$blockKey]);
$row = $stmt->fetch();

if (!$row && $tutorialId) {
    // Fallback to default formula_sheet if specific tutorial has no custom formula sheet yet
    $stmt = $pdo->prepare('SELECT value_json FROM content_blocks WHERE block_key = ?');
    $stmt->execute(['formula_sheet']);
    $row = $stmt->fetch();
}

$sheet = $row ? json_decode($row['value_json'], true) : ['heading' => '', 'subheading' => '', 'boxes' => []];
if (!$sheet['boxes']) $sheet['boxes'] = [['title' => '', 'items' => ['']]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subId = (int)($_POST['subject_id'] ?? 0);
    $tutId = (int)($_POST['tutorial_id'] ?? 0);
    $heading = trim($_POST['heading'] ?? '');
    $subheading = trim($_POST['subheading'] ?? '');
    $boxTitles = $_POST['box_title'] ?? [];
    $boxItems = $_POST['box_items'] ?? [];

    $boxes = [];
    foreach ($boxTitles as $i => $title) {
        $title = trim($title);
        $items = array_values(array_filter(array_map('trim', explode("\n", $boxItems[$i] ?? '')), fn($v) => $v !== ''));
        if ($title === '' && !$items) continue;
        $boxes[] = ['title' => $title, 'items' => $items];
    }

    $value = ['heading' => $heading, 'subheading' => $subheading, 'boxes' => $boxes];
    $saveKey = $tutId ? 'formula_sheet_t' . $tutId : 'formula_sheet';

    $stmt = $pdo->prepare('INSERT INTO content_blocks (block_key, value_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)');
    $stmt->execute([$saveKey, json_encode($value)]);

    header('Location: formula_sheet.php?subject_id=' . $subId . '&tutorial_id=' . $tutId . '&saved=1');
    exit;
}

admin_page_start('Formula Sheet', 'formula');
?>

<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px 22px; margin-bottom: 20px;">
  <form method="get" id="selectForm" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 220px;">
      <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em;">Select Subject</label>
      <select name="subject_id" onchange="document.getElementById('selectForm').submit()" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 600; color: #102A56;">
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $subjectId ? 'selected' : '' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="flex: 1; min-width: 220px;">
      <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em;">Select Tutorial</label>
      <select name="tutorial_id" onchange="document.getElementById('selectForm').submit()" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 600; color: #102A56;">
        <?php if (!$tutorials): ?>
          <option value="0">No tutorials found</option>
        <?php else: ?>
          <?php foreach ($tutorials as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $t['id'] == $tutorialId ? 'selected' : '' ?>><?= h($t['title']) ?></option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </div>
  </form>
</div>

<form method="post" id="fsForm">
  <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">
  <input type="hidden" name="tutorial_id" value="<?= (int)$tutorialId ?>">

  <?php if (isset($_GET['saved'])): ?><div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:10px 14px; border-radius:8px; margin-bottom:18px; font-weight:500;">Saved — changes are live on this tutorial's workbook now.</div><?php endif; ?>

  <div class="card">
    <div class="field"><label>Heading</label><input type="text" name="heading" value="<?= h($sheet['heading'] ?? '') ?>"></div>
    <div class="field"><label>Subheading</label><input type="text" name="subheading" value="<?= h($sheet['subheading'] ?? '') ?>"></div>
  </div>

  <div class="card">
    <h2 style="font-size: 16px; margin: 0 0 8px 0; color: #0F172A;">Formula Boxes</h2>
    <p class="hint" style="margin: 0 0 16px 0; display: block; color: #64748B; font-size: 13px;">Each box is one column on the formula sheet for this tutorial. Box title and items both support HTML — one item per line.</p>
    <div id="boxRows">
      <?php foreach ($sheet['boxes'] as $b): ?>
      <div class="card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
        <div class="field"><label>Box title</label><input type="text" name="box_title[]" value="<?= h($b['title'] ?? '') ?>"></div>
        <div class="field"><label>Items (one per line, HTML allowed)</label><textarea name="box_items[]" rows="5"><?= h(implode("\n", $b['items'] ?? [])) ?></textarea></div>
        <button type="button" class="rm-btn" onclick="this.closest('.card').remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:12px;">Remove box</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addBox()" style="background:none; border:1.5px dashed #CBD5E1; color:#102A56; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px;">+ Add box</button>
  </div>

  <div class="save-bar" style="margin-top: 20px;">
    <button type="submit" class="btn btn-primary" style="background:#102A56; color:#fff; border:none; padding:11px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Save changes for this Tutorial</button>
  </div>
</form>

<script>
function addBox(){
  var div = document.createElement('div');
  div.className = 'card';
  div.style.cssText = 'background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;';
  div.innerHTML = '<div class="field"><label>Box title</label><input type="text" name="box_title[]"></div>' +
    '<div class="field"><label>Items (one per line, HTML allowed)</label><textarea name="box_items[]" rows="5"></textarea></div>' +
    '<button type="button" class="rm-btn" onclick="this.closest(\'.card\').remove()" style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:12px;">Remove box</button>';
  document.getElementById('boxRows').appendChild(div);
}
</script>
<?php admin_page_end(); ?>
