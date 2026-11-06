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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Handle File Upload if provided
    if (isset($_FILES['slide_file']) && $_FILES['slide_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['slide_file']['tmp_name'];
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['slide_file']['name']);
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $destPath = $uploadDir . $fileName;
        if (!in_array($ext, ['pptx', 'ppt', 'ppsx', 'pps', 'odp', 'pdf'], true)) {
            $error = 'Only .pptx, .ppt, .ppsx, .odp or .pdf files can be uploaded as slides.';
        } elseif (move_uploaded_file($fileTmp, $destPath)) {
            $url = '/uploads/' . $fileName;
        }
    }

    if ($error !== '') {
        // keep the upload error
    } elseif ($title === '' || $url === '') {
        $error = 'Title and a slides link (or an uploaded .pptx / .pdf file) are required.';
    } else {
        if ($p) {
            $stmt = $pdo->prepare('UPDATE slides SET title=?, url=?, description=? WHERE id=?');
            $stmt->execute([$title, $url, $description ?: null, $p['id']]);
        } else {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM slides WHERE tutorial_id = ' . (int)$tutorialId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO slides (tutorial_id, title, url, description, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$tutorialId, $title, $url, $description ?: null, $maxOrder + 10]);
        }
        header('Location: slides.php?tutorial_id=' . $tutorialId);
        exit;
    }
}

admin_page_start($p ? 'Edit Slides' : 'New Slides', 'subjects', 'slides.php?tutorial_id=' . $tutorialId, 'Back to slides');
?>
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
<?php admin_page_end(); ?>
