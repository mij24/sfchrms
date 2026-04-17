<?php
// ============================================
// FILE: pages/view_employee.php — FIXED
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: employees.php"); exit; }

// Main employee record
$stmt = $conn->prepare("
    SELECT e.*,
           d.name AS department_name,
           p.title AS position_title,
           s.full_name AS supervisor_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions   p ON e.position_id   = p.id
    LEFT JOIN employees   s ON e.supervisor_id  = s.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
if (!$emp) { header("Location: employees.php"); exit; }

// Leave credits
$lc_result = $conn->query("SELECT * FROM leave_credits WHERE employee_id = $id ORDER BY year DESC LIMIT 1");
$lc = $lc_result ? $lc_result->fetch_assoc() : null;

// Attendance summary current month
$att_result = $conn->query("
    SELECT
        SUM(status = 'Present')  AS present,
        SUM(status = 'Absent')   AS absent,
        SUM(status = 'Late')     AS late,
        SUM(tardiness_minutes)   AS total_tardiness,
        SUM(overtime_hours)      AS total_overtime
    FROM attendance
    WHERE employee_id = $id
      AND MONTH(attendance_date) = MONTH(CURDATE())
      AND YEAR(attendance_date)  = YEAR(CURDATE())
");
$att = $att_result ? $att_result->fetch_assoc() : array();

// Recent attendance log
$attLog = $conn->query("
    SELECT * FROM attendance
    WHERE employee_id = $id
    ORDER BY attendance_date DESC
    LIMIT 15
");

// Documents
$docs = $conn->query("SELECT * FROM employee_documents WHERE employee_id = $id ORDER BY uploaded_at DESC");

// Status badge map
$statusMap = array(
    'Active'     => 'success',
    'Inactive'   => 'secondary',
    'Resigned'   => 'warning',
    'Terminated' => 'danger',
    'On leave'   => 'info'
);
$statusBadge = isset($statusMap[$emp['employment_status']]) ? $statusMap[$emp['employment_status']] : 'secondary';

// Initials for avatar
$name_parts = explode(' ', trim($emp['full_name']));
$initials = '';
foreach (array_slice($name_parts, 0, 2) as $part) {
    if ($part !== '') $initials .= strtoupper($part[0]);
}

// Safe value helper — avoids null htmlspecialchars warnings
function safe($val) {
    return htmlspecialchars($val ? $val : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= safe($emp['full_name']) ?> | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .avatar-circle {
      width: 72px; height: 72px; border-radius: 50%;
      background: #4a90d9; color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; font-weight: 600; flex-shrink: 0;
    }
    .info-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
    .info-value { font-size: 14px; color: #343a40; font-weight: 500; word-break: break-word; }
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
        <div class="col-sm-6"><h1 class="m-0">Employee Profile</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
            <li class="breadcrumb-item active"><?= safe($emp['full_name']) ?></li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">

      <!-- Profile header -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center flex-wrap" style="gap:20px;">
            <div class="avatar-circle"><?= $initials ?></div>
            <div style="flex:1;">
              <h3 class="mb-0"><?= safe($emp['full_name']) ?></h3>
              <p class="text-muted mb-1">
                <?= safe($emp['position_title']) ?>
                <?php if ($emp['position_title'] && $emp['department_name']): ?> &mdash; <?php endif; ?>
                <?= safe($emp['department_name']) ?>
              </p>
              <span class="badge badge-<?= $statusBadge ?> mr-1"><?= safe($emp['employment_status']) ?></span>
              <span class="badge badge-secondary mr-1"><?= safe($emp['employment_type']) ?></span>
              <span class="badge badge-light"><?= safe($emp['employee_id']) ?></span>
            </div>
            <div>
              <a href="edit_employee.php?id=<?= $emp['id'] ?>" class="btn btn-warning btn-sm mr-1">
                <i class="fas fa-edit mr-1"></i> Edit
              </a>
              <a href="employees.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="card">
        <div class="card-header p-0">
          <ul class="nav nav-tabs" id="empTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="tab" href="#tab-personal">
                <i class="fas fa-user mr-1"></i> Personal
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#tab-employment">
                <i class="fas fa-briefcase mr-1"></i> Employment
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#tab-govids">
                <i class="fas fa-id-card mr-1"></i> Gov't IDs
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#tab-leave">
                <i class="fas fa-calendar-minus mr-1"></i> Leave Credits
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#tab-attendance">
                <i class="fas fa-clock mr-1"></i> Attendance
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#tab-documents">
                <i class="fas fa-folder mr-1"></i> 201 File
              </a>
            </li>
          </ul>
        </div>

        <div class="card-body tab-content">

          <!-- PERSONAL TAB -->
          <div class="tab-pane fade show active" id="tab-personal">
            <div class="row">
              <?php
              $personal_fields = array(
                'Full name'         => $emp['full_name'],
                'Date of birth'     => ($emp['date_of_birth'] && $emp['date_of_birth'] !== '0000-00-00')
                                        ? date('F d, Y', strtotime($emp['date_of_birth'])) : '',
                'Gender'            => $emp['gender'],
                'Civil status'      => $emp['civil_status'],
                'Nationality'       => $emp['nationality'],
                'Contact number'    => $emp['contact_number'],
                'Email address'     => $emp['email_address'],
                'Address'           => $emp['address'],
                'Emergency contact' => $emp['emergency_contact'],
              );
              foreach ($personal_fields as $label => $val):
                $display = ($val !== null && $val !== '') ? $val : '—';
              ?>
              <div class="col-md-4 mb-3">
                <div class="info-label"><?= $label ?></div>
                <div class="info-value"><?= htmlspecialchars($display) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- EMPLOYMENT TAB -->
          <div class="tab-pane fade" id="tab-employment">
            <div class="row">
              <?php
              $basic_salary_display = ($emp['basic_salary'] > 0)
                ? '&#8369; ' . number_format((float)$emp['basic_salary'], 2) : '—';

              $date_hired_display = ($emp['date_hired'] && $emp['date_hired'] !== '0000-00-00')
                ? date('F d, Y', strtotime($emp['date_hired'])) : '—';

              $employment_fields = array(
                'Employee ID'          => $emp['employee_id'],
                'Department'           => $emp['department_name'],
                'Position'             => $emp['position_title'],
                'Employment type'      => $emp['employment_type'],
                'Employment status'    => $emp['employment_status'],
                'Date hired'           => $date_hired_display,
                'Immediate supervisor' => $emp['supervisor_name'],
                'Work location'        => $emp['work_location'],
                'Salary grade'         => $emp['salary_grade'],
              );
              foreach ($employment_fields as $label => $val):
                $display = ($val !== null && $val !== '') ? $val : '—';
              ?>
              <div class="col-md-4 mb-3">
                <div class="info-label"><?= $label ?></div>
                <div class="info-value"><?= htmlspecialchars($display) ?></div>
              </div>
              <?php endforeach; ?>
              <div class="col-md-4 mb-3">
                <div class="info-label">Basic salary</div>
                <div class="info-value"><?= $basic_salary_display ?></div>
              </div>
            </div>
          </div>

          <!-- GOV'T IDs TAB -->
          <div class="tab-pane fade" id="tab-govids">
            <div class="row">
              <?php
              $gov_fields = array(
                'SSS number'        => $emp['sss_number'],
                'PhilHealth number' => $emp['philhealth_number'],
                'Pag-IBIG number'   => $emp['pagibig_number'],
                'TIN number'        => $emp['tin_number'],
              );
              foreach ($gov_fields as $label => $val):
                $display = ($val !== null && $val !== '') ? $val : '—';
              ?>
              <div class="col-md-3 mb-3">
                <div class="info-label"><?= $label ?></div>
                <div class="info-value"><?= htmlspecialchars($display) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- LEAVE CREDITS TAB -->
          <div class="tab-pane fade" id="tab-leave">
            <?php if ($lc): ?>
            <p class="text-muted mb-3" style="font-size:13px;">
              Leave credits for <strong><?= (int)$lc['year'] ?></strong>
            </p>
            <div class="row">
              <?php
              $leave_display = array(
                array('Vacation leave',    'vacation_leave',    'vacation_used',    'success'),
                array('Sick leave',        'sick_leave',        'sick_used',        'info'),
                array('Emergency leave',   'emergency_leave',   'emergency_used',   'warning'),
                array('Maternity leave',   'maternity_leave',   'maternity_used',   'danger'),
                array('Paternity leave',   'paternity_leave',   'paternity_used',   'secondary'),
                array('Solo parent leave', 'solo_parent_leave', 'solo_parent_used', 'dark'),
              );
              foreach ($leave_display as $ld):
                $lname     = $ld[0];
                $total_key = $ld[1];
                $used_key  = $ld[2];
                $lcolor    = $ld[3];
                $total     = isset($lc[$total_key]) ? (float)$lc[$total_key] : 0;
                $used      = isset($lc[$used_key])  ? (float)$lc[$used_key]  : 0;
                $remaining = $total - $used;
                $pct       = ($total > 0) ? round(($used / $total) * 100) : 0;
              ?>
              <div class="col-md-4 mb-3">
                <div class="card mb-0">
                  <div class="card-body py-3 px-3" style="border-left:4px solid;">
                    <div class="info-label mb-1"><?= $lname ?></div>
                    <div class="d-flex justify-content-between align-items-end">
                      <div>
                        <span style="font-size:26px;font-weight:700;color:#343a40;"><?= number_format($remaining, 1) ?></span>
                        <span class="text-muted" style="font-size:12px;"> / <?= number_format($total, 1) ?> days left</span>
                      </div>
                      <small class="text-muted"><?= number_format($used, 1) ?> used</small>
                    </div>
                    <div class="progress mt-2" style="height:5px;">
                      <div class="progress-bar bg-<?= $lcolor ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
              <p class="text-muted">No leave credit record found for this employee.</p>
              <a href="edit_employee.php?id=<?= $id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-plus mr-1"></i> Add leave credits
              </a>
            <?php endif; ?>
          </div>

          <!-- ATTENDANCE TAB -->
          <div class="tab-pane fade" id="tab-attendance">
            <p class="text-muted mb-3" style="font-size:13px;">
              Summary for <strong><?= date('F Y') ?></strong>
            </p>
            <div class="row mb-4">
              <?php
              $att_present   = isset($att['present'])         ? (int)$att['present']         : 0;
              $att_absent    = isset($att['absent'])          ? (int)$att['absent']           : 0;
              $att_late      = isset($att['late'])            ? (int)$att['late']             : 0;
              $att_tardy     = isset($att['total_tardiness']) ? (int)$att['total_tardiness']  : 0;
              $att_ot        = isset($att['total_overtime'])  ? (float)$att['total_overtime'] : 0;

              $att_stats = array(
                array('Days present',     $att_present,                   'success'),
                array('Days absent',      $att_absent,                    'danger'),
                array('Late arrivals',    $att_late,                      'warning'),
                array('Tardiness (mins)', $att_tardy,                     'info'),
                array('Overtime (hrs)',   number_format($att_ot, 2),      'primary'),
              );
              foreach ($att_stats as $as):
              ?>
              <div class="col-6 col-md-2 mb-3">
                <div class="small-box bg-<?= $as[2] ?>" style="border-radius:8px;">
                  <div class="inner" style="padding:12px 14px;">
                    <h4 style="margin:0;"><?= $as[1] ?></h4>
                    <p style="font-size:11px;margin:0;"><?= $as[0] ?></p>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <h6 class="mb-2">Recent attendance log</h6>
            <table class="table table-sm table-bordered table-striped" id="attTable">
              <thead class="thead-light">
                <tr>
                  <th>Date</th><th>Time in</th><th>Time out</th>
                  <th>Status</th><th>Tardiness</th><th>Overtime</th><th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $att_status_map = array(
                  'Present'  => 'success', 'Absent' => 'danger',
                  'Late'     => 'warning', 'Half-day' => 'info',
                  'On leave' => 'primary', 'Holiday'  => 'secondary',
                  'Rest day' => 'dark'
                );
                while ($arow = $attLog->fetch_assoc()):
                  $asb = isset($att_status_map[$arow['status']]) ? $att_status_map[$arow['status']] : 'secondary';
                  $time_in_disp  = ($arow['time_in']  && $arow['time_in']  !== '00:00:00') ? date('h:i A', strtotime($arow['time_in']))  : '—';
                  $time_out_disp = ($arow['time_out'] && $arow['time_out'] !== '00:00:00') ? date('h:i A', strtotime($arow['time_out'])) : '—';
                  $tardy_disp    = ($arow['tardiness_minutes'] > 0) ? $arow['tardiness_minutes'] . ' mins' : '—';
                  $ot_disp       = ($arow['overtime_hours'] > 0)    ? $arow['overtime_hours']    . ' hrs'  : '—';
                  $remarks_disp  = ($arow['remarks'] && $arow['remarks'] !== '') ? htmlspecialchars($arow['remarks']) : '—';
                ?>
                <tr>
                  <td><?= date('M d, Y', strtotime($arow['attendance_date'])) ?></td>
                  <td><?= $time_in_disp ?></td>
                  <td><?= $time_out_disp ?></td>
                  <td><span class="badge badge-<?= $asb ?>"><?= $arow['status'] ?></span></td>
                  <td><?= $tardy_disp ?></td>
                  <td><?= $ot_disp ?></td>
                  <td><?= $remarks_disp ?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>

          <!-- DOCUMENTS TAB -->
          <div class="tab-pane fade" id="tab-documents">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="mb-0">201 File &mdash; Uploaded Documents</h6>
              <a href="upload_document.php?id=<?= $emp['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-upload mr-1"></i> Upload document
              </a>
            </div>
            <?php if ($docs && $docs->num_rows > 0): ?>
            <table class="table table-sm table-bordered">
              <thead class="thead-light">
                <tr><th>Document type</th><th>File name</th><th>Uploaded</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php while ($doc = $docs->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($doc['document_type']) ?></td>
                  <td><i class="fas fa-file mr-1 text-muted"></i><?= htmlspecialchars($doc['file_name']) ?></td>
                  <td><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                  <td>
                    <a href="../uploads/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-info btn-xs">
                      <i class="fas fa-download"></i> View
                    </a>
                    <a href="delete_document.php?id=<?= $doc['id'] ?>&emp=<?= $id ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Remove this document?')">
                      <i class="fas fa-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
            <?php else: ?>
              <p class="text-muted">No documents uploaded yet.</p>
            <?php endif; ?>
          </div>

        </div><!-- /.tab-content -->
      </div><!-- /.card -->

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function() {
    $('#attTable').DataTable({ pageLength: 10, order: [[0, 'desc']] });
});
</script>
</body>
</html>