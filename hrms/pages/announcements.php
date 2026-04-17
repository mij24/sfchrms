<?php
// ============================================
// FILE: pages/announcements.php — COMPLETE
// In case it was missing
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';
$role       = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

if (in_array($role,array('superadmin','admin','hr_head','hr'))) {
    if ($get_action === 'delete' && $get_id) {
        db_run($pdo,"DELETE FROM announcements WHERE id=?",array($get_id));
        prg_redirect('announcements.php','Announcement deleted.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $title    = trim($_POST['title']);
        $body     = trim($_POST['body']);
        $priority = trim($_POST['priority']);
        $post_id  = isset($_POST['ann_id']) ? (int)$_POST['ann_id'] : 0;
        $user_id  = (int)$_SESSION['user_id'];

        if (empty($title) || empty($body)) {
            prg_redirect_error('announcements.php','Title and message are required.');
        }

        if ($post_id) {
            db_run($pdo,"UPDATE announcements SET title=?,body=?,priority=? WHERE id=?",
                array($title,$body,$priority,$post_id));
            prg_redirect('announcements.php','Announcement updated.');
        } else {
            db_run($pdo,
                "INSERT INTO announcements (title,body,priority,posted_by) VALUES (?,?,?,?)",
                array($title,$body,$priority,$user_id)
            );
            prg_redirect('announcements.php','Announcement posted.');
        }
    }
}

$edit = ($get_action==='edit' && $get_id)
    ? db_fetch($pdo,"SELECT * FROM announcements WHERE id=?",array($get_id))
    : null;

$announcements = db_fetchall($pdo,
    "SELECT a.*, u.username AS posted_by_name
     FROM announcements a LEFT JOIN users u ON a.posted_by=u.id
     WHERE a.is_active=1 ORDER BY a.created_at DESC"
);
$priority_colors = array('Normal'=>'secondary','Important'=>'warning','Urgent'=>'danger');
$can_post = in_array($role,array('superadmin','admin','hr_head','hr'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Announcements | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Announcements</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <?php if ($can_post): ?>
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title"><?= $edit ? 'Edit announcement' : 'Post announcement' ?></h3>
            <?php if ($edit): ?>
              <div class="card-tools">
                <a href="announcements.php" class="btn btn-xs btn-secondary">Cancel</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <?php if ($edit): ?><input type="hidden" name="ann_id" value="<?= $edit['id'] ?>"><?php endif; ?>
              <div class="form-group">
                <label>Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required
                       value="<?= $edit ? htmlspecialchars($edit['title']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Priority</label>
                <select name="priority" class="form-control">
                  <?php foreach (array('Normal','Important','Urgent') as $pr): ?>
                    <option <?= ($edit && $edit['priority']===$pr)?'selected':'' ?>><?= $pr ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Message <span class="text-danger">*</span></label>
                <textarea name="body" class="form-control" rows="6" required><?= $edit ? htmlspecialchars($edit['body']) : '' ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-bullhorn mr-1"></i> <?= $edit ? 'Update' : 'Post announcement' ?>
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="<?= $can_post ? 'col-md-8' : 'col-md-12' ?>">
        <?php if (empty($announcements)): ?>
          <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="fas fa-bullhorn fa-3x mb-3 d-block" style="opacity:.2;"></i>
            No announcements yet.
          </div></div>
        <?php endif; ?>
        <?php foreach ($announcements as $ann):
          $pc = isset($priority_colors[$ann['priority']]) ? $priority_colors[$ann['priority']] : 'secondary';
        ?>
        <div class="card mb-3" style="border-left:4px solid <?= $pc==='danger'?'#dc3545':($pc==='warning'?'#ffc107':'#6c757d') ?>;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div style="flex:1;">
                <span class="badge badge-<?= $pc ?> mr-1"><?= $ann['priority'] ?></span>
                <strong style="font-size:15px;"><?= htmlspecialchars($ann['title']) ?></strong>
              </div>
              <?php if ($can_post): ?>
              <div style="flex-shrink:0;margin-left:10px;">
                <a href="announcements.php?action=edit&id=<?= $ann['id'] ?>"
                   class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                <a href="announcements.php?action=delete&id=<?= $ann['id'] ?>"
                   class="btn btn-danger btn-xs"
                   onclick="return confirm('Delete this announcement?')">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
              <?php endif; ?>
            </div>
            <div class="mt-2" style="font-size:14px;line-height:1.7;white-space:pre-wrap;">
              <?= htmlspecialchars($ann['body']) ?>
            </div>
            <div class="mt-2" style="font-size:12px;color:#aaa;">
              <i class="fas fa-user mr-1"></i><?= htmlspecialchars($ann['posted_by_name'] ?: 'System') ?>
              &mdash; <?= date('F d, Y g:i A', strtotime($ann['created_at'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>