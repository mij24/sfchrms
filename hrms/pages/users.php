<?php
// ============================================
// FILE: pages/users.php — FIXED
// - Removed dt-export class (prevents global reinit conflict)
// - DataTable initialized only once, explicitly
// - Export buttons prompt removed (exportOptions set)
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

$get_action = isset($_GET['action']) ? $_GET['action'] : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

if ($get_action === 'delete' && $get_id) {
    $target = db_fetch($pdo, "SELECT role,username FROM users WHERE id=?", array($get_id));
    if ($target && $target['username'] !== 'admin' && $target['role'] !== 'superadmin') {
        db_run($pdo, "DELETE FROM users WHERE id=?", array($get_id));
        prg_redirect('users.php', 'User deleted.');
    } else {
        prg_redirect_error('users.php','Cannot delete the superadmin account.');
    }
}

if ($get_action === 'toggle' && $get_id) {
    $cur = db_value($pdo, "SELECT is_active FROM users WHERE id=?", array($get_id));
    db_run($pdo, "UPDATE users SET is_active=? WHERE id=?", array($cur ? 0 : 1, $get_id));
    prg_redirect('users.php', 'User status updated.');
}

if ($get_action === 'approve' && $get_id) {
    db_run($pdo,
        "UPDATE users SET account_status='Active', is_active=1, approved_by=?, approved_at=NOW() WHERE id=?",
        array((int)$_SESSION['user_id'], $get_id)
    );
    prg_redirect('users.php', 'Account approved and activated.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = clean_string($_POST['form_action']);
    $allowed = creatable_roles();

    if ($action === 'create') {

//////////////////////////////////////////////////////////////////////////////		
	$username = clean_string($_POST['username']);
    $role     = clean_string($_POST['role']);
    $emp_id   = clean_int($_POST['employee_id']);
    $acc_type = clean_string($_POST['account_type']); // 'permanent' or 'temporary'
    $allowed  = creatable_roles();

    global $ROLE_LEVELS;
    if (!isset($ROLE_LEVELS[$role])) {
        prg_redirect_error('users.php','Invalid role selected.');
    }
    if (!is_admin() && isset($ROLE_LEVELS[$role]) && $ROLE_LEVELS[$role] > get_user_level()) {
        prg_redirect_error('users.php','You cannot assign a role higher than your own.');
    }
    if (db_value($pdo,"SELECT COUNT(*) FROM users WHERE username=?",array($username))) {
        prg_redirect_error('users.php',"Username '$username' already taken.");
    }

    $emp_id_val    = $emp_id > 0 ? $emp_id : null;
    $creator       = (int)$_SESSION['user_id'];
    $assigned_office = ($role === 'exec_secretary') ? clean_string(isset($_POST['assigned_office']) ? $_POST['assigned_office'] : '') : null;

    if ($acc_type === 'temporary') {
        // ── Generate raw password (plain text) ──
        $raw_temp = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        // ── Hash it ONCE ──
        $hashed = password_hash($raw_temp, PASSWORD_DEFAULT);

        // ── Store: hashed in password, plain in temp_password_plain ──
        db_run($pdo,
            "INSERT INTO users
             (username, password, temp_password_plain, role, employee_id,
              account_status, must_change_password, is_active, created_by, assigned_office)
             VALUES (?, ?, ?, ?, ?, 'Temporary', 1, 1, ?, ?)",
            array($username, $hashed, $raw_temp, $role, $emp_id_val, $creator, $assigned_office)
        );

        // ── Show the PLAIN password to HR (only time it's visible) ──
        $_SESSION['flash_temp_pw'] = array(
            'username' => $username,
            'password' => $raw_temp,  // <-- plain text, NOT the hash
        );

    } else {
        // ── Permanent account ──
        if (empty($_POST['password'])) {
            prg_redirect_error('users.php','Password required for permanent accounts.');
        }

        // ── Hash ONCE ──
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);

        db_run($pdo,
            "INSERT INTO users
             (username, password, role, employee_id,
              account_status, is_active, created_by, assigned_office)
             VALUES (?, ?, ?, ?, 'Active', 1, ?, ?)",
            array($username, $hashed, $role, $emp_id_val, $creator, $assigned_office)
        );
    }

    prg_redirect('users.php','User account created successfully.');
		
		
///////////////////////////////////////////////////////
    }

    if ($action === 'update') {
        $uid      = clean_int($_POST['user_id']);
        $username = clean_string($_POST['username']);
        $role     = clean_string($_POST['role']);
        $emp_id   = clean_int($_POST['employee_id']);

        global $ROLE_LEVELS;
        if (!isset($ROLE_LEVELS[$role])) {
            prg_redirect_error('users.php','Invalid role selected.');
        }
        if (!is_admin() && isset($ROLE_LEVELS[$role]) && $ROLE_LEVELS[$role] > get_user_level()) {
            prg_redirect_error('users.php','You cannot assign a role higher than your own.');
        }
        $assigned_office = ($role === 'exec_secretary') ? clean_string(isset($_POST['assigned_office']) ? $_POST['assigned_office'] : '') : null;

        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            db_run($pdo,
                "UPDATE users SET username=?,password=?,role=?,employee_id=?,assigned_office=? WHERE id=?",
                array($username,$hashed,$role,$emp_id>0?$emp_id:null,$assigned_office,$uid)
            );
        } else {
            db_run($pdo,
                "UPDATE users SET username=?,role=?,employee_id=?,assigned_office=? WHERE id=?",
                array($username,$role,$emp_id>0?$emp_id:null,$assigned_office,$uid)
            );
        }
        prg_redirect('users.php','User updated.');
    }
}

$employees = db_fetchall($pdo,"SELECT id,full_name,employee_id FROM employees ORDER BY full_name");
$all_users = db_fetchall($pdo,
    "SELECT u.*, e.full_name AS emp_name, e.employee_id AS emp_code, cb.username AS created_by_name
     FROM users u
     LEFT JOIN employees e ON u.employee_id=e.id
     LEFT JOIN users cb    ON u.created_by=cb.id
     ORDER BY u.created_at DESC"
);
$allowed_roles = creatable_roles();
global $ROLE_LABELS, $ROLE_COLORS;

$temp_pw_notice = null;
if (!empty($_SESSION['flash_temp_pw'])) {
    $temp_pw_notice = $_SESSION['flash_temp_pw'];
    unset($_SESSION['flash_temp_pw']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Management | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <!-- DataTables Buttons CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables-buttons/2.4.1/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">User Management</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Users</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <?php if ($temp_pw_notice): ?>
    <div class="alert alert-warning alert-dismissible">
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      <i class="fas fa-key mr-1"></i> <strong>Temporary account created</strong><br>
      Username: <code><?= htmlspecialchars($temp_pw_notice['username']) ?></code> &nbsp;|&nbsp;
      Temp password: <code><?= htmlspecialchars($temp_pw_notice['password']) ?></code><br>
      <small>Share this with the employee. They must change it on first login.</small>
    </div>
    <?php endif; ?>

    <!-- Full width table with Add button -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">All user accounts</h3>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createModal">
          <i class="fas fa-plus mr-1"></i> Create user
        </button>
      </div>
      <div class="card-body" style="padding:16px 20px 0;">
        <!-- NOTE: NO dt-export class here — we init this table manually below -->
        <table class="table table-sm table-bordered table-hover" id="userTable">
          <thead class="thead-light">
            <tr>
              <th>Username</th><th>Role</th><th>Office</th><th>Linked employee</th>
              <th>Active</th><th>Account status</th><th>Created by</th>
              <th>Last login</th><th style="min-width:130px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_users as $u):
              $rl  = isset($ROLE_LABELS[$u['role']]) ? $ROLE_LABELS[$u['role']] : ucfirst($u['role']);
              $rc  = isset($ROLE_COLORS[$u['role']]) ? $ROLE_COLORS[$u['role']] : 'secondary';
              $asc = array('Active'=>'success','Pending'=>'warning','Temporary'=>'info','Suspended'=>'danger');
              $asb = isset($asc[$u['account_status']]) ? $asc[$u['account_status']] : 'secondary';
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
              <td><span class="badge badge-<?= $rc ?>"><?= $rl ?></span></td>
              <td style="font-size:12px;">
                <?php if (!empty($u['assigned_office'])): ?>
                  <span class="badge badge-dark" style="font-size:10px;"><?= htmlspecialchars($u['assigned_office']) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?= $u['emp_name'] ? htmlspecialchars($u['emp_name']).'<br><small class="text-muted">'.htmlspecialchars($u['emp_code']).'</small>' : '—' ?>
              </td>
              <td class="text-center">
                <span class="badge badge-<?= $u['is_active'] ? 'success':'secondary' ?>">
                  <?= $u['is_active'] ? 'Yes' : 'No' ?>
                </span>
              </td>
              <td><span class="badge badge-<?= $asb ?>"><?= htmlspecialchars($u['account_status']) ?></span></td>
              <td style="font-size:12px;"><?= htmlspecialchars($u['created_by_name'] ?: 'system') ?></td>
              <td style="font-size:12px;"><?= $u['last_login'] ? date('M d, Y g:i A',strtotime($u['last_login'])) : 'Never' ?></td>
              <td style="white-space:nowrap;">
                <?php if ($u['account_status']==='Pending'): ?>
                  <a href="users.php?action=approve&id=<?= $u['id'] ?>"
                     class="btn btn-success btn-xs" title="Approve">
                    <i class="fas fa-check"></i>
                  </a>
                <?php endif; ?>
                <button class="btn btn-warning btn-xs edit-btn"
                        data-id="<?= $u['id'] ?>"
                        data-username="<?= htmlspecialchars($u['username']) ?>"
                        data-role="<?= htmlspecialchars($u['role']) ?>"
                        data-empid="<?= (int)$u['employee_id'] ?>"
                        data-office="<?= htmlspecialchars($u['assigned_office'] ?? '') ?>"
                        data-toggle="modal" data-target="#editModal"
                        title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <a href="users.php?action=toggle&id=<?= $u['id'] ?>"
                   class="btn btn-info btn-xs"
                   title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                  <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                </a>
                <?php if ($u['username']!=='admin' && $u['role']!=='superadmin'): ?>
                <a href="users.php?action=delete&id=<?= $u['id'] ?>"
                   class="btn btn-danger btn-xs"
                   onclick="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>? This cannot be undone.')"
                   title="Delete">
                  <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header bg-primary text-white">
      <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Create user account</h5>
      <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
    </div>
    <form method="POST" action="">
      <?php csrf_field(); ?>
      <input type="hidden" name="form_action" value="create">
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group"><label>Username <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group"><label>Role <span class="text-danger">*</span></label>
              <select name="role" class="form-control" id="createRole" required
                      onchange="toggleOfficeField(this.value)">
                <option value="">Select role...</option>
                <?php
                global $ROLE_LABELS, $ROLE_LEVELS;
                foreach ($ROLE_LABELS as $rkey => $rlabel):
                    if (!is_admin() && isset($ROLE_LEVELS[$rkey]) && $ROLE_LEVELS[$rkey] > get_user_level()) continue;
                ?>
                  <option value="<?= $rkey ?>"><?= htmlspecialchars($rlabel) ?></option>
                <?php endforeach; ?>
              </select>
              <!-- Assigned Office (only for exec_secretary) -->
              <div id="officeField" style="display:none;margin-top:8px;">
                <label style="font-size:12px;">Assigned Executive Office <span class="text-danger">*</span></label>
                <select name="assigned_office" class="form-control form-control-sm">
                  <option value="">Select office...</option>
                  <option value="VP Academic">VP for Academic Affairs</option>
                  <option value="VP Administration">VP for Administration</option>
                  <option value="President">President's Office</option>
                  <option value="Board">Board of Trustees</option>
                </select>
                <small class="text-muted">This determines which executive office logbook they manage.</small>
              </div>
            </div>
            <div class="form-group"><label>Link to employee (optional)</label>
              <select name="employee_id" class="form-control">
                <option value="0">-- None --</option>
                <?php foreach ($employees as $e): ?>
                  <option value="<?= $e['id'] ?>">
                    <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group"><label>Account type</label>
              <select name="account_type" id="accType" class="form-control" onchange="togglePwField(this.value)">
                <option value="permanent">Permanent (set password now)</option>
                <option value="temporary">Temporary (auto-generate, employee changes on first login)</option>
              </select>
            </div>
            <div id="pwGroup">
              <div class="form-group"><label>Password <span class="text-danger">*</span></label>
                <input type="password" name="password" id="pwInput" class="form-control" autocomplete="new-password">
              </div>
            </div>
            <div class="alert alert-info d-none" id="tempNote" style="font-size:12px;">
              <i class="fas fa-info-circle mr-1"></i>
              A random 8-character password will be generated. It will be shown once after creation.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Create account</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-warning">
      <h5 class="modal-title">Edit user</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <form method="POST" action="">
      <?php csrf_field(); ?>
      <input type="hidden" name="form_action" value="update">
      <input type="hidden" name="user_id" id="edit_uid">
      <div class="modal-body">
        <div class="form-group"><label>Username</label>
          <input type="text" name="username" id="edit_username" class="form-control" required>
        </div>
        <div class="form-group"><label>Role</label>
          <select name="role" id="edit_role" class="form-control"
                  onchange="toggleOfficeFieldEdit(this.value)">
            <?php
            global $ROLE_LABELS, $ROLE_LEVELS;
            foreach ($ROLE_LABELS as $rkey => $rlabel):
                if (!is_admin() && isset($ROLE_LEVELS[$rkey]) && $ROLE_LEVELS[$rkey] > get_user_level()) continue;
            ?>
              <option value="<?= $rkey ?>"><?= htmlspecialchars($rlabel) ?></option>
            <?php endforeach; ?>
          </select>
          <!-- Assigned Office for exec_secretary -->
          <div id="officeFieldEdit" style="display:none;margin-top:8px;">
            <label style="font-size:12px;">Assigned Executive Office</label>
            <select name="assigned_office" id="edit_office" class="form-control form-control-sm">
              <option value="">Select office...</option>
              <option value="VP Academic">VP for Academic Affairs</option>
              <option value="VP Administration">VP for Administration</option>
              <option value="President">President's Office</option>
              <option value="Board">Board of Trustees</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Link to employee</label>
          <select name="employee_id" id="edit_empid" class="form-control">
            <option value="0">-- None --</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?= $e['id'] ?>">
                <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>New password <small class="text-muted">(leave blank to keep current)</small></label>
          <input type="password" name="password" class="form-control" autocomplete="new-password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i>Update</button>
      </div>
    </form>
  </div></div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// ── Initialize userTable ONCE, explicitly ──
// Do NOT add dt-export class to this table.
// The global dt-export handler in footer.php would cause reinit conflict.
$(function(){
    if ($.fn.DataTable.isDataTable('#userTable')) {
        $('#userTable').DataTable().destroy();
    }
    $('#userTable').DataTable({
        pageLength: 25,
        order: [[0,'asc']],
        dom: '<"d-flex justify-content-between align-items-center mb-3"lB><"mt-1"f>rtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel mr-1"></i>Excel',
                className: 'btn btn-success btn-sm',
                // exportOptions prevents the "Are you sure?" browser prompt
                exportOptions: { columns: ':not(:last-child)' },
                title: 'HRMS Users',
                filename: 'hrms_users_' + new Date().toISOString().slice(0,10)
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv mr-1"></i>CSV',
                className: 'btn btn-info btn-sm',
                exportOptions: { columns: ':not(:last-child)' },
                filename: 'hrms_users_' + new Date().toISOString().slice(0,10)
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print mr-1"></i>Print',
                className: 'btn btn-secondary btn-sm',
                exportOptions: { columns: ':not(:last-child)' },
                autoPrint: false   // shows print preview instead of auto-printing
            }
        ]
    });

    // Populate edit modal
    $('#editModal').on('show.bs.modal', function(e){
        var b = $(e.relatedTarget);
        $('#edit_uid').val(b.data('id'));
        $('#edit_username').val(b.data('username'));
        $('#edit_role').val(b.data('role'));
        $('#edit_empid').val(b.data('empid') || 0);
        var office = b.data('office') || '';
        $('#edit_office').val(office);
        toggleOfficeFieldEdit(b.data('role'));
    });
});

function togglePwField(val) {
    if (val === 'temporary') {
        $('#pwGroup').addClass('d-none');
        $('#tempNote').removeClass('d-none');
        $('#pwInput').removeAttr('required');
    } else {
        $('#pwGroup').removeClass('d-none');
        $('#tempNote').addClass('d-none');
        $('#pwInput').attr('required','required');
    }
}

function toggleOfficeField(role) {
    document.getElementById('officeField').style.display =
        (role === 'exec_secretary') ? 'block' : 'none';
}

function toggleOfficeFieldEdit(role) {
    var el = document.getElementById('officeFieldEdit');
    if (el) el.style.display = (role === 'exec_secretary') ? 'block' : 'none';
}
</script>
</body></html>