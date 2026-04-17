<?php
// ============================================
// FILE: pages/leave_credits.php
// Standalone leave credits bulk management
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$success = ''; $error = '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Save individual leave credit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    if ($_POST['action'] === 'update_single') {
        $lc_id     = (int)$_POST['lc_id'];
        $vac       = (float)$_POST['vacation_leave'];
        $sick      = (float)$_POST['sick_leave'];
        $emergency = (float)$_POST['emergency_leave'];
        $vac_used  = (float)$_POST['vacation_used'];
        $sick_used = (float)$_POST['sick_used'];

        db_run($pdo,
            "UPDATE leave_credits SET
                vacation_leave=?, sick_leave=?, emergency_leave=?,
                vacation_used=?, sick_used=?
             WHERE id=?",
            array($vac, $sick, $emergency, $vac_used, $sick_used, $lc_id)
        );
        $success = "Leave credits updated.";
    }

    // Year-end rollover — reset used to 0, carry forward balance (optional)
    if ($_POST['action'] === 'year_rollover') {
        $new_year = (int)$_POST['new_year'];
        $carry    = isset($_POST['carry_forward']) ? 1 : 0;

        $existing = db_fetchall($pdo,
            "SELECT * FROM leave_credits WHERE year = ?", array($year)
        );

        $inserted = 0;
        foreach ($existing as $lc) {
            $check = db_value($pdo,
                "SELECT COUNT(*) FROM leave_credits WHERE employee_id=? AND year=?",
                array($lc['employee_id'], $new_year)
            );
            if (!$check) {
                $carry_vac  = $carry ? max(0, $lc['vacation_leave']    - $lc['vacation_used'])    : 0;
                $carry_sick = $carry ? max(0, $lc['sick_leave']         - $lc['sick_used'])         : 0;
                db_run($pdo,
                    "INSERT INTO leave_credits
                     (employee_id, year, vacation_leave, sick_leave, emergency_leave,
                      maternity_leave, paternity_leave, solo_parent_leave)
                     VALUES (?,?,?,?,?,?,?,?)",
                    array(
                        $lc['employee_id'], $new_year,
                        15 + $carry_vac, 15 + $carry_sick,
                        $lc['emergency_leave'], $lc['maternity_leave'],
                        $lc['paternity_leave'], $lc['solo_parent_leave']
                    )
                );
                $inserted++;
            }
        }
        $success = "Year rollover complete. $inserted employee records created for $new_year.";
    }

    // Bulk reset used days to 0
    if ($_POST['action'] === 'reset_used') {
        db_run($pdo,
            "UPDATE leave_credits SET
                vacation_used=0, sick_used=0, emergency_used=0,
                maternity_used=0, paternity_used=0, solo_parent_used=0
             WHERE year=?",
            array($year)
        );
        $success = "All used leave counts reset to 0 for $year.";
    }
}

$credits = db_fetchall($pdo,
    "SELECT lc.*, e.full_name, e.employee_id AS emp_code, d.name AS dept
     FROM leave_credits lc
     JOIN employees e ON lc.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE lc.year = ?
     ORDER BY e.full_name",
    array($year)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leave Credits | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <h1 class="m-0">Leave Credits Management</h1>
  </div></div>
  <div class="content"><div class="container-fluid">

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Year selector + bulk actions -->
    <div class="card">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-3">
            <form method="GET" action="" class="form-inline" style="gap:8px;">
              <label class="mr-2">Year:</label>
              <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 3; $y--): ?>
                  <option <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </form>
          </div>
          <div class="col-md-9 text-right">
            <!-- Year rollover modal -->
            <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#rolloverModal">
              <i class="fas fa-sync mr-1"></i> Year-end rollover
            </button>
            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Reset all used leave counts to 0 for <?= $year ?>?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="reset_used">
              <button type="submit" class="btn btn-warning btn-sm">
                <i class="fas fa-redo mr-1"></i> Reset all used days
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Credits table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Leave credits — <?= $year ?> (<?= count($credits) ?> employees)</h3>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-sm table-bordered table-hover mb-0" id="lcTable">
          <thead class="thead-light" style="font-size:11px;">
            <tr>
              <th>Employee</th><th>Dept</th>
              <th>VL Total</th><th>VL Used</th><th>VL Left</th>
              <th>SL Total</th><th>SL Used</th><th>SL Left</th>
              <th>EL Total</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($credits as $lc):
              $vl_left = $lc['vacation_leave']  - $lc['vacation_used'];
              $sl_left = $lc['sick_leave']       - $lc['sick_used'];
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($lc['full_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($lc['emp_code']) ?></small>
              </td>
              <td><?= htmlspecialchars($lc['dept'] ? $lc['dept'] : '—') ?></td>
              <td><?= number_format($lc['vacation_leave'],1) ?></td>
              <td><?= number_format($lc['vacation_used'],1) ?></td>
              <td><strong class="<?= $vl_left < 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($vl_left,1) ?></strong></td>
              <td><?= number_format($lc['sick_leave'],1) ?></td>
              <td><?= number_format($lc['sick_used'],1) ?></td>
              <td><strong class="<?= $sl_left < 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($sl_left,1) ?></strong></td>
              <td><?= number_format($lc['emergency_leave'],1) ?></td>
              <td>
                <button class="btn btn-warning btn-xs edit-btn"
                        data-id="<?= $lc['id'] ?>"
                        data-vac="<?= $lc['vacation_leave'] ?>"
                        data-sick="<?= $lc['sick_leave'] ?>"
                        data-emer="<?= $lc['emergency_leave'] ?>"
                        data-vacused="<?= $lc['vacation_used'] ?>"
                        data-sickused="<?= $lc['sick_used'] ?>"
                        data-name="<?= htmlspecialchars($lc['full_name']) ?>"
                        data-toggle="modal" data-target="#editModal">
                  <i class="fas fa-edit"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit leave credits — <span id="modal_name"></span></h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update_single">
        <input type="hidden" name="lc_id" id="modal_lc_id">
        <div class="modal-body">
          <div class="row">
            <div class="col-6"><div class="form-group"><label>Vacation leave (total)</label><input type="number" name="vacation_leave" id="modal_vac" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Vacation used</label><input type="number" name="vacation_used" id="modal_vacused" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Sick leave (total)</label><input type="number" name="sick_leave" id="modal_sick" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Sick used</label><input type="number" name="sick_used" id="modal_sickused" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Emergency leave (total)</label><input type="number" name="emergency_leave" id="modal_emer" class="form-control" step="0.5"></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Rollover Modal -->
<div class="modal fade" id="rolloverModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Year-end leave rollover</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="year_rollover">
        <div class="modal-body">
          <div class="form-group">
            <label>Create leave credits for year:</label>
            <select name="new_year" class="form-control">
              <?php for ($y = (int)date('Y'); $y <= (int)date('Y') + 2; $y++): ?>
                <option><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" name="carry_forward" class="custom-control-input" id="carry">
            <label class="custom-control-label" for="carry">Carry forward unused balance to new year</label>
          </div>
          <small class="text-muted d-block mt-2">This creates new leave credit records for all active employees. Existing records for the selected year will be skipped.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info">Run rollover</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    $('#lcTable').DataTable({ pageLength:25 });
    $('#editModal').on('show.bs.modal', function(e) {
        var b = $(e.relatedTarget);
        $('#modal_lc_id').val(b.data('id'));
        $('#modal_name').text(b.data('name'));
        $('#modal_vac').val(b.data('vac'));
        $('#modal_sick').val(b.data('sick'));
        $('#modal_emer').val(b.data('emer'));
        $('#modal_vacused').val(b.data('vacused'));
        $('#modal_sickused').val(b.data('sickused'));
    });
});
</script>
</body></html>
