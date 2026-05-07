-- phpMyAdmin SQL Dump
-- version 4.6.5.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2026 at 08:16 AM
-- Server version: 10.1.21-MariaDB
-- PHP Version: 7.1.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrms`
--

-- --------------------------------------------------------

--
-- Table structure for table `allowance_types`
--

CREATE TABLE `allowance_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'Allowance',
  `is_taxable` tinyint(1) DEFAULT '0',
  `is_recurring` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `allowance_types`
--

INSERT INTO `allowance_types` (`id`, `name`, `type`, `is_taxable`, `is_recurring`, `created_at`) VALUES
(1, 'Transportation allowance', 'Allowance', 0, 1, '2026-04-12 01:37:34'),
(2, 'Meal allowance', 'Allowance', 0, 1, '2026-04-12 01:37:34'),
(3, 'Rice allowance', 'Allowance', 0, 1, '2026-04-12 01:37:34'),
(4, 'Communication allowance', 'Allowance', 0, 1, '2026-04-12 01:37:34'),
(5, 'Housing allowance', 'Allowance', 1, 1, '2026-04-12 01:37:34'),
(6, 'Late deduction', 'Deduction', 0, 0, '2026-04-12 01:37:34'),
(7, 'Absent deduction', 'Deduction', 0, 0, '2026-04-12 01:37:34'),
(8, 'Loan amortization', 'Deduction', 0, 1, '2026-04-12 01:37:34');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `priority` enum('Normal','Important','Urgent') DEFAULT 'Normal',
  `posted_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `body`, `priority`, `posted_by`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Graduation', 'hihihihi', 'Normal', 1, 1, '2026-04-14 11:02:26', '2026-04-14 11:02:26');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `job_opening_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `source` enum('Referral','JobStreet','LinkedIn','Walk-in','Indeed','Company website','Other') DEFAULT 'Other',
  `resume_path` varchar(255) DEFAULT NULL,
  `application_date` date DEFAULT NULL,
  `stage` enum('Initial screening','HR interview','Department interview','Exam / Assessment','Job offer','Hired','Rejected') DEFAULT 'Initial screening',
  `offered_salary` decimal(12,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `offer_letter_path` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `appraisals`
--

CREATE TABLE `appraisals` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `overall_rating` decimal(3,1) DEFAULT NULL,
  `kpi_score` decimal(5,2) DEFAULT NULL,
  `attendance_score` decimal(5,2) DEFAULT NULL,
  `attitude_score` decimal(5,2) DEFAULT NULL,
  `performance_score` decimal(5,2) DEFAULT NULL,
  `strengths` text,
  `improvements` text,
  `comments` text,
  `status` enum('Draft','Submitted','Acknowledged') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `approval_templates`
--

CREATE TABLE `approval_templates` (
  `id` int(11) NOT NULL,
  `request_type_id` int(11) NOT NULL,
  `employee_classification` enum('Teaching','Non-Teaching','All') DEFAULT 'All',
  `step_order` int(5) NOT NULL,
  `role_required` varchar(50) NOT NULL COMMENT 'matches users.role',
  `step_label` varchar(100) NOT NULL COMMENT 'Display label for this step',
  `action_label` varchar(50) DEFAULT 'Approve' COMMENT 'Check/Consolidate/Recommending Approval/Approved/Noted',
  `can_proceed_after_this` tinyint(1) DEFAULT '0' COMMENT '1 = employee may proceed after this step',
  `triggers_logbook` tinyint(1) NOT NULL DEFAULT '0',
  `logbook_office` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `approval_templates`
--

INSERT INTO `approval_templates` (`id`, `request_type_id`, `employee_classification`, `step_order`, `role_required`, `step_label`, `action_label`, `can_proceed_after_this`, `triggers_logbook`, `logbook_office`) VALUES
(1, 1, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(2, 3, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(3, 4, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(4, 5, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(5, 6, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(6, 16, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(7, 18, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(8, 19, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(9, 20, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(10, 21, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(16, 1, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(17, 3, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(18, 4, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(19, 5, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(20, 6, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(21, 16, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(22, 18, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(23, 19, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(24, 20, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(25, 21, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(31, 1, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(32, 3, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(33, 4, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(34, 5, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(35, 6, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(36, 16, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(37, 18, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(38, 19, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(39, 20, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(40, 21, 'All', 3, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(46, 1, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(47, 3, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(48, 4, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(49, 5, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(50, 6, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(51, 16, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(52, 18, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(53, 19, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(54, 20, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(55, 21, 'All', 4, 'president', 'President', 'Approved', 0, 1, 'President'),
(61, 1, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(62, 3, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(63, 4, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(64, 5, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(65, 6, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(66, 16, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(67, 18, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(68, 19, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(69, 20, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(70, 21, 'All', 5, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(76, 2, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(77, 17, 'All', 1, 'dept_secretary', 'Department Secretary', 'Digital Sign & Endorse', 0, 0, NULL),
(79, 2, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(80, 17, 'All', 2, 'manager', 'Department Head', 'Digital Sign & Forward', 1, 0, NULL),
(82, 2, 'All', 3, 'clinic_head', 'Clinic Office', 'Medical Review & Sign', 0, 0, NULL),
(83, 17, 'All', 3, 'clinic_head', 'Clinic Office', 'Medical Review & Sign', 0, 0, NULL),
(85, 2, 'All', 4, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(86, 17, 'All', 4, 'vp_admin', 'VP for Administration', 'Recommending Approval', 0, 1, 'VP Administration'),
(88, 2, 'All', 5, 'president', 'President', 'Approved', 0, 1, 'President'),
(89, 17, 'All', 5, 'president', 'President', 'Approved', 0, 1, 'President'),
(91, 2, 'All', 6, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL),
(92, 17, 'All', 6, 'hr', 'HR Office', 'Received & Filed', 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `am_status` varchar(50) DEFAULT NULL,
  `pm_time_in` time DEFAULT NULL,
  `pm_time_out` time DEFAULT NULL,
  `pm_status` varchar(50) DEFAULT NULL,
  `status` enum('Present','Absent','Late','Half-day','On leave','Holiday','Rest day') DEFAULT 'Present',
  `tardiness_minutes` int(6) DEFAULT '0',
  `overtime_hours` decimal(4,2) DEFAULT '0.00',
  `undertime_minutes` int(6) DEFAULT '0',
  `has_offset_schedule` tinyint(1) DEFAULT '0',
  `lunch_break_skipped` tinyint(1) DEFAULT '0',
  `am_forgot_clockout` tinyint(1) DEFAULT '0',
  `pm_forgot_clockin` tinyint(1) DEFAULT '0',
  `effective_shift_start` time DEFAULT NULL,
  `effective_shift_end` time DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `time_in`, `time_out`, `am_status`, `pm_time_in`, `pm_time_out`, `pm_status`, `status`, `tardiness_minutes`, `overtime_hours`, `undertime_minutes`, `has_offset_schedule`, `lunch_break_skipped`, `am_forgot_clockout`, `pm_forgot_clockin`, `effective_shift_start`, `effective_shift_end`, `remarks`, `created_at`) VALUES
(1, 3, '2026-04-25', '07:08:49', '16:28:11', NULL, NULL, NULL, NULL, 'Present', 0, '0.00', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-04-25 16:09:24'),
(2, 3, '2026-04-26', '09:07:04', '12:28:52', NULL, '12:29:15', NULL, NULL, 'Late', 67, '0.00', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-04-26 01:07:04'),
(3, 3, '2026-04-27', '13:40:25', '13:50:25', NULL, '13:51:10', '17:20:05', NULL, 'Late', 340, '0.33', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-04-27 05:40:25'),
(4, 3, '2026-04-28', '13:11:07', '13:11:15', NULL, '19:50:33', '19:50:41', NULL, 'Late', 311, '2.84', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-04-28 05:11:07'),
(5, 9, '2026-04-29', '08:35:05', NULL, NULL, NULL, NULL, NULL, 'Late', 35, '0.00', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-04-29 00:35:05'),
(6, 3, '2026-04-29', '08:35:21', NULL, NULL, NULL, NULL, NULL, 'Late', 35, '0.00', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-04-29 00:35:21'),
(7, 7, '2026-05-07', '13:31:21', '13:31:25', NULL, '13:31:29', NULL, NULL, 'Late', 331, '0.00', 0, 0, 0, 0, 0, NULL, NULL, NULL, '2026-05-07 05:31:21');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_flags`
--

CREATE TABLE `attendance_flags` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `flag_date` date NOT NULL,
  `flag_type` enum('Forgot Clock Out AM','Forgot Clock In PM','Late AM','Late Return PM','Undertime','No Clock Out PM','Unusual Time Gap') NOT NULL,
  `flag_note` varchar(255) DEFAULT NULL,
  `notified_users` varchar(255) DEFAULT NULL COMMENT 'comma-separated user IDs notified',
  `is_resolved` tinyint(1) DEFAULT '0',
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_note` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `changes` longtext,
  `ip_address` varchar(45) DEFAULT NULL,
  `page` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `table_name`, `record_id`, `changes`, `ip_address`, `page`, `created_at`) VALUES
(1, 1, 'admin', 'UPDATE', 'settings', 0, '{\"section\":\"time\",\"system_timezone\":\"Asia\\/Dubai\",\"clock_offset_min\":0,\"changed_by\":\"admin\"}', '::1', 'settings.php', '2026-04-24 15:07:00'),
(2, 1, 'admin', 'UPDATE', 'settings', 0, '{\"section\":\"time\",\"system_timezone\":\"America\\/New_York\",\"clock_offset_min\":0,\"changed_by\":\"admin\"}', '::1', 'settings.php', '2026-04-24 15:07:36'),
(3, 1, 'admin', 'UPDATE', 'settings', 0, '{\"section\":\"time\",\"system_timezone\":\"Asia\\/Manila\",\"clock_offset_min\":0,\"changed_by\":\"admin\"}', '::1', 'settings.php', '2026-04-24 15:08:07'),
(4, 1, 'admin', 'UPDATE', 'settings', 0, '{\"section\":\"time\",\"system_timezone\":\"Asia\\/Manila\",\"clock_offset_min\":0,\"changed_by\":\"admin\"}', '::1', 'settings.php', '2026-04-24 23:30:46'),
(5, 1, 'admin', 'UPDATE', 'settings', 0, '{\"section\":\"time\",\"system_timezone\":\"Asia\\/Manila\",\"clock_offset_min\":0,\"changed_by\":\"admin\"}', '::1', 'settings.php', '2026-04-25 08:42:33'),
(6, 1, 'admin', 'UPDATE', 'settings', 0, '{\"timezone\":\"Asia\\/Dubai\",\"delta_seconds\":-115200,\"delta_human\":\"-115200s\"}', '::1', 'settings.php', '2026-04-25 15:21:10'),
(7, 1, 'admin', 'UPDATE', 'settings', 0, '{\"timezone\":\"Asia\\/Manila\",\"delta_seconds\":-115200,\"delta_human\":\"-115200s\"}', '::1', 'settings.php', '2026-04-25 15:21:18'),
(8, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:31:19'),
(9, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:32:45'),
(10, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:36:49'),
(11, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:38:49'),
(12, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:39:16'),
(13, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:40:12'),
(14, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:41:03'),
(15, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\",\"timezone\":\"Asia\\/Manila\"}', '::1', 'settings.php', '2026-04-25 15:49:40'),
(16, 1, 'admin', 'UPDATE', 'settings', 0, '{\"timezone\":\"UTC\",\"delta_seconds\":0,\"delta_human\":\"reset\"}', '::1', 'settings.php', '2026-04-25 15:50:29'),
(17, 1, 'admin', 'UPDATE', 'settings', 0, '{\"timezone\":\"UTC\",\"delta_seconds\":-28816,\"delta_human\":\"-28816s\"}', '::1', 'settings.php', '2026-04-25 15:50:51'),
(18, 1, 'admin', 'UPDATE', 'settings', 0, '{\"timezone\":\"UTC\",\"delta_seconds\":-32435,\"delta_human\":\"-32435s\"}', '::1', 'settings.php', '2026-04-25 16:08:51'),
(19, 1, 'admin', 'UPDATE', 'settings', 0, '{\"timezone\":\"Asia\\/Manila\",\"delta_seconds\":-32435,\"delta_human\":\"-32435s\"}', '::1', 'settings.php', '2026-04-25 16:32:46'),
(20, 1, 'admin', 'UPDATE', 'settings', 0, '{\"action\":\"reset_clock\"}', '::1', 'settings.php', '2026-04-25 16:33:02'),
(21, 7, 'hradmin', 'INSERT', 'teaching_loads', 1, '{\"action\":\"create\"}', '::1', 'teaching_load.php', '2026-04-27 04:45:17'),
(22, 7, 'hradmin', 'UPDATE', 'teaching_loads', 1, '{\"action\":\"submitted_for_faculty_review\"}', '::1', 'teaching_load.php', '2026-04-27 05:16:59'),
(23, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-28 09:40:47'),
(24, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-04-28 09:51:54'),
(25, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-04-28 09:52:44'),
(26, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-04-28 10:05:44'),
(27, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-04-28 10:06:14'),
(28, 9, 'beth', 'DIGITAL_SIGN', 'request_approvals', 1, '{\"request_id\":1,\"step\":\"Department\\/Office Timekeeper\",\"action\":\"Check\",\"signer\":\"ELIZABETH MANALIGOD\"}', '::1', 'request_detail.php', '2026-04-28 10:06:14'),
(29, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-04-28 10:06:14'),
(30, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_head\"}', '::1', 'request_detail.php', '2026-04-28 10:10:30'),
(31, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_head\"}', '::1', 'request_detail.php', '2026-04-28 10:10:44'),
(32, 10, 'monz', 'DIGITAL_SIGN', 'request_approvals', 2, '{\"request_id\":1,\"step\":\"Department\\/Office Head\",\"action\":\"Check\",\"signer\":\"RAMON Z. MARAYAG\"}', '::1', 'request_detail.php', '2026-04-28 10:10:44'),
(33, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_head\"}', '::1', 'request_detail.php', '2026-04-28 10:10:44'),
(34, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-28 10:12:25'),
(35, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-28 11:39:41'),
(36, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-28 11:40:49'),
(37, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_head\"}', '::1', 'request_detail.php', '2026-04-28 11:42:02'),
(38, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-04-28 11:43:23'),
(39, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-28 18:38:35'),
(40, 16, 'glenny', 'INSERT', 'document_logbook', 1, '{\"control_number\":\"VPA-2026-0001\",\"office\":\"VP Administration\",\"action\":\"incoming_logged\"}', '::1', 'exec_secretary_logbook.php', '2026-04-28 19:15:52'),
(41, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"non_academic_head\"}', '::1', 'request_detail.php', '2026-04-28 19:17:05'),
(42, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"non_academic_head\"}', '::1', 'request_detail.php', '2026-04-28 19:17:15'),
(43, 13, 'elena', 'DIGITAL_SIGN', 'request_approvals', 4, '{\"request_id\":1,\"step\":\"VP for Administration\",\"action\":\"Recommending Approval\",\"signer\":\"ELENA C. ARIOLA\"}', '::1', 'request_detail.php', '2026-04-28 19:17:15'),
(44, 13, 'elena', 'INSERT', 'document_logbook', 2, '{\"control_number\":\"PRES-2026-0001\",\"triggered_by_step\":\"VP for Administration\",\"request_id\":1}', '::1', 'request_detail.php', '2026-04-28 19:17:15'),
(45, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"non_academic_head\"}', '::1', 'request_detail.php', '2026-04-28 19:17:15'),
(46, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"non_academic_head\"}', '::1', 'request_detail.php', '2026-04-28 19:17:47'),
(47, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-29 00:16:01'),
(48, 15, 'junjun', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"president\"}', '::1', 'request_detail.php', '2026-04-29 00:25:51'),
(49, 15, 'junjun', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"president\"}', '::1', 'request_detail.php', '2026-04-29 00:25:54'),
(50, 15, 'junjun', 'DIGITAL_SIGN', 'request_approvals', 5, '{\"request_id\":1,\"step\":\"School President\",\"action\":\"Final Approval\",\"signer\":\"EDMUNDO CASTA\\u00d1EDA JR.\"}', '::1', 'request_detail.php', '2026-04-29 00:25:54'),
(51, 15, 'junjun', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"president\"}', '::1', 'request_detail.php', '2026-04-29 00:25:54'),
(52, 7, 'hradmin', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"hr_head\"}', '::1', 'request_detail.php', '2026-04-29 00:26:13'),
(53, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"non_academic_head\"}', '::1', 'request_detail.php', '2026-04-29 00:36:28'),
(54, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"vp_admin\"}', '::1', 'request_detail.php', '2026-04-29 02:46:52'),
(55, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"vp_admin\"}', '::1', 'request_detail.php', '2026-04-29 03:00:18'),
(56, 15, 'junjun', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"president\"}', '::1', 'request_detail.php', '2026-04-29 03:01:22'),
(57, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-29 03:21:17'),
(58, 15, 'junjun', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"president\"}', '::1', 'request_detail.php', '2026-04-29 03:55:00'),
(59, 8, 'elouie', 'DIGITAL_SIGN', 'teaching_loads', 1, '{\"action\":\"faculty_confirmed\",\"signer\":\"ELOUIE TUMOLVA\"}', '::1', 'my_inbox.php', '2026-04-29 07:46:27'),
(60, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"vp_admin\"}', '::1', 'request_detail.php', '2026-04-29 08:28:18'),
(61, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"vp_admin\"}', '::1', 'request_detail.php', '2026-04-29 08:29:20'),
(62, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 05:03:02'),
(63, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 13:00:27'),
(64, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 13:04:27'),
(65, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 13:04:28'),
(66, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 13:10:37'),
(67, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 13:19:48'),
(68, 10, 'monz', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"academic_dept_head\"}', '::1', 'request_detail.php', '2026-04-30 13:19:49'),
(69, 9, 'beth', 'INSERT', 'teaching_loads', 2, '{\"action\":\"create\",\"subjects\":2}', '::1', 'teaching_load.php', '2026-04-30 15:01:04'),
(70, 13, 'elena', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"vp_admin\"}', '::1', 'request_detail.php', '2026-05-04 15:12:19'),
(71, 9, 'beth', 'VIEW', 'employee_requests', 1, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-05-05 02:09:55'),
(72, 11, 'jerome', 'VIEW', 'employee_requests', 2, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 03:45:08'),
(73, 11, 'jerome', 'VIEW', 'employee_requests', 2, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 03:52:52'),
(74, 11, 'jerome', 'VIEW', 'employee_requests', 2, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 03:55:44'),
(75, 11, 'jerome', 'UPDATE', 'employee_requests', 2, '{\"action\":\"retracted_by_requester\"}', '::1', 'my_requests_history.php', '2026-05-06 04:03:39'),
(76, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 04:06:46'),
(77, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 04:08:09'),
(78, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 04:08:10'),
(79, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 04:08:10'),
(80, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 05:44:16'),
(81, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 05:46:03'),
(82, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 05:47:11'),
(83, 11, 'jerome', 'VIEW', 'employee_requests', 3, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-06 08:30:22'),
(84, 11, 'jerome', 'UPDATE', 'employee_requests', 3, '{\"action\":\"edited_by_requester\"}', '::1', 'my_approvals.php', '2026-05-06 12:41:40'),
(85, 11, 'jerome', 'UPDATE', 'employee_requests', 3, '{\"action\":\"edited_by_requester\"}', '::1', 'my_approvals.php', '2026-05-06 12:47:55'),
(86, 9, 'beth', 'VIEW', 'employee_requests', 5, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-05-06 13:37:43'),
(87, 11, 'jerome', 'UPDATE', 'employee_requests', 6, '{\"action\":\"edited_by_requester\"}', '::1', 'my_approvals.php', '2026-05-06 21:45:45'),
(88, 11, 'jerome', 'UPDATE', 'employee_requests', 6, '{\"action\":\"edited_by_requester\"}', '::1', 'my_approvals.php', '2026-05-06 21:46:02'),
(89, 9, 'beth', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-05-06 21:48:06'),
(90, 9, 'beth', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-05-06 21:48:54'),
(91, 9, 'beth', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-05-06 21:49:14'),
(92, 9, 'beth', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"dept_secretary\"}', '::1', 'request_detail.php', '2026-05-06 21:49:16'),
(93, 11, 'jerome', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-07 01:18:57'),
(94, 11, 'jerome', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-07 01:19:05'),
(95, 11, 'jerome', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-07 05:24:59'),
(96, 11, 'jerome', 'VIEW', 'employee_requests', 4, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-07 05:25:07'),
(97, 11, 'jerome', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-07 05:28:04'),
(98, 11, 'jerome', 'VIEW', 'employee_requests', 6, '{\"viewer_role\":\"employee\"}', '::1', 'request_detail.php', '2026-05-07 05:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `classification_history`
--

CREATE TABLE `classification_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `action_type` varchar(30) NOT NULL,
  `recommended_by` int(11) DEFAULT NULL,
  `recommender_role` varchar(50) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `classification_notifications`
--

CREATE TABLE `classification_notifications` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `notified_user_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `message` text,
  `is_read` tinyint(1) DEFAULT '0',
  `action_required` tinyint(1) DEFAULT '1',
  `action_taken` tinyint(1) DEFAULT '0',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `dashboard_notes`
--

CREATE TABLE `dashboard_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note_date` date NOT NULL,
  `note_date_end` date DEFAULT NULL COMMENT 'For date range notes',
  `note` text NOT NULL,
  `color` varchar(20) DEFAULT 'primary',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `head_employee_id` int(11) DEFAULT NULL,
  `parent_department_id` int(11) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `head_employee_id`, `parent_department_id`, `cost_center`, `created_at`, `updated_at`) VALUES
(1, 'College of Nursing', 'CON', NULL, NULL, '', '2026-04-15 21:17:00', '2026-04-15 21:17:00'),
(2, 'Human Resource and Development Office', 'HRMDO', NULL, NULL, '', '2026-04-23 23:47:03', '2026-04-23 23:47:03'),
(3, 'Clinic and Dental Services Office', 'CDSO', NULL, NULL, '', '2026-04-23 23:51:06', '2026-04-23 23:51:06'),
(4, 'College of Information Technology', 'CIT', NULL, NULL, '', '2026-04-26 08:19:18', '2026-04-26 08:19:18'),
(5, 'College of Arts and Sciences', 'CAS', NULL, NULL, '', '2026-04-26 08:19:32', '2026-04-26 08:19:32'),
(6, 'Medical and Dental Services', '', NULL, NULL, '', '2026-04-27 13:12:45', '2026-04-27 13:12:45'),
(7, 'Office of the Vice President for Academic Affairs', 'OVPAA', NULL, NULL, '', '2026-04-27 13:14:35', '2026-04-27 13:19:44'),
(8, 'Office of the Vice President for Administration', 'OVPA', NULL, NULL, '', '2026-04-27 13:19:06', '2026-04-27 13:19:58'),
(9, 'Office of the President', 'OP', NULL, NULL, '', '2026-04-27 13:19:22', '2026-04-27 13:19:22'),
(10, 'Accounts Office', 'AO', NULL, NULL, '', '2026-05-02 06:34:40', '2026-05-02 06:34:40'),
(11, 'Office of the Registrar', 'REG', NULL, NULL, '', '2026-05-05 04:12:33', '2026-05-05 04:12:53');

-- --------------------------------------------------------

--
-- Table structure for table `disciplinary`
--

CREATE TABLE `disciplinary` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `incident_date` date NOT NULL,
  `violation_type` varchar(150) NOT NULL,
  `description` text,
  `sanction` enum('Verbal warning','Written warning','Suspension','Demotion','Termination') DEFAULT 'Verbal warning',
  `status` enum('Open','Resolved','Closed') DEFAULT 'Open',
  `nte_issued` tinyint(1) DEFAULT '0',
  `nte_date` date DEFAULT NULL,
  `resolution` text,
  `filed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `document_control_counter`
--

CREATE TABLE `document_control_counter` (
  `id` int(11) NOT NULL,
  `office` varchar(50) NOT NULL,
  `year` year(4) NOT NULL,
  `counter` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `document_control_counter`
--

INSERT INTO `document_control_counter` (`id`, `office`, `year`, `counter`) VALUES
(1, 'VPAA', 2026, 0),
(2, 'VPA', 2026, 1),
(3, 'PRES', 2026, 1),
(4, 'BOT', 2026, 0);

-- --------------------------------------------------------

--
-- Table structure for table `document_logbook`
--

CREATE TABLE `document_logbook` (
  `id` int(11) NOT NULL,
  `control_number` varchar(50) NOT NULL COMMENT 'e.g. VPAA-2026-0001, PRES-2026-0001',
  `request_id` int(11) DEFAULT NULL COMMENT 'Links to employee_requests.id',
  `reference_no` varchar(50) DEFAULT NULL COMMENT 'HRMS reference number e.g. REQ-20260427-0001',
  `office` enum('VP Academic','VP Administration','President','Board') NOT NULL DEFAULT 'President' COMMENT 'Which executive office this belongs to',
  `document_type` varchar(100) NOT NULL COMMENT 'e.g. Leave Request, Teaching Load, Memo, Letter',
  `document_category` enum('Request','Memo','Letter','Report','Contract','Certificate','Resolution','Order','Other') NOT NULL DEFAULT 'Request',
  `requestor_name` varchar(150) NOT NULL,
  `requestor_emp_id` varchar(30) DEFAULT NULL,
  `requestor_dept` varchar(150) DEFAULT NULL,
  `requestor_employee_id` int(11) DEFAULT NULL COMMENT 'Links to employees.id',
  `date_received` datetime NOT NULL COMMENT 'Date and time document arrived at the office',
  `received_from` varchar(150) DEFAULT NULL COMMENT 'Who delivered it (Dept Secretary, HR, Walk-in, etc.)',
  `incoming_remarks` text COMMENT 'Secretary notes on receipt',
  `incoming_logged_by` int(11) DEFAULT NULL COMMENT 'users.id of the exec secretary who logged it',
  `incoming_logged_at` timestamp NULL DEFAULT NULL,
  `exec_action` enum('Pending','Approved','Noted','Recommended','Rejected','Returned','Signed','For Compliance','Deferred') NOT NULL DEFAULT 'Pending',
  `exec_action_by` varchar(150) DEFAULT NULL COMMENT 'Name of the executive who acted',
  `exec_action_date` datetime DEFAULT NULL COMMENT 'Exact date and time of executive decision',
  `exec_remarks` text COMMENT 'Executive remarks / notes on decision',
  `is_released` tinyint(1) NOT NULL DEFAULT '0',
  `date_released` datetime DEFAULT NULL COMMENT 'Actual date document left the office',
  `release_method` enum('Printed Copy','Email','System Notification','Courier','Walk-in Pickup','Fax','Other') DEFAULT NULL,
  `received_by` varchar(150) DEFAULT NULL COMMENT 'Who physically received the released document',
  `received_by_position` varchar(100) DEFAULT NULL,
  `outgoing_remarks` text COMMENT 'Secretary notes on release',
  `outgoing_logged_by` int(11) DEFAULT NULL,
  `outgoing_logged_at` timestamp NULL DEFAULT NULL,
  `status` enum('Incoming','With Executive','Released','Cancelled') NOT NULL DEFAULT 'Incoming',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teaching_load_id` int(11) DEFAULT NULL COMMENT 'FK to teaching_loads.id — used when document_type is Teaching Load'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Executive office document logbook';

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `address` text,
  `contact_number` varchar(20) DEFAULT NULL,
  `email_address` varchar(100) DEFAULT NULL,
  `emergency_contact` varchar(150) DEFAULT NULL,
  `sss_number` varchar(30) DEFAULT NULL,
  `philhealth_number` varchar(30) DEFAULT NULL,
  `pagibig_number` varchar(30) DEFAULT NULL,
  `tin_number` varchar(30) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `org_level_id` int(11) DEFAULT NULL,
  `employment_type` enum('Regular','Probationary','Contractual','Part-time','Project-based') DEFAULT 'Regular',
  `date_hired` date DEFAULT NULL,
  `employment_status` enum('Active','Inactive','Resigned','Terminated','On leave') DEFAULT 'Active',
  `supervisor_id` int(11) DEFAULT NULL,
  `work_location` varchar(100) DEFAULT NULL,
  `work_location_id` int(11) DEFAULT NULL,
  `salary_grade` varchar(50) DEFAULT NULL,
  `salary_grade_id` int(11) DEFAULT NULL,
  `basic_salary` decimal(12,2) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `flex_schedule_id` int(11) DEFAULT NULL,
  `employee_category_id` int(11) DEFAULT NULL,
  `faculty_qualification` varchar(100) DEFAULT NULL,
  `is_part_time` tinyint(1) DEFAULT '0',
  `classification_status` varchar(30) DEFAULT 'Probationary',
  `classification_note` text,
  `classification_date` date DEFAULT NULL,
  `next_review_date` date DEFAULT NULL,
  `regularization_recommended_by` int(11) DEFAULT NULL,
  `regularization_recommended_at` timestamp NULL DEFAULT NULL,
  `regularization_status` varchar(30) DEFAULT NULL,
  `classification_id` int(11) DEFAULT NULL,
  `rank_id` int(11) DEFAULT NULL,
  `employment_type_id` int(11) DEFAULT NULL,
  `employment_status_id` int(11) DEFAULT NULL,
  `has_teaching_load` tinyint(1) DEFAULT '0',
  `employee_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `full_name`, `date_of_birth`, `gender`, `civil_status`, `nationality`, `address`, `contact_number`, `email_address`, `emergency_contact`, `sss_number`, `philhealth_number`, `pagibig_number`, `tin_number`, `department_id`, `position_id`, `org_level_id`, `employment_type`, `date_hired`, `employment_status`, `supervisor_id`, `work_location`, `work_location_id`, `salary_grade`, `salary_grade_id`, `basic_salary`, `photo`, `created_at`, `updated_at`, `flex_schedule_id`, `employee_category_id`, `faculty_qualification`, `is_part_time`, `classification_status`, `classification_note`, `classification_date`, `next_review_date`, `regularization_recommended_by`, `regularization_recommended_at`, `regularization_status`, `classification_id`, `rank_id`, `employment_type_id`, `employment_status_id`, `has_teaching_load`, `employee_type`) VALUES
(3, 'EMP-0001', 'MILBERT MILLARES', '2000-04-22', 'Male', 'Married', 'Filipino', 'home lng na malapit', '', 'email@email.com', '00000000000', '00-000000000', '00000000000', '00000000', '000000000000', 2, 1, NULL, 'Regular', '2026-04-22', 'Active', NULL, 'Main Campus', 1, 'SG-7', 7, '10000.00', NULL, '2026-04-22 09:58:03', '2026-04-23 23:47:22', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, 11, 14, 3, 1, 0, NULL),
(4, 'EMP-0002', 'ELOUIE TUMOLVA', '0000-00-00', 'Male', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 4, 5, NULL, 'Regular', '2026-04-26', 'Active', 6, 'Main Campus', 1, 'SG-12', 12, '33947.00', NULL, '2026-04-26 08:27:58', '2026-05-04 15:04:03', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, 5, 1, 1, 1, 0, NULL),
(5, 'EMP-0003', 'ELIZABETH MANALIGOD', NULL, 'Female', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 4, NULL, NULL, '', '2026-04-27', 'Active', NULL, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-04-27 05:59:31', '2026-04-27 05:59:31', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(6, 'EMP-0004', 'RAMON Z. MARAYAG', '0000-00-00', 'Male', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 4, 14, NULL, 'Regular', '2026-04-27', 'Active', 10, 'Main Campus', 1, '', NULL, '0.00', NULL, '2026-04-27 08:15:35', '2026-05-05 02:15:14', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(7, 'EMP-0005', 'JEROME CABRERA', NULL, 'Male', 'Single', 'Filipino', '', '', '', '', '', '', '', '', 4, NULL, NULL, '', '2026-04-27', 'Active', NULL, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-04-27 13:38:51', '2026-04-27 13:38:51', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(8, 'EMP-0006', 'JENNIFER IDO', NULL, 'Female', 'Single', 'Filipino', '', '', '', '', '', '', '', '', 7, 6, NULL, '', '2026-04-28', 'Active', NULL, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-04-28 05:15:10', '2026-04-28 05:15:10', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(9, 'EMP-0007', 'ELENA C. ARIOLA', '0000-00-00', 'Female', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 8, 11, NULL, 'Regular', '2026-04-28', 'Active', NULL, 'Main Campus', 1, '', NULL, '0.00', NULL, '2026-04-28 12:04:28', '2026-04-28 12:06:30', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(10, 'EMP-0008', 'CHARINA G. ALEJO', NULL, 'Female', 'Single', 'Filipino', '', '', '', '', '', '', '', '', 7, 12, NULL, '', '2026-04-28', 'Active', NULL, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-04-28 12:07:15', '2026-04-28 12:07:15', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(11, 'EMP-0009', 'EDMUNDO CASTAÑEDA JR.', NULL, 'Male', 'Single', 'Filipino', '', '', '', '', '', '', '', '', 9, 13, NULL, '', '2026-04-28', 'Active', NULL, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-04-28 12:08:22', '2026-04-28 12:08:22', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(12, 'EMP-0010', 'GLEN FUTAD', NULL, 'Male', 'Single', 'Filipino', '', '', '', '', '', '', '', '', 8, 10, NULL, '', '2026-04-28', 'Active', 9, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-04-28 18:50:48', '2026-04-28 18:50:48', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(13, 'EMP-0011', 'ALONA MELAD', NULL, 'Female', 'Single', 'Filipino', '', '', '', '', '', '', '', '', 10, 14, NULL, '', '2026-05-02', 'Active', NULL, 'Main Campus', 1, '', NULL, NULL, NULL, '2026-05-02 06:35:43', '2026-05-02 06:35:43', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(14, 'EMP-0012', 'IRENE', NULL, 'Female', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 3, 8, NULL, '', '2026-05-05', 'Active', NULL, '', NULL, '', NULL, NULL, NULL, '2026-05-05 04:07:21', '2026-05-05 04:07:21', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(15, 'EMP-0013', 'DIANNE', NULL, 'Female', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 3, 8, NULL, '', '2026-05-05', 'Active', NULL, '', NULL, '', NULL, NULL, NULL, '2026-05-05 04:09:12', '2026-05-05 04:09:12', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL),
(16, 'EMP-0014', 'ISMAEL DELA ROSA', NULL, 'Male', 'Married', 'Filipino', '', '', '', '', '', '', '', '', 11, 14, NULL, '', '2026-05-05', 'Active', NULL, '', NULL, '', NULL, NULL, NULL, '2026-05-05 04:13:32', '2026-05-05 04:13:32', NULL, NULL, NULL, 0, 'Probationary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_allowances`
--

CREATE TABLE `employee_allowances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `allowance_type_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `effective_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_categories`
--

CREATE TABLE `employee_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text,
  `sort_order` int(5) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employee_categories`
--

INSERT INTO `employee_categories` (`id`, `name`, `parent_id`, `description`, `sort_order`) VALUES
(1, 'Teaching', NULL, 'All academic/faculty personnel', 1),
(2, 'Non-Teaching', NULL, 'All non-academic/administrative personnel', 2),
(3, 'Elementary Faculty', 1, 'Faculty for elementary level', 1),
(4, 'Secondary Faculty', 1, 'Faculty for high school level', 2),
(5, 'Tertiary Faculty', 1, 'Faculty for college/university level', 3),
(6, 'TVET/Technical-Vocational', 1, 'Technical-vocational faculty', 4),
(7, 'Physical Education Faculty', 1, 'PE faculty', 5),
(8, 'Lecturer', 1, 'Part-time lecturers', 6),
(9, 'Personnel & Administrative', 2, 'Office and administrative staff', 1),
(10, 'Support Staff', 2, 'Utility, security, maintenance', 2),
(11, 'Clinic Staff', 2, 'Medical/health personnel', 3);

-- --------------------------------------------------------

--
-- Table structure for table `employee_classifications`
--

CREATE TABLE `employee_classifications` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `category` enum('Teaching','Non-Teaching','Both') DEFAULT 'Both',
  `description` text,
  `sort_order` int(5) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employee_classifications`
--

INSERT INTO `employee_classifications` (`id`, `name`, `parent_id`, `category`, `description`, `sort_order`, `is_active`) VALUES
(1, 'Teaching', NULL, 'Teaching', 'All academic/faculty personnel', 1, 1),
(2, 'Non-Teaching', NULL, 'Non-Teaching', 'All administrative/support personnel', 2, 1),
(3, 'Elementary Faculty', 1, 'Teaching', 'Faculty for elementary level', 1, 1),
(4, 'Secondary/HS Faculty', 1, 'Teaching', 'Faculty for high school level', 2, 1),
(5, 'Tertiary Faculty', 1, 'Teaching', 'Faculty for college level (Master\'s required)', 3, 1),
(6, 'Technical-Vocational', 1, 'Teaching', 'TVET faculty, 1yr trade experience required', 4, 1),
(7, 'Physical Education', 1, 'Teaching', 'BS Physical Education holders', 5, 1),
(8, 'Lecturer/Visiting', 1, 'Teaching', 'Part-time lecturers and visiting professors', 6, 1),
(9, 'Personnel & Administrative', 2, 'Non-Teaching', 'Office and administrative staff', 1, 1),
(10, 'Finance & Accounting', 2, 'Non-Teaching', 'Finance, accounting, budgeting', 2, 1),
(11, 'IT & Systems', 2, 'Non-Teaching', 'Technology and systems support', 3, 1),
(12, 'Library', 2, 'Non-Teaching', 'Library services', 4, 1),
(13, 'Clinic/Health Services', 2, 'Non-Teaching', 'Medical and health personnel', 5, 1),
(14, 'Guidance & Counseling', 2, 'Non-Teaching', 'Guidance counselors', 6, 1),
(15, 'Security', 2, 'Non-Teaching', 'Security personnel', 7, 1),
(16, 'Maintenance & Utility', 2, 'Non-Teaching', 'Janitorial and maintenance', 8, 1),
(17, 'Management', 2, 'Non-Teaching', 'Board, President, Vice Presidents', 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `employee_day_schedules`
--

CREATE TABLE `employee_day_schedules` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `day_of_week` tinyint(1) DEFAULT NULL COMMENT '0=Sun,1=Mon,...,6=Sat; NULL=specific date only',
  `schedule_type` enum('Standard','Teaching','Offset','Flex','Compressed') DEFAULT 'Standard',
  `shift_start` time NOT NULL DEFAULT '08:00:00',
  `shift_end` time NOT NULL DEFAULT '17:00:00',
  `late_threshold` time NOT NULL DEFAULT '08:00:00' COMMENT 'After this = late',
  `undertime_threshold` time NOT NULL DEFAULT '17:00:00' COMMENT 'Before this = undertime',
  `lunch_break_required` tinyint(1) DEFAULT '1' COMMENT '0 = no lunch clock-out required (class during 12-1pm)',
  `lunch_start` time DEFAULT '12:00:00',
  `lunch_end` time DEFAULT '13:00:00',
  `required_hours` decimal(4,2) DEFAULT '8.00',
  `offset_hours` decimal(4,2) DEFAULT '0.00' COMMENT 'Extra hours from offsetting',
  `notes` text,
  `noted_by_id` int(11) DEFAULT NULL COMMENT 'Department Head user id',
  `noted_at` timestamp NULL DEFAULT NULL,
  `recommended_by_id` int(11) DEFAULT NULL COMMENT 'VP user id',
  `recommended_at` timestamp NULL DEFAULT NULL,
  `approved_by_id` int(11) DEFAULT NULL COMMENT 'President user id',
  `approved_at` timestamp NULL DEFAULT NULL,
  `hr_notified` tinyint(1) DEFAULT '0',
  `hr_notified_at` timestamp NULL DEFAULT NULL,
  `status` enum('Draft','Pending','Noted','Recommended','Approved','Rejected') DEFAULT 'Draft',
  `assigned_by` int(11) DEFAULT NULL COMMENT 'Secretary user id',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_exits`
--

CREATE TABLE `employee_exits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `exit_type` enum('Resignation','Retirement','End of Contract','Termination','Death','Redundancy','Mutual Agreement') DEFAULT 'Resignation',
  `last_day` date DEFAULT NULL,
  `date_filed` date DEFAULT NULL,
  `reason` text,
  `exit_interview_date` date DEFAULT NULL,
  `exit_interview_notes` text,
  `clearance_status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `cleared_hr` tinyint(1) DEFAULT '0',
  `cleared_finance` tinyint(1) DEFAULT '0',
  `cleared_it` tinyint(1) DEFAULT '0',
  `cleared_property` tinyint(1) DEFAULT '0',
  `cleared_dept_head` tinyint(1) DEFAULT '0',
  `unpaid_salary` decimal(12,2) DEFAULT '0.00',
  `unused_leave_days` decimal(5,2) DEFAULT '0.00',
  `leave_conversion` decimal(12,2) DEFAULT '0.00',
  `thirteenth_prorated` decimal(12,2) DEFAULT '0.00',
  `separation_pay` decimal(12,2) DEFAULT '0.00',
  `other_pay` decimal(12,2) DEFAULT '0.00',
  `total_final_pay` decimal(12,2) DEFAULT '0.00',
  `status` enum('Draft','Filed','Processing','Completed') DEFAULT 'Draft',
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_onboarding`
--

CREATE TABLE `employee_onboarding` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_qr_codes`
--

CREATE TABLE `employee_qr_codes` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `qr_token` varchar(64) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_ranks`
--

CREATE TABLE `employee_ranks` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `category` enum('Teaching','Non-Teaching','Both') DEFAULT 'Both',
  `salary_grade` varchar(20) DEFAULT NULL,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `description` text,
  `sort_order` int(5) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employee_ranks`
--

INSERT INTO `employee_ranks` (`id`, `name`, `code`, `category`, `salary_grade`, `salary_min`, `salary_max`, `description`, `sort_order`, `is_active`) VALUES
(1, 'Instructor I', 'INS1', 'Teaching', NULL, NULL, NULL, NULL, 1, 1),
(2, 'Instructor II', 'INS2', 'Teaching', NULL, NULL, NULL, NULL, 2, 1),
(3, 'Instructor III', 'INS3', 'Teaching', NULL, NULL, NULL, NULL, 3, 1),
(4, 'Assistant Professor I', 'AP1', 'Teaching', NULL, NULL, NULL, NULL, 4, 1),
(5, 'Assistant Professor II', 'AP2', 'Teaching', NULL, NULL, NULL, NULL, 5, 1),
(6, 'Assistant Professor III', 'AP3', 'Teaching', NULL, NULL, NULL, NULL, 6, 1),
(7, 'Associate Professor I', 'ACP1', 'Teaching', NULL, NULL, NULL, NULL, 7, 1),
(8, 'Associate Professor II', 'ACP2', 'Teaching', NULL, NULL, NULL, NULL, 8, 1),
(9, 'Professor I', 'PROF1', 'Teaching', NULL, NULL, NULL, NULL, 9, 1),
(10, 'Professor II', 'PROF2', 'Teaching', NULL, NULL, NULL, NULL, 10, 1),
(11, 'Professor III', 'PROF3', 'Teaching', NULL, NULL, NULL, NULL, 11, 1),
(12, 'Master Teacher I', 'MT1', 'Teaching', NULL, NULL, NULL, NULL, 12, 1),
(13, 'Master Teacher II', 'MT2', 'Teaching', NULL, NULL, NULL, NULL, 13, 1),
(14, 'Staff I', 'STA1', 'Non-Teaching', NULL, NULL, NULL, NULL, 1, 1),
(15, 'Staff II', 'STA2', 'Non-Teaching', NULL, NULL, NULL, NULL, 2, 1),
(16, 'Staff III', 'STA3', 'Non-Teaching', NULL, NULL, NULL, NULL, 3, 1),
(17, 'Senior Staff', 'SSTA', 'Non-Teaching', NULL, NULL, NULL, NULL, 4, 1),
(18, 'Supervisor', 'SUP', 'Non-Teaching', NULL, NULL, NULL, NULL, 5, 1),
(19, 'Department Head', 'DH', 'Non-Teaching', NULL, NULL, NULL, NULL, 6, 1),
(20, 'Officer', 'OFR', 'Non-Teaching', NULL, NULL, NULL, NULL, 7, 1),
(21, 'Senior Officer', 'SOFR', 'Non-Teaching', NULL, NULL, NULL, NULL, 8, 1),
(22, 'Vice President', 'VP', 'Both', NULL, NULL, NULL, NULL, 1, 1),
(23, 'President', 'PRES', 'Both', NULL, NULL, NULL, NULL, 2, 1),
(24, 'Director', 'DIR', 'Both', NULL, NULL, NULL, NULL, 3, 1),
(25, 'Board Member', 'BOD', 'Both', NULL, NULL, NULL, NULL, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `employee_requests`
--

CREATE TABLE `employee_requests` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(30) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `request_type_id` int(11) NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `days_applied` decimal(5,2) DEFAULT NULL,
  `time_from` time DEFAULT NULL,
  `time_to` time DEFAULT NULL,
  `purpose` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','In Progress','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `current_step` int(5) DEFAULT '1',
  `can_proceed` tinyint(1) DEFAULT '0' COMMENT 'Set to 1 once can_proceed_after_this step is passed',
  `employee_classification` enum('Teaching','Non-Teaching') DEFAULT 'Non-Teaching',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_action_at` datetime DEFAULT NULL COMMENT 'Timestamp of the most recent action on this request',
  `last_action_by` int(11) DEFAULT NULL COMMENT 'users.id who last acted',
  `credits_deducted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = leave credits already deducted, prevents double deduction'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employee_requests`
--

INSERT INTO `employee_requests` (`id`, `reference_no`, `employee_id`, `request_type_id`, `date_from`, `date_to`, `days_applied`, `time_from`, `time_to`, `purpose`, `attachment_path`, `status`, `current_step`, `can_proceed`, `employee_classification`, `created_at`, `updated_at`, `last_action_at`, `last_action_by`, `credits_deducted`) VALUES
(1, 'REQ-20260428-6471', 7, 1, '2026-05-04', '2026-05-08', '5.00', NULL, NULL, 'Eh basta mag file aq, bakit ksi', NULL, 'Approved', 5, 1, 'Non-Teaching', '2026-04-28 05:24:31', '2026-04-29 00:25:54', '2026-04-29 08:25:54', 15, 0),
(2, 'REQ-20260506-4644', 7, 2, '2026-05-04', '2026-05-05', '2.00', NULL, NULL, 'Migraine', NULL, 'Cancelled', 1, 0, 'Non-Teaching', '2026-05-06 03:12:15', '2026-05-06 04:03:39', NULL, NULL, 0),
(3, 'REQ-20260506-2690', 7, 2, '2026-05-04', '2026-05-05', '2.00', NULL, NULL, 'migraine!!', NULL, 'In Progress', 1, 0, 'Non-Teaching', '2026-05-06 04:05:55', '2026-05-06 12:47:55', NULL, NULL, 0),
(4, 'REQ-20260506-3152', 7, 11, NULL, NULL, NULL, NULL, NULL, 'para sa masa', NULL, 'Pending', 1, 0, 'Non-Teaching', '2026-05-06 12:49:10', '2026-05-06 12:49:10', NULL, NULL, 0),
(5, 'REQ-20260506-1578', 7, 5, '2026-05-06', '2026-05-13', NULL, NULL, NULL, 'dahil manganganak butiki', NULL, 'In Progress', 1, 0, 'Non-Teaching', '2026-05-06 12:52:42', '2026-05-06 12:52:42', NULL, NULL, 0),
(6, 'REQ-20260506-6044', 7, 1, '2026-05-08', '2026-05-11', NULL, NULL, NULL, 'asdasdsada', NULL, 'Pending', 1, 0, 'Non-Teaching', '2026-05-06 21:45:00', '2026-05-06 21:46:02', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `employee_self_updates`
--

CREATE TABLE `employee_self_updates` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `submitted_data` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_shifts`
--

CREATE TABLE `employee_shifts` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_transfers`
--

CREATE TABLE `employee_transfers` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `transfer_type` enum('Transfer','Promotion','Demotion','Reclassification') DEFAULT 'Transfer',
  `from_department_id` int(11) DEFAULT NULL,
  `to_department_id` int(11) DEFAULT NULL,
  `from_position_id` int(11) DEFAULT NULL,
  `to_position_id` int(11) DEFAULT NULL,
  `from_rank` varchar(100) DEFAULT NULL,
  `to_rank` varchar(100) DEFAULT NULL,
  `from_salary` decimal(12,2) DEFAULT NULL,
  `to_salary` decimal(12,2) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `reason` text,
  `endorsed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employment_statuses`
--

CREATE TABLE `employment_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `is_active_status` tinyint(1) DEFAULT '1',
  `color` varchar(20) DEFAULT 'secondary',
  `sort_order` int(5) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employment_statuses`
--

INSERT INTO `employment_statuses` (`id`, `name`, `code`, `description`, `is_active_status`, `color`, `sort_order`, `is_active`) VALUES
(1, 'Active', 'ACT', 'Currently reporting for work', 1, 'success', 1, 1),
(2, 'On Leave', 'OL', 'On approved leave of absence', 1, 'info', 2, 1),
(3, 'Suspended', 'SUS', 'Under disciplinary suspension', 0, 'warning', 3, 1),
(4, 'Inactive', 'INA', 'Not currently working, not yet separated', 0, 'secondary', 4, 1),
(5, 'Resigned', 'RES', 'Voluntarily separated', 0, 'warning', 5, 1),
(6, 'Terminated', 'TER', 'Involuntarily separated', 0, 'danger', 6, 1),
(7, 'Retired', 'RET', 'Separated by retirement', 0, 'dark', 7, 1),
(8, 'Deceased', 'DEC', 'Employee has passed away', 0, 'dark', 8, 1),
(9, 'End of Contract', 'EOC', 'Contract ended and not renewed', 0, 'secondary', 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `employment_types`
--

CREATE TABLE `employment_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `probation_months` int(5) DEFAULT NULL,
  `sort_order` int(5) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `employment_types`
--

INSERT INTO `employment_types` (`id`, `name`, `code`, `description`, `probation_months`, `sort_order`, `is_active`) VALUES
(1, 'Regular/Permanent', 'REG', 'Passed probation, full employment rights', NULL, 1, 1),
(2, 'Probationary', 'PROB', 'On trial period per Labor Code', 6, 2, 1),
(3, 'Contractual', 'CON', 'Fixed term contract, auto-terminates at end', NULL, 3, 1),
(4, 'Substitute', 'SUB', 'Replacing employee on approved leave', NULL, 4, 1),
(5, 'Part-time', 'PT', 'Less than full working hours', NULL, 5, 1),
(6, 'Project-based', 'PROJ', 'Duration tied to specific project', NULL, 6, 1),
(7, 'Casual', 'CAS', 'Day-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed termDay-to-day basis, no fixed term', NULL, 7, 1);

-- --------------------------------------------------------

--
-- Table structure for table `flex_schedules`
--

CREATE TABLE `flex_schedules` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `schedule_type` enum('Fixed','Flexible','Compressed','Remote') DEFAULT 'Fixed',
  `core_start` time DEFAULT '09:00:00',
  `core_end` time DEFAULT '15:00:00',
  `flex_start_earliest` time DEFAULT '07:00:00',
  `flex_start_latest` time DEFAULT '10:00:00',
  `required_hours` decimal(4,2) DEFAULT '8.00',
  `work_days` varchar(50) DEFAULT 'Mon-Fri',
  `effective_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `grievances`
--

CREATE TABLE `grievances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `category` enum('Work environment','Compensation','Management','Harassment','Discrimination','Other') DEFAULT 'Other',
  `status` enum('Filed','Under review','Resolved','Closed') DEFAULT 'Filed',
  `resolution` text,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `holiday_date` date NOT NULL,
  `type` varchar(30) DEFAULT 'Regular holiday',
  `year` year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `name`, `holiday_date`, `type`, `year`) VALUES
(1, 'New Year\'s Day', '2025-01-01', 'Regular holiday', 2025),
(2, 'Araw ng Kagitingan', '2025-04-09', 'Regular holiday', 2025),
(3, 'Maundy Thursday', '2025-04-17', 'Regular holiday', 2025),
(4, 'Good Friday', '2025-04-18', 'Regular holiday', 2025),
(5, 'Labor Day', '2025-05-01', 'Regular holiday', 2025),
(6, 'Independence Day', '2025-06-12', 'Regular holiday', 2025),
(7, 'National Heroes Day', '2025-08-25', 'Regular holiday', 2025),
(8, 'Bonifacio Day', '2025-11-30', 'Regular holiday', 2025),
(9, 'Christmas Day', '2025-12-25', 'Regular holiday', 2025),
(10, 'Rizal Day', '2025-12-30', 'Regular holiday', 2025),
(11, 'All Saints Day', '2025-11-01', 'Special non-working', 2025),
(12, 'Christmas Eve', '2025-12-24', 'Special non-working', 2025),
(13, 'Last Day of the Year', '2025-12-31', 'Special non-working', 2025);

-- --------------------------------------------------------

--
-- Table structure for table `job_openings`
--

CREATE TABLE `job_openings` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `slots` int(5) DEFAULT '1',
  `description` text,
  `requirements` text,
  `status` enum('Open','Closed','On hold') DEFAULT 'Open',
  `date_opened` date DEFAULT NULL,
  `date_closed` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `job_openings`
--

INSERT INTO `job_openings` (`id`, `title`, `department_id`, `position_id`, `slots`, `description`, `requirements`, `status`, `date_opened`, `date_closed`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Computer Technician 1', NULL, 4, 1, '', '', 'Open', '2026-04-11', NULL, 1, '2026-04-11 14:54:38', '2026-04-11 14:54:38');

-- --------------------------------------------------------

--
-- Table structure for table `leave_credits`
--

CREATE TABLE `leave_credits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `year` year(4) NOT NULL DEFAULT '2025',
  `vacation_leave` decimal(5,2) DEFAULT '15.00',
  `sick_leave` decimal(5,2) DEFAULT '15.00',
  `emergency_leave` decimal(5,2) DEFAULT '3.00',
  `maternity_leave` decimal(5,2) DEFAULT '105.00',
  `paternity_leave` decimal(5,2) DEFAULT '7.00',
  `solo_parent_leave` decimal(5,2) DEFAULT '7.00',
  `vacation_used` decimal(5,2) DEFAULT '0.00',
  `sick_used` decimal(5,2) DEFAULT '0.00',
  `emergency_used` decimal(5,2) DEFAULT '0.00',
  `maternity_used` decimal(5,2) DEFAULT '0.00',
  `paternity_used` decimal(5,2) DEFAULT '0.00',
  `solo_parent_used` decimal(5,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `leave_credits`
--

INSERT INTO `leave_credits` (`id`, `employee_id`, `year`, `vacation_leave`, `sick_leave`, `emergency_leave`, `maternity_leave`, `paternity_leave`, `solo_parent_leave`, `vacation_used`, `sick_used`, `emergency_used`, `maternity_used`, `paternity_used`, `solo_parent_used`, `created_at`, `updated_at`) VALUES
(3, 3, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-22 09:58:03', '2026-04-22 09:58:03'),
(4, 4, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-26 08:27:58', '2026-04-26 08:27:58'),
(5, 5, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-27 05:59:31', '2026-04-27 05:59:31'),
(6, 6, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-27 08:15:35', '2026-04-27 08:15:35'),
(7, 7, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '5.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-27 13:38:51', '2026-04-29 00:25:54'),
(8, 8, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-28 05:15:10', '2026-04-28 05:15:10'),
(9, 9, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-28 12:04:28', '2026-04-28 12:04:28'),
(10, 10, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-28 12:07:15', '2026-04-28 12:07:15'),
(11, 11, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-28 12:08:22', '2026-04-28 12:08:22'),
(12, 12, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-04-28 18:50:48', '2026-04-28 18:50:48'),
(13, 13, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-05-02 06:35:43', '2026-05-02 06:35:43'),
(14, 14, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-05-05 04:07:21', '2026-05-05 04:07:21'),
(15, 15, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-05-05 04:09:12', '2026-05-05 04:09:12'),
(16, 16, 2026, '15.00', '15.00', '3.00', '105.00', '7.00', '7.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2026-05-05 04:13:32', '2026-05-05 04:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('Vacation leave','Sick leave','Emergency leave','Maternity leave','Paternity leave','Solo parent leave') NOT NULL,
  `leave_type_id` int(11) DEFAULT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `days_applied` decimal(4,1) NOT NULL DEFAULT '1.0',
  `reason` text,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type`, `leave_type_id`, `date_from`, `date_to`, `days_applied`, `reason`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 7, 'Vacation leave', NULL, '2026-05-04', '2026-05-08', '5.0', 'Eh basta mag file aq, bakit ksi', 'Approved', 15, '2026-04-29 00:25:54', '2026-04-29 00:25:54');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `days_per_year` decimal(5,2) DEFAULT '15.00',
  `is_paid` tinyint(1) DEFAULT '1',
  `requires_document` tinyint(1) DEFAULT '0',
  `gender_restriction` enum('All','Male','Female') DEFAULT 'All',
  `employment_type` varchar(100) DEFAULT 'All',
  `min_tenure_months` int(5) DEFAULT '0',
  `carry_forward` tinyint(1) DEFAULT '0',
  `max_carry_days` decimal(5,2) DEFAULT '0.00',
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `code`, `days_per_year`, `is_paid`, `requires_document`, `gender_restriction`, `employment_type`, `min_tenure_months`, `carry_forward`, `max_carry_days`, `description`, `is_active`, `created_at`) VALUES
(1, 'Vacation leave', 'VL', '15.00', 1, 0, 'All', 'All', 0, 1, '0.00', 'Annual vacation leave for all employees', 1, '2026-04-14 09:44:00'),
(2, 'Sick leave', 'SL', '15.00', 1, 1, 'All', 'All', 0, 0, '0.00', 'Medical / illness leave requiring medical certificate', 1, '2026-04-14 09:44:00'),
(3, 'Emergency leave', 'EL', '3.00', 1, 0, 'All', 'All', 0, 0, '0.00', 'Emergency leave for immediate family', 1, '2026-04-14 09:44:00'),
(4, 'Maternity leave', 'ML', '105.00', 1, 1, 'Female', 'All', 0, 0, '0.00', 'RA 11210 — 105 days maternity leave', 1, '2026-04-14 09:44:00'),
(5, 'Paternity leave', 'PL', '7.00', 1, 0, 'Male', 'Regular', 0, 0, '0.00', 'RA 8187 — 7 days paternity leave', 1, '2026-04-14 09:44:00'),
(6, 'Solo parent leave', 'SPL', '7.00', 1, 1, 'All', 'Regular', 0, 0, '0.00', 'RA 8972 — 7 days for solo parents', 1, '2026-04-14 09:44:00'),
(7, 'Bereavement leave', 'BL', '5.00', 1, 1, 'All', 'All', 0, 0, '0.00', 'Death of immediate family member', 1, '2026-04-14 09:44:00'),
(8, 'Study leave', 'STL', '5.00', 0, 1, 'All', 'Regular', 12, 0, '0.00', 'For approved academic programs', 1, '2026-04-14 09:44:00'),
(9, 'Offsetting leave', 'OL', '0.00', 1, 0, 'All', 'All', 0, 0, '0.00', 'Leave earned from overtime/offsetting', 1, '2026-04-14 09:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type` varchar(50) DEFAULT 'Personal loan',
  `amount` decimal(12,2) NOT NULL,
  `monthly_amortization` decimal(10,2) DEFAULT NULL,
  `total_months` int(5) DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT '0.00',
  `balance` decimal(12,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `date_released` date DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `payroll_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT '0',
  `reason` varchar(50) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `success`, `reason`, `attempted_at`) VALUES
(71, 'mij25', '192.168.68.77', 1, 'SUCCESS', '2026-04-21 03:34:07'),
(80, 'hradmin', '192.168.43.1', 1, 'SUCCESS', '2026-04-22 21:34:31'),
(88, 'hradmin', '192.168.68.114', 1, 'SUCCESS', '2026-04-23 06:10:32'),
(357, 'jerome', '::1', 1, 'SUCCESS', '2026-05-07 05:24:30');

-- --------------------------------------------------------

--
-- Table structure for table `mobile_attendance_log`
--

CREATE TABLE `mobile_attendance_log` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `action` enum('Clock In','Clock Out') NOT NULL,
  `log_datetime` datetime NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `accuracy_meters` decimal(8,2) DEFAULT NULL,
  `address_text` varchar(255) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `linked_attendance_id` int(11) DEFAULT NULL COMMENT 'Links to attendance table',
  `status` enum('Valid','Flagged','Rejected') DEFAULT 'Valid',
  `flag_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `mobile_attendance_log`
--

INSERT INTO `mobile_attendance_log` (`id`, `employee_id`, `action`, `log_datetime`, `photo_path`, `latitude`, `longitude`, `accuracy_meters`, `address_text`, `device_info`, `ip_address`, `linked_attendance_id`, `status`, `flag_reason`, `created_at`) VALUES
(1, 3, 'Clock In', '2026-04-25 07:08:49', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 1, 'Valid', NULL, '2026-04-25 16:09:24'),
(2, 3, 'Clock Out', '2026-04-25 07:27:36', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 1, 'Valid', NULL, '2026-04-25 16:28:11'),
(3, 3, 'Clock In', '2026-04-26 09:07:04', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 2, 'Valid', NULL, '2026-04-26 01:07:04'),
(4, 3, 'Clock Out', '2026-04-26 12:28:52', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 2, 'Valid', NULL, '2026-04-26 04:28:52'),
(5, 3, 'Clock In', '2026-04-26 12:29:15', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 2, 'Valid', NULL, '2026-04-26 04:29:15'),
(6, 3, 'Clock In', '2026-04-27 13:40:25', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 3, 'Valid', NULL, '2026-04-27 05:40:25'),
(7, 3, 'Clock Out', '2026-04-27 13:50:25', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 3, 'Valid', NULL, '2026-04-27 05:50:25'),
(8, 3, 'Clock In', '2026-04-27 13:51:10', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 3, 'Valid', NULL, '2026-04-27 05:51:10'),
(9, 3, 'Clock Out', '2026-04-27 17:20:05', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 3, 'Valid', NULL, '2026-04-27 09:20:05'),
(10, 3, 'Clock In', '2026-04-28 13:11:07', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 4, 'Valid', NULL, '2026-04-28 05:11:07'),
(11, 3, 'Clock Out', '2026-04-28 13:11:15', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 4, 'Valid', NULL, '2026-04-28 05:11:15'),
(12, 3, 'Clock In', '2026-04-28 19:50:33', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 4, 'Valid', NULL, '2026-04-28 11:50:34'),
(13, 3, 'Clock Out', '2026-04-28 19:50:41', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 4, 'Valid', NULL, '2026-04-28 11:50:41'),
(14, 9, 'Clock In', '2026-04-29 08:35:05', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 5, 'Valid', NULL, '2026-04-29 00:35:05'),
(15, 3, 'Clock In', '2026-04-29 08:35:21', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 6, 'Valid', NULL, '2026-04-29 00:35:21'),
(16, 7, 'Clock In', '2026-05-07 13:31:21', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 7, 'Valid', NULL, '2026-05-07 05:31:21'),
(17, 7, 'Clock Out', '2026-05-07 13:31:25', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 7, 'Valid', NULL, '2026-05-07 05:31:25'),
(18, 7, 'Clock In', '2026-05-07 13:31:29', NULL, NULL, NULL, NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '::1', 7, 'Valid', NULL, '2026-05-07 05:31:29');

-- --------------------------------------------------------

--
-- Table structure for table `offsetting`
--

CREATE TABLE `offsetting` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `offset_date` date NOT NULL,
  `offset_hours` decimal(4,2) NOT NULL,
  `reason` text,
  `target_date` date DEFAULT NULL,
  `target_use` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Used','Cancelled') DEFAULT 'Pending',
  `hours_remaining` decimal(4,2) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_tasks`
--

CREATE TABLE `onboarding_tasks` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `task_description` text,
  `responsible` varchar(20) DEFAULT 'HR',
  `due_days` int(5) DEFAULT '1',
  `sort_order` int(5) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `onboarding_tasks`
--

INSERT INTO `onboarding_tasks` (`id`, `template_id`, `task_name`, `task_description`, `responsible`, `due_days`, `sort_order`) VALUES
(1, 1, 'Submit pre-employment requirements', NULL, 'Employee', 1, 1),
(2, 1, 'Sign employment contract', NULL, 'HR', 1, 2),
(3, 1, 'Setup company email account', NULL, 'IT', 1, 3),
(4, 1, 'Issue laptop / equipment', NULL, 'IT', 1, 4),
(5, 1, 'Enroll in SSS, PhilHealth, Pag-IBIG', NULL, 'HR', 3, 5),
(6, 1, 'Complete company orientation', NULL, 'HR', 5, 6),
(7, 1, 'Meet team and immediate supervisor', NULL, 'Manager', 2, 7),
(8, 1, 'Complete IT security training', NULL, 'IT', 7, 8),
(9, 1, 'Set probationary period goals', NULL, 'Manager', 7, 9),
(10, 1, 'Issue company ID', NULL, 'HR', 5, 10);

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_templates`
--

CREATE TABLE `onboarding_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `onboarding_templates`
--

INSERT INTO `onboarding_templates` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Standard new hire onboarding', 'Default checklist for all new employees', 1, '2026-04-12 01:37:35');

-- --------------------------------------------------------

--
-- Table structure for table `org_levels`
--

CREATE TABLE `org_levels` (
  `id` int(11) NOT NULL,
  `level_name` varchar(100) NOT NULL,
  `level_order` int(5) DEFAULT '0',
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `org_levels`
--

INSERT INTO `org_levels` (`id`, `level_name`, `level_order`, `description`) VALUES
(1, 'Board of Directors', 1, NULL),
(2, 'President', 2, NULL),
(3, 'Vice President', 3, NULL),
(4, 'Academic Head', 4, NULL),
(5, 'Non-Academic Head', 5, NULL),
(6, 'Department Head', 6, NULL),
(7, 'Faculty', 7, NULL),
(8, 'Staff', 8, NULL),
(9, 'Employee', 9, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `basic_salary` decimal(12,2) DEFAULT '0.00',
  `days_worked` decimal(5,2) DEFAULT '0.00',
  `overtime_pay` decimal(12,2) DEFAULT '0.00',
  `allowances` decimal(12,2) DEFAULT '0.00',
  `gross_pay` decimal(12,2) DEFAULT '0.00',
  `sss_contribution` decimal(10,2) DEFAULT '0.00',
  `philhealth_contribution` decimal(10,2) DEFAULT '0.00',
  `pagibig_contribution` decimal(10,2) DEFAULT '0.00',
  `withholding_tax` decimal(10,2) DEFAULT '0.00',
  `other_deductions` decimal(10,2) DEFAULT '0.00',
  `total_deductions` decimal(10,2) DEFAULT '0.00',
  `net_pay` decimal(12,2) DEFAULT '0.00',
  `status` enum('Draft','Released') DEFAULT 'Draft',
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `job_level` varchar(50) DEFAULT NULL,
  `salary_grade` varchar(50) DEFAULT NULL,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employment_type` enum('Regular','Probationary','Contractual','Part-time','Project-based') DEFAULT 'Regular',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `title`, `job_level`, `salary_grade`, `salary_min`, `salary_max`, `department_id`, `employment_type`, `created_at`) VALUES
(1, 'HR Manager', 'Manager', 'SG-14', NULL, NULL, NULL, 'Regular', '2026-04-10 09:55:56'),
(2, 'HR Officer', 'Supervisor', 'SG-11', NULL, NULL, NULL, 'Regular', '2026-04-10 09:55:56'),
(3, 'Software Developer', 'Staff', 'SG-12', NULL, NULL, NULL, 'Regular', '2026-04-10 09:55:56'),
(4, 'IT Manager', 'Manager', 'SG-14', NULL, NULL, NULL, 'Regular', '2026-04-10 09:55:56'),
(5, 'Instructor', 'Faculty', '', '0.00', '0.00', 4, 'Regular', '2026-04-26 08:25:25'),
(6, 'Clerk', 'Staff', '', '0.00', '0.00', 4, 'Regular', '2026-04-27 13:02:18'),
(7, 'Dean', 'Manager', '', '0.00', '0.00', 4, 'Regular', '2026-04-27 13:03:11'),
(8, 'Medical Assistant', 'Staff', '', '0.00', '0.00', 6, 'Regular', '2026-04-27 13:13:37'),
(9, 'Vice President for Academic Affairs', 'VP', '', '0.00', '0.00', NULL, 'Regular', '2026-04-27 13:14:11'),
(10, 'Excutive Assistant', 'Staff', '', '0.00', '0.00', NULL, 'Regular', '2026-04-28 05:13:50'),
(11, 'Vice President for Administration', 'Staff', '', '0.00', '0.00', 8, 'Regular', '2026-04-28 12:05:02'),
(12, 'Vice President for Academic Affairs', 'Staff', '', '0.00', '0.00', 7, 'Regular', '2026-04-28 12:05:43'),
(13, 'President', 'Staff', '', '0.00', '0.00', 9, 'Regular', '2026-04-28 12:06:02'),
(14, 'Head', 'Staff', '', '0.00', '0.00', 10, 'Regular', '2026-05-02 06:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `profile_edit_requests`
--

CREATE TABLE `profile_edit_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text,
  `new_value` text,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `request_approvals`
--

CREATE TABLE `request_approvals` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `step_order` int(5) NOT NULL,
  `role_required` varchar(50) NOT NULL,
  `step_label` varchar(100) NOT NULL,
  `action_label` varchar(50) DEFAULT 'Approve',
  `can_proceed_after_this` tinyint(1) DEFAULT '0',
  `status` enum('Pending','Approved','Rejected','Noted','Skipped') DEFAULT 'Pending',
  `acted_by` int(11) DEFAULT NULL COMMENT 'user id',
  `acted_at` timestamp NULL DEFAULT NULL,
  `remarks` text,
  `digital_signed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = digitally signed by the approver',
  `signed_by` int(11) DEFAULT NULL COMMENT 'users.id of who signed',
  `signed_at` datetime DEFAULT NULL COMMENT 'Exact timestamp of digital signature',
  `signed_name` varchar(150) DEFAULT NULL COMMENT 'Full name at time of signing (denormalized for audit)',
  `triggers_logbook` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = reaching this step auto-creates a logbook incoming entry',
  `logbook_office` varchar(50) DEFAULT NULL COMMENT 'Which exec office logbook this triggers (VP Academic, VP Administration, President)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `request_status_log`
--

CREATE TABLE `request_status_log` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL COMMENT 'user_id who triggered the change',
  `prev_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `remarks` text,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `request_types`
--

CREATE TABLE `request_types` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('Leave','Document','Profile Change','Overtime','Other') DEFAULT 'Other',
  `requires_days` tinyint(1) DEFAULT '0',
  `requires_time` tinyint(1) DEFAULT '0',
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(5) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `request_types`
--

INSERT INTO `request_types` (`id`, `name`, `category`, `requires_days`, `requires_time`, `description`, `is_active`, `sort_order`) VALUES
(1, 'Vacation Leave', 'Leave', 1, 0, NULL, 1, 1),
(2, 'Sick Leave', 'Leave', 1, 0, NULL, 1, 2),
(3, 'Emergency Leave', 'Leave', 1, 0, NULL, 1, 3),
(4, 'Maternity Leave', 'Leave', 1, 0, NULL, 1, 4),
(5, 'Paternity Leave', 'Leave', 1, 0, NULL, 1, 5),
(6, 'Solo Parent Leave', 'Leave', 1, 0, NULL, 1, 6),
(7, 'Birthday Leave', 'Leave', 1, 0, NULL, 1, 7),
(8, 'Application for 30+ Days Vacation', 'Leave', 1, 0, NULL, 1, 8),
(9, 'Emergency Leave of Absence', 'Leave', 1, 0, NULL, 1, 9),
(10, 'Leave Due to Death of Kin', 'Leave', 1, 0, NULL, 1, 10),
(11, 'Certificate of Employment (COE)', 'Document', 0, 0, NULL, 1, 11),
(12, 'Overtime Authorization', 'Overtime', 0, 1, NULL, 1, 12),
(13, 'Profile/Details Change', 'Profile Change', 0, 0, NULL, 1, 13),
(14, 'Official Travel/Business Trip', 'Other', 1, 0, NULL, 1, 14),
(15, 'Other Request', 'Other', 0, 0, NULL, 1, 15),
(16, 'Vacation Leave', 'Leave', 0, 0, NULL, 1, 1),
(17, 'Sick Leave', 'Leave', 0, 0, NULL, 1, 2),
(18, 'Emergency Leave', 'Leave', 0, 0, NULL, 1, 3),
(19, 'Maternity Leave', 'Leave', 0, 0, NULL, 1, 4),
(20, 'Paternity Leave', 'Leave', 0, 0, NULL, 1, 5),
(21, 'Solo Parent Leave', 'Leave', 0, 0, NULL, 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `salary_grades`
--

CREATE TABLE `salary_grades` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `monthly_rate` decimal(12,2) DEFAULT NULL,
  `description` text,
  `category` enum('Teaching','Non-Teaching','Both') DEFAULT 'Both',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(5) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `salary_grades`
--

INSERT INTO `salary_grades` (`id`, `name`, `code`, `monthly_rate`, `description`, `category`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'SG-1', 'SG1', NULL, NULL, 'Both', 1, 1, '2026-04-18 16:33:08'),
(2, 'SG-2', 'SG2', NULL, NULL, 'Both', 1, 2, '2026-04-18 16:33:08'),
(3, 'SG-3', 'SG3', NULL, NULL, 'Both', 1, 3, '2026-04-18 16:33:08'),
(4, 'SG-4', 'SG4', NULL, NULL, 'Both', 1, 4, '2026-04-18 16:33:08'),
(5, 'SG-5', 'SG5', NULL, NULL, 'Both', 1, 5, '2026-04-18 16:33:08'),
(6, 'SG-6', 'SG6', NULL, NULL, 'Both', 1, 6, '2026-04-18 16:33:08'),
(7, 'SG-7', 'SG7', NULL, NULL, 'Both', 1, 7, '2026-04-18 16:33:08'),
(8, 'SG-8', 'SG8', NULL, NULL, 'Both', 1, 8, '2026-04-18 16:33:08'),
(9, 'SG-9', 'SG9', NULL, NULL, 'Both', 1, 9, '2026-04-18 16:33:08'),
(10, 'SG-10', 'SG10', NULL, NULL, 'Both', 1, 10, '2026-04-18 16:33:08'),
(11, 'SG-11', 'SG11', NULL, NULL, 'Both', 1, 11, '2026-04-18 16:33:08'),
(12, 'SG-12', 'SG12', NULL, NULL, 'Both', 1, 12, '2026-04-18 16:33:08'),
(13, 'SG-13', 'SG13', NULL, NULL, 'Both', 1, 13, '2026-04-18 16:33:08'),
(14, 'SG-14', 'SG14', NULL, NULL, 'Both', 1, 14, '2026-04-18 16:33:08'),
(15, 'SG-15', 'SG15', NULL, NULL, 'Both', 1, 15, '2026-04-18 16:33:08'),
(16, 'SG-16', 'SG16', NULL, NULL, 'Both', 1, 16, '2026-04-18 16:33:08'),
(17, 'SG-17', 'SG17', NULL, NULL, 'Both', 1, 17, '2026-04-18 16:33:08'),
(18, 'SG-18', 'SG18', NULL, NULL, 'Both', 1, 18, '2026-04-18 16:33:08'),
(19, 'SG-19', 'SG19', NULL, NULL, 'Both', 1, 19, '2026-04-18 16:33:08'),
(20, 'SG-20', 'SG20', NULL, NULL, 'Both', 1, 20, '2026-04-18 16:33:08');

-- --------------------------------------------------------

--
-- Table structure for table `separation_records`
--

CREATE TABLE `separation_records` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `separation_type` varchar(50) DEFAULT 'Resignation',
  `last_day_worked` date DEFAULT NULL,
  `unpaid_salary` decimal(12,2) DEFAULT '0.00',
  `unused_leave_days` decimal(5,2) DEFAULT '0.00',
  `leave_conversion` decimal(12,2) DEFAULT '0.00',
  `thirteenth_prorated` decimal(12,2) DEFAULT '0.00',
  `separation_pay` decimal(12,2) DEFAULT '0.00',
  `other_pay` decimal(12,2) DEFAULT '0.00',
  `total_final_pay` decimal(12,2) DEFAULT '0.00',
  `status` varchar(20) DEFAULT 'Draft',
  `processed_by` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_group` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `updated_at`) VALUES
(1, 'company_name', 'Saint Ferdinand College', 'company', '2026-04-14 08:16:08'),
(2, 'company_address', 'Centro, Cabagan, Isabela', 'company', '2026-04-14 08:16:54'),
(3, 'company_phone', '', 'company', '2026-04-12 01:37:34'),
(4, 'company_email', '', 'company', '2026-04-12 01:37:34'),
(5, 'company_logo', 'logo_1776154568.jpg', 'company', '2026-04-14 08:16:08'),
(6, 'payroll_cutoff_1', '15', 'payroll', '2026-04-12 01:37:34'),
(7, 'payroll_cutoff_2', '30', 'payroll', '2026-04-12 01:37:34'),
(8, 'working_days_month', '22', 'payroll', '2026-04-12 01:37:34'),
(9, 'working_hours_day', '8', 'payroll', '2026-04-12 01:37:34'),
(10, 'overtime_rate', '1.25', 'payroll', '2026-04-12 01:37:34'),
(11, 'night_diff_rate', '1.10', 'payroll', '2026-04-12 01:37:34'),
(12, 'rest_day_rate', '1.30', 'payroll', '2026-04-12 01:37:34'),
(13, 'holiday_rate', '2.00', 'payroll', '2026-04-12 01:37:34'),
(14, 'special_holiday_rate', '1.30', 'payroll', '2026-04-12 01:37:34'),
(15, 'session_timeout', '1800', 'security', '2026-04-12 01:37:34'),
(16, 'max_login_attempts', '5', 'security', '2026-04-12 01:37:34'),
(17, 'lockout_minutes', '15', 'security', '2026-04-12 01:37:34'),
(18, 'smtp_host', '', 'email', '2026-04-12 01:37:34'),
(19, 'smtp_port', '587', 'email', '2026-04-12 01:37:34'),
(20, 'smtp_username', '', 'email', '2026-04-12 01:37:34'),
(21, 'smtp_password', '', 'email', '2026-04-12 01:37:34'),
(22, 'smtp_from_name', 'HRMS', 'email', '2026-04-12 01:37:34'),
(23, 'smtp_from_email', '', 'email', '2026-04-12 01:37:34'),
(24, 'emp_id_prefix', 'EMP', 'general', '2026-04-15 07:55:26'),
(25, 'emp_id_digits', '4', 'general', '2026-04-15 07:55:26'),
(26, 'emp_id_by_dept', '0', 'general', '2026-04-15 07:55:26'),
(27, 'grace_minutes', '15', 'hr_setup', '2026-04-15 09:08:32'),
(28, 'shift_start_default', '08:00', 'hr_setup', '2026-04-25 15:16:48'),
(29, 'shift_start_non_teaching', '08:00:00', 'hr_setup', '2026-04-24 15:01:05'),
(30, 'shift_end_non_teaching', '17:00', 'hr_setup', '2026-04-25 15:16:48'),
(31, 'lunch_start', '12:00', 'hr_setup', '2026-04-25 15:16:48'),
(32, 'lunch_end', '13:00', 'hr_setup', '2026-04-25 15:16:48'),
(33, 'pm_grace_minutes', '0', 'hr_setup', '2026-04-24 15:01:05'),
(34, 'undertime_threshold_mins', '0', 'hr_setup', '2026-04-24 15:01:05'),
(35, 'shift_start_basic_ed', '07:00', 'hr_setup', '2026-04-25 15:16:48'),
(36, 'shift_end_basic_ed', '16:00', 'hr_setup', '2026-04-25 15:16:48'),
(37, 'shift_start_college', '07:00', 'hr_setup', '2026-04-25 15:16:48'),
(38, 'required_hours_daily', '8', 'hr_setup', '2026-04-24 15:01:05'),
(40, 'system_timezone', 'Asia/Manila', 'general', '2026-04-25 16:32:46'),
(41, 'clock_offset_minutes', '0', 'general', '2026-04-24 15:07:00'),
(43, 'shift_end_default', '17:00', 'general', '2026-04-24 15:07:00'),
(45, 'break_minutes', '60', 'general', '2026-04-24 15:07:00'),
(46, 'ot_threshold_minutes', '0', 'general', '2026-04-24 15:07:00'),
(75, 'clock_delta_seconds', '0', 'general', '2026-04-25 16:33:02'),
(76, 'current_school_year', '2025-2026', 'general', '2026-04-26 07:48:27'),
(77, 'current_semester', '1st Semester', 'general', '2026-04-26 07:48:27');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `time_in` time NOT NULL,
  `time_out` time NOT NULL,
  `break_minutes` int(5) DEFAULT '60',
  `work_days` varchar(50) DEFAULT 'Mon-Fri',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `name`, `time_in`, `time_out`, `break_minutes`, `work_days`, `created_at`) VALUES
(1, 'Morning shift', '08:00:00', '17:00:00', 60, 'Mon-Fri', '2026-04-11 14:36:20'),
(2, 'Afternoon shift', '14:00:00', '23:00:00', 60, 'Mon-Fri', '2026-04-11 14:36:20'),
(3, 'Night shift', '22:00:00', '06:00:00', 60, 'Mon-Fri', '2026-04-11 14:36:20'),
(4, 'Flexible', '09:00:00', '18:00:00', 60, 'Mon-Fri', '2026-04-11 14:36:20');

-- --------------------------------------------------------

--
-- Table structure for table `sick_leave_approvals`
--

CREATE TABLE `sick_leave_approvals` (
  `id` int(11) NOT NULL,
  `leave_request_id` int(11) NOT NULL,
  `level` tinyint(2) NOT NULL,
  `approver_role` varchar(50) NOT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` text,
  `acted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_loads`
--

CREATE TABLE `teacher_loads` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `section` varchar(100) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `day_of_week` varchar(20) NOT NULL COMMENT 'Mon, Tue, Wed, Thu, Fri, Sat, or combination e.g. Mon,Wed',
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `school_year` varchar(20) DEFAULT NULL COMMENT 'e.g. 2025-2026',
  `semester` enum('1st','2nd','Summer') DEFAULT '1st',
  `level` enum('Basic Education','College','Graduate School') DEFAULT 'College',
  `units` decimal(3,1) DEFAULT NULL,
  `status` enum('Pending','Noted','Recommended','Approved','Rejected') DEFAULT 'Pending',
  `noted_by_id` int(11) DEFAULT NULL,
  `noted_at` timestamp NULL DEFAULT NULL,
  `recommended_by_id` int(11) DEFAULT NULL,
  `recommended_at` timestamp NULL DEFAULT NULL,
  `approved_by_id` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `hr_copy_received` tinyint(1) DEFAULT '0',
  `submitted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `teaching_loads`
--

CREATE TABLE `teaching_loads` (
  `id` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL COMMENT 'e.g. 2025-2026',
  `semester` enum('1st Semester','2nd Semester','Summer') NOT NULL DEFAULT '1st Semester',
  `employee_id` int(11) NOT NULL,
  `subject_title` varchar(200) NOT NULL,
  `subject_code` varchar(50) DEFAULT NULL,
  `student_program` varchar(200) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `day_of_week` varchar(50) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `room` varchar(100) DEFAULT NULL,
  `units` decimal(3,1) DEFAULT '3.0',
  `level` enum('Nursery','Elementary','Junior High','Senior High','College','Graduate School') DEFAULT 'College',
  `has_offset` tinyint(1) DEFAULT '0',
  `offset_time_start` time DEFAULT NULL,
  `offset_time_end` time DEFAULT NULL,
  `offset_notes` text,
  `status` enum('Draft','For Faculty Review','Faculty Confirmed','For Dept Head Review','Dept Head Approved','For VP Review','VP Recommended','For President Approval','Approved','Rejected','Returned') NOT NULL DEFAULT 'Draft',
  `current_step` tinyint(1) NOT NULL DEFAULT '1',
  `rejection_reason` text,
  `returned_to_step` tinyint(1) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_units` decimal(5,1) DEFAULT '0.0' COMMENT 'Sum of all subject units (computed)',
  `total_hours` decimal(5,1) DEFAULT '0.0' COMMENT 'Sum of all subject hours (computed)',
  `total_preps` int(3) DEFAULT '0' COMMENT 'Number of subject preparations',
  `registrar_logbook_id` int(11) DEFAULT NULL COMMENT 'Reserved for future registrar logbook link',
  `vp_logbook_id` int(11) DEFAULT NULL COMMENT 'document_logbook.id for VP Academic incoming entry',
  `pres_logbook_id` int(11) DEFAULT NULL COMMENT 'document_logbook.id for President incoming entry',
  `offset_schedule` varchar(200) DEFAULT NULL COMMENT 'Offset schedule description (non-teaching with load)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `teaching_loads`
--

INSERT INTO `teaching_loads` (`id`, `school_year`, `semester`, `employee_id`, `subject_title`, `subject_code`, `student_program`, `section`, `day_of_week`, `time_start`, `time_end`, `room`, `units`, `level`, `has_offset`, `offset_time_start`, `offset_time_end`, `offset_notes`, `status`, `current_step`, `rejection_reason`, `returned_to_step`, `created_by`, `created_at`, `updated_at`, `total_units`, `total_hours`, `total_preps`, `registrar_logbook_id`, `vp_logbook_id`, `pres_logbook_id`, `offset_schedule`) VALUES
(1, '2025-2026', '1st Semester', 4, 'Introduction to Computing', '', 'BS Information Technology', '1-B', 'Mon/Tue/Wed', '07:00:00', '08:00:00', 'L101', '3.0', 'College', 0, NULL, NULL, NULL, 'For VP Review', 4, NULL, NULL, 7, '2026-04-27 04:45:17', '2026-04-30 05:01:19', '3.0', '0.0', 1, NULL, NULL, NULL, NULL),
(2, '2025-2026', '1st Semester', 4, 'HCI1', '', 'BS Information Technology', '1-B', 'Mon', '08:00:00', '09:00:00', 'GL-1', '3.0', 'College', 0, NULL, NULL, NULL, 'Draft', 1, NULL, NULL, 9, '2026-04-30 15:01:04', '2026-04-30 15:01:04', '6.0', '6.0', 2, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teaching_load_approvals`
--

CREATE TABLE `teaching_load_approvals` (
  `id` int(11) NOT NULL,
  `load_id` int(11) NOT NULL,
  `step` tinyint(1) NOT NULL,
  `step_label` varchar(100) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `action` enum('Confirmed','Noted','Recommended','Approved','Rejected','Returned','HR Received') NOT NULL,
  `remarks` text,
  `acted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `teaching_load_approvals`
--

INSERT INTO `teaching_load_approvals` (`id`, `load_id`, `step`, `step_label`, `actor_user_id`, `actor_name`, `action`, `remarks`, `acted_at`) VALUES
(1, 1, 2, 'Faculty Confirmation', 8, 'ELOUIE TUMOLVA', '', '', '2026-04-29 07:46:27'),
(2, 1, 3, 'Department Head Review', 10, 'monz', 'Noted', '', '2026-04-29 07:53:02');

-- --------------------------------------------------------

--
-- Table structure for table `teaching_load_subjects`
--

CREATE TABLE `teaching_load_subjects` (
  `id` int(11) NOT NULL,
  `load_id` int(11) NOT NULL COMMENT 'FK → teaching_loads.id',
  `course_no` varchar(30) DEFAULT NULL COMMENT 'Course/Subject code',
  `description` varchar(200) NOT NULL COMMENT 'Subject title/description',
  `units` decimal(4,1) NOT NULL DEFAULT '3.0',
  `hours` decimal(4,1) NOT NULL DEFAULT '3.0',
  `no_of_students` int(5) DEFAULT NULL,
  `day_of_week` varchar(50) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `sort_order` int(3) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `teaching_load_subjects`
--

INSERT INTO `teaching_load_subjects` (`id`, `load_id`, `course_no`, `description`, `units`, `hours`, `no_of_students`, `day_of_week`, `time_start`, `time_end`, `room`, `program`, `section`, `level`, `sort_order`, `created_at`) VALUES
(1, 1, '', 'Introduction to Computing', '3.0', '3.0', NULL, 'Mon/Tue/Wed', '07:00:00', '08:00:00', 'L101', 'BS Information Technology', '1-B', 'College', 0, '2026-04-30 13:01:19'),
(2, 1, '', 'Introduction to Computing', '3.0', '3.0', NULL, 'Mon/Tue/Wed', '07:00:00', '08:00:00', 'L101', 'BS Information Technology', '1-B', 'College', 0, '2026-04-30 21:10:26'),
(3, 2, '', 'HCI1', '3.0', '3.0', 20, 'Mon', '08:00:00', '09:00:00', 'GL-1', 'BS Information Technology', '1-B', 'College', 0, '2026-04-30 23:01:04'),
(4, 2, '', 'HCI2', '3.0', '3.0', 20, 'Mon', '09:00:00', '10:00:00', 'GL-1', 'BS Information Technology', '1-B', 'College', 1, '2026-04-30 23:01:04');

-- --------------------------------------------------------

--
-- Table structure for table `trainings`
--

CREATE TABLE `trainings` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `trainer` varchar(150) DEFAULT NULL,
  `training_date` date DEFAULT NULL,
  `duration_hours` decimal(5,2) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status` enum('Scheduled','Ongoing','Completed','Cancelled') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `training_participants`
--

CREATE TABLE `training_participants` (
  `id` int(11) NOT NULL,
  `training_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `status` enum('Enrolled','Completed','Absent') DEFAULT 'Enrolled',
  `certificate_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `temp_password` varchar(255) DEFAULT NULL,
  `must_change_password` tinyint(1) DEFAULT '0',
  `role` varchar(50) DEFAULT 'employee',
  `is_active` tinyint(1) DEFAULT '1',
  `account_status` enum('Active','Pending','Suspended','Temporary') DEFAULT 'Active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `temp_password_plain` varchar(20) DEFAULT NULL,
  `assigned_office` varchar(50) DEFAULT NULL COMMENT 'For exec_secretary: VP Academic, VP Administration, President, Board'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `username`, `password`, `temp_password`, `must_change_password`, `role`, `is_active`, `account_status`, `last_login`, `created_at`, `created_by`, `approved_by`, `approved_at`, `temp_password_plain`, `assigned_office`) VALUES
(1, NULL, 'admin', '$2a$12$wePSZP9Nrq/Gu093lT6HT.Qari4ZBUeN7hXPP6F0jhtkyAyTX2ZyK', NULL, 0, 'superadmin', 1, 'Active', '2026-04-28 11:45:00', '2026-04-10 09:55:56', NULL, NULL, NULL, NULL, NULL),
(7, 3, 'hradmin', '$2y$10$mEasZFsUVNPBlcIF73WfeOtJ9mvdwASik.D5xWQUkzr9kSN1GkEOa', NULL, 0, 'hr_head', 1, 'Temporary', '2026-05-06 02:52:07', '2026-04-22 09:59:17', 1, NULL, NULL, '5D12ED94', NULL),
(8, 4, 'elouie', '$2y$10$BZPsWKuEhaTNTxWGsYuWieI3SxN1ov5bZyQtyyq9qkbpJclGljLN6', NULL, 0, 'employee', 1, 'Active', '2026-05-06 12:37:56', '2026-04-27 05:15:18', 7, NULL, NULL, NULL, NULL),
(9, 5, 'beth', '$2y$10$ooAjR5UD6k.rDuGP1Vtv6OQWe08OHfdLWjWT8ikzJhzgp/xIDZ7py', NULL, 0, 'dept_secretary', 1, 'Active', '2026-05-07 00:15:11', '2026-04-27 06:00:13', 7, NULL, NULL, NULL, NULL),
(10, 6, 'monz', '$2y$10$IC9hM348nTqqyoL9YakkhuzqqSfTTfXAFFFHf7gTQ7gFjYtLxt36C', NULL, 0, 'academic_head', 1, 'Active', '2026-05-06 12:39:37', '2026-04-27 08:16:20', 7, NULL, NULL, NULL, NULL),
(11, 7, 'jerome', '$2y$10$Nut9YdJpBbAELRALjDXG8eao1i.YsTqeNNh.0y6Tk3tZEqxhmlgre', NULL, 0, 'employee', 1, 'Active', '2026-05-07 05:24:30', '2026-04-27 13:39:31', 7, NULL, NULL, NULL, NULL),
(12, 8, 'jenny', '$2y$10$Ez9t1y37ArPvLTqjgpS97ePmbQAHRH6vVxRLORh8gjP98B22OiilW', NULL, 0, 'exec_secretary', 1, 'Active', '2026-05-05 06:00:55', '2026-04-28 05:15:48', 7, NULL, NULL, NULL, 'VP Academic'),
(13, 9, 'elena', '$2y$10$cbGKnr5FC66rZcUU8SpKne9bdVwUISl6Q.x0WsoAkWEDKqxyJlGWm', NULL, 0, 'vp_admin', 1, 'Active', '2026-05-05 04:04:42', '2026-04-28 18:41:13', 7, NULL, NULL, NULL, NULL),
(14, 10, 'charina', '$2y$10$sY3OoALt98IXoPHpXl9mE.YgQHXCBqQgmjp5LeInfmdYbjnSyVhsi', NULL, 0, 'vp_academic', 1, 'Active', '2026-05-05 05:04:48', '2026-04-28 18:42:47', 7, NULL, NULL, NULL, NULL),
(15, 11, 'junjun', '$2y$10$wRqrbcsuXxfPrC9pNUdKx.vGO1h5qi5Jq.xH8iWxHcNaPnP8LqdJS', NULL, 0, 'president', 1, 'Active', '2026-05-05 04:05:27', '2026-04-28 18:43:13', 7, NULL, NULL, NULL, NULL),
(16, 12, 'glenny', '$2y$10$oUePRxWRIWAK4dWd4ke4EOTIdTXZ9vXU0KlUBRwo1VZ1CsuRi68KG', NULL, 0, 'exec_secretary', 1, 'Active', '2026-05-02 06:38:42', '2026-04-28 18:52:33', 7, NULL, NULL, NULL, 'VP Administration'),
(17, 13, 'alona', '$2y$10$Qr3rHzt06QmrD2ZOzQJbiu1aId7.uPUINVP3bEtBVkr.gV8y8T2aO', NULL, 0, 'non_academic_head', 1, 'Active', '2026-05-05 05:36:14', '2026-05-02 06:36:14', 7, NULL, NULL, NULL, NULL),
(18, 14, 'irene', '$2y$10$5iFDfqBEIpg2Xb5xpFIA2Oq/F5ZA89OqUNTsKc35PlBHYQba1.BhO', NULL, 0, 'clinic_head', 1, 'Active', '2026-05-05 04:08:04', '2026-05-05 04:07:54', 7, NULL, NULL, NULL, NULL),
(19, 15, 'dianne', '$2y$10$7bsUXOIg0n37.ky8o6.VR.sj9DqEj4y5CQ6.XS7AI2f4boM6gOwNK', NULL, 0, 'clinic_staff', 1, 'Active', '2026-05-05 04:09:52', '2026-05-05 04:09:40', 7, NULL, NULL, NULL, NULL),
(20, 16, 'ismael', '$2y$10$qNN5kHiGqSXFOQGvIOcsoO1Hhfp1GHdCh.XpViPfwIZQjV0tqLtt2', NULL, 0, 'registrar_head', 1, 'Active', '2026-05-05 05:37:27', '2026-05-05 04:13:54', 7, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `work_locations`
--

CREATE TABLE `work_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(5) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `work_locations`
--

INSERT INTO `work_locations` (`id`, `name`, `code`, `description`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Main Campus', 'MAIN', 'Main campus - Sta Ana. St. Bagumbayan, City of Ilaga, Isabela', 1, 1, '2026-04-18 16:33:08'),
(2, 'Remote / Work from Home', 'WFH', 'Remote work arrangement', 0, 2, '2026-04-18 16:33:08'),
(3, 'Field Assignment', 'FIELD', 'Off-site / field work', 0, 3, '2026-04-18 16:33:08'),
(4, 'Cabagan', 'BRANCH', 'Cabagan Branch - Centro, Cabagan, Isabela', 1, 0, '2026-04-26 08:30:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `allowance_types`
--
ALTER TABLE `allowance_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ann_user` (`posted_by`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_app_jo` (`job_opening_id`);

--
-- Indexes for table `appraisals`
--
ALTER TABLE `appraisals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_apr_emp` (`employee_id`),
  ADD KEY `fk_apr_reviewer` (`reviewer_id`);

--
-- Indexes for table `approval_templates`
--
ALTER TABLE `approval_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_at_rtype` (`request_type_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_emp_date` (`employee_id`,`attendance_date`),
  ADD KEY `fk_att_emp` (`employee_id`);

--
-- Indexes for table `attendance_flags`
--
ALTER TABLE `attendance_flags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_af_emp_date` (`employee_id`,`flag_date`),
  ADD KEY `idx_af_att` (`attendance_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `classification_history`
--
ALTER TABLE `classification_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ch_emp` (`employee_id`);

--
-- Indexes for table `classification_notifications`
--
ALTER TABLE `classification_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cn_emp` (`employee_id`);

--
-- Indexes for table `dashboard_notes`
--
ALTER TABLE `dashboard_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dn_user_date` (`user_id`,`note_date`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dept_head` (`head_employee_id`);

--
-- Indexes for table `disciplinary`
--
ALTER TABLE `disciplinary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_disc_emp` (`employee_id`);

--
-- Indexes for table `document_control_counter`
--
ALTER TABLE `document_control_counter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_office_year` (`office`,`year`);

--
-- Indexes for table `document_logbook`
--
ALTER TABLE `document_logbook`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `control_number` (`control_number`),
  ADD KEY `idx_dl_request_id` (`request_id`),
  ADD KEY `idx_dl_control` (`control_number`),
  ADD KEY `idx_dl_office` (`office`),
  ADD KEY `idx_dl_status` (`status`),
  ADD KEY `idx_dl_date_received` (`date_received`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `fk_emp_dept` (`department_id`),
  ADD KEY `fk_emp_position` (`position_id`),
  ADD KEY `fk_emp_supervisor` (`supervisor_id`),
  ADD KEY `fk_emp_level` (`org_level_id`),
  ADD KEY `fk_emp_work_location` (`work_location_id`),
  ADD KEY `fk_emp_salary_grade` (`salary_grade_id`);

--
-- Indexes for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ea_emp` (`employee_id`),
  ADD KEY `fk_ea_type` (`allowance_type_id`);

--
-- Indexes for table `employee_categories`
--
ALTER TABLE `employee_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ec_parent` (`parent_id`);

--
-- Indexes for table `employee_classifications`
--
ALTER TABLE `employee_classifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ecl_parent` (`parent_id`);

--
-- Indexes for table `employee_day_schedules`
--
ALTER TABLE `employee_day_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_emp_date` (`employee_id`,`schedule_date`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_doc_emp` (`employee_id`);

--
-- Indexes for table `employee_exits`
--
ALTER TABLE `employee_exits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ee_emp` (`employee_id`);

--
-- Indexes for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_eo_emp` (`employee_id`),
  ADD KEY `fk_eo_task` (`task_id`);

--
-- Indexes for table `employee_qr_codes`
--
ALTER TABLE `employee_qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_token` (`qr_token`),
  ADD KEY `fk_qrc_emp` (`employee_id`);

--
-- Indexes for table `employee_ranks`
--
ALTER TABLE `employee_ranks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rank_name` (`name`);

--
-- Indexes for table `employee_requests`
--
ALTER TABLE `employee_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_er_emp` (`employee_id`),
  ADD KEY `fk_er_rtype` (`request_type_id`);

--
-- Indexes for table `employee_self_updates`
--
ALTER TABLE `employee_self_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_esu_emp` (`employee_id`);

--
-- Indexes for table `employee_shifts`
--
ALTER TABLE `employee_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_es_emp` (`employee_id`),
  ADD KEY `fk_es_shift` (`shift_id`);

--
-- Indexes for table `employee_transfers`
--
ALTER TABLE `employee_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_et_emp` (`employee_id`);

--
-- Indexes for table `employment_statuses`
--
ALTER TABLE `employment_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_es_name` (`name`);

--
-- Indexes for table `employment_types`
--
ALTER TABLE `employment_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_et_name` (`name`);

--
-- Indexes for table `flex_schedules`
--
ALTER TABLE `flex_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fs_emp` (`employee_id`);

--
-- Indexes for table `grievances`
--
ALTER TABLE `grievances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_grv_emp` (`employee_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_holiday_date` (`holiday_date`);

--
-- Indexes for table `job_openings`
--
ALTER TABLE `job_openings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jo_dept` (`department_id`),
  ADD KEY `fk_jo_pos` (`position_id`);

--
-- Indexes for table `leave_credits`
--
ALTER TABLE `leave_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_emp_year` (`employee_id`,`year`),
  ADD KEY `fk_lc_emp` (`employee_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lr_emp` (`employee_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_leave_code` (`code`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_loan_emp` (`employee_id`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lp_loan` (`loan_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `mobile_attendance_log`
--
ALTER TABLE `mobile_attendance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mal_emp` (`employee_id`);

--
-- Indexes for table `offsetting`
--
ALTER TABLE `offsetting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_off_emp` (`employee_id`);

--
-- Indexes for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ot_template` (`template_id`);

--
-- Indexes for table `onboarding_templates`
--
ALTER TABLE `onboarding_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `org_levels`
--
ALTER TABLE `org_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pr_user` (`user_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pay_emp` (`employee_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pos_dept` (`department_id`);

--
-- Indexes for table `profile_edit_requests`
--
ALTER TABLE `profile_edit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_per_emp` (`employee_id`);

--
-- Indexes for table `request_approvals`
--
ALTER TABLE `request_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ra_req` (`request_id`),
  ADD KEY `fk_ra_user` (`acted_by`);

--
-- Indexes for table `request_status_log`
--
ALTER TABLE `request_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rsl_request` (`request_id`),
  ADD KEY `idx_rsl_changed_at` (`changed_at`);

--
-- Indexes for table `request_types`
--
ALTER TABLE `request_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `salary_grades`
--
ALTER TABLE `salary_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sg_name` (`name`);

--
-- Indexes for table `separation_records`
--
ALTER TABLE `separation_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sep_emp` (`employee_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_key` (`setting_key`),
  ADD KEY `idx_group` (`setting_group`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sick_leave_approvals`
--
ALTER TABLE `sick_leave_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sla_lr` (`leave_request_id`);

--
-- Indexes for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tl_emp` (`employee_id`);

--
-- Indexes for table `teaching_loads`
--
ALTER TABLE `teaching_loads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tl_employee_id` (`employee_id`),
  ADD KEY `idx_tl_sy_sem` (`school_year`,`semester`),
  ADD KEY `idx_tl_status` (`status`),
  ADD KEY `idx_tl_created_by` (`created_by`);

--
-- Indexes for table `teaching_load_approvals`
--
ALTER TABLE `teaching_load_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tla_load_id` (`load_id`),
  ADD KEY `idx_tla_step` (`step`);

--
-- Indexes for table `teaching_load_subjects`
--
ALTER TABLE `teaching_load_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tls_load_id` (`load_id`);

--
-- Indexes for table `trainings`
--
ALTER TABLE `trainings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `training_participants`
--
ALTER TABLE `training_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tr_emp` (`training_id`,`employee_id`),
  ADD KEY `fk_tp_training` (`training_id`),
  ADD KEY `fk_tp_emp` (`employee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_usr_emp` (`employee_id`);

--
-- Indexes for table `work_locations`
--
ALTER TABLE `work_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wl_name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `allowance_types`
--
ALTER TABLE `allowance_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `appraisals`
--
ALTER TABLE `appraisals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `approval_templates`
--
ALTER TABLE `approval_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;
--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
--
-- AUTO_INCREMENT for table `attendance_flags`
--
ALTER TABLE `attendance_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;
--
-- AUTO_INCREMENT for table `classification_history`
--
ALTER TABLE `classification_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `classification_notifications`
--
ALTER TABLE `classification_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `dashboard_notes`
--
ALTER TABLE `dashboard_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
--
-- AUTO_INCREMENT for table `disciplinary`
--
ALTER TABLE `disciplinary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `document_control_counter`
--
ALTER TABLE `document_control_counter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `document_logbook`
--
ALTER TABLE `document_logbook`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- AUTO_INCREMENT for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_categories`
--
ALTER TABLE `employee_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
--
-- AUTO_INCREMENT for table `employee_classifications`
--
ALTER TABLE `employee_classifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
--
-- AUTO_INCREMENT for table `employee_day_schedules`
--
ALTER TABLE `employee_day_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_exits`
--
ALTER TABLE `employee_exits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_qr_codes`
--
ALTER TABLE `employee_qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_ranks`
--
ALTER TABLE `employee_ranks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
--
-- AUTO_INCREMENT for table `employee_requests`
--
ALTER TABLE `employee_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT for table `employee_self_updates`
--
ALTER TABLE `employee_self_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_shifts`
--
ALTER TABLE `employee_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employee_transfers`
--
ALTER TABLE `employee_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `employment_statuses`
--
ALTER TABLE `employment_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
--
-- AUTO_INCREMENT for table `employment_types`
--
ALTER TABLE `employment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
--
-- AUTO_INCREMENT for table `flex_schedules`
--
ALTER TABLE `flex_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `grievances`
--
ALTER TABLE `grievances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
--
-- AUTO_INCREMENT for table `job_openings`
--
ALTER TABLE `job_openings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `leave_credits`
--
ALTER TABLE `leave_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;
--
-- AUTO_INCREMENT for table `mobile_attendance_log`
--
ALTER TABLE `mobile_attendance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
--
-- AUTO_INCREMENT for table `offsetting`
--
ALTER TABLE `offsetting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
--
-- AUTO_INCREMENT for table `onboarding_templates`
--
ALTER TABLE `onboarding_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `org_levels`
--
ALTER TABLE `org_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
--
-- AUTO_INCREMENT for table `profile_edit_requests`
--
ALTER TABLE `profile_edit_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `request_approvals`
--
ALTER TABLE `request_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `request_status_log`
--
ALTER TABLE `request_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `request_types`
--
ALTER TABLE `request_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
--
-- AUTO_INCREMENT for table `salary_grades`
--
ALTER TABLE `salary_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
--
-- AUTO_INCREMENT for table `separation_records`
--
ALTER TABLE `separation_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;
--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `sick_leave_approvals`
--
ALTER TABLE `sick_leave_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `teaching_loads`
--
ALTER TABLE `teaching_loads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `teaching_load_approvals`
--
ALTER TABLE `teaching_load_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `teaching_load_subjects`
--
ALTER TABLE `teaching_load_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `trainings`
--
ALTER TABLE `trainings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `training_participants`
--
ALTER TABLE `training_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
--
-- AUTO_INCREMENT for table `work_locations`
--
ALTER TABLE `work_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_user` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_app_jo` FOREIGN KEY (`job_opening_id`) REFERENCES `job_openings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appraisals`
--
ALTER TABLE `appraisals`
  ADD CONSTRAINT `fk_apr_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_apr_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_flags`
--
ALTER TABLE `attendance_flags`
  ADD CONSTRAINT `fk_af_att` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_af_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classification_history`
--
ALTER TABLE `classification_history`
  ADD CONSTRAINT `fk_ch_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `disciplinary`
--
ALTER TABLE `disciplinary`
  ADD CONSTRAINT `fk_disc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_logbook`
--
ALTER TABLE `document_logbook`
  ADD CONSTRAINT `fk_dl_request` FOREIGN KEY (`request_id`) REFERENCES `employee_requests` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  ADD CONSTRAINT `fk_ea_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ea_type` FOREIGN KEY (`allowance_type_id`) REFERENCES `allowance_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_day_schedules`
--
ALTER TABLE `employee_day_schedules`
  ADD CONSTRAINT `fk_eds_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `fk_doc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_exits`
--
ALTER TABLE `employee_exits`
  ADD CONSTRAINT `fk_ee_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD CONSTRAINT `fk_eo_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eo_task` FOREIGN KEY (`task_id`) REFERENCES `onboarding_tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_qr_codes`
--
ALTER TABLE `employee_qr_codes`
  ADD CONSTRAINT `fk_qrc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_requests`
--
ALTER TABLE `employee_requests`
  ADD CONSTRAINT `fk_er_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_er_rtype` FOREIGN KEY (`request_type_id`) REFERENCES `request_types` (`id`);

--
-- Constraints for table `employee_self_updates`
--
ALTER TABLE `employee_self_updates`
  ADD CONSTRAINT `fk_esu_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_shifts`
--
ALTER TABLE `employee_shifts`
  ADD CONSTRAINT `fk_es_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_es_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_transfers`
--
ALTER TABLE `employee_transfers`
  ADD CONSTRAINT `fk_et_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `flex_schedules`
--
ALTER TABLE `flex_schedules`
  ADD CONSTRAINT `fk_fs_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grievances`
--
ALTER TABLE `grievances`
  ADD CONSTRAINT `fk_grv_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_openings`
--
ALTER TABLE `job_openings`
  ADD CONSTRAINT `fk_jo_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_jo_pos` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_credits`
--
ALTER TABLE `leave_credits`
  ADD CONSTRAINT `fk_lc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `fk_loan_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `fk_lp_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mobile_attendance_log`
--
ALTER TABLE `mobile_attendance_log`
  ADD CONSTRAINT `fk_mal_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offsetting`
--
ALTER TABLE `offsetting`
  ADD CONSTRAINT `fk_off_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  ADD CONSTRAINT `fk_ot_template` FOREIGN KEY (`template_id`) REFERENCES `onboarding_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_pay_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `fk_pos_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `profile_edit_requests`
--
ALTER TABLE `profile_edit_requests`
  ADD CONSTRAINT `fk_per_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_approvals`
--
ALTER TABLE `request_approvals`
  ADD CONSTRAINT `fk_ra_req` FOREIGN KEY (`request_id`) REFERENCES `employee_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ra_user` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `separation_records`
--
ALTER TABLE `separation_records`
  ADD CONSTRAINT `fk_sep_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sick_leave_approvals`
--
ALTER TABLE `sick_leave_approvals`
  ADD CONSTRAINT `fk_sla_lr` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  ADD CONSTRAINT `fk_tl_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teaching_loads`
--
ALTER TABLE `teaching_loads`
  ADD CONSTRAINT `fk_teaching_loads_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teaching_loads_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teaching_load_approvals`
--
ALTER TABLE `teaching_load_approvals`
  ADD CONSTRAINT `fk_teaching_load_approvals_load` FOREIGN KEY (`load_id`) REFERENCES `teaching_loads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teaching_load_subjects`
--
ALTER TABLE `teaching_load_subjects`
  ADD CONSTRAINT `fk_tls_load` FOREIGN KEY (`load_id`) REFERENCES `teaching_loads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_participants`
--
ALTER TABLE `training_participants`
  ADD CONSTRAINT `fk_tp_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tp_training` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_usr_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
