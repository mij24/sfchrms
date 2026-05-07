<?php
// ============================================
// FILE: pages/my_attendance.php — FINAL
// Single page, 2 tabs: View Attendance | Clock In/Out
// attendance_selfservice.php handles AJAX clock actions
// White page fix: removed dt-export class, added csrf.php
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

// ── Not linked ──────────────────────────────────────────────────
if (!$emp_id) {
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
<div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    Your account is not linked to an employee record. Contact HR.
  </div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
</div></body></html><?php
    exit;
}

// ── Load employee data ──────────────────────────────────────────
$emp = db_fetch($pdo,
    "SELECT e.full_name, e.employee_id FROM employees WHERE id=?",
    array($emp_id)
);

// ── Attendance query ────────────────────────────────────────────
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

// ── Today's record ──────────────────────────────────────────────
$today_att = db_fetch($pdo,
    "SELECT * FROM attendance WHERE employee_id=? AND attendance_date=CURDATE()",
    array($emp_id)
);

// ── QR code ─────────────────────────────────────────────────────
$my_qr = db_fetch($pdo,
    "SELECT * FROM employee_qr_codes WHERE employee_id=? AND is_active=1 LIMIT 1",
    array($emp_id)
);

// ── Helpers ─────────────────────────────────────────────────────
$months_list = array(
    1=>'January', 2=>'February', 3=>'March',    4=>'April',
    5=>'May',     6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October',11=>'November',12=>'December'
);
$stat_badge = array(
    'Present'  =>'success','Absent'  =>'danger','Late'    =>'warning',
    'Half-day' =>'info',   'On leave'=>'primary','Holiday'=>'secondary','Rest day'=>'dark'
);
$color_map = array(
    'Present'  =>'#28a745','Absent'  =>'#dc3545','Late'    =>'#ffc107',
    'Half-day' =>'#17a2b8','On leave'=>'#007bff','Holiday' =>'#6c757d','Rest day'=>'#343a40'
);

$csrf_tok = csrf_token(); // For AJAX
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <!-- NOTE: NO datatables CSS here — we use a plain table below -->
  <style>
    /* ── Tab bar ── */
    .att-tabbar { background:#fff; border-bottom:2px solid #dee2e6; margin-bottom:0; }
    .att-tabbar .nav-link {
      color:#6c757d; font-size:13px; font-weight:600;
      padding:13px 22px; border:none; border-bottom:3px solid transparent;
      border-radius:0; transition:color .15s;
    }
    .att-tabbar .nav-link.active { color:#1a73e8; border-bottom-color:#1a73e8; background:none; }
    .att-tabbar .nav-link:hover  { color:#1a73e8; }

    /* ── Clock tile ── */
    .clock-tile {
      background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);
      border-radius:16px; padding:28px 24px 22px; color:#fff; text-align:center;
    }
    .clock-tile .ct-time  { font-size:56px; font-weight:200; letter-spacing:4px; line-height:1; }
    .clock-tile .ct-date  { font-size:13px; opacity:.6; margin-top:6px; }
    .clock-tile .ct-emp   { font-size:12px; opacity:.4; margin-top:4px; }
    .ts-row   { display:flex; gap:10px; margin-top:16px; }
    .ts-box   { flex:1; background:rgba(255,255,255,.08); border-radius:10px; padding:12px 8px; }
    .ts-lbl   { font-size:10px; text-transform:uppercase; letter-spacing:.05em; opacity:.55; }
    .ts-val   { font-size:22px; font-weight:700; margin-top:3px; }
    .ts-sub   { font-size:10px; margin-top:2px; }

    /* ── Buttons ── */
    .btn-ci, .btn-co {
      width:100%; border:0; font-weight:700; font-size:15px;
      padding:14px; border-radius:14px; cursor:pointer; transition:opacity .15s;
    }
    .btn-ci { background:#00c853; color:#fff; }
    .btn-ci:hover  { opacity:.88; }
    .btn-ci:disabled { background:#a5d6a7; cursor:not-allowed; }
    .btn-co { background:#f44336; color:#fff; }
    .btn-co:hover  { opacity:.88; }
    .btn-co:disabled { background:#ef9a9a; cursor:not-allowed; }

    /* ── Camera ── */
    .cam-wrap {
      position:relative; background:#111; border-radius:14px;
      overflow:hidden; aspect-ratio:4/3; max-height:260px;
    }
    .cam-wrap video { width:100%; height:100%; object-fit:cover; display:none; }
    .cam-overlay {
      position:absolute; inset:0; display:flex; flex-direction:column;
      align-items:center; justify-content:center;
      color:#fff; font-size:13px; text-align:center; padding:16px;
    }
    .geo-box { background:#f8f9fa; border-radius:10px; padding:10px 14px; font-size:12px; }

    /* ── Calendar grid ── */
    .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; text-align:center; }
    .cal-day-header { font-size:11px; font-weight:700; color:#6c757d; padding:4px 0; }
    .cal-day {
      border-radius:6px; padding:6px 2px; font-size:12px;
      line-height:1.2; transition:transform .1s;
    }
    .cal-day:hover { transform:scale(1.08); }

    /* ── Simple table (no DataTable) ── */
    #attDetailTable th, #attDetailTable td { font-size:12px; vertical-align:middle; }
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

  <div class="content">
    <div class="container-fluid">

      <!-- ══ TAB BAR ══ -->
      <div class="card mb-0" style="border-radius:10px 10px 0 0; border-bottom:0;">
        <div class="card-body p-0">
          <div class="att-tabbar">
            <ul class="nav" id="attTabBar">
              <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#pane-view">
                  <i class="fas fa-calendar-alt mr-1"></i> View Attendance
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#pane-clock">
                  <i class="fas fa-clock mr-1"></i> Clock In / Out
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ══ TAB CONTENT ══ -->
      <div class="tab-content" style="background:#f4f6f9; padding:20px 0 30px;">

        <!-- ─────────────────────────────────────────
             PANE 1 — VIEW ATTENDANCE
        ───────────────────────────────────────── -->
        <div class="tab-pane fade show active" id="pane-view">
          <div class="container-fluid">

            <!-- Month/year filter -->
            <div class="card mb-3">
              <div class="card-body py-2">
                <form method="GET" action="" class="form-inline" style="gap:10px;">
                  <label class="font-weight-bold mr-1" style="font-size:13px;">Month:</label>
                  <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                    <?php foreach ($months_list as $mn => $ml): ?>
                      <option value="<?= $mn ?>" <?= $mn===$month?'selected':'' ?>><?= $ml ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                    <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-3; $y--): ?>
                      <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                  <span class="text-muted ml-2" style="font-size:12px;"><?= count($records) ?> records</span>
                </form>
              </div>
            </div>

            <!-- Summary cards -->
            <div class="row mb-3">
              <?php
              $cards = array(
                array('Present',         (int)($summary['present']??0),                   'success'),
                array('Absent',          (int)($summary['absent']??0),                    'danger'),
                array('Late',            (int)($summary['late']??0),                      'warning'),
                array('Half-day',        (int)($summary['halfday']??0),                   'info'),
                array('Tardiness (min)', (int)($summary['total_tardiness']??0),           'secondary'),
                array('Overtime (hrs)',  number_format((float)($summary['total_overtime']??0),2), 'primary'),
              );
              foreach ($cards as $c): ?>
              <div class="col-6 col-md-2 mb-2">
                <div class="card mb-0 text-center">
                  <div class="card-body py-2 px-1">
                    <div style="font-size:22px;font-weight:700;"><?= $c[1] ?></div>
                    <div style="font-size:11px;color:#6c757d;"><?= $c[0] ?></div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Calendar -->
            <div class="card mb-3">
              <div class="card-header">
                <h3 class="card-title"><?= $months_list[$month] ?> <?= $year ?> — Attendance Overview</h3>
              </div>
              <div class="card-body">
                <?php
                $date_map      = array();
                foreach ($records as $r) $date_map[$r['attendance_date']] = $r;
                $days_in_month = (int)date('t', mktime(0,0,0,$month,1,$year));
                $first_dow     = (int)date('w', mktime(0,0,0,$month,1,$year));
                ?>
                <div class="cal-grid">
                  <?php foreach (array('Sun','Mon','Tue','Wed','Thu','Fri','Sat') as $d): ?>
                    <div class="cal-day-header"><?= $d ?></div>
                  <?php endforeach; ?>
                  <?php for ($i=0;$i<$first_dow;$i++): ?><div></div><?php endfor; ?>
                  <?php for ($day=1;$day<=$days_in_month;$day++):
                    $ds  = sprintf('%04d-%02d-%02d',$year,$month,$day);
                    $rec = isset($date_map[$ds]) ? $date_map[$ds] : null;
                    $bg  = $rec ? (isset($color_map[$rec['status']]) ? $color_map[$rec['status']] : '#e9ecef') : '#f8f9fa';
                    $fc  = $rec ? '#fff' : '#adb5bd';
                    $itd = ($ds === date('Y-m-d'));
                  ?>
                  <div class="cal-day"
                       title="<?= $rec ? htmlspecialchars($rec['status']) : $ds ?>"
                       style="background:<?= $bg ?>;color:<?= $fc ?>;
                              <?= $itd ? 'border:2px solid #007bff;font-weight:800;' : '' ?>">
                    <?= $day ?>
                    <?php if ($rec && $rec['tardiness_minutes'] > 0): ?>
                      <div style="font-size:9px;opacity:.8;"><?= $rec['tardiness_minutes'] ?>m</div>
                    <?php endif; ?>
                  </div>
                  <?php endfor; ?>
                </div>
                <!-- Legend -->
                <div class="d-flex flex-wrap mt-3" style="gap:10px;">
                  <?php foreach ($color_map as $lbl => $col): ?>
                    <div style="display:flex;align-items:center;gap:5px;font-size:12px;">
                      <div style="width:12px;height:12px;border-radius:3px;background:<?= $col ?>;"></div>
                      <?= $lbl ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Daily Time Log table — plain table, NO dt-export to avoid footer.php conflict -->
            <div class="card">
              <div class="card-header"><h3 class="card-title">Daily Time Log</h3></div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered table-hover mb-0" id="attDetailTable">
                    <thead class="thead-light">
                      <tr>
                        <th>Date</th><th>Day</th>
                        <th>Time In</th><th>Time Out</th>
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
                          <td><?= date('D', strtotime($r['attendance_date'])) ?></td>
                          <td><?= $ti ?></td>
                          <td><?= $to ?></td>
                          <td><span class="badge badge-<?= $sb ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                          <td><?= $r['tardiness_minutes'] > 0 ? $r['tardiness_minutes'].' min' : '—' ?></td>
                          <td><?= $r['overtime_hours'] > 0    ? $r['overtime_hours'].' hrs'    : '—' ?></td>
                          <td><?= htmlspecialchars($r['remarks'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="8" class="text-center text-muted py-4">

                            <i class="fas fa-calendar-times fa-2x d-block mb-2" style="opacity:.2;"></i>
                            No records for <?= $months_list[$month] ?> <?= $year ?>.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>
        </div><!-- /pane-view -->

        <!-- ─────────────────────────────────────────
             PANE 2 — CLOCK IN / OUT
        ───────────────────────────────────────── -->
        <div class="tab-pane fade" id="pane-clock">
          <div class="container-fluid">
            <div class="row justify-content-center">
              <div class="col-md-7 col-lg-5">

                <!-- Live clock tile -->
                <div class="clock-tile mb-3">
                  <div class="ct-time" id="ctTime"><?= date('h:i:s A') ?></div>
                  <div class="ct-date"><?= date('l, F d, Y') ?></div>
                  <div class="ct-emp"><?= htmlspecialchars($emp['full_name']) ?> &middot; <?= htmlspecialchars($emp['employee_id']) ?></div>
                  <!-- Today's In/Out status -->
                  <div class="ts-row">
                    <div class="ts-box">
                      <div class="ts-lbl">Time In</div>
                      <div class="ts-val">
                        <?= ($today_att && $today_att['time_in'] && $today_att['time_in'] !== '00:00:00')
                            ? date('h:i A', strtotime($today_att['time_in'])) : '—' ?>
                      </div>
                      <?php if ($today_att && (int)$today_att['tardiness_minutes'] > 0): ?>
                        <div class="ts-sub" style="color:#ff6b6b;"><?= $today_att['tardiness_minutes'] ?> min late</div>
                      <?php endif; ?>
                    </div>
                    <div class="ts-box">
                      <div class="ts-lbl">Time Out</div>
                      <div class="ts-val">
                        <?= ($today_att && $today_att['time_out'] && $today_att['time_out'] !== '00:00:00')
                            ? date('h:i A', strtotime($today_att['time_out'])) : '—' ?>
                      </div>
                      <?php if ($today_att && (float)$today_att['overtime_hours'] > 0): ?>
                        <div class="ts-sub" style="color:#69f0ae;"><?= $today_att['overtime_hours'] ?>h OT</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Camera selfie card -->
                <div class="card mb-3">
                  <div class="card-header"><h3 class="card-title"><i class="fas fa-camera mr-1"></i> Selfie Clock</h3></div>
                  <div class="card-body">

                    <!-- Camera error box -->
                    <div id="camErrBox" class="alert alert-warning" style="display:none;font-size:12px;">
                      <i class="fas fa-exclamation-triangle mr-1"></i>
                      <strong>Camera unavailable.</strong> <span id="camErrMsg"></span>
                      <br><small>You can still clock in/out without a selfie.
                      To enable camera: allow permission in browser → address bar camera icon → Allow.
                      On mobile: Settings → Browser → Permissions → Camera → Allow.
                      Note: Camera requires HTTPS — on plain HTTP it may be blocked by the browser.</small>
                    </div>

                    <!-- Camera preview -->
                    <div class="cam-wrap mb-3" id="camWrap">
                      <video id="camVideo" autoplay playsinline muted></video>
                      <canvas id="camCanvas" style="display:none;"></canvas>
                      <div class="cam-overlay" id="camOverlay">
                        <i class="fas fa-camera fa-3x mb-2" style="opacity:.25;display:block;"></i>
                        <div id="camStatus">Initializing camera...</div>
                      </div>
                    </div>

                    <!-- Captured photo preview -->
                    <img id="photoPreview" src="" alt="Selfie"
                         style="display:none;width:100%;max-height:200px;object-fit:cover;
                                border-radius:10px;border:2px solid #28a745;margin-bottom:10px;">

                    <!-- Retake / note row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div id="camNote" style="font-size:11px;color:#6c757d;flex:1;">
                        Selfie will be captured automatically when you clock in/out.
                      </div>
                      <button type="button" id="retakeBtn" class="btn btn-xs btn-outline-secondary ml-2"
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

                    <!-- AJAX feedback -->
                    <div id="clockResult" class="alert" style="display:none;font-size:13px;"></div>
                    <div id="clockSpinner" style="display:none;text-align:center;padding:8px 0;font-size:13px;color:#555;">
                      <i class="fas fa-spinner fa-spin mr-1"></i> Processing...
                    </div>

                    <!-- Clock buttons -->
                    <div class="row mt-1">
                      <div class="col-6 pr-1">
                        <button type="button" class="btn-ci" id="btnIn"
                                <?= ($today_att && $today_att['time_in']) ? 'disabled' : '' ?>
                                onclick="doClockAction('in')">
                          <i class="fas fa-sign-in-alt mr-1"></i> Clock In
                        </button>
                      </div>
                      <div class="col-6 pl-1">
                        <button type="button" class="btn-co" id="btnOut"
                                <?= (!$today_att || !$today_att['time_in'] || $today_att['time_out']) ? 'disabled' : '' ?>
                                onclick="doClockAction('out')">
                          <i class="fas fa-sign-out-alt mr-1"></i> Clock Out
                        </button>
                      </div>
                    </div>

                  </div>
                </div><!-- /camera card -->

                <!-- QR alternative -->
                <?php if ($my_qr):
                  $qr_data = urlencode('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/qr_scanner.php?token='.$my_qr['qr_token']);
                  $qr_url  = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl='.$qr_data.'&choe=UTF-8';
                ?>
                <div class="card">
                  <div class="card-header"><h3 class="card-title"><i class="fas fa-qrcode mr-1"></i> QR Code Alternative</h3></div>
                  <div class="card-body text-center">
                    <img src="<?= $qr_url ?>" style="width:130px;height:130px;" alt="My QR">
                    <div style="font-size:12px;color:#6c757d;margin-top:8px;">
                      Scan this at the attendance kiosk to clock in/out.
                    </div>
                  </div>
                </div>
                <?php endif; ?>

              </div>
            </div>
          </div>
        </div><!-- /pane-clock -->

      </div><!-- /tab-content -->
    </div><!-- /container-fluid -->
  </div><!-- /content -->
</div><!-- /content-wrapper -->

<?php include '../includes/footer.php'; ?>

<script>
/* ── CSRF token for AJAX ──────────────────────────────── */
var CSRF_TOKEN = '<?= htmlspecialchars($csrf_tok, ENT_QUOTES) ?>';

/* ── Live clock (ticks every second) ─────────────────── */
(function tick() {
    var d = new Date(), h = d.getHours(), m = d.getMinutes(), s = d.getSeconds();
    var ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    var el = document.getElementById('ctTime');
    if (el) el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s) + ' ' + ap;
    setTimeout(tick, 1000);
})();
function pad(n) { return n < 10 ? '0' + n : '' + n; }

/* ── Camera ────────────────────────────────────────────── */
var camStream   = null;
var capturedB64 = null;
var geoLat = null, geoLng = null, geoAccuracy = null, geoAddr = null;

function initCamera() {
    var video   = document.getElementById('camVideo');
    var overlay = document.getElementById('camOverlay');
    var errBox  = document.getElementById('camErrBox');
    var errMsg  = document.getElementById('camErrMsg');
    var camNote = document.getElementById('camNote');

    /* Modern API */
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
            .then(function(stream) {
                camStream = stream;
                video.srcObject = stream;
                video.style.display = 'block';
                overlay.style.display = 'none';
                errBox.style.display = 'none';
                camNote.textContent = 'Camera ready — selfie captured on clock action.';
            })
            .catch(function(err) { handleCamErr(err); });
        return;
    }

    /* Legacy fallback (old Android Chrome) */
    var legacyGUM = navigator.getUserMedia
                 || navigator.webkitGetUserMedia
                 || navigator.mozGetUserMedia;
    if (legacyGUM) {
        legacyGUM.call(navigator, { video: true },
            function(stream) {
                camStream = stream;
                try { video.srcObject = stream; } catch(e) { video.src = URL.createObjectURL(stream); }
                video.style.display = 'block';
                overlay.style.display = 'none';
                errBox.style.display = 'none';
                camNote.textContent = 'Camera ready.';
            },
            function(err) { handleCamErr(err); }
        );
        return;
    }

    /* No camera API at all */
    handleCamErr({ name: 'NotSupportedError' });
}

function handleCamErr(err) {
    var errBox = document.getElementById('camErrBox');
    var errMsg = document.getElementById('camErrMsg');
    var overlay= document.getElementById('camOverlay');
    var camNote= document.getElementById('camNote');
    var msg = '';

    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        msg = ' Permission denied — click the camera icon in your browser address bar and choose Allow.';
    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        msg = ' No camera found on this device.';
    } else if (err.name === 'NotReadableError') {
        msg = ' Camera is in use by another app. Close it and refresh.';
    } else if (location.protocol === 'http:') {
        msg = ' Camera requires HTTPS. System is running on HTTP — ask IT to enable HTTPS.';
    } else {
        msg = ' ' + (err.message || err.name || 'Unknown error.');
    }
    errMsg.textContent = msg;
    errBox.style.display = 'block';
    if (overlay) overlay.querySelector('#camStatus').textContent = 'Camera unavailable';
    if (camNote) camNote.style.display = 'none';
}

function capturePhoto() {
    var video  = document.getElementById('camVideo');
    var canvas = document.getElementById('camCanvas');
    if (!camStream || !video.srcObject) return null;
    canvas.width  = video.videoWidth  || 480;
    canvas.height = video.videoHeight || 360;
    var ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    /* Timestamp watermark */
    ctx.fillStyle = 'rgba(0,0,0,.55)';
    ctx.fillRect(0, canvas.height - 26, canvas.width, 26);
    ctx.fillStyle = '#fff'; ctx.font = '11px monospace';
    ctx.fillText(new Date().toLocaleString('en-PH'), 6, canvas.height - 8);
    capturedB64 = canvas.toDataURL('image/jpeg', 0.75);
    var prev = document.getElementById('photoPreview');
    prev.src = capturedB64; prev.style.display = 'block';
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

/* ── Geolocation ────────────────────────────────────── */
function getGeo() {
    var el = document.getElementById('geoStatus');
    el.textContent = 'Getting location…';
    if (!navigator.geolocation) { el.textContent = 'Geolocation not supported.'; return; }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            geoLat = pos.coords.latitude; geoLng = pos.coords.longitude;
            geoAccuracy = pos.coords.accuracy;
            el.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i>Location captured.';
            document.getElementById('geoCoords').textContent =
                geoLat.toFixed(5) + ', ' + geoLng.toFixed(5) + ' (±' + Math.round(geoAccuracy) + 'm)';
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + geoLat + '&lon=' + geoLng)
                .then(function(r) { return r.json(); })
                .then(function(d) { geoAddr = d.display_name || ''; })
                .catch(function() {});
        },
        function(err) { el.textContent = 'Error: ' + err.message; },
        { enableHighAccuracy: true, timeout: 12000 }
    );
}

/* ── Clock AJAX ─────────────────────────────────────── */
function doClockAction(action) {
    if (!capturedB64 && camStream) capturePhoto();

    document.getElementById('btnIn').disabled  = true;
    document.getElementById('btnOut').disabled = true;
    document.getElementById('clockSpinner').style.display = 'block';
    document.getElementById('clockResult').style.display  = 'none';

    var fd = new FormData();
    fd.append('ajax_clock',  '1');
    fd.append('action',      action);
    fd.append('csrf_token',  CSRF_TOKEN);
    if (capturedB64) fd.append('photo_b64', capturedB64.replace(/^data:image\/\w+;base64,/, ''));
    if (geoLat)      fd.append('lat',       geoLat);
    if (geoLng)      fd.append('lng',       geoLng);
    if (geoAccuracy) fd.append('accuracy',  geoAccuracy);
    if (geoAddr)     fd.append('address',   geoAddr);
    fd.append('device', navigator.userAgent.substring(0, 200));

    fetch('attendance_selfservice.php', { method: 'POST', body: fd })
        .then(function(r) {
            /* attendance_selfservice.php might return HTML on error — guard it */
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('json') === -1) {
                return r.text().then(function(t) {
                    throw new Error('Server returned non-JSON: ' + t.substring(0, 80));
                });
            }
            return r.json();
        })
        .then(function(data) {
            document.getElementById('clockSpinner').style.display = 'none';
            var box = document.getElementById('clockResult');
            box.style.display = 'block';
            box.className     = 'alert ' + (data.ok ? 'alert-success' : 'alert-danger');
            box.textContent   = data.msg;
            if (data.ok) {
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                document.getElementById('btnIn').disabled  = false;
                document.getElementById('btnOut').disabled = false;
            }
        })
        .catch(function(e) {
            document.getElementById('clockSpinner').style.display = 'none';
            var box = document.getElementById('clockResult');
            box.style.display = 'block';
            box.className     = 'alert alert-danger';
            box.textContent   = 'Error: ' + e.message;
            document.getElementById('btnIn').disabled  = false;
            document.getElementById('btnOut').disabled = false;
        });
}

/* ── Init camera when Clock tab shown ───────────────── */
$('a[href="#pane-clock"]').on('shown.bs.tab', function() {
    if (!camStream) { initCamera(); getGeo(); }
});

/* ── On DOM ready ───────────────────────────────────── */
$(function() {
    /* Simple search for the attendance table (no DataTable to avoid footer conflict) */
    var tbl = document.getElementById('attDetailTable');
    if (tbl) {
        var input = document.createElement('input');
        input.type = 'text'; input.placeholder = 'Search records…';
        input.className = 'form-control form-control-sm mb-2';
        input.style.maxWidth = '220px';
        tbl.parentNode.insertBefore(input, tbl);
        input.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            var rows = tbl.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>