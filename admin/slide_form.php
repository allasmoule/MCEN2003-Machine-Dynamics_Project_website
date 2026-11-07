<?php
require_once __DIR__ . '/../includes/config.php';
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
$p = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM slides WHERE id = ? AND tutorial_id = ?');
    $stmt->execute([$id, $tutorialId]);
    $p = $stmt->fetch();
    if (!$p) { header('Location: slides.php?tutorial_id=' . $tutorialId); exit; }
}

$error = '';
$allowedExt = ['pptx', 'ppt', 'ppsx', 'pps', 'odp', 'pdf'];

/** Save one uploaded slides file; returns its /uploads/ URL, '' if none, or null on a bad type. */
function save_slide_upload(string $tmp, string $name, array $allowedExt): ?string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $fileName = time() . '_' . bin2hex(random_bytes(3)) . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
    return move_uploaded_file($tmp, $uploadDir . $fileName) ? '/uploads/' . $fileName : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($p) {
        // Edit one existing deck
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (isset($_FILES['slide_file']) && !is_array($_FILES['slide_file']['name']) && $_FILES['slide_file']['error'] === UPLOAD_ERR_OK) {
            $up = save_slide_upload($_FILES['slide_file']['tmp_name'], $_FILES['slide_file']['name'], $allowedExt);
            if ($up === null) $error = 'Only .pptx, .ppt, .ppsx, .odp or .pdf files can be uploaded as slides.';
            elseif ($up !== '') $url = $up;
        }
        if ($error === '' && ($title === '' || $url === '')) {
            $error = 'Title and a slides link (or an uploaded .pptx / .pdf file) are required.';
        }
        if ($error === '') {
            $pdo->prepare('UPDATE slides SET title=?, url=?, description=? WHERE id=?')
                ->execute([$title, $url, $description ?: null, $p['id']]);
            header('Location: slides.php?tutorial_id=' . $tutorialId);
            exit;
        }
    } else {
        // Add one or more decks at once
        $titles = (array)($_POST['title'] ?? []);
        $urls = (array)($_POST['url'] ?? []);
        $descs = (array)($_POST['description'] ?? []);
        $files = $_FILES['slide_file'] ?? null;
        $rows = [];
        $n = max(count($titles), count($urls), $files && is_array($files['name']) ? count($files['name']) : 0);
        for ($i = 0; $i < $n; $i++) {
            $title = trim((string)($titles[$i] ?? ''));
            $url = trim((string)($urls[$i] ?? ''));
            $desc = trim((string)($descs[$i] ?? ''));
            if ($files && is_array($files['name']) && ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $up = save_slide_upload($files['tmp_name'][$i], $files['name'][$i], $allowedExt);
                if ($up === null) { $error = 'Slide ' . ($i + 1) . ': only .pptx, .ppt, .ppsx, .odp or .pdf files can be uploaded.'; break; }
                if ($up !== '') {
                    $url = $up;
                    if ($title === '') $title = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                }
            }
            if ($title === '' && $url === '') continue; // empty row
            if ($title === '' || $url === '') { $error = 'Slide ' . ($i + 1) . ': needs both a title and a link (or an uploaded file).'; break; }
            $rows[] = [$title, $url, $desc];
        }
        if ($error === '' && !$rows) $error = 'Add at least one slide: a title and a link (or an uploaded .pptx / .pdf file).';
        if ($error === '') {
            $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM slides WHERE tutorial_id = ?');
            $st->execute([$tutorialId]);
            $order = (int)$st->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO slides (tutorial_id, title, url, description, sort_order) VALUES (?,?,?,?,?)');
            foreach ($rows as [$title, $url, $desc]) {
                $order += 10;
                $ins->execute([$tutorialId, $title, $url, $desc ?: null, $order]);
            }
            header('Location: slides.php?tutorial_id=' . $tutorialId);
            exit;
        }
    }
}

admin_page_start($p ? 'Edit Slides' : 'New Slides', 'subjects', 'slides.php?tutorial_id=' . $tutorialId, 'Back to slides');
?>
<?php if ($p): ?>
<form method="post" enctype="multipart/form-data" style="max-width:640px;">
  <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:20px;">
    <?php if ($error): ?><div class="err" style="background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600;"><?= h($error) ?></div><?php endif; ?>
    
    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Document Title *</label>
      <input type="text" name="title" value="<?= h($p['title'] ?? '') ?>" placeholder="e.g. Lecture 3 — Relative Motion slides" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Upload Slides File</label>
      <input type="file" name="slide_file" accept=".pptx,.ppt,.ppsx,.odp,.pdf" style="font-size:13px; color:#475569;">
      <span class="hint" style="display:block; font-size:12px; color:#64748B; margin-top:4px;">Upload a .pptx / .ppt / .pdf file from your computer, or paste a Google Slides / OneDrive / PDF link below. (Google Slides: Share → "Anyone with the link".)</span>
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Slides Link / URL</label>
      <input type="text" name="url" value="<?= h($p['url'] ?? '') ?>" placeholder="https://docs.google.com/presentation/d/... or /uploads/..." style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Description / Notes <span style="font-weight:400; color:#64748B;">(optional)</span></label>
      <textarea name="description" rows="3" placeholder="Brief summary of what these slides cover..." style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box; line-height:1.5;"><?= h($p['description'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="save-bar" style="display:flex; gap:12px;">
    <a href="slides.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-outline" style="padding:10px 20px; border-radius:8px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Cancel</a>
    <button type="submit" class="btn btn-primary" style="background:#0891B2; color:#fff; padding:10px 24px; border-radius:8px; border:none; font-weight:700; cursor:pointer;"><?= $p ? 'Save changes' : 'Add Slides' ?></button>
  </div>
</form>
<?php else: ?>
<form method="post" enctype="multipart/form-data" style="max-width:680px;">
  <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:20px;">
    <?php if ($error): ?><div class="err" style="background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600;"><?= h($error) ?></div><?php endif; ?>
    <p style="margin:0 0 14px; font-size:13px; color:#64748B;">Add one or more slide decks. Each can be a Google Slides / OneDrive / PDF link or an uploaded .pptx / .pdf file. (Google Slides: Share &rarr; "Anyone with the link".)</p>
    <div id="slideRows"><div class="slide-row" style="border:1px solid #E2E8F0; border-radius:10px; padding:16px; margin-bottom:14px; background:#F8FAFC;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;"><strong class="slide-row-n" style="font-size:13px; color:#0891B2;">Slide 1</strong><button type="button" class="slide-row-del" style="background:none; border:none; color:#DC2626; font-weight:600; cursor:pointer; font-size:12.5px;">Remove</button></div><div class="field" style="margin-bottom:12px;"><label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Title *</label><input type="text" name="title[0]" placeholder="e.g. Lecture 3 — Relative Motion slides" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;"></div><div class="field" style="margin-bottom:12px;"><label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Upload file <span style="font-weight:400; color:#64748B;">(.pptx / .ppt / .pdf — or paste a link below)</span></label><input type="file" name="slide_file[0]" accept=".pptx,.ppt,.ppsx,.odp,.pdf" style="font-size:13px; color:#475569;"></div><div class="field" style="margin-bottom:12px;"><label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Slides Link / URL</label><input type="text" name="url[0]" placeholder="https://docs.google.com/presentation/d/... or a .pptx / .pdf link" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;"></div><div class="field"><label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Description <span style="font-weight:400; color:#64748B;">(optional)</span></label><input type="text" name="description[0]" placeholder="What these slides cover" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;"></div></div></div>
    <button type="button" id="addSlideRow" style="background:#ECFEFF; color:#0891B2; border:1px dashed #0891B2; padding:10px 16px; border-radius:8px; font-weight:700; cursor:pointer; width:100%; font-size:13.5px;">+ Add another slide</button>
  </div>
  <div class="save-bar" style="display:flex; gap:12px;">
    <a href="slides.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-outline" style="padding:10px 20px; border-radius:8px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Cancel</a>
    <button type="submit" class="btn btn-primary" style="background:#0891B2; color:#fff; padding:10px 24px; border-radius:8px; border:none; font-weight:700; cursor:pointer;">Save slides</button>
  </div>
</form>
<script>
(function(){
  var box = document.getElementById('slideRows'), add = document.getElementById('addSlideRow');
  if (!box || !add) return;
  var tpl = box.querySelector('.slide-row').cloneNode(true);
  function renumber(){
    var rows = box.querySelectorAll('.slide-row');
    rows.forEach(function(r, i){
      r.querySelector('.slide-row-n').textContent = 'Slide ' + (i + 1);
      r.querySelectorAll('[name]').forEach(function(el){ el.name = el.name.replace(/\[\d*\]$/, '[' + i + ']'); });
      r.querySelector('.slide-row-del').style.visibility = rows.length > 1 ? 'visible' : 'hidden';
    });
  }
  box.addEventListener('click', function(e){
    if (!e.target.classList.contains('slide-row-del')) return;
    if (box.querySelectorAll('.slide-row').length > 1) e.target.closest('.slide-row').remove();
    renumber();
  });
  add.addEventListener('click', function(){
    var r = tpl.cloneNode(true);
    r.querySelectorAll('input').forEach(function(i){ i.value = ''; });
    box.appendChild(r); renumber();
    r.querySelector('input[type=text]').focus();
  });
  renumber();
})();
</script>
<?php endif; ?>
<?php admin_page_end(); ?>
