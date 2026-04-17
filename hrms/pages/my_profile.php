<?php
// ============================================
// FILE: pages/my_profile.php — FIXED
// Superadmin/Admin see system overview instead
// of "not linked" error
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

$user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

// ── Superadmin/Admin: show system overview instead ────────────────
if (is_admin() && !$emp_id) {
    $total_emp   = db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_status='Active'");
    $total_users = db_value($pdo,"SELECT COUNT(*) FROM users WHERE is_active=1");
    $last_backup_file = '';
    if (is_dir('../backups/')) {
        $files = glob('../backups/*.sql');
        if ($files) {
            rsort($files);
            $last_backup_file = basename($files[0]);
        }
    }
    ?>
    <!DOCTYPE html><html lang="en">
    <head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Admin Profile | HRMS</title>
      <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
      <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
      <link rel="stylesheet" href="../assets/dist/css/custom.css">
    </head>
    <body class="hold-transition sidebar-mini"><div class="wrapper">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-wrapper">
      <div class="content-header"><div class="container-fluid"><h1 class="m-0">My Account</h1></div></div>
      <div class="content"><div class="container-fluid">

        <div class="card card-primary card-outline">
          <div class="card-body">
            <div class="d-flex align-items-center" style="gap:20px;">
              <div style="width:80px;height:80px;border-radius:50%;background:#dc3545;
                          display:flex;align-items:center;justify-content:center;
                          font-size:28px;font-weight:700;color:#fff;">
                <?= strtoupper(substr($user['username'],0,2)) ?>
              </div>
              <div>
                <h3 class="mb-0"><?= htmlspecialchars($user['username']) ?></h3>
                <p class="text-muted mb-1">
                  <?= ucwords(str_replace('_',' ',$user['role'])) ?> &mdash; System account
                </p>
                <span class="badge badge-danger"><?= ucwords(str_replace('_',' ',$user['role'])) ?></span>
                <span class="badge badge-success ml-1">Active</span>
              </div>
              <div class="ml-auto">
                <a href="change_password.php" class="btn btn-outline-primary btn-sm">
                  <i class="fas fa-lock mr-1"></i> Change password
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="alert alert-info">
          <i class="fas fa-info-circle mr-2"></i>
          This is a <strong>system administrator account</strong> and is not linked to an
          employee record. To access personal HR data (payslips, attendance, leave),
          link this account to an employee record via
          <a href="users.php">User Management</a>, or create a separate employee account.
        </div>

        <div class="row">
          <div class="col-md-3 col-6">
            <div class="small-box bg-primary" style="border-radius:8px;">
              <div class="inner"><h3><?= $total_emp ?></h3><p>Active employees</p></div>
              <div class="icon"><i class="fas fa-users"></i></div>
              <a href="employees.php" class="small-box-footer">Manage <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="small-box bg-info" style="border-radius:8px;">
              <div class="inner"><h3><?= $total_users ?></h3><p>Active user accounts</p></div>
              <div class="icon"><i class="fas fa-user-cog"></i></div>
              <a href="users.php" class="small-box-footer">Manage <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="small-box bg-success" style="border-radius:8px;">
              <div class="inner">
                <h3 style="font-size:14px;"><?= $last_backup_file ?: 'None' ?></h3>
                <p>Last backup</p>
              </div>
              <div class="icon"><i class="fas fa-database"></i></div>
              <a href="backup.php" class="small-box-footer">Backup now <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="small-box bg-warning" style="border-radius:8px;">
              <div class="inner"><h3>v1.0</h3><p>HRMS version</p></div>
              <div class="icon"><i class="fas fa-cog"></i></div>
              <a href="settings.php" class="small-box-footer">Settings <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
        </div>

      </div></div>
    </div>
    <?php include '../includes/footer.php'; ?>
    </body></html>
    <?php
    exit;
}

// ── Regular employee flow ─────────────────────────────────────────
if (!$emp_id) { ?>
  <!DOCTYPE html><html><body class="hold-transition sidebar-mini"><div class="wrapper">
  <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
  <div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle mr-2"></i>
      Your account is not linked to an employee record.
      Please contact your HR administrator to link your account.
    </div>
  </div></div></div>
  <?php include '../includes/footer.php'; ?>
  </div></body></html>
<?php exit; }

$emp = db_fetch($pdo,
    "SELECT e.*, d.name AS dept, p.title AS position
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     WHERE e.id=?", array($emp_id));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $editable = array('contact_number','email_address','address','emergency_contact','civil_status','nationality');
    $submitted = array();
    foreach ($editable as $field) {
        if (isset($_POST[$field])) {
            $new_val = clean_string($_POST[$field]);
            if ($new_val !== $emp[$field]) {
                $submitted[$field] = array('old'=>$emp[$field],'new'=>$new_val);
            }
        }
    }
    if (!empty($submitted)) {
        db_run($pdo,
            "INSERT INTO employee_self_updates (employee_id,section,submitted_data) VALUES (?,?,?)",
            array($emp_id,'personal_info',json_encode($submitted))
        );
        prg_redirect('my_profile.php','Profile update request submitted. HR will review and approve.');
    } else {
        prg_redirect('my_profile.php','No changes detected.');
    }
}

$pending = db_fetchall($pdo,
    "SELECT * FROM employee_self_updates WHERE employee_id=? ORDER BY created_at DESC LIMIT 10",
    array($emp_id));
$parts    = explode(' ', trim($emp['full_name']));
$initials = ''; foreach (array_slice($parts,0,2) as $p) if($p) $initials .= strtoupper($p[0]);
$photo_url = (!empty($emp['photo'])) ? '../uploads/photos/'.htmlspecialchars($emp['photo']) : null;
?>
<!DOCTYPE html><html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .avatar { width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #dee2e6; }
    .avatar-init { width:80px;height:80px;border-radius:50%;background:#4a90d9;color:#fff;
                   display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:600; }
    .info-label { font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px; }
    .info-val   { font-size:14px;font-weight:500;color:#343a40; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">My Profile</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex align-items-center" style="gap:20px;">
          <?php if ($photo_url): ?>
            <img src="<?= $photo_url ?>" class="avatar" alt="photo">
          <?php else: ?>
            <div class="avatar-init"><?= $initials ?></div>
          <?php endif; ?>
          <div>
            <h3 class="mb-0"><?= htmlspecialchars($emp['full_name']) ?></h3>
            <p class="text-muted mb-1"><?= htmlspecialchars($emp['position']?:'—') ?> &mdash; <?= htmlspecialchars($emp['dept']?:'—') ?></p>
            <span class="badge badge-success"><?= htmlspecialchars($emp['employment_status']) ?></span>
            <span class="badge badge-secondary ml-1"><?= htmlspecialchars($emp['employment_type']) ?></span>
          </div>
          <div class="ml-auto">
            <a href="upload_photo.php?id=<?= $emp_id ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-camera mr-1"></i>Photo</a>
            <a href="change_password.php" class="btn btn-sm btn-outline-secondary ml-1"><i class="fas fa-lock mr-1"></i>Password</a>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-4">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Employment details</h3></div>
          <div class="card-body">
            <?php
            $ro_fields = array(
              'Employee ID'  => $emp['employee_id'],
              'Department'   => $emp['dept'],
              'Position'     => $emp['position'],
              'Date hired'   => $emp['date_hired'] ? date('M d, Y',strtotime($emp['date_hired'])) : '—',
              'Basic salary' => $emp['basic_salary'] ? '&#8369;'.number_format($emp['basic_salary'],2) : '—',
            );
            foreach ($ro_fields as $lbl => $val): ?>
            <div class="mb-3">
              <div class="info-label"><?= $lbl ?></div>
              <div class="info-val"><?= htmlspecialchars($val ?: '—') ?></div>
            </div>
            <?php endforeach; ?>
            <small class="text-muted"><i class="fas fa-lock mr-1"></i>Changed by HR only.</small>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Personal information <small class="text-muted ml-1">(changes need HR approval)</small></h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Contact number</label>
                  <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($emp['contact_number']?:'') ?>"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Email address</label>
                  <input type="email" name="email_address" class="form-control" value="<?= htmlspecialchars($emp['email_address']?:'') ?>"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Civil status</label>
                  <select name="civil_status" class="form-control">
                    <?php foreach (array('Single','Married','Widowed','Separated') as $cs): ?>
                      <option <?= $emp['civil_status']===$cs?'selected':'' ?>><?= $cs ?></option>
                    <?php endforeach; ?>
                  </select></div></div>
                <div class="col-md-6"><div class="form-group"><label>Nationality</label>
                  <input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($emp['nationality']?:'') ?>"></div></div>
                <div class="col-12"><div class="form-group"><label>Home address</label>
                  <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($emp['address']?:'') ?>"></div></div>
                <div class="col-12"><div class="form-group"><label>Emergency contact</label>
                  <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact']?:'') ?>"></div></div>
              </div>
              <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i>Submit for HR approval</button>
            </form>
          </div>
        </div>
        <?php if (!empty($pending)): ?>
        <div class="card">
          <div class="card-header"><h3 class="card-title">My pending requests</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Section</th><th>Status</th><th>Submitted</th></tr></thead>
              <tbody>
                <?php foreach ($pending as $pr):
                  $sc = array('Pending'=>'warning','Approved'=>'success','Rejected'=>'danger');
                  $sb = isset($sc[$pr['status']]) ? $sc[$pr['status']] : 'secondary';
                ?>
                <tr>
                  <td><?= htmlspecialchars($pr['section']) ?></td>
                  <td><span class="badge badge-<?= $sb ?>"><?= $pr['status'] ?></span></td>
                  <td><?= date('M d, Y',strtotime($pr['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>