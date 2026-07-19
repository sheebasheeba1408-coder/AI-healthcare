<?php
$page_title = 'Patients';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$msg = ''; $msg_type = 'success';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'full_name' => sanitize($_POST['full_name']),
            'dob' => $_POST['dob'] ?: null,
            'gender' => $_POST['gender'],
            'blood_group' => $_POST['blood_group'],
            'phone' => sanitize($_POST['phone']),
            'email' => sanitize($_POST['email']),
            'address' => sanitize($_POST['address']),
            'emergency_contact' => sanitize($_POST['emergency_contact']),
            'emergency_phone' => sanitize($_POST['emergency_phone']),
            'allergies' => sanitize($_POST['allergies']),
            'medical_history' => sanitize($_POST['medical_history']),
            'insurance_provider' => sanitize($_POST['insurance_provider']),
            'insurance_number' => sanitize($_POST['insurance_number']),
        ];
        if ($action === 'add') {
            $data['patient_id'] = generatePatientId();
            $cols = implode(',', array_keys($data));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $stmt = $db->prepare("INSERT INTO patients ($cols) VALUES ($vals)");
            $stmt->execute(array_values($data));
            $msg = "Patient {$data['full_name']} registered successfully! ID: {$data['patient_id']}";
        } else {
            $id = (int)$_POST['id'];
            $sets = implode(',', array_map(fn($k)=>"$k=?", array_keys($data)));
            $stmt = $db->prepare("UPDATE patients SET $sets WHERE id=?");
            $stmt->execute([...array_values($data), $id]);
            $msg = 'Patient updated successfully.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM patients WHERE id=?")->execute([$id]);
        $msg = 'Patient deleted.'; $msg_type = 'warning';
    }
}

// Filters
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = $search ? "WHERE full_name LIKE ? OR phone LIKE ? OR patient_id LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%","%$search%","%$search%","%$search%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM patients $where");
$total->execute($params);
$total_rows = $total->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $db->prepare("SELECT * FROM patients $where ORDER BY registered_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$patients = $stmt->fetchAll();

// View single patient
$view_patient = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE id=?");
    $stmt->execute([(int)$_GET['view']]);
    $view_patient = $stmt->fetch();
    // Get appointments
    $appts = $db->prepare("SELECT a.*, u.full_name as doctor_name FROM appointments a JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id WHERE a.patient_id=? ORDER BY a.appointment_date DESC LIMIT 5");
    $appts->execute([$view_patient['id']]);
    $patient_appts = $appts->fetchAll();
}

// Edit patient
$edit_patient = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_patient = $stmt->fetch();
}

$action_mode = $_GET['action'] ?? '';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if ($view_patient): ?>
<!-- Patient Detail View -->
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/patients.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:18px;font-weight:700"><?= htmlspecialchars($view_patient['full_name']) ?></h2>
    <span class="badge badge-primary"><?= $view_patient['patient_id'] ?></span>
    <span class="badge badge-danger"><?= $view_patient['blood_group'] ?></span>
  </div>
  <div class="flex gap-2">
    <a href="/appointments.php?action=add&patient_id=<?= $view_patient['id'] ?>" class="btn btn-success btn-sm"><i class="fa fa-calendar-plus"></i> Book Appointment</a>
    <a href="/patients.php?edit=<?= $view_patient['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-pen"></i> Edit</a>
  </div>
</div>
<div class="grid-2" style="gap:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">Personal Information</span></div>
    <div class="card-body">
      <table style="font-size:14px;width:100%">
        <tr><td style="color:var(--text-muted);padding:6px 0;width:40%">Full Name</td><td><strong><?= htmlspecialchars($view_patient['full_name']) ?></strong></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Date of Birth</td><td><?= $view_patient['dob'] ? date('d M Y', strtotime($view_patient['dob'])) : 'N/A' ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Gender</td><td><?= $view_patient['gender'] ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Blood Group</td><td><span class="badge badge-danger"><?= $view_patient['blood_group'] ?></span></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Phone</td><td><?= $view_patient['phone'] ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Email</td><td><?= htmlspecialchars($view_patient['email'] ?: 'N/A') ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Address</td><td><?= htmlspecialchars($view_patient['address'] ?: 'N/A') ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:6px 0">Registered</td><td><?= date('d M Y', strtotime($view_patient['registered_at'])) ?></td></tr>
      </table>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-header"><span class="card-title">Emergency Contact</span></div>
      <div class="card-body">
        <table style="font-size:14px;width:100%">
          <tr><td style="color:var(--text-muted);padding:5px 0;width:40%">Name</td><td><?= htmlspecialchars($view_patient['emergency_contact'] ?: 'N/A') ?></td></tr>
          <tr><td style="color:var(--text-muted);padding:5px 0">Phone</td><td><?= $view_patient['emergency_phone'] ?: 'N/A' ?></td></tr>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><span class="card-title">Medical Information</span></div>
      <div class="card-body">
        <div style="margin-bottom:12px"><strong style="font-size:13px">Allergies:</strong><p style="font-size:13px;color:var(--text-muted);margin-top:4px"><?= htmlspecialchars($view_patient['allergies'] ?: 'None') ?></p></div>
        <div><strong style="font-size:13px">Medical History:</strong><p style="font-size:13px;color:var(--text-muted);margin-top:4px"><?= htmlspecialchars($view_patient['medical_history'] ?: 'None') ?></p></div>
      </div>
    </div>
    <?php if ($view_patient['insurance_provider']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Insurance</span></div>
      <div class="card-body">
        <table style="font-size:14px;width:100%">
          <tr><td style="color:var(--text-muted);padding:5px 0;width:40%">Provider</td><td><?= htmlspecialchars($view_patient['insurance_provider']) ?></td></tr>
          <tr><td style="color:var(--text-muted);padding:5px 0">Policy No.</td><td><?= htmlspecialchars($view_patient['insurance_number']) ?></td></tr>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<!-- Recent Appointments -->
<div class="card mt-4">
  <div class="card-header"><span class="card-title">Recent Appointments</span></div>
  <div class="card-body" style="padding:0">
    <table><thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Type</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (empty($patient_appts)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No appointments found</td></tr>
    <?php else: foreach($patient_appts as $a): $sc=match($a['status']){'Confirmed'=>'badge-success','Cancelled'=>'badge-danger','Completed'=>'badge-info',default=>'badge-secondary'}; ?>
    <tr>
      <td><?= date('d M Y', strtotime($a['appointment_date'])) ?></td>
      <td><?= date('h:i A', strtotime($a['appointment_time'])) ?></td>
      <td><?= htmlspecialchars($a['doctor_name']) ?></td>
      <td><span class="badge badge-secondary"><?= $a['type'] ?></span></td>
      <td><span class="badge <?= $sc ?>"><?= $a['status'] ?></span></td>
    </tr>
    <?php endforeach; endif; ?></tbody></table>
  </div>
</div>

<?php elseif ($action_mode === 'add' || $edit_patient): ?>
<!-- Add/Edit Form -->
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/patients.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700"><?= $edit_patient ? 'Edit Patient' : 'Register New Patient' ?></h2>
  </div>
</div>
<div class="card">
  <div class="card-body">
    <form method="POST" action="/patients.php">
      <input type="hidden" name="action" value="<?= $edit_patient ? 'edit' : 'add' ?>">
      <?php if ($edit_patient): ?><input type="hidden" name="id" value="<?= $edit_patient['id'] ?>"><?php endif; ?>
      <div class="form-grid-3">
        <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($edit_patient['full_name'] ?? '') ?>" required></div>
        <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= $edit_patient['dob'] ?? '' ?>"></div>
        <div class="form-group"><label class="form-label">Gender *</label>
          <select name="gender" class="form-select" required>
            <?php foreach(['Male','Female','Other'] as $g): ?><option value="<?=$g?>" <?= ($edit_patient['gender']??'')===$g?'selected':'' ?>><?=$g?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Blood Group</label>
          <select name="blood_group" class="form-select">
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $bg): ?><option value="<?=$bg?>" <?= ($edit_patient['blood_group']??'Unknown')===$bg?'selected':'' ?>><?=$bg?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Phone *</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_patient['phone'] ?? '') ?>" required></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_patient['email'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($edit_patient['address'] ?? '') ?></textarea></div>
      <hr class="divider">
      <div style="font-weight:700;font-size:14px;margin-bottom:14px">Emergency Contact</div>
      <div class="form-grid-2">
        <div class="form-group"><label class="form-label">Contact Name</label><input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($edit_patient['emergency_contact'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">Contact Phone</label><input type="text" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($edit_patient['emergency_phone'] ?? '') ?>"></div>
      </div>
      <hr class="divider">
      <div style="font-weight:700;font-size:14px;margin-bottom:14px">Medical Information</div>
      <div class="form-grid-2">
        <div class="form-group"><label class="form-label">Allergies</label><textarea name="allergies" class="form-control" rows="3"><?= htmlspecialchars($edit_patient['allergies'] ?? '') ?></textarea></div>
        <div class="form-group"><label class="form-label">Medical History</label><textarea name="medical_history" class="form-control" rows="3"><?= htmlspecialchars($edit_patient['medical_history'] ?? '') ?></textarea></div>
      </div>
      <hr class="divider">
      <div style="font-weight:700;font-size:14px;margin-bottom:14px">Insurance Details</div>
      <div class="form-grid-2">
        <div class="form-group"><label class="form-label">Insurance Provider</label><input type="text" name="insurance_provider" class="form-control" value="<?= htmlspecialchars($edit_patient['insurance_provider'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">Policy Number</label><input type="text" name="insurance_number" class="form-control" value="<?= htmlspecialchars($edit_patient['insurance_number'] ?? '') ?>"></div>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= $edit_patient ? 'Update Patient' : 'Register Patient' ?></button>
        <a href="/patients.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<!-- Patient List -->
<div class="section-header">
  <div class="flex flex-center gap-3">
    <div class="search-bar">
      <span class="search-icon"><i class="fa fa-search"></i></span>
      <form method="GET"><input type="text" name="search" class="form-control" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>"></form>
    </div>
    <span style="color:var(--text-muted);font-size:13px"><?= number_format($total_rows) ?> patients</span>
  </div>
  <a href="/patients.php?action=add" class="btn btn-primary"><i class="fa fa-user-plus"></i> Register Patient</a>
</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-container">
      <table>
        <thead><tr><th>Patient ID</th><th>Name</th><th>Age/Gender</th><th>Blood</th><th>Phone</th><th>Email</th><th>Registered</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($patients)): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">No patients found</td></tr>
        <?php else: foreach($patients as $p): 
          $age = $p['dob'] ? (int)((time() - strtotime($p['dob'])) / 31557600) : 'N/A';
        ?>
          <tr>
            <td><span class="badge badge-primary"><?= $p['patient_id'] ?></span></td>
            <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
            <td><?= $age ?> / <?= $p['gender'] ?></td>
            <td><span class="badge badge-danger"><?= $p['blood_group'] ?></span></td>
            <td><?= $p['phone'] ?></td>
            <td style="color:var(--text-muted);font-size:13px"><?= htmlspecialchars($p['email'] ?: '—') ?></td>
            <td style="color:var(--text-muted);font-size:13px"><?= date('d M Y', strtotime($p['registered_at'])) ?></td>
            <td>
              <div class="flex gap-2">
                <a href="/patients.php?view=<?= $p['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="View"><i class="fa fa-eye"></i></a>
                <a href="/patients.php?edit=<?= $p['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Edit"><i class="fa fa-pen"></i></a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this patient?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete"><i class="fa fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="padding:16px 22px;border-top:1px solid var(--border)">
      <div class="pagination">
        <?php for($i=1;$i<=$total_pages;$i++): ?>
        <a href="?page=<?=$i?><?= $search?"&search=".urlencode($search):'' ?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
