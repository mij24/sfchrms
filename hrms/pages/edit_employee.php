<?php
// ============================================
// FILE: pages/edit_employee.php — FIXED
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: employees.php"); exit; }

$departments = $conn->query("SELECT * FROM departments ORDER BY name");
$positions   = $conn->query("SELECT * FROM positions ORDER BY title");
$supervisors = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE id != $id ORDER BY full_name");
$lc          = $conn->query("SELECT * FROM leave_credits WHERE employee_id = $id ORDER BY year DESC LIMIT 1")->fetch_assoc();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();

    $full_name         = $conn->real_escape_string(trim($_POST['full_name']));
    $date_of_birth     = $conn->real_escape_string($_POST['date_of_birth']);
    $gender            = $conn->real_escape_string($_POST['gender']);
    $civil_status      = $conn->real_escape_string($_POST['civil_status']);
    $nationality       = $conn->real_escape_string($_POST['nationality']);
    $address           = $conn->real_escape_string($_POST['address']);
    $contact_number    = $conn->real_escape_string($_POST['contact_number']);
    $email_address     = $conn->real_escape_string($_POST['email_address']);
    $emergency_contact = $conn->real_escape_string($_POST['emergency_contact']);
    $sss_number        = $conn->real_escape_string($_POST['sss_number']);
    $philhealth_number = $conn->real_escape_string($_POST['philhealth_number']);
    $pagibig_number    = $conn->real_escape_string($_POST['pagibig_number']);
    $tin_number        = $conn->real_escape_string($_POST['tin_number']);
    $department_id     = (int)$_POST['department_id'];
    $position_id       = (int)$_POST['position_id'];
    $employment_type   = $conn->real_escape_string($_POST['employment_type']);
    $date_hired        = $conn->real_escape_string($_POST['date_hired']);
    $employment_status = $conn->real_escape_string($_POST['employment_status']);
    $supervisor_id_raw = (int)$_POST['supervisor_id'];
    $supervisor_id     = $supervisor_id_raw > 0 ? $supervisor_id_raw : 'NULL';
    $work_location     = $conn->real_escape_string($_POST['work_location']);
    $salary_grade      = $conn->real_escape_string($_POST['salary_grade']);
    $basic_salary      = (float)$_POST['basic_salary'];

    $sql = "UPDATE employees SET
        full_name         = '$full_name',
        date_of_birth     = '$date_of_birth',
        gender            = '$gender',
        civil_status      = '$civil_status',
        nationality       = '$nationality',
        address           = '$address',
        contact_number    = '$contact_number',
        email_address     = '$email_address',
        emergency_contact = '$emergency_contact',
        sss_number        = '$sss_number',
        philhealth_number = '$philhealth_number',
        pagibig_number    = '$pagibig_number',
        tin_number        = '$tin_number',
        department_id     = $department_id,
        position_id       = $position_id,
        employment_type   = '$employment_type',
        date_hired        = '$date_hired',
        employment_status = '$employment_status',
        supervisor_id     = $supervisor_id,
        work_location     = '$work_location',
        salary_grade      = '$salary_grade',
        basic_salary      = $basic_salary
        WHERE id = $id";

    if ($conn->query($sql)) {
        if ($lc) {
            $vacation_leave    = (float)$_POST['vacation_leave'];
            $sick_leave        = (float)$_POST['sick_leave'];
            $emergency_leave   = (float)$_POST['emergency_leave'];
            $maternity_leave   = (float)$_POST['maternity_leave'];
            $paternity_leave   = (float)$_POST['paternity_leave'];
            $solo_parent_leave = (float)$_POST['solo_parent_leave'];
            $vacation_used     = (float)$_POST['vacation_used'];
            $sick_used         = (float)$_POST['sick_used'];

            $lc_year = (int)$lc['year'];
            $conn->query("UPDATE leave_credits SET
                vacation_leave    = $vacation_leave,
                sick_leave        = $sick_leave,
                emergency_leave   = $emergency_leave,
                maternity_leave   = $maternity_leave,
                paternity_leave   = $paternity_leave,
                solo_parent_leave = $solo_parent_leave,
                vacation_used     = $vacation_used,
                sick_used         = $sick_used
                WHERE employee_id = $id AND year = $lc_year");
        }
        //$success = "Employee record updated successfully.";
		prg_redirect('view_employee.php?id=' . $id, 'Employee record updated.');
    } else {
        //$error = "Update failed: " . $conn->error;
		prg_redirect_error('edit_employee.php?id=' . $id, 'Update failed: ' . $pdo->errorInfo()[2]);
    }
}

// Reload fresh data after save
$emp = $conn->query("SELECT * FROM employees WHERE id = $id")->fetch_assoc();
if (!$emp) { header("Location: employees.php"); exit; }
$lc  = $conn->query("SELECT * FROM leave_credits WHERE employee_id = $id ORDER BY year DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Employee | HRMS</title>
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
        <div class="col-sm-6"><h1 class="m-0">Edit Employee</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
            <li class="breadcrumb-item active">Edit</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">
		<!--flash this if success or failed-->
	<?= prg_flash() ?>
      <!--<?php //if ($success): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <i class="fas fa-check-circle mr-1"></i><?//= $success ?>
        </div>
      <?php //endif; ?>
      <?php //if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?//= $error ?>
        </div>
      <?php //endif; ?>-->

      <form method="POST" action="">
		<?php csrf_field(); ?>

        <!-- PERSONAL INFORMATION -->
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Personal information</h3></div>
          <div class="card-body">
            <div class="row">

              <div class="col-md-4">
                <div class="form-group">
                  <label>Employee ID</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($emp['employee_id']) ?>" disabled>
                </div>
              </div>

              <div class="col-md-8">
                <div class="form-group">
                  <label>Full name <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($emp['full_name']) ?>">
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Date of birth</label>
                  <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($emp['date_of_birth']) ?>">
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Gender</label>
                  <select name="gender" class="form-control">
                    <?php
                    $genders = array('Male', 'Female', 'Prefer not to say');
                    foreach ($genders as $g):
                        $sel = ($emp['gender'] === $g) ? 'selected' : '';
                    ?>
                      <option <?= $sel ?>><?= $g ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Civil status</label>
                  <select name="civil_status" class="form-control">
                    <?php
                    $civil_opts = array('Single', 'Married', 'Widowed', 'Separated');
                    foreach ($civil_opts as $c):
                        $sel = ($emp['civil_status'] === $c) ? 'selected' : '';
                    ?>
                      <option <?= $sel ?>><?= $c ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Nationality</label>
                  <input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($emp['nationality']) ?>">
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Contact number</label>
                  <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($emp['contact_number']) ?>">
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Email address</label>
                  <input type="email" name="email_address" class="form-control" value="<?= htmlspecialchars($emp['email_address']) ?>">
                </div>
              </div>

              <div class="col-md-8">
                <div class="form-group">
                  <label>Address</label>
                  <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($emp['address']) ?>">
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Emergency contact</label>
                  <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact']) ?>">
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- GOVERNMENT IDs -->
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Government IDs</h3></div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>SSS number</label>
                  <input type="text" name="sss_number" class="form-control" value="<?= htmlspecialchars($emp['sss_number']) ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>PhilHealth number</label>
                  <input type="text" name="philhealth_number" class="form-control" value="<?= htmlspecialchars($emp['philhealth_number']) ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Pag-IBIG number</label>
                  <input type="text" name="pagibig_number" class="form-control" value="<?= htmlspecialchars($emp['pagibig_number']) ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>TIN number</label>
                  <input type="text" name="tin_number" class="form-control" value="<?= htmlspecialchars($emp['tin_number']) ?>">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- EMPLOYMENT DETAILS -->
        <div class="card card-info card-outline">
          <div class="card-header"><h3 class="card-title">Employment details</h3></div>
          <div class="card-body">
            <div class="row">

              <div class="col-md-4">
                <div class="form-group">
                  <label>Department</label>
                  <select name="department_id" class="form-control">
                    <option value="0">Select...</option>
                    <?php
                    $departments->data_seek(0);
                    while ($d = $departments->fetch_assoc()):
                        $sel = ($emp['department_id'] == $d['id']) ? 'selected' : '';
                    ?>
                      <option value="<?= $d['id'] ?>" <?= $sel ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Position</label>
                  <select name="position_id" class="form-control">
                    <option value="0">Select...</option>
                    <?php
                    $positions->data_seek(0);
                    while ($p = $positions->fetch_assoc()):
                        $sel = ($emp['position_id'] == $p['id']) ? 'selected' : '';
                    ?>
                      <option value="<?= $p['id'] ?>" <?= $sel ?>><?= htmlspecialchars($p['title']) ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label>Employment type</label>
                  <select name="employment_type" class="form-control">
                    <?php
                    $etypes = array('Regular','Probationary','Contractual','Part-time','Project-based');
                    foreach ($etypes as $et):
                        $sel = ($emp['employment_type'] === $et) ? 'selected' : '';
                    ?>
                      <option <?= $sel ?>><?= $et ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Date hired</label>
                  <input type="date" name="date_hired" class="form-control" value="<?= htmlspecialchars($emp['date_hired']) ?>">
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Employment status</label>
                  <select name="employment_status" class="form-control">
                    <?php
                    $estatuses = array('Active','Inactive','Resigned','Terminated','On leave');
                    foreach ($estatuses as $es):
                        $sel = ($emp['employment_status'] === $es) ? 'selected' : '';
                    ?>
                      <option <?= $sel ?>><?= $es ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Immediate supervisor</label>
                  <select name="supervisor_id" class="form-control">
                    <option value="0">None</option>
                    <?php
                    $supervisors->data_seek(0);
                    while ($sv = $supervisors->fetch_assoc()):
                        $sel = ($emp['supervisor_id'] == $sv['id']) ? 'selected' : '';
                    ?>
                      <option value="<?= $sv['id'] ?>" <?= $sel ?>>
                        <?= htmlspecialchars($sv['full_name']) ?> (<?= htmlspecialchars($sv['employee_id']) ?>)
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Work location</label>
                  <input type="text" name="work_location" class="form-control" value="<?= htmlspecialchars($emp['work_location']) ?>">
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Salary grade</label>
                  <input type="text" name="salary_grade" class="form-control" value="<?= htmlspecialchars($emp['salary_grade']) ?>">
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Basic salary (&#8369;)</label>
                  <input type="number" name="basic_salary" class="form-control" step="0.01" value="<?= htmlspecialchars($emp['basic_salary']) ?>">
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- LEAVE CREDITS -->
        <?php if ($lc): ?>
        <div class="card card-warning card-outline">
          <div class="card-header">
            <h3 class="card-title">Leave credits &mdash; <?= (int)$lc['year'] ?></h3>
          </div>
          <div class="card-body">
            <div class="row">
              <?php
              $leave_fields = array(
                array('vacation_leave',    'vacation_used',    'Vacation leave'),
                array('sick_leave',        'sick_used',        'Sick leave'),
                array('emergency_leave',   'emergency_used',   'Emergency leave'),
                array('maternity_leave',   'maternity_used',   'Maternity leave'),
                array('paternity_leave',   'paternity_used',   'Paternity leave'),
                array('solo_parent_leave', 'solo_parent_used', 'Solo parent leave'),
              );
              foreach ($leave_fields as $lf):
                $total_key = $lf[0];
                $used_key  = $lf[1];
                $lf_label  = $lf[2];
                $total_val = isset($lc[$total_key]) ? (float)$lc[$total_key] : 0;
                $used_val  = isset($lc[$used_key])  ? (float)$lc[$used_key]  : 0;
              ?>
              <div class="col-md-4 mb-2">
                <label style="font-size:12px;color:#555;"><?= $lf_label ?></label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text">Total</span>
                  </div>
                  <input type="number" name="<?= $total_key ?>" class="form-control" step="0.5" value="<?= $total_val ?>">
                  <div class="input-group-prepend">
                    <span class="input-group-text">Used</span>
                  </div>
                  <input type="number" name="<?= $used_key ?>" class="form-control" step="0.5" value="<?= $used_val ?>">
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
          <div class="col-12">
            <a href="view_employee.php?id=<?= $id ?>" class="btn btn-secondary">
              <i class="fas fa-times mr-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary float-right">
              <i class="fas fa-save mr-1"></i> Save changes
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>