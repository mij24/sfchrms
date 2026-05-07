<?php
// ============================================
// FILE: pages/leave_credits.php — ENHANCED
// HR can add/manage any leave entitlement type per employee
// Includes Future Rest Day entitlement
// Auto-grant on civil status change (maternity/paternity)
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$success = ''; $error = '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    // ── Update individual leave credits ──
    if ($_POST['action'] === 'update_single') {
        $lc_id     = (int)$_POST['lc_id'];
        $vac       = (float)$_POST['vacation_leave'];
        $sick      = (float)$_POST['sick_leave'];
        $emergency = (float)$_POST['emergency_leave'];
        $maternity = (float)$_POST['maternity_leave'];
        $paternity = (float)$_POST['paternity_leave'];
        $solo      = (float)$_POST['solo_parent_leave'];
        $frd       = (float)$_POST['future_rest_day'];
        $vac_used  = (float)$_POST['vacation_used'];
        $sick_used = (float)$_POST['sick_used'];

        db_run($pdo,
            "UPDATE leave_credits SET
                vacation_leave=?, sick_leave=?, emergency_leave=?,
                maternity_leave=?, paternity_leave=?, solo_parent_leave=?,
                future_rest_day=?,
                vacation_used=?, sick_used=?
             WHERE id=?",
            array($vac,$sick,$emergency,$maternity,$paternity,$solo,$frd,$vac_used,$sick_used,$lc_id)
        );
        prg_redirect('leave_credits.php?year='.$year, 'Leave credits updated.');
    }

    // ── Add entitlement to specific employee ──
    if ($_POST['action'] === 'add_entitlement') {
        $emp_id   = (int)$_POST['emp_entitlement_id'];
        $ent_type = trim($_POST['entitlement_type']);
        $ent_days = (float)$_POST['entitlement_days'];
        $reason   = trim($_POST['entitlement_reason']);

        if (!$emp_id || $ent_days <= 0) {
            prg_redirect_error('leave_credits.php?year='.$year, 'Select employee and enter valid days.');
        }

        // Get or create leave credit record for this year
        $lc = db_fetch($pdo, "SELECT id FROM leave_credits WHERE employee_id=? AND year=?", array($emp_id,$year));
        if (!$lc) {
            db_run($pdo,
                "INSERT INTO leave_credits (employee_id,year,vacation_leave,sick_leave,emergency_leave) VALUES (?,?,15,15,3)",
                array($emp_id,$year)
            );
            $lc_id = db_lastid($pdo);
        } else {
            $lc_id = $lc['id'];
        }

        // Map entitlement type to column
        $col_map = array(
            'Vacation Leave'    => 'vacation_leave',
            'Sick Leave'        => 'sick_leave',
            'Emergency Leave'   => 'emergency_leave',
            'Maternity Leave'   => 'maternity_leave',
            'Paternity Leave'   => 'paternity_leave',
            'Solo Parent Leave' => 'solo_parent_leave',
            'Future Rest Day'   => 'future_rest_day',
        );

        if (isset($col_map[$ent_type])) {
            $col = $col_map[$ent_type];
            db_run($pdo, "UPDATE leave_credits SET `$col` = `$col` + ? WHERE id=?", array($ent_days,$lc_id));
            // Log the addition
            try {
                db_run($pdo,
                    "INSERT INTO leave_entitlement_log (employee_id,year,leave_type,days_added,reason,added_by,created_at)
                     VALUES (?,?,?,?,?,?,NOW())",
                    array($emp_id,$year,$ent_type,$ent_days,$reason,(int)$_SESSION['user_id'])
                );
            } catch (Exception $e) { /* log table may not exist yet */ }
        }
        prg_redirect('leave_credits.php?year='.$year, "$ent_days day(s) of $ent_type added to employee #$emp_id.");
    }

    // ── Year-end rollover ──
    if ($_POST['action'] === 'year_rollover') {
        $new_year = (int)$_POST['new_year'];
        $carry    = isset($_POST['carry_forward']) ? 1 : 0;
        $existing = db_fetchall($pdo, "SELECT * FROM leave_credits WHERE year = ?", array($year));
        $inserted = 0;
        foreach ($existing as $lc) {
            $check = db_value($pdo,
                "SELECT COUNT(*) FROM leave_credits WHERE employee_id=? AND year=?",
                array($lc['employee_id'], $new_year)
            );
            if (!$check) {
                $carry_vac  = $carry ? max(0, $lc['vacation_leave'] - $lc['vacation_used']) : 0;
                $carry_sick = $carry ? max(0, $lc['sick_leave']      - $lc['sick_used'])     : 0;
                db_run($pdo,
                    "INSERT INTO leave_credits
                     (employee_id, year, vacation_leave, sick_leave, emergency_leave,
                      maternity_leave, paternity_leave, solo_parent_leave, future_rest_day)
                     VALUES (?,?,?,?,?,?,?,?,?)",
                    array(
                        $lc['employee_id'], $new_year,
                        15 + $carry_vac, 15 + $carry_sick,
                        $lc['emergency_leave'],
                        $lc['maternity_leave'],
                        $lc['paternity_leave'],
                        $lc['solo_parent_leave'],
                        0  // Future rest day resets
                    )
                );
                $inserted++;
            }
        }
        prg_redirect('leave_credits.php?year='.$new_year, "Year rollover complete. $inserted record(s) created for $new_year.");
    }

    // ── Reset used days ──
    if ($_POST['action'] === 'reset_used') {
        db_run($pdo,
            "UPDATE leave_credits SET
                vacation_used=0, sick_used=0, emergency_used=0,
                maternity_used=0, paternity_used=0, solo_parent_used=0, future_rest_used=0
             WHERE year=?",
            array($year)
        );
        prg_redirect('leave_credits.php?year='.$year, "All used leave counts reset to 0 for $year.");
    }
}

$credits = db_fetchall($pdo,
    "SELECT lc.*, e.full_name, e.employee_id AS emp_code, d.name AS dept,
            e.gender, e.civil_status
     FROM leave_credits lc
     JOIN employees e ON lc.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE lc.year = ?
     ORDER BY e.full_name",
    array($year)
);

$all_employees = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id AS emp_code
     FROM employees e WHERE e.employment_status='Active' ORDER BY e.full_name"
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
    <?= prg_flash() ?>

    <!-- Year + Bulk Actions -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-4">
            <form method="GET" action="" class="form-inline" style="gap:8px;">
              <label class="mr-2 font-weight-bold">Year:</label>
              <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php for ($y=(int)date('Y')+1; $y>=(int)date('Y')-3; $y--): ?>
                  <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
              <span class="text-muted ml-2" style="font-size:13px;"><?= count($credits) ?> employees</span>
            </form>
          </div>
          <div class="col-md-8 text-right">
            <button class="btn btn-success btn-sm mr-1" data-toggle="modal" data-target="#addEntitlementModal">
              <i class="fas fa-plus mr-1"></i> Add Entitlement
            </button>
            <button class="btn btn-info btn-sm mr-1" data-toggle="modal" data-target="#rolloverModal">
              <i class="fas fa-sync mr-1"></i> Year-End Rollover
            </button>
            <form method="POST" action="" class="d-inline"
                  onsubmit="return confirm('Reset all used leave counts to 0 for <?= $year ?>?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="reset_used">
              <button type="submit" class="btn btn-warning btn-sm">
                <i class="fas fa-redo mr-1"></i> Reset Used Days
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Leave Credits Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Leave Credits — <?= $year ?></h3>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-sm table-bordered table-hover mb-0" id="lcTable">
          <thead class="thead-light" style="font-size:11px;">
            <tr>
              <th>Employee</th><th>Dept</th>
              <th class="text-center" title="Vacation Leave">VL Total</th>
              <th class="text-center">VL Used</th>
              <th class="text-center">VL Left</th>
              <th class="text-center" title="Sick Leave">SL Total</th>
              <th class="text-center">SL Used</th>
              <th class="text-center">SL Left</th>
              <th class="text-center" title="Emergency Leave">EL</th>
              <th class="text-center" title="Maternity Leave">Mat.</th>
              <th class="text-center" title="Paternity Leave">Pat.</th>
              <th class="text-center" title="Solo Parent Leave">Solo</th>
              <th class="text-center" title="Future Rest Day">FRD</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($credits as $lc):
              $vl_left = $lc['vacation_leave'] - $lc['vacation_used'];
              $sl_left = $lc['sick_leave']      - $lc['sick_used'];
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($lc['full_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($lc['emp_code']) ?></small>
              </td>
              <td><?= htmlspecialchars($lc['dept'] ?: '—') ?></td>
              <td class="text-center"><?= number_format($lc['vacation_leave'],1) ?></td>
              <td class="text-center"><?= number_format($lc['vacation_used'],1) ?></td>
              <td class="text-center">
                <strong class="<?= $vl_left<0?'text-danger':'text-success' ?>"><?= number_format($vl_left,1) ?></strong>
              </td>
              <td class="text-center"><?= number_format($lc['sick_leave'],1) ?></td>
              <td class="text-center"><?= number_format($lc['sick_used'],1) ?></td>
              <td class="text-center">
                <strong class="<?= $sl_left<0?'text-danger':'text-success' ?>"><?= number_format($sl_left,1) ?></strong>
              </td>
              <td class="text-center"><?= number_format($lc['emergency_leave'],1) ?></td>
              <td class="text-center">
                <?php $mat = (float)($lc['maternity_leave'] ?? 0); ?>
                <?= $mat > 0 ? '<span class="badge badge-info">'.$mat.'</span>' : '—' ?>
              </td>
              <td class="text-center">
                <?php $pat = (float)($lc['paternity_leave'] ?? 0); ?>
                <?= $pat > 0 ? '<span class="badge badge-primary">'.$pat.'</span>' : '—' ?>
              </td>
              <td class="text-center">
                <?php $solo = (float)($lc['solo_parent_leave'] ?? 0); ?>
                <?= $solo > 0 ? '<span class="badge badge-dark">'.$solo.'</span>' : '—' ?>
              </td>
              <td class="text-center">
                <?php $frd = (float)($lc['future_rest_day'] ?? 0); ?>
                <?= $frd > 0 ? '<span class="badge badge-secondary">'.$frd.'</span>' : '—' ?>
              </td>
              <td>
                <button class="btn btn-warning btn-xs edit-btn"
                        data-id="<?= $lc['id'] ?>"
                        data-name="<?= htmlspecialchars($lc['full_name']) ?>"
                        data-vac="<?= $lc['vacation_leave'] ?>"
                        data-sick="<?= $lc['sick_leave'] ?>"
                        data-emer="<?= $lc['emergency_leave'] ?>"
                        data-mat="<?= $lc['maternity_leave'] ?? 0 ?>"
                        data-pat="<?= $lc['paternity_leave'] ?? 0 ?>"
                        data-solo="<?= $lc['solo_parent_leave'] ?? 0 ?>"
                        data-frd="<?= $lc['future_rest_day'] ?? 0 ?>"
                        data-vacused="<?= $lc['vacation_used'] ?>"
                        data-sickused="<?= $lc['sick_used'] ?>"
                        data-toggle="modal" data-target="#editModal">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-success btn-xs"
                        data-emp-id="<?= $lc['employee_id'] ?>"
                        data-emp-name="<?= htmlspecialchars($lc['full_name']) ?>"
                        onclick="openAddEnt(this)"
                        title="Add entitlement">
                  <i class="fas fa-plus"></i>
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

<!-- ── Edit Modal ── -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Leave Credits — <span id="modal_name"></span></h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update_single">
        <input type="hidden" name="lc_id" id="modal_lc_id">
        <div class="modal-body">
          <div class="row">
            <div class="col-6"><div class="form-group"><label>Vacation Leave (total)</label>
              <input type="number" name="vacation_leave" id="modal_vac" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Vacation Used</label>
              <input type="number" name="vacation_used" id="modal_vacused" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Sick Leave (total)</label>
              <input type="number" name="sick_leave" id="modal_sick" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Sick Used</label>
              <input type="number" name="sick_used" id="modal_sickused" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Emergency Leave (total)</label>
              <input type="number" name="emergency_leave" id="modal_emer" class="form-control" step="0.5"></div></div>
            <div class="col-6"><div class="form-group"><label>Maternity Leave</label>
              <input type="number" name="maternity_leave" id="modal_mat" class="form-control" step="1" min="0"></div></div>
            <div class="col-6"><div class="form-group"><label>Paternity Leave</label>
              <input type="number" name="paternity_leave" id="modal_pat" class="form-control" step="1" min="0"></div></div>
            <div class="col-6"><div class="form-group"><label>Solo Parent Leave</label>
              <input type="number" name="solo_parent_leave" id="modal_solo" class="form-control" step="0.5" min="0"></div></div>
            <div class="col-6"><div class="form-group">
              <label>Future Rest Day <small class="text-muted">(converted OT)</small></label>
              <input type="number" name="future_rest_day" id="modal_frd" class="form-control" step="0.5" min="0"></div></div>
          </div>
          <div class="alert alert-info" style="font-size:12px;margin-top:8px;">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Future Rest Day</strong> — An additional leave type that allows employees to convert overtime/extra working days
            into a rest day credit, especially when Vacation Leave and Sick Leave are exhausted.
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

<!-- ── Add Entitlement Modal ── -->
<div class="modal fade" id="addEntitlementModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-plus mr-2"></i>Add Leave Entitlement</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="add_entitlement">
        <div class="modal-body">
          <div class="alert alert-info" style="font-size:12px;">
            <i class="fas fa-info-circle mr-1"></i>
            Use this to grant additional leave entitlements — for example, when an employee changes civil status
            (Single to Married → Maternity/Paternity Leave), or to award Future Rest Day credits for overtime work.
          </div>
          <div class="form-group">
            <label>Employee <span class="text-danger">*</span></label>
            <select name="emp_entitlement_id" id="entEmpSel" class="form-control" required>
              <option value="">Select employee...</option>
              <?php foreach ($all_employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['emp_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Leave type to add <span class="text-danger">*</span></label>
            <select name="entitlement_type" class="form-control" required>
              <option>Vacation Leave</option>
              <option>Sick Leave</option>
              <option>Emergency Leave</option>
              <option>Maternity Leave</option>
              <option>Paternity Leave</option>
              <option>Solo Parent Leave</option>
              <option>Future Rest Day</option>
            </select>
          </div>
          <div class="form-group">
            <label>Number of days to add <span class="text-danger">*</span></label>
            <input type="number" name="entitlement_days" class="form-control" step="0.5" min="0.5" required value="1">
          </div>
          <div class="form-group">
            <label>Reason / Justification</label>
            <input type="text" name="entitlement_reason" class="form-control"
                   placeholder="e.g. Changed civil status from Single to Married — maternity leave granted">
          </div>
          <div class="alert alert-warning" style="font-size:12px;">
            <i class="fas fa-lightbulb mr-1"></i>
            <strong>Tip:</strong> For civil status changes (Single → Married), the system does NOT automatically
            add maternity/paternity leave — you must manually grant it here after verifying the supporting document.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-plus mr-1"></i> Add Entitlement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Rollover Modal ── -->
<div class="modal fade" id="rolloverModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Year-End Leave Rollover</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="year_rollover">
        <div class="modal-body">
          <div class="form-group">
            <label>Create leave credits for year:</label>
            <select name="new_year" class="form-control">
              <?php for ($y=(int)date('Y'); $y<=(int)date('Y')+2; $y++): ?>
                <option><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" name="carry_forward" class="custom-control-input" id="carry">
            <label class="custom-control-label" for="carry">Carry forward unused balance to new year</label>
          </div>
          <small class="text-muted d-block mt-2">
            This creates new leave credit records for all active employees.
            Existing records for the selected year will be skipped.
            Future Rest Day balances always reset to 0 for the new year.
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info">Run Rollover</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    $('#lcTable').DataTable({ pageLength:25 });

    // Edit modal population
    $('#editModal').on('show.bs.modal', function(e) {
        var b = $(e.relatedTarget);
        $('#modal_lc_id').val(b.data('id'));
        $('#modal_name').text(b.data('name'));
        $('#modal_vac').val(b.data('vac'));
        $('#modal_sick').val(b.data('sick'));
        $('#modal_emer').val(b.data('emer'));
        $('#modal_mat').val(b.data('mat'));
        $('#modal_pat').val(b.data('pat'));
        $('#modal_solo').val(b.data('solo'));
        $('#modal_frd').val(b.data('frd'));
        $('#modal_vacused').val(b.data('vacused'));
        $('#modal_sickused').val(b.data('sickused'));
    });
});

// Pre-select employee in Add Entitlement modal from row button
function openAddEnt(btn) {
    var empId   = $(btn).data('emp-id');
    var empName = $(btn).data('emp-name');
    $('#entEmpSel').val(empId);
    $('#addEntitlementModal').modal('show');
}
</script>
</body></html>