<?php
// ============================================
// FILE: pages/allowances.php
// Allowances & deductions manager
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$success = ''; $error = '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$get_action = isset($_GET['action']) ? $_GET['action']   : '';

if ($get_action === 'delete' && $get_id) {
    db_run($pdo, "DELETE FROM employee_allowances WHERE id=?", array($get_id));
    //$success = "Record removed.";
	prg_redirect('allowances.php', 'Record removed.');
}
if ($get_action === 'toggle' && $get_id) {
    $cur = db_value($pdo, "SELECT is_active FROM employee_allowances WHERE id=?", array($get_id));
    db_run($pdo, "UPDATE employee_allowances SET is_active=? WHERE id=?", array($cur ? 0 : 1, $get_id));
    //$success = "Status updated.";
	prg_redirect('allowances.php', 'Status updated.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action']);

    if ($action === 'add_type') {
        $name    = clean_string($_POST['name']);
        $type    = clean_string($_POST['type']);
        $taxable = isset($_POST['is_taxable']) ? 1 : 0;
        $recur   = isset($_POST['is_recurring']) ? 1 : 0;
        db_run($pdo, "INSERT INTO allowance_types (name,type,is_taxable,is_recurring) VALUES (?,?,?,?)",
            array($name,$type,$taxable,$recur));
        //$success = "Allowance/deduction type added.";
		prg_redirect('allowances.php', 'Allowance/Deduction type added.');
    }

    if ($action === 'assign') {
        $emp_id  = clean_int($_POST['employee_id']);
        $type_id = clean_int($_POST['allowance_type_id']);
        $amount  = clean_float($_POST['amount']);
        $eff     = clean_date($_POST['effective_date']);
        $end     = clean_date($_POST['end_date']);
        $end_val = $end ? $end : null;
        db_run($pdo,
            "INSERT INTO employee_allowances (employee_id,allowance_type_id,amount,effective_date,end_date) VALUES (?,?,?,?,?)",
            array($emp_id,$type_id,$amount,$eff,$end_val)
        );
        //$success = "Allowance/deduction assigned.";
		prg_redirect('allowances.php', 'Allowance/Deduction assigned.');
    }
}

$employees = db_fetchall($pdo, "SELECT id,full_name,employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$types     = db_fetchall($pdo, "SELECT * FROM allowance_types ORDER BY type,name");
$records   = db_fetchall($pdo,
    "SELECT ea.*, e.full_name, e.employee_id AS emp_code, at.name AS type_name, at.type AS type_cat
     FROM employee_allowances ea
     JOIN employees e ON ea.employee_id=e.id
     JOIN allowance_types at ON ea.allowance_type_id=at.id
     ORDER BY e.full_name, at.type"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Allowances | SFCHRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Allowances &amp; Deductions</h1></div></div>
  <div class="content"><div class="container-fluid">
    
	  <!--<?php //if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php //endif; ?>-->
	  
    <div class="row">
      <div class="col-md-4">
        <!-- Add type -->
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Add type</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="add_type">
              <div class="form-group"><label>Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
              <div class="form-group"><label>Category</label>
                <select name="type" class="form-control">
                  <option>Allowance</option><option>Deduction</option>
                </select>
              </div>
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" name="is_taxable" class="custom-control-input" id="taxable">
                <label class="custom-control-label" for="taxable">Taxable</label>
              </div>
              <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" name="is_recurring" class="custom-control-input" id="recur" checked>
                <label class="custom-control-label" for="recur">Recurring (every payroll)</label>
              </div>
              <button type="submit" class="btn btn-success btn-block"><i class="fas fa-plus mr-1"></i> Add type</button>
            </form>
          </div>
        </div>

        <!-- Assign -->
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Assign to employee</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="assign">
              <div class="form-group"><label>Employee</label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)</option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Type</label>
                <select name="allowance_type_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id'] ?>">[<?= $t['type'] ?>] <?= htmlspecialchars($t['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Amount (&#8369;)</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Effective date</label><input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>"></div></div>
                <div class="col-6"><div class="form-group"><label>End date (opt.)</label><input type="date" name="end_date" class="form-control"></div></div>
              </div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-link mr-1"></i> Assign</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Employee allowances &amp; deductions</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="allowTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Type</th><th>Category</th><th class="text-right">Amount</th><th>Effective</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($r['type_name']) ?></td>
                  <td><span class="badge badge-<?= $r['type_cat']==='Allowance' ? 'success' : 'danger' ?>"><?= $r['type_cat'] ?></span></td>
                  <td class="text-right">&#8369; <?= number_format($r['amount'],2) ?></td>
                  <td style="font-size:12px;"><?= $r['effective_date'] ? date('M d, Y', strtotime($r['effective_date'])) : '—' ?></td>
                  <td><span class="badge badge-<?= $r['is_active'] ? 'success' : 'secondary' ?>"><?= $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="allowances.php?action=toggle&id=<?= $r['id'] ?>" class="btn btn-info btn-xs"><i class="fas fa-toggle-on"></i></a>
                    <a href="allowances.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Remove?')"><i class="fas fa-trash"></i></a>
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
<script>$(function(){ $('#allowTable').DataTable({ pageLength:25 }); });</script>
</body></html>
