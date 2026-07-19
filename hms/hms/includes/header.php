<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $full_name)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?? 'HMS' ?> - <?= APP_NAME ?></title>
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-layout">
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo">🏥</div>
    <div><h1>MedCare HMS</h1><small>v1.0</small></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-group">
      <div class="nav-group-label">Main</div>
      <a href="/dashboard.php" class="nav-item <?= $current_page=='dashboard'?'active':'' ?>">
        <span class="icon"><i class="fa fa-gauge"></i></span> Dashboard
      </a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Patient Care</div>
      <a href="/patients.php" class="nav-item <?= $current_page=='patients'?'active':'' ?>">
        <span class="icon"><i class="fa fa-users"></i></span> Patients
      </a>
      <a href="/appointments.php" class="nav-item <?= $current_page=='appointments'?'active':'' ?>">
        <span class="icon"><i class="fa fa-calendar-check"></i></span> Appointments
      </a>
      <a href="/admissions.php" class="nav-item <?= $current_page=='admissions'?'active':'' ?>">
        <span class="icon"><i class="fa fa-bed"></i></span> Admissions
      </a>
      <a href="/medical_records.php" class="nav-item <?= $current_page=='medical_records'?'active':'' ?>">
        <span class="icon"><i class="fa fa-file-medical"></i></span> Medical Records
      </a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Hospital</div>
      <a href="/doctors.php" class="nav-item <?= $current_page=='doctors'?'active':'' ?>">
        <span class="icon"><i class="fa fa-user-doctor"></i></span> Doctors
      </a>
      <a href="/wards.php" class="nav-item <?= $current_page=='wards'?'active':'' ?>">
        <span class="icon"><i class="fa fa-hospital"></i></span> Wards & Beds
      </a>
      <a href="/departments.php" class="nav-item <?= $current_page=='departments'?'active':'' ?>">
        <span class="icon"><i class="fa fa-sitemap"></i></span> Departments
      </a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Services</div>
      <a href="/pharmacy.php" class="nav-item <?= $current_page=='pharmacy'?'active':'' ?>">
        <span class="icon"><i class="fa fa-pills"></i></span> Pharmacy
      </a>
      <a href="/lab.php" class="nav-item <?= $current_page=='lab'?'active':'' ?>">
        <span class="icon"><i class="fa fa-flask"></i></span> Laboratory
      </a>
      <a href="/billing.php" class="nav-item <?= $current_page=='billing'?'active':'' ?>">
        <span class="icon"><i class="fa fa-file-invoice-dollar"></i></span> Billing
      </a>
    </div>
    <?php if ($role === 'admin'): ?>
    <div class="nav-group">
      <div class="nav-group-label">Admin</div>
      <a href="/users.php" class="nav-item <?= $current_page=='users'?'active':'' ?>">
        <span class="icon"><i class="fa fa-user-gear"></i></span> Users
      </a>
      <a href="/reports.php" class="nav-item <?= $current_page=='reports'?'active':'' ?>">
        <span class="icon"><i class="fa fa-chart-bar"></i></span> Reports
      </a>
    </div>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?= substr($initials, 0, 2) ?></div>
      <div>
        <div style="color:#fff;font-weight:600;font-size:13px"><?= htmlspecialchars($full_name) ?></div>
        <div style="font-size:11px;text-transform:capitalize"><?= str_replace('_', ' ', $role) ?></div>
      </div>
    </div>
  </div>
</aside>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-title"><?= $page_title ?? 'Dashboard' ?></div>
    <div class="topbar-actions">
      <span style="font-size:13px;color:var(--text-muted)"><?= date('D, d M Y') ?></span>
      <a href="/logout.php" class="btn btn-secondary btn-sm"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>
  <div class="page-body">
