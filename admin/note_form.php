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
$n = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = ? AND tutorial_id = ?');
    $stmt->execute([$id, $tutorialId]);
    $n = $stmt->fetch();
    if (!$n) { header('Location: notes.php?tutorial_id=' . $tutorialId); exit; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $content === '') {
        $error = 'Title and Text Content are required.';
    } else {
        if ($n) {
            $stmt = $pdo->prepare('UPDATE notes SET title=?, content=?, description=? WHERE id=?');
            $stmt->execute([$title, $content, $description ?: null, $n['id']]);
        } else {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM notes WHERE tutorial_id = ' . (int)$tutorialId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO notes (tutorial_id, title, content, description, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$tutorialId, $title, $content, $description ?: null, $maxOrder + 10]);
        }
        header('Location: notes.php?tutorial_id=' . $tutorialId);
        exit;
    }
}

admin_page_start($n ? 'Edit Text Note' : 'New Text Note', 'subjects', 'notes.php?tutorial_id=' . $tutorialId, 'Back to text notes');
?>
<form method="post" style="max-width:720px;">
  <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:20px;">
    <?php if ($error): ?><div class="err" style="background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600;"><?= h($error) ?></div><?php endif; ?>
    
    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Note Title *</label>
      <input type="text" name="title" value="<?= h($n['title'] ?? '') ?>" placeholder="e.g. Fundamental Concepts & Key Equations" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Category / Tag <span style="font-weight:400; color:#64748B;">(optional)</span></label>
      <input type="text" name="description" value="<?= h($n['description'] ?? '') ?>" placeholder="e.g. Summary, Formulas, Homework Tip" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Text / Content Body *</label>
      <textarea name="content" rows="10" placeholder="Write full text notes, explanations, formulas, or study instructions here..." required style="width:100%; padding:12px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box; line-height:1.6; font-family:inherit;"><?= h($n['content'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="save-bar" style="display:flex; gap:12px;">
    <a href="notes.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-outline" style="padding:10px 20px; border-radius:8px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Cancel</a>
    <button type="submit" class="btn btn-primary" style="background:#7C3AED; color:#fff; padding:10px 24px; border-radius:8px; border:none; font-weight:700; cursor:pointer;"><?= $n ? 'Save changes' : 'Add Text Note' ?></button>
  </div>
</form>
<?php admin_page_end(); ?>
