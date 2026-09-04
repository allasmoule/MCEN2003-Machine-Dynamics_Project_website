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
$v = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM videos WHERE id = ? AND tutorial_id = ?');
    $stmt->execute([$id, $tutorialId]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: videos.php?tutorial_id=' . $tutorialId); exit; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $url === '') {
        $error = 'Title and URL are required.';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid URL.';
    } else {
        if ($v) {
            $stmt = $pdo->prepare('UPDATE videos SET title=?, url=?, description=? WHERE id=?');
            $stmt->execute([$title, $url, $description ?: null, $v['id']]);
        } else {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM videos WHERE tutorial_id = ' . (int)$tutorialId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO videos (tutorial_id, title, url, description, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$tutorialId, $title, $url, $description ?: null, $maxOrder + 10]);
        }
        header('Location: videos.php?tutorial_id=' . $tutorialId);
        exit;
    }
}

admin_page_start($v ? 'Edit Video' : 'New Video', 'subjects', 'videos.php?tutorial_id=' . $tutorialId, 'Back to ' . $tutorial['title'] . ' videos');
?>
<form method="post">
  <div class="card">
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <div class="field"><label>Title</label><input type="text" name="title" value="<?= h($v['title'] ?? '') ?>" placeholder="e.g. Worked example: T1.1 walkthrough" required></div>
    <div class="field"><label>URL <span class="hint">(YouTube, Vimeo, or a direct video file link)</span></label><input type="url" name="url" value="<?= h($v['url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=..." required></div>
    <div class="field"><label>Description <span class="hint">(optional)</span></label><textarea name="description" rows="3"><?= h($v['description'] ?? '') ?></textarea></div>
  </div>
  <div class="save-bar">
    <a href="videos.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-outline">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= $v ? 'Save changes' : 'Add video' ?></button>
  </div>
</form>
<?php admin_page_end(); ?>
