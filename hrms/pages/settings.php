<?php
// ============================================
// FILE: pages/settings.php
// Tabs: Company | Payroll | Time & Attendance | Security | Email
// TIME TAB:
//   - Timezone selector (Asia/Manila default)
//   - Manual clock adjuster (±seconds, minutes,
//     hours, days, months, years)
//   - All stored as clock_delta_seconds in DB
//   - Applied on top of timezone in all clock files
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_ADMIN);

// ── Apply saved timezone + delta immediately ─────────────────
$_saved_tz    = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='system_timezone' LIMIT 1") ?: 'Asia/Manila';
$_clock_delta = (int)(db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='clock_delta_seconds' LIMIT 1") ?: 0);
try   { date_default_timezone_set($_saved_tz); }
catch (Exception $e) { date_default_timezone_set('Asia/Manila'); }

// Helper: get current HRMS time (timezone + delta)
function hrms_now() {
    global $_clock_delta;
    return time() + $_clock_delta;
}

// Load all settings
$raw = db_fetchall($pdo, "SELECT setting_key, setting_value FROM settings");
$s   = array();
foreach ($raw as $r) $s[$r['setting_key']] = $r['setting_value'];

function sv($s, $key, $default = '') {
    return htmlspecialchars(isset($s[$key]) ? $s[$key] : $default);
}
function save_keys(PDO $pdo, array $keys, array $post) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $post)) {
            $val = trim($post[$key]);
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute(array($key,$val,$val));
        }
    }
}

// ── GET reset handler ────────────────────────────────────────────
if (isset($_GET['do_reset']) && $_GET['do_reset'] === '1') {
    $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('clock_delta_seconds','0')
                   ON DUPLICATE KEY UPDATE setting_value='0'")->execute();
    audit_log($pdo, 'UPDATE', 'settings', 0, array('action'=>'reset_clock'));
    prg_redirect('settings.php?tab=time', 'System clock reset to real server time.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $section = isset($_POST['section']) ? $_POST['section'] : '';

    // ── COMPANY ──────────────────────────────────────────────
    if ($section === 'company') {
        save_keys($pdo, array('company_name','company_address','company_phone','company_email'), $_POST);
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg','jpeg','png','gif'))) {
                $dir = '../uploads/logos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $file = 'logo_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $dir.$file))
                    $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('company_logo',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(array($file,$file));
            }
        }
        prg_redirect('settings.php?tab=company', 'Company information saved.');
    }

    // ── PAYROLL ───────────────────────────────────────────────
    if ($section === 'payroll') {
        save_keys($pdo, array('payroll_cutoff_1','payroll_cutoff_2','working_days_month','working_hours_day',
            'overtime_rate','night_diff_rate','rest_day_rate','holiday_rate','special_holiday_rate'), $_POST);
        prg_redirect('settings.php?tab=payroll', 'Payroll configuration saved.');
    }

    // ── SECURITY ──────────────────────────────────────────────
    if ($section === 'security') {
        save_keys($pdo, array('session_timeout','max_login_attempts','lockout_minutes'), $_POST);
        prg_redirect('settings.php?tab=security', 'Security settings saved.');
    }

    // ── EMAIL ─────────────────────────────────────────────────
    if ($section === 'email') {
        save_keys($pdo, array('smtp_host','smtp_port','smtp_username','smtp_from_name','smtp_from_email'), $_POST);
        if (!empty($_POST['smtp_password'])) {
            $pw = trim($_POST['smtp_password']);
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('smtp_password',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(array($pw,$pw));
        }
        prg_redirect('settings.php?tab=email', 'Email settings saved.');
    }

    // ── TIME & ATTENDANCE ─────────────────────────────────────
    if ($section === 'time') {
        // 1. Timezone
        $tz = isset($_POST['system_timezone']) ? trim($_POST['system_timezone']) : 'Asia/Manila';
        try { new DateTimeZone($tz); } catch(Exception $e) { $tz = 'Asia/Manila'; }

        // Existing saved delta (preserved unless overridden below)
        $existing_delta = (int)(db_value($pdo,
            "SELECT setting_value FROM settings WHERE setting_key='clock_delta_seconds' LIMIT 1"
        ) ?: 0);
        $new_delta = $existing_delta; // default: keep existing

        // Manual datetime — user picked a specific date/time, compute delta
        if (!empty($_POST['manual_datetime'])) {
            $manual_dt = trim($_POST['manual_datetime']); // Y-m-d H:i:s
            date_default_timezone_set($tz);
            $manual_ts = strtotime($manual_dt);
            if ($manual_ts) {
                $new_delta = $manual_ts - time();
            }
        }

        // Save everything
        $keys_vals = array(
            'system_timezone'       => $tz,
            'clock_delta_seconds'   => $new_delta,
            'clock_offset_minutes'  => 0, // legacy, we use delta now
            'shift_start_default'   => isset($_POST['shift_start_default'])   ? trim($_POST['shift_start_default'])   : '08:00',
            'shift_end_non_teaching'=> isset($_POST['shift_end_non_teaching']) ? trim($_POST['shift_end_non_teaching']): '17:00',
            'shift_start_basic_ed'  => isset($_POST['shift_start_basic_ed'])   ? trim($_POST['shift_start_basic_ed'])  : '07:00',
            'shift_end_basic_ed'    => isset($_POST['shift_end_basic_ed'])      ? trim($_POST['shift_end_basic_ed'])    : '16:00',
            'shift_start_college'   => isset($_POST['shift_start_college'])     ? trim($_POST['shift_start_college'])   : '07:00',
            'grace_minutes'         => isset($_POST['grace_minutes'])           ? (int)$_POST['grace_minutes']          : 15,
            'lunch_start'           => isset($_POST['lunch_start'])             ? trim($_POST['lunch_start'])            : '12:00',
            'lunch_end'             => isset($_POST['lunch_end'])               ? trim($_POST['lunch_end'])              : '13:00',
        );
        foreach ($keys_vals as $k => $v) {
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute(array($k,$v,$v));
        }

        // Audit log using the correct db.php function
        audit_log($pdo, 'UPDATE', 'settings', 0, array(
            'timezone'       => $tz,
            'delta_seconds'  => $new_delta,
            'delta_human'    => ($new_delta != 0 ? $new_delta.'s' : 'reset'),
        ));

        date_default_timezone_set($tz);
        $new_ts   = time() + $new_delta;
        $new_time = date('h:i:s A', $new_ts);
        prg_redirect('settings.php?tab=time', 'System clock updated. Current HRMS time: '.$new_time);
    }
}

// Reload after PRG
$raw = db_fetchall($pdo,"SELECT setting_key, setting_value FROM settings");
$s   = array();
foreach ($raw as $r) $s[$r['setting_key']] = $r['setting_value'];

// Re-apply after reload
$_saved_tz    = isset($s['system_timezone'])     ? $s['system_timezone']     : 'Asia/Manila';
$_clock_delta = isset($s['clock_delta_seconds']) ? (int)$s['clock_delta_seconds'] : 0;
try   { date_default_timezone_set($_saved_tz); }
catch (Exception $e) { date_default_timezone_set('Asia/Manila'); }

$active_tab  = isset($_GET['tab']) ? $_GET['tab'] : 'company';

// Compute current HRMS time values for display
$hrms_ts     = time() + $_clock_delta;
$hrms_time   = date('h:i:s A', $hrms_ts);
$hrms_date   = date('l, F d, Y', $hrms_ts);
$hrms_raw    = date('Y-m-d H:i:s', $hrms_ts);
$real_time   = date('h:i:s A');  // server time without delta
$delta_human = '';
if ($_clock_delta != 0) {
    $abs     = abs($_clock_delta);
    $sign    = $_clock_delta > 0 ? 'ahead' : 'behind';
    $y       = floor($abs / (365*86400)); $abs -= $y*365*86400;
    $mo      = floor($abs / (30*86400));  $abs -= $mo*30*86400;
    $d       = floor($abs / 86400);       $abs -= $d*86400;
    $h       = floor($abs / 3600);        $abs -= $h*3600;
    $m       = floor($abs / 60);
    $sec     = $abs % 60;
    $parts   = array();
    if ($y)   $parts[] = $y.'y';
    if ($mo)  $parts[] = $mo.'mo';
    if ($d)   $parts[] = $d.'d';
    if ($h)   $parts[] = $h.'h';
    if ($m)   $parts[] = $m.'min';
    if ($sec) $parts[] = $sec.'s';
    $delta_human = implode(' ', $parts).' '.$sign;
}

// Timezone offset label
$now_dt      = new DateTime('now', new DateTimeZone($_saved_tz));
$tz_offset_s = $now_dt->getOffset();
$tz_h        = intdiv(abs($tz_offset_s),3600);
$tz_m        = (abs($tz_offset_s)%3600)/60;
$tz_sign     = $tz_offset_s >= 0 ? '+' : '-';
$tz_label    = sprintf('UTC%s%02d:%02d', $tz_sign, $tz_h, $tz_m);

$tz_groups = array(
    'Philippine / ASEAN' => array(
        'Asia/Manila'       => 'Asia/Manila (UTC+8) — Philippines ★',
        'Asia/Singapore'    => 'Asia/Singapore (UTC+8)',
        'Asia/Hong_Kong'    => 'Asia/Hong_Kong (UTC+8)',
        'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur (UTC+8)',
        'Asia/Jakarta'      => 'Asia/Jakarta (UTC+7)',
        'Asia/Bangkok'      => 'Asia/Bangkok (UTC+7)',
    ),
    'Other Zones' => array(
        'UTC'                 => 'UTC (UTC+0)',
        'Asia/Dubai'          => 'Asia/Dubai (UTC+4)',
        'Asia/Kolkata'        => 'Asia/Kolkata (UTC+5:30)',
        'Asia/Tokyo'          => 'Asia/Tokyo (UTC+9)',
        'Australia/Sydney'    => 'Australia/Sydney (UTC+10/11)',
        'America/New_York'    => 'America/New_York (UTC-5/-4)',
        'America/Los_Angeles' => 'America/Los_Angeles (UTC-8/-7)',
        'Europe/London'       => 'Europe/London (UTC+0/1)',
        'Europe/Paris'        => 'Europe/Paris (UTC+1/2)',
    ),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>System Settings | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    /* ── Clock display ── */
    .hrms-clock-box {
      background:linear-gradient(135deg,#1a1a2e,#16213e);
      border-radius:14px; padding:22px 18px; color:#fff; text-align:center;
    }
    .hcb-label { font-size:10px; opacity:.4; text-transform:uppercase; letter-spacing:.1em; margin-bottom:4px; }
    .hcb-time  { font-size:44px; font-weight:200; letter-spacing:4px; line-height:1.1; }
    .hcb-date  { font-size:12px; opacity:.6; margin-top:5px; }
    .hcb-tz    { font-size:11px; opacity:.35; margin-top:4px; }
    .hcb-delta {
      display:inline-block; margin-top:8px; font-size:11px;
      padding:3px 12px; border-radius:99px; font-weight:600;
    }
    .hcb-delta.ahead  { background:#28a745; color:#fff; }
    .hcb-delta.behind { background:#dc3545; color:#fff; }
    .hcb-delta.zero   { background:rgba(255,255,255,.1); color:rgba(255,255,255,.5); }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <h1 class="m-0">System Settings</h1>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs" id="settingsTabs">
          <?php
          $tabs = array(
            'company'  => array('fas fa-building',        'Company Info'),
            'payroll'  => array('fas fa-money-bill-wave', 'Payroll Config'),
            'time'     => array('fas fa-clock',           'Time &amp; Attendance'),
            'security' => array('fas fa-shield-alt',      'Security'),
            'email'    => array('fas fa-envelope',        'Email (SMTP)'),
          );
          foreach ($tabs as $tid => $tdata):
            $act = ($active_tab === $tid) ? 'active' : '';
          ?>
          <li class="nav-item">
            <a class="nav-link <?= $act ?>" href="settings.php?tab=<?= $tid ?>">
              <i class="<?= $tdata[0] ?> mr-1"></i> <?= $tdata[1] ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card-body">

        <!-- ════ COMPANY ════ -->
        <?php if ($active_tab === 'company'): ?>
        <form method="POST" action="" enctype="multipart/form-data">
          <?php csrf_field(); ?>
          <input type="hidden" name="section" value="company">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Company name <span class="text-danger">*</span></label>
                <input type="text" name="company_name" class="form-control"
                       value="<?= sv($s,'company_name') ?>" required>
              </div>
              <div class="form-group">
                <label>Email address</label>
                <input type="email" name="company_email" class="form-control"
                       value="<?= sv($s,'company_email') ?>">
              </div>
              <div class="form-group">
                <label>Phone number</label>
                <input type="text" name="company_phone" class="form-control"
                       value="<?= sv($s,'company_phone') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Complete address</label>
                <textarea name="company_address" class="form-control" rows="3"><?= sv($s,'company_address') ?></textarea>
              </div>
              <div class="form-group">
                <label>Company logo (JPG/PNG, max 2MB)</label>
                <?php if (!empty($s['company_logo']) && file_exists('../uploads/logos/'.$s['company_logo'])): ?>
                  <div class="mb-2">
                    <img src="../uploads/logos/<?= htmlspecialchars($s['company_logo']) ?>"
                         height="60" style="border-radius:6px;border:1px solid #dee2e6;">
                    <small class="text-muted ml-2">Current logo</small>
                  </div>
                <?php endif; ?>
                <input type="file" name="company_logo" class="form-control-file"
                       accept=".jpg,.jpeg,.png,.gif">
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Save company information
          </button>
        </form>

        <!-- ════ PAYROLL ════ -->
        <?php elseif ($active_tab === 'payroll'): ?>
        <form method="POST" action="">
          <?php csrf_field(); ?>
          <input type="hidden" name="section" value="payroll">
          <h6 class="mb-3">Pay periods</h6>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label>1st cutoff day</label>
                <input type="number" name="payroll_cutoff_1" class="form-control"
                       min="1" max="31" value="<?= sv($s,'payroll_cutoff_1','15') ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>2nd cutoff day</label>
                <input type="number" name="payroll_cutoff_2" class="form-control"
                       min="1" max="31" value="<?= sv($s,'payroll_cutoff_2','30') ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Working days / month</label>
                <input type="number" name="working_days_month" class="form-control"
                       min="1" max="31" value="<?= sv($s,'working_days_month','22') ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Working hours / day</label>
                <input type="number" name="working_hours_day" class="form-control"
                       min="1" max="24" value="<?= sv($s,'working_hours_day','8') ?>">
              </div>
            </div>
          </div>
          <h6 class="mb-3 mt-2">Pay rate multipliers</h6>
          <div class="row">
            <?php
            $rates = array(
              array('overtime_rate',        'Overtime rate',           '1.25','Regular OT = 1.25x'),
              array('night_diff_rate',      'Night differential',      '1.10','10% premium'),
              array('rest_day_rate',        'Rest day rate',           '1.30','Working on rest day'),
              array('holiday_rate',         'Regular holiday rate',    '2.00','200% of daily rate'),
              array('special_holiday_rate', 'Special non-working rate','1.30','130% if worked'),
            );
            foreach ($rates as $r): ?>
            <div class="col-md-4">
              <div class="form-group">
                <label><?= $r[1] ?></label>
                <input type="number" name="<?= $r[0] ?>" class="form-control"
                       step="0.01" value="<?= sv($s,$r[0],$r[2]) ?>">
                <small class="text-muted"><?= $r[3] ?></small>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Save payroll configuration
          </button>
        </form>

        <!-- ════ TIME & ATTENDANCE ════ -->
        <?php elseif ($active_tab === 'time'): ?>

        <!-- Live HRMS clock + server time side by side -->
        <div class="row mb-4">
          <div class="col-md-5">
            <div class="hrms-clock-box">
              <div class="hcb-label">HRMS System Clock</div>
              <div class="hcb-time" id="hcbTime"><?= $hrms_time ?></div>
              <div class="hcb-date" id="hcbDate"><?= $hrms_date ?></div>
              <div class="hcb-tz"><?= htmlspecialchars($_saved_tz) ?> (<?= $tz_label ?>)</div>
              <div>
                <?php if ($_clock_delta == 0): ?>
                  <span class="hcb-delta zero"><i class="fas fa-check mr-1"></i>No adjustment</span>
                <?php elseif ($_clock_delta > 0): ?>
                  <span class="hcb-delta ahead"><i class="fas fa-fast-forward mr-1"></i><?= $delta_human ?></span>
                <?php else: ?>
                  <span class="hcb-delta behind"><i class="fas fa-fast-backward mr-1"></i><?= $delta_human ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-center mt-1" style="font-size:11px;color:#6c757d;">
              <i class="fas fa-server mr-1"></i>
              Server real time: <span id="serverRealTime"><?= $real_time ?></span>
            </div>
          </div>
          <div class="col-md-7">
            <div class="alert alert-info mb-2" style="font-size:12px;">
              <i class="fas fa-info-circle mr-1"></i>
              <strong>How the System Clock works:</strong><br>
              The HRMS clock = <em>server time</em> + <em>timezone offset</em> + <em>manual adjustment</em>.<br>
              Use the adjuster below to simulate a future or past time for testing,
              or to correct a server clock that runs slightly fast/slow.
              <strong>Adjustments are cumulative</strong> — each save adds to the existing offset.
              Use <strong>Reset to Real Time</strong> to cancel all adjustments.
            </div>
            <div class="alert alert-warning mb-0" style="font-size:12px;">
              <i class="fas fa-exclamation-triangle mr-1"></i>
              <strong>Important:</strong> This only affects HRMS timestamps.
              It does <strong>not</strong> change your server's actual system clock.
              Existing attendance records are <strong>not</strong> retroactively modified.
            </div>
          </div>
        </div>

        <form method="POST" action="" id="timeForm">
          <?php csrf_field(); ?>
          <input type="hidden" name="section" value="time">
          <input type="hidden" name="manual_datetime" id="manualDatetime" value="">

          <!-- ── Timezone ── -->
          <div class="card card-outline card-primary mb-3">
            <div class="card-header bg-light">
              <h3 class="card-title"><i class="fas fa-globe mr-1 text-primary"></i> Timezone</h3>
            </div>
            <div class="card-body">
              <div class="row align-items-end">
                <div class="col-md-6">
                  <div class="form-group mb-0">
                    <label class="font-weight-bold">System Timezone <span class="text-danger">*</span></label>
                    <select name="system_timezone" id="tzSelect" class="form-control">
                      <?php foreach ($tz_groups as $grp => $zones): ?>
                        <optgroup label="── <?= $grp ?> ──">
                          <?php foreach ($zones as $tz_val => $tz_lbl): ?>
                            <option value="<?= $tz_val ?>" <?= ($_saved_tz === $tz_val) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($tz_lbl) ?>
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Philippine schools: <strong>Asia/Manila (UTC+8)</strong></small>
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="font-weight-bold d-block">Preview after save</label>
                  <div style="background:#f8f9fa;border-radius:8px;padding:10px;text-align:center;border:1px solid #dee2e6;">
                    <div id="tzPreview" style="font-size:16px;font-weight:700;font-family:monospace;"></div>
                    <div id="tzPreviewLabel" style="font-size:10px;color:#6c757d;margin-top:2px;"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Manual Clock Adjuster ── -->
          <div class="card card-outline card-warning mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">
                <i class="fas fa-sliders-h mr-1 text-warning"></i>
                Manual Clock Adjustment
                <?php if ($_clock_delta != 0): ?>
                  <span class="badge badge-warning ml-2" style="font-size:10px;">
                    Active: <?= $delta_human ?>
                  </span>
                <?php endif; ?>
              </h3>
              <div>
                <button type="button" class="btn btn-sm btn-outline-secondary mr-1"
                        onclick="setManualNow()">
                  <i class="fas fa-crosshairs mr-1"></i> Set to specific time
                </button>
                <!-- Reset — simple link, no nested form needed -->
                <a href="settings.php?tab=time&do_reset=1"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Reset HRMS clock to real server time? All manual adjustments will be cleared.')">
                  <i class="fas fa-undo mr-1"></i> Reset to real time
                </a>
              </div>
            </div>
            <div class="card-body">

              <!-- Resulting HRMS time preview -->
              <div class="p-3 mb-3"
                   style="background:#1a1a2e;border-radius:10px;text-align:center;color:#fff;">
                <div style="font-size:10px;opacity:.4;text-transform:uppercase;letter-spacing:.1em;">
                  Resulting HRMS Time after save
                </div>
                <div id="previewResultTime"
                     style="font-size:32px;font-weight:200;letter-spacing:3px;margin-top:4px;font-family:monospace;">
                  <?= $hrms_time ?>
                </div>
                <div id="previewResultDate" style="font-size:12px;opacity:.6;margin-top:3px;">
                  <?= $hrms_date ?>
                </div>
                <div id="previewDeltaLabel" style="font-size:11px;opacity:.4;margin-top:4px;">
                  <?= $_clock_delta != 0 ? 'Current offset: '.$delta_human : 'No offset active' ?>
                </div>
              </div>

              <!-- Manual datetime input -->
              <div id="manualDtPanel" style="display:none;margin-top:14px;">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-calendar-alt mr-1"></i> Set to</span>
                  </div>
                  <input type="datetime-local" id="manualDtInput" class="form-control"
                         step="1"
                         value="<?= date('Y-m-d\TH:i:s', $hrms_ts) ?>">
                  <div class="input-group-append">
                    <button type="button" class="btn btn-warning" onclick="applyManualDt()">
                      <i class="fas fa-check mr-1"></i> Apply &amp; Save
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="hideManualDt()">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <small class="text-muted">
                  <i class="fas fa-info-circle mr-1"></i>
                  Pick the target date and time, then click <strong>Apply &amp; Save</strong> — the HRMS clock will immediately update.
                </small>
              </div>

            </div>
          </div>

          <!-- ── Shift defaults ── -->
          <div class="card card-outline card-success mb-3">
            <div class="card-header bg-light">
              <h3 class="card-title"><i class="fas fa-business-time mr-1 text-success"></i> Default Shift Times</h3>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-4">
                  <h6 class="text-muted mb-2" style="font-size:12px;">Non-Teaching Staff</h6>
                  <div class="form-group">
                    <label>Shift Start</label>
                    <input type="time" name="shift_start_default" class="form-control"
                           value="<?= sv($s,'shift_start_default','08:00') ?>">
                  </div>
                  <div class="form-group">
                    <label>Shift End</label>
                    <input type="time" name="shift_end_non_teaching" class="form-control"
                           value="<?= sv($s,'shift_end_non_teaching','17:00') ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <h6 class="text-muted mb-2" style="font-size:12px;">Basic Education (Nursery–SHS)</h6>
                  <div class="form-group">
                    <label>Shift Start</label>
                    <input type="time" name="shift_start_basic_ed" class="form-control"
                           value="<?= sv($s,'shift_start_basic_ed','07:00') ?>">
                  </div>
                  <div class="form-group">
                    <label>Shift End</label>
                    <input type="time" name="shift_end_basic_ed" class="form-control"
                           value="<?= sv($s,'shift_end_basic_ed','16:00') ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <h6 class="text-muted mb-2" style="font-size:12px;">College / Graduate</h6>
                  <div class="form-group">
                    <label>Earliest Shift Start</label>
                    <input type="time" name="shift_start_college" class="form-control"
                           value="<?= sv($s,'shift_start_college','07:00') ?>">
                    <small class="text-muted">Flexible — must complete 8 hours.</small>
                  </div>
                </div>
              </div>
              <div class="row mt-2">
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Grace Period (min)</label>
                    <input type="number" name="grace_minutes" class="form-control"
                           min="0" max="60" value="<?= sv($s,'grace_minutes','15') ?>">
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Lunch Start</label>
                    <input type="time" name="lunch_start" class="form-control"
                           value="<?= sv($s,'lunch_start','12:00') ?>">
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Lunch End</label>
                    <input type="time" name="lunch_end" class="form-control"
                           value="<?= sv($s,'lunch_end','13:00') ?>">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-clock mr-1"></i> Save Time &amp; Attendance Settings
            </button>
            <small class="text-muted">
              All new clock-in/out records will immediately use the saved time.
            </small>
          </div>
        </form>

        <!-- ════ SECURITY ════ -->
        <?php elseif ($active_tab === 'security'): ?>
        <form method="POST" action="">
          <?php csrf_field(); ?>
          <input type="hidden" name="section" value="security">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Session timeout (seconds)</label>
                <input type="number" name="session_timeout" class="form-control"
                       min="300" value="<?= sv($s,'session_timeout','1800') ?>">
                <small class="text-muted">1800 = 30 minutes.</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Max login attempts</label>
                <input type="number" name="max_login_attempts" class="form-control"
                       min="3" max="20" value="<?= sv($s,'max_login_attempts','5') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Lockout duration (minutes)</label>
                <input type="number" name="lockout_minutes" class="form-control"
                       min="1" value="<?= sv($s,'lockout_minutes','15') ?>">
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-shield-alt mr-1"></i> Save security settings
          </button>
        </form>

        <!-- ════ EMAIL / SMTP ════ -->
        <?php elseif ($active_tab === 'email'): ?>
        <form method="POST" action="">
          <?php csrf_field(); ?>
          <input type="hidden" name="section" value="email">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group">
                <label>SMTP host</label>
                <input type="text" name="smtp_host" class="form-control"
                       value="<?= sv($s,'smtp_host') ?>" placeholder="smtp.gmail.com">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>SMTP port</label>
                <input type="number" name="smtp_port" class="form-control"
                       value="<?= sv($s,'smtp_port','587') ?>">
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group">
                <label>SMTP username</label>
                <input type="text" name="smtp_username" class="form-control"
                       value="<?= sv($s,'smtp_username') ?>" autocomplete="off">
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group">
                <label>SMTP password</label>
                <input type="password" name="smtp_password" class="form-control"
                       placeholder="Leave blank to keep current" autocomplete="new-password">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>From name</label>
                <input type="text" name="smtp_from_name" class="form-control"
                       value="<?= sv($s,'smtp_from_name','HRMS') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>From email</label>
                <input type="email" name="smtp_from_email" class="form-control"
                       value="<?= sv($s,'smtp_from_email') ?>">
              </div>
            </div>
          </div>
          <div class="alert alert-warning" style="font-size:13px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            For Gmail: use App Password — not your regular password.
            Generate at <strong>myaccount.google.com/apppasswords</strong>.
          </div>
          <button type="submit" class="btn btn-info">
            <i class="fas fa-envelope mr-1"></i> Save email settings
          </button>
        </form>

        <?php endif; ?>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
/* ════════════════════════════════════════════════════════════
   LIVE CLOCKS
════════════════════════════════════════════════════════════ */
var _tz    = <?= json_encode($_saved_tz) ?>;
var _delta = <?= (int)$_clock_delta ?>;   // current saved delta in seconds

function getHrmsDate(extraDelta) {
    var d = new Date(Date.now() + (_delta + (extraDelta||0)) * 1000);
    return d;
}

// Live HRMS clock (ticks every second)
(function tickHrms() {
    try {
        var d   = getHrmsDate(0);
        var opt = { hour:'2-digit', minute:'2-digit', second:'2-digit',
                    hour12:true, timeZone:_tz };
        var tel = document.getElementById('hcbTime');
        var del = document.getElementById('hcbDate');
        if (tel) tel.textContent = d.toLocaleTimeString('en-PH', opt).toUpperCase();
        if (del) del.textContent = d.toLocaleDateString('en-PH',
            {weekday:'long',year:'numeric',month:'long',day:'numeric',timeZone:_tz});
    } catch(e) {}
    setTimeout(tickHrms, 1000);
})();

// Live server real time
(function tickReal() {
    var d  = new Date();
    var h  = d.getHours(), m = d.getMinutes(), s = d.getSeconds();
    var ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12; if (!h) h = 12;
    var el = document.getElementById('serverRealTime');
    if (el) el.textContent = pad(h)+':'+pad(m)+':'+pad(s)+' '+ap;
    setTimeout(tickReal, 1000);
})();

function humanDelta(abs) {
    var y  = Math.floor(abs/(365*86400)); abs -= y*365*86400;
    var mo = Math.floor(abs/(30*86400));  abs -= mo*30*86400;
    var d  = Math.floor(abs/86400);       abs -= d*86400;
    var h  = Math.floor(abs/3600);        abs -= h*3600;
    var m  = Math.floor(abs/60);
    var s  = abs % 60;
    var p  = [];
    if (y)  p.push(y+'y');
    if (mo) p.push(mo+'mo');
    if (d)  p.push(d+'d');
    if (h)  p.push(h+'h');
    if (m)  p.push(m+'min');
    if (s)  p.push(s+'s');
    return p.length ? p.join(' ') : '0s';
}

function pad(n) { return n < 10 ? '0'+n : ''+n; }

/* ════════════════════════════════════════════════════════════
   MANUAL ADJUSTER — datetime input only
════════════════════════════════════════════════════════════ */

function setManualNow() {
    document.getElementById('manualDtPanel').style.display = 'block';
    // Pre-fill with current HRMS time
    var d = getHrmsDate(0);
    var pad2 = function(n){ return n<10?'0'+n:''+n; };
    var dtStr = d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate())+
                'T'+pad2(d.getHours())+':'+pad2(d.getMinutes())+':'+pad2(d.getSeconds());
    document.getElementById('manualDtInput').value = dtStr;
}
function hideManualDt() {
    document.getElementById('manualDtPanel').style.display = 'none';
    document.getElementById('manualDatetime').value = '';
}
function applyManualDt() {
    var val = document.getElementById('manualDtInput').value;
    if (!val) { alert('Please select a date and time first.'); return; }

    // Convert datetime-local value (YYYY-MM-DDTHH:MM:SS) to Y-m-d H:i:s
    var dt = val.replace('T', ' ');
    if (dt.length === 16) dt += ':00'; // add seconds if browser omits them

    // Set the hidden field then submit immediately
    document.getElementById('manualDatetime').value = dt;
    document.getElementById('timeForm').submit();
}

/* ── Timezone preview ── */
function updateTzPreview() {
    var tz = document.getElementById('tzSelect').value;
    var d  = getHrmsDate(0);
    try {
        var pt = document.getElementById('tzPreview');
        var pl = document.getElementById('tzPreviewLabel');
        if (pt) pt.textContent = d.toLocaleTimeString('en-PH',
            {hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true,timeZone:tz}).toUpperCase();
        if (pl) pl.textContent = tz;
    } catch(e) {
        var pt = document.getElementById('tzPreview');
        if (pt) pt.textContent = 'Invalid TZ';
    }
}
document.getElementById('tzSelect') && document.getElementById('tzSelect').addEventListener('change', updateTzPreview);
setInterval(updateTzPreview, 1000);
updateTzPreview();
</script>
</body>
</html>