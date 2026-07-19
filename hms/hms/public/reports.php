<?php
$page_title = 'Reports';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');
$db = getDB();

$month = $_GET['month'] ?? date('Y-m');
$start = $month . '-01';
$end   = date('Y-m-t', strtotime($start));

// Revenue summary
$rev  = $db->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COALESCE(SUM(paid_amount),0) as paid, COUNT(*) as count FROM bills WHERE bill_date BETWEEN ? AND ?");
$rev->execute([$start." 00:00:00", $end." 23:59:59"]); $revenue = $rev->fetch();

// Appointments summary
$appt = $db->prepare("SELECT status,COUNT(*) as cnt FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY status");
$appt->execute([$start,$end]); $appt_stats = $appt->fetchAll(PDO::FETCH_KEY_PAIR);

// Patient registrations per week
$weekly = $db->prepare("SELECT WEEK(registered_at) as wk, COUNT(*) as cnt FROM patients WHERE registered_at BETWEEN ? AND ? GROUP BY wk");
$weekly->execute([$start." 00:00:00", $end." 23:59:59"]); $weekly = $weekly->fetchAll();

// Top doctors by appointments
$top_docs = $db->prepare("SELECT u.full_name, COUNT(*) as cnt FROM appointments a JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id WHERE a.appointment_date BETWEEN ? AND ? GROUP BY d.id ORDER BY cnt DESC LIMIT 5");
$top_docs->execute([$start,$end]); $top_docs = $top_docs->fetchAll();

// Department wise
$dept_stats = $db->prepare("SELECT dept.name, COUNT(*) as appts FROM appointments a JOIN doctors d ON a.doctor_id=d.id JOIN departments dept ON d.department_id=dept.id WHERE a.appointment_date BETWEEN ? AND ? GROUP BY dept.id ORDER BY appts DESC");
$dept_stats->execute([$start,$end]); $dept_stats = $dept_stats->fetchAll();

// Revenue by day (last 30 days)
$daily_rev = $db->query("SELECT DATE(bill_date) as day, SUM(paid_amount) as total FROM bills WHERE bill_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY DATE(bill_date) ORDER BY day")->fetchAll();

// Bed occupancy
$bed_occ = $db->query("SELECT w.ward_name, w.total_beds, (w.total_beds - w.available_beds) as occupied FROM wards w ORDER BY w.ward_name")->fetchAll();
?>

<div style="margin-bottom:20px;display:flex;align-items:center;gap:12px">
  <form method="GET" class="flex gap-2 flex-center">
    <label style="font-size:14px;font-weight:600">Month:</label>
    <input type="month" name="month" class="form-control" value="<?=$month?>" style="width:180px">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-chart-bar"></i> Generate</button>
  </form>
  <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa fa-print"></i> Print Report</button>
</div>

<!-- Revenue Summary -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon green"><i class="fa fa-rupee-sign"></i></div><div><div class="stat-value">₹<?= number_format($revenue['total'],0) ?></div><div class="stat-label">Total Billed</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fa fa-money-bill-wave"></i></div><div><div class="stat-value">₹<?= number_format($revenue['paid'],0) ?></div><div class="stat-label">Collected</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fa fa-clock"></i></div><div><div class="stat-value">₹<?= number_format($revenue['total']-$revenue['paid'],0) ?></div><div class="stat-label">Outstanding</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fa fa-file-invoice"></i></div><div><div class="stat-value"><?= $revenue['count'] ?></div><div class="stat-label">Total Bills</div></div></div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px">
  <!-- Appointments by Status -->
  <div class="card">
    <div class="card-header"><span class="card-title">Appointments — <?= date('F Y', strtotime($start)) ?></span></div>
    <div class="card-body">
      <?php
      $total_appts = array_sum($appt_stats);
      foreach(['Scheduled'=>'blue','Confirmed'=>'green','Completed'=>'purple','Cancelled'=>'red','No-Show'=>'orange'] as $status=>$color):
        $cnt = $appt_stats[$status] ?? 0;
        $pct = $total_appts > 0 ? round(($cnt/$total_appts)*100) : 0;
      ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
          <span><?=$status?></span><span style="font-weight:700"><?=$cnt?> (<?=$pct?>%)</span>
        </div>
        <div class="progress"><div class="progress-bar <?= $color==='red'?'red':($color==='green'?'green':'') ?>" style="width:<?=$pct?>%;background:var(--<?=$color==='blue'?'primary':($color==='purple'?'info':($color==='orange'?'warning':$color)) ?>)"></div></div>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:16px;font-size:13px;color:var(--text-muted);text-align:right">Total: <strong><?=$total_appts?></strong></div>
    </div>
  </div>

  <!-- Top Doctors -->
  <div class="card">
    <div class="card-header"><span class="card-title">Top Doctors by Appointments</span></div>
    <div class="card-body">
      <?php if (empty($top_docs)): ?><p style="color:var(--text-muted);text-align:center">No data for this period</p>
      <?php else: $max = $top_docs[0]['cnt']; foreach($top_docs as $i=>$doc): $pct = $max > 0 ? ($doc['cnt']/$max)*100 : 0; ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
          <span><?= htmlspecialchars($doc['full_name']) ?></span><span style="font-weight:700"><?=$doc['cnt']?> appts</span>
        </div>
        <div class="progress"><div class="progress-bar green" style="width:<?=$pct?>%"></div></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px">
  <!-- Department Stats -->
  <div class="card">
    <div class="card-header"><span class="card-title">Department-wise Appointments</span></div>
    <div class="card-body" style="padding:0">
      <table><thead><tr><th>Department</th><th style="text-align:right">Appointments</th></tr></thead>
      <tbody>
      <?php if (empty($dept_stats)): ?><tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:20px">No data</td></tr>
      <?php else: foreach($dept_stats as $ds): ?>
      <tr><td><?= htmlspecialchars($ds['name']) ?></td><td style="text-align:right"><span class="badge badge-primary"><?=$ds['appts']?></span></td></tr>
      <?php endforeach; endif; ?>
      </tbody></table>
    </div>
  </div>

  <!-- Bed Occupancy -->
  <div class="card">
    <div class="card-header"><span class="card-title">Current Bed Occupancy</span></div>
    <div class="card-body">
      <?php foreach($bed_occ as $ward): $pct = $ward['total_beds']>0 ? round(($ward['occupied']/$ward['total_beds'])*100):0; ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
          <span><?= htmlspecialchars($ward['ward_name']) ?></span>
          <span style="font-weight:700"><?=$ward['occupied']?>/<?=$ward['total_beds']?> (<?=$pct?>%)</span>
        </div>
        <div class="progress"><div class="progress-bar <?= $pct>=90?'red':($pct>=70?'orange':'green') ?>" style="width:<?=$pct?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Daily Revenue Table -->
<div class="card">
  <div class="card-header"><span class="card-title">Daily Revenue — Last 30 Days</span></div>
  <div class="card-body" style="padding:0">
    <div class="table-container">
      <table>
        <thead><tr><th>Date</th><th style="text-align:right">Revenue Collected</th></tr></thead>
        <tbody>
        <?php if (empty($daily_rev)): ?><tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:20px">No revenue data</td></tr>
        <?php else: foreach($daily_rev as $d): ?>
        <tr><td><?= date('D, d M Y', strtotime($d['day'])) ?></td><td style="text-align:right;font-weight:700;color:var(--success)">₹<?= number_format($d['total'],2) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
