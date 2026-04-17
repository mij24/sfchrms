<?php
// ============================================
// FILE: pages/hr_setup.php
// Master data management under Settings:
// - Employee Classifications
// - Ranks
// - Employment Types
// - Employment Statuses
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

// ── Generic delete handler ────────────────────────────────────────
if ($get_action === 'delete' && $get_id && $get_type) {
    $table_map = array(
        'classification' => 'employee_classifications',
        'rank'           => 'employee_ranks',
        'type'           => 'employment_types',
        'status'         => 'employment_statuses',
    );
    if (isset($table_map[$get_type])) {
        db_run($pdo,"UPDATE {$table_map[$get_type]} SET is_active=0 WHERE id=?",array($get_id));
        prg_redirect("hr_setup.php?tab={$get_type}s","Record deactivated.");
    }
}
if ($get_action === 'restore' && $get_id && $get_type) {
    $table_map = array(
        'classification' => 'employee_classifications',
        'rank'           => 'employee_ranks',
        'type'           => 'employment_types',
        'status'         => 'employment_statuses',
    );
    if (isset($table_map[$get_type])) {
        db_run($pdo,"UPDATE {$table_map[$get_type]} SET is_active=1 WHERE id=?",array($get_id));
        prg_redirect("hr_setup.php?tab={$get_type}s","Record restored.");
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
        $desc      = clean_string($_POST['description'],500);

        if (empty($name)) prg_redirect_error('hr_setup.php?tab=classifications','Name is required.');

        $dup = $post_id
            ? db_value($pdo,"SELECT COUNT(*) FROM employee_classifications WHERE name=? AND id!=?",array($name,$post_id))
            : db_value($pdo,"SELECT COUNT(*) FROM employee_classifications WHERE name=?",array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=classifications',"'$name' already exists.");

        if ($post_id) {
            db_run($pdo,"UPDATE employee_classifications SET name=?,parent_id=?,category=?,description=? WHERE id=?",
                array($name,$parent_id,$category,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=classifications','Classification updated.');
        } else {
            db_run($pdo,"INSERT INTO employee_classifications (name,parent_id,category,description) VALUES (?,?,?,?)",
                array($name,$parent_id,$category,$desc));
            prg_redirect('hr_setup.php?tab=classifications','Classification added.');
        }
    }

    // ── RANK ──
    if ($form_type === 'rank') {
        $name     = trim($_POST['name']);
        $code     = strtoupper(trim($_POST['code']));
        $cat      = clean_string($_POST['category']);
        $grade    = clean_string($_POST['salary_grade']);
        $sal_min  = (float)$_POST['salary_min'];
        $sal_max  = (float)$_POST['salary_max'];
        $desc     = clean_string($_POST['description'],500);

        if (empty($name)) prg_redirect_error('hr_setup.php?tab=ranks','Rank name is required.');

        $dup = $post_id
            ? db_value($pdo,"SELECT COUNT(*) FROM employee_ranks WHERE name=? AND id!=?",array($name,$post_id))
            : db_value($pdo,"SELECT COUNT(*) FROM employee_ranks WHERE name=?",array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=ranks',"Rank '$name' already exists.");

        if ($post_id) {
            db_run($pdo,"UPDATE employee_ranks SET name=?,code=?,category=?,salary_grade=?,salary_min=?,salary_max=?,description=? WHERE id=?",
                array($name,$code,$cat,$grade,$sal_min,$sal_max,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=ranks','Rank updated.');
        } else {
            db_run($pdo,"INSERT INTO employee_ranks (name,code,category,salary_grade,salary_min,salary_max,description) VALUES (?,?,?,?,?,?,?)",
                array($name,$code,$cat,$grade,$sal_min,$sal_max,$desc));
            prg_redirect('hr_setup.php?tab=ranks','Rank added.');
        }
    }

    // ── EMPLOYMENT TYPE ──
    if ($form_type === 'type') {
        $name     = trim($_POST['name']);
        $code     = strtoupper(trim($_POST['code']));
        $prob_mo  = (int)$_POST['probation_months'] > 0 ? (int)$_POST['probation_months'] : null;
        $desc     = clean_string($_POST['description'],500);

        if (empty($name)) prg_redirect_error('hr_setup.php?tab=types','Name is required.');

        $dup = $post_id
            ? db_value($pdo,"SELECT COUNT(*) FROM employment_types WHERE name=? AND id!=?",array($name,$post_id))
            : db_value($pdo,"SELECT COUNT(*) FROM employment_types WHERE name=?",array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=types',"Type '$name' already exists.");

        if ($post_id) {
            db_run($pdo,"UPDATE employment_types SET name=?,code=?,probation_months=?,description=? WHERE id=?",
                array($name,$code,$prob_mo,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=types','Employment type updated.');
        } else {
            db_run($pdo,"INSERT INTO employment_types (name,code,probation_months,description) VALUES (?,?,?,?)",
                array($name,$code,$prob_mo,$desc));
            prg_redirect('hr_setup.php?tab=types','Employment type added.');
        }
    }

    // ── EMPLOYMENT STATUS ──
    if ($form_type === 'status') {
        $name        = trim($_POST['name']);
        $code        = strtoupper(trim($_POST['code']));
        $color       = clean_string($_POST['color']);
        $is_active_s = isset($_POST['is_active_status']) ? 1 : 0;
        $desc        = clean_string($_POST['description'],500);

        if (empty($name)) prg_redirect_error('hr_setup.php?tab=statuses','Name is required.');

        $dup = $post_id
            ? db_value($pdo,"SELECT COUNT(*) FROM employment_statuses WHERE name=? AND id!=?",array($name,$post_id))
            : db_value($pdo,"SELECT COUNT(*) FROM employment_statuses WHERE name=?",array($name));
        if ($dup) prg_redirect_error('hr_setup.php?tab=statuses',"Status '$name' already exists.");

        if ($post_id) {
            db_run($pdo,"UPDATE employment_statuses SET name=?,code=?,color=?,is_active_status=?,description=? WHERE id=?",
                array($name,$code,$color,$is_active_s,$desc,$post_id));
            prg_redirect('hr_setup.php?tab=statuses','Employment status updated.');
        } else {
            db_run($pdo,"INSERT INTO employment_statuses (name,code,color,is_active_status,description) VALUES (?,?,?,?,?)",
                array($name,$code,$color,$is_active_s,$desc));
            prg_redirect('hr_setup.php?tab=statuses','Employment status added.');
        }
    }
}

// ── Load data for current tab ─────────────────────────────────────
$classifications = db_fetchall($pdo,
    "SELECT ec.*, p.name AS parent_name
     FROM employee_classifications ec
     LEFT JOIN employee_classifications p ON ec.parent_id=p.id
     ORDER BY ec.parent_id IS NOT NULL, ec.sort_order, ec.name"
);
$ranks      = db_fetchall($pdo,"SELECT * FROM employee_ranks ORDER BY category,sort_order,name");
$emp_types  = db_fetchall($pdo,"SELECT * FROM employment_types ORDER BY sort_order,name");
$emp_stats  = db_fetchall($pdo,"SELECT * FROM employment_statuses ORDER BY sort_order,name");

// Edit record
$edit_record = null;
if ($get_action === 'edit' && $get_id && $get_type) {
    $table_map = array(
        'classification' => 'employee_classifications',
        'rank'           => 'employee_ranks',
        'type'           => 'employment_types',
        'status'         => 'employment_statuses',
    );
    if (isset($table_map[$get_type])) {
        $edit_record = db_fetch($pdo,"SELECT * FROM {$table_map[$get_type]} WHERE id=?",array($get_id));
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

    <!-- Tab navigation -->
    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs" id="hrSetupTabs">
          <?php
          $tabs = array(
            'classifications' => array('fas fa-sitemap',       'Employee Classifications'),
            'ranks'           => array('fas fa-medal',         'Ranks'),
            'types'           => array('fas fa-id-badge',      'Employment Types'),
            'statuses'        => array('fas fa-toggle-on',     'Employment Statuses'),
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
        // ── HELPER: render add/edit form + table for each tab ──────
        function render_section($title, $form_type, $tab, $edit, $form_html, $table_html, $type_slug) {
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
          <div class="form-group"><label>Classification name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="form-group"><label>Parent (leave blank for top-level)</label>
            <select name="parent_id" class="form-control">
              <option value="0">-- Top level --</option>
              <?php foreach ($parents as $p): ?>
                <option value="<?= $p['id'] ?>"
                  <?= ($edit && $edit['parent_id']==$p['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($p['name']) ?>
                </option>
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
          <div class="card">
            <div class="card-header"><h3 class="card-title">All employee classifications</h3></div>
            <div class="card-body" style="padding:12px 16px 0;">
              <table class="table table-sm table-bordered dt-export" id="clTable">
                <thead class="thead-light">
                  <tr><th>Name</th><th>Parent</th><th>Category</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($classifications as $cl): ?>
                  <tr class="<?= !$cl['is_active']?'text-muted':'' ?>">
                    <td>
                      <?= $cl['parent_id'] ? '&nbsp;&nbsp;&nbsp;<i class="fas fa-level-up-alt fa-rotate-90 mr-1 text-muted" style="font-size:10px;"></i>' : '' ?>
                      <strong><?= htmlspecialchars($cl['name']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($cl['parent_name']?:'—') ?></td>
                    <td><span class="badge badge-<?= $cl['category']==='Teaching'?'primary':($cl['category']==='Non-Teaching'?'success':'secondary') ?>">
                      <?= $cl['category'] ?></span></td>
                    <td><span class="badge badge-<?= $cl['is_active']?'success':'secondary' ?>"><?= $cl['is_active']?'Active':'Inactive' ?></span></td>
                    <td style="white-space:nowrap;">
                      <a href="hr_setup.php?tab=classifications&action=edit&type=classification&id=<?= $cl['id'] ?>"
                         class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                      <?php if ($cl['is_active']): ?>
                      <a href="hr_setup.php?tab=classifications&action=delete&type=classification&id=<?= $cl['id'] ?>"
                         class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                      <?php else: ?>
                      <a href="hr_setup.php?tab=classifications&action=restore&type=classification&id=<?= $cl['id'] ?>"
                         class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php $table_html = ob_get_clean();
          render_section('classification','classification','classifications',$edit,$form_html,$table_html,'classification');
        endif; ?>

        <!-- ── RANKS TAB ── -->
        <?php if ($active_tab === 'ranks'):
          $edit = ($get_action==='edit' && $get_type==='rank') ? $edit_record : null;
          ob_start(); ?>
          <div class="form-group"><label>Rank name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="row">
            <div class="col-6"><div class="form-group"><label>Code</label>
              <input type="text" name="code" class="form-control" maxlength="10"
                     value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>">
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
            <input type="text" name="salary_grade" class="form-control"
                   value="<?= $edit ? htmlspecialchars($edit['salary_grade']) : '' ?>" placeholder="e.g. SG-11">
          </div>
          <div class="row">
            <div class="col-6"><div class="form-group"><label>Min salary (&#8369;)</label>
              <input type="number" name="salary_min" class="form-control" step="0.01"
                     value="<?= $edit ? $edit['salary_min'] : '' ?>">
            </div></div>
            <div class="col-6"><div class="form-group"><label>Max salary (&#8369;)</label>
              <input type="number" name="salary_max" class="form-control" step="0.01"
                     value="<?= $edit ? $edit['salary_max'] : '' ?>">
            </div></div>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();

          ob_start(); ?>
          <div class="card">
            <div class="card-header"><h3 class="card-title">All ranks</h3></div>
            <div class="card-body" style="padding:12px 16px 0;">
              <table class="table table-sm table-bordered dt-export" id="rankTable">
                <thead class="thead-light">
                  <tr><th>Rank</th><th>Code</th><th>Category</th><th>Grade</th><th>Salary range</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($ranks as $r): ?>
                  <tr class="<?= !$r['is_active']?'text-muted':'' ?>">
                    <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($r['code']?:'—') ?></code></td>
                    <td><span class="badge badge-<?= $r['category']==='Teaching'?'primary':($r['category']==='Non-Teaching'?'success':'secondary') ?>"><?= $r['category'] ?></span></td>
                    <td><?= htmlspecialchars($r['salary_grade']?:'—') ?></td>
                    <td style="font-size:12px;">
                      <?= ($r['salary_min']||$r['salary_max'])
                          ? '&#8369;'.number_format($r['salary_min']).'–'.number_format($r['salary_max'])
                          : '—' ?>
                    </td>
                    <td><span class="badge badge-<?= $r['is_active']?'success':'secondary' ?>"><?= $r['is_active']?'Active':'Inactive' ?></span></td>
                    <td style="white-space:nowrap;">
                      <a href="hr_setup.php?tab=ranks&action=edit&type=rank&id=<?= $r['id'] ?>"
                         class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                      <?php if ($r['is_active']): ?>
                      <a href="hr_setup.php?tab=ranks&action=delete&type=rank&id=<?= $r['id'] ?>"
                         class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                      <?php else: ?>
                      <a href="hr_setup.php?tab=ranks&action=restore&type=rank&id=<?= $r['id'] ?>"
                         class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php $table_html = ob_get_clean();
          render_section('rank','rank','ranks',$edit,$form_html,$table_html,'rank');
        endif; ?>

        <!-- ── EMPLOYMENT TYPES TAB ── -->
        <?php if ($active_tab === 'types'):
          $edit = ($get_action==='edit' && $get_type==='type') ? $edit_record : null;
          ob_start(); ?>
          <div class="alert alert-light" style="font-size:12px;">
            <i class="fas fa-info-circle text-info mr-1"></i>
            <strong>Employment Type</strong> = the nature of the appointment/contract.<br>
            Example: Regular, Probationary, Contractual, Part-time
          </div>
          <div class="form-group"><label>Type name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="form-group"><label>Code</label>
            <input type="text" name="code" class="form-control" maxlength="10"
                   value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>" placeholder="e.g. REG">
          </div>
          <div class="form-group"><label>Probation period (months, blank if not applicable)</label>
            <input type="number" name="probation_months" class="form-control" min="0"
                   value="<?= $edit ? $edit['probation_months'] : '' ?>" placeholder="e.g. 6">
            <small class="text-muted">Non-teaching: 6 months. Teaching tertiary: 36 months (6 semesters)</small>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();

          ob_start(); ?>
          <div class="card">
            <div class="card-header"><h3 class="card-title">All employment types</h3></div>
            <div class="card-body" style="padding:12px 16px 0;">
              <table class="table table-sm table-bordered dt-export" id="typeTable">
                <thead class="thead-light">
                  <tr><th>Type</th><th>Code</th><th>Probation months</th><th>Description</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($emp_types as $et): ?>
                  <tr class="<?= !$et['is_active']?'text-muted':'' ?>">
                    <td><strong><?= htmlspecialchars($et['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($et['code']?:'—') ?></code></td>
                    <td class="text-center"><?= $et['probation_months'] ? $et['probation_months'].' mo' : '—' ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars(substr($et['description']?:'',0,60)) ?></td>
                    <td><span class="badge badge-<?= $et['is_active']?'success':'secondary' ?>"><?= $et['is_active']?'Active':'Inactive' ?></span></td>
                    <td style="white-space:nowrap;">
                      <a href="hr_setup.php?tab=types&action=edit&type=type&id=<?= $et['id'] ?>"
                         class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                      <?php if ($et['is_active']): ?>
                      <a href="hr_setup.php?tab=types&action=delete&type=type&id=<?= $et['id'] ?>"
                         class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                      <?php else: ?>
                      <a href="hr_setup.php?tab=types&action=restore&type=type&id=<?= $et['id'] ?>"
                         class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php $table_html = ob_get_clean();
          render_section('employment type','type','types',$edit,$form_html,$table_html,'type');
        endif; ?>

        <!-- ── EMPLOYMENT STATUSES TAB ── -->
        <?php if ($active_tab === 'statuses'):
          $edit = ($get_action==='edit' && $get_type==='status') ? $edit_record : null;
          ob_start(); ?>
          <div class="alert alert-light" style="font-size:12px;">
            <i class="fas fa-info-circle text-info mr-1"></i>
            <strong>Employment Status</strong> = current work state of the employee.<br>
            Example: Active (reporting), On Leave, Resigned, Terminated
          </div>
          <div class="form-group"><label>Status name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </div>
          <div class="form-group"><label>Code</label>
            <input type="text" name="code" class="form-control" maxlength="10"
                   value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>" placeholder="e.g. ACT">
          </div>
          <div class="form-group"><label>Badge color</label>
            <select name="color" class="form-control" id="colorSel" onchange="updatePreview(this.value)">
              <?php foreach ($colors_list as $cl): ?>
                <option value="<?= $cl ?>" <?= ($edit && $edit['color']===$cl)?'selected':'' ?>><?= ucfirst($cl) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="mt-2">
              Preview: <span class="badge" id="colorPreview"><?= $edit ? $edit['name'] : 'Status name' ?></span>
            </div>
          </div>
          <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" name="is_active_status" class="custom-control-input" id="isActiveStatus"
                   <?= (!$edit || $edit['is_active_status']) ? 'checked' : '' ?>>
            <label class="custom-control-label" for="isActiveStatus">
              Employee is actively employed (still working)
            </label>
          </div>
          <div class="form-group"><label>Description</label>
            <textarea name="description" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
          </div>
          <?php $form_html = ob_get_clean();

          ob_start(); ?>
          <div class="card">
            <div class="card-header"><h3 class="card-title">All employment statuses</h3></div>
            <div class="card-body" style="padding:12px 16px 0;">
              <table class="table table-sm table-bordered dt-export" id="statusTable">
                <thead class="thead-light">
                  <tr><th>Status</th><th>Code</th><th>Badge</th><th>Active emp?</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($emp_stats as $es): ?>
                  <tr class="<?= !$es['is_active']?'text-muted':'' ?>">
                    <td><strong><?= htmlspecialchars($es['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($es['code']?:'—') ?></code></td>
                    <td><span class="badge badge-<?= htmlspecialchars($es['color']) ?>"><?= htmlspecialchars($es['name']) ?></span></td>
                    <td class="text-center">
                      <?= $es['is_active_status']
                          ? '<i class="fas fa-check text-success"></i>'
                          : '<i class="fas fa-times text-muted"></i>' ?>
                    </td>
                    <td><span class="badge badge-<?= $es['is_active']?'success':'secondary' ?>"><?= $es['is_active']?'Active':'Inactive' ?></span></td>
                    <td style="white-space:nowrap;">
                      <a href="hr_setup.php?tab=statuses&action=edit&type=status&id=<?= $es['id'] ?>"
                         class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                      <?php if ($es['is_active']): ?>
                      <a href="hr_setup.php?tab=statuses&action=delete&type=status&id=<?= $es['id'] ?>"
                         class="btn btn-danger btn-xs" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></a>
                      <?php else: ?>
                      <a href="hr_setup.php?tab=statuses&action=restore&type=status&id=<?= $es['id'] ?>"
                         class="btn btn-success btn-xs"><i class="fas fa-undo"></i></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php $table_html = ob_get_clean();
          render_section('employment status','status','statuses',$edit,$form_html,$table_html,'status');
        endif; ?>

      </div><!-- /.card-body -->
    </div><!-- /.card -->

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
function updatePreview(color) {
    var badge = document.getElementById('colorPreview');
    badge.className = 'badge badge-' + color;
}
// Init preview
$(function(){
    var sel = document.getElementById('colorSel');
    if (sel) updatePreview(sel.value);
});
</script>
</body></html>