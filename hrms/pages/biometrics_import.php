<?php
// ============================================
// FILE: pages/biometrics_import.php
// Import attendance from biometric device CSV
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR);

$success_count = 0; $skip_count = 0; $error_rows = array();
$preview = array(); $processed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action']);

    if ($action === 'preview' || $action === 'import') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            prg_redirect_error('biometrics_import.php','No file uploaded.');
        }

        $shift_start = clean_string($_POST['shift_start'] ?? '08:00');
        $grace_mins  = (int)($_POST['grace_minutes'] ?? 15);
        $col_id      = (int)($_POST['col_employee_id'] ?? 0);
        $col_date    = (int)($_POST['col_date']        ?? 1);
        $col_in      = (int)($_POST['col_time_in']     ?? 2);
        $col_out     = (int)($_POST['col_time_out']    ?? 3);

        $handle = fopen($_FILES['csv_file']['tmp_name'],'r');
        $header = fgetcsv($handle); // skip header row

        $rows = array();
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 4) continue;
            $emp_code = trim($data[$col_id]  ?? '');
            $att_date = trim($data[$col_date] ?? '');
            $time_in  = trim($data[$col_in]   ?? '');
            $time_out = trim($data[$col_out]  ?? '');

            if (!$emp_code || !$att_date) continue;

            // Try to parse date
            $parsed_date = date('Y-m-d', strtotime($att_date));
            if ($parsed_date === '1970-01-01') continue;

            // Parse times
            $parsed_in  = $time_in  ? date('H:i:s', strtotime($time_in))  : null;
            $parsed_out = $time_out ? date('H:i:s', strtotime($time_out)) : null;

            // Compute tardiness
            $tardy_mins = 0;
            if ($parsed_in) {
                $shift_ts = strtotime($parsed_date.' '.$shift_start.':00');
                $grace_ts = $shift_ts + ($grace_mins * 60);
                $in_ts    = strtotime($parsed_date.' '.$parsed_in);
                if ($in_ts > $grace_ts) {
                    $tardy_mins = (int)round(($in_ts - $shift_ts) / 60);
                }
            }

            $status = $parsed_in ? ($tardy_mins > 0 ? 'Late' : 'Present') : 'Absent';

            $rows[] = array(
                'emp_code'    => $emp_code,
                'att_date'    => $parsed_date,
                'time_in'     => $parsed_in,
                'time_out'    => $parsed_out,
                'tardy_mins'  => $tardy_mins,
                'status'      => $status,
            );
        }
        fclose($handle);

        if ($action === 'preview') {
            $preview   = array_slice($rows,0,20);
            $processed = true;
        }

        if ($action === 'import') {
            foreach ($rows as $row) {
                $emp = db_fetch($pdo,
                    "SELECT id FROM employees WHERE employee_id=?",
                    array($row['emp_code'])
                );
                if (!$emp) { $error_rows[] = $row['emp_code'].' — not found'; continue; }

                $exists = db_value($pdo,
                    "SELECT COUNT(*) FROM attendance WHERE employee_id=? AND attendance_date=?",
                    array($emp['id'],$row['att_date'])
                );
                if ($exists) { $skip_count++; continue; }

                db_run($pdo,
                    "INSERT INTO attendance
                     (employee_id,attendance_date,time_in,time_out,status,tardiness_minutes)
                     VALUES (?,?,?,?,?,?)",
                    array($emp['id'],$row['att_date'],$row['time_in'],$row['time_out'],
                          $row['status'],$row['tardy_mins'])
                );
                $success_count++;
            }
            prg_redirect('biometrics_import.php',
                "$success_count records imported, $skip_count skipped (duplicates), ".count($error_rows)." errors.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Biometrics Import | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Biometrics / DTR Import</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="row">
      <div class="col-md-5">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Upload CSV</h3></div>
          <div class="card-body">
            <div class="alert alert-info" style="font-size:12px;">
              <strong>Expected CSV format:</strong><br>
              Your CSV must have columns in this order (0-indexed):<br>
              Employee ID, Date, Time In, Time Out<br>
              Header row is automatically skipped.<br>
              Dates: YYYY-MM-DD or M/D/YYYY<br>
              Times: HH:MM or HH:MM:SS (24h or 12h)
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
              <?php csrf_field(); ?>
              <div class="form-group">
                <label>CSV file <span class="text-danger">*</span></label>
                <input type="file" name="csv_file" class="form-control-file" accept=".csv,.txt" required>
              </div>
              <h6>Column positions (0 = first column)</h6>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Employee ID column</label>
                  <input type="number" name="col_employee_id" class="form-control form-control-sm" value="0" min="0"></div></div>
                <div class="col-6"><div class="form-group"><label>Date column</label>
                  <input type="number" name="col_date" class="form-control form-control-sm" value="1" min="0"></div></div>
                <div class="col-6"><div class="form-group"><label>Time In column</label>
                  <input type="number" name="col_time_in" class="form-control form-control-sm" value="2" min="0"></div></div>
                <div class="col-6"><div class="form-group"><label>Time Out column</label>
                  <input type="number" name="col_time_out" class="form-control form-control-sm" value="3" min="0"></div></div>
              </div>
              <h6>Tardiness settings</h6>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Shift start time</label>
                  <input type="time" name="shift_start" class="form-control" value="08:00"></div></div>
                <div class="col-6"><div class="form-group"><label>Grace period (minutes)</label>
                  <input type="number" name="grace_minutes" class="form-control" value="15" min="0"></div></div>
              </div>
              <div class="row">
                <div class="col-6">
                  <button type="submit" name="action" value="preview" class="btn btn-info btn-block">
                    <i class="fas fa-eye mr-1"></i> Preview (first 20)
                  </button>
                </div>
                <div class="col-6">
                  <button type="submit" name="action" value="import" class="btn btn-primary btn-block"
                          onclick="return confirm('Import all records? Duplicates will be skipped.')">
                    <i class="fas fa-upload mr-1"></i> Import all
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <?php if ($processed && !empty($preview)): ?>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Preview (first 20 rows)</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr><th>Emp ID</th><th>Date</th><th>Time in</th><th>Time out</th><th>Status</th><th>Tardiness</th></tr>
              </thead>
              <tbody>
                <?php foreach ($preview as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['emp_code']) ?></td>
                  <td><?= htmlspecialchars($row['att_date']) ?></td>
                  <td><?= $row['time_in'] ? date('h:i A',strtotime($row['time_in'])) : '—' ?></td>
                  <td><?= $row['time_out'] ? date('h:i A',strtotime($row['time_out'])) : '—' ?></td>
                  <td><span class="badge badge-<?= $row['status']==='Present'?'success':($row['status']==='Late'?'warning':'danger') ?>"><?= $row['status'] ?></span></td>
                  <td><?= $row['tardy_mins'] > 0 ? $row['tardy_mins'].' mins' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php else: ?>
        <div class="card">
          <div class="card-body text-center text-muted py-5">
            <i class="fas fa-fingerprint fa-3x mb-3 d-block" style="opacity:.2;"></i>
            Upload a CSV file from your biometric device or time-keeping system.<br>
            <small>Common formats: ZKTeco, BioTime, Fingertec, HikVision</small>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>