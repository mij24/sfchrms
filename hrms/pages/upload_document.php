<?php
// ============================================
// FILE: pages/upload_document.php
// 201 file uploader
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: employees.php"); exit; }

$emp     = $conn->query("SELECT * FROM employees WHERE id = $id")->fetch_assoc();
if (!$emp) { header("Location: employees.php"); exit; }

$error   = '';
$success = '';

$doc_types = array(
    'Resume / CV', 'Employment contract', 'NBI clearance',
    'Birth certificate', 'Diploma / TOR', '2x2 Photo / ID',
    'SSS ID', 'PhilHealth ID', 'Pag-IBIG ID', 'TIN ID',
    'Medical certificate', 'COE (Certificate of Employment)',
    'Separation documents', 'Other'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $doc_type = $conn->real_escape_string($_POST['document_type']);
    $upload_dir = '../uploads/';

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $orig_name  = basename($_FILES['document']['name']);
        $ext        = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed    = array('pdf','jpg','jpeg','png','doc','docx');

        if (!in_array($ext, $allowed)) {
            $error = "File type not allowed. Allowed: PDF, JPG, PNG, DOC, DOCX.";
        } elseif ($_FILES['document']['size'] > 5 * 1024 * 1024) {
            $error = "File too large. Maximum size is 5MB.";
        } else {
            $safe_name  = $id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
            $dest       = $upload_dir . $safe_name;

            if (move_uploaded_file($_FILES['document']['tmp_name'], $dest)) {
                $conn->query("INSERT INTO employee_documents
                    (employee_id, document_type, file_name, file_path)
                    VALUES ($id, '$doc_type', '$orig_name', '$safe_name')");
                $success = "Document uploaded successfully.";
            } else {
                $error = "Upload failed. Check folder permissions on /uploads/.";
            }
        }
    } else {
        $error = "No file selected or upload error.";
    }
}

// Existing documents
$docs = $conn->query("SELECT * FROM employee_documents WHERE employee_id = $id ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload Document | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <h1 class="m-0">201 File — Upload Document</h1>
    </div>
  </div>
  <div class="content">
    <div class="container-fluid">

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <i class="fas fa-check-circle mr-1"></i> <?= $success ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= $error ?>
        </div>
      <?php endif; ?>

      <div class="row">
        <!-- Upload form -->
        <div class="col-md-4">
          <div class="card card-primary card-outline">
            <div class="card-header">
              <h3 class="card-title">Upload for: <?= htmlspecialchars($emp['full_name']) ?></h3>
            </div>
            <div class="card-body">
              <form method="POST" enctype="multipart/form-data" action="">
				  <?php csrf_field(); ?>
                <div class="form-group">
                  <label>Document type <span class="text-danger">*</span></label>
                  <select name="document_type" class="form-control" required>
                    <?php foreach ($doc_types as $dt): ?>
                      <option><?= $dt ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>File <span class="text-danger">*</span></label>
                  <div class="custom-file">
                    <input type="file" class="custom-file-input" name="document"
                           id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    <label class="custom-file-label" for="fileInput">Choose file...</label>
                  </div>
                  <small class="text-muted">Allowed: PDF, JPG, PNG, DOC, DOCX. Max 5MB.</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                  <i class="fas fa-upload mr-1"></i> Upload document
                </button>
                <a href="view_employee.php?id=<?= $id ?>" class="btn btn-secondary btn-block mt-1">
                  <i class="fas fa-arrow-left mr-1"></i> Back to profile
                </a>
              </form>
            </div>
          </div>
        </div>

        <!-- Existing documents -->
        <div class="col-md-8">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Uploaded documents</h3></div>
            <div class="card-body p-0">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr><th>Type</th><th>File name</th><th>Uploaded</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php
                  $doc_count = 0;
                  while ($doc = $docs->fetch_assoc()):
                    $doc_count++;
                  ?>
                  <tr>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                    <td>
                      <i class="fas fa-file mr-1 text-muted"></i>
                      <?= htmlspecialchars($doc['file_name']) ?>
                    </td>
                    <td><?= date('M d, Y g:i A', strtotime($doc['uploaded_at'])) ?></td>
                    <td>
                      <a href="../uploads/<?= htmlspecialchars($doc['file_path']) ?>"
                         target="_blank" class="btn btn-info btn-xs">
                        <i class="fas fa-eye"></i> View
                      </a>
                      <a href="delete_document.php?id=<?= $doc['id'] ?>&emp=<?= $id ?>"
                         class="btn btn-danger btn-xs"
                         onclick="return confirm('Remove this document?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                  <?php if ($doc_count === 0): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No documents uploaded yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    $('#fileInput').on('change', function(){
        var name = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').text(name ? name : 'Choose file...');
    });
});
</script>
</body>
</html>