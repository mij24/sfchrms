<?php
// ============================================================
// FILE: includes/time_helper.php
// Place in: hrms/includes/time_helper.php
//
// Include ONCE in any file that records or displays time.
// Must be included AFTER db.php so $pdo is available.
//
// Reads from settings table:
//   system_timezone     -> sets PHP timezone
//   clock_delta_seconds -> offset added to time()
//
// Provides these functions:
//   hrms_now()        -> corrected Unix timestamp
//   hrms_date($fmt)   -> date() using corrected time
//   hrms_today()      -> Y-m-d using corrected time
//   hrms_time_str()   -> H:i:s using corrected time
//   hrms_datetime()   -> Y-m-d H:i:s using corrected time
// ============================================================

if (defined('HRMS_TIME_HELPER_LOADED')) return;
define('HRMS_TIME_HELPER_LOADED', true);

global $pdo;
$_hrms_delta = 0;

try {
    $stmt = $pdo->query(
        "SELECT setting_key, setting_value FROM settings
         WHERE setting_key IN ('system_timezone','clock_delta_seconds')"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tz_val = 'Asia/Manila';
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'system_timezone' && $row['setting_value']) {
            $tz_val = $row['setting_value'];
        }
        if ($row['setting_key'] === 'clock_delta_seconds') {
            $_hrms_delta = (int)$row['setting_value'];
        }
    }
    try { date_default_timezone_set($tz_val); }
    catch (Exception $e) { date_default_timezone_set('Asia/Manila'); }
} catch (Exception $e) {
    date_default_timezone_set('Asia/Manila');
}

function hrms_now()      { global $_hrms_delta; return time() + $_hrms_delta; }
function hrms_date($fmt, $ts = null) { if ($ts === null) $ts = hrms_now(); return date($fmt, $ts); }
function hrms_today()    { return hrms_date('Y-m-d'); }
function hrms_time_str() { return hrms_date('H:i:s'); }
function hrms_datetime() { return hrms_date('Y-m-d H:i:s'); }