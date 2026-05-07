<?php
// Place in hrms/pages/read_error_log.php
// Visit it to see the ACTUAL PHP error

echo '<pre style="font-size:12px;padding:20px;">';

// Common XAMPP error log locations
$log_paths = array(
    'C:\\xampp\\php\\logs\\php_error_log',
    'C:\\xampp\\apache\\logs\\error.log', 
    'C:\\xampp\\php\\logs\\php_error.log',
    ini_get('error_log'),
    'C:\\xampp\\tmp\\php_error.log',
);

echo "PHP error_log setting: " . ini_get('error_log') . "\n\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n\n";

foreach ($log_paths as $path) {
    if ($path && file_exists($path)) {
        echo "=== Found log at: $path ===\n";
        // Show last 50 lines
        $lines = file($path);
        $last = array_slice($lines, -50);
        // Filter for teaching_load errors
        foreach ($last as $line) {
            if (stripos($line, 'teaching_load') !== false || 
                stripos($line, 'fatal') !== false ||
                stripos($line, 'parse error') !== false ||
                stripos($line, 'syntax') !== false) {
                echo htmlspecialchars($line);
            }
        }
        echo "\n--- Last 10 lines ---\n";
        foreach (array_slice($lines, -10) as $line) {
            echo htmlspecialchars($line);
        }
        break;
    }
}

echo "\n\nAlso checking with trigger_error:\n";
// Force an error log write
trigger_error("TL_DIAGNOSTIC: " . date('Y-m-d H:i:s') . " - read_error_log.php loaded", E_USER_NOTICE);

echo "</pre>";