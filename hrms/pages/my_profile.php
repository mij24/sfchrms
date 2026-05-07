<?php
// ============================================
// FILE: pages/my_profile.php — ENHANCED
// Banner + 4 Tabs: Profile | Leave | Training/Seminars | Documents
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

$user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

// ── Admin without employee record ──
if (is_admin() && !$emp_id) {
    $total_emp   = db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_status='Active'");
    $total_users = db_value($pdo,"SELECT COUNT(*) FROM users WHERE is_active=1");
    ?><!DOCTYPE html><html lang="en">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Account | HRMS</title>
    <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="../assets/dist/css/custom.css"></head>
    <body class="hold-transition sidebar-mini"><div class="wrapper">
    <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
    <div class="content-wrapper"><div class="content-header"><div class="container-fluid">
      <h1 class="m-0">My Account</h1></div></div>
    <div class="content"><div class="container-fluid">
      <div class="alert alert-info">
        <i class="fas fa-info-circle mr-2"></i>
        This is a <strong>system administrator account</strong> not linked to an employee record.
        <a href="users.php">Link via User Management</a> to access HR self-service features.
      </div>
      <div class="row">
        <div class="col-md-3 col-6">
          <div class="small-box bg-primary" style="border-radius:8px;">
            <div class="inner"><h3><?= $total_emp ?></h3><p>Active employees</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <a href="employees.php" class="small-box-footer">Manage <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-info" style="border-radius:8px;">
            <div class="inner"><h3><?= $total_users ?></h3><p>User accounts</p></div>
            <div class="icon"><i class="fas fa-user-cog"></i></div>
            <a href="users.php" class="small-box-footer">Manage <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
      </div>
    </div></div></div>
    <?php include '../includes/footer.php'; ?>
    </div></body></html><?php
    exit;
}

if (!$emp_id) { ?>
  <!DOCTYPE html><html><body class="hold-transition sidebar-mini"><div class="wrapper">
  <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
  <div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle mr-2"></i>
      Your account is not linked to an employee record. Contact HR.
    </div>
  </div></div></div>
  <?php include '../includes/footer.php'; ?>
  </div></body></html>
<?php exit; }

// ── Load employee record ──
$emp = db_fetch($pdo,
    "SELECT e.*,
            d.name  AS dept,
            p.title AS position,
            s.full_name AS supervisor_name,
            ec.name AS classification_name,
            er.name AS rank_name,
            wl.name AS work_location_name
     FROM employees e
     LEFT JOIN departments              d  ON e.department_id      = d.id
     LEFT JOIN positions                p  ON e.position_id        = p.id
     LEFT JOIN employees                s  ON e.supervisor_id      = s.id
     LEFT JOIN employee_classifications ec ON e.classification_id  = ec.id
     LEFT JOIN employee_ranks           er ON e.rank_id            = er.id
     LEFT JOIN work_locations           wl ON e.work_location_id   = wl.id
     WHERE e.id=?",
    array($emp_id)
);

// ── Handle profile change request POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'profile_update') {
    csrf_verify();
    $editable  = array('contact_number','email_address','address','emergency_contact','civil_status','nationality');
    $submitted = array();
    foreach ($editable as $field) {
        if (isset($_POST[$field])) {
            $new_val = clean_string($_POST[$field]);
            if ($new_val !== ($emp[$field] ?? '')) {
                $submitted[$field] = array('old' => $emp[$field] ?? '', 'new' => $new_val);
            }
        }
    }
    if (!empty($submitted)) {
        db_run($pdo,
            "INSERT INTO employee_self_updates (employee_id,section,submitted_data) VALUES (?,?,?)",
            array($emp_id,'personal_info',json_encode($submitted))
        );
        prg_redirect('my_profile.php','Profile update request submitted. HR will review and approve.');
    } else {
        prg_redirect('my_profile.php','No changes detected.');
    }
}

// ── Handle Leave Request Modal POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'request_leave') {
    csrf_verify();
    $ltype  = clean_string($_POST['leave_type']);
    $from   = clean_date($_POST['date_from']);
    $to     = clean_date($_POST['date_to']);
    $days   = clean_float($_POST['days_applied']);
    $reason = clean_string($_POST['reason'], 500);
    db_run($pdo,
        "INSERT INTO leave_requests (employee_id,leave_type,date_from,date_to,days_applied,reason)
         VALUES (?,?,?,?,?,?)",
        array($emp_id,$ltype,$from,$to,$days,$reason)
    );
    prg_redirect('my_profile.php?tab=leave','Leave request submitted successfully.');
}

// ── Handle Training Entry POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_training') {
    csrf_verify();
    $title       = clean_string($_POST['training_title']);
    $date_from   = clean_date($_POST['training_date_from']);
    $date_to     = clean_date($_POST['training_date_to']);
    $institution = clean_string($_POST['institution']);
    $conductor   = clean_string($_POST['conductor']);
    $level       = clean_string($_POST['training_level']);
    $points      = clean_float($_POST['equivalent_points']);

    // Try to insert into employee_trainings table (self-service, pending approval)
    try {
        db_run($pdo,
            "INSERT INTO employee_trainings
             (employee_id,training_title,date_from,date_to,institution,conductor,level,equivalent_points,status)
             VALUES (?,?,?,?,?,?,?,?,'Pending')",
            array($emp_id,$title,$date_from,$date_to,$institution,$conductor,$level,$points)
        );
    } catch (Exception $e) {
        // Table may not exist yet — silently handle
    }
    prg_redirect('my_profile.php?tab=training','Training entry submitted for HR approval.');
}

// ── Handle Document Upload POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'upload_doc') {
    csrf_verify();
    $doc_type = clean_string($_POST['document_type']);
    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        $allowed = array('pdf','jpg','jpeg','png','doc','docx');
        if (in_array($ext,$allowed) && $_FILES['doc_file']['size'] <= 5*1024*1024) {
            $dir = '../uploads/';
            if (!is_dir($dir)) mkdir($dir,0755,true);
            $fname = $emp_id.'_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',basename($_FILES['doc_file']['name']));
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'],$dir.$fname)) {
                db_run($pdo,
                    "INSERT INTO employee_documents (employee_id,document_type,file_name,file_path,status)
                     VALUES (?,?,?,?,'Pending')",
                    array($emp_id,$doc_type,$_FILES['doc_file']['name'],$fname)
                );
                prg_redirect('my_profile.php?tab=documents','Document uploaded and pending HR approval.');
            }
        }
    }
    prg_redirect('my_profile.php?tab=documents','Upload failed. Check file type and size (max 5MB).');
}

// ── Supporting data ──
$lc = db_fetch($pdo,
    "SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",
    array($emp_id)
);
$my_leave_requests = db_fetchall($pdo,
    "SELECT * FROM leave_requests WHERE employee_id=? ORDER BY created_at DESC LIMIT 20",
    array($emp_id)
);

$docs = db_fetchall($pdo,
    "SELECT * FROM employee_documents WHERE employee_id=? ORDER BY uploaded_at DESC",
    array($emp_id)
);

// Training records — try new table, fall back gracefully
$trainings = array();
try {
    $trainings = db_fetchall($pdo,
        "SELECT * FROM employee_trainings WHERE employee_id=? ORDER BY date_from DESC",
        array($emp_id)
    );
} catch (Exception $e) { $trainings = array(); }

$qr = db_fetch($pdo,
    "SELECT * FROM employee_qr_codes WHERE employee_id=? AND is_active=1 LIMIT 1",
    array($emp_id)
);

// Initials + photo
$parts    = explode(' ', trim($emp['full_name']));
$initials = '';
foreach (array_slice($parts,0,2) as $p) if($p) $initials .= strtoupper($p[0]);
$photo_url = (!empty($emp['photo'])) ? '../uploads/photos/'.htmlspecialchars($emp['photo']) : null;

// Active tab from query string (for redirect after POST)
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Leave credit helper
function lc_val($lc, $key) { return isset($lc[$key]) ? (float)$lc[$key] : 0; }

// Leave types for dropdown
$leave_types_list = array(
    'Vacation Leave','Sick Leave','Emergency Leave',
    'Maternity Leave','Paternity Leave','Solo Parent Leave','Future Rest Day'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Profile | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    /* ── PROFILE BANNER ── */
    .profile-banner {
      background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
      border-radius: 12px;
      padding: 28px 30px 20px;
      color: #fff;
      margin-bottom: 0;
      position: relative;
      overflow: hidden;
    }
    .profile-banner::before {
      content: '';
      position: absolute;
      top: -40px; right: -40px;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: rgba(255,255,255,0.06);
    }
    .profile-banner::after {
      content: '';
      position: absolute;
      bottom: -60px; right: 80px;
      width: 160px; height: 160px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
    }
    .banner-avatar {
      width: 80px; height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid rgba(255,255,255,0.5);
      flex-shrink: 0;
    }
    .banner-avatar-init {
      width: 80px; height: 80px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 28px; font-weight: 700;
      border: 3px solid rgba(255,255,255,0.4);
      flex-shrink: 0;
    }
    .banner-name { font-size: 20px; font-weight: 700; margin-bottom: 2px; }
    .banner-sub  { font-size: 13px; opacity: 0.85; margin-bottom: 6px; }
    .banner-badges .badge { margin-right: 4px; font-size: 11px; padding: 4px 8px; }
    .banner-actions { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
    .banner-actions .btn {
      font-size: 12px; padding: 5px 14px;
      background: rgba(255,255,255,0.15);
      color: #fff; border: 1px solid rgba(255,255,255,0.3);
      border-radius: 20px;
    }
    .banner-actions .btn:hover { background: rgba(255,255,255,0.28); color:#fff; }
    .banner-actions .btn-clockin {
      background: #00c853; border-color: #00c853; font-weight: 600;
    }
    .banner-actions .btn-clockin:hover { background: #00a844; }

    /* ── TABS ── */
    .profile-tabs { background: #fff; border-bottom: 1px solid #dee2e6; padding: 0 20px; border-radius: 0 0 0 0; }
    .profile-tabs .nav-link { color: #6c757d; font-size: 13px; font-weight: 500; padding: 12px 18px; border: none; border-radius: 0; border-bottom: 3px solid transparent; }
    .profile-tabs .nav-link.active { color: #1a73e8; border-bottom-color: #1a73e8; background: none; }
    .profile-tabs .nav-link:hover { color: #1a73e8; }
    .profile-tabs .nav-link i { margin-right: 5px; }

    /* ── INFO LABELS ── */
    .info-label { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
    .info-val   { font-size: 14px; font-weight: 500; color: #343a40; word-break: break-word; }
    .section-title { font-size: 11px; font-weight: 700; color: #6c757d; text-transform: uppercase;
                     letter-spacing: .05em; margin: 16px 0 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 4px; }

    /* ── LEAVE CREDITS ── */
    .lc-card { border-radius: 10px; padding: 14px 16px; background: #f8f9fa; margin-bottom: 10px; }
    .lc-card .lc-label { font-size: 11px; color: #6c757d; text-transform: uppercase; margin-bottom: 2px; }
    .lc-card .lc-value { font-size: 22px; font-weight: 700; color: #343a40; }
    .lc-card .lc-sub   { font-size: 12px; color: #aaa; margin-top: 2px; }

    /* ── TRAINING TABLE ── */
    .level-badge-international { background: #6f42c1; color: #fff; }
    .level-badge-national      { background: #007bff; color: #fff; }
    .level-badge-local         { background: #28a745; color: #fff; }

    /* ── DOC TYPE GROUPS ── */
    .doc-group-header { background: #f1f3f5; font-weight: 600; font-size: 12px; color: #495057;
                        padding: 6px 12px; border-radius: 6px; margin-bottom: 6px; margin-top: 12px; }
    .doc-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px;
                border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 6px; background: #fff; }
    .doc-item .doc-icon { font-size: 20px; color: #6c757d; flex-shrink: 0; }
    .doc-item .doc-name { flex: 1; font-size: 13px; font-weight: 500; color: #343a40; }
    .doc-item .doc-meta { font-size: 11px; color: #aaa; }
    .doc-item .doc-status { margin-left: auto; flex-shrink: 0; }
    .pending-badge  { background: #fff3cd; color: #856404; font-size: 10px; padding: 2px 8px; border-radius: 99px; }
    .approved-badge { background: #d4edda; color: #155724; font-size: 10px; padding: 2px 8px; border-radius: 99px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <h1 class="m-0">My Profile</h1>
  </div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- ═══════════════════════════════════════════
         BANNER
    ═══════════════════════════════════════════ -->
    <div class="card mb-0" style="border-radius:12px 12px 0 0;border-bottom:0;">
      <div class="card-body p-0">
        <div class="profile-banner">
          <div class="d-flex align-items-center flex-wrap" style="gap:18px;">
            <?php if ($photo_url): ?>
              <img src="<?= $photo_url ?>" class="banner-avatar" alt="photo">
            <?php else: ?>
              <div class="banner-avatar-init"><?= $initials ?></div>
            <?php endif; ?>
            <div style="flex:1;min-width:200px;">
              <div class="banner-name"><?= htmlspecialchars($emp['full_name']) ?></div>
              <div class="banner-sub">
                <?= htmlspecialchars($emp['position'] ?: '—') ?>
                <?php if ($emp['dept']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($emp['dept']) ?><?php endif; ?>
              </div>
              <div class="banner-badges">
                <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff;">
                  <?= htmlspecialchars($emp['employment_status'] ?: 'Active') ?>
                </span>
                <span class="badge" style="background:rgba(255,255,255,0.15);color:#fff;">
                  <?= htmlspecialchars($emp['employee_id']) ?>
                </span>
                <?php if ($emp['classification_name']): ?>
                <span class="badge" style="background:rgba(255,255,255,0.12);color:#fff;">
                  <?= htmlspecialchars($emp['classification_name']) ?>
                </span>
                <?php endif; ?>
              </div>
              <div class="banner-actions">
                <a href="upload_photo.php?id=<?= $emp_id ?>" class="btn">
                  <i class="fas fa-camera mr-1"></i> Photo
                </a>
                <a href="change_password.php" class="btn">
                  <i class="fas fa-lock mr-1"></i> Password
                </a>
                <!--<a href="attendance_selfservice.php" class="btn btn-clockin">
                  <i class="fas fa-clock mr-1"></i> Clock In/Out
                </a>-->
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         4 TABS
    ═══════════════════════════════════════════ -->
    <div class="card mb-0" style="border-radius:0;border-top:0;border-bottom:0;">
      <div class="profile-tabs">
        <ul class="nav" id="profileTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link <?= $active_tab==='profile'?'active':'' ?>" data-toggle="tab" href="#tab-profile" role="tab">
              <i class="fas fa-user"></i> Profile
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $active_tab==='leave'?'active':'' ?>" data-toggle="tab" href="#tab-leave" role="tab">
              <i class="fas fa-calendar-alt"></i> Leave
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $active_tab==='training'?'active':'' ?>" data-toggle="tab" href="#tab-training" role="tab">
              <i class="fas fa-chalkboard-teacher"></i> Training / Seminars
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $active_tab==='documents'?'active':'' ?>" data-toggle="tab" href="#tab-documents" role="tab">
              <i class="fas fa-folder-open"></i> Documents
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         TAB CONTENT
    ═══════════════════════════════════════════ -->
    <div class="tab-content" style="background:#f4f6f9;padding:20px 0;">

      <!-- ─── TAB 1: PROFILE ─── -->
      <div class="tab-pane fade <?= $active_tab==='profile'?'show active':'' ?>" id="tab-profile">
        <div class="container-fluid">
          <div class="row">

            <!-- LEFT: QR Code + Personal Info -->
            <div class="col-md-6">

              <!-- QR Code -->
              <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-qrcode mr-1 text-primary"></i> My QR Code</h3></div>
                <div class="card-body text-center">
                  <?php if ($qr): ?>
                    <?php
                    $qr_data = urlencode('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/qr_scanner.php?token='.$qr['qr_token']);
                    $qr_url  = 'https://chart.googleapis.com/chart?chs=180x180&cht=qr&chl='.$qr_data.'&choe=UTF-8';
                    ?>
                    <img src="<?= $qr_url ?>" style="width:150px;height:150px;" alt="My QR Code">
                    <div class="mt-2" style="font-size:12px;color:#6c757d;">Scan at the attendance kiosk to time in/out.</div>
                    <a href="<?= $qr_url ?>" download="qr_<?= $emp['employee_id'] ?>.png" class="btn btn-sm btn-outline-primary mt-2">
                      <i class="fas fa-download mr-1"></i> Download QR
                    </a>
                  <?php else: ?>
                    <i class="fas fa-qrcode fa-3x mb-2" style="opacity:.2;display:block;"></i>
                    <div style="font-size:13px;color:#6c757d;">No QR code assigned. Contact HR.</div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Personal Information -->
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-id-card mr-1 text-info"></i> Personal Information</h3>
                  <small class="text-muted">Changes need HR approval</small>
                </div>
                <div class="card-body">
                  <div class="section-title">Basic Details</div>
                  <div class="row mb-2">
                    <?php
                    $personal_ro = array(
                      'Date of birth' => ($emp['date_of_birth'] && $emp['date_of_birth'] !== '0000-00-00')
                                          ? date('F d, Y', strtotime($emp['date_of_birth'])) : '—',
                      'Gender'        => $emp['gender'] ?: '—',
                    );
                    foreach ($personal_ro as $lbl => $val): ?>
                    <div class="col-6 mb-3">
                      <div class="info-label"><?= $lbl ?></div>
                      <div class="info-val"><?= htmlspecialchars($val) ?></div>
                    </div>
                    <?php endforeach; ?>
                  </div>

                  <div class="section-title">Editable Details <small class="text-muted font-weight-normal">(submit for HR approval)</small></div>
                  <form method="POST" action="">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="form_action" value="profile_update">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label class="info-label">Contact number</label>
                          <input type="text" name="contact_number" class="form-control form-control-sm"
                                 value="<?= htmlspecialchars($emp['contact_number'] ?: '') ?>">
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label class="info-label">Email address</label>
                          <input type="email" name="email_address" class="form-control form-control-sm"
                                 value="<?= htmlspecialchars($emp['email_address'] ?: '') ?>">
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label class="info-label">Civil status</label>
                          <select name="civil_status" class="form-control form-control-sm">
                            <?php foreach (array('Single','Married','Widowed','Separated') as $cs): ?>
                              <option <?= ($emp['civil_status']===$cs)?'selected':'' ?>><?= $cs ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label class="info-label">Nationality</label>
                          <input type="text" name="nationality" class="form-control form-control-sm"
                                 value="<?= htmlspecialchars($emp['nationality'] ?: '') ?>">
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="form-group">
                          <label class="info-label">Home address</label>
                          <input type="text" name="address" class="form-control form-control-sm"
                                 value="<?= htmlspecialchars($emp['address'] ?: '') ?>">
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="form-group">
                          <label class="info-label">Emergency contact</label>
                          <input type="text" name="emergency_contact" class="form-control form-control-sm"
                                 value="<?= htmlspecialchars($emp['emergency_contact'] ?: '') ?>">
                        </div>
                      </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                      <i class="fas fa-paper-plane mr-1"></i> Submit for HR Approval
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <!-- RIGHT: Employment Details + Gov IDs -->
            <div class="col-md-6">

              <!-- Employment Details -->
              <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-briefcase mr-1 text-success"></i> Employment Details</h3></div>
                <div class="card-body">
                  <div class="row">
                    <?php
                    $emp_details = array(
                      'Employee ID'       => $emp['employee_id'],
                      'Department'        => $emp['dept'] ?: '—',
                      'Position'          => $emp['position'] ?: '—',
                      'Classification'    => $emp['classification_name'] ?: '—',
                      'Rank'              => $emp['rank_name'] ?: '—',
                      'Employment type'   => $emp['employment_type'] ?: '—',
                      'Employment status' => $emp['employment_status'] ?: '—',
                      'Direct head'       => $emp['supervisor_name'] ?: '—',
                      'Work location'     => $emp['work_location_name'] ?: $emp['work_location'] ?? '—',
                      'Date hired'        => ($emp['date_hired'] && $emp['date_hired'] !== '0000-00-00')
                                              ? date('F d, Y', strtotime($emp['date_hired'])) : '—',
                    );
                    foreach ($emp_details as $lbl => $val): ?>
                    <div class="col-6 mb-3">
                      <div class="info-label"><?= $lbl ?></div>
                      <div class="info-val"><?= htmlspecialchars($val) ?></div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <small class="text-muted"><i class="fas fa-lock mr-1"></i>Changed by HR only.</small>
                </div>
              </div>

              <!-- Government IDs -->
              <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-id-badge mr-1 text-warning"></i> Government IDs</h3></div>
                <div class="card-body">
                  <div class="row">
                    <?php
                    $gov_ids = array(
                      'SSS number'        => $emp['sss_number'] ?? '—',
                      'PhilHealth number' => $emp['philhealth_number'] ?? '—',
                      'Pag-IBIG number'   => $emp['pagibig_number'] ?? '—',
                      'TIN number'        => $emp['tin_number'] ?? '—',
                    );
                    foreach ($gov_ids as $lbl => $val): ?>
                    <div class="col-6 mb-3">
                      <div class="info-label"><?= $lbl ?></div>
                      <div class="info-val"><?= htmlspecialchars($val ?: '—') ?></div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <small class="text-muted"><i class="fas fa-lock mr-1"></i>To update, submit a Profile Change request.</small>
                </div>
              </div>

              <!-- 201 Uploaded Documents (quick view in profile) -->
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-folder mr-1 text-secondary"></i> 201 Documents</h3>
                  <a href="#tab-documents" data-toggle="tab" class="btn btn-xs btn-outline-secondary">View all</a>
                </div>
                <div class="card-body p-0">
                  <?php if (empty($docs)): ?>
                    <p class="text-muted text-center py-3" style="font-size:13px;">No documents uploaded yet.</p>
                  <?php else: ?>
                    <table class="table table-sm mb-0">
                      <thead class="thead-light"><tr><th>Type</th><th>File</th><th>Status</th></tr></thead>
                      <tbody>
                        <?php foreach (array_slice($docs,0,5) as $doc): ?>
                        <tr>
                          <td><span class="badge badge-secondary" style="font-size:10px;"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                          <td style="font-size:12px;"><?= htmlspecialchars($doc['file_name']) ?></td>
                          <td>
                            <?php
                            $dstat = $doc['status'] ?? 'Approved';
                            $dsb   = $dstat === 'Pending' ? 'warning' : 'success';
                            ?>
                            <span class="badge badge-<?= $dsb ?>" style="font-size:10px;"><?= $dstat ?></span>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Biometric / Mobile Clock-in Integration -->
              <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-fingerprint mr-1 text-danger"></i> Biometric / Mobile Time-In Integration</h3></div>
                <div class="card-body" style="font-size:13px;color:#555;">
                  <?php
                  $dtr_logs = array();
                  try {
                      $dtr_logs = db_fetchall($pdo,
                          "SELECT * FROM mobile_attendance_log WHERE employee_id=? ORDER BY log_datetime DESC LIMIT 5",
                          array($emp_id)
                      );
                  } catch (Exception $e) {}
                  ?>
                  <?php if (!empty($dtr_logs)): ?>
                  <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Action</th><th>Date & Time</th><th>Photo</th><th>Location</th></tr></thead>
                    <tbody>
                      <?php foreach ($dtr_logs as $log): ?>
                      <tr>
                        <td><span class="badge badge-<?= $log['action']==='Clock In'?'success':'danger' ?>" style="font-size:10px;"><?= $log['action'] ?></span></td>
                        <td style="font-size:11px;"><?= date('M d, g:i A', strtotime($log['log_datetime'])) ?></td>
                        <td>
                          <?php if (!empty($log['photo_path'])): ?>
                            <a href="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>" target="_blank">
                              <img src="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">
                            </a>
                          <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td style="font-size:11px;">
                          <?php if (!empty($log['latitude'])): ?>
                            <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="text-danger"><i class="fas fa-map-marker-alt"></i> Map</a>
                          <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  <?php else: ?>
                    <div class="text-center text-muted py-2">
                      <i class="fas fa-mobile-alt fa-2x d-block mb-2" style="opacity:.2;"></i>
                      No mobile clock-in records yet. Use the Clock In/Out button above or scan your QR code.
                    </div>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- ─── TAB 2: LEAVE ─── -->
      <div class="tab-pane fade <?= $active_tab==='leave'?'show active':'' ?>" id="tab-leave">
        <div class="container-fluid">

          <!-- Leave Entitlement + Balance -->
          <div class="row mb-3">
            <div class="col-12">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-chart-bar mr-1 text-primary"></i> Leave Entitlement &amp; Balance — <?= date('Y') ?></h3>
                  <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#leaveRequestModal">
                    <i class="fas fa-plus mr-1"></i> Request Leave
                  </button>
                </div>
                <div class="card-body">
                  <?php if ($lc): ?>
                  <div class="row">
                    <?php
                    $leave_display = array(
                      array('Vacation Leave',    'vacation_leave',    'vacation_used',     'success', 'fas fa-umbrella-beach'),
                      array('Sick Leave',        'sick_leave',        'sick_used',         'info',    'fas fa-notes-medical'),
                      array('Emergency Leave',   'emergency_leave',   'emergency_used',    'warning', 'fas fa-exclamation-circle'),
                      array('Maternity Leave',   'maternity_leave',   'maternity_used',    'pink',    'fas fa-baby'),
                      array('Paternity Leave',   'paternity_leave',   'paternity_used',    'primary', 'fas fa-male'),
                      array('Solo Parent Leave', 'solo_parent_leave', 'solo_parent_used',  'dark',    'fas fa-child'),
                      array('Future Rest Day',   'future_rest_day',   'future_rest_used',  'secondary','fas fa-calendar-check'),
                    );
                    foreach ($leave_display as $lv):
                      $tot  = lc_val($lc, $lv[1]);
                      $used = lc_val($lc, $lv[2]);
                      $rem  = $tot - $used;
                      $pct  = $tot > 0 ? min(100, round(($used/$tot)*100)) : 0;
                      $color = $lv[3] === 'pink' ? '#e91e8c' : null;
                    ?>
                    <div class="col-md-4 col-6 mb-3">
                      <div class="card mb-0" style="border-left:4px solid <?= $color ?: ('var(--'.($lv[3]==='secondary'?'secondary':$lv[3]).')') ?>;">
                        <div class="card-body py-2 px-3">
                          <div style="font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.04em;"><?= $lv[0] ?></div>
                          <div style="font-size:24px;font-weight:700;color:#343a40;">
                            <?= number_format($rem,1) ?>
                            <small style="font-size:12px;color:#aaa;font-weight:400;">/ <?= number_format($tot,1) ?> days</small>
                          </div>
                          <div class="progress mt-1" style="height:4px;">
                            <div class="progress-bar <?= $lv[3]==='pink'?'':'bg-'.$lv[3] ?>"
                                 <?= $lv[3]==='pink'?'style="background:#e91e8c;width:'.$pct.'%"':'style="width:'.$pct.'%"' ?>></div>
                          </div>
                          <div style="font-size:11px;color:#aaa;margin-top:2px;"><?= number_format($used,1) ?> used</div>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?>
                    <div class="text-center text-muted py-3">No leave credits found for <?= date('Y') ?>. Contact HR.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- My Leave Request History (tracking) -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-history mr-1"></i> My Leave Requests — Tracking</h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm table-hover mb-0" id="leaveTrackTable">
                <thead class="thead-light">
                  <tr>
                    <th>Leave type</th><th>From</th><th>To</th>
                    <th class="text-center">Days</th><th>Status</th>
                    <th>Reason</th><th>Filed</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $stat_badge = array('Pending'=>'warning','Approved'=>'success','Rejected'=>'danger');
                  foreach ($my_leave_requests as $lr):
                    $sb = isset($stat_badge[$lr['status']]) ? $stat_badge[$lr['status']] : 'secondary';
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($lr['leave_type']) ?></td>
                    <td><?= date('M d, Y',strtotime($lr['date_from'])) ?></td>
                    <td><?= date('M d, Y',strtotime($lr['date_to'])) ?></td>
                    <td class="text-center"><?= $lr['days_applied'] ?></td>
                    <td>
                      <span class="badge badge-<?= $sb ?>"><?= $lr['status'] ?></span>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($lr['reason'] ?: '—') ?></td>
                    <td style="font-size:11px;color:#aaa;"><?= date('M d, Y',strtotime($lr['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($my_leave_requests)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No leave requests yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      <!-- ─── TAB 3: TRAINING / SEMINARS ─── -->
      <div class="tab-pane fade <?= $active_tab==='training'?'show active':'' ?>" id="tab-training">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title"><i class="fas fa-chalkboard-teacher mr-1 text-warning"></i> Training &amp; Seminar Records</h3>
              <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#addTrainingModal">
                <i class="fas fa-plus mr-1"></i> Add Entry
              </button>
            </div>
            <div class="card-body">
              <div class="alert alert-info" style="font-size:12px;">
                <i class="fas fa-info-circle mr-1"></i>
                New entries are subject to HR approval before being consolidated/reflected in your official record.
              </div>
              <table class="table table-sm table-bordered table-hover" id="trainingTable">
                <thead class="thead-light">
                  <tr>
                    <th>Training / Seminar Title</th>
                    <th>Date</th>
                    <th>Institution / Training Center</th>
                    <th>Conducted by</th>
                    <th class="text-center">Level</th>
                    <th class="text-right">Points</th>
                    <th class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($trainings as $tr):
                    $lvl = $tr['level'] ?? 'Local';
                    $lvl_class = 'level-badge-'.strtolower(str_replace(' ','',$lvl));
                    $tr_stat = $tr['status'] ?? 'Approved';
                    $tr_sb   = $tr_stat === 'Pending' ? 'warning' : 'success';
                  ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($tr['training_title'] ?? $tr['title'] ?? '—') ?></strong></td>
                    <td style="font-size:12px;">
                      <?php
                      $df = $tr['date_from'] ?? $tr['training_date'] ?? null;
                      $dt = $tr['date_to'] ?? null;
                      echo $df ? date('M d, Y',strtotime($df)) : '—';
                      if ($dt && $dt !== $df) echo ' – '.date('M d, Y',strtotime($dt));
                      ?>
                    </td>
                    <td><?= htmlspecialchars($tr['institution'] ?? $tr['location'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($tr['conductor'] ?? $tr['trainer'] ?? '—') ?></td>
                    <td class="text-center">
                      <span class="badge <?= $lvl_class ?>" style="font-size:10px;"><?= htmlspecialchars($lvl) ?></span>
                    </td>
                    <td class="text-right"><?= number_format($tr['equivalent_points'] ?? $tr['duration_hours'] ?? 0, 1) ?></td>
                    <td class="text-center">
                      <span class="badge badge-<?= $tr_sb ?>" style="font-size:10px;"><?= $tr_stat ?></span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($trainings)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">
                      <i class="fas fa-chalkboard fa-2x d-block mb-2" style="opacity:.2;"></i>
                      No training records yet. Use the "Add Entry" button to add your first record.
                    </td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── TAB 4: DOCUMENTS ─── -->
      <div class="tab-pane fade <?= $active_tab==='documents'?'show active':'' ?>" id="tab-documents">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title"><i class="fas fa-folder-open mr-1 text-secondary"></i> 201 Documents</h3>
              <button class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#uploadDocModal">
                <i class="fas fa-upload mr-1"></i> Add Document
              </button>
            </div>
            <div class="card-body">
              <div class="alert alert-info" style="font-size:12px;">
                <i class="fas fa-info-circle mr-1"></i>
                Uploaded documents are subject to HR approval before being reflected in your official 201 file.
              </div>

              <?php
              // Group docs by type
              $doc_groups = array();
              foreach ($docs as $doc) {
                  $type = $doc['document_type'];
                  $doc_groups[$type][] = $doc;
              }
              ?>

              <?php if (empty($docs)): ?>
                <div class="text-center text-muted py-5">
                  <i class="fas fa-folder-open fa-3x d-block mb-2" style="opacity:.2;"></i>
                  No documents uploaded yet. Use the "Add Document" button above.
                </div>
              <?php else: ?>
                <?php foreach ($doc_groups as $type => $group_docs): ?>
                <div class="doc-group-header">
                  <i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($type) ?>
                  <span class="badge badge-light ml-1"><?= count($group_docs) ?></span>
                </div>
                <?php foreach ($group_docs as $doc):
                  $ext      = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                  $icon_map = array('pdf'=>'fa-file-pdf','jpg'=>'fa-file-image','jpeg'=>'fa-file-image',
                                    'png'=>'fa-file-image','doc'=>'fa-file-word','docx'=>'fa-file-word');
                  $icon = isset($icon_map[$ext]) ? $icon_map[$ext] : 'fa-file';
                  $dstat = $doc['status'] ?? 'Approved';
                ?>
                <div class="doc-item">
                  <div class="doc-icon"><i class="fas <?= $icon ?>"></i></div>
                  <div>
                    <div class="doc-name"><?= htmlspecialchars($doc['file_name']) ?></div>
                    <div class="doc-meta"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></div>
                  </div>
                  <div class="doc-status">
                    <?php if ($dstat === 'Pending'): ?>
                      <span class="pending-badge"><i class="fas fa-clock mr-1"></i>Pending HR</span>
                    <?php else: ?>
                      <span class="approved-badge"><i class="fas fa-check mr-1"></i>Approved</span>
                    <?php endif; ?>
                  </div>
                  <a href="../uploads/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-xs btn-info">
                    <i class="fas fa-eye"></i>
                  </a>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /tab-content -->
  </div></div>
</div>

<!-- ═══════════════════════════════════════════
     MODALS
═══════════════════════════════════════════ -->

<!-- Request Leave Modal -->
<div class="modal fade" id="leaveRequestModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-calendar-plus mr-2"></i>Request Leave</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="form_action" value="request_leave">
        <div class="modal-body">
          <div class="form-group">
            <label>Leave type <span class="text-danger">*</span></label>
            <select name="leave_type" class="form-control" required>
              <?php foreach ($leave_types_list as $lt): ?>
                <option><?= $lt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label>Date from <span class="text-danger">*</span></label>
                <input type="date" name="date_from" id="lr_from" class="form-control" required onchange="calcLeaveDays()">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Date to <span class="text-danger">*</span></label>
                <input type="date" name="date_to" id="lr_to" class="form-control" required onchange="calcLeaveDays()">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Number of days</label>
            <input type="number" name="days_applied" id="lr_days" class="form-control" step="0.5" min="0.5" value="1">
          </div>
          <div class="form-group">
            <label>Reason</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="Brief explanation..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane mr-1"></i> Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Training Modal -->
<div class="modal fade" id="addTrainingModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-chalkboard-teacher mr-2"></i>Add Training / Seminar Entry</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="form_action" value="add_training">
        <div class="modal-body">
          <div class="alert alert-info" style="font-size:12px;">
            <i class="fas fa-info-circle mr-1"></i>
            Your entry will be submitted to HR for approval before appearing in your official record.
          </div>
          <div class="form-group">
            <label>Training / Seminar Title <span class="text-danger">*</span></label>
            <input type="text" name="training_title" class="form-control" required placeholder="e.g. Child Protection Policy Training">
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Date from <span class="text-danger">*</span></label>
                <input type="date" name="training_date_from" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Date to</label>
                <input type="date" name="training_date_to" class="form-control">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Training Center / Institution</label>
                <input type="text" name="institution" class="form-control" placeholder="e.g. DepEd, CHED, Private Institution">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Conducted by / Initiated by</label>
                <input type="text" name="conductor" class="form-control" placeholder="e.g. DepEd Region III">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Level</label>
                <select name="training_level" class="form-control">
                  <option>International</option>
                  <option>National</option>
                  <option selected>Local</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Equivalent Points / Hours</label>
                <input type="number" name="equivalent_points" class="form-control" step="0.5" min="0" value="0">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane mr-1"></i> Submit for Approval</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title"><i class="fas fa-upload mr-2"></i>Upload Document</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <input type="hidden" name="form_action" value="upload_doc">
        <div class="modal-body">
          <div class="alert alert-info" style="font-size:12px;">
            <i class="fas fa-info-circle mr-1"></i>
            Documents are subject to HR approval before being reflected in your official 201 file.
          </div>
          <div class="form-group">
            <label>Document type <span class="text-danger">*</span></label>
            <select name="document_type" class="form-control" required>
              <?php
              $doc_types = array(
                'Resume / CV','Employment contract','NBI clearance',
                'Birth certificate','Diploma / TOR','2x2 Photo / ID',
                'SSS ID','PhilHealth ID','Pag-IBIG ID','TIN ID',
                'Medical certificate','COE (Certificate of Employment)',
                'Separation documents','Other'
              );
              foreach ($doc_types as $dt): ?>
                <option><?= $dt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>File <span class="text-danger">*</span></label>
            <div class="custom-file">
              <input type="file" class="custom-file-input" name="doc_file" id="docFileInput"
                     accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
              <label class="custom-file-label" for="docFileInput">Choose file...</label>
            </div>
            <small class="text-muted">Allowed: PDF, JPG, PNG, DOC, DOCX. Max 5MB.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    // DataTables
    if ($('#leaveTrackTable').length) {
        $('#leaveTrackTable').DataTable({ pageLength:10, order:[[6,'desc']] });
    }
    if ($('#trainingTable').length) {
        $('#trainingTable').DataTable({ pageLength:15, order:[[1,'desc']] });
    }

    // File input label update
    $('#docFileInput').on('change', function(){
        var name = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').text(name || 'Choose file...');
    });

    // Keep tab active on page load from query string
    var hash = '<?= $active_tab ?>';
    if (hash && hash !== 'profile') {
        $('#profileTabs a[href="#tab-' + hash + '"]').tab('show');
    }

    // Update hash on tab switch for back-nav
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
        var target = $(e.target).attr('href').replace('#tab-','');
        if (history.replaceState) {
            history.replaceState(null, null, '?tab=' + target);
        }
    });
});

function calcLeaveDays() {
    var from = document.getElementById('lr_from').value;
    var to   = document.getElementById('lr_to').value;
    if (from && to) {
        var d1   = new Date(from), d2 = new Date(to);
        var diff = Math.ceil((d2 - d1) / (1000*60*60*24)) + 1;
        if (diff > 0) document.getElementById('lr_days').value = diff;
    }
}
</script>
</body>
</html>