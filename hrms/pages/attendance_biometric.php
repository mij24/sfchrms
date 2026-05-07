<?php
// ============================================
// FILE: pages/attendance_biometric.php
// QR Code generation & management for DTR
// Employees get a unique QR token
// External scanner reads it via qr_scanner.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_action = isset($_GET['action']) ? $_GET['action'] : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

// Generate / regenerate QR for employee
if ($get_action === 'generate' && $get_id) {
    $token = bin2hex(random_bytes(16)); // 32-char unique token
    $existing = db_value($pdo, "SELECT COUNT(*) FROM employee_qr_codes WHERE employee_id=?", array($get_id));
    if ($existing) {
        db_run($pdo,
            "UPDATE employee_qr_codes SET qr_token=?, is_active=1, generated_at=NOW() WHERE employee_id=?",
            array($token, $get_id)
        );
    } else {
        db_run($pdo,
            "INSERT INTO employee_qr_codes (employee_id, qr_token) VALUES (?,?)",
            array($get_id, $token)
        );
    }
    prg_redirect('attendance_biometric.php', 'QR code generated for employee.');
}

if ($get_action === 'deactivate' && $get_id) {
    db_run($pdo, "UPDATE employee_qr_codes SET is_active=0 WHERE id=?", array($get_id));
    prg_redirect('attendance_biometric.php', 'QR code deactivated.');
}

// Load employees with QR status
$employees_qr = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id AS emp_code,
            d.name AS dept,
            q.id AS qr_id, q.qr_token, q.is_active AS qr_active,
            q.generated_at, q.last_used
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN employee_qr_codes q ON e.id=q.employee_id
     WHERE e.employment_status='Active'
     ORDER BY e.full_name"
);

// Recent mobile clock-ins
$mobile_logs = db_fetchall($pdo,
    "SELECT mal.*, e.full_name, e.employee_id AS emp_code
     FROM mobile_attendance_log mal
     JOIN employees e ON mal.employee_id=e.id
     ORDER BY mal.log_datetime DESC LIMIT 50"
);

$scanner_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/qr_scanner.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Biometric & QR Management | HRMS</title>
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
    <h1 class="m-0">Biometric &amp; QR Code Management</h1>
  </div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- QR Scanner info card -->
    <div class="alert alert-info">
      <i class="fas fa-qrcode mr-2"></i>
      <strong>QR Scanner URL:</strong>
      <code><?= htmlspecialchars($scanner_url) ?></code>
      — Open this on your external scanning device / tablet kiosk.
      Each employee's QR code links to their unique token for time-in/out.
    </div>

    <!-- Employee QR table -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Employee QR Codes</h3></div>
      <div class="card-body" style="padding:16px 20px 0;">
        <table class="table table-sm table-bordered table-hover" id="qrTable">
          <thead class="thead-light">
            <tr><th>Employee</th><th>Department</th><th>QR Token</th>
                <th>Status</th><th>Generated</th><th>Last used</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($employees_qr as $e): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($e['full_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($e['emp_code']) ?></small>
              </td>
              <td><?= htmlspecialchars($e['dept'] ?: '—') ?></td>
              <td>
                <?php if ($e['qr_token']): ?>
                  <code style="font-size:11px;"><?= substr($e['qr_token'],0,16) ?>...</code>
                  <!-- QR image via Google Charts API (free, no library needed) -->
                  <a href="#" onclick="showQR('<?= htmlspecialchars($e['qr_token']) ?>','<?= htmlspecialchars($e['full_name']) ?>')"
                     class="btn btn-xs btn-info ml-1">
                    <i class="fas fa-qrcode"></i> View
                  </a>
                <?php else: ?>
                  <span class="text-muted">Not generated</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$e['qr_token']): ?>
                  <span class="badge badge-secondary">None</span>
                <?php elseif ($e['qr_active']): ?>
                  <span class="badge badge-success">Active</span>
                <?php else: ?>
                  <span class="badge badge-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;">
                <?= $e['generated_at'] ? date('M d, Y', strtotime($e['generated_at'])) : '—' ?>
              </td>
              <td style="font-size:12px;">
                <?= $e['last_used'] ? date('M d, Y g:i A', strtotime($e['last_used'])) : 'Never' ?>
              </td>
              <td style="white-space:nowrap;">
                <a href="attendance_biometric.php?action=generate&id=<?= $e['id'] ?>"
                   class="btn btn-primary btn-xs"
                   title="<?= $e['qr_token'] ? 'Regenerate QR' : 'Generate QR' ?>">
                  <i class="fas fa-sync mr-1"></i> <?= $e['qr_token'] ? 'Regen' : 'Generate' ?>
                </a>
                <?php if ($e['qr_id'] && $e['qr_active']): ?>
                <a href="attendance_biometric.php?action=deactivate&id=<?= $e['qr_id'] ?>"
                   class="btn btn-danger btn-xs"
                   onclick="return confirm('Deactivate this QR code?')">
                  <i class="fas fa-ban"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile clock-in log -->
    <div class="card mt-3">
      <div class="card-header"><h3 class="card-title">Mobile Clock-In / Clock-Out Log (Latest 50)</h3></div>
      <div class="card-body" style="padding:16px 20px 0;">
        <table class="table table-sm table-bordered table-hover" id="mobileTable">
          <thead class="thead-light">
            <tr><th>Employee</th><th>Action</th><th>Date & Time</th>
                <th>Photo</th><th>Location</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($mobile_logs as $log): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($log['full_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($log['emp_code']) ?></small>
              </td>
              <td>
                <span class="badge badge-<?= $log['action']==='Clock In'?'success':'danger' ?>">
                  <?= $log['action'] ?>
                </span>
              </td>
              <td><?= date('M d, Y g:i A', strtotime($log['log_datetime'])) ?></td>
              <td>
                <?php if ($log['photo_path']): ?>
                  <a href="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>"
                     target="_blank">
                    <img src="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>"
                         style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:11px;">
                <?php if ($log['latitude']): ?>
                  <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>"
                     target="_blank">
                    <i class="fas fa-map-marker-alt mr-1 text-danger"></i>
                    <?= number_format($log['latitude'],4) ?>, <?= number_format($log['longitude'],4) ?>
                  </a><br>
                  <?php if ($log['accuracy_meters']): ?>
                    <small class="text-muted">±<?= number_format($log['accuracy_meters'],0) ?>m</small>
                  <?php endif; ?>
                  <?php if ($log['address_text']): ?>
                    <br><small><?= htmlspecialchars($log['address_text']) ?></small>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">No GPS</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-<?= $log['status']==='Valid'?'success':($log['status']==='Flagged'?'warning':'danger') ?>">
                  <?= $log['status'] ?>
                </span>
                <?php if ($log['flag_reason']): ?>
                  <br><small class="text-muted"><?= htmlspecialchars($log['flag_reason']) ?></small>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="qrModalTitle">Employee QR Code</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <div class="modal-body text-center">
      <img id="qrImage" src="" style="width:200px;height:200px;" alt="QR Code">
      <div class="mt-2" id="qrName" style="font-size:13px;font-weight:600;"></div>
      <div style="font-size:11px;color:#6c757d;">Scan at the time-in/out kiosk</div>
      <a id="qrDownload" href="#" download="qr_code.png" class="btn btn-primary btn-sm mt-2">
        <i class="fas fa-download mr-1"></i> Download
      </a>
    </div>
  </div></div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
function showQR(token, name) {
    // Use Google Charts QR API (free, no server lib needed)
    var qrData  = encodeURIComponent(window.location.origin + '/hrms/pages/qr_scanner.php?token=' + token);
    var qrUrl   = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' + qrData + '&choe=UTF-8';
    document.getElementById('qrImage').src      = qrUrl;
    document.getElementById('qrName').textContent = name;
    document.getElementById('qrDownload').href  = qrUrl;
    document.getElementById('qrModalTitle').textContent = 'QR Code — ' + name;
    $('#qrModal').modal('show');
}
$(function(){
    $('#qrTable').DataTable({ pageLength:25, order:[[0,'asc']] });
    $('#mobileTable').DataTable({ pageLength:25, order:[[2,'desc']] });
});
</script>
</body></html>