<?php
// ============================================
// FILE: pages/backup.php
// Database backup & restore — admin only
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_ADMIN);

$success = ''; $error = '';
$backup_dir = '../backups/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

// Protect backup folder
$htaccess_backup = $backup_dir . '.htaccess';
if (!file_exists($htaccess_backup)) {
    file_put_contents($htaccess_backup, "Order deny,allow\nDeny from all\n");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action']);

    // ── Generate SQL backup manually (no exec required) ──
    if ($action === 'backup') {
        $filename   = 'hrms_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath   = $backup_dir . $filename;
        $sql_output = "-- HRMS Database Backup\n";
        $sql_output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql_output .= "-- ================================================\n\n";
        $sql_output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = db_fetchall($pdo, "SHOW TABLES");
        foreach ($tables as $table_row) {
            $table = array_values($table_row)[0];

            // CREATE TABLE statement
            $create = db_fetch($pdo, "SHOW CREATE TABLE `$table`");
            $sql_output .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_output .= array_values($create)[1] . ";\n\n";

            // INSERT rows
            $rows = db_fetchall($pdo, "SELECT * FROM `$table`");
            if (!empty($rows)) {
                $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
                $sql_output .= "INSERT INTO `$table` ($cols) VALUES\n";
                $vals = array();
                foreach ($rows as $row) {
                    $escaped = array();
                    foreach ($row as $v) {
                        if ($v === null) {
                            $escaped[] = 'NULL';
                        } else {
                            $escaped[] = "'" . $pdo->quote(strval($v), PDO::PARAM_STR) . "'";
                        }
                    }
                    $vals[] = '(' . implode(',', $escaped) . ')';
                }
                $sql_output .= implode(",\n", $vals) . ";\n\n";
            }
        }

        $sql_output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($filepath, $sql_output);
        $success = "Backup created: $filename";
        audit_log($pdo,'EXPORT','database',0,array('file'=>$filename));
    }

    // ── Download backup ──
    if ($action === 'download') {
        $file = clean_string($_POST['filename'], 100);
        $file = basename($file); // security: strip any path traversal
        $path = $backup_dir . $file;
        if (file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'sql') {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
        $error = "File not found.";
    }

    // ── Delete backup ──
    if ($action === 'delete_backup') {
        $file = basename(clean_string($_POST['filename'], 100));
        $path = $backup_dir . $file;
        if (file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'sql') {
            unlink($path);
            $success = "Backup deleted.";
        }
    }
}

// List existing backups
$backups = array();
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '*.sql');
    if ($files) {
        rsort($files);
        foreach ($files as $f) {
            $backups[] = array(
                'name'    => basename($f),
                'size'    => round(filesize($f) / 1024, 1),
                'created' => date('M d, Y H:i', filemtime($f))
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Backup | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Database Backup &amp; Restore</h1></div></div>
  <div class="content"><div class="container-fluid">

    <?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger  alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row">
      <div class="col-md-4">
        <!-- Create backup -->
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Create backup</h3></div>
          <div class="card-body">
            <p style="font-size:13px;color:#6c757d;">
              Creates a complete SQL dump of the HRMS database including all tables and data.
              Backups are stored in <code>/backups/</code> and protected from direct browser access.
            </p>
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="backup">
              <button type="submit" class="btn btn-success btn-block">
                <i class="fas fa-database mr-1"></i> Create backup now
              </button>
            </form>
          </div>
        </div>

        <!-- Restore info -->
        <div class="card card-warning card-outline">
          <div class="card-header"><h3 class="card-title">How to restore</h3></div>
          <div class="card-body" style="font-size:13px;color:#6c757d;">
            <ol style="padding-left:16px;margin:0;">
              <li>Download the backup file below</li>
              <li>Open phpMyAdmin</li>
              <li>Select the <code>hrms</code> database</li>
              <li>Click <strong>Import</strong> tab</li>
              <li>Choose the downloaded <code>.sql</code> file</li>
              <li>Click <strong>Go</strong></li>
            </ol>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Existing backups (<?= count($backups) ?>)</h3></div>
          <div class="card-body p-0">
            <?php if (empty($backups)): ?>
              <p class="text-muted text-center py-4">No backups yet. Create your first backup.</p>
            <?php else: ?>
            <table class="table table-sm table-hover mb-0">
              <thead class="thead-light">
                <tr><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($backups as $b): ?>
                <tr>
                  <td><i class="fas fa-file-code mr-1 text-muted"></i><?= htmlspecialchars($b['name']) ?></td>
                  <td><?= $b['size'] ?> KB</td>
                  <td><?= $b['created'] ?></td>
                  <td style="white-space:nowrap;">
                    <form method="POST" action="" class="d-inline">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="download">
                      <input type="hidden" name="filename" value="<?= htmlspecialchars($b['name']) ?>">
                      <button type="submit" class="btn btn-primary btn-xs">
                        <i class="fas fa-download"></i> Download
                      </button>
                    </form>
                    <form method="POST" action="" class="d-inline">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_backup">
                      <input type="hidden" name="filename" value="<?= htmlspecialchars($b['name']) ?>">
                      <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Delete this backup?')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>
