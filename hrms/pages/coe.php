<?php
// ============================================
// FILE: pages/coe.php
// Certificate of Employment generator
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$emp_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$emp      = null;
$purpose  = isset($_GET['purpose']) ? clean_string($_GET['purpose'], 200) : '';
$show_sal = isset($_GET['show_salary']) ? (int)$_GET['show_salary'] : 0;

if ($emp_id) {
    $emp = db_fetch($pdo,
        "SELECT e.*, d.name AS dept, p.title AS position
         FROM employees e
         LEFT JOIN departments d ON e.department_id=d.id
         LEFT JOIN positions   p ON e.position_id=p.id
         WHERE e.id=?",
        array($emp_id)
    );
}

$company_name    = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='company_name'") ?: 'Your Company';
$company_address = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='company_address'") ?: '';
$employees       = db_fetchall($pdo,"SELECT id,full_name,employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");

// Years of service
$yos = '';
if ($emp && $emp['date_hired']) {
    $hired = new DateTime($emp['date_hired']);
    $now   = new DateTime();
    $diff  = $hired->diff($now);
    $yos   = $diff->y > 0 ? $diff->y . ' year' . ($diff->y>1?'s':'') . ($diff->m>0?', '.$diff->m.' month'.($diff->m>1?'s':''):'') : $diff->m . ' month' . ($diff->m>1?'s':'');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>COE Generator | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    @media print {
      .no-print { display:none!important; }
      .content-wrapper { margin-left:0!important; }
      body { background:#fff!important; font-size:14px; }
    }
    .coe-doc { max-width:720px;margin:0 auto;background:#fff;
               padding:48px 56px;border:1px solid #dee2e6; }
    .coe-doc p { margin-bottom:10px;line-height:1.8; }
    .coe-doc .company-header { text-align:center;margin-bottom:32px;border-bottom:2px solid #343a40;padding-bottom:16px; }
    .coe-doc .company-name { font-size:20px;font-weight:700;letter-spacing:.5px; }
    .coe-doc .doc-title { font-size:16px;font-weight:700;text-align:center;
                          text-transform:uppercase;letter-spacing:2px;margin:24px 0 20px; }
    .coe-doc .sig-block { margin-top:48px; }
    .coe-doc .sig-line { border-top:1px solid #343a40;width:200px;margin-top:40px;padding-top:4px;font-size:13px; }
    .coe-highlight { font-weight:700; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Certificate of Employment</h1></div>
        <div class="col-sm-6">
          <?php if ($emp): ?>
          <button onclick="window.print()" class="btn btn-sm btn-primary float-right no-print">
            <i class="fas fa-print mr-1"></i> Print / Save PDF
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="content"><div class="container-fluid">

    <!-- Controls -->
    <div class="card no-print mb-3">
      <div class="card-header"><h3 class="card-title">Generate COE</h3></div>
      <div class="card-body">
        <form method="GET" action="" class="form-inline flex-wrap" style="gap:12px;">
          <div class="form-group">
            <label class="mr-2">Employee:</label>
            <select name="id" class="form-control form-control-sm">
              <option value="">Select employee...</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $emp_id==$e['id']?'selected':'' ?>>
                  <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="mr-2">Purpose:</label>
            <input type="text" name="purpose" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($purpose) ?>"
                   placeholder="e.g. Loan application, Visa application">
          </div>
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" name="show_salary" value="1" class="custom-control-input" id="showSal" <?= $show_sal?'checked':'' ?>>
              <label class="custom-control-label" for="showSal">Include salary</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-file-alt mr-1"></i> Generate COE
          </button>
        </form>
      </div>
    </div>

    <?php if ($emp): ?>
    <!-- COE Document -->
    <div style="background:#f4f6f9;padding:20px 0;">
    <div class="coe-doc">

      <!-- Company header -->
      <div class="company-header">
        <?php
        $logo = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='company_logo'");
        if ($logo && file_exists('../uploads/logos/'.$logo)):
        ?>
          <img src="../uploads/logos/<?= htmlspecialchars($logo) ?>" height="60" alt="logo" style="margin-bottom:8px;display:block;margin:0 auto 8px;"><br>
        <?php endif; ?>
        <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
        <?php if ($company_address): ?>
          <div style="font-size:13px;color:#6c757d;"><?= htmlspecialchars($company_address) ?></div>
        <?php endif; ?>
      </div>

      <div class="doc-title">Certificate of Employment</div>

      <p>To whom it may concern,</p>

      <p>
        This is to certify that <span class="coe-highlight"><?= htmlspecialchars($emp['full_name']) ?></span>,
        with Employee ID <span class="coe-highlight"><?= htmlspecialchars($emp['employee_id']) ?></span>,
        is a <span class="coe-highlight"><?= htmlspecialchars($emp['employment_status']) ?></span>
        employee of <span class="coe-highlight"><?= htmlspecialchars($company_name) ?></span>
        holding the position of <span class="coe-highlight"><?= htmlspecialchars($emp['position']?:'—') ?></span>
        under the <span class="coe-highlight"><?= htmlspecialchars($emp['dept']?:'—') ?></span> department.
      </p>

      <p>
        <?php $pronoun = ($emp['gender']==='Female') ? 'She' : (($emp['gender']==='Male') ? 'He' : 'They'); ?>
        <?= $pronoun ?> has been employed with us since
        <span class="coe-highlight"><?= $emp['date_hired'] ? date('F d, Y', strtotime($emp['date_hired'])) : '—' ?></span>,
        with a total length of service of <span class="coe-highlight"><?= $yos ?: '—' ?></span>.
        <?= $pronoun ?> holds a <span class="coe-highlight"><?= htmlspecialchars($emp['employment_type']) ?></span> employment status.
      </p>

      <?php if ($show_sal && $emp['basic_salary'] > 0): ?>
      <p>
        <?= $pronoun ?> receives a monthly basic salary of
        <span class="coe-highlight">&#8369; <?= number_format((float)$emp['basic_salary'], 2) ?></span>.
      </p>
      <?php endif; ?>

      <?php if ($purpose): ?>
      <p>
        This certification is issued upon the employee's request for
        <span class="coe-highlight"><?= htmlspecialchars($purpose) ?></span>.
      </p>
      <?php endif; ?>

      <p>
        Issued this <span class="coe-highlight"><?= date('jS \d\a\y \o\f F Y') ?></span>
        at <?= htmlspecialchars($company_name) ?>.
      </p>

      <!-- Signature -->
      <div class="sig-block">
        <div class="sig-line">
          HR Head / Authorized Representative
        </div>
        <p style="margin-top:4px;font-size:12px;color:#6c757d;"><?= htmlspecialchars($company_name) ?></p>
      </div>

      <div style="margin-top:32px;border-top:0.5px solid #dee2e6;padding-top:10px;font-size:11px;color:#aaa;text-align:center;">
        Generated by HRMS &mdash; <?= date('F d, Y g:i A') ?> &mdash; Document is valid without signature if digitally generated.
      </div>
    </div>
    </div>
    <?php else: ?>
      <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="fas fa-file-contract fa-3x mb-3 d-block" style="opacity:.2;"></i>
        Select an employee above to generate their Certificate of Employment.
      </div></div>
    <?php endif; ?>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>
