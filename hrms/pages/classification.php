<?php
// ============================================
// FILE: pages/classification.php
// Replaces probationary.php
// Manages Teaching/Non-Teaching classification:
// Contractual → Probationary → Regular/Permanent
// Includes notifications to Dept Head + HR Head
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';
$filter_cat = isset($_GET['cat'])    ? $_GET['cat']       : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// ── Classification actions ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['form_action']);

    // Recommend for regularization (Dept Head action)
    if ($action === 'recommend') {
        $emp_id = clean_int($_POST['employee_id']);
        $notes  = clean_string($_POST['notes'],500);
        $uid    = (int)$_SESSION['user_id'];

        $emp = db_fetch($pdo,"SELECT * FROM employees WHERE id=?",array($emp_id));
        db_run($pdo,
            "UPDATE employees SET
                classification_status='For Regularization',
                regularization_recommended_by=?,
                regularization_recommended_at=NOW(),
                classification_note=?
             WHERE id=?",
            array($uid,$notes,$emp_id)
        );
        // Log history
        db_run($pdo,
            "INSERT INTO classification_history (employee_id,from_status,to_status,action_type,recommended_by,recommender_role,notes,effective_date)
             VALUES (?,?,?,?,?,?,?,CURDATE())",
            array($emp_id,$emp['classification_status'],'For Regularization','Recommended',$uid,$_SESSION['role'],$notes)
        );
        // Notify HR Head
        notify_hr_head($pdo,$emp_id,'regularization_recommended',
            "Dept Head recommends ".get_emp_name($pdo,$emp_id)." for regularization.");
        prg_redirect('classification.php','Recommendation submitted. HR Head has been notified.');
    }

    // Approve regularization (HR Head action)
    if ($action === 'approve_regular') {
        $emp_id    = clean_int($_POST['employee_id']);
        $eff_date  = clean_string($_POST['effective_date']);
        $notes     = clean_string($_POST['notes'],500);
        $uid       = (int)$_SESSION['user_id'];

        $emp = db_fetch($pdo,"SELECT * FROM employees WHERE id=?",array($emp_id));
        db_run($pdo,
            "UPDATE employees SET
                employment_type='Regular',
                classification_status='Regular',
                classification_date=?,
                classification_note=?,
                next_review_date=NULL
             WHERE id=?",
            array($eff_date,$notes,$emp_id)
        );
        db_run($pdo,
            "INSERT INTO classification_history (employee_id,from_status,to_status,action_type,approved_by,notes,effective_date)
             VALUES (?,?,?,?,?,?,?)",
            array($emp_id,$emp['classification_status'],'Regular','Approved Regular',$uid,$notes,$eff_date)
        );
        prg_redirect('classification.php','Employee regularized successfully.');
    }

    // Hold / extend probation
    if ($action === 'hold') {
        $emp_id      = clean_int($_POST['employee_id']);
        $new_review  = clean_string($_POST['new_review_date']);
        $reason      = clean_string($_POST['notes'],500);
        $uid         = (int)$_SESSION['user_id'];

        $emp = db_fetch($pdo,"SELECT * FROM employees WHERE id=?",array($emp_id));
        db_run($pdo,
            "UPDATE employees SET
                classification_status='On Hold',
                next_review_date=?,
                classification_note=?
             WHERE id=?",
            array($new_review,$reason,$emp_id)
        );
        db_run($pdo,
            "INSERT INTO classification_history (employee_id,from_status,to_status,action_type,recommended_by,notes,effective_date)
             VALUES (?,?,?,?,?,?,?)",
            array($emp_id,$emp['classification_status'],'On Hold','Held',$uid,$reason,$new_review)
        );
        prg_redirect('classification.php','Probation period placed on hold.');
    }

    // Terminate / non-renewal
    if ($action === 'terminate') {
        $emp_id = clean_int($_POST['employee_id']);
        $reason = clean_string($_POST['notes'],500);
        $uid    = (int)$_SESSION['user_id'];

        $emp = db_fetch($pdo,"SELECT * FROM employees WHERE id=?",array($emp_id));
        db_run($pdo,
            "UPDATE employees SET employment_status='Terminated', classification_status='Terminated', classification_note=? WHERE id=?",
            array($reason,$emp_id)
        );
        db_run($pdo,
            "INSERT INTO classification_history (employee_id,from_status,to_status,action_type,recommended_by,notes,effective_date)
             VALUES (?,?,?,?,?,?,CURDATE())",
            array($emp_id,$emp['classification_status'],'Terminated','Non-renewal',$uid,$reason)
        );
        prg_redirect('classification.php','Employee status set to terminated.');
    }

    // Send review reminder to dept head
    if ($action === 'send_reminder') {
        $emp_id = clean_int($_POST['employee_id']);
        $msg    = "Please review the classification status of ".get_emp_name($pdo,$emp_id).". Their probationary review is due.";
        notify_dept_head($pdo,$emp_id,'review_reminder',$msg);
        prg_redirect('classification.php','Reminder sent to department head.');
    }
}

// ── GET actions ───────────────────────────────────────────────────
if ($get_action === 'mark_read' && $get_id) {
    db_run($pdo,"UPDATE classification_notifications SET is_read=1 WHERE id=?",array($get_id));
    prg_redirect('classification.php','');
}

// ── Helper functions ──────────────────────────────────────────────
function get_emp_name(PDO $pdo, $emp_id) {
    return db_value($pdo,"SELECT full_name FROM employees WHERE id=?",array($emp_id)) ?: 'Employee';
}
function notify_hr_head(PDO $pdo, $emp_id, $type, $msg) {
    $hr_users = db_fetchall($pdo,
        "SELECT id FROM users WHERE role IN ('hr_head','admin','superadmin') AND is_active=1"
    );
    foreach ($hr_users as $u) {
        db_run($pdo,
            "INSERT INTO classification_notifications (employee_id,notified_user_id,notification_type,message,action_required,due_date)
             VALUES (?,?,?,?,1,CURDATE())",
            array($emp_id,$u['id'],$type,$msg)
        );
    }
}
function notify_dept_head(PDO $pdo, $emp_id, $type, $msg) {
    $emp = db_fetch($pdo,"SELECT department_id FROM employees WHERE id=?",array($emp_id));
    if (!$emp || !$emp['department_id']) return;
    $head = db_fetch($pdo,"SELECT head_employee_id FROM departments WHERE id=?",array($emp['department_id']));
    if (!$head || !$head['head_employee_id']) return;
    $head_user = db_fetch($pdo,"SELECT id FROM users WHERE employee_id=?",array($head['head_employee_id']));
    if (!$head_user) return;
    db_run($pdo,
        "INSERT INTO classification_notifications (employee_id,notified_user_id,notification_type,message,action_required,due_date)
         VALUES (?,?,?,?,1,DATE_ADD(CURDATE(),INTERVAL 7 DAY))",
        array($emp_id,$head_user['id'],$type,$msg)
    );
}

// ── Load data ─────────────────────────────────────────────────────
$where_parts = array("e.employment_status='Active'");
$params = array();
if ($filter_cat) { $where_parts[] = "e.employee_category_id=?"; $params[] = $filter_cat; }
if ($filter_status) { $where_parts[] = "e.classification_status=?"; $params[] = $filter_status; }
$where_sql = "WHERE ".implode(" AND ", $where_parts);

$employees = db_fetchall($pdo,
    "SELECT e.*, d.name AS dept, p.title AS position,
            ec.name AS category_name,
            TIMESTAMPDIFF(MONTH,e.date_hired,CURDATE()) AS months_served,
            u.username AS recommender_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     LEFT JOIN employee_categories ec ON e.employee_category_id=ec.id
     LEFT JOIN users u ON e.regularization_recommended_by=u.id
     $where_sql
     ORDER BY e.next_review_date ASC, e.full_name",
    $params
);

$categories = db_fetchall($pdo,"SELECT * FROM employee_categories ORDER BY sort_order,name");

// Due for review (next_review_date within 30 days or overdue)
$due_review = db_fetchall($pdo,
    "SELECT e.*, d.name AS dept,
            DATEDIFF(e.next_review_date,CURDATE()) AS days_remaining
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE e.employment_status='Active'
       AND e.classification_status IN ('Probationary','Contractual','On Hold')
       AND e.next_review_date IS NOT NULL
       AND e.next_review_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY e.next_review_date ASC"
);

// My notifications
$my_uid = (int)$_SESSION['user_id'];
$notifications = db_fetchall($pdo,
    "SELECT cn.*, e.full_name AS emp_name, e.employee_id AS emp_code
     FROM classification_notifications cn
     JOIN employees e ON cn.employee_id=e.id
     WHERE cn.notified_user_id=? AND cn.is_read=0
     ORDER BY cn.created_at DESC LIMIT 20",
    array($my_uid)
);

$status_colors = array(
    'Contractual'         => 'secondary',
    'Substitute'          => 'light',
    'Probationary'        => 'warning',
    'For Regularization'  => 'info',
    'On Hold'             => 'danger',
    'Regular'             => 'success',
    'Terminated'          => 'dark',
);
$status_labels = array(
    'Contractual'         => 'Contractual/Temporary',
    'Substitute'          => 'Substitute',
    'Probationary'        => 'Probationary',
    'For Regularization'  => 'For Regularization',
    'On Hold'             => 'On Hold',
    'Regular'             => 'Regular/Permanent',
    'Terminated'          => 'Non-renewed/Terminated',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employment Classification | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .status-pill { font-size:12px; padding:3px 10px; border-radius:99px; font-weight:500; display:inline-block; }
    .review-urgent { background:#fff3cd;border-left:4px solid #ffc107; }
    .review-overdue { background:#f8d7da;border-left:4px solid #dc3545; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Employment Classification</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Classification</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Notifications -->
    <?php if (!empty($notifications)): ?>
    <div class="card card-warning card-outline mb-3">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-bell mr-1 text-warning"></i>
          Pending classification actions (<?= count($notifications) ?>)
        </h3>
      </div>
      <div class="card-body p-0">
        <?php foreach ($notifications as $n): ?>
        <div class="d-flex align-items-center p-2 border-bottom">
          <div style="flex:1;">
            <strong><?= htmlspecialchars($n['emp_name']) ?></strong>
            <span class="text-muted ml-1">(<?= htmlspecialchars($n['emp_code']) ?>)</span><br>
            <small><?= htmlspecialchars($n['message']) ?></small>
          </div>
          <div>
            <a href="classification.php?action=mark_read&id=<?= $n['id'] ?>"
               class="btn btn-xs btn-secondary">Dismiss</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Due for review alert -->
    <?php if (!empty($due_review)): ?>
    <div class="card mb-3">
      <div class="card-header bg-warning">
        <h3 class="card-title text-dark">
          <i class="fas fa-exclamation-triangle mr-1"></i>
          <?= count($due_review) ?> employee(s) due for classification review
        </h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light">
            <tr><th>Employee</th><th>Department</th><th>Status</th><th>Review date</th><th>Days</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($due_review as $dr):
              $days = (int)$dr['days_remaining'];
              $row_class = $days < 0 ? 'review-overdue' : 'review-urgent';
            ?>
            <tr class="<?= $row_class ?>">
              <td><strong><?= htmlspecialchars($dr['full_name']) ?></strong><br>
                  <small><?= htmlspecialchars($dr['employee_id']) ?></small></td>
              <td><?= htmlspecialchars($dr['dept']?:'—') ?></td>
              <td><span class="badge badge-<?= isset($status_colors[$dr['classification_status']]) ? $status_colors[$dr['classification_status']] : 'secondary' ?>">
                <?= htmlspecialchars($dr['classification_status']) ?></span></td>
              <td><?= date('M d, Y',strtotime($dr['next_review_date'])) ?></td>
              <td><strong class="<?= $days < 0 ? 'text-danger' : 'text-warning' ?>">
                <?= $days < 0 ? abs($days).' days overdue' : $days.' days left' ?></strong></td>
              <td>
                <button class="btn btn-success btn-xs action-btn"
                        data-id="<?= $dr['id'] ?>" data-name="<?= htmlspecialchars($dr['full_name']) ?>"
                        data-action="recommend"
                        data-toggle="modal" data-target="#actionModal">
                  <i class="fas fa-user-check mr-1"></i>Recommend Regular
                </button>
                <button class="btn btn-warning btn-xs action-btn"
                        data-id="<?= $dr['id'] ?>" data-name="<?= htmlspecialchars($dr['full_name']) ?>"
                        data-action="hold"
                        data-toggle="modal" data-target="#actionModal">
                  <i class="fas fa-pause mr-1"></i>Hold
                </button>
                <form method="POST" action="" class="d-inline">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="form_action" value="send_reminder">
                  <input type="hidden" name="employee_id" value="<?= $dr['id'] ?>">
                  <button type="submit" class="btn btn-info btn-xs">
                    <i class="fas fa-bell mr-1"></i>Remind Dept Head
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="" class="form-inline flex-wrap" style="gap:10px;">
          <label>Category:</label>
          <select name="cat" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $filter_cat==$cat['id']?'selected':'' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label>Status:</label>
          <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach ($status_labels as $key => $lbl): ?>
              <option value="<?= $key ?>" <?= $filter_status===$key?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <a href="classification.php" class="btn btn-sm btn-secondary">Reset</a>
        </form>
      </div>
    </div>

    <!-- Main table -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">All employee classifications (<?= count($employees) ?>)</h3>
      </div>
      <div class="card-body" style="padding:16px 20px 0;">
        <table class="table table-sm table-bordered table-hover dt-export" id="classTable">
          <thead class="thead-light">
            <tr>
              <th>Employee</th><th>Category</th><th>Department</th><th>Position</th>
              <th>Classification status</th><th>Months served</th>
              <th>Next review</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp):
              $sc    = isset($status_colors[$emp['classification_status']]) ? $status_colors[$emp['classification_status']] : 'secondary';
              $sl    = isset($status_labels[$emp['classification_status']])  ? $status_labels[$emp['classification_status']]  : $emp['classification_status'];
              $months = (int)$emp['months_served'];

              // Probation limit: non-teaching=6mo, teaching=36mo (3yrs)
              $is_teaching = $emp['employee_category_id'] && $emp['employee_category_id'] <= 8;
              $prob_limit  = $is_teaching ? 36 : 6;
              $pct         = $prob_limit > 0 ? min(100,round(($months/$prob_limit)*100)) : 0;
            ?>
            <tr>
              <td>
                <a href="view_employee.php?id=<?= $emp['id'] ?>">
                  <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                </a><br>
                <small class="text-muted"><?= htmlspecialchars($emp['employee_id']) ?></small>
              </td>
              <td>
                <?= htmlspecialchars($emp['category_name'] ?: '—') ?>
                <?php if ($emp['is_part_time']): ?>
                  <span class="badge badge-light ml-1" style="font-size:10px;">Part-time</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($emp['dept'] ?: '—') ?></td>
              <td><?= htmlspecialchars($emp['position'] ?: '—') ?></td>
              <td>
                <span class="badge badge-<?= $sc ?>"><?= $sl ?></span>
                <?php if ($emp['classification_status']==='For Regularization'): ?>
                  <br><small class="text-info">By: <?= htmlspecialchars($emp['recommender_name'] ?: 'N/A') ?></small>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size:12px;"><?= $months ?> / <?= $prob_limit ?> mo</div>
                <div class="progress mt-1" style="height:4px;">
                  <div class="progress-bar bg-<?= $pct>=100?'danger':($pct>=80?'warning':'success') ?>"
                       style="width:<?= $pct ?>%"></div>
                </div>
              </td>
              <td style="font-size:12px;">
                <?php if ($emp['next_review_date']): ?>
                  <?= date('M d, Y',strtotime($emp['next_review_date'])) ?>
                  <?php $dr = (int)ceil((strtotime($emp['next_review_date'])-time())/86400); ?>
                  <?php if ($dr < 0): ?>
                    <span class="badge badge-danger">Overdue</span>
                  <?php elseif ($dr <= 30): ?>
                    <span class="badge badge-warning"><?= $dr ?>d left</span>
                  <?php endif; ?>
                <?php else: echo '—'; endif; ?>
              </td>
              <td style="white-space:nowrap;">
                <?php if (in_array($emp['classification_status'],array('Probationary','On Hold','Contractual'))): ?>
                <button class="btn btn-success btn-xs action-btn"
                        data-id="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['full_name']) ?>"
                        data-action="recommend"
                        data-toggle="modal" data-target="#actionModal"
                        title="Recommend for regularization">
                  <i class="fas fa-level-up-alt"></i>
                </button>
                <button class="btn btn-warning btn-xs action-btn"
                        data-id="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['full_name']) ?>"
                        data-action="hold"
                        data-toggle="modal" data-target="#actionModal"
                        title="Place on hold / extend">
                  <i class="fas fa-pause"></i>
                </button>
                <button class="btn btn-danger btn-xs action-btn"
                        data-id="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['full_name']) ?>"
                        data-action="terminate"
                        data-toggle="modal" data-target="#actionModal"
                        title="Non-renewal / terminate">
                  <i class="fas fa-times"></i>
                </button>
                <?php endif; ?>
                <?php if ($emp['classification_status']==='For Regularization' && is_hr_head()): ?>
                <button class="btn btn-primary btn-xs action-btn"
                        data-id="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['full_name']) ?>"
                        data-action="approve_regular"
                        data-toggle="modal" data-target="#actionModal"
                        title="Approve regularization">
                  <i class="fas fa-check-double"></i> Regularize
                </button>
                <?php endif; ?>
                <a href="view_employee.php?id=<?= $emp['id'] ?>"
                   class="btn btn-info btn-xs" title="View profile">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header" id="modalHeader">
      <h5 class="modal-title" id="modalTitle">Classification Action</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <form method="POST" action="">
      <?php csrf_field(); ?>
      <input type="hidden" name="form_action" id="modal_action">
      <input type="hidden" name="employee_id" id="modal_emp_id">
      <div class="modal-body">
        <p id="modal_desc" class="text-muted" style="font-size:13px;"></p>

        <div id="eff_date_group" class="form-group" style="display:none;">
          <label>Effective date</label>
          <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div id="new_review_group" class="form-group" style="display:none;">
          <label>New review date</label>
          <input type="date" name="new_review_date" class="form-control"
                 value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
        </div>

        <div class="form-group">
          <label>Notes / Remarks <span class="text-danger">*</span></label>
          <textarea name="notes" class="form-control" rows="3" required
                    placeholder="Enter reason or remarks..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn" id="modalSubmitBtn">Confirm</button>
      </div>
    </form>
  </div></div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
var actionConfig = {
  recommend: {
    title: 'Recommend for Regularization',
    desc:  'Recommending {name} for regularization. This will notify the HR Head for final approval.',
    header: 'bg-success text-white',
    btn: 'btn-success',
    btnTxt: 'Submit recommendation',
    showEffDate: false,
    showReviewDate: false
  },
  approve_regular: {
    title: 'Approve Regularization',
    desc:  'Approving {name} as a Regular/Permanent employee. This action is final.',
    header: 'bg-primary text-white',
    btn: 'btn-primary',
    btnTxt: 'Approve & Regularize',
    showEffDate: true,
    showReviewDate: false
  },
  hold: {
    title: 'Place Probation On Hold',
    desc:  'Placing {name} probation on hold. Set a new review date.',
    header: 'bg-warning',
    btn: 'btn-warning',
    btnTxt: 'Confirm Hold',
    showEffDate: false,
    showReviewDate: true
  },
  terminate: {
    title: 'Non-renewal / Termination',
    desc:  'Non-renewing {name}\'s contract. This will set status to Terminated.',
    header: 'bg-danger text-white',
    btn: 'btn-danger',
    btnTxt: 'Confirm Non-renewal',
    showEffDate: false,
    showReviewDate: false
  }
};

$(function(){
    $(document).on('click','.action-btn', function(){
        var id     = $(this).data('id');
        var name   = $(this).data('name');
        var action = $(this).data('action');
        var cfg    = actionConfig[action];
        if (!cfg) return;

        $('#modal_action').val(action);
        $('#modal_emp_id').val(id);
        $('#modalTitle').text(cfg.title);
        $('#modal_desc').text(cfg.desc.replace('{name}',name));
        $('#modalHeader').attr('class','modal-header '+cfg.header);
        $('#modalSubmitBtn').attr('class','btn '+cfg.btn).text(cfg.btnTxt);
        $('#eff_date_group').toggle(cfg.showEffDate);
        $('#new_review_group').toggle(cfg.showReviewDate);
    });
});
</script>
</body></html>