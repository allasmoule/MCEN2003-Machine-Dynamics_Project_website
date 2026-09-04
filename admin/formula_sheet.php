<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();
$stmt = $pdo->prepare('SELECT value_json FROM content_blocks WHERE block_key = ?');
$stmt->execute(['formula_sheet']);
$row = $stmt->fetch();
$sheet = $row ? json_decode($row['value_json'], true) : ['heading' => '', 'subheading' => '', 'boxes' => []];
if (!$sheet['boxes']) $sheet['boxes'] = [['title' => '', 'items' => ['']]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $heading = trim($_POST['heading'] ?? '');
    $subheading = trim($_POST['subheading'] ?? '');
    $boxTitles = $_POST['box_title'] ?? [];
    $boxItems = $_POST['box_items'] ?? []; // one textarea per box, newline-separated

    $boxes = [];
    foreach ($boxTitles as $i => $title) {
        $title = trim($title);
        $items = array_values(array_filter(array_map('trim', explode("\n", $boxItems[$i] ?? '')), fn($v) => $v !== ''));
        if ($title === '' && !$items) continue;
        $boxes[] = ['title' => $title, 'items' => $items];
    }

    $value = ['heading' => $heading, 'subheading' => $subheading, 'boxes' => $boxes];
    $stmt = $pdo->prepare('INSERT INTO content_blocks (block_key, value_json) VALUES (\'formula_sheet\', :v) ON DUPLICATE KEY UPDATE value_json = :v');
    $stmt->execute([':v' => json_encode($value)]);
    header('Location: formula_sheet.php?saved=1');
    exit;
}

admin_page_start('Formula Sheet', 'formula');
?>
<form method="post" id="fsForm">
  <?php if (isset($_GET['saved'])): ?><div class="ok-msg">Saved — changes are live on the workbook now.</div><?php endif; ?>

  <div class="card">
    <div class="field"><label>Heading</label><input type="text" name="heading" value="<?= h($sheet['heading'] ?? '') ?>"></div>
    <div class="field"><label>Subheading</label><input type="text" name="subheading" value="<?= h($sheet['subheading'] ?? '') ?>"></div>
  </div>

  <div class="card">
    <h2>Boxes</h2>
    <p class="hint" style="margin:-8px 0 12px 0;display:block">Each box is one column on the formula sheet. Box title and items both support HTML (e.g. &amp;sup2; for &sup2;, &lt;sub&gt;&lt;/sub&gt; for subscripts) — one item per line.</p>
    <div id="boxRows">
      <?php foreach ($sheet['boxes'] as $b): ?>
      <div class="card" style="background:#F8FAFC">
        <div class="field"><label>Box title</label><input type="text" name="box_title[]" value="<?= h($b['title'] ?? '') ?>"></div>
        <div class="field"><label>Items (one per line, HTML allowed)</label><textarea name="box_items[]" rows="5"><?= h(implode("\n", $b['items'] ?? [])) ?></textarea></div>
        <button type="button" class="rm-btn" onclick="this.closest('.card').remove()">Remove box</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="add-btn" onclick="addBox()">+ Add box</button>
  </div>

  <div class="save-bar">
    <button type="submit" class="btn btn-primary">Save changes</button>
  </div>
</form>
<script>
function addBox(){
  var div = document.createElement('div');
  div.className = 'card';
  div.style.background = '#F8FAFC';
  div.innerHTML = '<div class="field"><label>Box title</label><input type="text" name="box_title[]"></div>' +
    '<div class="field"><label>Items (one per line, HTML allowed)</label><textarea name="box_items[]" rows="5"></textarea></div>' +
    '<button type="button" class="rm-btn" onclick="this.closest(\'.card\').remove()">Remove box</button>';
  document.getElementById('boxRows').appendChild(div);
}
</script>
<?php admin_page_end(); ?>
