<?php
// ============================================
// FILE: pages/employee_id_generator.php
// Format: 000-MMDD-SS
//   000  = 3-digit department/office numeric code
//   MMDD = employee birth month+day (4 digits)
//   SS   = 2-digit sequence (auto-increment per combo)
// Example: 203-0415-01
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR);

// ── Helper ──────────────────────────────────────────────────────
function dept_num_code($dept) {
    $raw = preg_replace('/[^0-9]/','', $dept['code'] ?? '');
    return strlen($raw) > 0
        ? str_pad(substr($raw,0,3), 3, '0', STR_PAD_LEFT)
        : str_pad($dept['id'],      3, '0', STR_PAD_LEFT);
}

$departments  = db_fetchall($pdo,"SELECT id,name,code FROM departments ORDER BY name");
$generated_id = null;
$preview      = null;
$error        = '';
$success      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action'] ?? '');

    if ($action === 'generate') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $dob     = clean_date($_POST['date_of_birth'] ?? '');

        if (!$dept_id) { $error = 'Please select a department/office.'; }
        elseif (!$dob) { $error = 'Please enter the employee\'s date of birth.'; }
        else {
            $dept = db_fetch($pdo,"SELECT * FROM departments WHERE id=?",array($dept_id));
            if (!$dept) { $error = 'Department not found.'; }
            else {
                $code    = dept_num_code($dept);
                $dob_obj = new DateTime($dob);
                $mmdd    = $dob_obj->format('md');

                $last = db_value($pdo,
                    "SELECT MAX(CAST(SUBSTRING_INDEX(employee_id,'-',-1) AS UNSIGNED))
                     FROM employees WHERE employee_id LIKE ?",
                    array($code.'-'.$mmdd.'-%')
                );
                $seq  = str_pad(($last ? (int)$last+1 : 1), 2, '0', STR_PAD_LEFT);
                $generated_id = $code.'-'.$mmdd.'-'.$seq;

                $_SESSION['prefilled_emp_id']  = $generated_id;
                $_SESSION['prefilled_dept_id'] = $dept_id;
                $preview = array(
                    'dept_name'=>$dept['name'],'dept_code'=>$code,
                    'dob_fmt'=>$dob_obj->format('F d, Y'),
                    'mmdd'=>$mmdd,'seq'=>$seq,'full_id'=>$generated_id,
                );
            }
        }
    }

    if ($action === 'check') {
        $chk = clean_string($_POST['check_id'] ?? '', 20);
        $exists = db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employee_id=?",array($chk));
        if ($exists) $error   = '"'.$chk.'" is already assigned to an employee.';
        else          $success = '"'.$chk.'" is available.';
    }
}

$existing = db_fetchall($pdo,
    "SELECT e.employee_id,e.full_name,e.date_of_birth,d.name AS dept_name
     FROM employees e LEFT JOIN departments d ON e.department_id=d.id
     WHERE e.employee_id REGEXP '^[0-9]{3}-[0-9]{4}-[0-9]{2}$'
     ORDER BY e.employee_id DESC LIMIT 12"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Employee ID Generator | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .id-display{font-size:46px;font-weight:800;letter-spacing:6px;font-family:'Courier New',monospace;
                background:#d4edda;border-radius:12px;padding:20px 10px;text-align:center;}
    .id-dept{color:#084298;}.id-mmdd{color:#664d03;}.id-seq{color:#0a3622;}.id-sep{color:#adb5bd;font-weight:300;}
    .fp{display:inline-block;padding:4px 12px;border-radius:6px;font-weight:700;font-family:monospace;font-size:15px;margin:2px;}
    .fp-d{background:#cfe2ff;color:#084298;}.fp-m{background:#fff3cd;color:#664d03;}.fp-s{background:#d1e7dd;color:#0a3622;}
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Employee ID Generator</h1></div></div>
  <div class="content"><div class="container-fluid">

    <?php if ($error):   ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="row">
      <!-- LEFT -->
      <div class="col-md-6">

        <!-- Format card -->
        <div class="card card-outline card-info mb-3">
          <div class="card-header bg-light"><h3 class="card-title"><i class="fas fa-id-card mr-1 text-info"></i> Employee ID Format</h3></div>
          <div class="card-body">
            <div class="text-center mb-3">
              <div style="font-size:26px;font-weight:700;font-family:monospace;">
                <span class="fp fp-d">203</span><span style="color:#aaa;font-weight:300;">-</span>
                <span class="fp fp-m">0415</span><span style="color:#aaa;font-weight:300;">-</span>
                <span class="fp fp-s">01</span>
              </div>
              <div style="display:flex;justify-content:space-around;font-size:11px;color:#6c757d;margin-top:6px;">
                <span>Dept Code</span><span>Birth MMDD</span><span>Sequence</span>
              </div>
            </div>
            <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
              <tr><td width="50"><span class="fp fp-d" style="font-size:10px;padding:2px 6px;">000</span></td>
                  <td><strong>3-digit department/office code</strong>. Set in the numeric code field of each department.</td></tr>
              <tr><td><span class="fp fp-m" style="font-size:10px;padding:2px 6px;">MMDD</span></td>
                  <td><strong>Birth month and day</strong> (e.g. April 15 → <code>0415</code>, December 3 → <code>1203</code>).</td></tr>
              <tr><td><span class="fp fp-s" style="font-size:10px;padding:2px 6px;">SS</span></td>
                  <td><strong>Auto-sequence</strong> — increments for each new employee with the same dept+birthday combo.</td></tr>
            </table>
          </div>
        </div>

        <!-- Generator form -->
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-magic mr-1"></i> Generate ID</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="generate">

              <div class="form-group">
                <label class="font-weight-bold">Department / Office <span class="text-danger">*</span></label>
                <select name="dept_id" id="deptSel" class="form-control" required onchange="updatePrev()">
                  <option value="">Select department...</option>
                  <?php foreach ($departments as $d):
                    $c = dept_num_code($d);
                  ?>
                  <option value="<?= $d['id'] ?>" data-code="<?= $c ?>" data-name="<?= htmlspecialchars($d['name']) ?>">
                    [<?= $c ?>] <?= htmlspecialchars($d['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Code shown in [brackets]. <a href="departments.php" target="_blank">Edit dept codes →</a></small>
              </div>

              <div class="form-group">
                <label class="font-weight-bold">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" name="date_of_birth" id="dobIn" class="form-control" required onchange="updatePrev()">
                <small class="text-muted">Used to determine the MMDD portion.</small>
              </div>

              <!-- Live preview -->
              <div class="p-3 mb-3 text-center" style="background:#f8f9fa;border-radius:10px;">
                <div style="font-size:11px;color:#6c757d;margin-bottom:4px;">Preview (sequence assigned on generate)</div>
                <div style="font-size:28px;font-weight:800;font-family:monospace;">
                  <span class="id-dept" id="pDept">???</span>
                  <span class="id-sep">-</span>
                  <span class="id-mmdd" id="pMmdd">????</span>
                  <span class="id-sep">-</span>
                  <span class="id-seq">??</span>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i class="fas fa-magic mr-1"></i> Generate Employee ID
              </button>
            </form>
          </div>
        </div>

        <!-- Check form -->
        <div class="card card-outline card-secondary">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-search mr-1"></i> Check Availability</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="check">
              <div class="input-group">
                <input type="text" name="check_id" class="form-control" placeholder="e.g. 203-0415-01" maxlength="15">
                <div class="input-group-append">
                  <button type="submit" class="btn btn-secondary"><i class="fas fa-check mr-1"></i> Check</button>
                </div>
              </div>
              <small class="text-muted">Format: 000-0000-00</small>
            </form>
          </div>
        </div>

      </div><!-- /left -->

      <!-- RIGHT -->
      <div class="col-md-6">

        <!-- Result -->
        <?php if ($generated_id && $preview): ?>
        <div class="card card-success mb-3">
          <div class="card-header bg-success text-white">
            <h3 class="card-title"><i class="fas fa-check-circle mr-1"></i> Generated ID</h3>
          </div>
          <div class="card-body">
            <div class="id-display mb-3">
              <span class="id-dept"><?= $preview['dept_code'] ?></span>
              <span class="id-sep">-</span>
              <span class="id-mmdd"><?= $preview['mmdd'] ?></span>
              <span class="id-sep">-</span>
              <span class="id-seq"><?= $preview['seq'] ?></span>
            </div>
            <div class="row text-center mb-3">
              <div class="col-4">
                <div style="font-size:10px;color:#aaa;text-transform:uppercase;">Dept</div>
                <div style="font-size:24px;font-weight:800;color:#084298;font-family:monospace;"><?= $preview['dept_code'] ?></div>
                <div style="font-size:11px;color:#6c757d;"><?= htmlspecialchars($preview['dept_name']) ?></div>
              </div>
              <div class="col-4">
                <div style="font-size:10px;color:#aaa;text-transform:uppercase;">Birth MMDD</div>
                <div style="font-size:24px;font-weight:800;color:#664d03;font-family:monospace;"><?= $preview['mmdd'] ?></div>
                <div style="font-size:11px;color:#6c757d;"><?= $preview['dob_fmt'] ?></div>
              </div>
              <div class="col-4">
                <div style="font-size:10px;color:#aaa;text-transform:uppercase;">Sequence</div>
                <div style="font-size:24px;font-weight:800;color:#0a3622;font-family:monospace;"><?= $preview['seq'] ?></div>
                <div style="font-size:11px;color:#6c757d;">Hire #<?= (int)$preview['seq'] ?></div>
              </div>
            </div>
            <div class="d-flex justify-content-center" style="gap:10px;">
              <button id="copyBtn" onclick="navigator.clipboard.writeText('<?= $generated_id ?>');document.getElementById('copyBtn').innerHTML='<i class=\'fas fa-check mr-1\'></i>Copied!';"
                      class="btn btn-outline-success">
                <i class="fas fa-copy mr-1"></i> Copy
              </button>
              <a href="add_employee.php" class="btn btn-success">
                <i class="fas fa-user-plus mr-1"></i> Use in Add Employee
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Dept code reference -->
        <div class="card card-outline card-warning mb-3">
          <div class="card-header bg-light"><h3 class="card-title"><i class="fas fa-building mr-1 text-warning"></i> Department Code Reference</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th style="width:80px;">Code</th><th>Department / Office</th></tr></thead>
              <tbody>
                <?php foreach ($departments as $d):
                  $c   = dept_num_code($d);
                  $raw = preg_replace('/[^0-9]/','', $d['code']??'');
                ?>
                <tr>
                  <td><code style="font-size:16px;font-weight:700;"><?= $c ?></code></td>
                  <td><?= htmlspecialchars($d['name']) ?>
                    <?php if (!$raw): ?>
                      <span class="badge badge-warning ml-1" style="font-size:9px;">No numeric code — using ID</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="px-3 py-2" style="background:#fffbea;font-size:11px;color:#856404;border-top:1px solid #dee2e6;">
              <i class="fas fa-exclamation-triangle mr-1"></i>
              Departments without a numeric code use their database ID. Set codes in <a href="departments.php" target="_blank">Departments</a>.
            </div>
          </div>
        </div>

        <!-- Recent IDs -->
        <?php if (!empty($existing)): ?>
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i> Recently Assigned (this format)</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Employee ID</th><th>Name</th><th>Dept</th></tr></thead>
              <tbody>
                <?php foreach ($existing as $ex): ?>
                <tr>
                  <td><code style="font-weight:700;font-size:13px;"><?= htmlspecialchars($ex['employee_id']) ?></code></td>
                  <td><?= htmlspecialchars($ex['full_name']) ?></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($ex['dept_name']??'—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /right -->
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
function updatePrev(){
    var sel=document.getElementById('deptSel');
    var dob=document.getElementById('dobIn').value;
    var code=sel&&sel.value?(sel.options[sel.selectedIndex].getAttribute('data-code')||'???'):'???';
    document.getElementById('pDept').textContent=code;
    if(dob){
        var d=new Date(dob+'T00:00:00');
        var mm=String(d.getMonth()+1).padStart(2,'0');
        var dd=String(d.getDate()).padStart(2,'0');
        document.getElementById('pMmdd').textContent=mm+dd;
    } else { document.getElementById('pMmdd').textContent='????'; }
}
$(function(){
    if(document.getElementById('deptSel').value) updatePrev();
    if(document.getElementById('dobIn').value) updatePrev();
});
</script>
</body>
</html>