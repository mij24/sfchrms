<?php
// ============================================
// FILE: pages/positions.php — FIXED
// PRG pattern + duplicate check
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'delete' && $get_id) {
    $in_use = db_value($pdo,"SELECT COUNT(*) FROM employees WHERE position_id=?",array($get_id));
    if ($in_use) {
        prg_redirect_error('positions.php',"Cannot delete — $in_use employee(s) hold this position.");
    }
    db_run($pdo,"DELETE FROM positions WHERE id=?",array($get_id));
    prg_redirect('positions.php','Position deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title   = trim($_POST['title']);
    $level   = trim($_POST['job_level']);
    $grade   = trim($_POST['salary_grade']);
    $min     = (float)$_POST['salary_min'];
    $max     = (float)$_POST['salary_max'];
    $dept_id = (int)$_POST['department_id'] > 0 ? (int)$_POST['department_id'] : null;
    $etype   = trim($_POST['employment_type']);
    $post_id = isset($_POST['pos_id']) ? (int)$_POST['pos_id'] : 0;

    if (empty($title)) {
        prg_redirect_error('positions.php','Position title is required.');
    }

    // Duplicate check within same department
    $dup_sql    = $post_id
        ? "SELECT COUNT(*) FROM positions WHERE title=? AND department_id<=>? AND id!=?"
        : "SELECT COUNT(*) FROM positions WHERE title=? AND department_id<=>?";
    $dup_params = $post_id
        ? array($title,$dept_id,$post_id)
        : array($title,$dept_id);
    if (db_value($pdo,$dup_sql,$dup_params)) {
        prg_redirect_error('positions.php',"Position '$title' already exists in that department.");
    }

    if ($post_id) {
        db_run($pdo,
            "UPDATE positions SET title=?,job_level=?,salary_grade=?,salary_min=?,
             salary_max=?,department_id=?,employment_type=? WHERE id=?",
            array($title,$level,$grade,$min,$max,$dept_id,$etype,$post_id)
        );
        prg_redirect('positions.php','Position updated.');
    } else {
        db_run($pdo,
            "INSERT INTO positions (title,job_level,salary_grade,salary_min,salary_max,department_id,employment_type)
             VALUES (?,?,?,?,?,?,?)",
            array($title,$level,$grade,$min,$max,$dept_id,$etype)
        );
        prg_redirect('positions.php','Position added.');
    }
}

$edit      = ($get_action==='edit' && $get_id)
    ? db_fetch($pdo,"SELECT * FROM positions WHERE id=?",array($get_id))
    : null;
$depts     = db_fetchall($pdo,"SELECT * FROM departments ORDER BY name");
$positions = db_fetchall($pdo,
    "SELECT p.*,d.name AS dept_name,
            (SELECT COUNT(*) FROM employees WHERE position_id=p.id) AS headcount
     FROM positions p LEFT JOIN departments d ON p.department_id=d.id ORDER BY p.title"
);
$levels    = array('Staff','Supervisor','Manager','Director','VP','C-Level','Rank & File','Faculty','Administrative');
$etypes    = array('Regular','Probationary','Contractual','Part-time','Project-based');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Positions | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Positions</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title"><?= $edit ? 'Edit position' : 'Add position' ?></h3>
            <?php if ($edit): ?>
              <div class="card-tools">
                <a href="positions.php" class="btn btn-xs btn-secondary">Cancel</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <?php if ($edit): ?><input type="hidden" name="pos_id" value="<?= $edit['id'] ?>"><?php endif; ?>
              <div class="form-group">
                <label>Job title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required
                       value="<?= $edit ? htmlspecialchars($edit['title']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-control">
                  <option value="0">-- None --</option>
                  <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['id'] ?>"
                      <?= ($edit && $edit['department_id']==$d['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($d['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Job level</label>
                <select name="job_level" class="form-control">
                  <?php foreach ($levels as $lv): ?>
                    <option <?= ($edit && $edit['job_level']===$lv)?'selected':'' ?>><?= $lv ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Salary grade</label>
                <input type="text" name="salary_grade" class="form-control"
                       value="<?= $edit ? htmlspecialchars($edit['salary_grade']) : '' ?>"
                       placeholder="e.g. SG-11">
              </div>
              <div class="row">
                <div class="col-6">
                  <div class="form-group">
                    <label>Min salary (&#8369;)</label>
                    <input type="number" name="salary_min" class="form-control" step="0.01"
                           value="<?= $edit ? $edit['salary_min'] : '' ?>">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Max salary (&#8369;)</label>
                    <input type="number" name="salary_max" class="form-control" step="0.01"
                           value="<?= $edit ? $edit['salary_max'] : '' ?>">
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label>Employment type</label>
                <select name="employment_type" class="form-control">
                  <?php foreach ($etypes as $et): ?>
                    <option <?= ($edit && $edit['employment_type']===$et)?'selected':'' ?>><?= $et ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i> <?= $edit ? 'Update position' : 'Add position' ?>
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All positions (<?= count($positions) ?>)</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-bordered table-hover dt-export" id="posTable">
              <thead class="thead-light">
                <tr><th>Title</th><th>Department</th><th>Level</th><th>Grade</th>
                    <th>Salary range</th><th class="text-center">HC</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($positions as $p): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                  <td><?= htmlspecialchars($p['dept_name'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($p['job_level'] ?: '—') ?></td>
                  <td><code><?= htmlspecialchars($p['salary_grade'] ?: '—') ?></code></td>
                  <td style="font-size:12px;">
                    <?= ($p['salary_min']||$p['salary_max'])
                        ? '&#8369;'.number_format($p['salary_min']).' – &#8369;'.number_format($p['salary_max'])
                        : '—' ?>
                  </td>
                  <td class="text-center"><span class="badge badge-info"><?= $p['headcount'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="positions.php?action=edit&id=<?= $p['id'] ?>"
                       class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <a href="positions.php?action=delete&id=<?= $p['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Delete <?= htmlspecialchars($p['title']) ?>?')">
                      <i class="fas fa-trash"></i>
                    </a>
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