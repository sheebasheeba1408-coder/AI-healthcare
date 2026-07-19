<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stats = getStats();

// Recent appointments
$recent_appts = $db->query("SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name
    FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id
    WHERE a.appointment_date >= CURDATE() ORDER BY a.appointment_date,a.appointment_time LIMIT 8")->fetchAll();

// Recent patients
$recent_patients = $db->query("SELECT * FROM patients ORDER BY registered_at DESC LIMIT 5")->fetchAll();

// Low stock medicines
$low_stock = $db->query("SELECT * FROM medicines WHERE stock_quantity <= reorder_level ORDER BY stock_quantity LIMIT 5")->fetchAll();

// Monthly revenue chart data
$revenue_data = $db->query("SELECT MONTH(bill_date) as m, SUM(paid_amount) as total FROM bills WHERE YEAR(bill_date)=YEAR(CURDATE()) GROUP BY MONTH(bill_date) ORDER BY m")->fetchAll();
$months = array_fill(1, 12, 0);
foreach ($revenue_data as $r) $months[$r['m']] = (float)$r['total'];
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-users"></i></div>
    <div><div class="stat-value"><?= number_format($stats['total_patients']) ?></div><div class="stat-label">Total Patients</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa fa-calendar-check"></i></div>
    <div><div class="stat-value"><?= $stats['today_appointments'] ?></div><div class="stat-label">Today's Appointments</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa fa-bed"></i></div>
    <div><div class="stat-value"><?= $stats['admitted_patients'] ?></div><div class="stat-label">Admitted Patients</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon cyan"><i class="fa fa-door-open"></i></div>
    <div><div class="stat-value"><?= $stats['available_beds'] ?></div><div class="stat-label">Available Beds</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fa fa-user-doctor"></i></div>
    <div><div class="stat-value"><?= $stats['total_doctors'] ?></div><div class="stat-label">Total Doctors</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa fa-file-invoice-dollar"></i></div>
    <div><div class="stat-value">₹<?= number_format($stats['monthly_revenue']) ?></div><div class="stat-label">Monthly Revenue</div></div>
  </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px">
  <!-- Today's Appointments -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-calendar-check" style="color:var(--primary)"></i> Upcoming Appointments</span>
      <a href="/appointments.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-container">
        <table>
          <thead><tr><th>Patient</th><th>Doctor</th><th>Date & Time</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (empty($recent_appts)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">No upcoming appointments</td></tr>
          <?php else: foreach ($recent_appts as $a): 
            $status_class = match($a['status']) {'Confirmed'=>'badge-success','Cancelled'=>'badge-danger','Completed'=>'badge-info','No-Show'=>'badge-warning',default=>'badge-secondary'};
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($a['patient_name']) ?></strong></td>
              <td><span style="color:var(--text-muted);font-size:13px"><?= htmlspecialchars($a['doctor_name']) ?></span></td>
              <td><span style="font-size:13px"><?= date('d M', strtotime($a['appointment_date'])) ?> <?= date('h:i A', strtotime($a['appointment_time'])) ?></span></td>
              <td><span class="badge <?= $status_class ?>"><?= $a['status'] ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Quick Actions + Stats -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-header"><span class="card-title">⚡ Quick Actions</span></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <a href="/patients.php?action=add" class="btn btn-primary" style="justify-content:center;flex-direction:column;height:64px;gap:4px;font-size:12px"><i class="fa fa-user-plus fa-lg"></i>New Patient</a>
          <a href="/appointments.php?action=add" class="btn btn-success" style="justify-content:center;flex-direction:column;height:64px;gap:4px;font-size:12px"><i class="fa fa-calendar-plus fa-lg"></i>Book Appointment</a>
          <a href="/admissions.php?action=add" class="btn btn-warning" style="justify-content:center;flex-direction:column;height:64px;gap:4px;font-size:12px"><i class="fa fa-bed fa-lg"></i>Admit Patient</a>
          <a href="/billing.php?action=add" class="btn btn-secondary" style="justify-content:center;flex-direction:column;height:64px;gap:4px;font-size:12px"><i class="fa fa-file-invoice-dollar fa-lg"></i>Create Bill</a>
        </div>
      </div>
    </div>
    <?php if (!empty($low_stock)): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-triangle-exclamation" style="color:var(--warning)"></i> Low Stock Alert</span>
        <a href="/pharmacy.php" class="btn btn-outline btn-sm">View</a>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <?php foreach ($low_stock as $m): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
          <span><?= htmlspecialchars($m['name']) ?></span>
          <span class="badge badge-danger"><?= $m['stock_quantity'] ?> left</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Patients -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa fa-users" style="color:var(--primary)"></i> Recently Registered Patients</span>
    <a href="/patients.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-container">
      <table>
        <thead><tr><th>Patient ID</th><th>Name</th><th>Gender</th><th>Blood Group</th><th>Phone</th><th>Registered</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($recent_patients as $p): ?>
        <tr>
          <td><span class="badge badge-primary"><?= $p['patient_id'] ?></span></td>
          <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
          <td><?= $p['gender'] ?></td>
          <td><span class="badge badge-danger"><?= $p['blood_group'] ?></span></td>
          <td><?= $p['phone'] ?></td>
          <td style="color:var(--text-muted);font-size:13px"><?= date('d M Y', strtotime($p['registered_at'])) ?></td>
          <td><a href="/patients.php?view=<?= $p['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
