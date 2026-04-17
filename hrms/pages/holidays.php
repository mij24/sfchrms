<?php
// ============================================
// FILE: pages/holidays.php
// Manage company holidays calendar
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_action = isset($_GET['action']) ? $_GET['action'] : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

if ($get_action === 'delete' && $get_id) {
    db_run($pdo, "DELETE FROM holidays WHERE id=?", array($get_id));
    prg_redirect('holidays.php','Holiday deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = clean_string($_POST['name']);
    $date = clean_date($_POST['holiday_date']);
    $type = clean_string($_POST['type']);
    $year = $date ? (int)date('Y', strtotime($date)) : (int)date('Y');

    $exists = db_value($pdo,"SELECT COUNT(*) FROM holidays WHERE holiday_date=?",array($date));
    if ($exists) {
        prg_redirect_error('holidays.php','A holiday already exists on that date.');
    }
    db_run($pdo,
        "INSERT INTO holidays (name,holiday_date,type,year) VALUES (?,?,?,?)",
        array($name,$date,$type,$year)
    );
    prg_redirect('holidays.php','Holiday added.');
}

$year     = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$holidays = db_fetchall($pdo,
    "SELECT * FROM holidays WHERE year=? ORDER BY holiday_date",
    array($year)
);

$type_colors = array(
    'Regular holiday'     => 'danger',
    'Special non-working' => 'warning',
    'Local holiday'       => 'info',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Holidays | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid"><h1 class="m-0">Holiday Calendar</h1></div>
  </div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="row">
      <!-- Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Add holiday</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group">
                <label>Holiday name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. New Year's Day">
              </div>
              <div class="form-group">
                <label>Date <span class="text-danger">*</span></label>
                <input type="date" name="holiday_date" class="form-control" required>
              </div>
              <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control">
                  <option>Regular holiday</option>
                  <option>Special non-working</option>
                  <option>Local holiday</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-plus mr-1"></i> Add holiday
              </button>
            </form>
          </div>
        </div>

        <!-- Type legend -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Pay rate reference</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <tbody>
                <tr><td><span class="badge badge-danger">Regular holiday</span></td><td>200% pay (or 260% if worked on restday)</td></tr>
                <tr><td><span class="badge badge-warning">Special non-working</span></td><td>130% if worked, no pay if absent</td></tr>
                <tr><td><span class="badge badge-info">Local holiday</span></td><td>Per LGU ordinance</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Calendar view -->
      <div class="col-md-8">
        <!-- Year filter -->
        <div class="card mb-0" style="border-bottom:0;border-radius:8px 8px 0 0;">
          <div class="card-body py-2">
            <form method="GET" action="" class="form-inline" style="gap:8px;">
              <label>Year:</label>
              <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php for ($y=(int)date('Y')+1; $y>=(int)date('Y')-1; $y--): ?>
                  <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
              <span class="text-muted ml-2" style="font-size:13px;"><?= count($holidays) ?> holidays</span>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0 dt-export" id="holidayTable">
              <thead class="thead-light">
                <tr><th>#</th><th>Holiday</th><th>Date</th><th>Day</th><th>Type</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php $i=1; foreach ($holidays as $h):
                  $tc = isset($type_colors[$h['type']]) ? $type_colors[$h['type']] : 'secondary';
                  $is_past = strtotime($h['holiday_date']) < time();
                ?>
                <tr <?= $is_past ? 'style="opacity:.6;"' : '' ?>>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($h['name']) ?></td>
                  <td><?= date('F d, Y', strtotime($h['holiday_date'])) ?></td>
                  <td><?= date('l', strtotime($h['holiday_date'])) ?></td>
                  <td><span class="badge badge-<?= $tc ?>"><?= htmlspecialchars($h['type']) ?></span></td>
                  <td>
                    <a href="holidays.php?action=delete&id=<?= $h['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Delete <?= htmlspecialchars($h['name']) ?>?')">
                      <i class="fas fa-trash"></i>
                    </a>
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
