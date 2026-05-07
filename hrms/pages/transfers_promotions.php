<?php
// ============================================
// FILE: pages/transfers_promotions.php
// Employee Lifecycle — Transfers & Promotions
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

// Approve / Reject
if ($get_action === 'approve' && $get_id) {
    $uid = (int)$_SESSION['user_id'];
    $rec = db_fetch($pdo, "SELECT * FROM employee_transfers WHERE id=?", array($get_id));
    if ($rec) {
        // Apply the transfer to the employee record
        $update_fields = array();
        $update_vals   = array();
        if ($rec['to_department_id']) { $update_fields[] = "department_id=?"; $update_vals[] = $rec['to_department_id']; }
        if ($rec['to_position_id'])   { $update_fields[] = "position_id=?";   $update_vals[] = $rec['to_position_id']; }
        if ($rec['to_salary'])        { $update_fields[] = "basic_salary=?";   $update_vals[] = $rec['to_salary']; }
        if (!empty($update_fields)) {
            $update_vals[] = $rec['employee_id'];
            db_run($pdo, "UPDATE employees SET ".implode(',',$update_fields)." WHERE id=?", $update_vals);
        }
        db_run($pdo, "UPDATE employee_transfers SET status='Approved', approved_by=? WHERE id=?", array($uid, $get_id));
        prg_redirect('transfers_promotions.php', 'Transfer/Promotion approved and applied.');
    }
}
if ($get_action === 'reject' && $get_id) {
    db_run($pdo, "UPDATE employee_transfers SET status='Rejected' WHERE id=?", array($get_id));
    prg_redirect('transfers_promotions.php', 'Record rejected.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $emp_id      = clean_int($_POST['employee_id']);
    $type        = clean_string($_POST['transfer_type']);
    $from_dept   = clean_int($_POST['from_department_id']) ?: null;
    $to_dept     = clean_int($_POST['to_department_id'])   ?: null;
    $from_pos    = clean_int($_POST['from_position_id'])   ?: null;
    $to_pos      = clean_int($_POST['to_position_id'])     ?: null;
    $from_rank   = clean_string($_POST['from_rank']);
    $to_rank     = clean_string($_POST['to_rank']);
    $from_salary = clean_float($_POST['from_salary']) ?: null;
    $to_salary   = clean_float($_POST['to_salary'])   ?: null;
    $eff_date    = clean_date($_POST['effective_date']);
    $reason      = clean_string($_POST['reason'], 1000);
    $endorsed    = clean_int($_POST['endorsed_by']) ?: null;

    db_run($pdo,
        "INSERT INTO employee_transfers
         (employee_id, transfer_type, from_department_id, to_department_id,
          from_position_id, to_position_id, from_rank, to_rank,
          from_salary, to_salary, effective_date, reason, endorsed_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        array($emp_id,$type,$from_dept,$to_dept,$from_pos,$to_pos,
              $from_rank,$to_rank,$from_salary,$to_salary,$eff_date,$reason,$endorsed)
    );
    prg_redirect('transfers_promotions.php', ucfirst($type).' record filed successfully.');
}

$employees   = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id, e.basic_salary,
            d.name AS dept, p.title AS position
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     WHERE e.employment_status='Active' ORDER BY e.full_name"
);
$departments = db_fetchall($pdo, "SELECT * FROM departments ORDER BY name");
$positions   = db_fetchall($pdo, "SELECT * FROM positions ORDER BY title");

$records = db_fetchall($pdo,
    "SELECT et.*, e.full_name, e.employee_id AS emp_code,
            fd.name AS from_dept, td.name AS to_dept,
            fp.title AS from_pos, tp.title AS to_pos,
            u.full_name AS endorsed_name
     FROM employee_transfers et
     JOIN employees e ON et.employee_id=e.id
     LEFT JOIN departments fd ON et.from_department_id=fd.id
     LEFT JOIN departments td ON et.to_department_id=td.id
     LEFT JOIN positions fp ON et.from_position_id=fp.id
     LEFT JOIN positions tp ON et.to_position_id=tp.id
     LEFT JOIN employees u ON et.endorsed_by=u.id
     ORDER BY et.created_at DESC"
);

$type_colors = array(
    'Transfer'=>'info','Promotion'=>'success',
    'Demotion'=>'warning','Reclassification'=>'primary'
);
$stat_colors = array('Pending'=>'warning','Approved'=>'success','Rejected'=>'danger','Cancelled'=>'secondary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transfers & Promotions | HRMS</title>
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
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Transfers &amp; Promotions</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item">Employee Lifecycle</li>
          <li class="breadcrumb-item active">Transfers / Promotions</li>
        </ol>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <!-- Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">File Transfer / Promotion</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group"><label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" id="empSel" class="form-control" required
                        onchange="prefillFrom(this)">
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"
                            data-dept="<?= $e['dept'] ?>"
                            data-pos="<?= $e['position'] ?>"
                            data-salary="<?= $e['basic_salary'] ?>"
                            data-dept-id="<?= $e['id'] ?>"
                            data-pos-id="<?= $e['id'] ?>">
                      <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Type</label>
                <select name="transfer_type" class="form-control">
                  <?php foreach (array('Transfer','Promotion','Demotion','Reclassification') as $t): ?>
                    <option><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <hr>
              <p style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;">FROM</p>
              <div class="form-group"><label>From department</label>
                <select name="from_department_id" id="fromDept" class="form-control">
                  <option value="0">Select...</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>From position</label>
                <select name="from_position_id" id="fromPos" class="form-control">
                  <option value="0">Select...</option>
                  <?php foreach ($positions as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Current rank</label>
                <input type="text" name="from_rank" id="fromRank" class="form-control">
              </div>
              <div class="form-group"><label>Current salary (&#8369;)</label>
                <input type="number" name="from_salary" id="fromSalary" class="form-control" step="0.01">
              </div>
              <hr>
              <p style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;">TO</p>
              <div class="form-group"><label>To department</label>
                <select name="to_department_id" class="form-control">
                  <option value="0">No change</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>To position</label>
                <select name="to_position_id" class="form-control">
                  <option value="0">No change</option>
                  <?php foreach ($positions as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>New rank</label>
                <input type="text" name="to_rank" class="form-control">
              </div>
              <div class="form-group"><label>New salary (&#8369;)</label>
                <input type="number" name="to_salary" class="form-control" step="0.01">
              </div>
              <hr>
              <div class="form-group"><label>Effective date</label>
                <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="form-group"><label>Endorsed by</label>
                <select name="endorsed_by" class="form-control">
                  <option value="0">-- None --</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Reason / Justification</label>
                <textarea name="reason" class="form-control" rows="3"></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i> File Record
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Records table -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All Transfer / Promotion Records</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-bordered table-hover" id="tpTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Type</th><th>From</th><th>To</th>
                    <th>Effective</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($records as $r):
                  $tc = isset($type_colors[$r['transfer_type']]) ? $type_colors[$r['transfer_type']] : 'secondary';
                  $sc = isset($stat_colors[$r['status']]) ? $stat_colors[$r['status']] : 'secondary';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><br>
                      <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small></td>
                  <td><span class="badge badge-<?= $tc ?>"><?= $r['transfer_type'] ?></span></td>
                  <td style="font-size:12px;">
                    <?= htmlspecialchars($r['from_dept'] ?: '—') ?><br>
                    <small><?= htmlspecialchars($r['from_pos'] ?: '—') ?></small>
                  </td>
                  <td style="font-size:12px;">
                    <?= htmlspecialchars($r['to_dept'] ?: 'No change') ?><br>
                    <small><?= htmlspecialchars($r['to_pos'] ?: 'No change') ?></small>
                    <?php if ($r['to_salary']): ?>
                      <br><small class="text-success">&#8369; <?= number_format($r['to_salary'],2) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= $r['effective_date'] ? date('M d, Y', strtotime($r['effective_date'])) : '—' ?></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $r['status'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <?php if ($r['status'] === 'Pending' && is_hr_head()): ?>
                    <a href="transfers_promotions.php?action=approve&id=<?= $r['id'] ?>"
                       class="btn btn-success btn-xs"
                       onclick="return confirm('Approve and apply this change?')">
                      <i class="fas fa-check"></i>
                    </a>
                    <a href="transfers_promotions.php?action=reject&id=<?= $r['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Reject this record?')">
                      <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
// Pre-fill "From" fields based on selected employee
var empData = <?= json_encode(array_map(function($e) {
    return array(
        'id'         => $e['id'],
        'dept'       => $e['dept'],
        'position'   => $e['position'],
        'salary'     => $e['basic_salary'],
    );
}, $employees)) ?>;

function prefillFrom(sel) {
    var id  = parseInt(sel.value);
    var emp = empData.find(function(e){ return e.id === id; });
    if (!emp) return;
    document.getElementById('fromRank').value   = '';
    document.getElementById('fromSalary').value = emp.salary || '';
}
$(function(){ $('#tpTable').DataTable({ pageLength:15, order:[[4,'desc']] }); });
</script>
</body></html>