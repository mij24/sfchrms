<?php
// ============================================
// FILE: pages/upload_photo.php
// Employee profile photo upload
// Linked from view_employee.php and edit_employee.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: employees.php"); exit; }

$emp   = db_fetch($pdo, "SELECT id,full_name,employee_id,photo FROM employees WHERE id=?", array($id));
if (!$emp) { header("Location: employees.php"); exit; }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = array('jpg','jpeg','png','gif','webp');

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG, GIF, WEBP allowed.";
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $error = "File too large. Maximum is 2MB.";
        } else {
            // Verify it's actually an image
            $img_info = getimagesize($_FILES['photo']['tmp_name']);
            if (!$img_info) {
                $error = "Invalid image file.";
            } else {
                $photo_dir = '../uploads/photos/';
                if (!is_dir($photo_dir)) mkdir($photo_dir, 0755, true);

                // Delete old photo
                if (!empty($emp['photo']) && file_exists($photo_dir . $emp['photo'])) {
                    unlink($photo_dir . $emp['photo']);
                }

                $filename = 'emp_' . $id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_dir . $filename)) {
                    db_run($pdo, "UPDATE employees SET photo=? WHERE id=?", array($filename, $id));
                    $emp['photo'] = $filename;
                    $success = "Photo updated successfully.";
                } else {
                    $error = "Upload failed. Check /uploads/photos/ folder permissions.";
                }
            }
        }
    } else {
        $error = "No file selected.";
    }
}

$photo_url = (!empty($emp['photo'])) ? '../uploads/photos/' . htmlspecialchars($emp['photo']) : null;
// Build initials for fallback avatar
$parts    = explode(' ', trim($emp['full_name']));
$initials = '';
foreach (array_slice($parts,0,2) as $p) if ($p) $initials .= strtoupper($p[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload Photo | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .avatar-lg { width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #dee2e6; }
    .avatar-init { width:120px;height:120px;border-radius:50%;background:#4a90d9;color:#fff;
                   display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:600;margin:0 auto; }
    .drop-zone { border:2px dashed #dee2e6;border-radius:10px;padding:30px;text-align:center;
                 cursor:pointer;transition:border-color .2s; }
    .drop-zone:hover, .drop-zone.dragover { border-color:#007bff;background:#f0f8ff; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Upload Employee Photo</h1></div></div>
  <div class="content"><div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-6">

        <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="card">
          <div class="card-header"><h3 class="card-title">Photo for <?= htmlspecialchars($emp['full_name']) ?></h3></div>
          <div class="card-body text-center">

            <!-- Current photo -->
            <div class="mb-3">
              <?php if ($photo_url): ?>
                <img src="<?= $photo_url ?>" class="avatar-lg" alt="Employee photo" id="preview">
              <?php else: ?>
                <div class="avatar-init" id="initials_box"><?= $initials ?></div>
                <img src="" class="avatar-lg d-none" id="preview">
              <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" action="">
              <?php csrf_field(); ?>
              <div class="drop-zone" id="dropZone" onclick="document.getElementById('photoInput').click()">
                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2 d-block"></i>
                <div style="font-size:13px;color:#6c757d;">Click to select or drag &amp; drop a photo here</div>
                <div style="font-size:11px;color:#aaa;margin-top:4px;">JPG, PNG, WEBP — max 2MB</div>
              </div>
              <input type="file" name="photo" id="photoInput" class="d-none" accept=".jpg,.jpeg,.png,.gif,.webp">
              <div id="fileName" class="mt-2 text-muted" style="font-size:12px;"></div>
              <div class="mt-3 d-flex justify-content-between">
                <a href="view_employee.php?id=<?= $id ?>" class="btn btn-secondary">
                  <i class="fas fa-arrow-left mr-1"></i> Back to profile
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-upload mr-1"></i> Upload photo
                </button>
              </div>
            </form>

          </div>
        </div>

      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    var input  = document.getElementById('photoInput');
    var drop   = document.getElementById('dropZone');
    var prev   = document.getElementById('preview');
    var initb  = document.getElementById('initials_box');
    var fname  = document.getElementById('fileName');

    input.addEventListener('change', previewFile);

    drop.addEventListener('dragover', function(e){ e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave',function(){ drop.classList.remove('dragover'); });
    drop.addEventListener('drop', function(e){
        e.preventDefault(); drop.classList.remove('dragover');
        input.files = e.dataTransfer.files;
        previewFile();
    });

    function previewFile(){
        var file = input.files[0];
        if (!file) return;
        fname.textContent = file.name + ' (' + (file.size/1024).toFixed(0) + ' KB)';
        var reader = new FileReader();
        reader.onload = function(e){
            prev.src = e.target.result;
            prev.classList.remove('d-none');
            if (initb) initb.style.display='none';
        };
        reader.readAsDataURL(file);
    }
});
</script>
</body></html>
