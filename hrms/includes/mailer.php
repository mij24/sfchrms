<?php
// ============================================
// FILE: includes/mailer.php
// Email notifications via PHPMailer
// Requires: composer require phpmailer/phpmailer
// OR download PHPMailer from github and place in
// C:\xampp\htdocs\hrms\vendor\PHPMailer\
// ============================================

// Load PHPMailer — try Composer autoload first, then manual
$phpmailer_loaded = false;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $phpmailer_loaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
} elseif (file_exists(__DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
    $phpmailer_loaded = true;
}

/**
 * Send an email notification.
 *
 * Usage:
 *   send_notification($pdo,
 *       'employee@email.com',
 *       'Your Name',
 *       'Leave Request Approved',
 *       '<p>Your leave request has been approved.</p>'
 *   );
 */
function send_notification(PDO $pdo, $to_email, $to_name, $subject, $html_body) {
    global $phpmailer_loaded;

    if (!$phpmailer_loaded) {
        error_log("PHPMailer not installed. Email not sent to: $to_email");
        return false;
    }

    // Load SMTP settings from DB
    $cfg_raw = db_fetchall($pdo, "SELECT setting_key,setting_value FROM settings WHERE setting_group='email'");
    $cfg = array();
    foreach ($cfg_raw as $c) $cfg[$c['setting_key']] = $c['setting_value'];

    if (empty($cfg['smtp_host']) || empty($cfg['smtp_username'])) {
        error_log("SMTP not configured. Email not sent.");
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_username'];
        $mail->Password   = $cfg['smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($cfg['smtp_port'] ?? 587);

        $mail->setFrom($cfg['smtp_from_email'], $cfg['smtp_from_name'] ?? 'HRMS');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_template($subject, $html_body, $cfg['smtp_from_name'] ?? 'HRMS');
        $mail->AltBody = strip_tags($html_body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function email_template($title, $body, $company = 'HRMS') {
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f6f9;padding:20px;">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;">
      <div style="background:#007bff;padding:20px 28px;">
        <h2 style="color:#fff;margin:0;font-size:18px;">' . htmlspecialchars($company) . '</h2>
      </div>
      <div style="padding:28px;">
        <h3 style="font-size:16px;margin-bottom:16px;">' . htmlspecialchars($title) . '</h3>
        ' . $body . '
        <p style="margin-top:24px;font-size:12px;color:#aaa;">
          This is an automated message from the HRMS system. Please do not reply directly.
        </p>
      </div>
    </div>
    </body></html>';
}

// ── Convenience notification functions ────────────────────────────

function notify_leave_approved(PDO $pdo, $employee_id, $leave_type, $date_from, $date_to) {
    $emp = db_fetch($pdo,
        "SELECT e.full_name, e.email_address FROM employees e WHERE e.id=?",
        array($employee_id)
    );
    if (!$emp || !$emp['email_address']) return;
    send_notification($pdo,
        $emp['email_address'], $emp['full_name'],
        'Leave request approved',
        '<p>Dear <strong>'.htmlspecialchars($emp['full_name']).'</strong>,</p>
         <p>Your <strong>'.htmlspecialchars($leave_type).'</strong> request from
         <strong>'.date('M d, Y',strtotime($date_from)).'</strong> to
         <strong>'.date('M d, Y',strtotime($date_to)).'</strong> has been <span style="color:green;font-weight:700;">approved</span>.</p>'
    );
}

function notify_leave_rejected(PDO $pdo, $employee_id, $leave_type) {
    $emp = db_fetch($pdo, "SELECT full_name,email_address FROM employees WHERE id=?", array($employee_id));
    if (!$emp || !$emp['email_address']) return;
    send_notification($pdo,
        $emp['email_address'], $emp['full_name'],
        'Leave request update',
        '<p>Dear <strong>'.htmlspecialchars($emp['full_name']).'</strong>,</p>
         <p>Your <strong>'.htmlspecialchars($leave_type).'</strong> request has been <span style="color:red;font-weight:700;">rejected</span>. Please contact HR for more information.</p>'
    );
}

function notify_payslip_released(PDO $pdo, $employee_id, $period_start, $period_end, $net_pay) {
    $emp = db_fetch($pdo, "SELECT full_name,email_address FROM employees WHERE id=?", array($employee_id));
    if (!$emp || !$emp['email_address']) return;
    send_notification($pdo,
        $emp['email_address'], $emp['full_name'],
        'Your payslip is ready',
        '<p>Dear <strong>'.htmlspecialchars($emp['full_name']).'</strong>,</p>
         <p>Your payslip for the period <strong>'.date('M d',strtotime($period_start)).' &ndash; '.date('M d, Y',strtotime($period_end)).'</strong> has been released.</p>
         <p>Net pay: <strong>&#8369; '.number_format($net_pay,2).'</strong></p>
         <p>Log in to the HRMS portal to view and download your payslip.</p>'
    );
}

function notify_account_created(PDO $pdo, $employee_id, $username, $temp_password, $email) {
    $emp = db_fetch($pdo, "SELECT full_name FROM employees WHERE id=?", array($employee_id));
    $name = $emp ? $emp['full_name'] : $username;
    send_notification($pdo,
        $email, $name,
        'Your HRMS account has been created',
        '<p>Dear <strong>'.htmlspecialchars($name).'</strong>,</p>
         <p>Your HRMS account has been created. Please use the following credentials to log in:</p>
         <table style="border-collapse:collapse;margin:16px 0;">
           <tr><td style="padding:6px 12px;background:#f8f9fa;font-weight:600;">Username</td>
               <td style="padding:6px 12px;"><code>'.htmlspecialchars($username).'</code></td></tr>
           <tr><td style="padding:6px 12px;background:#f8f9fa;font-weight:600;">Temp password</td>
               <td style="padding:6px 12px;"><code>'.htmlspecialchars($temp_password).'</code></td></tr>
         </table>
         <p style="color:#dc3545;"><strong>You will be required to change your password on first login.</strong></p>'
    );
}

// ── How to use in leave_requests.php ──────────────────────────────
// require_once '../includes/mailer.php';
// After approval:
//   notify_leave_approved($pdo, $emp_id, $lr['leave_type'], $lr['date_from'], $lr['date_to']);
// After rejection:
//   notify_leave_rejected($pdo, $emp_id, $lr['leave_type']);

// ── How to use in payroll_bulk.php / payroll_release.php ──────────
// After releasing payroll:
//   notify_payslip_released($pdo, $emp_id, $period_start, $period_end, $net_pay);

// ── How to use in users.php after creating temp account ───────────
// if (!empty($emp['email_address'])) {
//     notify_account_created($pdo, $emp_id, $username, $raw_temp, $emp['email_address']);
// }
?>