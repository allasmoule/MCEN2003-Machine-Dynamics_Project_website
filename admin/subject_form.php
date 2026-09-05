<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$s = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ?');
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) { header('Location: subjects.php'); exit; }
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'subject';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        if ($s) {
            $stmt = $pdo->prepare('UPDATE subjects SET name=?, institution=?, description=? WHERE id=?');
            $stmt->execute([$name, $institution ?: null, $description ?: null, $s['id']]);
        } else {
            $base = slugify($name);
            $slug = $base;
            $n = 1;
            $check = $pdo->prepare('SELECT id FROM subjects WHERE slug = ?');
            while (true) {
                $check->execute([$slug]);
                if (!$check->fetch()) break;
                $n++;
                $slug = $base . '-' . $n;
            }
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM subjects')->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO subjects (slug, name, institution, description, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$slug, $name, $institution ?: null, $description ?: null, $maxOrder + 10]);
            $newId = (int)$pdo->lastInsertId();
            header('Location: tutorial_form.php?subject_id=' . $newId . '&created_subject=1');
            exit;
        }
        header('Location: subjects.php');
        exit;
    }
}

admin_page_start($s ? 'Edit Subject' : 'New Subject', 'subjects', 'subjects.php', 'Back to subjects');
?>
<form method="post">
  <div class="card">
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <div class="field"><label>Name</label><input type="text" name="name" value="<?= h($s['name'] ?? '') ?>" placeholder="e.g. MCEN2003 Machine Dynamics" required></div>
    <div class="field"><label>Institution <span class="hint">(shown under the name)</span></label><input type="text" name="institution" value="<?= h($s['institution'] ?? '') ?>" placeholder="e.g. Curtin University"></div>
    <div class="field"><label>Description <span class="hint">(shown on the homepage subject card)</span></label><textarea name="description" rows="3"><?= h($s['description'] ?? '') ?></textarea></div>
  </div>
  <div class="save-bar">
    <a href="subjects.php" class="btn btn-outline">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= $s ? 'Save changes' : 'Create subject' ?></button>
  </div>
</form>
<?php admin_page_end(); ?>
