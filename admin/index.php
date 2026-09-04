<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([(int)$_POST['delete_id']]);
    header('Location: index.php');
    exit;
}

$users = db()->query('SELECT id, name, email, phone, batch, created_at FROM users ORDER BY created_at DESC')->fetchAll();

admin_page_start('Students', 'students');
?>
<div class="toolbar">
  <span class="meta"><?= count($users) ?> registered student<?= count($users) === 1 ? '' : 's' ?></span>
  <input type="text" id="search" placeholder="Search name, email, batch&hellip;" style="max-width:260px">
</div>

<?php if (!$users): ?>
  <div class="empty">No students have signed up yet.</div>
<?php else: ?>
<table id="userTable">
  <thead>
    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Batch</th><th>Registered</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= h($u['name']) ?></td>
      <td><?= h($u['email']) ?></td>
      <td><?= h($u['phone']) ?></td>
      <td><?= h($u['batch']) ?></td>
      <td><?= h($u['created_at']) ?></td>
      <td class="row-actions">
        <form method="post" onsubmit="return confirm('Delete this student?');">
          <input type="hidden" name="delete_id" value="<?= (int)$u['id'] ?>">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<script>
document.getElementById('search') && document.getElementById('search').addEventListener('input', function (e) {
  var q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('#userTable tbody tr').forEach(function (row) {
    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
  });
});
</script>
<?php admin_page_end(); ?>
