<?php
// ============================================
// FILE: pages/my_attendance.php — FIXED
// 2 Tabs: View Attendance | Clock In/Out
// FIXES: csrf.php included, blank page resolved,
//        Clock In/Out tab links to attendance_selfservice.php
// ============================================


error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

if (!$emp_id) { ?>
  <!DOCTYPE html><html><body class="hold-transition sidebar-mini"><div class="wrapper">
  <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
  <div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i>
      Your account is not linked to an employee record. Contact HR.
    </div>
  </div></div></div>
  <?php include '../includes/footer.php'; ?>
  </div></body></html>
<?php exit; }

//$emp = db_fetch($pdo,
    //"SELECT e.full_name, e.employee_id FROM employees WHERE id=?",
    //array($emp_id)
//);

$emp = db_fetch($pdo,
    "SELECT full_name, employee_id FROM employees WHERE id=?",
    array($emp_id)
);

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$records = db_fetchall($pdo,
    "SELECT * FROM attendance
     WHERE employee_id=?
       AND MONTH(attendance_date)=?
       AND YEAR(attendance_date)=?
     ORDER BY attendance_date DESC",
    array($emp_id, $month, $year)
);

$summary = db_fetch($pdo,
    "SELECT
        SUM(status='Present')  AS present,
        SUM(status='Absent')   AS absent,
        SUM(status='Late')     AS late,
        SUM(status='Half-day') AS halfday,
        SUM(tardiness_minutes) AS total_tardiness,
        SUM(overtime_hours)    AS total_overtime
     FROM attendance
     WHERE employee_id=?
       AND MONTH(attendance_date)=?
       AND YEAR(attendance_date)=?",
    array($emp_id, $month, $year)
);

// Today's record
$today_att = db_fetch($pdo,
    "SELECT * FROM attendance WHERE employee_id=? AND attendance_date=CURDATE()",
    array($emp_id)
);

// QR code
$my_qr = db_fetch($pdo,
    "SELECT * FROM employee_qr_codes WHERE employee_id=? AND is_active=1 LIMIT 1",
    array($emp_id)
);

$months_list = array(
    1=>'January',2=>'February',3=>'March',4=>'April',
    5=>'May',6=>'June',7=>'July',8=>'August',
    9=>'September',10=>'October',11=>'November',12=>'December'
);
$stat_badge = array(
    'Present'  => 'success', 'Absent'   => 'danger',
    'Late'     => 'warning', 'Half-day' => 'info',
    'On leave' => 'primary', 'Holiday'  => 'secondary', 'Rest day' => 'dark'
);
$color_map = array(
    'Present'=>'#28a745','Absent'=>'#dc3545','Late'=>'#ffc107',
    'Half-day'=>'#17a2b8','On leave'=>'#007bff',
    'Holiday'=>'#6c757d','Rest day'=>'#343a40'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .att-tabs { background:#fff; border-bottom:1px solid #dee2e6; padding:0 20px; }
    .att-tabs .nav-link {
      color:#6c757d; font-size:13px; font-weight:500; padding:12px 20px;
      border:none; border-radius:0; border-bottom:3px solid transparent;
    }
    .att-tabs .nav-link.active { color:#1a73e8; border-bottom-color:#1a73e8; background:none; }
    .att-tabs .nav-link:hover  { color:#1a73e8; }

    /* Clock-In / Out tile */
    .clock-tile {
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
      border-radius: 14px; padding: 28px 24px; color:#fff; text-align:center;
    }
    .clock-tile .live-clock { font-size:52px; font-weight:200; letter-spacing:3px; }
    .clock-tile .live-date  { font-size:13px; opacity:.65; margin-top:4px; }
    .clock-tile .emp-tag    { margin-top:8px; font-size:12px; opacity:.5; }

    .today-status-row { display:flex; gap:12px; margin-top:18px; }
    .today-status-box { flex:1; background:rgba(255,255,255,0.09); border-radius:10px;
                        padding:12px; text-align:center; }
    .today-status-box .ts-label { font-size:10px; text-transform:uppercase; opacity:.6; }
    .today-status-box .ts-val   { font-size:20px; font-weight:700; margin-top:4px; }

    .btn-clockin  { background:#00c853; color:#fff; border:0; font-weight:700;
                    font-size:15px; padding:13px; border-radius:12px; width:100%; }
    .btn-clockin:hover  { background:#00a844; color:#fff; }
    .btn-clockout { background:#f44336; color:#fff; border:0; font-weight:700;
                    font-size:15px; padding:13px; border-radius:12px; width:100%; }
    .btn-clockout:hover { background:#d32f2f; color:#fff; }

    /* Camera */
    .camera-wrap { position:relative; background:#111; border-radius:12px;
                   overflow:hidden; aspect-ratio:4/3; max-height:260px; }
    .camera-wrap video  { width:100%; height:100%; object-fit:cover; display:none; }
    .camera-overlay {
      position:absolute; inset:0; display:flex; flex-direction:column;
      align-items:center; justify-content:center; color:#fff;
      font-size:13px; text-align:center; padding:16px;
    }
    .camera-note { font-size:11px; color:#6c757d; text-align:center; margin-top:8px; }

    .geo-box { background:#f8f9fa; border-radius:8px; padding:10px 12px; font-size:12px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">My Attendance</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">My Attendance</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content"><div class="container-fluid">

    <!-- ── TABS ── -->
    <div class="card mb-0" style="border-radius:10px 10px 0 0;border-bottom:0;">
      <div class="att-tabs">
        <ul class="nav" id="attTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-view" role="tab">
              <i class="fas fa-calendar-alt mr-1"></i> View Attendance
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-clock" role="tab">
              <i class="fas fa-clock mr-1"></i> Clock In / Out
            </a>
          </li>
        </ul>
      </div>
    </div>

    <div class="tab-content" style="background:#f4f6f9; padding:20px 0;">

      <!-- ═══════════════════════════════════
           TAB 1 — VIEW ATTENDANCE
      ═══════════════════════════════════ -->
      <div class="tab-pane fade show active" id="tab-view">
        <div class="container-fluid">

          <!-- Month/Year filter -->
          <div class="card mb-3">
            <div class="card-body py-2">
              <form method="GET" action="" class="form-inline" style="gap:10px;">
                <label class="mr-1 font-weight-bold" style="font-size:13px;">Month:</label>
                <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                  <?php foreach ($months_list as $mn => $ml): ?>
                    <option value="<?= $mn ?>" <?= $mn===$month?'selected':'' ?>><?= $ml ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                  <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-2; $y--): ?>
                    <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </form>
            </div>
          </div>

          <!-- Summary cards -->
          <div class="row mb-3">
            <?php
            $cards = array(
              array('Present',          (int)($summary['present']??0),                   'success'),
              array('Absent',           (int)($summary['absent']??0),                    'danger'),
              array('Late',             (int)($summary['late']??0),                      'warning'),
              array('Half-day',         (int)($summary['halfday']??0),                   'info'),
              array('Tardiness (mins)', (int)($summary['total_tardiness']??0),           'secondary'),
              array('Overtime (hrs)',   number_format((float)($summary['total_overtime']??0),2),'primary'),
            );
            foreach ($cards as $c): ?>
            <div class="col-6 col-md-2 mb-2">
              <div class="card mb-0 text-center">
                <div class="card-body py-2">
                  <div style="font-size:22px;font-weight:700;"><?= $c[1] ?></div>
                  <div style="font-size:11px;color:#6c757d;"><?= $c[0] ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Calendar view -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <?= $months_list[$month] ?> <?= $year ?> — Attendance Overview
              </h3>
            </div>
            <div class="card-body">
              <?php
              $date_map      = array();
              foreach ($records as $r) { $date_map[$r['attendance_date']] = $r; }
              $days_in_month = (int)date('t', mktime(0,0,0,$month,1,$year));
              $first_dow     = (int)date('w', mktime(0,0,0,$month,1,$year));
              ?>
              <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;">
                <?php foreach (array('Sun','Mon','Tue','Wed','Thu','Fri','Sat') as $d): ?>
                  <div style="font-size:11px;font-weight:600;color:#6c757d;padding:4px 0;"><?= $d ?></div>
                <?php endforeach; ?>
                <?php for ($i=0;$i<$first_dow;$i++): ?><div></div><?php endfor; ?>
                <?php for ($day=1;$day<=$days_in_month;$day++):
                  $date_str = sprintf('%04d-%02d-%02d',$year,$month,$day);
                  $rec  = isset($date_map[$date_str]) ? $date_map[$date_str] : null;
                  $bg   = $rec ? (isset($color_map[$rec['status']]) ? $color_map[$rec['status']] : '#e9ecef') : '#f8f9fa';
                  $fc   = $rec ? '#fff' : '#adb5bd';
                  $isToday = ($date_str === date('Y-m-d'));
                ?>
                <div title="<?= $rec ? htmlspecialchars($rec['status']) : '' ?>"
                     style="background:<?= $bg ?>;color:<?= $fc ?>;border-radius:6px;
                            padding:6px 2px;font-size:12px;
                            <?= $isToday ? 'border:2px solid #007bff;font-weight:700;' : '' ?>">
                  <?= $day ?>
                  <?php if ($rec && $rec['tardiness_minutes'] > 0): ?>
                    <div style="font-size:9px;opacity:.85;"><?= $rec['tardiness_minutes'] ?>m</div>
                  <?php endif; ?>
                </div>
                <?php endfor; ?>
              </div>
              <!-- Legend -->
              <div class="d-flex flex-wrap mt-3" style="gap:10px;">
                <?php foreach ($color_map as $label => $color): ?>
                  <div style="display:flex;align-items:center;gap:4px;font-size:12px;">
                    <div style="width:12px;height:12px;border-radius:3px;background:<?= $color ?>;"></div>
                    <?= $label ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Daily detail table — NOTE: plain DataTable, NOT dt-export class to avoid footer.php conflict -->
          <div class="card">
            <div class="card-header"><h3 class="card-title">Daily Time Log</h3></div>
            <div class="card-body" style="padding:16px 20px 0;">
              <table class="table table-sm table-bordered table-hover mb-0" id="myAttTable">
                <thead class="thead-light">
                  <tr>
                    <th>Date</th><th>Day</th><th>Time In</th><th>Time Out</th>
                    <th>Status</th><th>Tardiness</th><th>Overtime</th><th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($records)): ?>
                    <?php foreach ($records as $r):
                      $sb = isset($stat_badge[$r['status']]) ? $stat_badge[$r['status']] : 'secondary';
                      $ti = ($r['time_in']  && $r['time_in']  !== '00:00:00') ? date('h:i A',strtotime($r['time_in']))  : '—';
                      $to = ($r['time_out'] && $r['time_out'] !== '00:00:00') ? date('h:i A',strtotime($r['time_out'])) : '—';
                    ?>
                    <tr>
                      <td><?= date('M d, Y', strtotime($r['attendance_date'])) ?></td>
                      <td><?= date('l', strtotime($r['attendance_date'])) ?></td>
                      <td><?= $ti ?></td>
                      <td><?= $to ?></td>
                      <td><span class="badge badge-<?= $sb ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                      <td><?= $r['tardiness_minutes']>0 ? $r['tardiness_minutes'].' mins' : '—' ?></td>
                      <td><?= $r['overtime_hours']>0    ? $r['overtime_hours'].' hrs'     : '—' ?></td>
                      <td><?= htmlspecialchars($r['remarks'] ? $r['remarks'] : '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="8" class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-2x d-block mb-2" style="opacity:.2;"></i>
                        No attendance records for <?= $months_list[$month] ?> <?= $year ?>.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div><!-- /tab-view -->

      <!-- ═══════════════════════════════════
           TAB 2 — CLOCK IN / OUT
      ═══════════════════════════════════ -->
      <div class="tab-pane fade" id="tab-clock">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

              <!-- Clock tile with live time + today status -->
              <div class="clock-tile mb-3">
                <div class="live-clock" id="liveClock"><?= date('h:i:s A') ?></div>
                <div class="live-date"><?= date('l, F d, Y') ?></div>
                <div class="emp-tag"><?= htmlspecialchars($emp['full_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($emp['employee_id']) ?></div>
                <div class="today-status-row">
                  <div class="today-status-box">
                    <div class="ts-label">Time In</div>
                    <div class="ts-val">
                      <?= ($today_att && $today_att['time_in'] && $today_att['time_in'] !== '00:00:00')
                          ? date('h:i A', strtotime($today_att['time_in'])) : '—' ?>
                    </div>
                    <?php if ($today_att && $today_att['tardiness_minutes'] > 0): ?>
                      <div style="font-size:10px;color:#ff6b6b;"><?= $today_att['tardiness_minutes'] ?> min late</div>
                    <?php endif; ?>
                  </div>
                  <div class="today-status-box">
                    <div class="ts-label">Time Out</div>
                    <div class="ts-val">
                      <?= ($today_att && $today_att['time_out'] && $today_att['time_out'] !== '00:00:00')
                          ? date('h:i A', strtotime($today_att['time_out'])) : '—' ?>
                    </div>
                    <?php if ($today_att && $today_att['overtime_hours'] > 0): ?>
                      <div style="font-size:10px;color:#69f0ae;"><?= $today_att['overtime_hours'] ?>h OT</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Camera card -->
              <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-camera mr-1"></i> Selfie Clock</h3></div>
                <div class="card-body">

                  <!-- Camera error banner (hidden by default) -->
                  <div id="cameraErrBox" class="alert alert-warning" style="display:none;font-size:12px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Camera unavailable.</strong> <span id="cameraErrMsg"></span>
                    <br><small>You can still clock in/out without a selfie. To enable camera:
                    <ul style="margin:4px 0 0 16px;">
                      <li>Allow camera permission in your browser address bar.</li>
                      <li>On mobile: go to Settings → Browser → Permissions → Camera → Allow.</li>
                      <li>If the system runs over HTTP (not HTTPS), the browser may block camera access. Ask your IT admin to enable HTTPS.</li>
                    </ul></small>
                  </div>

                  <!-- Camera preview -->
                  <div class="camera-wrap mb-3" id="camWrap">
                    <video id="camVideo" autoplay playsinline muted></video>
                    <canvas id="camCanvas" style="display:none;"></canvas>
                    <div class="camera-overlay" id="camOverlay">
                      <i class="fas fa-camera fa-3x mb-2" style="opacity:.3;"></i>
                      <div id="camStatus" style="font-size:13px;">Initializing camera...</div>
                    </div>
                  </div>
                  <!-- Captured photo preview -->
                  <img id="photoPreview" src="" alt="Selfie"
                       style="display:none;width:100%;max-height:200px;object-fit:cover;
                              border-radius:10px;border:2px solid #28a745;margin-bottom:10px;">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div id="camNote" class="camera-note" style="flex:1;text-align:left;">
                      Camera will take a selfie when you Clock In or Clock Out.
                    </div>
                    <button type="button" id="retakeBtn" class="btn btn-xs btn-secondary ml-2"
                            style="display:none;" onclick="retakePhoto()">
                      <i class="fas fa-redo mr-1"></i> Retake
                    </button>
                  </div>

                  <!-- Geolocation -->
                  <div class="geo-box mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                      <span><i class="fas fa-map-marker-alt mr-1 text-danger"></i> Location</span>
                      <button type="button" class="btn btn-xs btn-outline-secondary" onclick="getGeo()">
                        <i class="fas fa-crosshairs mr-1"></i> Get location
                      </button>
                    </div>
                    <div id="geoStatus" class="mt-1" style="color:#6c757d;">Not yet captured.</div>
                    <div id="geoCoords" style="font-size:11px;color:#aaa;"></div>
                  </div>

                  <!-- AJAX result -->
                  <div id="clockResult" class="alert" style="display:none;"></div>
                  <div id="clockSpinner" style="display:none;text-align:center;margin-bottom:10px;">
                    <i class="fas fa-spinner fa-spin text-primary mr-1"></i> Processing...
                  </div>

                  <!-- Clock In / Out buttons -->
                  <div class="row">
                    <div class="col-6 pr-1">
                      <button type="button" class="btn-clockin"
                              id="btnIn"
                              <?= ($today_att && $today_att['time_in']) ? 'disabled' : '' ?>
                              onclick="doClockAjax('in')">
                        <i class="fas fa-sign-in-alt mr-1"></i> Clock In
                      </button>
                    </div>
                    <div class="col-6 pl-1">
                      <button type="button" class="btn-clockout"
                              id="btnOut"
                              <?= (!$today_att || !$today_att['time_in'] || $today_att['time_out']) ? 'disabled' : '' ?>
                              onclick="doClockAjax('out')">
                        <i class="fas fa-sign-out-alt mr-1"></i> Clock Out
                      </button>
                    </div>
                  </div>

                </div>
              </div>

              <!-- QR code alternative -->
              <?php if ($my_qr): ?>
              <?php
                $qr_data = urlencode('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/qr_scanner.php?token='.$my_qr['qr_token']);
                $qr_url  = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl='.$qr_data.'&choe=UTF-8';
              ?>
              <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-qrcode mr-1"></i> QR Code Alternative</h3></div>
                <div class="card-body text-center">
                  <img src="<?= $qr_url ?>" style="width:130px;height:130px;" alt="QR">
                  <div style="font-size:12px;color:#6c757d;margin-top:8px;">
                    Scan at the attendance kiosk to clock in/out.
                  </div>
                </div>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div><!-- /tab-clock -->

    </div><!-- /tab-content -->
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
// ── Hidden CSRF token for AJAX ──────────────────────────────────
var CSRF_TOKEN = '<?= csrf_token() ?>';

// ── Live clock ──────────────────────────────────────────────────
(function tick(){
    var n=new Date(), h=n.getHours(), m=n.getMinutes(), s=n.getSeconds();
    var ap=h>=12?'PM':'AM'; h=h%12||12;
    var el=document.getElementById('liveClock');
    if(el) el.textContent=pad(h)+':'+pad(m)+':'+pad(s)+' '+ap;
    setTimeout(tick,1000);
})();
function pad(n){ return n<10?'0'+n:n; }

// ── Camera state ────────────────────────────────────────────────
var camStream    = null;
var capturedB64  = null;
var geoLat=null, geoLng=null, geoAccuracy=null, geoAddr=null;

function initCamera() {
    var video   = document.getElementById('camVideo');
    var overlay = document.getElementById('camOverlay');
    var errBox  = document.getElementById('cameraErrBox');
    var errMsg  = document.getElementById('cameraErrMsg');
    var camNote = document.getElementById('camNote');

    // ── PRIMARY: modern getUserMedia ────────────────────────────
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode:'user' }, audio:false })
            .then(function(stream) {
                camStream = stream;
                video.srcObject = stream;
                video.style.display = 'block';
                overlay.style.display = 'none';
                errBox.style.display = 'none';
                camNote.textContent = 'Camera ready — selfie will be taken on clock action.';
            })
            .catch(function(err) {
                handleCamError(err, errBox, errMsg, overlay, camNote);
            });
        return;
    }

    // ── FALLBACK: legacy getUserMedia (older Android browsers) ─
    var legacyGUM = navigator.getUserMedia
                 || navigator.webkitGetUserMedia
                 || navigator.mozGetUserMedia
                 || navigator.msGetUserMedia;

    if (legacyGUM) {
        legacyGUM.call(navigator,
            { video: true },
            function(stream) {
                camStream = stream;
                try { video.srcObject = stream; }
                catch(e) { video.src = window.URL.createObjectURL(stream); }
                video.style.display = 'block';
                overlay.style.display = 'none';
                errBox.style.display = 'none';
                camNote.textContent = 'Camera ready.';
            },
            function(err) { handleCamError(err, errBox, errMsg, overlay, camNote); }
        );
        return;
    }

    // ── NO CAMERA API AT ALL ────────────────────────────────────
    handleCamError(
        { name:'NotSupportedError',
          message: location.protocol === 'http:'
            ? 'Camera requires HTTPS. Running on HTTP — camera blocked by browser.'
            : 'Camera API not supported in this browser.' },
        errBox, errMsg, overlay, camNote
    );
}

function handleCamError(err, errBox, errMsg, overlay, camNote) {
    var msg = '';
    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        msg = 'Permission denied. Click the camera icon in your browser\'s address bar and allow camera access, then refresh.';
    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        msg = 'No camera found on this device.';
    } else if (err.name === 'NotReadableError') {
        msg = 'Camera is in use by another app. Close other apps and refresh.';
    } else if (location.protocol === 'http:') {
        msg = 'Camera requires a secure (HTTPS) connection. Currently on HTTP — ask IT to enable HTTPS for full camera support.';
    } else {
        msg = (err.message || err.name || 'Unknown error');
    }
    errMsg.textContent = ' ' + msg;
    errBox.style.display = 'block';
    overlay.querySelector('#camStatus').textContent = 'Camera unavailable';
    camNote.style.display = 'none';
}

function capturePhoto() {
    var video = document.getElementById('camVideo');
    var canvas= document.getElementById('camCanvas');
    if (!camStream || !video.srcObject) return null;
    canvas.width  = video.videoWidth  || 480;
    canvas.height = video.videoHeight || 360;
    var ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    // Timestamp watermark
    ctx.fillStyle='rgba(0,0,0,0.5)';
    ctx.fillRect(0, canvas.height-28, canvas.width, 28);
    ctx.fillStyle='#fff'; ctx.font='11px monospace';
    ctx.fillText(new Date().toLocaleString('en-PH'), 6, canvas.height-10);
    capturedB64 = canvas.toDataURL('image/jpeg', 0.75);
    // Show preview
    var prev = document.getElementById('photoPreview');
    prev.src = capturedB64;
    prev.style.display = 'block';
    video.style.display = 'none';
    document.getElementById('retakeBtn').style.display = 'inline-block';
    return capturedB64;
}

function retakePhoto() {
    capturedB64 = null;
    document.getElementById('photoPreview').style.display = 'none';
    document.getElementById('camVideo').style.display = 'block';
    document.getElementById('retakeBtn').style.display = 'none';
}

// ── Geolocation ─────────────────────────────────────────────────
function getGeo() {
    document.getElementById('geoStatus').textContent = 'Getting location...';
    if (!navigator.geolocation) {
        document.getElementById('geoStatus').textContent = 'Geolocation not supported.';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            geoLat=pos.coords.latitude; geoLng=pos.coords.longitude; geoAccuracy=pos.coords.accuracy;
            document.getElementById('geoStatus').innerHTML =
                '<i class="fas fa-check-circle text-success mr-1"></i>Location captured.';
            document.getElementById('geoCoords').textContent =
                geoLat.toFixed(5)+', '+geoLng.toFixed(5)+' (±'+Math.round(geoAccuracy)+'m)';
            // Reverse geocode
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+geoLat+'&lon='+geoLng)
                .then(function(r){ return r.json(); })
                .then(function(d){ geoAddr=d.display_name||''; })
                .catch(function(){});
        },
        function(err) {
            document.getElementById('geoStatus').textContent = 'Error: '+err.message;
        },
        { enableHighAccuracy:true, timeout:12000 }
    );
}

// ── Clock AJAX ──────────────────────────────────────────────────
function doClockAjax(action) {
    // Capture photo if camera is running
    if (!capturedB64 && camStream) capturePhoto();

    document.getElementById('btnIn').disabled  = true;
    document.getElementById('btnOut').disabled = true;
    document.getElementById('clockSpinner').style.display = 'block';
    document.getElementById('clockResult').style.display  = 'none';

    var fd = new FormData();
    fd.append('ajax_clock', '1');
    fd.append('action', action);
    fd.append('csrf_token', CSRF_TOKEN);
    if (capturedB64) fd.append('photo_b64', capturedB64.split(',')[1] || capturedB64);
    if (geoLat)      fd.append('lat', geoLat);
    if (geoLng)      fd.append('lng', geoLng);
    if (geoAccuracy) fd.append('accuracy', geoAccuracy);
    if (geoAddr)     fd.append('address', geoAddr);
    fd.append('device', navigator.userAgent.substring(0,200));

    fetch('attendance_selfservice.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            document.getElementById('clockSpinner').style.display = 'none';
            var box = document.getElementById('clockResult');
            box.style.display  = 'block';
            box.className      = 'alert ' + (data.ok ? 'alert-success' : 'alert-danger');
            box.textContent    = data.msg;
            if (data.ok) {
                setTimeout(function(){ window.location.reload(); }, 2200);
            } else {
                document.getElementById('btnIn').disabled  = false;
                document.getElementById('btnOut').disabled = false;
            }
        })
        .catch(function() {
            document.getElementById('clockSpinner').style.display = 'none';
            var box = document.getElementById('clockResult');
            box.style.display = 'block';
            box.className     = 'alert alert-danger';
            box.textContent   = 'Network error. Please try again.';
            document.getElementById('btnIn').disabled  = false;
            document.getElementById('btnOut').disabled = false;
        });
}

// ── Init camera when clock tab is shown ─────────────────────────
$('a[href="#tab-clock"]').on('shown.bs.tab', function(){
    if (!camStream) initCamera();
    getGeo();
});

// ── Init DataTable for attendance (explicit, no dt-export) ──────
$(function(){
    if ($.fn.DataTable && $('#myAttTable').length) {
        $('#myAttTable').DataTable({
            pageLength : 25,
            order      : [[0, 'desc']],
            dom        : 'lfrtip'   // NO Buttons — avoids footer.php conflict
        });
    }
});
</script>
</body>
</html>