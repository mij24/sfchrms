<?php
// ============================================================
// FILE: pages/teaching_load_modal.php
// AJAX endpoint — returns modal body HTML for a teaching load
// Called by teaching_load.php View button
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }

$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila';
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone' && $_r['setting_value']) $_hrms_tz = $_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo '<div class="alert alert-danger m-3">Invalid request.</div>'; exit; }

$load = db_fetch($pdo,
    "SELECT tl.*, e.full_name, e.employee_id AS emp_code,
            d.name AS dept, p.title AS position, ec.name AS cat_name
     FROM teaching_loads tl
     JOIN employees e ON tl.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
     WHERE tl.id=?",
    array($id)
);
if (!$load) { echo '<div class="alert alert-danger m-3">Teaching load not found.</div>'; exit; }

$trail = db_fetchall($pdo,
    "SELECT * FROM teaching_load_approvals WHERE load_id=? ORDER BY step, acted_at",
    array($id)
);

// Load subjects
$subjects = db_fetchall($pdo,
    "SELECT * FROM teaching_load_subjects WHERE load_id=? ORDER BY sort_order,id",
    array($id)
);
// Fallback: legacy single-subject
if (empty($subjects) && !empty($load['subject_title'])) {
    $subjects = array(array(
        'course_no'=>$load['subject_code']??'',
        'description'=>$load['subject_title'],
        'units'=>$load['units']??0,
        'hours'=>$load['units']??0,
        'no_of_students'=>0,
        'day_of_week'=>$load['day_of_week'],
        'time_start'=>$load['time_start'],
        'time_end'=>$load['time_end'],
        'room'=>$load['room']??'',
    ));
}

// Current user info for action check
$my_uid    = (int)$_SESSION['user_id'];
$my_role   = $_SESSION['role'] ?? 'employee';
$my_user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;

// Determine if current user can act
function can_act_modal($load, $my_emp_id, $my_role) {
    $s = $load['status'];
    $dh = array('manager','academic_dept_head','non_academic_dept_head');
    if ($s==='For Faculty Review'         && $load['employee_id']==$my_emp_id) return 'faculty_confirm';
    if ($s==='For Dept Head Review'       && in_array($my_role,$dh))           return 'dept_head_approve';
    if ($s==='For Registrar Verification' && in_array($my_role,array('registrar_head','non_academic_dept_head'))) return 'registrar_verify';
    if ($s==='For VP Academic Review'     && $my_role==='vp_academic')         return 'vp_recommend';
    if ($s==='For President Approval'     && has_role(ROLE_PRESIDENT))         return 'president_approve';
    if ($s==='Approved'                   && in_array($my_role,array('hr','hr_head'))) return 'hr_receive';
    return null;
}
$action_key = can_act_modal($load, $my_emp_id, $my_role);

// Step bar data
$step_defs = array(
    1=>'Created', 2=>'Faculty Confirmed', 3=>'Dept Head Noted',
    4=>'Registrar Verified', 5=>'VP Recommended', 6=>'President Approved', 7=>'HR Received',
);
$status_to_step = array(
    'Draft'=>1,'For Faculty Review'=>2,'Faculty Confirmed'=>2,
    'For Dept Head Review'=>3,'Dept Head Approved'=>3,
    'For Registrar Verification'=>4,'Registrar Verified'=>4,
    'For VP Academic Review'=>5,'VP Academic Recommended'=>5,
    'For President Approval'=>6,'President Approved'=>6,
    'Approved'=>7,'Rejected'=>0,'Returned'=>0,
);
$cur_step   = isset($status_to_step[$load['status']]) ? $status_to_step[$load['status']] : 0;
$is_rejected = in_array($load['status'],array('Rejected','Returned'));

$status_badge = array(
    'Draft'=>'secondary','For Faculty Review'=>'warning','Faculty Confirmed'=>'info',
    'For Dept Head Review'=>'warning','Dept Head Approved'=>'info',
    'For VP Review'=>'warning','VP Recommended'=>'primary',
    'For President Approval'=>'warning','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'warning',
);
$sb = isset($status_badge[$load['status']]) ? $status_badge[$load['status']] : 'secondary';

$action_labels = array(
    'faculty_confirm'   => array('Digital Sign & Confirm', 'btn-success'),
    'dept_head_approve' => array('Noted & Digitally Signed','btn-info'),
    'registrar_verify'  => array('Verified & Signed',       'btn-success'),
    'vp_recommend'      => array('Recommending Approval',    'btn-primary'),
    'president_approve' => array('Approve Teaching Load',  'btn-success'),
    'hr_receive'        => array('Acknowledge HR Copy',    'btn-secondary'),
);
?>
<!-- Step bar -->
<div style="padding:16px 20px 0;">
  <div style="display:flex;gap:0;">
    <?php foreach ($step_defs as $sn => $sl):
      if ($is_rejected && $sn >= $cur_step) $cls = 'rejected';
      elseif ($sn < $cur_step) $cls = 'done';
      elseif ($sn == $cur_step) $cls = 'active';
      else $cls = '';
      $colors = array('done'=>'#28a745','active'=>'#007bff','rejected'=>'#dc3545',''=>'#dee2e6');
      $col = $colors[$cls];
    ?>
    <div style="flex:1;text-align:center;padding:6px 2px;border-top:3px solid <?= $col ?>;font-size:10px;color:<?= $col==='#dee2e6'?'#adb5bd':$col ?>;">
      <div style="width:22px;height:22px;border-radius:50%;background:<?= $col ?>;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;margin-bottom:3px;"><?= $sn ?></div>
      <div style="font-size:10px;"><?= $sl ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Load details + trail in two columns -->
<div class="row px-3 pt-3">
    <div class="col-md-5">
    <div style="font-size:13px;font-weight:700;margin-bottom:4px;">
      Instructor's Teaching Load
      <small class="text-muted ml-1">(<?= count($subjects) ?> subject<?= count($subjects)!==1?'s':'' ?>)</small>
    </div>
    <span class="badge badge-<?= $sb ?> mb-2" style="font-size:11px;"><?= $load['status'] ?></span>
    <table class="table table-sm table-borderless mb-2" style="font-size:12px;">
      <tr><td class="text-muted" width="110">Faculty</td>
          <td><strong><?= htmlspecialchars($load['full_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($load['emp_code']) ?> — <?= htmlspecialchars($load['dept']??'—') ?></small></td></tr>
      <tr><td class="text-muted">Program</td>
          <td><?= htmlspecialchars($load['student_program']??'—') ?><?= $load['section']?' — '.htmlspecialchars($load['section']):'' ?></td></tr>
      <tr><td class="text-muted">Level</td>     <td><?= htmlspecialchars($load['level']) ?></td></tr>
      <tr><td class="text-muted">Total Units</td><td><strong><?= $load['total_units'] ?: $load['units'] ?></strong></td></tr>
      <tr><td class="text-muted">SY / Sem</td>
          <td><?= htmlspecialchars($load['school_year']) ?> — <?= htmlspecialchars($load['semester']) ?></td></tr>
    </table>
    <!-- Subjects table -->
    <div style="overflow-x:auto;">
    <table class="table table-sm table-bordered" style="font-size:11px;">
      <thead class="thead-dark">
        <tr><th>#</th><th>Course No.</th><th>Description</th><th>Units</th><th>Days</th><th>Time</th><th>Room</th></tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $si => $subj): ?>
        <tr>
          <td><?= $si+1 ?></td>
          <td><?= htmlspecialchars($subj['course_no']??'') ?></td>
          <td><strong><?= htmlspecialchars($subj['description']) ?></strong></td>
          <td><?= $subj['units'] ?></td>
          <td><?= htmlspecialchars($subj['day_of_week']) ?></td>
          <td style="white-space:nowrap;"><?= date('h:i A',strtotime($subj['time_start'])) ?>–<?= date('h:i A',strtotime($subj['time_end'])) ?></td>
          <td><?= htmlspecialchars($subj['room']??'—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php if ($load['has_offset']): ?>
    <div class="alert alert-warning p-2" style="font-size:11px;">
      <i class="fas fa-exchange-alt mr-1"></i>
      <strong>Offset:</strong>
      <?= date('h:i A',strtotime($load['offset_time_start'])) ?> – <?= date('h:i A',strtotime($load['offset_time_end'])) ?>
      <?php if ($load['offset_notes']): ?><br><em><?= htmlspecialchars($load['offset_notes']) ?></em><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: approval trail -->
  <div class="col-md-7" style="border-left:1px solid #f0f0f0;">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;margin-bottom:10px;">
      Approval Trail
    </div>
    <?php if (empty($trail)): ?>
      <div class="text-muted" style="font-size:13px;">No actions recorded yet.</div>
    <?php else: ?>
      <?php foreach ($trail as $t):
        $action_lc = strtolower($t['action']??'');
        $t_color = strpos($action_lc,'confirm')!==false||strpos($action_lc,'approv')!==false||strpos($action_lc,'noted')!==false||strpos($action_lc,'recommend')!==false
            ? '#28a745' : (strpos($action_lc,'declin')!==false||strpos($action_lc,'reject')!==false ? '#dc3545' : '#6c757d');
      ?>
      <div style="border-left:3px solid <?= $t_color ?>;padding:8px 12px;margin-bottom:8px;border-radius:0 4px 4px 0;background:rgba(0,0,0,.01);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <strong style="font-size:13px;"><?= htmlspecialchars($t['step_label']) ?></strong>
            <span class="badge ml-1"
                  style="font-size:9px;background:<?= $t_color ?>;color:#fff;">
              <?= htmlspecialchars($t['action']) ?>
            </span>
          </div>
          <small class="text-muted" style="white-space:nowrap;font-size:11px;">
            <?= $t['acted_at'] ? date('M d, Y h:i A',strtotime($t['acted_at'])) : '' ?>
          </small>
        </div>
        <div style="font-size:12px;color:#6c757d;margin-top:2px;">
          By: <?= htmlspecialchars($t['actor_name']??'—') ?>
          <?php if ($t['remarks']): ?>
            <div style="font-style:italic;margin-top:2px;">"<?= htmlspecialchars($t['remarks']) ?>"</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($action_key): ?>
    <!-- Action panel -->
    <div style="margin-top:16px;padding:14px;background:#f8f9ff;border:1px solid #d0d9ff;border-radius:8px;">
      <div style="font-size:12px;font-weight:600;color:#007bff;margin-bottom:10px;">
        <i class="fas fa-pen-nib mr-1"></i>Your Action Required
      </div>
      <?php $btn = $action_labels[$action_key]; ?>
      <!-- Sign form -->
      <form method="POST" action="teaching_load.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="post_action" value="<?= $action_key ?>">
        <input type="hidden" name="load_id" value="<?= $load['id'] ?>">
        <?php if ($action_key !== 'hr_receive'): ?>
        <textarea name="remarks" class="form-control form-control-sm mb-2" rows="2"
                  placeholder="Add remarks (optional)..."></textarea>
        <?php endif; ?>
        <?php if ($action_key !== 'hr_receive'): ?>
        <div class="custom-control custom-checkbox mb-2">
          <input type="checkbox" class="custom-control-input"
                 id="chkSign<?= $id ?>"
                 onchange="document.getElementById('btnSign<?= $id ?>').disabled=!this.checked;">
          <label class="custom-control-label" for="chkSign<?= $id ?>" style="font-size:12px;">
            I confirm and affix my <strong>digital signature</strong>.
          </label>
        </div>
        <?php endif; ?>
        <button type="submit" id="btnSign<?= $id ?>"
                class="btn btn-sm <?= $btn[1] ?>"
                <?= $action_key !== 'hr_receive' ? 'disabled' : '' ?>>
          <i class="fas fa-signature mr-1"></i><?= $btn[0] ?>
        </button>
      </form>

      <?php if ($action_key !== 'hr_receive'): ?>
      <!-- Return/decline form -->
      <form method="POST" action="teaching_load.php" class="mt-2">
        <?php csrf_field(); ?>
        <input type="hidden" name="post_action" value="return">
        <input type="hidden" name="load_id" value="<?= $load['id'] ?>">
        <div class="input-group input-group-sm">
          <textarea name="rejection_reason" class="form-control form-control-sm" rows="1"
                    placeholder="Reason for returning..." required style="resize:none;"></textarea>
          <div class="input-group-append">
            <button type="submit" class="btn btn-outline-warning btn-sm"
                    onclick="return confirm('Return this load for revision?')">
              <i class="fas fa-undo mr-1"></i>Return
            </button>
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<div style="padding:12px 20px;border-top:1px solid #f0f0f0;text-align:right;">
  <a href="teaching_load.php?view=<?= $load['id'] ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-external-link-alt mr-1"></i>Open Full Page
  </a>
</div>