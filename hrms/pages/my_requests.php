<?php
// ============================================
// FILE: pages/my_requests.php
// Employee self-service — submit any request
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;
if (!$emp_id) {
    echo not_linked_error(); exit;
}

$emp = db_fetch($pdo,
    "SELECT e.*, ec.category AS classification_category
     FROM employees e
     LEFT JOIN employee_classifications ec ON e.classification_id = ec.id
     WHERE e.id=?",
    array($emp_id)
);

// Determine teaching/non-teaching
$emp_class = 'Non-Teaching';
if ($emp) {
    $cat = (isset($emp['classification_category']) ? $emp['classification_category'] : '');
    if (stripos($cat, 'Teaching') !== false && stripos($cat, 'Non') === false) {
        $emp_class = 'Teaching';
    }
    // Also check parent classification
    if ($emp_class === 'Non-Teaching' && $emp['classification_id']) {
        $parent = db_fetch($pdo,
            "SELECT p.category FROM employee_classifications c
             JOIN employee_classifications p ON c.parent_id = p.id
             WHERE c.id=?", array($emp['classification_id'])
        );
        if ($parent && stripos($parent['category'], 'Teaching') !== false && stripos($parent['category'], 'Non') === false) {
            $emp_class = 'Teaching';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $type_id     = clean_int($_POST['request_type_id']);
    $date_from   = !empty($_POST['date_from']) ? clean_date($_POST['date_from']) : null;
    $date_to     = !empty($_POST['date_to'])   ? clean_date($_POST['date_to'])   : null;
    $days        = !empty($_POST['days_applied']) ? clean_float($_POST['days_applied']) : null;
    $time_from   = !empty($_POST['time_from']) ? $_POST['time_from'] : null;
    $time_to     = !empty($_POST['time_to'])   ? $_POST['time_to']   : null;
    $purpose     = clean_string($_POST['purpose'], 1000);
    $attach_path = null;

    if (empty($purpose)) {
        prg_redirect_error('my_requests.php', 'Purpose / Reason is required.');
    }

    // Handle file attachment
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed = array('pdf','jpg','jpeg','png','doc','docx');
        if (in_array($ext, $allowed) && $_FILES['attachment']['size'] <= 5*1024*1024) {
            $dir = '../uploads/requests/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'req_'.$emp_id.'_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir.$fname)) {
                $attach_path = $fname;
            }
        }
    }

    // Generate reference number
    $ref_no = 'REQ-'.date('Ymd').'-'.str_pad(mt_rand(1,9999), 4, '0', STR_PAD_LEFT);

    // Insert request
    db_run($pdo,
        "INSERT INTO employee_requests
         (reference_no, employee_id, request_type_id, date_from, date_to, days_applied,
          time_from, time_to, purpose, attachment_path, status, current_step, employee_classification)
         VALUES (?,?,?,?,?,?,?,?,?,?,'Pending',1,?)",
        array($ref_no, $emp_id, $type_id, $date_from, $date_to, $days,
              $time_from, $time_to, $purpose, $attach_path, $emp_class)
    );
    $req_id = db_lastid($pdo);

    // Build approval steps from workflow templates
    $steps = db_fetchall($pdo,
        "SELECT * FROM approval_templates
         WHERE request_type_id=?
           AND (employee_classification=? OR employee_classification='All')
         ORDER BY step_order",
        array($type_id, $emp_class)
    );

    // Fallback: try 'All' only
    if (empty($steps)) {
        $steps = db_fetchall($pdo,
            "SELECT * FROM approval_templates
             WHERE request_type_id=? AND employee_classification='All'
             ORDER BY step_order",
            array($type_id)
        ) ?: array();
    }

    if (!empty($steps)) {
        // Use template steps
        foreach ($steps as $step) {
            db_run($pdo,
                "INSERT INTO request_approvals
                 (request_id, step_order, role_required, step_label, action_label, can_proceed_after_this)
                 VALUES (?,?,?,?,?,?)",
                array($req_id, $step['step_order'], $step['role_required'],
                      $step['step_label'], $step['action_label'], $step['can_proceed_after_this'])
            );
        }
    } else {
        // No template found — insert a default 3-step approval flow
        // so every request always has a visible approval trail
        $default_steps = array(
            array(1,'dept_secretary', 'Department Secretary Review',    'Received & Endorsed', 0),
            array(2,'manager',        'Department Head Approval',        'Approved',            0),
            array(3,'hr',             'HR Final Processing',             'Processed & Filed',   0),
        );
        foreach ($default_steps as $ds) {
            db_run($pdo,
                "INSERT INTO request_approvals
                 (request_id, step_order, role_required, step_label, action_label, can_proceed_after_this)
                 VALUES (?,?,?,?,?,?)",
                array($req_id, $ds[0], $ds[1], $ds[2], $ds[3], $ds[4])
            );
        }
    }

    // Status stays 'Pending' — Pending Clarification is set only when returned by approver

    prg_redirect('my_approvals.php', "Request $ref_no submitted. Track it under Approvals → Pending.");
}

$request_types = db_fetchall($pdo,
    "SELECT * FROM request_types WHERE is_active=1 ORDER BY sort_order, name"
);

// Leave credits for reference
$credits = db_fetch($pdo,
    "SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",
    array($emp_id)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Request | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .leave-credit-badge { background:#e9f7ef; border-radius:6px; padding:4px 10px;
                          font-size:12px; color:#155724; margin-right:6px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">New Request</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">New Request</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="row">
      <div class="col-md-7">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Submit a Request</h3></div>
          <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
              <?php csrf_field(); ?>

              <!-- Request Type -->
              <div class="form-group">
                <label>Type of Request <span class="text-danger">*</span></label>
                <select name="request_type_id" id="reqType" class="form-control" required
                        onchange="onTypeChange(this)">
                  <option value="">-- Select request type --</option>
                  <?php
                  $last_cat = '';
                  foreach ($request_types as $rt):
                    if ($rt['category'] !== $last_cat) {
                        if ($last_cat !== '') echo '</optgroup>';
                        echo '<optgroup label="── '.$rt['category'].' ──">';
                        $last_cat = $rt['category'];
                    }
                  ?>
                    <option value="<?= $rt['id'] ?>"
                            data-requires-days="<?= $rt['requires_days'] ?>"
                            data-requires-time="<?= $rt['requires_time'] ?>"
                            data-category="<?= htmlspecialchars($rt['category']) ?>">
                      <?= htmlspecialchars($rt['name']) ?>
                    </option>
                  <?php endforeach; ?>
                  <?php if ($last_cat) echo '</optgroup>'; ?>
                </select>
              </div>

              <!-- Date range (for leave/travel) -->
              <div id="dateGroup" style="display:none;">
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Date from</label>
                      <input type="date" name="date_from" id="dateFrom" class="form-control"
                             onchange="calcDays()">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Date to</label>
                      <input type="date" name="date_to" id="dateTo" class="form-control"
                             onchange="calcDays()">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Number of days</label>
                      <input type="number" name="days_applied" id="daysApplied"
                             class="form-control" step="0.5" min="0.5">
                    </div>
                  </div>
                </div>
              </div>

              <!-- Time range (for overtime) -->
              <div id="timeGroup" style="display:none;">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Time from</label>
                      <input type="time" name="time_from" class="form-control">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Time to</label>
                      <input type="time" name="time_to" class="form-control">
                    </div>
                  </div>
                </div>
              </div>

              <!-- Purpose / Reason -->
              <div class="form-group">
                <label>Purpose / Reason <span class="text-danger">*</span></label>
                <textarea name="purpose" class="form-control" rows="4" required
                          placeholder="Describe your request in detail..."></textarea>
              </div>

              <!-- Attachment -->
              <div class="form-group">
                <label>Supporting document <small class="text-muted">(PDF, JPG, PNG, DOC — max 5MB)</small></label>
                <input type="file" name="attachment" class="form-control-file"
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
              </div>

              <!-- Classification notice -->
              <div class="alert alert-info" style="font-size:12px;">
                <i class="fas fa-info-circle mr-1"></i>
                Your classification: <strong><?= $emp_class ?></strong>.
                The approval flow will be determined based on your request type and classification.
              </div>

              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-paper-plane mr-1"></i> Submit Request
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Leave credits reference panel -->
      <div class="col-md-5">
        <?php if ($credits): ?>
        <div class="card">
          <div class="card-header"><h3 class="card-title">My Leave Credits — <?= date('Y') ?></h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Leave type</th><th class="text-right">Total</th><th class="text-right">Used</th><th class="text-right">Balance</th></tr></thead>
              <tbody>
                <?php
                $lv_map = array(
                  array('Vacation leave',    'vacation_leave',    'vacation_used'),
                  array('Sick leave',        'sick_leave',        'sick_used'),
                  array('Emergency leave',   'emergency_leave',   'emergency_used'),
                  array('Maternity leave',   'maternity_leave',   'maternity_used'),
                  array('Paternity leave',   'paternity_leave',   'paternity_used'),
                  array('Solo parent leave', 'solo_parent_leave', 'solo_parent_used'),
                );
                foreach ($lv_map as $lv):
                  $tot  = (float)((isset($credits[$lv[1]]) ? (float)$credits[$lv[1]] : 0));
                  $used = (float)((isset($credits[$lv[2]]) ? (float)$credits[$lv[2]] : 0));
                  $bal  = $tot - $used;
                ?>
                <tr>
                  <td><?= $lv[0] ?></td>
                  <td class="text-right"><?= number_format($tot,1) ?></td>
                  <td class="text-right"><?= number_format($used,1) ?></td>
                  <td class="text-right <?= $bal < 0 ? 'text-danger font-weight-bold' : 'text-success' ?>">
                    <?= number_format($bal,1) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header"><h3 class="card-title">What happens next?</h3></div>
          <div class="card-body" style="font-size:13px;color:#555;">
            <ol style="padding-left:18px;margin:0;line-height:2;">
              <li>Your request is submitted and routed automatically.</li>
              <li>Each approver is notified in sequence.</li>
              <li>You can track every step in <strong>Request History</strong>.</li>
              <li>For leave requests: once it reaches the <strong>VP for Administration</strong>, you may already proceed — you do not need to wait for the President's final signature.</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
function onTypeChange(sel) {
    var opt     = sel.options[sel.selectedIndex];
    var reqDays = opt ? parseInt(opt.getAttribute('data-requires-days')) : 0;
    var reqTime = opt ? parseInt(opt.getAttribute('data-requires-time')) : 0;
    document.getElementById('dateGroup').style.display = reqDays ? 'block' : 'none';
    document.getElementById('timeGroup').style.display = reqTime ? 'block' : 'none';
    // Make date fields required when shown
    document.getElementById('dateFrom').required = !!reqDays;
    document.getElementById('dateTo').required   = !!reqDays;
}
function calcDays() {
    var from = document.getElementById('dateFrom').value;
    var to   = document.getElementById('dateTo').value;
    if (from && to) {
        var d1 = new Date(from), d2 = new Date(to);
        var diff = Math.ceil((d2 - d1) / (1000*60*60*24)) + 1;
        if (diff > 0) document.getElementById('daysApplied').value = diff;
    }
}
</script>
</body></html>