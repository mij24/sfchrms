<?php
// ============================================
// FILE: pages/dashboard.php — FIXED
// White page was caused by session_start() conflict
// and missing $pdo variable. This version is clean.
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// ── Summary stats ──────────────────────────────────────────────────
$today = date('Y-m-d');

$total_emp      = (int)db_value($pdo, "SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'");
$total_dept     = (int)db_value($pdo, "SELECT COUNT(*) FROM departments");
$present_today  = (int)db_value($pdo, "SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'Present'", array($today));
$absent_today   = (int)db_value($pdo, "SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'Absent'", array($today));
$new_this_month = (int)db_value($pdo,
    "SELECT COUNT(*) FROM employees WHERE MONTH(date_hired)=MONTH(CURDATE()) AND YEAR(date_hired)=YEAR(CURDATE())"
);
$pending_leaves = (int)db_value($pdo, "SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'");
$open_jobs      = (int)db_value($pdo, "SELECT COUNT(*) FROM job_openings WHERE status = 'Open'");

// ── Upcoming birthdays this week ──
$birthdays = db_fetchall($pdo,
    "SELECT full_name, employee_id,
            DATE_FORMAT(date_of_birth,'%M %d') AS bday,
            TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) + 1 AS turning
     FROM employees
     WHERE DATE_FORMAT(date_of_birth,'%m-%d')
           BETWEEN DATE_FORMAT(CURDATE(),'%m-%d')
               AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY),'%m-%d')
       AND employment_status = 'Active'
     ORDER BY DATE_FORMAT(date_of_birth,'%m-%d')
     LIMIT 5"
);

// ── Employment type breakdown ──
$emp_types = db_fetchall($pdo,
    "SELECT employment_type, COUNT(*) AS cnt
     FROM employees WHERE employment_status='Active'
     GROUP BY employment_type ORDER BY cnt DESC"
);

// ── Recent employees ──
$recent_emp = db_fetchall($pdo,
    "SELECT e.id, e.employee_id, e.full_name, e.employment_status, e.date_hired,
            d.name AS dept, p.title AS position
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN positions   p ON e.position_id   = p.id
     ORDER BY e.created_at DESC
     LIMIT 5"
);

// ── Attendance today breakdown ──
$att_raw = db_fetchall($pdo,
    "SELECT status, COUNT(*) AS cnt FROM attendance WHERE attendance_date=? GROUP BY status",
    array($today)
);
$att_map = array();
foreach ($att_raw as $r) $att_map[$r['status']] = $r['cnt'];

// ── Latest announcements ──
$announcements = db_fetchall($pdo,
    "SELECT title, priority, created_at FROM announcements
     WHERE is_active=1 ORDER BY created_at DESC LIMIT 3"
);

$status_colors = array(
    'Active'=>'success','Inactive'=>'secondary',
    'Resigned'=>'warning','Terminated'=>'danger','On leave'=>'info'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .stat-card { border-radius:10px; border:none; }
    .section-title { font-size:12px; font-weight:600; color:#6c757d;
                     text-transform:uppercase; letter-spacing:.05em; margin:1.5rem 0 .75rem; }
    .bday-badge { background:#fff3cd; color:#856404; font-size:11px;
                  padding:2px 8px; border-radius:99px; }
    .priority-normal    { color:#6c757d; }
    .priority-important { color:#856404; }
    .priority-urgent    { color:#842029; font-weight:600; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Dashboard</h1></div>
        <div class="col-sm-6">
          <span class="float-right text-muted" style="font-size:13px;margin-top:6px;">
            <i class="fas fa-calendar-alt mr-1"></i><?= date('l, F d, Y') ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">

      <!-- ── SUMMARY CARDS ── -->
      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-primary stat-card">
            <div class="inner"><h3><?= $total_emp ?></h3><p>Active employees</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <a href="employees.php" class="small-box-footer">View all <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success stat-card">
            <div class="inner"><h3><?= $present_today ?></h3><p>Present today</p></div>
            <div class="icon"><i class="fas fa-user-check"></i></div>
            <a href="attendance.php" class="small-box-footer">Attendance <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning stat-card">
            <div class="inner"><h3><?= $pending_leaves ?></h3><p>Pending leave requests</p></div>
            <div class="icon"><i class="fas fa-calendar-minus"></i></div>
            <a href="leave_requests.php" class="small-box-footer">Review <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info stat-card">
            <div class="inner"><h3><?= $open_jobs ?></h3><p>Open job postings</p></div>
            <div class="icon"><i class="fas fa-user-plus"></i></div>
            <a href="job_openings.php" class="small-box-footer">Recruitment <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
      </div>

      <div class="row">

        <!-- Left column -->
        <div class="col-md-4">

          <!-- Attendance today -->
          <p class="section-title">Today's attendance</p>
          <div class="card">
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead class="thead-light">
                  <tr><th>Status</th><th class="text-right">Count</th></tr>
                </thead>
                <tbody>
                  <?php
                  $statuses_list = array(
                    array('Present','success'),array('Absent','danger'),
                    array('Late','warning'),array('Half-day','info'),
                    array('On leave','primary'),array('Holiday','secondary')
                  );
                  foreach ($statuses_list as $s):
                    $cnt = isset($att_map[$s[0]]) ? $att_map[$s[0]] : 0;
                  ?>
                  <tr>
                    <td><span class="badge badge-<?= $s[1] ?>"><?= $s[0] ?></span></td>
                    <td class="text-right font-weight-bold"><?= $cnt ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Upcoming birthdays -->
          <p class="section-title">Upcoming birthdays</p>
          <div class="card">
            <div class="card-body p-0">
              <?php if (empty($birthdays)): ?>
                <p class="text-muted text-center py-3" style="font-size:13px;">No birthdays this week.</p>
              <?php else: ?>
              <table class="table table-sm mb-0">
                <tbody>
                  <?php foreach ($birthdays as $b): ?>
                  <tr>
                    <td>
                      <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars($b['full_name']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($b['employee_id']) ?></small>
                    </td>
                    <td class="text-right">
                      <span class="bday-badge">
                        <i class="fas fa-birthday-cake mr-1" style="font-size:10px;"></i>
                        <?= htmlspecialchars($b['bday']) ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>

          <!-- Latest announcements -->
          <p class="section-title">Announcements</p>
          <div class="card">
            <div class="card-body p-0">
              <?php if (empty($announcements)): ?>
                <p class="text-muted text-center py-3" style="font-size:13px;">No announcements.</p>
              <?php else: ?>
              <table class="table table-sm mb-0">
                <tbody>
                  <?php foreach ($announcements as $ann):
                    $pc = array('Normal'=>'secondary','Important'=>'warning','Urgent'=>'danger');
                    $pb = isset($pc[$ann['priority']]) ? $pc[$ann['priority']] : 'secondary';
                  ?>
                  <tr>
                    <td>
                      <span class="badge badge-<?= $pb ?> mr-1"><?= $ann['priority'] ?></span>
                      <span style="font-size:13px;"><?= htmlspecialchars($ann['title']) ?></span><br>
                      <small class="text-muted"><?= date('M d, Y', strtotime($ann['created_at'])) ?></small>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div class="card-footer text-right p-2">
                <a href="announcements.php" style="font-size:12px;">View all</a>
              </div>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <!-- Right column -->
        <div class="col-md-8">

          <!-- Employment type -->
          <p class="section-title">Workforce breakdown</p>
          <div class="row mb-3">
            <div class="col-md-3 col-6">
              <div class="card mb-0 text-center">
                <div class="card-body py-2">
                  <div style="font-size:22px;font-weight:700;color:#007bff;"><?= $total_dept ?></div>
                  <div style="font-size:11px;color:#6c757d;">Departments</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="card mb-0 text-center">
                <div class="card-body py-2">
                  <div style="font-size:22px;font-weight:700;color:#28a745;"><?= $new_this_month ?></div>
                  <div style="font-size:11px;color:#6c757d;">New hires this month</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="card mb-0 text-center">
                <div class="card-body py-2">
                  <div style="font-size:22px;font-weight:700;color:#dc3545;"><?= $absent_today ?></div>
                  <div style="font-size:11px;color:#6c757d;">Absent today</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="card mb-0 text-center">
                <div class="card-body py-2">
                  <div style="font-size:22px;font-weight:700;color:#6c757d;"><?= $open_jobs ?></div>
                  <div style="font-size:11px;color:#6c757d;">Open positions</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent employees -->
          <p class="section-title">Recently added employees</p>
          <div class="card">
            <div class="card-body p-0">
              <table class="table table-sm table-hover mb-0" id="recentTable">
                <thead class="thead-light">
                  <tr>
                    <th>ID</th><th>Name</th><th>Department</th>
                    <th>Position</th><th>Date hired</th><th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recent_emp as $r):
                    $badge = isset($status_colors[$r['employment_status']]) ? $status_colors[$r['employment_status']] : 'secondary';
                  ?>
                  <tr>
                    <td><code><?= htmlspecialchars($r['employee_id']) ?></code></td>
                    <td>
                      <a href="view_employee.php?id=<?= $r['id'] ?>">
                        <?= htmlspecialchars($r['full_name']) ?>
                      </a>
                    </td>
                    <td><?= htmlspecialchars($r['dept'] ? $r['dept'] : '—') ?></td>
                    <td><?= htmlspecialchars($r['position'] ? $r['position'] : '—') ?></td>
                    <td><?= $r['date_hired'] ? date('M d, Y', strtotime($r['date_hired'])) : '—' ?></td>
                    <td><span class="badge badge-<?= $badge ?>"><?= $r['employment_status'] ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="card-footer text-right">
              <a href="employees.php" class="btn btn-sm btn-primary">
                View all employees <i class="fas fa-arrow-right ml-1"></i>
              </a>
            </div>
          </div>

          <!-- Quick actions -->
          <p class="section-title">Quick actions</p>
          <div class="row">
            <?php
            $links = array(
              array('add_employee.php',   'fas fa-user-plus',       'Add employee',     'primary'),
              array('attendance.php',     'fas fa-clock',           'Log attendance',   'success'),
              array('leave_requests.php', 'fas fa-calendar-minus',  'Leave requests',   'warning'),
              array('payroll.php',        'fas fa-money-bill-wave', 'Process payroll',  'info'),
              array('reports.php',        'fas fa-chart-bar',       'Reports',          'secondary'),
              array('announcements.php',  'fas fa-bullhorn',        'Announcements',    'danger'),
            );
            foreach ($links as $l):
            ?>
            <div class="col-md-2 col-4 mb-3">
              <a href="<?= $l[0] ?>" class="btn btn-outline-<?= $l[3] ?> btn-block py-3"
                 style="border-radius:10px;">
                <i class="<?= $l[1] ?> fa-lg mb-1 d-block"></i>
                <span style="font-size:11px;"><?= $l[2] ?></span>
              </a>
            </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function() {
    $('#recentTable').DataTable({ paging:false, searching:false, info:false, order:[] });
});
</script>
</body>
</html>