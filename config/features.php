<?php

/**
 * Definitive feature-key registry (SRS §14.1). Keep in sync with the
 * feature_registry table (FeatureRegistrySeeder) and the `features` JSON on
 * subscription_plans. A copy of this file also lives in the HRM app
 * (hrm/config/features.php) — keep the key list identical.
 *
 * Each entry:
 *   name        - label shown in the panel
 *   module      - HRM module the flag gates (informational)
 *   group       - section heading on the plan / provisioning feature grid
 *   description - one line explaining what the tenant gets
 *   default     - value when a tenant has no override and no plan snapshot entry
 *
 * Deprecated keys (e.g. task_management) are NOT listed here; they live only in
 * feature_registry with a replaced_by pointer so old snapshots still resolve.
 */
return [

    // ---- Attendance & Time -------------------------------------------------
    'attendance' => [
        'name' => 'Attendance',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Clock in / out, daily timesheet and the attendance register.',
        'default' => true,
    ],
    'attendance_face' => [
        'name' => 'Face-verification attendance',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Selfie + face match on every punch. When off, employees use simple clock in / out only.',
        'default' => false,
    ],
    'regularization' => [
        'name' => 'Attendance regularization',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Employees raise requests to correct a missed or wrong punch; manager approves.',
        'default' => true,
    ],
    'overtime' => [
        'name' => 'Overtime',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Capture overtime hours with a request and approval flow.',
        'default' => true,
    ],
    'geo_tracking' => [
        'name' => 'Geo tracking',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Live GPS trail of field employees during their shift (paid add-on).',
        'default' => false,
    ],
    'wfh_travel' => [
        'name' => 'WFH & travel',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Work-from-home and on-duty / travel requests.',
        'default' => true,
    ],
    'attendance_biometric' => [
        'name' => 'Biometric / fingerprint attendance',
        'module' => 'attendance',
        'group' => 'Attendance & Time',
        'description' => 'Punches captured from a fingerprint/biometric terminal device. When off, the Biometric settings screen and device sync are unavailable.',
        'default' => false,
    ],

    // ---- Shifts ----------------------------------------------------------
    'fixed_shift' => [
        'name' => 'Fixed shift management',
        'module' => 'attendance',
        'group' => 'Shifts',
        'description' => 'Company-wide fixed shift definitions and a default shift for all employees.',
        'default' => true,
    ],
    'custom_shift' => [
        'name' => 'Custom & rotational shifts',
        'module' => 'attendance',
        'group' => 'Shifts',
        'description' => 'Per-employee shift assignment and rotating shift patterns / weekly roster.',
        'default' => false,
    ],

    // ---- Leave & Holidays ----------------------------------------------
    'leave_management' => [
        'name' => 'Leave management',
        'module' => 'leave',
        'group' => 'Leave & Holidays',
        'description' => 'Leave types, balances, accruals and the approval flow.',
        'default' => true,
    ],
    'holiday' => [
        'name' => 'Holiday calendar',
        'module' => 'leave',
        'group' => 'Leave & Holidays',
        'description' => 'Company holiday list with optional location / religion-based holidays.',
        'default' => true,
    ],

    // ---- Tasks & Projects --------------------------------------------
    'task_single' => [
        'name' => 'Single tasks',
        'module' => 'tasks',
        'group' => 'Tasks & Projects',
        'description' => 'Assign and track individual tasks for one employee at a time.',
        'default' => true,
    ],
    'task_group' => [
        'name' => 'Group tasks',
        'module' => 'tasks',
        'group' => 'Tasks & Projects',
        'description' => 'Shared tasks for a team or group with combined progress and checklists.',
        'default' => true,
    ],
    'project_management' => [
        'name' => 'Project management',
        'module' => 'projects',
        'group' => 'Tasks & Projects',
        'description' => 'Projects, members, task grouping and progress tracking.',
        'default' => false,
    ],

    // ---- Assets ---------------------------------------------------
    'asset_management' => [
        'name' => 'Asset management',
        'module' => 'assets',
        'group' => 'Assets',
        'description' => 'Company assets, categories, vendors, lifecycle and employee assignment.',
        'default' => true,
    ],

    // ---- People -------------------------------------------------------
    'onboarding' => [
        'name' => 'Onboarding',
        'module' => 'onboarding',
        'group' => 'People',
        'description' => 'New-joiner checklists and onboarding task assignment.',
        'default' => false,
    ],
    'offboarding' => [
        'name' => 'Offboarding',
        'module' => 'offboarding',
        'group' => 'People',
        'description' => 'Resignation, exit interview, clearance and full-and-final.',
        'default' => false,
    ],
    'kpi_performance' => [
        'name' => 'Performance & KPI',
        'module' => 'performance',
        'group' => 'People',
        'description' => 'KPI scorecards, goals and periodic manager reviews.',
        'default' => false,
    ],

    // ---- Payroll & Finance ----------------------------------------
    'payroll' => [
        'name' => 'Payroll',
        'module' => 'payroll',
        'group' => 'Payroll & Finance',
        'description' => 'Salary structure, monthly payroll run, payroll master and payslips.',
        'default' => false,
    ],
    'expense_management' => [
        'name' => 'Expense management',
        'module' => 'expenses',
        'group' => 'Payroll & Finance',
        'description' => 'Expense claims, category budgets and reimbursement.',
        'default' => false,
    ],
    'expense_bulk_payment' => [
        'name' => 'Expense bulk payment',
        'module' => 'expenses',
        'group' => 'Payroll & Finance',
        'description' => 'Pay many approved advances / reimbursements in one voucher (bank-transfer CSV, PDF, void) and bulk approve or reject expenses.',
        'default' => true,
    ],
    'expense_payroll_link' => [
        'name' => 'Expense reimbursement via payroll',
        'module' => 'expenses',
        'group' => 'Payroll & Finance',
        'description' => 'Approved reimbursements can be added to the employee\'s next salary slip (paid with the salary) instead of a payment voucher. Needs Payroll, Expense management and the dynamic payroll engine.',
        'default' => false,
    ],
    'loan_management' => [
        'name' => 'Loan management',
        'module' => 'loans',
        'group' => 'Payroll & Finance',
        'description' => 'Employee loans / advances with an EMI repayment schedule.',
        'default' => false,
    ],

    // ---- Communication -------------------------------------------
    'announcements' => [
        'name' => 'Announcements',
        'module' => 'announcements',
        'group' => 'Communication',
        'description' => 'Company-wide announcements with read acknowledgement.',
        'default' => true,
    ],
    'broadcast_notifications' => [
        'name' => 'Broadcast notifications',
        'module' => 'broadcasts',
        'group' => 'Communication',
        'description' => 'Compose and send targeted notifications to employees, filtered by role, department, designation, branch or specific people.',
        'default' => true,
    ],
    'employee_chat' => [
        'name' => 'Employee chat',
        'module' => 'chat',
        'group' => 'Communication',
        'description' => 'One-to-one and group messaging between employees.',
        'default' => false,
    ],
    'meetings' => [
        'name' => 'Meetings',
        'module' => 'meetings',
        'group' => 'Communication',
        'description' => 'Meeting scheduling, participants and minutes of meeting.',
        'default' => true,
    ],
    'daily_reports' => [
        'name' => 'Daily reports',
        'module' => 'reports',
        'group' => 'Communication',
        'description' => 'End-of-day work reports submitted by employees.',
        'default' => true,
    ],

    // ---- Hiring -------------------------------------------------
    'recruitment' => [
        'name' => 'Recruitment',
        'module' => 'recruitment',
        'group' => 'Hiring',
        'description' => 'Job openings, candidate pipeline, interviews and offers.',
        'default' => false,
    ],
    'candidate_portal' => [
        'name' => 'Candidate portal',
        'module' => 'recruitment',
        'group' => 'Hiring',
        'description' => 'Public-facing careers page for applicants.',
        'default' => false,
    ],

    // ---- Platform ---------------------------------------------
    'branches' => [
        'name' => 'Branches',
        'module' => 'settings',
        'group' => 'Platform',
        'description' => 'Multiple branches / locations under one company.',
        'default' => false,
    ],
    'ai_assistant' => [
        'name' => 'HRM AI assistant',
        'module' => 'platform',
        'group' => 'Platform',
        'description' => 'In-app AI helper for employees and admins — policies, leave balance, how-to.',
        'default' => false,
    ],

];
