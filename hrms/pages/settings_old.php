<?php
// ============================================
// FILE: pages/settings.php — TABBED VERSION
// Each section has its own Save button
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_ADMIN);

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
            $pdo->prepare(
                "INSERT INTO settings (setting_key,setting_value)
                 VALUES (?,?)
                 ON DUPLICATE KEY UPDATE setting_value=?"
            )->execute(array($key,$val,$val));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $section = isset($_POST['section']) ? $_POST['section'] : '';

    if ($section === 'company') {
        save_keys($pdo, array(
            'company_name','company_address','company_phone','company_email'
        ), $_POST);

        // Logo upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg','jpeg','png','gif'))) {
                $dir  = '../uploads/logos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $file = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $dir . $file)) {
                    $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('company_logo',?)
                                   ON DUPLICATE KEY UPDATE setting_value=?")->execute(array($file,$file));
                }
            }
        }
        prg_redirect('settings.php?tab=company', 'Company information saved.');
    }

    if ($section === 'payroll') {
        save_keys($pdo, array(
            'payroll_cutoff_1','payroll_cutoff_2','working_days_month','working_hours_day',
            'overtime_rate','night_diff_rate','rest_day_rate','holiday_rate','special_holiday_rate'
        ), $_POST);
        prg_redirect('settings.php?tab=payroll', 'Payroll configuration saved.');
    }

    if ($section === 'security') {
        save_keys($pdo, array(
            'session_timeout','max_login_attempts','lockout_minutes'
        ), $_POST);
        prg_redirect('settings.php?tab=security', 'Security settings saved.');
    }

    if ($section === 'email') {
        save_keys($pdo, array(
            'smtp_host','smtp_port','smtp_username','smtp_from_name','smtp_from_email'
        ), $_POST);
        if (!empty($_POST['smtp_password'])) {
            $pw = trim($_POST['smtp_password']);
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('smtp_password',?)
                           ON DUPLICATE KEY UPDATE setting_value=?")->execute(array($pw,$pw));
        }
        prg_redirect('settings.php?tab=email', 'Email settings saved.');
    }
}

// Reload after PRG
$raw = db_fetchall($pdo, "SELECT setting_key, setting_value FROM settings");
$s   = array();
foreach ($raw as $r) $s[$r['setting_key']] = $r['setting_value'];

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>System Settings | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <h1 class="m-0">System Settings</h1>
    </div>
  </div>
  <div class="content"><div class="container-fluid">

    <?= prg_flash() ?>

    <!-- Tab navigation -->
    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs" id="settingsTabs">
          <?php
          $tabs = array(
            'company'  => array('fas fa-building',       'Company information'),
            'payroll'  => array('fas fa-money-bill-wave','Payroll configuration'),
            'security' => array('fas fa-shield-alt',     'Security settings'),
            'email'    => array('fas fa-envelope',       'Email (SMTP)'),
          );
          foreach ($tabs as $tid => $tdata):
            $active = ($active_tab === $tid) ? 'active' : '';
          ?>
          <li class="nav-item">
            <a class="nav-link <?= $active ?>" href="settings.php?tab=<?= $tid ?>">
              <i class="<?= $tdata[0] ?> mr-1"></i> <?= $tdata[1] ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card-body">

        <!-- ── COMPANY INFORMATION ── -->
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
                         height="60" alt="Current logo"
                         style="border-radius:6px;border:1px solid #dee2e6;">
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

        <!-- ── PAYROLL CONFIGURATION ── -->
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
                <small class="text-muted">e.g. 15 for the 15th</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>2nd cutoff day</label>
                <input type="number" name="payroll_cutoff_2" class="form-control"
                       min="1" max="31" value="<?= sv($s,'payroll_cutoff_2','30') ?>">
                <small class="text-muted">e.g. 30 for month-end</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Working days per month</label>
                <input type="number" name="working_days_month" class="form-control"
                       min="1" max="31" value="<?= sv($s,'working_days_month','22') ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Working hours per day</label>
                <input type="number" name="working_hours_day" class="form-control"
                       min="1" max="24" value="<?= sv($s,'working_hours_day','8') ?>">
              </div>
            </div>
          </div>
          <h6 class="mb-3 mt-2">Pay rate multipliers</h6>
          <div class="row">
            <?php
            $rates = array(
              array('overtime_rate',        'Overtime rate (OT)',          '1.25', 'Regular OT = 1.25x'),
              array('night_diff_rate',      'Night differential',          '1.10', '10% premium on basic'),
              array('rest_day_rate',        'Rest day rate',               '1.30', 'Working on rest day'),
              array('holiday_rate',         'Regular holiday rate',        '2.00', '200% of daily rate'),
              array('special_holiday_rate', 'Special non-working rate',    '1.30', '130% if worked'),
            );
            foreach ($rates as $r):
            ?>
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

        <!-- ── SECURITY ── -->
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
                <small class="text-muted">1800 = 30 minutes. Minimum: 300 (5 min).</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Max login attempts before lockout</label>
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
          <div class="alert alert-info mt-2" style="font-size:13px;">
            <i class="fas fa-info-circle mr-1"></i>
            Security settings take effect immediately for new sessions. Active sessions are not affected until they expire.
          </div>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-shield-alt mr-1"></i> Save security settings
          </button>
        </form>

        <!-- ── EMAIL / SMTP ── -->
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
            <div class="col-md-4">
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
            For Gmail: use App Password (not your regular password). Enable 2FA first, then generate an App Password at <strong>myaccount.google.com/apppasswords</strong>.
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
</body></html>