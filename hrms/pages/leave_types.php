<?php
// ============================================
// FILE: pages/leave_types.php
// Manage leave types — days, eligibility, rules
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'delete' && $get_id) {
    db_run($pdo,"UPDATE leave_types SET is_active=0 WHERE id=?",array($get_id));
    prg_redirect('leave_types.php','Leave type deactivated.');
}
if ($get_action === 'restore' && $get_id) {
    db_run($pdo,"UPDATE leave_types SET is_active=1 WHERE id=?",array($get_id));
    prg_redirect('leave_types.php','Leave type restored.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name         = trim($_POST['name']);
    $code         = strtoupper(trim($_POST['code']));
    $days         = (float)$_POST['days_per_year'];
    $is_paid      = isset($_POST['is_paid'])           ? 1 : 0;
    $req_doc      = isset($_POST['requires_document'])  ? 1 : 0;
    $carry        = isset($_POST['carry_forward'])       ? 1 : 0;
    $max_carry    = (float)$_POST['max_carry_days'];
    $gender       = trim($_POST['gender_restriction']);
    $emp_type     = trim($_POST['employment_type']);
    $min_tenure   = (int)$_POST['min_tenure_months'];
    $desc         = trim($_POST['description']);
    $post_id      = isset($_POST['lt_id']) ? (int)$_POST['lt_id'] : 0;

    if (empty($name)) { prg_redirect_error('leave_types.php','Name is required.'); }

    // Duplicate code check
    $dup_sql = $post_id
        ? "SELECT COUNT(*) FROM leave_types WHERE code=? AND id!=?"
        : "SELECT COUNT(*) FROM leave_types WHERE code=?";
    $dup_p = $post_id ? array($code,$post_id) : array($code);
    if ($code && db_value($pdo,$dup_sql,$dup_p)) {
        prg_redirect_error('leave_types.php',"Leave code '$code' already exists.");
    }

    if ($post_id) {
        db_run($pdo,
            "UPDATE leave_types SET name=?,code=?,days_per_year=?,is_paid=?,requires_document=?,
             carry_forward=?,max_carry_days=?,gender_restriction=?,employment_type=?,
             min_tenure_months=?,description=? WHERE id=?",
            array($name,$code,$days,$is_paid,$req_doc,$carry,$max_carry,
                  $gender,$emp_type,$min_tenure,$desc,$post_id)
        );
        prg_redirect('leave_types.php','Leave type updated.');
    } else {
        db_run($pdo,
            "INSERT INTO leave_types (name,code,days_per_year,is_paid,requires_document,
             carry_forward,max_carry_days,gender_restriction,employment_type,
             min_tenure_months,description)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            array($name,$code,$days,$is_paid,$req_doc,$carry,$max_carry,
                  $gender,$emp_type,$min_tenure,$desc)
        );
        prg_redirect('leave_types.php','Leave type added.');
    }
}

$edit  = ($get_action==='edit' && $get_id)
    ? db_fetch($pdo,"SELECT * FROM leave_types WHERE id=?",array($get_id))
    : null;
$types = db_fetchall($pdo,"SELECT * FROM leave_types ORDER BY is_active DESC, name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leave Types | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Leave Types</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <!-- Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title"><?= $edit ? 'Edit leave type' : 'Add leave type' ?></h3>
            <?php if ($edit): ?>
              <div class="card-tools">
                <a href="leave_types.php" class="btn btn-xs btn-secondary">Cancel</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <?php if ($edit): ?><input type="hidden" name="lt_id" value="<?= $edit['id'] ?>"><?php endif; ?>

              <div class="row">
                <div class="col-8">
                  <div class="form-group">
                    <label>Leave name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>"
                           placeholder="e.g. Vacation leave">
                  </div>
                </div>
                <div class="col-4">
                  <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="code" class="form-control" maxlength="5"
                           value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>"
                           placeholder="VL">
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Days per year</label>
                <input type="number" name="days_per_year" class="form-control"
                       step="0.5" min="0"
                       value="<?= $edit ? $edit['days_per_year'] : 15 ?>">
                <small class="text-muted">Set 0 for offsetting/earned leave (no fixed days)</small>
              </div>

              <div class="row">
                <div class="col-6">
                  <div class="form-group">
                    <label>Gender restriction</label>
                    <select name="gender_restriction" class="form-control">
                      <?php foreach (array('All','Male','Female') as $g): ?>
                        <option <?= ($edit && $edit['gender_restriction']===$g)?'selected':'' ?>><?= $g ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Employment type</label>
                    <select name="employment_type" class="form-control">
                      <option value="All" <?= ($edit && $edit['employment_type']==='All')?'selected':'' ?>>All</option>
                      <?php foreach (array('Regular','Probationary','Contractual','Part-time') as $et): ?>
                        <option <?= ($edit && $edit['employment_type']===$et)?'selected':'' ?>><?= $et ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Minimum tenure (months)</label>
                <input type="number" name="min_tenure_months" class="form-control"
                       min="0" value="<?= $edit ? $edit['min_tenure_months'] : 0 ?>">
                <small class="text-muted">0 = available from day 1</small>
              </div>

              <div class="row">
                <div class="col-12">
                  <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" name="is_paid" class="custom-control-input" id="isPaid"
                           <?= (!$edit || $edit['is_paid']) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="isPaid">Paid leave</label>
                  </div>
                  <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" name="requires_document" class="custom-control-input" id="reqDoc"
                           <?= ($edit && $edit['requires_document']) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="reqDoc">Requires supporting document</label>
                  </div>
                  <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" name="carry_forward" class="custom-control-input" id="carryFwd"
                           <?= ($edit && $edit['carry_forward']) ? 'checked' : '' ?>
                           onchange="document.getElementById('maxCarryGrp').style.display=this.checked?'block':'none'">
                    <label class="custom-control-label" for="carryFwd">Allow carry-forward to next year</label>
                  </div>
                </div>
              </div>

              <div class="form-group" id="maxCarryGrp"
                   style="display:<?= ($edit && $edit['carry_forward']) ? 'block' : 'none' ?>;">
                <label>Max carry-forward days</label>
                <input type="number" name="max_carry_days" class="form-control"
                       step="0.5" value="<?= $edit ? $edit['max_carry_days'] : 0 ?>">
              </div>

              <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i> <?= $edit ? 'Update' : 'Add leave type' ?>
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All leave types</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-bordered table-hover dt-export" id="ltTable">
              <thead class="thead-light">
                <tr>
                  <th>Name</th><th>Code</th><th class="text-center">Days/yr</th>
                  <th>Eligibility</th><th>Paid</th><th>Doc req.</th>
                  <th>Carry fwd</th><th>Status</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($types as $lt): ?>
                <tr class="<?= !$lt['is_active'] ? 'text-muted' : '' ?>">
                  <td><strong><?= htmlspecialchars($lt['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($lt['code'] ?: '—') ?></code></td>
                  <td class="text-center"><?= $lt['days_per_year'] > 0 ? $lt['days_per_year'] : 'Earned' ?></td>
                  <td style="font-size:12px;">
                    <?= htmlspecialchars($lt['gender_restriction']) ?>
                    <?= $lt['employment_type'] !== 'All' ? ' / '.$lt['employment_type'] : '' ?>
                    <?= $lt['min_tenure_months'] > 0 ? ' / '.$lt['min_tenure_months'].'mo+' : '' ?>
                  </td>
                  <td class="text-center">
                    <span class="badge badge-<?= $lt['is_paid']?'success':'secondary' ?>">
                      <?= $lt['is_paid'] ? 'Paid' : 'Unpaid' ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <?= $lt['requires_document'] ? '<i class="fas fa-check text-warning"></i>' : '—' ?>
                  </td>
                  <td class="text-center">
                    <?= $lt['carry_forward']
                        ? '<span class="badge badge-info">'.($lt['max_carry_days']>0?$lt['max_carry_days'].'d':'Yes').'</span>'
                        : '—' ?>
                  </td>
                  <td>
                    <span class="badge badge-<?= $lt['is_active']?'success':'secondary' ?>">
                      <?= $lt['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td style="white-space:nowrap;">
                    <a href="leave_types.php?action=edit&id=<?= $lt['id'] ?>"
                       class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($lt['is_active']): ?>
                    <a href="leave_types.php?action=delete&id=<?= $lt['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Deactivate this leave type?')">
                      <i class="fas fa-ban"></i>
                    </a>
                    <?php else: ?>
                    <a href="leave_types.php?action=restore&id=<?= $lt['id'] ?>"
                       class="btn btn-success btn-xs">
                      <i class="fas fa-undo"></i>
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
</body></html>