<?php
// ============================================
// FILE: pages/attendance_selfservice.php
// Mobile Clock-In/Out with:
//   - Selfie photo capture (front camera)
//   - GPS geolocation + accuracy
//   - Date/time stamp
//   - Saves to mobile_attendance_log + attendance
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

// ── HRMS timezone + delta ────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone'     && $_r['setting_value']) $_hrms_tz    = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds')                          $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }
function hrms_now()     { global $_hrms_delta; return time() + $_hrms_delta; }
function hrms_fmt($fmt) { return date($fmt, hrms_now()); }
// ── END ──────────────────────────────────────────────────────────

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;
if (!$emp_id) { echo not_linked_error(); exit; }

$emp = db_fetch($pdo,
    "SELECT e.*, d.name AS dept, p.title AS position
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     WHERE e.id=?",
    array($emp_id)
);

$today    = hrms_fmt('Y-m-d');
$att_today = db_fetch($pdo,
    "SELECT * FROM attendance WHERE employee_id=? AND attendance_date=?",
    array($emp_id, $today)
);

$shift_start = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='shift_start_default'") ?: '08:00:00';
$grace_mins  = (int)(db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='grace_minutes'") ?: 15);

$message  = '';
$msg_type = 'info';

// Handle AJAX POST (JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_clock'])) {
    header('Content-Type: application/json');
    csrf_verify();

    $action      = clean_string($_POST['action']);    // 'in' or 'out'
    $lat         = isset($_POST['lat'])      ? (float)$_POST['lat']      : null;
    $lng         = isset($_POST['lng'])      ? (float)$_POST['lng']      : null;
    $accuracy    = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
    $address     = isset($_POST['address'])  ? clean_string($_POST['address'], 255) : null;
    $device_info = isset($_POST['device'])   ? clean_string($_POST['device'], 255) : null;
    $ip          = $_SERVER['REMOTE_ADDR'];
    $now_dt      = hrms_fmt('Y-m-d H:i:s');
    $now_time    = hrms_fmt('H:i:s');

    // Handle base64 photo
    $photo_path = null;
    if (!empty($_POST['photo_b64'])) {
        $b64 = $_POST['photo_b64'];
        // Strip data URI prefix
        if (strpos($b64, ',') !== false) {
            $b64 = explode(',', $b64, 2)[1];
        }
        $img_data = base64_decode($b64);
        if ($img_data) {
            $dir = '../uploads/mobile_selfie/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname      = 'selfie_'.$emp_id.'_'.time().'.jpg';
            file_put_contents($dir.$fname, $img_data);
            $photo_path = $fname;
        }
    }

    $att_rec = db_fetch($pdo,
        "SELECT * FROM attendance WHERE employee_id=? AND attendance_date=?",
        array($emp_id, $today)
    );

    if ($action === 'in') {
        if ($att_rec && $att_rec['time_in']) {
            echo json_encode(array('ok'=>false,'msg'=>'You already clocked in today at '.date('h:i A', strtotime($att_rec['time_in'])).'.'));
            exit;
        }
        // Compute tardiness
        $shift_ts = strtotime($today.' '.$shift_start);
        $grace_ts = $shift_ts + ($grace_mins * 60);
        $in_ts    = strtotime($today.' '.$now_time);
        $tardy    = max(0, (int)round(($in_ts - $shift_ts) / 60));
        $status   = $in_ts > $grace_ts ? 'Late' : 'Present';

        if (!$att_rec) {
            db_run($pdo,
                "INSERT INTO attendance (employee_id, attendance_date, time_in, status, tardiness_minutes)
                 VALUES (?,?,?,?,?)",
                array($emp_id, $today, $now_time, $status, $tardy)
            );
        } else {
            db_run($pdo,
                "UPDATE attendance SET time_in=?, status=?, tardiness_minutes=? WHERE id=?",
                array($now_time, $status, $tardy, $att_rec['id'])
            );
        }
        $att_id = db_value($pdo,
            "SELECT id FROM attendance WHERE employee_id=? AND attendance_date=?",
            array($emp_id, $today)
        );
        db_run($pdo,
            "INSERT INTO mobile_attendance_log
             (employee_id, action, log_datetime, photo_path, latitude, longitude,
              accuracy_meters, address_text, device_info, ip_address, linked_attendance_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            array($emp_id,'Clock In',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_id)
        );

        $msg = $status === 'Late'
            ? "Clocked in at ".hrms_fmt('h:i A')." — Late by $tardy minute(s)."
            : "Clocked in at ".hrms_fmt('h:i A').". Have a great day!";
        echo json_encode(array('ok'=>true,'msg'=>$msg,'status'=>$status,'time'=>hrms_fmt('h:i A')));
        exit;

    } elseif ($action === 'out') {
        if (!$att_rec || !$att_rec['time_in']) {
            echo json_encode(array('ok'=>false,'msg'=>'No clock-in found for today.'));
            exit;
        }
        if ($att_rec['time_out']) {
            echo json_encode(array('ok'=>false,'msg'=>'You already clocked out today at '.date('h:i A', strtotime($att_rec['time_out'])).'.'));
            exit;
        }
        $shift_end_ts = strtotime($today.' 17:00:00');
        $out_ts       = strtotime($today.' '.$now_time);
        $ot_hrs       = max(0, round(($out_ts - $shift_end_ts) / 3600, 2));

        db_run($pdo,
            "UPDATE attendance SET time_out=?, overtime_hours=? WHERE id=?",
            array($now_time, $ot_hrs, $att_rec['id'])
        );
        db_run($pdo,
            "INSERT INTO mobile_attendance_log
             (employee_id, action, log_datetime, photo_path, latitude, longitude,
              accuracy_meters, address_text, device_info, ip_address, linked_attendance_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            array($emp_id,'Clock Out',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_rec['id'])
        );

        $msg = "Clocked out at ".hrms_fmt('h:i A').".";
        if ($ot_hrs > 0) $msg .= " Overtime: {$ot_hrs}h.";
        echo json_encode(array('ok'=>true,'msg'=>$msg,'time'=>hrms_fmt('h:i A')));
        exit;

    } elseif ($action === 'pm_in') {
        if (!$att_rec || !$att_rec['time_in']) {
            echo json_encode(array('ok'=>false,'msg'=>'You need to clock in for AM first.'));
            exit;
        }
        if (!empty($att_rec['pm_time_in']) && $att_rec['pm_time_in'] !== '00:00:00') {
            echo json_encode(array('ok'=>false,'msg'=>'Already clocked in for PM at '.date('h:i A',strtotime($att_rec['pm_time_in'])).'.'));
            exit;
        }
        db_run($pdo,
            "UPDATE attendance SET pm_time_in=? WHERE id=?",
            array($now_time, $att_rec['id'])
        );
        db_run($pdo,
            "INSERT INTO mobile_attendance_log
             (employee_id, action, log_datetime, photo_path, latitude, longitude,
              accuracy_meters, address_text, device_info, ip_address, linked_attendance_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            array($emp_id,'Clock In',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_rec['id'])
        );
        $msg = "PM Clock In at ".hrms_fmt('h:i A').".";
        echo json_encode(array('ok'=>true,'msg'=>$msg,'time'=>hrms_fmt('h:i A')));
        exit;

    } elseif ($action === 'pm_out') {
        if (!$att_rec || empty($att_rec['pm_time_in']) || $att_rec['pm_time_in'] === '00:00:00') {
            echo json_encode(array('ok'=>false,'msg'=>'You need to clock in for PM first.'));
            exit;
        }
        if (!empty($att_rec['pm_time_out']) && $att_rec['pm_time_out'] !== '00:00:00') {
            echo json_encode(array('ok'=>false,'msg'=>'Already clocked out for PM at '.date('h:i A',strtotime($att_rec['pm_time_out'])).'.'));
            exit;
        }
        $shift_end_ts = strtotime(hrms_fmt('Y-m-d').' 17:00:00');
        $ot_hrs2      = max(0, round((hrms_now() - $shift_end_ts) / 3600, 2));
        db_run($pdo,
            "UPDATE attendance SET pm_time_out=?, overtime_hours=? WHERE id=?",
            array($now_time, $ot_hrs2, $att_rec['id'])
        );
        db_run($pdo,
            "INSERT INTO mobile_attendance_log
             (employee_id, action, log_datetime, photo_path, latitude, longitude,
              accuracy_meters, address_text, device_info, ip_address, linked_attendance_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            array($emp_id,'Clock Out',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_rec['id'])
        );
        $msg = "PM Clock Out at ".hrms_fmt('h:i A').".";
        if ($ot_hrs2 > 0) $msg .= " Overtime: {$ot_hrs2}h.";
        echo json_encode(array('ok'=>true,'msg'=>$msg,'time'=>hrms_fmt('h:i A')));
        exit;
    }
    echo json_encode(array('ok'=>false,'msg'=>'Unknown action.'));
    exit;
}

// Initials for avatar
$parts    = explode(' ', trim($emp['full_name']));
$initials = '';
foreach (array_slice($parts,0,2) as $p) if($p) $initials .= strtoupper($p[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>Clock In / Out | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .clock-card { border-radius:16px; overflow:hidden; }
    #videoPreview { width:100%; max-height:220px; object-fit:cover;
                    border-radius:10px; background:#000; display:block; }
    #snapCanvas  { display:none; }
    #photoPreview { width:100%; max-height:180px; object-fit:cover;
                    border-radius:10px; display:none; border:2px solid #28a745; }
    .status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px; }
    .geo-box { background:#f8f9fa; border-radius:8px; padding:10px; font-size:12px; }
    .btn-clock { border-radius:12px; font-size:16px; font-weight:600; padding:14px; }
    .timestamp { font-size:11px; color:#6c757d; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <h1 class="m-0">Mobile Clock In / Out</h1>
  </div></div>
  <div class="content"><div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">

        <!-- Employee info -->
        <div class="card mb-3">
          <div class="card-body d-flex align-items-center" style="gap:14px;">
            <div style="width:50px;height:50px;border-radius:50%;background:#4a90d9;
                        display:flex;align-items:center;justify-content:center;
                        font-size:18px;font-weight:700;color:#fff;flex-shrink:0;">
              <?= $initials ?>
            </div>
            <div>
              <strong><?= htmlspecialchars($emp['full_name']) ?></strong><br>
              <small class="text-muted">
                <?= htmlspecialchars($emp['position'] ?: '—') ?> &mdash; <?= htmlspecialchars($emp['dept'] ?: '—') ?>
              </small>
            </div>
            <div class="ml-auto text-right">
              <?php if ($att_today && $att_today['time_in'] && $att_today['time_in']!=='00:00:00'): ?>
                <small class="text-success"><i class="fas fa-sign-in-alt mr-1"></i>AM In: <?= date('h:i A', strtotime($att_today['time_in'])) ?></small>
              <?php endif; ?>
              <?php if ($att_today && $att_today['time_out'] && $att_today['time_out']!=='00:00:00'): ?>
                <br><small class="text-danger"><i class="fas fa-sign-out-alt mr-1"></i>AM Out: <?= date('h:i A', strtotime($att_today['time_out'])) ?></small>
              <?php endif; ?>
              <?php if ($att_today && !empty($att_today['pm_time_in']) && $att_today['pm_time_in']!=='00:00:00'): ?>
                <br><small class="text-primary"><i class="fas fa-sign-in-alt mr-1"></i>PM In: <?= date('h:i A', strtotime($att_today['pm_time_in'])) ?></small>
              <?php endif; ?>
              <?php if ($att_today && !empty($att_today['pm_time_out']) && $att_today['pm_time_out']!=='00:00:00'): ?>
                <br><small class="text-warning"><i class="fas fa-sign-out-alt mr-1"></i>PM Out: <?= date('h:i A', strtotime($att_today['pm_time_out'])) ?></small>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="card clock-card">
          <div class="card-body">

            <!-- Alert -->
            <div id="alertBox" style="display:none;" class="alert mb-3"></div>

            <!-- Camera preview -->
            <div class="mb-3">
              <label class="d-flex justify-content-between align-items-center mb-1">
                <span style="font-size:13px;font-weight:600;">
                  <i class="fas fa-camera mr-1 text-primary"></i> Photo capture
                </span>
                <span id="camStatus" style="font-size:11px;color:#6c757d;">Loading camera...</span>
              </label>
              <video id="videoPreview" autoplay playsinline muted></video>
              <canvas id="snapCanvas"></canvas>
              <img id="photoPreview" src="" alt="Captured photo">
              <button type="button" id="retakeBtn" class="btn btn-secondary btn-sm mt-1"
                      style="display:none;" onclick="retakePhoto()">
                <i class="fas fa-redo mr-1"></i> Retake
              </button>
            </div>

            <!-- Geolocation -->
            <div class="geo-box mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-map-marker-alt mr-1 text-danger"></i> Location</span>
                <button type="button" id="getLocBtn" class="btn btn-xs btn-outline-secondary"
                        onclick="getLocation()">
                  <i class="fas fa-crosshairs mr-1"></i> Get location
                </button>
              </div>
              <div id="geoStatus" class="mt-1" style="color:#6c757d;">Not yet captured.</div>
              <div id="geoCoords" style="font-size:11px;"></div>
            </div>

            <!-- Date/time stamp -->
            <div class="d-flex justify-content-between mb-3">
              <div>
                <div style="font-size:24px;font-weight:700;" id="liveTime"><?= hrms_fmt('h:i:s A') ?></div>
                <div class="timestamp"><?= hrms_fmt('l, F d, Y') ?></div>
              </div>
              <div class="text-right">
                <?php
                $has_am_in  = $att_today && !empty($att_today['time_in'])     && $att_today['time_in']     !== '00:00:00';
                $has_am_out = $att_today && !empty($att_today['time_out'])    && $att_today['time_out']    !== '00:00:00';
                $has_pm_in  = $att_today && !empty($att_today['pm_time_in'])  && $att_today['pm_time_in']  !== '00:00:00';
                $has_pm_out = $att_today && !empty($att_today['pm_time_out']) && $att_today['pm_time_out'] !== '00:00:00';
                $can_am_in  = !$has_am_in;
                $can_am_out = $has_am_in  && !$has_am_out;
                $can_pm_in  = $has_am_out && !$has_pm_in;
                $can_pm_out = $has_pm_in  && !$has_pm_out;
                ?>
                <span class="badge badge-<?= $has_am_in ? 'success' : 'warning' ?>" style="font-size:12px;">
                  <?= $has_pm_out ? 'Complete' : ($has_pm_in ? 'PM session' : ($has_am_out ? 'Lunch break' : ($has_am_in ? 'AM session' : 'Not clocked in'))) ?>
                </span>
              </div>
            </div>

            <!-- 4 Action buttons: AM In | AM Out | PM In | PM Out -->
            <div class="row mb-2" style="gap:0;">
              <div class="col-6 pr-1 mb-2">
                <button id="btnAMIn" class="btn btn-success btn-clock btn-block"
                        <?= !$can_am_in ? 'disabled' : '' ?>
                        onclick="doClockAction('in')">
                  <i class="fas fa-sign-in-alt d-block mb-1" style="font-size:18px;"></i>
                  <span style="font-size:12px;">AM Clock In</span>
                </button>
              </div>
              <div class="col-6 pl-1 mb-2">
                <button id="btnAMOut" class="btn btn-danger btn-clock btn-block"
                        <?= !$can_am_out ? 'disabled' : '' ?>
                        onclick="doClockAction('out')">
                  <i class="fas fa-sign-out-alt d-block mb-1" style="font-size:18px;"></i>
                  <span style="font-size:12px;">AM Clock Out</span>
                </button>
              </div>
              <div class="col-6 pr-1">
                <button id="btnPMIn" class="btn btn-primary btn-clock btn-block"
                        <?= !$can_pm_in ? 'disabled' : '' ?>
                        onclick="doClockAction('pm_in')">
                  <i class="fas fa-sign-in-alt d-block mb-1" style="font-size:18px;"></i>
                  <span style="font-size:12px;">PM Clock In</span>
                </button>
              </div>
              <div class="col-6 pl-1">
                <button id="btnPMOut" class="btn btn-warning btn-clock btn-block"
                        <?= !$can_pm_out ? 'disabled' : '' ?>
                        onclick="doClockAction('pm_out')">
                  <i class="fas fa-sign-out-alt d-block mb-1" style="font-size:18px;"></i>
                  <span style="font-size:12px;">PM Clock Out</span>
                </button>
              </div>
            </div>

            <div id="loadingSpinner" style="display:none;text-align:center;margin-top:12px;">
              <i class="fas fa-spinner fa-spin text-primary"></i> Processing...
            </div>
          </div>
        </div>

        <!-- My log today -->
        <?php
        $today_logs = db_fetchall($pdo,
            "SELECT * FROM mobile_attendance_log WHERE employee_id=? AND DATE(log_datetime)=? ORDER BY log_datetime",
            array($emp_id, $today)
        );
        ?>
        <?php if (!empty($today_logs)): ?>
        <div class="card mt-3">
          <div class="card-header"><h3 class="card-title" style="font-size:13px;">Today's Log</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Action</th><th>Time</th><th>Photo</th><th>Location</th></tr></thead>
              <tbody>
                <?php foreach ($today_logs as $log): ?>
                <tr>
                  <td><span class="badge badge-<?= $log['action']==='Clock In'?'success':'danger' ?>">
                    <?= $log['action'] ?></span></td>
                  <td><?= date('h:i A', strtotime($log['log_datetime'])) ?></td>
                  <td>
                    <?php if ($log['photo_path']): ?>
                      <a href="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>" target="_blank">
                        <img src="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>"
                             style="width:32px;height:32px;object-fit:cover;border-radius:4px;">
                      </a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                  <td style="font-size:11px;">
                    <?php if ($log['latitude']): ?>
                      <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>"
                         target="_blank"><i class="fas fa-map-marker-alt text-danger"></i> Map</a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
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
<script>
// ── State ────────────────────────────────────────────────────────
var stream      = null;
var capturedB64 = null;
var geoLat      = null;
var geoLng      = null;
var geoAccuracy = null;
var geoAddress  = null;

// ── Live clock (HRMS adjusted time) ─────────────────────────────
var _hrms_ms = <?= (int)$_hrms_delta ?> * 1000;
function updateClock() {
    var n = new Date(Date.now() + _hrms_ms);
    var h = n.getHours(), m = n.getMinutes(), s = n.getSeconds();
    var ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
    document.getElementById('liveTime').textContent =
        pad(h)+':'+pad(m)+':'+pad(s)+' '+ap;
}
function pad(n){ return n < 10 ? '0'+n : n; }
setInterval(updateClock, 1000);

// ── Camera ───────────────────────────────────────────────────────
async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width:{ideal:480}, height:{ideal:360} }
        });
        var vid = document.getElementById('videoPreview');
        vid.srcObject = stream;
        document.getElementById('camStatus').textContent = 'Camera ready. Photo will be taken on clock.';
    } catch(err) {
        document.getElementById('camStatus').textContent = 'Camera not available: ' + err.message;
    }
}

function capturePhoto() {
    var vid = document.getElementById('videoPreview');
    var can = document.getElementById('snapCanvas');
    can.width  = vid.videoWidth  || 480;
    can.height = vid.videoHeight || 360;
    var ctx = can.getContext('2d');
    ctx.drawImage(vid, 0, 0, can.width, can.height);

    // Add timestamp watermark
    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    ctx.fillRect(0, can.height - 30, can.width, 30);
    ctx.fillStyle = '#fff';
    ctx.font = '12px Arial';
    var ts = new Date().toLocaleString('en-PH');
    ctx.fillText(ts, 8, can.height - 10);
    if (geoLat) ctx.fillText('GPS: '+geoLat.toFixed(4)+', '+geoLng.toFixed(4), can.width/2, can.height - 10);

    capturedB64 = can.toDataURL('image/jpeg', 0.75);
    var prev = document.getElementById('photoPreview');
    prev.src = capturedB64;
    prev.style.display = 'block';
    vid.style.display  = 'none';
    document.getElementById('retakeBtn').style.display = 'inline-block';
    return capturedB64;
}

function retakePhoto() {
    capturedB64 = null;
    document.getElementById('photoPreview').style.display = 'none';
    document.getElementById('videoPreview').style.display = 'block';
    document.getElementById('retakeBtn').style.display   = 'none';
}

// ── Geolocation ──────────────────────────────────────────────────
function getLocation() {
    var btn = document.getElementById('getLocBtn');
    btn.disabled = true;
    document.getElementById('geoStatus').textContent = 'Getting location...';
    if (!navigator.geolocation) {
        document.getElementById('geoStatus').textContent = 'Geolocation not supported.';
        btn.disabled = false; return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            geoLat      = pos.coords.latitude;
            geoLng      = pos.coords.longitude;
            geoAccuracy = pos.coords.accuracy;
            document.getElementById('geoStatus').innerHTML =
                '<i class="fas fa-check-circle text-success mr-1"></i>Location captured.';
            document.getElementById('geoCoords').textContent =
                geoLat.toFixed(6) + ', ' + geoLng.toFixed(6) + ' (±' + Math.round(geoAccuracy) + 'm)';
            // Reverse geocode using nominatim (free)
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+geoLat+'&lon='+geoLng)
                .then(function(r){ return r.json(); })
                .then(function(d){ geoAddress = d.display_name; })
                .catch(function(){});
            btn.disabled = false;
        },
        function(err) {
            document.getElementById('geoStatus').textContent = 'Location error: ' + err.message;
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000 }
    );
}

// ── Clock action ─────────────────────────────────────────────────
function setButtons(disabled) {
    var ids = ['btnAMIn','btnAMOut','btnPMIn','btnPMOut'];
    for (var i=0; i<ids.length; i++) {
        var b = document.getElementById(ids[i]);
        if (b) b.disabled = disabled;
    }
}

function doClockAction(action) {
    if (!capturedB64 && stream) capturePhoto();

    document.getElementById('loadingSpinner').style.display = 'block';
    setButtons(true);

    var formData = new FormData();
    formData.append('ajax_clock', '1');
    formData.append('action', action);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    if (capturedB64) formData.append('photo_b64', capturedB64.split(',')[1] || capturedB64);
    if (geoLat)      formData.append('lat', geoLat);
    if (geoLng)      formData.append('lng', geoLng);
    if (geoAccuracy) formData.append('accuracy', geoAccuracy);
    if (geoAddress)  formData.append('address', geoAddress);
    formData.append('device', navigator.userAgent.substring(0,200));

    fetch('attendance_selfservice.php', { method:'POST', body:formData })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            document.getElementById('loadingSpinner').style.display = 'none';
            var box = document.getElementById('alertBox');
            box.style.display = 'block';
            box.className = 'alert ' + (data.ok ? 'alert-success' : 'alert-danger');
            box.textContent = data.msg;
            if (data.ok) {
                setTimeout(function(){ window.location.reload(); }, 2500);
            } else {
                setButtons(false);
            }
        })
        .catch(function(e) {
            document.getElementById('loadingSpinner').style.display = 'none';
            var box = document.getElementById('alertBox');
            box.style.display = 'block';
            box.className = 'alert alert-danger';
            box.textContent = 'Network error. Please try again.';
            setButtons(false);
        });
}

// ── Init ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    startCamera();
    getLocation();
});
</script>
</body>
</html>