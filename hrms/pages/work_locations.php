<?php
// ============================================
// FILE: pages/work_locations.php
// Manage work locations — under Organization Structure
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'delete' && $get_id) {
    $in_use = db_value($pdo, "SELECT COUNT(*) FROM employees WHERE work_location_id=?", array($get_id));
    if ($in_use) {
        prg_redirect_error('work_locations.php', "Cannot delete — $in_use employee(s) are assigned to this location.");
    }
    db_run($pdo, "UPDATE work_locations SET is_active=0 WHERE id=?", array($get_id));
    prg_redirect('work_locations.php', 'Work location deactivated.');
}
if ($get_action === 'restore' && $get_id) {
    db_run($pdo, "UPDATE work_locations SET is_active=1 WHERE id=?", array($get_id));
    prg_redirect('work_locations.php', 'Work location restored.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name']);
    $code    = strtoupper(trim($_POST['code']));
    $desc    = clean_string($_POST['description'], 500);
    $post_id = isset($_POST['wl_id']) ? (int)$_POST['wl_id'] : 0;

    if (empty($name)) {
        prg_redirect_error('work_locations.php', 'Location name is required.');
    }

    $dup_sql    = $post_id
        ? "SELECT COUNT(*) FROM work_locations WHERE name=? AND id!=?"
        : "SELECT COUNT(*) FROM work_locations WHERE name=?";
    $dup_params = $post_id ? array($name, $post_id) : array($name);
    if (db_value($pdo, $dup_sql, $dup_params)) {
        prg_redirect_error('work_locations.php', "Location '$name' already exists.");
    }

    if ($post_id) {
        db_run($pdo, "UPDATE work_locations SET name=?,code=?,description=? WHERE id=?",
            array($name, $code, $desc, $post_id));
        prg_redirect('work_locations.php', 'Work location updated.');
    } else {
        db_run($pdo, "INSERT INTO work_locations (name,code,description) VALUES (?,?,?)",
            array($name, $code, $desc));
        prg_redirect('work_locations.php', 'Work location added.');
    }
}

$edit      = ($get_action === 'edit' && $get_id)
    ? db_fetch($pdo, "SELECT * FROM work_locations WHERE id=?", array($get_id))
    : null;
$locations = db_fetchall($pdo,
    "SELECT wl.*,
            (SELECT COUNT(*) FROM employees WHERE work_location_id=wl.id) AS headcount
     FROM work_locations wl
     ORDER BY wl.sort_order, wl.name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Work Locations | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Work Locations</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item">Organization</li>
          <li class="breadcrumb-item active">Work Locations</li>
        </ol>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <!-- Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title"><?= $edit ? 'Edit location' : 'Add work location' ?></h3>
            <?php if ($edit): ?>
              <div class="card-tools">
                <a href="work_locations.php" class="btn btn-xs btn-secondary">Cancel</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <?php if ($edit): ?><input type="hidden" name="wl_id" value="<?= $edit['id'] ?>"><?php endif; ?>
              <div class="form-group">
                <label>Location name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                       value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>"
                       placeholder="e.g. Main Campus">
              </div>
              <div class="form-group">
                <label>Code</label>
                <input type="text" name="code" class="form-control" maxlength="20"
                       value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>"
                       placeholder="e.g. MAIN">
              </div>
              <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i>
                <?= $edit ? 'Update location' : 'Add location' ?>
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All work locations (<?= count($locations) ?>)</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-bordered table-hover dt-export" id="wlTable">
              <thead class="thead-light">
                <tr><th>Name</th><th>Code</th><th>Description</th><th class="text-center">Employees</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($locations as $loc): ?>
                <tr class="<?= !$loc['is_active'] ? 'text-muted' : '' ?>">
                  <td><strong><?= htmlspecialchars($loc['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($loc['code'] ?: '—') ?></code></td>
                  <td><?= htmlspecialchars($loc['description'] ?: '—') ?></td>
                  <td class="text-center"><span class="badge badge-info"><?= $loc['headcount'] ?></span></td>
                  <td><span class="badge badge-<?= $loc['is_active'] ? 'success' : 'secondary' ?>"><?= $loc['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="work_locations.php?action=edit&id=<?= $loc['id'] ?>"
                       class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($loc['is_active']): ?>
                    <a href="work_locations.php?action=delete&id=<?= $loc['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Deactivate this location?')">
                      <i class="fas fa-ban"></i>
                    </a>
                    <?php else: ?>
                    <a href="work_locations.php?action=restore&id=<?= $loc['id'] ?>"
                       class="btn btn-success btn-xs">
                      <i class="fas fa-undo"></i>
                    </a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>