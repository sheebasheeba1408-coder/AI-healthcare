<?php
$page_title = 'Appointments';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'patient_id' => (int)$_POST['patient_id'],
            'doctor_id' => (int)$_POST['doctor_id'],
            'appointment_date' => $_POST['appointment_date'],
            'appointment_time' => $_POST['appointment_time'],
            'type' => $_POST['type'],
            'status' => $_POST['status'],
            'reason' => sanitize($_POST['reason']),
            'notes' => sanitize($_POST['notes']),
        ];
        if ($action === 'add') {
            $cols = implode(',', array_keys($data));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO appointments ($cols) VALUES ($vals)")->execute(array_values($data));
            $msg = 'Appointment booked successfully!';
        } else {
            $id = (int)$_POST['id'];
            $sets = implode(',', array_map(fn($k)=>"$k=?", array_keys($data)));
            $db->prepare("UPDATE appointments SET $sets WHERE id=?")->execute([...array_values($data), $id]);
            $msg = 'Appointment updated.';
        }
    } elseif ($action === 'status') {
        $db->prepare("UPDATE appointments SET status=? WHERE id=?")->execute([$_POST['status'], (int)$_POST['id']]);
        $msg = 'Status updated.';
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM appointments WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Appointment deleted.'; $msg_type = 'warning';
    }
}

$doctors = $db->query("SELECT d.id, u.full_name, d.specialization FROM doctors d JOIN users u ON d.user_id=u.id ORDER BY u.full_name")->fetchAll();
$patients = $db->query("SELECT id, patient_id, full_name FROM patients ORDER BY full_name")->fetchAll();

$date_filter = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';

$where = ["1=1"];
$params = [];
if ($date_filter) { $where[] = "a.appointment_date = ?"; $params[] = $date_filter; }
if ($status_filter) { $where[] = "a.status = ?"; $params[] = $status_filter; }
if ($doctor_filter) { $where[] = "a.doctor_id = ?"; $params[] = (int)$doctor_filter; }
$where_sql = implode(' AND ', $where);

$appts = $db->prepare("SELECT a.*, p.full_name as patient_name, p.patient_id as pid, p.phone, u.full_name as doctor_name, dept.name as dept_name
    FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id
    LEFT JOIN departments dept ON d.department_id=dept.id WHERE $where_sql ORDER BY a.appointment_time");
$appts->execute($params);
$appointments = $appts->fetchAll();

$action_mode = $_GET['action'] ?? '';
$edit_appt = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM appointments WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_appt = $stmt->fetch();
}

$pre_patient = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if ($action_mode === 'add' || $edit_appt): ?>
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/appointments.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700"><?= $edit_appt ? 'Edit Appointment' : 'Book Appointment' ?></h2>
  </div>
</div>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="action" value="<?= $edit_appt ? 'edit' : 'add' ?>">
  <?php if ($edit_appt): ?><input type="hidden" name="id" value="<?= $edit_appt['id'] ?>"><?php endif; ?>
  <div class="form-grid-2">
    <div class="form-group"><label class="form-label">Patient *</label>
      <select name="patient_id" class="form-select" required>
        <option value="">-- Select Patient --</option>
        <?php foreach($patients as $p): $sel = ($edit_appt['patient_id']??$pre_patient)==$p['id']?'selected':''; ?>
        <option value="<?= $p['id'] ?>" <?= $sel ?>><?= htmlspecialchars($p['full_name']) ?> (<?= $p['patient_id'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Doctor *</label>
      <select name="doctor_id" class="form-select" required>
        <option value="">-- Select Doctor --</option>
        <?php foreach($doctors as $d): $sel = ($edit_appt['doctor_id']??'')==$d['id']?'selected':''; ?>
        <option value="<?= $d['id'] ?>" <?= $sel ?>><?= htmlspecialchars($d['full_name']) ?> — <?= $d['specialization'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Date *</label><input type="date" name="appointment_date" class="form-control" value="<?= $edit_appt['appointment_date'] ?? date('Y-m-d') ?>" required></div>
    <div class="form-group"><label class="form-label">Time *</label><input type="time" name="appointment_time" class="form-control" value="<?= $edit_appt['appointment_time'] ?? '' ?>" required></div>
    <div class="form-group"><label class="form-label">Type</label>
      <select name="type" class="form-select">
        <?php foreach(['Consultation','Follow-up','Emergency','Routine Check'] as $t): ?><option <?= ($edit_appt['type']??'')===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach(['Scheduled','Confirmed','Completed','Cancelled','No-Show'] as $s): ?><option <?= ($edit_appt['status']??'Scheduled')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group"><label class="form-label">Reason for Visit</label><textarea name="reason" class="form-control" rows="2"><?= htmlspecialchars($edit_appt['reason'] ?? '') ?></textarea></div>
  <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($edit_appt['notes'] ?? '') ?></textarea></div>
  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= $edit_appt ? 'Update' : 'Book Appointment' ?></button>
    <a href="/appointments.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
</div></div>

<?php else: ?>
<div class="section-header">
  <form method="GET" class="flex flex-center gap-2">
    <input type="date" name="date" class="form-control" value="<?= $date_filter ?>" style="width:160px">
    <select name="status" class="form-select" style="width:150px">
      <option value="">All Status</option>
      <?php foreach(['Scheduled','Confirmed','Completed','Cancelled','No-Show'] as $s): ?><option <?= $status_filter===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
    </select>
    <select name="doctor" class="form-select" style="width:180px">
      <option value="">All Doctors</option>
      <?php foreach($doctors as $d): ?><option value="<?= $d['id'] ?>" <?= $doctor_filter==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['full_name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary"><i class="fa fa-filter"></i></button>
    <a href="/appointments.php" class="btn btn-secondary btn-sm">Clear</a>
  </form>
  <a href="/appointments.php?action=add" class="btn btn-primary"><i class="fa fa-calendar-plus"></i> Book Appointment</a>
</div>
<div class="card">
  <div class="card-header">
    <span class="card-title">Appointments — <?= date('d M Y', strtotime($date_filter)) ?></span>
    <span class="badge badge-primary"><?= count($appointments) ?> appointments</span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-container">
      <table>
        <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Time</th><th>Type</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($appointments)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">No appointments found for this date</td></tr>
        <?php else: foreach($appointments as $i => $a):
          $sc = match($a['status']){'Confirmed'=>'badge-success','Cancelled'=>'badge-danger','Completed'=>'badge-info','No-Show'=>'badge-warning',default=>'badge-secondary'};
        ?>
        <tr>
          <td style="color:var(--text-muted)"><?= $i+1 ?></td>
          <td>
            <strong><?= htmlspecialchars($a['patient_name']) ?></strong><br>
            <span style="font-size:12px;color:var(--text-muted)"><?= $a['pid'] ?> · <?= $a['phone'] ?></span>
          </td>
          <td>
            <span><?= htmlspecialchars($a['doctor_name']) ?></span><br>
            <span style="font-size:12px;color:var(--text-muted)"><?= $a['dept_name'] ?></span>
          </td>
          <td style="font-weight:600"><?= date('h:i A', strtotime($a['appointment_time'])) ?></td>
          <td><span class="badge badge-secondary"><?= $a['type'] ?></span></td>
          <td style="font-size:13px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($a['reason'] ?: '—') ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $a['id'] ?>">
              <select name="status" class="form-select" style="font-size:12px;padding:4px 8px;height:28px;width:120px" onchange="this.form.submit()">
                <?php foreach(['Scheduled','Confirmed','Completed','Cancelled','No-Show'] as $s): ?><option <?= $a['status']===$s?'selected':'' ?>><?=$s?></option><?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <div class="flex gap-2">
              <a href="/appointments.php?edit=<?= $a['id'] ?>" class="btn btn-primary btn-sm btn-icon"><i class="fa fa-pen"></i></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
