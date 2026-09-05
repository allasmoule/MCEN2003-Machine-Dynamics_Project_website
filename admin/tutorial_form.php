<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();
$subjectId = (int)($_GET['subject_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ?');
$stmt->execute([$subjectId]);
$subject = $stmt->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$t = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM tutorials WHERE id = ? AND subject_id = ?');
    $stmt->execute([$id, $subjectId]);
    $t = $stmt->fetch();
    if (!$t) { header('Location: tutorials.php?subject_id=' . $subjectId); exit; }
}

function slugify_tut(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'tutorial';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        if ($t) {
            $stmt = $pdo->prepare('UPDATE tutorials SET title=?, description=? WHERE id=?');
            $stmt->execute([$title, $description ?: null, $t['id']]);
        } else {
            $base = slugify_tut($title);
            $slug = $base;
            $n = 1;
            $check = $pdo->prepare('SELECT id FROM tutorials WHERE subject_id = ? AND slug = ?');
            while (true) {
                $check->execute([$subjectId, $slug]);
                if (!$check->fetch()) break;
                $n++;
                $slug = $base . '-' . $n;
            }
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM tutorials WHERE subject_id = ' . (int)$subjectId)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO tutorials (subject_id, slug, title, description, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$subjectId, $slug, $title, $description ?: null, $maxOrder + 10]);
            $newTutId = (int)$pdo->lastInsertId();
            header('Location: question_form.php?tutorial_id=' . $newTutId . '&created_tutorial=1');
            exit;
        }
        header('Location: tutorials.php?subject_id=' . $subjectId);
        exit;
    }
}

admin_page_start($t ? 'Edit Tutorial' : 'New Tutorial', 'subjects', 'tutorials.php?subject_id=' . $subjectId, 'Back to ' . $subject['name']);
?>
<?php if (isset($_GET['created_subject'])): ?>
  <div class="ok-msg" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-weight:600;">
    🎉 Subject created successfully! Now create your first tutorial for <strong><?= h($subject['name']) ?></strong> below.
  </div>
<?php endif; ?>
<form method="post">
  <div class="card">
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <div class="field"><label>Title</label><input type="text" name="title" value="<?= h($t['title'] ?? '') ?>" placeholder="e.g. Tutorial 2 — Dynamics" required></div>
    <div class="field"><label>Description <span class="hint">(optional)</span></label><textarea name="description" rows="3"><?= h($t['description'] ?? '') ?></textarea></div>
  </div>
  <div class="save-bar">
    <a href="tutorials.php?subject_id=<?= $subjectId ?>" class="btn btn-outline">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= $t ? 'Save changes' : 'Create tutorial' ?></button>
  </div>
</form>
<?php admin_page_end(); ?>
