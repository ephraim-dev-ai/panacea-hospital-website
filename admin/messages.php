<?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'read' && $id) {
    $pdo->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$id]);
    $msg = $pdo->prepare('SELECT * FROM contact_messages WHERE id=?');
    $msg->execute([$id]); $msg = $msg->fetch();
    $pageTitle = 'Message from ' . ($msg['full_name'] ?? '');
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <a href="/panacea/admin/messages.php" class="btn btn-sm btn-outline-secondary mb-4">
      <i class="bi bi-arrow-left me-1"></i>Back to Messages
    </a>
    <div class="form-card" style="max-width:680px">
      <div class="form-card-head">
        <h4><?= clean($msg['subject'] ?: 'No Subject') ?></h4>
      </div>
      <div class="form-card-body">
        <table class="table table-sm" style="font-size:.88rem;width:auto">
          <tr>
            <th style="color:var(--muted);padding:8px 16px 8px 0;border:none;white-space:nowrap">From</th>
            <td style="border:none;padding:8px 0;font-weight:600"><?= clean($msg['full_name']) ?></td>
          </tr>
          <tr>
            <th style="color:var(--muted);padding:8px 16px 8px 0">Phone</th>
            <td style="padding:8px 0"><?= clean($msg['phone']) ?></td>
          </tr>
          <tr>
            <th style="color:var(--muted);padding:8px 16px 8px 0">Email</th>
            <td style="padding:8px 0"><?= clean($msg['email'] ?: '—') ?></td>
          </tr>
          <tr>
            <th style="color:var(--muted);padding:8px 16px 8px 0">Date</th>
            <td style="padding:8px 0"><?= date('d M Y, H:i', strtotime($msg['created_at'])) ?></td>
          </tr>
        </table>
        <div style="background:var(--bg);border-radius:10px;padding:20px;margin-top:16px;
                    font-size:.92rem;line-height:1.8;color:var(--text)">
          <?= nl2br(clean($msg['message'])) ?>
        </div>
        <?php if ($msg['email']): ?>
          <div class="mt-4 d-flex gap-2">
            <a href="mailto:<?= clean($msg['email']) ?>" class="btn btn-primary">
              <i class="bi bi-reply me-1"></i>Reply via Email
            </a>
            <a href="/panacea/admin/messages.php" class="btn btn-outline-secondary">
              Back to Inbox
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

if ($action === 'delete' && $id) {
    $pdo->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
    flash('main','Message deleted.','success');
    header('Location: /panacea/admin/messages.php'); exit;
}

$msgs = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
$unread = array_filter($msgs, fn($m) => !$m['is_read']);

$pageTitle = 'Messages';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>

<?php if (count($unread) > 0): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4"
     style="border-radius:10px;font-size:.875rem">
  <i class="bi bi-envelope-fill"></i>
  You have <strong><?= count($unread) ?> unread</strong> message<?= count($unread) > 1 ? 's' : '' ?>.
</div>
<?php endif; ?>

<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-envelope-fill text-primary me-2"></i>
    <h5>Contact Messages</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($msgs) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th></th><th>From</th><th>Phone</th><th>Subject / Preview</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($msgs as $m): ?>
        <tr style="<?= !$m['is_read'] ? 'font-weight:600' : '' ?>">
          <td style="width:20px">
            <?php if (!$m['is_read']): ?>
              <span style="width:9px;height:9px;background:var(--blue-bright);
                           border-radius:50%;display:inline-block"></span>
            <?php else: ?>
              <span style="width:9px;height:9px;background:var(--border);
                           border-radius:50%;display:inline-block"></span>
            <?php endif; ?>
          </td>
          <td><?= clean($m['full_name']) ?></td>
          <td style="font-size:.82rem"><?= clean($m['phone']) ?></td>
          <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;
                     white-space:nowrap;font-size:.85rem">
            <?php
              $preview = $m['subject'] ?: $m['message'];
              echo clean(strlen($preview) > 60 ? substr($preview,0,60).'…' : $preview);
            ?>
          </td>
          <td style="font-size:.78rem;color:var(--muted);white-space:nowrap">
            <?= date('d M Y, H:i', strtotime($m['created_at'])) ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=read&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Read
              </a>
              <a href="?action=delete&id=<?= $m['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 data-confirm="Delete this message?">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$msgs): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-5">
              <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px"></i>
              No messages yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
