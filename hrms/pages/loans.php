<?php
// ============================================
// FILE: pages/loans.php
// Loans & cash advances
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$get_action = isset($_GET['action']) ? $_GET['action']   : '';

if ($get_action === 'approve' && $get_id) {
    $uid = (int)$_SESSION['user_id'];
    db_run($pdo, "UPDATE loans SET status='Active', approved_by=?, approved_at=NOW() WHERE id=?", array($uid, $get_id));
    //$success = "Loan approved and activated.";
	prg_redirect('loans.php', 'Loan approved and activated.');
}
if ($get_action === 'reject' && $get_id) {
    db_run($pdo, "UPDATE loans SET status='Rejected' WHERE id=?", array($get_id));
    //$success = "Loan rejected.";
	prg_redirect('loans.php', 'Loan rejected.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action']);

    if ($action === 'add_loan') {
        $emp_id    = clean_int($_POST['employee_id']);
        $type      = clean_string($_POST['loan_type']);
        $amount    = clean_float($_POST['amount']);
        $months    = clean_int($_POST['total_months']);
        $amort     = $months > 0 ? round($amount / $months, 2) : 0;
        $released  = clean_date($_POST['date_released']);
        $remarks   = clean_string($_POST['remarks'], 500);

        db_run($pdo,
            "INSERT INTO loans (employee_id,loan_type,amount,monthly_amortization,total_months,balance,date_released,remarks)
             VALUES (?,?,?,?,?,?,?,?)",
            array($emp_id, $type, $amount, $amort, $months, $amount, $released, $remarks)
        );
        //$success = "Loan request filed.";
		prg_redirect('loans.php', 'Loan request filed.');
    }

    if ($action === 'add_payment') {
        $loan_id = clean_int($_POST['loan_id']);
        $pay_amt = clean_float($_POST['payment_amount']);
        $pay_date= clean_date($_POST['payment_date']);

        db_run($pdo,
            "INSERT INTO loan_payments (loan_id,amount,payment_date) VALUES (?,?,?)",
            array($loan_id, $pay_amt, $pay_date)
        );
        db_run($pdo,
            "UPDATE loans SET amount_paid = amount_paid + ?,
                              balance = balance - ?,
                              status = IF(balance - ? <= 0, 'Fully paid', status)
             WHERE id=?",
            array($pay_amt, $pay_amt, $pay_amt, $loan_id)
        );
        $success = "Payment recorded.";
    }
}

$employees = db_fetchall($pdo, "SELECT id,full_name,employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$loans     = db_fetchall($pdo,
    "SELECT l.*, e.full_name, e.employee_id AS emp_code
     FROM loans l JOIN employees e ON l.employee_id=e.id
     ORDER BY l.created_at DESC"
);
$loan_types = array('Personal loan','Cash advance','SSS loan','Pag-IBIG loan','Emergency loan');
$stat_color = array('Pending'=>'warning','Approved'=>'info','Active'=>'success','Fully paid'=>'secondary','Rejected'=>'danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Loans | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Loans &amp; Cash Advances</h1></div></div>
  <div class="content"><div class="container-fluid">
    
	  <?=prg_flash()?>
	  <!--<?php// if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php //endif; ?>-->
	  
    <div class="row">
      <!-- Add loan form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">File loan request</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="add_loan">
              <div class="form-group"><label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Loan type</label>
                <select name="loan_type" class="form-control">
                  <?php foreach ($loan_types as $lt): ?><option><?= $lt ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Amount (&#8369;) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
              </div>
              <div class="form-group"><label>Total months to pay</label>
                <input type="number" name="total_months" class="form-control" min="1" value="12">
              </div>
              <div class="form-group"><label>Date released</label>
                <input type="date" name="date_released" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="form-group"><label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2"></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i> File loan</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Loans table -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All loans</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="loanTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Type</th><th>Amount</th><th>Amort/mo</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($loans as $loan):
                  $sc = isset($stat_color[$loan['status']]) ? $stat_color[$loan['status']] : 'secondary';
                  $pct = $loan['amount'] > 0 ? round((($loan['amount'] - $loan['balance']) / $loan['amount']) * 100) : 0;
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($loan['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($loan['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($loan['loan_type']) ?></td>
                  <td>&#8369; <?= number_format($loan['amount'],2) ?></td>
                  <td>&#8369; <?= number_format($loan['monthly_amortization'],2) ?></td>
                  <td>
                    <div>&#8369; <?= number_format($loan['balance'],2) ?></div>
                    <div class="progress mt-1" style="height:4px;">
                      <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                    </div>
                  </td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $loan['status'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <?php if ($loan['status'] === 'Pending'): ?>
                      <a href="loans.php?action=approve&id=<?= $loan['id'] ?>" class="btn btn-success btn-xs" onclick="return confirm('Approve this loan?')"><i class="fas fa-check"></i></a>
                      <a href="loans.php?action=reject&id=<?= $loan['id'] ?>"  class="btn btn-danger  btn-xs" onclick="return confirm('Reject this loan?')"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                    <?php if ($loan['status'] === 'Active'): ?>
                      <button class="btn btn-info btn-xs pay-btn"
                              data-id="<?= $loan['id'] ?>"
                              data-name="<?= htmlspecialchars($loan['full_name']) ?>"
                              data-amort="<?= $loan['monthly_amortization'] ?>"
                              data-toggle="modal" data-target="#payModal">
                        <i class="fas fa-money-bill"></i> Pay
                      </button>
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

<!-- Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Record payment — <span id="pay_name"></span></h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <form method="POST" action="">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="add_payment">
      <input type="hidden" name="loan_id" id="pay_loan_id">
      <div class="modal-body">
        <div class="form-group"><label>Payment amount (&#8369;)</label>
          <input type="number" name="payment_amount" id="pay_amount" class="form-control" step="0.01" required></div>
        <div class="form-group"><label>Payment date</label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Record payment</button>
      </div>
    </form>
  </div></div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    $('#loanTable').DataTable({ pageLength:15 });
    $('#payModal').on('show.bs.modal', function(e){
        var b = $(e.relatedTarget);
        $('#pay_loan_id').val(b.data('id'));
        $('#pay_name').text(b.data('name'));
        $('#pay_amount').val(b.data('amort'));
    });
});
</script>
</body></html>