<?php
// ============================================
// FILE: pages/add_employee.php — v3 FIXED
// Fixed: duplicate employment_status column in INSERT
// Fixed: PDO error display for debugging
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $employee_id       = trim($_POST['employee_id']);
    $full_name         = trim($_POST['full_name']);
    $dob               = !empty($_POST['date_of_birth'])   ? $_POST['date_of_birth']   : null;
    $gender            = !empty($_POST['gender'])           ? $_POST['gender']           : null;
    $civil             = !empty($_POST['civil_status'])     ? $_POST['civil_status']     : null;
    $nationality       = !empty($_POST['nationality'])      ? trim($_POST['nationality']) : 'Filipino';
    $address           = trim($_POST['address']);
    $contact           = trim($_POST['contact_number']);
    $email             = trim($_POST['email_address']);
    $emergency         = trim($_POST['emergency_contact']);
    $sss               = trim($_POST['sss_number']);
    $philhealth        = trim($_POST['philhealth_number']);
    $pagibig           = trim($_POST['pagibig_number']);
    $tin               = trim($_POST['tin_number']);

    // FK-safe: convert 0 to null
    $dept_id           = (int)$_POST['department_id']        > 0 ? (int)$_POST['department_id']        : null;
    $pos_id            = (int)$_POST['position_id']          > 0 ? (int)$_POST['position_id']          : null;
    $supervisor_id     = (int)$_POST['supervisor_id']        > 0 ? (int)$_POST['supervisor_id']        : null;
    $classification_id = (int)$_POST['classification_id']   > 0 ? (int)$_POST['classification_id']   : null;
    $rank_id           = (int)$_POST['rank_id']              > 0 ? (int)$_POST['rank_id']              : null;
    $emp_type_id       = (int)$_POST['employment_type_id']  > 0 ? (int)$_POST['employment_type_id']  : null;
    $emp_status_id     = (int)$_POST['employment_status_id'] > 0 ? (int)$_POST['employment_status_id'] : null;

    // Text versions for backward compatibility columns
    $emp_type_text   = trim($_POST['employment_type_text']);
    $emp_status_text = trim($_POST['employment_status_text']);

    $is_part_time  = isset($_POST['is_part_time']) ? 1 : 0;
    $date_hired    = !empty($_POST['date_hired'])   ? $_POST['date_hired'] : null;
    $location      = trim($_POST['work_location']);
    $salary_grade  = trim($_POST['salary_grade']);
    $basic_salary  = (float)$_POST['basic_salary'] > 0 ? (float)$_POST['basic_salary'] : null;

    // Validate required
    if (empty($employee_id) || empty($full_name)) {
        prg_redirect_error('add_employee.php', 'Employee ID and Full Name are required.');
    }

    // Duplicate employee_id check
    if (db_value($pdo, "SELECT COUNT(*) FROM employees WHERE employee_id=?", array($employee_id))) {
        prg_redirect_error('add_employee.php', "Employee ID '$employee_id' already exists.");
    }

    // ── INSERT — no duplicate columns ───────────────────────────
    db_run($pdo,
        "INSERT INTO employees (
            employee_id, full_name, date_of_birth, gender, civil_status, nationality,
            address, contact_number, email_address, emergency_contact,
            sss_number, philhealth_number, pagibig_number, tin_number,
            department_id, position_id, supervisor_id,
            classification_id, rank_id,
            employment_type_id, employment_status_id,
            employment_type, employment_status,
            is_part_time, date_hired,
            work_location, salary_grade, basic_salary
        ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,
            ?,?,
            ?,?,
            ?,?,
            ?,?,?
        )",
        array(
            $employee_id, $full_name, $dob, $gender, $civil, $nationality,
            $address, $contact, $email, $emergency,
            $sss, $philhealth, $pagibig, $tin,
            $dept_id, $pos_id, $supervisor_id,
            $classification_id, $rank_id,
            $emp_type_id, $emp_status_id,
            $emp_type_text ?: 'Active',
            $emp_status_text ?: 'Active',
            $is_part_time, $date_hired,
            $location, $salary_grade, $basic_salary
        )
    );

    $new_id = db_lastid($pdo);

    // Auto-create leave credits
    db_run($pdo,
        "INSERT INTO leave_credits
         (employee_id, year, vacation_leave, sick_leave, emergency_leave,
          maternity_leave, paternity_leave, solo_parent_leave)
         VALUES (?, ?, 15, 15, 3, 105, 7, 7)",
        array($new_id, date('Y'))
    );

    // Set probationary review date if applicable
    if ($emp_type_text === 'Probationary' && $date_hired) {
        $is_teaching = false;
        if ($classification_id) {
            $cat_row = db_fetch($pdo,
                "SELECT category FROM employee_classifications WHERE id=?",
                array($classification_id)
            );
            if ($cat_row) {
                $is_teaching = ($cat_row['category'] === 'Teaching');
                if (!$is_teaching) {
                    // Check parent
                    $parent_cat = db_fetch($pdo,
                        "SELECT p.category FROM employee_classifications c
                         JOIN employee_classifications p ON c.parent_id=p.id
                         WHERE c.id=?",
                        array($classification_id)
                    );
                    if ($parent_cat) $is_teaching = ($parent_cat['category'] === 'Teaching');
                }
            }
        }
        $prob_months = $is_teaching ? 36 : 6;
        $review_date = date('Y-m-d', strtotime($date_hired . " +{$prob_months} months"));
        db_run($pdo,
            "UPDATE employees SET
                classification_status = 'Probationary',
                next_review_date = ?
             WHERE id = ?",
            array($review_date, $new_id)
        );
    }

    prg_redirect('employees.php', "Employee '$full_name' added successfully.");
}

// ── Load dropdowns ────────────────────────────────────────────────
$departments     = db_fetchall($pdo, "SELECT * FROM departments ORDER BY name");
$positions       = db_fetchall($pdo, "SELECT * FROM positions ORDER BY title");
$supervisors     = db_fetchall($pdo,
    "SELECT id, full_name, employee_id FROM employees
     WHERE employment_status='Active' ORDER BY full_name"
);

// Load from master tables if they exist, fallback to hardcoded
try {
    $classifications = db_fetchall($pdo,
        "SELECT * FROM employee_classifications WHERE is_active=1
         ORDER BY parent_id IS NOT NULL, sort_order, name"
    );
    $ranks = db_fetchall($pdo,
        "SELECT * FROM employee_ranks WHERE is_active=1
         ORDER BY category, sort_order, name"
    );
    $emp_types = db_fetchall($pdo,
        "SELECT * FROM employment_types WHERE is_active=1
         ORDER BY sort_order, name"
    );
    $emp_statuses = db_fetchall($pdo,
        "SELECT * FROM employment_statuses WHERE is_active=1
         ORDER BY sort_order, name"
    );
} catch (Exception $e) {
    $classifications = array();
    $ranks           = array();
    $emp_types       = array();
    $emp_statuses    = array();
}

// Fallback if DB tables don't exist yet
if (empty($emp_types)) {
    $emp_types = array(
        array('id'=>0,'name'=>'Regular/Permanent','probation_months'=>null),
        array('id'=>0,'name'=>'Probationary','probation_months'=>6),
        array('id'=>0,'name'=>'Contractual','probation_months'=>null),
        array('id'=>0,'name'=>'Substitute','probation_months'=>null),
        array('id'=>0,'name'=>'Part-time','probation_months'=>null),
        array('id'=>0,'name'=>'Project-based','probation_months'=>null),
    );
}
if (empty($emp_statuses)) {
    $emp_statuses = array(
        array('id'=>0,'name'=>'Active'),
        array('id'=>0,'name'=>'On Leave'),
        array('id'=>0,'name'=>'Inactive'),
    );
}

// Auto-suggest next Employee ID
$id_prefix = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='emp_id_prefix'") ?: 'EMP';
$id_digits = (int)(db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='emp_id_digits'") ?: 4);
$pfx       = $id_prefix . '-';
$last_num  = (int)(db_value($pdo,
    "SELECT MAX(CAST(SUBSTRING(employee_id, ?) AS UNSIGNED)) FROM employees WHERE employee_id LIKE ?",
    array(strlen($pfx) + 1, $pfx . '%')
) ?: 0);
$suggested_id = $pfx . str_pad($last_num + 1, $id_digits, '0', STR_PAD_LEFT);

$ranks_json = json_encode($ranks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Employee | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Add New Employee</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
            <li class="breadcrumb-item active">Add employee</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">
      <?= prg_flash() ?>

      <form method="POST" action="">
        <?php csrf_field(); ?>

        <!-- ── PERSONAL INFORMATION ── -->
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Personal information</h3></div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Employee ID <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input type="text" name="employee_id" id="empIdInput"
                           class="form-control" required
                           value="<?= htmlspecialchars($suggested_id) ?>">
                    <div class="input-group-append">
                      <button type="button" class="btn btn-outline-secondary"
                              onclick="document.getElementById('empIdInput').value='<?= htmlspecialchars($suggested_id) ?>'"
                              title="Reset to suggested ID">
                        <i class="fas fa-magic"></i>
                      </button>
                    </div>
                  </div>
                  <small class="text-muted">Next available: <strong><?= htmlspecialchars($suggested_id) ?></strong></small>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group">
                  <label>Full name <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control" required
                         placeholder="Last Name, First Name Middle Name">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Date of birth</label>
                  <input type="date" name="date_of_birth" class="form-control">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>Gender</label>
                  <select name="gender" class="form-control">
                    <option value="">Select...</option>
                    <option>Male</option><option>Female</option>
                    <option>Prefer not to say</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>Civil status</label>
                  <select name="civil_status" class="form-control">
                    <option value="">Select...</option>
                    <option>Single</option><option>Married</option>
                    <option>Widowed</option><option>Separated</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>Nationality</label>
                  <input type="text" name="nationality" class="form-control" value="Filipino">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>Contact number</label>
                  <input type="text" name="contact_number" class="form-control" placeholder="09XX-XXX-XXXX">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group"><label>Email address</label>
                  <input type="email" name="email_address" class="form-control">
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group"><label>Home address</label>
                  <input type="text" name="address" class="form-control">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>Emergency contact</label>
                  <input type="text" name="emergency_contact" class="form-control">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── GOVERNMENT IDs ── -->
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Government IDs</h3></div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group"><label>SSS number</label>
                  <input type="text" name="sss_number" class="form-control" placeholder="XX-XXXXXXX-X">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>PhilHealth number</label>
                  <input type="text" name="philhealth_number" class="form-control">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>Pag-IBIG number</label>
                  <input type="text" name="pagibig_number" class="form-control">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group"><label>TIN number</label>
                  <input type="text" name="tin_number" class="form-control" placeholder="XXX-XXX-XXX">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── EMPLOYMENT DETAILS ── -->
        <div class="card card-info card-outline">
          <div class="card-header"><h3 class="card-title">Employment details</h3></div>
          <div class="card-body">
            <div class="row">

              <!-- Classification -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Employee classification
                    <span class="text-muted" style="font-size:11px;">(Teaching / Non-Teaching)</span>
                  </label>
                  <select name="classification_id" id="classSelect" class="form-control"
                          onchange="filterRanks(this)">
                    <option value="0">-- Select classification --</option>
                    <?php
                    $top_cl  = array_filter($classifications, function($c){ return !$c['parent_id']; });
                    $sub_cl  = array_filter($classifications, function($c){ return  $c['parent_id']; });
                    foreach ($top_cl as $tl):
                    ?>
                      <optgroup label="─ <?= htmlspecialchars($tl['name']) ?> ─">
                        <option value="<?= $tl['id'] ?>"
                                data-category="<?= htmlspecialchars($tl['category']) ?>">
                          <?= htmlspecialchars($tl['name']) ?> (General)
                        </option>
                        <?php foreach ($sub_cl as $sl):
                          if ($sl['parent_id'] != $tl['id']) continue; ?>
                          <option value="<?= $sl['id'] ?>"
                                  data-category="<?= htmlspecialchars($sl['category']) ?>">
                            &nbsp;&nbsp;↳ <?= htmlspecialchars($sl['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Rank -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Rank / Academic rank</label>
                  <select name="rank_id" id="rankSelect" class="form-control">
                    <option value="0">-- Select rank --</option>
                    <?php foreach ($ranks as $r): ?>
                      <option value="<?= $r['id'] ?>"
                              data-category="<?= htmlspecialchars($r['category']) ?>"
							  
							  data-grade="<?= htmlspecialchars(isset($r['salary_grade']) ? $r['salary_grade'] : '') ?>">
                              
                        <?= htmlspecialchars($r['name']) ?>
                        <?= !empty($r['code']) ? ' (' . $r['code'] . ')' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Part-time -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <div class="custom-control custom-checkbox" style="margin-top:10px;">
                    <input type="checkbox" name="is_part_time"
                           class="custom-control-input" id="isPartTime">
                    <label class="custom-control-label" for="isPartTime">
                      Part-time / Lecturer
                    </label>
                  </div>
                </div>
              </div>

              <!-- Department -->
              <div class="col-md-4">
                <div class="form-group"><label>Department</label>
                  <select name="department_id" class="form-control">
                    <option value="0">-- Select department --</option>
                    <?php foreach ($departments as $d): ?>
                      <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Position -->
              <div class="col-md-4">
                <div class="form-group"><label>Position / Job title</label>
                  <select name="position_id" class="form-control">
                    <option value="0">-- Select position --</option>
                    <?php foreach ($positions as $p): ?>
                      <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Supervisor -->
              <div class="col-md-4">
                <div class="form-group"><label>Immediate supervisor</label>
                  <select name="supervisor_id" class="form-control">
                    <option value="0">-- None --</option>
                    <?php foreach ($supervisors as $sv): ?>
                      <option value="<?= $sv['id'] ?>">
                        <?= htmlspecialchars($sv['full_name']) ?>
                        (<?= htmlspecialchars($sv['employee_id']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Employment Type (from DB) -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Employment type <span class="text-danger">*</span>
                    <a href="hr_setup.php?tab=types" target="_blank"
                       style="font-size:11px;" title="Manage types">
                      <i class="fas fa-cog text-muted"></i>
                    </a>
                  </label>
                  <select name="employment_type_id" id="empTypeId"
                          class="form-control" onchange="onTypeChange(this)">
                    <option value="0">-- Select type --</option>
                    <?php foreach ($emp_types as $et): ?>
                      <option value="<?= $et['id'] ?>"
                              data-name="<?= htmlspecialchars($et['name']) ?>"
                              
						  data-prob="<?= isset($et['probation_months']) ? $et['probation_months'] : '' ?>">
                        <?= htmlspecialchars($et['name']) ?>
                        <?php if (!empty($et['probation_months'])): ?>
                          (<?= $et['probation_months'] ?>mo probation)
                        <?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <!-- Hidden text field for backward compat -->
                  <input type="hidden" name="employment_type_text" id="empTypeText" value="">
                </div>
              </div>

              <!-- Employment Status (from DB) -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Employment status <span class="text-danger">*</span>
                    <a href="hr_setup.php?tab=statuses" target="_blank"
                       style="font-size:11px;" title="Manage statuses">
                      <i class="fas fa-cog text-muted"></i>
                    </a>
                  </label>
                  <select name="employment_status_id" id="empStatusId"
                          class="form-control" onchange="onStatusChange(this)">
                    <option value="0">-- Select status --</option>
                    <?php foreach ($emp_statuses as $es): ?>
                      <option value="<?= $es['id'] ?>"
                              data-name="<?= htmlspecialchars($es['name']) ?>">
                        <?= htmlspecialchars($es['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input type="hidden" name="employment_status_text" id="empStatusText" value="">
                </div>
              </div>

              <!-- Date Hired -->
              <div class="col-md-4">
                <div class="form-group"><label>Date hired</label>
                  <input type="date" name="date_hired" id="dateHired"
                         class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
              </div>

              <!-- Probationary info alert -->
              <div class="col-md-12" id="probInfo" style="display:none;">
                <div class="alert alert-warning py-2 mb-2">
                  <i class="fas fa-clock mr-1"></i>
                  <strong>Probationary:</strong>
                  Review date will be auto-set to
                  <span id="probDateDisplay"></span>.
                  HR and Dept Head will be notified before the review date.
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group"><label>Work location</label>
                  <input type="text" name="work_location" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group"><label>Salary grade</label>
                  <input type="text" name="salary_grade" id="salaryGradeInput"
                         class="form-control" placeholder="e.g. SG-11">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group"><label>Basic salary (&#8369;)</label>
                  <input type="number" name="basic_salary" class="form-control"
                         step="0.01" min="0">
                </div>
              </div>

            </div><!-- /.row -->
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-12">
            <a href="employees.php" class="btn btn-secondary">
              <i class="fas fa-times mr-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary float-right">
              <i class="fas fa-save mr-1"></i> Save employee record
            </button>
          </div>
        </div>
      </form>

    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
var allRanks = <?= $ranks_json ?: '[]' ?>;

// Filter ranks based on classification category
function filterRanks(sel) {
    var opt = sel.options[sel.selectedIndex];
    var cat = opt ? (opt.getAttribute('data-category') || '') : '';
    var rs  = document.getElementById('rankSelect');
    // Remove all except placeholder
    while (rs.options.length > 1) rs.remove(1);
    allRanks.forEach(function(r) {
        if (!cat || r.category === cat || r.category === 'Both') {
            var o        = document.createElement('option');
            o.value      = r.id;
            o.text       = r.name + (r.code ? ' (' + r.code + ')' : '');
            o.setAttribute('data-category', r.category);
            o.setAttribute('data-grade', r.salary_grade || '');
            rs.appendChild(o);
        }
    });
}

// Sync hidden text field + show/hide probation info
function onTypeChange(sel) {
    var opt  = sel.options[sel.selectedIndex];
    var name = opt ? (opt.getAttribute('data-name') || '') : '';
    var prob = opt ? (parseInt(opt.getAttribute('data-prob')) || 0) : 0;
    document.getElementById('empTypeText').value = name;

    var info = document.getElementById('probInfo');
    if (prob > 0) {
        info.style.display = 'block';
        updateProbDate(prob);
    } else {
        info.style.display = 'none';
    }
}

function onStatusChange(sel) {
    var opt  = sel.options[sel.selectedIndex];
    var name = opt ? (opt.getAttribute('data-name') || '') : '';
    document.getElementById('empStatusText').value = name;
}

function updateProbDate(months) {
    var hireInput = document.getElementById('dateHired');
    var display   = document.getElementById('probDateDisplay');
    if (!hireInput || !hireInput.value) {
        display.textContent = months + ' months from hire date';
        return;
    }
    var d = new Date(hireInput.value);
    d.setMonth(d.getMonth() + months);
    display.textContent = d.toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})
        + ' (' + months + ' months)';
}

// Auto-fill salary grade from rank
document.getElementById('rankSelect').addEventListener('change', function() {
    var grade = this.options[this.selectedIndex].getAttribute('data-grade');
    if (grade) document.getElementById('salaryGradeInput').value = grade;
});

// Update probation date when hire date changes
document.getElementById('dateHired').addEventListener('change', function() {
    var empType = document.getElementById('empTypeId');
    var prob    = parseInt(empType.options[empType.selectedIndex].getAttribute('data-prob')) || 0;
    if (prob > 0) updateProbDate(prob);
});
</script>
</body>
</html>