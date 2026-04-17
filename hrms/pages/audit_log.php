<?php
// ============================================
// FILE: pages/audit_log.php
// View all system activity
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_role(ROLE_ADMIN);

$filter_action = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$filter_table  = isset($_GET['table'])       ? $_GET['table']       : '';
$filter_user   = isset($_GET['user_id'])     ? (int)$_GET['user_id']: 0;
$filter_date   = isset($_GET['date'])        ? $_GET['date']        : '';

$where = array("1=1");
$params = array();
if ($filter_action) { $where[] = "action=?"; $params[] = $filter_action; }
if ($filter_table)  { $where[] = "table_name=?"; $params[] = $filter_table; }
if ($filter_user)   { $where[] = "user_id=?"; $params[] = $filter_user; }
if ($filter_date)   { $where[] = "DATE(created_at)=?"; $params[] = $filter_date; }

$logs = db_fetchall($pdo,
    "SELECT * FROM audit_logs WHERE ".implode(" AND ",$where)."
     ORDER BY created_at DESC LIMIT 500",
    $params
);

$all_users   = db_fetchall($pdo,"SELECT id,username FROM users ORDER BY username");
$all_tables  = db_fetchall($pdo,"SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL ORDER BY table_name");
$all_actions = db_fetchall($pdo,"SELECT DISTINCT action FROM audit_logs ORDER BY action");

$action_colors = array(
    'INSERT'=>'success','UPDATE'=>'warning','DELETE'=>'danger',
    'VIEW'=>'info','LOGIN'=>'primary','LOGOUT'=>'secondary','EXPORT'=>'dark'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Audit Log | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Audit Log</h1></div></div>
  <div class="content"><div class="container-fluid">

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="" class="form-inline flex-wrap" style="gap:8px;">
          <select name="action_type" class="form-control form-control-sm">
            <option value="">All actions</option>
            <?php foreach ($all_actions as $a): ?>
              <option <?= $filter_action===$a['action']?'selected':'' ?>><?= $a['action'] ?></option>
            <?php endforeach; ?>
          </select>
          <select name="table" class="form-control form-control-sm">
            <option value="">All tables</option>
            <?php foreach ($all_tables as $t): ?>
              <option <?= $filter_table===$t['table_name']?'selected':'' ?>><?= $t['table_name'] ?></option>
            <?php endforeach; ?>
          </select>
          <select name="user_id" class="form-control form-control-sm">
            <option value="0">All users</option>
            <?php foreach ($all_users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= $filter_user==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date) ?>">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
          <a href="audit_log.php" class="btn btn-sm btn-secondary">Reset</a>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Audit trail (last 500 records)</h3></div>
      <div class="card-body" style="padding:16px 20px 0;">
        <table class="table table-sm table-hover dt-export" id="auditTable">
          <thead class="thead-light">
            <tr><th>Date/Time</th><th>User</th><th>Action</th><th>Table</th>
                <th>Record ID</th><th>Changes</th><th>IP</th><th>Page</th></tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log):
              $ac = isset($action_colors[$log['action']]) ? $action_colors[$log['action']] : 'secondary';
              $changes_decoded = $log['changes'] ? json_decode($log['changes'],true) : null;
            ?>
            <tr>
              <td style="font-size:12px;white-space:nowrap;"><?= date('M d, Y H:i:s',strtotime($log['created_at'])) ?></td>
              <td><?= htmlspecialchars($log['username'] ?: '—') ?></td>
              <td><span class="badge badge-<?= $ac ?>"><?= htmlspecialchars($log['action']) ?></span></td>
              <td><code style="font-size:11px;"><?= htmlspecialchars($log['table_name'] ?: '—') ?></code></td>
              <td class="text-center"><?= $log['record_id'] ?: '—' ?></td>
              <td style="font-size:11px;max-width:200px;">
                <?php if ($changes_decoded && is_array($changes_decoded)): ?>
                  <?php foreach (array_slice($changes_decoded,0,3,true) as $k=>$v): ?>
                    <div><strong><?= htmlspecialchars($k) ?>:</strong>
                      <?php if (is_array($v)): ?>
                        <span class="text-danger"><?= htmlspecialchars(substr($v['old']??'',0,20)) ?></span>
                        &rarr;
                        <span class="text-success"><?= htmlspecialchars(substr($v['new']??'',0,20)) ?></span>
                      <?php else: ?>
                        <?= htmlspecialchars(substr($v,0,30)) ?>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php elseif ($log['changes']): ?>
                  <span class="text-muted" style="font-size:10px;"><?= htmlspecialchars(substr($log['changes'],0,60)) ?>...</span>
                <?php else: echo '—'; endif; ?>
              </td>
              <td style="font-size:11px;"><?= htmlspecialchars($log['ip_address'] ?: '—') ?></td>
              <td style="font-size:11px;"><?= htmlspecialchars($log['page'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>