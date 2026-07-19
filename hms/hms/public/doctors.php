<?php
$page_title = 'Doctors';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        try {
            $db->beginTransaction();
            $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (username,password,full_name,email,role,phone) VALUES (?,?,?,?,?,?)")
               ->execute([$_POST['username'],$pass,sanitize($_POST['full_name']),sanitize($_POST['email']),'doctor',sanitize($_POST['phone'])]);
            $uid = $db->lastInsertId();
            $db->prepare("INSERT INTO doctors (user_id,department_id,specialization,qualification,experience_years,consultation_fee,available_days) VALUES (?,?,?,?,?,?,?)")
               ->execute([$uid,(int)$_POST['department_id'],sanitize($_POST['specialization']),sanitize($_POST['qualification']),(int)$_POST['experience'],floatval($_POST['fee']),$_POST['available_days']]);
            $db->commit();
            $msg = 'Doctor added successfully!';
        } catch (Exception $e) { $db->rollBack(); $msg = 'Error: ' . $e->getMessage(); $msg_type = 'danger'; }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM users WHERE id=(SELECT user_id FROM doctors WHERE id=?)")->execute([(int)$_POST['id']]);
        $msg = 'Doctor removed.'; $msg_type = 'warning';
    }
}

$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$doctors = $db->query("SELECT d.*, u.full_name, u.email, u.phone, u.username, u.is_active, dept.name as dept_name
    FROM doctors d JOIN users u ON d.user_id=u.id LEFT JOIN departments dept ON d.department_id=dept.id ORDER BY u.full_name")->fetchAll();

$action_mode = $_GET['action'] ?? '';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msg_type?>"><?=$msg?></div><?php endif; ?>

<?php if ($action_mode === 'add'): ?>
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/doctors.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700">Add New Doctor</h2>
  </div>
</div>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="action" value="add">
  <div style="font-weight:700;font-size:14px;margin-bottom:12px">Account Details</div>
  <div class="form-grid-3">
    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required placeholder="Dr. John Smith"></div>
    <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required placeholder="dr.john"></div>
    <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
    <div class="form-group"><label class="form-label">Department</label>
      <select name="department_id" class="form-select"><option value="">-- Select --</option>
        <?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <hr class="divider">
  <div style="font-weight:700;font-size:14px;margin-bottom:12px">Professional Details</div>
  <div class="form-grid-3">
    <div class="form-group"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control"></div>
    <div class="form-group"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control" placeholder="MBBS, MD..."></div>
    <div class="form-group"><label class="form-label">Experience (years)</label><input type="number" name="experience" class="form-control" value="0" min="0"></div>
    <div class="form-group"><label class="form-label">Consultation Fee (₹)</label><input type="number" name="fee" class="form-control" value="500" min="0" step="0.01"></div>
    <div class="form-group"><label class="form-label">Available Days</label><input type="text" name="available_days" class="form-control" value="Mon,Tue,Wed,Thu,Fri" placeholder="Mon,Tue,Wed..."></div>
  </div>
  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Doctor</button>
    <a href="/doctors.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
</div></div>

<?php else: ?>
<div class="section-header">
  <span style="color:var(--text-muted);font-size:13px"><?= count($doctors) ?> doctors</span>
  <a href="/doctors.php?action=add" class="btn btn-primary"><i class="fa fa-user-plus"></i> Add Doctor</a>
</div>
<div class="grid-3" style="gap:20px">
<?php foreach($doctors as $doc): ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:24px">
    <div class="avatar" style="width:60px;height:60px;font-size:20px;margin:0 auto 12px;background:linear-gradient(135deg,var(--primary),var(--accent))"><?= strtoupper(substr($doc['full_name'],3,1)) ?></div>
    <h3 style="font-size:15px;font-weight:700"><?= htmlspecialchars($doc['full_name']) ?></h3>
    <p style="font-size:13px;color:var(--primary);font-weight:600"><?= htmlspecialchars($doc['specialization'] ?: 'General') ?></p>
    <p style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($doc['dept_name'] ?: 'N/A') ?></p>
    <hr class="divider" style="margin:12px 0">
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted)">
      <span><i class="fa fa-graduation-cap"></i> <?= $doc['experience_years'] ?> yrs</span>
      <span><i class="fa fa-rupee-sign"></i> ₹<?= number_format($doc['consultation_fee']) ?></span>
    </div>
    <div style="margin-top:8px;font-size:12px;color:var(--text-muted)"><i class="fa fa-phone"></i> <?= $doc['phone'] ?: 'N/A' ?></div>
    <div style="margin-top:4px;font-size:12px;color:var(--text-muted)"><i class="fa fa-calendar"></i> <?= $doc['available_days'] ?></div>
    <hr class="divider" style="margin:12px 0">
    <div class="flex gap-2" style="justify-content:center">
      <span class="badge <?= $doc['is_active']?'badge-success':'badge-danger' ?>"><?= $doc['is_active']?'Active':'Inactive' ?></span>
      <form method="POST" onsubmit="return confirm('Remove this doctor?')" style="display:inline">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $doc['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
