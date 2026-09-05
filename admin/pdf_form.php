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
$p = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM pdfs WHERE id = ? AND tutorial_id = ?');
    $stmt->execute([$id, $tutorialId]);
    $p = $stmt->fetch();
    if (!$p) { header('Location: pdfs.php?tutorial_id=' . $tutorialId); exit; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Handle File Upload if provided
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['pdf_file']['tmp_name'];
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['pdf_file']['name']);
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($fileTmp, $destPath)) {
            $url = '/uploads/' . $fileName;
        }
    }

    if ($title === '' || $url === '') {
        $error = 'Title and PDF File URL (or uploaded PDF file) are required.';
    } else {
        if ($p) {
            $stmt = $pdo->prepare('UPDATE pdfs SET title=?, url=?, description=? WHERE id=?');
            $stmt->execute([$title, $url, $description ?: null, $p['id']]);
        } else {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM pdfs WHERE tutorial_id = ' . (int)$tutorialId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO pdfs (tutorial_id, title, url, description, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$tutorialId, $title, $url, $description ?: null, $maxOrder + 10]);
        }
        header('Location: pdfs.php?tutorial_id=' . $tutorialId);
        exit;
    }
}

admin_page_start($p ? 'Edit PDF' : 'New PDF', 'subjects', 'pdfs.php?tutorial_id=' . $tutorialId, 'Back to PDF documents');
?>
<form method="post" enctype="multipart/form-data" style="max-width:640px;">
  <div class="card" style="background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:20px;">
    <?php if ($error): ?><div class="err" style="background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600;"><?= h($error) ?></div><?php endif; ?>
    
    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Document Title *</label>
      <input type="text" name="title" value="<?= h($p['title'] ?? '') ?>" placeholder="e.g. Lecture Notes — Chapter 2 Dynamics PDF" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Upload PDF File</label>
      <input type="file" name="pdf_file" accept=".pdf,application/pdf" style="font-size:13px; color:#475569;">
      <span class="hint" style="display:block; font-size:12px; color:#64748B; margin-top:4px;">Upload a .pdf file directly from your computer, or enter a URL link below.</span>
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">PDF Link / URL</label>
      <input type="text" name="url" value="<?= h($p['url'] ?? '') ?>" placeholder="https://example.com/document.pdf or /uploads/..." style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box;">
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label style="display:block; font-weight:700; font-size:13px; color:#1E293B; margin-bottom:6px;">Description / Notes <span style="font-weight:400; color:#64748B;">(optional)</span></label>
      <textarea name="description" rows="3" placeholder="Brief summary of what this PDF covers..." style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:14px; box-sizing:border-box; line-height:1.5;"><?= h($p['description'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="save-bar" style="display:flex; gap:12px;">
    <a href="pdfs.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-outline" style="padding:10px 20px; border-radius:8px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Cancel</a>
    <button type="submit" class="btn btn-primary" style="background:#059669; color:#fff; padding:10px 24px; border-radius:8px; border:none; font-weight:700; cursor:pointer;"><?= $p ? 'Save changes' : 'Add PDF Document' ?></button>
  </div>
</form>
<?php admin_page_end(); ?>
