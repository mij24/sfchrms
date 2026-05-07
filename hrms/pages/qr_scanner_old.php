<?php
// ============================================
// FILE: pages/qr_scanner.php
// QR kiosk — no login required
// Accepts ?token=xxx from QR code scan
// Records time-in or time-out in attendance table
// ============================================
require_once '../includes/db.php';

$shift_start = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='shift_start_default'") ?: '08:00:00';
$grace_mins  = (int)(db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='grace_minutes'") ?: 15);
$company     = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='company_name'") ?: 'SFC HRMS';

$message   = '';
$msg_type  = 'info';
$emp_found = null;
$today     = date('Y-m-d');
$now_time  = date('H:i:s');

// Process scanned token
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);

    $qr = db_fetch($pdo,
        "SELECT q.*, e.id AS emp_db_id, e.full_name, e.employee_id AS emp_code,
                e.employment_status, d.name AS dept, p.title AS position,
                a.id AS att_id, a.time_in, a.time_out, a.status AS att_status
         FROM employee_qr_codes q
         JOIN employees e ON q.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         LEFT JOIN positions p ON e.position_id=p.id
         LEFT JOIN attendance a ON a.employee_id=e.id AND a.attendance_date=?
         WHERE q.qr_token=? AND q.is_active=1 AND e.employment_status='Active'
         LIMIT 1",
        array($today, $token)
    );

    if (!$qr) {
        $message  = 'Invalid or inactive QR code. Please contact HR.';
        $msg_type = 'danger';
    } else {
        $emp_found = $qr;

        if (!$qr['att_id']) {
            // TIME IN
            $shift_ts = strtotime($today.' '.$shift_start);
            $grace_ts = $shift_ts + ($grace_mins * 60);
            $in_ts    = strtotime($today.' '.$now_time);
            $tardy    = max(0, (int)round(($in_ts - $shift_ts) / 60));
            $status   = $in_ts > $grace_ts ? 'Late' : 'Present';

            db_run($pdo,
                "INSERT INTO attendance (employee_id, attendance_date, time_in, status, tardiness_minutes)
                 VALUES (?,?,?,?,?)",
                array($qr['emp_db_id'], $today, $now_time, $status, $tardy)
            );
            // Update last_used
            db_run($pdo, "UPDATE employee_qr_codes SET last_used=NOW() WHERE qr_token=?", array($token));

            $msg_type     = $status === 'Late' ? 'warning' : 'success';
            $message      = $status === 'Late'
                ? "Good ".date('A').", {$qr['full_name']}! You are late by $tardy minute(s)."
                : "Good ".date('A').", {$qr['full_name']}! Time-in recorded at ".date('h:i A').".";
            $emp_found['time_in']   = $now_time;
            $emp_found['att_status'] = $status;

        } elseif (!$qr['time_out']) {
            // TIME OUT
            $shift_end_ts = strtotime($today.' 17:00:00');
            $out_ts       = strtotime($today.' '.$now_time);
            $ot_hrs       = max(0, round(($out_ts - $shift_end_ts) / 3600, 2));

            db_run($pdo,
                "UPDATE attendance SET time_out=?, overtime_hours=? WHERE id=?",
                array($now_time, $ot_hrs, $qr['att_id'])
            );
            db_run($pdo, "UPDATE employee_qr_codes SET last_used=NOW() WHERE qr_token=?", array($token));

            $message = "Goodbye, {$qr['full_name']}! Time-out at ".date('h:i A').".";
            if ($ot_hrs > 0) $message .= " Overtime: {$ot_hrs}h.";
            $msg_type           = 'success';
            $emp_found['time_out'] = $now_time;
        } else {
            $message  = "{$qr['full_name']} has already timed in and out today.";
            $msg_type = 'info';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title><?= htmlspecialchars($company) ?> | DTR Kiosk</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { background:#1a1a2e; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
           min-height:100vh; display:flex; flex-direction:column;
           align-items:center; justify-content:center; padding:16px; }
    .header { text-align:center; margin-bottom:24px; }
    .header h1 { color:#74c0fc; font-size:28px; font-weight:700; letter-spacing:2px; }
    .clock { color:#fff; font-size:48px; font-weight:300; margin-top:4px; }
    .date  { color:#aaa; font-size:16px; }
    .card  { background:#fff; border-radius:16px; padding:28px; width:100%;
             max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
    .card h2 { font-size:18px; color:#343a40; margin-bottom:20px; text-align:center; }
    .alert { padding:14px 16px; border-radius:10px; font-size:15px; margin:16px 0;
             text-align:center; font-weight:500; }
    .alert-success { background:#d4edda; color:#155724; }
    .alert-warning  { background:#fff3cd; color:#856404; }
    .alert-danger  { background:#f8d7da; color:#721c24; }
    .alert-info    { background:#d1ecf1; color:#0c5460; }
    .emp-card { background:#f8f9fa; border-radius:10px; padding:14px; margin:10px 0; text-align:center; }
    .emp-name { font-size:20px; font-weight:700; color:#343a40; }
    .emp-sub  { font-size:13px; color:#6c757d; margin-top:2px; }
    .time-badge { display:inline-block; background:#e9ecef; border-radius:99px;
                  padding:3px 12px; font-size:13px; margin-top:6px; font-weight:600; }
    .scan-prompt { text-align:center; padding:30px 0; color:#6c757d; }
    .scan-prompt i { font-size:64px; opacity:.3; display:block; margin-bottom:12px; }
    .footer { color:#aaa; font-size:12px; margin-top:20px; text-align:center; }
  </style>
</head>
<body>

<div class="header">
  <h1><?= htmlspecialchars($company) ?></h1>
  <div class="clock" id="clock"><?= date('h:i:s A') ?></div>
  <div class="date"><?= date('l, F d, Y') ?></div>
</div>

<div class="card">
  <h2><i class="fas fa-qrcode mr-2 text-primary"></i>QR Time In / Time Out</h2>

  <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($emp_found): ?>
    <div class="emp-card">
      <div class="emp-name"><?= htmlspecialchars($emp_found['full_name']) ?></div>
      <div class="emp-sub">
        <?= htmlspecialchars($emp_found['position'] ?: '—') ?>
        &mdash; <?= htmlspecialchars($emp_found['dept'] ?: '—') ?>
      </div>
      <?php if (!empty($emp_found['time_in'])): ?>
        <span class="time-badge">
          <i class="fas fa-sign-in-alt mr-1 text-success"></i>
          In: <?= date('h:i A', strtotime($emp_found['time_in'])) ?>
        </span>
      <?php endif; ?>
      <?php if (!empty($emp_found['time_out'])): ?>
        <span class="time-badge ml-1">
          <i class="fas fa-sign-out-alt mr-1 text-danger"></i>
          Out: <?= date('h:i A', strtotime($emp_found['time_out'])) ?>
        </span>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="scan-prompt">
      <i class="fas fa-qrcode"></i>
      Point your QR scanner at this screen or scan employee QR code to record attendance.
    </div>
  <?php endif; ?>
</div>

<div class="footer">
  <?= htmlspecialchars($company) ?> DTR Kiosk &mdash; <?= date('Y') ?>
</div>

<script>
function updateClock() {
    var now  = new Date();
    var h    = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    document.getElementById('clock').textContent =
        pad(h)+':'+pad(m)+':'+pad(s)+' '+ampm;
}
function pad(n) { return n < 10 ? '0'+n : n; }
setInterval(updateClock, 1000);

// Auto-reset page after 5 seconds if a result is showing
<?php if ($message): ?>
setTimeout(function(){ window.location.href = 'qr_scanner.php'; }, 5000);
<?php endif; ?>
</script>
</body>
</html>