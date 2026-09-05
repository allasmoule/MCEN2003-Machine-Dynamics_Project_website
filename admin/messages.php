<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = db()->prepare('DELETE FROM contact_messages WHERE id = ?');
    $stmt->execute([(int)$_POST['delete_id']]);
    header('Location: messages.php');
    exit;
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_read_id'])) {
    $stmt = db()->prepare('UPDATE contact_messages SET is_read = CASE WHEN is_read = 1 THEN 0 ELSE 1 END WHERE id = ?');
    $stmt->execute([(int)$_POST['toggle_read_id']]);
    header('Location: messages.php');
    exit;
}

$messages = db()->query('SELECT id, type, name, email, phone, message, is_read, created_at FROM contact_messages ORDER BY created_at DESC')->fetchAll();

admin_page_start('Contact Messages', 'messages');
?>
<div class="toolbar">
  <span class="meta"><?= count($messages) ?> contact message<?= count($messages) === 1 ? '' : 's' ?></span>
  <input type="text" id="searchMsg" placeholder="Search name, email, phone, message&hellip;" style="max-width:280px">
</div>

<?php if (!$messages): ?>
  <div class="empty">No contact messages received yet.</div>
<?php else: ?>
<table id="msgTable">
  <thead>
    <tr>
      <th>Status</th>
      <th>Type</th>
      <th>Sender Details</th>
      <th>Message</th>
      <th>Received</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($messages as $m): ?>
    <tr style="<?= $m['is_read'] ? 'opacity: 0.75;' : 'font-weight: 600; background: rgba(16, 42, 86, 0.02);' ?>">
      <td>
        <span class="badge" style="background: <?= $m['is_read'] ? '#F1F5F9; color:#64748B' : '#E0EEF3; color:#0F5F7D' ?>;">
          <?= $m['is_read'] ? 'Read' : 'New' ?>
        </span>
      </td>
      <td>
        <span class="badge" style="background: #EEF2FF; color: #102A56; font-size: 11px;">
          <?= h($m['type'] ?: 'General Inquiry') ?>
        </span>
      </td>
      <td>
        <strong><?= h($m['name']) ?></strong><br>
        <small style="color: #475569;"><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></small><br>
        <small style="color: #64748B;"><a href="tel:<?= h($m['phone']) ?>"><?= h($m['phone']) ?></a></small>
      </td>
      <td style="max-width: 320px; white-space: pre-wrap; font-weight: normal; font-size: 13.5px; line-height: 1.5; color: #334155;">
        <?= h($m['message']) ?>
      </td>
      <td style="font-size: 12px; color: #64748B; white-space: nowrap;">
        <?= h($m['created_at']) ?>
      </td>
      <td class="row-actions">
        <form method="post" style="display:inline-block; margin-right: 4px;">
          <input type="hidden" name="toggle_read_id" value="<?= (int)$m['id'] ?>">
          <button type="submit" style="background: transparent; border: 1px solid #CBD5E1; border-radius: 4px; padding: 4px 8px; font-size: 12px; cursor: pointer;">
            <?= $m['is_read'] ? 'Unread' : 'Mark Read' ?>
          </button>
        </form>
        <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this message?');">
          <input type="hidden" name="delete_id" value="<?= (int)$m['id'] ?>">
          <button type="submit" class="danger" style="color: #DC2626; background: transparent; border: 1px solid #FCA5A5; border-radius: 4px; padding: 4px 8px; font-size: 12px; cursor: pointer;">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<script>
document.getElementById('searchMsg') && document.getElementById('searchMsg').addEventListener('input', function (e) {
  var q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('#msgTable tbody tr').forEach(function (row) {
    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
  });
});
</script>
<?php admin_page_end(); ?>
