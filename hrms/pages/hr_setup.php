<?php
// ============================================
// FILE: pages/hr_setup.php — UPDATED
// Added: Salary Grades tab
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

$active_tab = isset($_GET['tab']) ? clean_string($_GET['tab']) : 'classifications';
$get_action = isset($_GET['action']) ? clean_string($_GET['action']) : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id']             : 0;
$get_type   = isset($_GET['type'])   ? clean_string($_GET['type'])   : '';

// ── Generic delete/restore handler ───────────────────────────────
if ($get_action === 'delete' && $get_id && $get_type) {
    $table_map = array(
        'classification' => 'employee_classifications',
        'rank'           => 'employee_ranks',
        'type'           => 'employment_types',
        'status'         => 'employment_statuses',
        'salary_grade'   => 'salary_grades',
    );
    if (isset($table_map[$get_type])) {
        db_run($pdo, "UPDATE {$table_map[$get_type]} SET is_active=0 WHERE id=?", array($get_id));
        $tab = $get_type === 'salary_grade' ? 'salary_grades' : $get_type.'s';
        prg_redirect("hr_setup.php?tab={$tab}", "Record deactivated.");
    }
}
if ($get_action === 'restore' && $get_id && $get_type) {
    $table_map = array(
        'classification' => 'employee_classifications',
        'rank'           => 'employee_ranks',
        'type'           => 'employment_types',
        'status'         => 'employment_statuses',
        'salary_grade'   => 'salary_grades',
    );
    if (isset($table_map[$get_type])) {
        db_run($pdo, "UPDATE {$table_map[$get_type]} SET is_active=1 WHERE id=?", array($get_id));
        $tab = $get_type === 'salary_grade' ? 'salary_grades' : $get_type.'s';
        prg_redirect("hr_setup.php?tab={$tab}", "Record restored.");
    }
}

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form_type = clean_string($_POST['form_type']);
    $post_id   = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;

    // ── CLASSIFICATION ──
    if ($form_type === 'classification') {
        $name      = trim($_POST['name']);
        $parent_id = (int)$_POST['parent_id'] > 0 ? (int)$_POST['parent_id'] : null;
        $category  = clean_string($_POST['category']);
        $desc      = clean_string($_POST['description'], 500);
        if (empty($name)) prg_redirect_error('hr_setup.php?tab=classifications', 'Name is required.');
        $dup = $post_id
            ? db_value($pdo, "SELECT COUNT(*) FROM employee_classifications WHERE name=? AND id!=?", array($name,$post_id))
            : db_value($pdo, "SELECT COUNT(*) FROM employee_classifications WHERE name=?", array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=classifications', "'$name' already exists.");
        if ($post_id) {
            db_run($pdo, "UPDATE employee_classifications SET name=?,parent_id=?,category=?,description=? WHERE id=?",
                array($name,$parent_id,$category,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=classifications', 'Classification updated.');
        } else {
            db_run($pdo, "INSERT INTO employee_classifications (name,parent_id,category,description) VALUES (?,?,?,?)",
                array($name,$parent_id,$category,$desc));
            prg_redirect('hr_setup.php?tab=classifications', 'Classification added.');
        }
    }

    // ── RANK ──
    if ($form_type === 'rank') {
        $name    = trim($_POST['name']);
        $code    = strtoupper(trim($_POST['code']));
        $cat     = clean_string($_POST['category']);
        $grade   = clean_string($_POST['salary_grade']);
        $sal_min = (float)$_POST['salary_min'];
        $sal_max = (float)$_POST['salary_max'];
        $desc    = clean_string($_POST['description'], 500);
        if (empty($name)) prg_redirect_error('hr_setup.php?tab=ranks', 'Rank name is required.');
        $dup = $post_id
            ? db_value($pdo, "SELECT COUNT(*) FROM employee_ranks WHERE name=? AND id!=?", array($name,$post_id))
            : db_value($pdo, "SELECT COUNT(*) FROM employee_ranks WHERE name=?", array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=ranks', "Rank '$name' already exists.");
        if ($post_id) {
            db_run($pdo, "UPDATE employee_ranks SET name=?,code=?,category=?,salary_grade=?,salary_min=?,salary_max=?,description=? WHERE id=?",
                array($name,$code,$cat,$grade,$sal_min,$sal_max,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=ranks', 'Rank updated.');
        } else {
            db_run($pdo, "INSERT INTO employee_ranks (name,code,category,salary_grade,salary_min,salary_max,description) VALUES (?,?,?,?,?,?,?)",
                array($name,$code,$cat,$grade,$sal_min,$sal_max,$desc));
            prg_redirect('hr_setup.php?tab=ranks', 'Rank added.');
        }
    }

    // ── EMPLOYMENT TYPE ──
    if ($form_type === 'type') {
        $name    = trim($_POST['name']);
        $code    = strtoupper(trim($_POST['code']));
        $prob_mo = (int)$_POST['probation_months'] > 0 ? (int)$_POST['probation_months'] : null;
        $desc    = clean_string($_POST['description'], 500);
        if (empty($name)) prg_redirect_error('hr_setup.php?tab=types', 'Name is required.');
        $dup = $post_id
            ? db_value($pdo, "SELECT COUNT(*) FROM employment_types WHERE name=? AND id!=?", array($name,$post_id))
            : db_value($pdo, "SELECT COUNT(*) FROM employment_types WHERE name=?", array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=types', "Type '$name' already exists.");
        if ($post_id) {
            db_run($pdo, "UPDATE employment_types SET name=?,code=?,probation_months=?,description=? WHERE id=?",
                array($name,$code,$prob_mo,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=types', 'Employment type updated.');
        } else {
            db_run($pdo, "INSERT INTO employment_types (name,code,probation_months,description) VALUES (?,?,?,?)",
                array($name,$code,$prob_mo,$desc));
            prg_redirect('hr_setup.php?tab=types', 'Employment type added.');
        }
    }

    // ── EMPLOYMENT STATUS ──
    if ($form_type === 'status') {
        $name        = trim($_POST['name']);
        $code        = strtoupper(trim($_POST['code']));
        $color       = clean_string($_POST['color']);
        $is_active_s = isset($_POST['is_active_status']) ? 1 : 0;
        $desc        = clean_string($_POST['description'], 500);
        if (empty($name)) prg_redirect_error('hr_setup.php?tab=statuses', 'Name is required.');
        $dup = $post_id
            ? db_value($pdo, "SELECT COUNT(*) FROM employment_statuses WHERE name=? AND id!=?", array($name,$post_id))
            : db_value($pdo, "SELECT COUNT(*) FROM employment_statuses WHERE name=?", array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=statuses', "Status '$name' already exists.");
        if ($post_id) {
            db_run($pdo, "UPDATE employment_statuses SET name=?,code=?,color=?,is_active_status=?,description=? WHERE id=?",
                array($name,$code,$color,$is_active_s,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=statuses', 'Employment status updated.');
        } else {
            db_run($pdo, "INSERT INTO employment_statuses (name,code,color,is_active_status,description) VALUES (?,?,?,?,?)",
                array($name,$code,$color,$is_active_s,$desc));
            prg_redirect('hr_setup.php?tab=statuses', 'Employment status added.');
        }
    }

    // ── SALARY GRADE ──
    if ($form_type === 'salary_grade') {
        $name        = trim($_POST['name']);
        $code        = strtoupper(trim($_POST['code']));
        $monthly     = (float)$_POST['monthly_rate'] > 0 ? (float)$_POST['monthly_rate'] : null;
        $category    = clean_string($_POST['category']);
        $desc        = clean_string($_POST['description'], 500);
        if (empty($name)) prg_redirect_error('hr_setup.php?tab=salary_grades', 'Salary grade name is required.');
        $dup = $post_id
            ? db_value($pdo, "SELECT COUNT(*) FROM salary_grades WHERE name=? AND id!=?", array($name,$post_id))
            : db_value($pdo, "SELECT COUNT(*) FROM salary_grades WHERE name=?", array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=salary_grades', "Salary grade '$name' already exists.");
        if ($post_id) {
            db_run($pdo, "UPDATE salary_grades SET name=?,code=?,monthly_rate=?,category=?,description=? WHERE id=?",
                array($name,$code,$monthly,$category,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=salary_grades', 'Salary grade updated.');
        } else {
            db_run($pdo, "INSERT INTO salary_grades (name,code,monthly_rate,category,description) VALUES (?,?,?,?,?)",
                array($name,$code,$monthly,$category,$desc));
            prg_redirect('hr_setup.php?tab=salary_grades', 'Salary grade added.');
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────
$classifications = db_fetchall($pdo,
    "SELECT ec.*, p.name AS parent_name FROM employee_classifications ec
     LEFT JOIN employee_classifications p ON ec.parent_id=p.id
     ORDER BY ec.parent_id IS NOT NULL, ec.sort_order, ec.name"
);
$ranks        = db_fetchall($pdo, "SELECT * FROM employee_ranks ORDER BY category, sort_order, name");
$emp_types    = db_fetchall($pdo, "SELECT * FROM employment_types ORDER BY sort_order, name");
$emp_stats    = db_fetchall($pdo, "SELECT * FROM employment_statuses ORDER BY sort_order, name");
$salary_grades= db_fetchall($pdo, "SELECT * FROM salary_grades ORDER BY sort_order, name");

// Edit record
$edit_record = null;
if ($get_action === 'edit' && $get_id && $get_type) {
    $table_map = array(
        'classification' => 'employee_classifications',
        'rank'           => 'employee_ranks',
        'type'           => 'employment_types',
        'status'         => 'employment_statuses',
        'salary_grade'   => 'salary_grades',
    );
    if (isset($table_map[$get_type])) {
        $edit_record = db_fetch($pdo, "SELECT * FROM {$table_map[$get_type]} WHERE id=?", array($get_id));
    }
}

$colors_list = array('success','primary','info','warning','danger','secondary','dark','light');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR Setup | HRMS</title>
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
      <div class="col-sm-6"><h1 class="m-0">HR Setup &amp; Master Data</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">HR Setup</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs" id="hrSetupTabs">
          <?php
          $tabs = array(
            'classifications' => array('fas fa-sitemap',       'Classifications'),
            'ranks'           => array('fas fa-medal',         'Ranks'),
            'types'           => array('fas fa-id-badge',      'Employment Types'),
            'statuses'        => array('fas fa-toggle-on',     'Employment Statuses'),
            'salary_grades'   => array('fas fa-dollar-sign',   'Salary Grades'),
            'emp_id'          => array('fas fa-id-card',       'Employee ID Generator'),
          );
          foreach ($tabs as $tid => $tdata):
          ?>
          <li class="nav-item">
            <a class="nav-link <?= $active_tab===$tid?'active':'' ?>"
               href="hr_setup.php?tab=<?= $tid ?>">
              <i class="<?= $tdata[0] ?> mr-1"></i> <?= $tdata[1] ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card-body">

        <?php
        function render_section($title, $form_type, $tab, $edit, $form_html, $table_html) {
            echo '<div class="row">';
            echo '<div class="col-md-4">';
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header d-flex justify-content-between align-items-center">';
            echo '<h3 class="card-title">'.($edit ? 'Edit '.$title : 'Add '.$title).'</h3>';
            if ($edit) echo '<a href="hr_setup.php?tab='.$tab.'" class="btn btn-xs btn-secondary">Cancel</a>';
            echo '</div><div class="card-body">';
            echo '<form method="POST" action="">';
            echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token()).'">';
            echo '<input type="hidden" name="form_type" value="'.$form_type.'">';
            if ($edit) echo '<input type="hidden" name="record_id" value="'.$edit['id'].'">';
            echo $form_html;
            echo '<button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i>';
            echo ($edit ? 'Update '.$title : 'Add '.$title).'</button>';
            echo '</form></div></div></div>';
            echo '<div class="col-md-8">'.$table_html.'</div>';
            echo '</div>';
        }
        ?>

        <!-- ── CLASSIFICATIONS TAB ── -->
        <?php if ($active_tab === 'classifications'):
          $edit = ($get_action==='edit' && $get_type==='classification') ? $edit_record : null;
          $parents = array_filter($classifications, function($c){ return !$c['parent_id']; });
          ob_start(); ?>
          <div class="form-group"><label>Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="form-group"><label>Parent (blank = top-level)</label>
            <select name="parent_id" class="form-control">
              <option value="0">-- Top level --</option>
              <?php foreach ($parents as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($edit && $edit['parent_id']==$p['id'])?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Category</label>
            <select name="category" class="form-control">
              <?php foreach (array('Teaching','Non-Teaching','Both') as $cat): ?>
                <option <?= ($edit && $edit['category']===$cat)?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();
          ob_start(); ?>
          <div class="card"><div class="card-header"><h3 class="card-title">All classifications</h3></div>
          <div class="card-body" style="padding:12px 16px 0;">
            <table class="table table-sm table-bordered dt-export" id="clTable">
              <thead class="thead-light"><tr><th>Name</th><th>Parent</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($classifications as $cl): ?>
                <tr class="<?= !$cl['is_active']?'text-muted':'' ?>">
                  <td><?= $cl['parent_id'] ? '&nbsp;&nbsp;<i class="fas fa-level-up-alt fa-rotate-90 mr-1 text-muted" style="font-size:10px;"></i>' : '' ?><strong><?= htmlspecialchars($cl['name']) ?></strong></td>
                  <td><?= htmlspecialchars($cl['parent_name']?:'—') ?></td>
                  <td><span class="badge badge-<?= $cl['category']==='Teaching'?'primary':($cl['category']==='Non-Teaching'?'success':'secondary') ?>"><?= $cl['category'] ?></span></td>
                  <td><span class="badge badge-<?= $cl['is_active']?'success':'secondary' ?>"><?= $cl['is_active']?'Active':'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="hr_setup.php?tab=classifications&action=edit&type=classification&id=<?= $cl['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($cl['is_active']): ?>
                    <a href="hr_setup.php?tab=classifications&action=delete&type=classification&id=<?= $cl['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                    <?php else: ?>
                    <a href="hr_setup.php?tab=classifications&action=restore&type=classification&id=<?= $cl['id'] ?>" class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div></div>
          <?php $table_html = ob_get_clean();
          render_section('classification','classification','classifications',$edit,$form_html,$table_html);
        endif; ?>

        <!-- ── RANKS TAB ── -->
        <?php if ($active_tab === 'ranks'):
          $edit = ($get_action==='edit' && $get_type==='rank') ? $edit_record : null;
          ob_start(); ?>
          <div class="form-group"><label>Rank name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="row">
            <div class="col-6"><div class="form-group"><label>Code</label>
              <input type="text" name="code" class="form-control" maxlength="10" value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>">
            </div></div>
            <div class="col-6"><div class="form-group"><label>Category</label>
              <select name="category" class="form-control">
                <?php foreach (array('Teaching','Non-Teaching','Both') as $cat): ?>
                  <option <?= ($edit && $edit['category']===$cat)?'selected':'' ?>><?= $cat ?></option>
                <?php endforeach; ?>
              </select>
            </div></div>
          </div>
          <div class="form-group"><label>Salary grade</label>
            <input type="text" name="salary_grade" class="form-control" value="<?= $edit ? htmlspecialchars($edit['salary_grade']) : '' ?>" placeholder="e.g. SG-11">
          </div>
          <div class="row">
            <div class="col-6"><div class="form-group"><label>Min salary (&#8369;)</label>
              <input type="number" name="salary_min" class="form-control" step="0.01" value="<?= $edit ? $edit['salary_min'] : '' ?>">
            </div></div>
            <div class="col-6"><div class="form-group"><label>Max salary (&#8369;)</label>
              <input type="number" name="salary_max" class="form-control" step="0.01" value="<?= $edit ? $edit['salary_max'] : '' ?>">
            </div></div>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();
          ob_start(); ?>
          <div class="card"><div class="card-header"><h3 class="card-title">All ranks</h3></div>
          <div class="card-body" style="padding:12px 16px 0;">
            <table class="table table-sm table-bordered dt-export" id="rankTable">
              <thead class="thead-light"><tr><th>Rank</th><th>Code</th><th>Category</th><th>Grade</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($ranks as $r): ?>
                <tr class="<?= !$r['is_active']?'text-muted':'' ?>">
                  <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($r['code']?:'—') ?></code></td>
                  <td><span class="badge badge-<?= $r['category']==='Teaching'?'primary':($r['category']==='Non-Teaching'?'success':'secondary') ?>"><?= $r['category'] ?></span></td>
                  <td><?= htmlspecialchars($r['salary_grade']?:'—') ?></td>
                  <td><span class="badge badge-<?= $r['is_active']?'success':'secondary' ?>"><?= $r['is_active']?'Active':'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="hr_setup.php?tab=ranks&action=edit&type=rank&id=<?= $r['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($r['is_active']): ?>
                    <a href="hr_setup.php?tab=ranks&action=delete&type=rank&id=<?= $r['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                    <?php else: ?>
                    <a href="hr_setup.php?tab=ranks&action=restore&type=rank&id=<?= $r['id'] ?>" class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div></div>
          <?php $table_html = ob_get_clean();
          render_section('rank','rank','ranks',$edit,$form_html,$table_html);
        endif; ?>

        <!-- ── EMPLOYMENT TYPES TAB ── -->
        <?php if ($active_tab === 'types'):
          $edit = ($get_action==='edit' && $get_type==='type') ? $edit_record : null;
          ob_start(); ?>
          <div class="form-group"><label>Type name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="form-group"><label>Code</label>
            <input type="text" name="code" class="form-control" maxlength="10" value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>" placeholder="e.g. REG">
          </div>
          <div class="form-group"><label>Probation months (blank if not applicable)</label>
            <input type="number" name="probation_months" class="form-control" min="0" value="<?= $edit ? $edit['probation_months'] : '' ?>">
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();
          ob_start(); ?>
          <div class="card"><div class="card-header"><h3 class="card-title">All employment types</h3></div>
          <div class="card-body" style="padding:12px 16px 0;">
            <table class="table table-sm table-bordered dt-export" id="typeTable">
              <thead class="thead-light"><tr><th>Type</th><th>Code</th><th>Probation</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($emp_types as $et): ?>
                <tr class="<?= !$et['is_active']?'text-muted':'' ?>">
                  <td><strong><?= htmlspecialchars($et['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($et['code']?:'—') ?></code></td>
                  <td class="text-center"><?= $et['probation_months'] ? $et['probation_months'].' mo' : '—' ?></td>
                  <td><span class="badge badge-<?= $et['is_active']?'success':'secondary' ?>"><?= $et['is_active']?'Active':'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="hr_setup.php?tab=types&action=edit&type=type&id=<?= $et['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($et['is_active']): ?>
                    <a href="hr_setup.php?tab=types&action=delete&type=type&id=<?= $et['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                    <?php else: ?>
                    <a href="hr_setup.php?tab=types&action=restore&type=type&id=<?= $et['id'] ?>" class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div></div>
          <?php $table_html = ob_get_clean();
          render_section('employment type','type','types',$edit,$form_html,$table_html);
        endif; ?>

        <!-- ── EMPLOYMENT STATUSES TAB ── -->
        <?php if ($active_tab === 'statuses'):
          $edit = ($get_action==='edit' && $get_type==='status') ? $edit_record : null;
          ob_start(); ?>
          <div class="form-group"><label>Status name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="form-group"><label>Code</label>
            <input type="text" name="code" class="form-control" maxlength="10" value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>" placeholder="e.g. ACT">
          </div>
          <div class="form-group"><label>Badge color</label>
            <select name="color" class="form-control" id="colorSel" onchange="updatePreview(this.value)">
              <?php foreach ($colors_list as $cl): ?>
                <option value="<?= $cl ?>" <?= ($edit && $edit['color']===$cl)?'selected':'' ?>><?= ucfirst($cl) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="mt-2">Preview: <span class="badge" id="colorPreview"><?= $edit ? $edit['name'] : 'Status' ?></span></div>
          </div>
          <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" name="is_active_status" class="custom-control-input" id="isActiveStatus" <?= (!$edit || $edit['is_active_status']) ? 'checked' : '' ?>>
            <label class="custom-control-label" for="isActiveStatus">Employee is actively employed</label>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();
          ob_start(); ?>
          <div class="card"><div class="card-header"><h3 class="card-title">All employment statuses</h3></div>
          <div class="card-body" style="padding:12px 16px 0;">
            <table class="table table-sm table-bordered dt-export" id="statusTable">
              <thead class="thead-light"><tr><th>Status</th><th>Code</th><th>Badge</th><th>Active?</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($emp_stats as $es): ?>
                <tr class="<?= !$es['is_active']?'text-muted':'' ?>">
                  <td><strong><?= htmlspecialchars($es['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($es['code']?:'—') ?></code></td>
                  <td><span class="badge badge-<?= htmlspecialchars($es['color']) ?>"><?= htmlspecialchars($es['name']) ?></span></td>
                  <td class="text-center"><?= $es['is_active_status'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                  <td><span class="badge badge-<?= $es['is_active']?'success':'secondary' ?>"><?= $es['is_active']?'Active':'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="hr_setup.php?tab=statuses&action=edit&type=status&id=<?= $es['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($es['is_active']): ?>
                    <a href="hr_setup.php?tab=statuses&action=delete&type=status&id=<?= $es['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                    <?php else: ?>
                    <a href="hr_setup.php?tab=statuses&action=restore&type=status&id=<?= $es['id'] ?>" class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div></div>
          <?php $table_html = ob_get_clean();
          render_section('employment status','status','statuses',$edit,$form_html,$table_html);
        endif; ?>

        <!-- ── SALARY GRADES TAB ── -->
        <?php if ($active_tab === 'salary_grades'):
          $edit = ($get_action==='edit' && $get_type==='salary_grade') ? $edit_record : null;
          ob_start(); ?>
          <div class="alert alert-light" style="font-size:12px;">
            <i class="fas fa-info-circle text-info mr-1"></i>
            Define salary grade levels here. These will appear as dropdown options in Add/Edit Employee.
          </div>
          <div class="form-group"><label>Salary grade name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>"
                   placeholder="e.g. SG-11">
          </div>
          <div class="form-group"><label>Code</label>
            <input type="text" name="code" class="form-control" maxlength="20"
                   value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>"
                   placeholder="e.g. SG11">
          </div>
          <div class="form-group"><label>Monthly rate (&#8369;) — optional</label>
            <input type="number" name="monthly_rate" class="form-control" step="0.01"
                   value="<?= $edit ? $edit['monthly_rate'] : '' ?>">
          </div>
          <div class="form-group"><label>Category</label>
            <select name="category" class="form-control">
              <?php foreach (array('Both','Teaching','Non-Teaching') as $cat): ?>
                <option <?= ($edit && $edit['category']===$cat)?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();
          ob_start(); ?>
          <div class="card"><div class="card-header"><h3 class="card-title">All salary grades</h3></div>
          <div class="card-body" style="padding:12px 16px 0;">
            <table class="table table-sm table-bordered dt-export" id="sgTable">
              <thead class="thead-light"><tr><th>Name</th><th>Code</th><th>Monthly rate</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($salary_grades as $sg): ?>
                <tr class="<?= !$sg['is_active']?'text-muted':'' ?>">
                  <td><strong><?= htmlspecialchars($sg['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($sg['code']?:'—') ?></code></td>
                  <td><?= $sg['monthly_rate'] ? '&#8369; '.number_format($sg['monthly_rate'],2) : '—' ?></td>
                  <td><span class="badge badge-<?= $sg['category']==='Teaching'?'primary':($sg['category']==='Non-Teaching'?'success':'secondary') ?>"><?= $sg['category'] ?></span></td>
                  <td><span class="badge badge-<?= $sg['is_active']?'success':'secondary' ?>"><?= $sg['is_active']?'Active':'Inactive' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="hr_setup.php?tab=salary_grades&action=edit&type=salary_grade&id=<?= $sg['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($sg['is_active']): ?>
                    <a href="hr_setup.php?tab=salary_grades&action=delete&type=salary_grade&id=<?= $sg['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                    <?php else: ?>
                    <a href="hr_setup.php?tab=salary_grades&action=restore&type=salary_grade&id=<?= $sg['id'] ?>" class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div></div>
          <?php $table_html = ob_get_clean();
          render_section('salary grade','salary_grade','salary_grades',$edit,$form_html,$table_html);
        endif; ?>

      <!-- ════ TAB: EMPLOYEE ID GENERATOR ════ -->
      <?php if ($active_tab === 'emp_id'): ?>
      <?php
      $id_depts = db_fetchall($pdo, "SELECT id, name, code FROM departments WHERE is_active=1 ORDER BY name");
      $gen_result = null;
      $gen_error  = null;
      if (isset($_POST['form_action']) && $_POST['form_action'] === 'gen_emp_id') {
          csrf_verify();
          $gd_dept = clean_int(isset($_POST['gd_dept']) ? $_POST['gd_dept'] : 0);
          $gd_dob  = clean_date(isset($_POST['gd_dob'])  ? $_POST['gd_dob']  : '');
          if (!$gd_dept) { $gen_error = 'Please select a department/office.'; }
          elseif (!$gd_dob) { $gen_error = 'Please enter the employee date of birth.'; }
          else {
              $dept_row  = db_fetch($pdo, "SELECT * FROM departments WHERE id=?", array($gd_dept));
              $raw_code  = $dept_row ? preg_replace('/[^0-9]/', '', $dept_row['code']) : '';
              if (!$raw_code) $raw_code = str_pad($gd_dept, 3, '0', STR_PAD_LEFT);
              $dept_code = str_pad(substr($raw_code,0,3), 3, '0', STR_PAD_LEFT);
              $dob_obj   = new DateTime($gd_dob);
              $mmdd      = $dob_obj->format('md');
              $prefix_lk = $dept_code.'-'.$mmdd.'-%';
              $last_seq  = db_value($pdo,
                  "SELECT MAX(CAST(SUBSTRING_INDEX(employee_id,'-',-1) AS UNSIGNED))
                   FROM employees WHERE employee_id LIKE ?",
                  array($prefix_lk)
              );
              $next_seq  = $last_seq ? ((int)$last_seq + 1) : 1;
              $gen_result = array(
                  'id'        => $dept_code.'-'.$mmdd.'-'.str_pad($next_seq,2,'0',STR_PAD_LEFT),
                  'dept_name' => $dept_row ? $dept_row['name'] : '—',
                  'dept_code' => $dept_code,
                  'dob'       => $dob_obj->format('F d, Y'),
                  'mmdd'      => $mmdd,
                  'seq'       => str_pad($next_seq,2,'0',STR_PAD_LEFT),
              );
          }
      }
      ?>
      <div class="row">
        <div class="col-md-5">
          <div class="alert alert-info" style="font-size:12px;">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Format: <code>000-MMDD-SS</code></strong><br>
            <code>000</code> = 3-digit department/office code (e.g. <strong>203</strong>)<br>
            <code>MMDD</code> = birth month+day (e.g. April 15 = <strong>0415</strong>)<br>
            <code>SS</code> = 2-digit sequence, auto-increments per dept+birthday
          </div>
          <?php if ($gen_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($gen_error) ?></div>
          <?php endif; ?>
          <form method="POST" action="">
            <?php csrf_field(); ?>
            <input type="hidden" name="tab" value="emp_id">
            <input type="hidden" name="form_action" value="gen_emp_id">
            <div class="form-group">
              <label class="font-weight-bold">Department / Office <span class="text-danger">*</span></label>
              <select name="gd_dept" id="gdDept" class="form-control" required onchange="gdPreview()">
                <option value="">Select department...</option>
                <?php foreach ($id_depts as $d):
                  $rc  = preg_replace('/[^0-9]/', '', $d['code']);
                  $rc2 = $rc ? str_pad(substr($rc,0,3),3,'0',STR_PAD_LEFT) : str_pad($d['id'],3,'0',STR_PAD_LEFT);
                ?>
                <option value="<?= $d['id'] ?>" data-code="<?= $rc2 ?>">
                  [<?= $rc2 ?>] <?= htmlspecialchars($d['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="font-weight-bold">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="gd_dob" id="gdDob" class="form-control" required onchange="gdPreview()">
              <small class="text-muted">Determines the MMDD portion of the Employee ID.</small>
            </div>
            <div class="p-3 mb-3" style="background:#f8f9fa;border-radius:8px;text-align:center;">
              <div style="font-size:11px;color:#6c757d;margin-bottom:4px;">Live Preview</div>
              <div style="font-size:26px;font-weight:800;font-family:monospace;">
                <span style="color:#084298;" id="gd_dept">???</span>-<span style="color:#664d03;" id="gd_mmdd">????</span>-<span style="color:#0a3622;" id="gd_seq">??</span>
              </div>
              <div style="font-size:11px;color:#aaa;">sequence determined on generate</div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
              <i class="fas fa-magic mr-1"></i> Generate Employee ID
            </button>
          </form>
        </div>
        <div class="col-md-7">
          <?php if ($gen_result): ?>
          <div class="card card-success mb-3">
            <div class="card-header bg-success text-white">
              <h3 class="card-title"><i class="fas fa-check-circle mr-1"></i> Generated Employee ID</h3>
            </div>
            <div class="card-body text-center">
              <div style="font-size:42px;font-weight:800;font-family:monospace;
                          color:#155724;background:#d4edda;border-radius:12px;padding:18px;">
                <?= htmlspecialchars($gen_result['id']) ?>
              </div>
              <div style="font-size:12px;color:#6c757d;margin-top:8px;">
                <strong><?= htmlspecialchars($gen_result['dept_name']) ?></strong>
                &middot; Born <?= htmlspecialchars($gen_result['dob']) ?>
                &middot; Hire sequence #<?= (int)$gen_result['seq'] ?>
              </div>
              <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;">
                <button onclick="navigator.clipboard.writeText('<?= addslashes($gen_result['id']) ?>')"
                        class="btn btn-outline-success btn-sm">
                  <i class="fas fa-copy mr-1"></i> Copy ID
                </button>
                <a href="add_employee.php" class="btn btn-success btn-sm">
                  <i class="fas fa-user-plus mr-1"></i> Use in Add Employee
                </a>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <div class="card">
            <div class="card-header"><h3 class="card-title">Department Code Reference</h3></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead class="thead-light">
                  <tr><th>Code</th><th>Department / Office</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($id_depts as $d):
                    $rc  = preg_replace('/[^0-9]/', '', $d['code']);
                    $rc2 = $rc ? str_pad(substr($rc,0,3),3,'0',STR_PAD_LEFT) : str_pad($d['id'],3,'0',STR_PAD_LEFT);
                  ?>
                  <tr>
                    <td><code style="font-size:15px;font-weight:700;"><?= $rc2 ?></code></td>
                    <td><?= htmlspecialchars($d['name']) ?>
                      <?php if (!$rc): ?>
                        <span class="badge badge-warning ml-1" style="font-size:9px;">No numeric code set</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div class="p-2" style="background:#fffbea;font-size:11px;color:#856404;border-top:1px solid #dee2e6;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Departments with no numeric code use their database ID. Set codes in
                <a href="departments.php">Departments</a>.
              </div>
            </div>
          </div>
        </div>
      </div>
      <script>
      function gdPreview() {
          var ds  = document.getElementById('gdDept');
          var db  = document.getElementById('gdDob');
          var opt = ds ? ds.options[ds.selectedIndex] : null;
          var code = (opt && opt.getAttribute('data-code')) ? opt.getAttribute('data-code') : '???';
          document.getElementById('gd_dept').textContent = code;
          var dob = db ? db.value : '';
          if (dob) {
              var d  = new Date(dob + 'T00:00:00');
              var mm = String(d.getMonth()+1).padStart(2,'0');
              var dd = String(d.getDate()).padStart(2,'0');
              document.getElementById('gd_mmdd').textContent = mm+dd;
          } else {
              document.getElementById('gd_mmdd').textContent = '????';
          }
      }
      </script>
      <?php endif; ?>

      </div><!-- /.card-body -->
    </div><!-- /.card -->
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
function updatePreview(color) {
    var badge = document.getElementById('colorPreview');
    if (badge) badge.className = 'badge badge-' + color;
}
$(function(){
    var sel = document.getElementById('colorSel');
    if (sel) updatePreview(sel.value);
});
</script>
</body></html>