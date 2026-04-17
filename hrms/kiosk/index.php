<?php
// ============================================
// FILE: kiosk/index.php
// Mobile-friendly employee time-in/time-out
// Place in: C:\xampp\htdocs\hrms\kiosk\index.php
// Access: http://localhost/hrms/kiosk/
// No login required — employee enters their ID
// ============================================
require_once '../includes/db.php';

// kiosk has its own session namespace
if (session_status() === PHP_SESSION_NONE) session_start();

$shift_start = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_start_default'") ?: '08:00:00';
$grace_mins  = (int)(db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='grace_minutes'") ?: 15);

$message    = '';
$msg_type   = 'info';
$emp_found  = null;
$today      = date('Y-m-d');
$now_time   = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = isset($_POST['action'])      ? $_POST['action']      : '';
    $emp_code= isset($_POST['employee_id']) ? trim($_POST['employee_id']) : '';

    if (empty($emp_code)) {
        $message = 'Please enter your Employee ID.'; $msg_type = 'warning';
    } else {
        $emp = db_fetch($pdo,
            "SELECT e.id,e.full_name,e.employee_id,e.employment_status,
                    d.name AS dept, p.title AS position,
                    a.id AS att_id, a.time_in, a.time_out, a.status AS att_status
             FROM employees e
             LEFT JOIN departments d ON e.department_id=d.id
             LEFT JOIN positions p ON e.position_id=p.id
             LEFT JOIN attendance a ON a.employee_id=e.id AND a.attendance_date=?
             WHERE e.employee_id=? AND e.employment_status='Active' LIMIT 1",
            array($today, $emp_code)
        );

        if (!$emp) {
            $message = "Employee ID '$emp_code' not found or inactive."; $msg_type = 'danger';
        } else {
            $emp_found = $emp;

            if ($action === 'time_in') {
                if ($emp['att_id']) {
                    $message = 'You already timed in today at '.date('h:i A',strtotime($emp['time_in'])).'.';
                    $msg_type = 'warning';
                } else {
                    // Compute tardiness
                    $shift_ts = strtotime($today.' '.$shift_start);
                    $grace_ts = $shift_ts + ($grace_mins * 60);
                    $in_ts    = strtotime($today.' '.$now_time);
                    $tardy    = max(0, (int)round(($in_ts - $shift_ts) / 60));
                    $status   = $in_ts > $grace_ts ? 'Late' : 'Present';

                    db_run($pdo,
                        "INSERT INTO attendance (employee_id,attendance_date,time_in,status,tardiness_minutes)
                         VALUES (?,?,?,?,?)",
                        array($emp['id'],$today,$now_time,$status,$tardy)
                    );

                    $msg_type = $status==='Late' ? 'warning' : 'success';
                    $message  = $status==='Late'
                        ? "Good ".date('A').", {$emp['full_name']}! You are late by $tardy minute(s)."
                        : "Good ".date('A').", {$emp['full_name']}! Time in recorded at ".date('h:i A').".";

                    // Refresh emp record
                    $emp_found['time_in'] = $now_time;
                    $emp_found['att_status'] = $status;
                }
            }

            if ($action === 'time_out') {
                if (!$emp['att_id']) {
                    $message = 'No time-in record found for today. Please time in first.';
                    $msg_type = 'warning';
                } elseif ($emp['time_out']) {
                    $message = 'You already timed out today at '.date('h:i A',strtotime($emp['time_out'])).'.';
                    $msg_type = 'warning';
                } else {
                    // Compute overtime
                    $shift_end_ts = strtotime($today.' 17:00:00');
                    $out_ts       = strtotime($today.' '.$now_time);
                    $ot_hours     = max(0, round(($out_ts - $shift_end_ts) / 3600, 2));

                    db_run($pdo,
                        "UPDATE attendance SET time_out=?, overtime_hours=? WHERE id=?",
                        array($now_time, $ot_hours, $emp['att_id'])
                    );
                    $message  = "Goodbye, {$emp['full_name']}! Time out recorded at ".date('h:i A').".";
                    if ($ot_hours > 0) $message .= " Overtime: {$ot_hours}h.";
                    $msg_type = 'success';
                    $emp_found['time_out'] = $now_time;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>SFCHRMS Kiosk — Time In / Out</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { background:#1a1a2e; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
           min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px; }
    .kiosk-header { text-align:center; margin-bottom:24px; }
    .kiosk-header h1 { color:#74c0fc; font-size:28px; font-weight:700; letter-spacing:2px; }
    .kiosk-header .time-display { color:#fff; font-size:48px; font-weight:300; margin-top:4px; }
    .kiosk-header .date-display { color:#aaa; font-size:16px; }
    .kiosk-card { background:#fff; border-radius:16px; padding:28px; width:100%; max-width:440px;
                  box-shadow:0 20px 60px rgba(0,0,0,.4); }
    .kiosk-card h2 { font-size:18px; color:#343a40; margin-bottom:20px; text-align:center; }
    .kiosk-input { width:100%; font-size:22px; padding:14px 16px; border:2px solid #dee2e6;
                   border-radius:10px; text-align:center; letter-spacing:4px; text-transform:uppercase;
                   outline:none; transition:border-color .2s; }
    .kiosk-input:focus { border-color:#007bff; }
    .kiosk-btn { width:100%; font-size:18px; padding:14px; border:none; border-radius:10px;
                 font-weight:700; cursor:pointer; margin-top:12px; transition:all .15s; }
    .kiosk-btn:active { transform:scale(.97); }
    .btn-in  { background:#28a745; color:#fff; }
    .btn-out { background:#dc3545; color:#fff; }
    .btn-in:hover  { background:#218838; }
    .btn-out:hover { background:#c82333; }
    .alert { padding:14px 16px; border-radius:10px; font-size:15px; margin-top:16px; text-align:center; font-weight:500; }
    .alert-success { background:#d4edda; color:#155724; }
    .alert-warning  { background:#fff3cd; color:#856404; }
    .alert-danger  { background:#f8d7da; color:#721c24; }
    .alert-info    { background:#d1ecf1; color:#0c5460; }
    .emp-card { background:#f8f9fa; border-radius:10px; padding:16px; margin-top:16px; text-align:center; }
    .emp-name { font-size:20px; font-weight:700; color:#343a40; }
    .emp-sub  { font-size:13px; color:#6c757d; margin-top:2px; }
    .time-badge { display:inline-block; background:#e9ecef; border-radius:99px; padding:3px 12px;
                  font-size:13px; margin-top:6px; font-weight:600; }
    .numpad { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-top:16px; }
    .numpad-btn { font-size:22px; padding:14px; border:1px solid #dee2e6; border-radius:8px;
                  background:#fff; cursor:pointer; font-weight:500; transition:background .1s; }
    .numpad-btn:active { background:#e9ecef; }
    .numpad-clear { background:#fff3cd; color:#856404; }
    .numpad-back  { background:#f8d7da; color:#721c24; }
    .kiosk-footer { color:#aaa; font-size:12px; margin-top:20px; text-align:center; }
    @media (max-width:480px) {
      .kiosk-header .time-display { font-size:36px; }
    }
  </style>
</head>
<body>

<div class="kiosk-header">
  <h1>SFC|<span style="color:#fff;">HRMS</span></h1>
  <div class="time-display" id="kiosk-time"><?= date('h:i:s A') ?></div>
  <div class="date-display"><?= date('l, F d, Y') ?></div>
</div>

<div class="kiosk-card">
  <h2><i class="fas fa-fingerprint mr-2 text-primary"></i>Employee Time In / Out</h2>

  <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($emp_found): ?>
    <div class="emp-card">
      <div class="emp-name"><?= htmlspecialchars($emp_found['full_name']) ?></div>
      <div class="emp-sub"><?= htmlspecialchars($emp_found['position']?:'—') ?> &mdash; <?= htmlspecialchars($emp_found['dept']?:'—') ?></div>
      <?php if ($emp_found['time_in']): ?>
        <span class="time-badge"><i class="fas fa-sign-in-alt mr-1 text-success"></i>In: <?= date('h:i A',strtotime($emp_found['time_in'])) ?></span>
      <?php endif; ?>
      <?php if ($emp_found['time_out']): ?>
        <span class="time-badge ml-1"><i class="fas fa-sign-out-alt mr-1 text-danger"></i>Out: <?= date('h:i A',strtotime($emp_found['time_out'])) ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="" id="kioskForm">
    <input type="text" name="employee_id" id="empInput" class="kiosk-input"
           placeholder="Enter Employee ID" autocomplete="off" autocapitalize="characters"
           value="<?= $emp_found ? '' : htmlspecialchars($_POST['employee_id']??'') ?>">

    <!-- On-screen numpad for touch screens -->
    <div class="numpad">
      <?php for ($i=1;$i<=9;$i++): ?>
        <button type="button" class="numpad-btn" onclick="numpadPress('<?= $i ?>')"><?= $i ?></button>
      <?php endfor; ?>
      <button type="button" class="numpad-btn numpad-clear" onclick="numpadClear()">CLR</button>
      <button type="button" class="numpad-btn" onclick="numpadPress('0')">0</button>
      <button type="button" class="numpad-btn numpad-back" onclick="numpadBack()">&#9003;</button>
    </div>

    <button type="submit" name="action" value="time_in"  class="kiosk-btn btn-in">
      <i class="fas fa-sign-in-alt mr-2"></i> TIME IN
    </button>
    <button type="submit" name="action" value="time_out" class="kiosk-btn btn-out">
      <i class="fas fa-sign-out-alt mr-2"></i> TIME OUT
    </button>
  </form>
</div>

<div class="kiosk-footer">
  SFC|HRMS Attendance Kiosk &mdash; <?= date('Y') ?><br>
  <small>Having trouble? Contact HR at your office.</small>
</div>

<script>
// Live clock
function updateClock() {
    var now = new Date();
    var h = now.getHours(); var m = now.getMinutes(); var s = now.getSeconds();
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    document.getElementById('kiosk-time').textContent =
        pad(h)+':'+pad(m)+':'+pad(s)+' '+ampm;
}
function pad(n) { return n < 10 ? '0'+n : n; }
setInterval(updateClock, 1000);

// Numpad
function numpadPress(val) {
    var inp = document.getElementById('empInput');
    inp.value += val;
}
function numpadClear() { document.getElementById('empInput').value = ''; }
function numpadBack() {
    var inp = document.getElementById('empInput');
    inp.value = inp.value.slice(0,-1);
}

// Auto-clear after 5 seconds if a message is shown
<?php if ($message): ?>
setTimeout(function(){
    document.getElementById('empInput').value = '';
    document.getElementById('empInput').focus();
}, 5000);
<?php endif; ?>
</script>
</body>
</html>