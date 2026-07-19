<?php
$page_title = 'Departments';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $db->prepare("INSERT INTO departments (name,description) VALUES (?,?)")
           ->execute([sanitize($_POST['name']),sanitize($_POST['description'])]);
        $msg = 'Department added!';
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM departments WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Department deleted.';
    }
}

$departments = $db->query("SELECT d.*,
    (SELECT COUNT(*) FROM doctors dr WHERE dr.department_id=d.id) as doctor_count,
    (SELECT COUNT(*) FROM appointments a JOIN doctors dr ON a.doctor_id=dr.id WHERE dr.department_id=d.id AND a.appointment_date=CURDATE()) as today_appts
    FROM departments d ORDER BY d.name")->fetchAll();

$icons = ['Cardiology'=>'❤️','Neurology'=>'🧠','Orthopedics'=>'🦴','Pediatrics'=>'👶','General Medicine'=>'🩺','Emergency'=>'🚨','Radiology'=>'📷','Pharmacy'=>'💊','Oncology'=>'🔬','Gynecology'=>'🌸','Dermatology'=>'🩹','ENT'=>'👂'];
?>
<?php if ($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>

<div class="section-header">
  <span class="section-title"><?= count($departments) ?> Departments</span>
  <button onclick="document.getElementById('deptModal').classList.add('active')" class="btn btn-primary"><i class="fa fa-plus"></i> Add Department</button>
</div>

<div class="grid-3" style="gap:20px">
<?php foreach($departments as $d): $icon = $icons[$d['name']] ?? '🏥'; ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:28px">
    <div style="font-size:40px;margin-bottom:12px"><?=$icon?></div>
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px"><?= htmlspecialchars($d['name']) ?></h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;min-height:38px"><?= htmlspecialchars($d['description'] ?: 'Hospital Department') ?></p>
    <div style="display:flex;justify-content:center;gap:20px;margin-bottom:16px">
      <div style="text-align:center"><div style="font-size:22px;font-weight:800;color:var(--primary)"><?=$d['doctor_count']?></div><div style="font-size:11px;color:var(--text-muted)">Doctors</div></div>
      <div style="text-align:center"><div style="font-size:22px;font-weight:800;color:var(--success)"><?=$d['today_appts']?></div><div style="font-size:11px;color:var(--text-muted)">Today</div></div>
    </div>
    <form method="POST" onsubmit="return confirm('Delete this department?')" style="display:inline">
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$d['id']?>">
      <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Remove</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="modal-overlay" id="deptModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><span class="modal-title">Add Department</span><button class="modal-close" onclick="document.getElementById('deptModal').classList.remove('active')">×</button></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-group"><label class="form-label">Department Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Cardiology"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Brief description..."></textarea></div>
        <button type="submit" class="btn btn-primary w-full" style="width:100%;justify-content:center"><i class="fa fa-save"></i> Add Department</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
