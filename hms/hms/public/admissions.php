<?php
$page_title = 'Admissions';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        try {
            $db->beginTransaction();
            $bed_id = (int)$_POST['bed_id'];
            $db->prepare("INSERT INTO admissions (patient_id,bed_id,doctor_id,diagnosis,notes) VALUES (?,?,?,?,?)")
               ->execute([(int)$_POST['patient_id'],$bed_id,(int)$_POST['doctor_id'],sanitize($_POST['diagnosis']),sanitize($_POST['notes'])]);
            $db->prepare("UPDATE beds SET status='Occupied' WHERE id=?")->execute([$bed_id]);
            $wid = $db->prepare("SELECT ward_id FROM beds WHERE id=?"); $wid->execute([$bed_id]); $w = $wid->fetch();
            $avail = $db->prepare("SELECT COUNT(*) FROM beds WHERE ward_id=? AND status='Available'"); $avail->execute([$w['ward_id']]);
            $db->prepare("UPDATE wards SET available_beds=? WHERE id=?")->execute([$avail->fetchColumn(),$w['ward_id']]);
            $db->commit();
            $msg = 'Patient admitted successfully!';
        } catch (Exception $e) { $db->rollBack(); $msg='Error: '.$e->getMessage(); $msg_type='danger'; }
    } elseif ($action === 'discharge') {
        try {
            $db->beginTransaction();
            $id = (int)$_POST['id'];
            $adm = $db->prepare("SELECT * FROM admissions WHERE id=?"); $adm->execute([$id]); $a = $adm->fetch();
            $db->prepare("UPDATE admissions SET status='Discharged',discharge_date=NOW() WHERE id=?")->execute([$id]);
            if ($a['bed_id']) {
                $db->prepare("UPDATE beds SET status='Available' WHERE id=?")->execute([$a['bed_id']]);
                $wid = $db->prepare("SELECT ward_id FROM beds WHERE id=?"); $wid->execute([$a['bed_id']]); $w = $wid->fetch();
                $avail = $db->prepare("SELECT COUNT(*) FROM beds WHERE ward_id=? AND status='Available'"); $avail->execute([$w['ward_id']]);
                $db->prepare("UPDATE wards SET available_beds=? WHERE id=?")->execute([$avail->fetchColumn(),$w['ward_id']]);
            }
            $db->commit();
            $msg = 'Patient discharged successfully.';
        } catch (Exception $e) { $db->rollBack(); $msg='Error: '.$e->getMessage(); $msg_type='danger'; }
    }
}

$patients = $db->query("SELECT id,patient_id,full_name FROM patients ORDER BY full_name")->fetchAll();
$doctors  = $db->query("SELECT d.id,u.full_name,d.specialization FROM doctors d JOIN users u ON d.user_id=u.id ORDER BY u.full_name")->fetchAll();
$avail_beds = $db->query("SELECT b.id,b.bed_number,w.ward_name,w.ward_type,w.price_per_day FROM beds b JOIN wards w ON b.ward_id=w.id WHERE b.status='Available' ORDER BY w.ward_name,b.bed_number")->fetchAll();

$filter = $_GET['filter'] ?? 'admitted';
$where  = $filter === 'all' ? "" : "WHERE a.status='".ucfirst($filter)."'";
$admissions = $db->query("SELECT a.*,p.full_name as patient_name,p.patient_id as pid,p.phone,
    u.full_name as doctor_name,b.bed_number,w.ward_name,w.ward_type
    FROM admissions a JOIN patients p ON a.patient_id=p.id
    LEFT JOIN doctors d ON a.doctor_id=d.id LEFT JOIN users u ON d.user_id=u.id
    LEFT JOIN beds b ON a.bed_id=b.id LEFT JOIN wards w ON b.ward_id=w.id
    $where ORDER BY a.admission_date DESC")->fetchAll();

$action_mode = $_GET['action'] ?? '';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msg_type?>"><?=$msg?></div><?php endif; ?>

<?php if ($action_mode === 'add'): ?>
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/admissions.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700">Admit Patient</h2>
  </div>
</div>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="action" value="add">
  <div class="form-grid-2">
    <div class="form-group"><label class="form-label">Patient *</label>
      <select name="patient_id" class="form-select" required>
        <option value="">-- Select Patient --</option>
        <?php foreach($patients as $p): ?><option value="<?=$p['id']?>"><?= htmlspecialchars($p['full_name']) ?> (<?=$p['patient_id']?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Attending Doctor *</label>
      <select name="doctor_id" class="form-select" required>
        <option value="">-- Select Doctor --</option>
        <?php foreach($doctors as $d): ?><option value="<?=$d['id']?>"><?= htmlspecialchars($d['full_name']) ?> — <?=$d['specialization']?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Assign Bed *</label>
      <select name="bed_id" class="form-select" required>
        <option value="">-- Select Available Bed --</option>
        <?php foreach($avail_beds as $b): ?><option value="<?=$b['id']?>"><?=$b['ward_name']?> · <?=$b['bed_number']?> (<?=$b['ward_type']?>) — ₹<?=number_format($b['price_per_day'])?>/day</option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Primary Diagnosis</label><input type="text" name="diagnosis" class="form-control"></div>
  </div>
  <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fa fa-bed"></i> Admit Patient</button>
    <a href="/admissions.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
</div></div>

<?php else: ?>
<div class="section-header">
  <div class="flex gap-2">
    <?php foreach(['admitted'=>'Admitted','discharged'=>'Discharged','all'=>'All'] as $k=>$label): ?>
    <a href="/admissions.php?filter=<?=$k?>" class="btn <?=$filter===$k?'btn-primary':'btn-secondary'?> btn-sm"><?=$label?></a>
    <?php endforeach; ?>
    <span style="color:var(--text-muted);font-size:13px;margin-left:8px"><?=count($admissions)?> records</span>
  </div>
  <a href="/admissions.php?action=add" class="btn btn-primary"><i class="fa fa-bed"></i> Admit Patient</a>
</div>
<div class="card"><div class="card-body" style="padding:0">
  <div class="table-container">
    <table>
      <thead><tr><th>Patient</th><th>Doctor</th><th>Ward / Bed</th><th>Admitted</th><th>Discharged</th><th>Diagnosis</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($admissions)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">No admissions found</td></tr>
      <?php else: foreach($admissions as $a):
        $sc = match($a['status']){'Admitted'=>'badge-danger','Discharged'=>'badge-success',default=>'badge-warning'};
        $days = $a['discharge_date']
            ? (int)((strtotime($a['discharge_date']) - strtotime($a['admission_date'])) / 86400)
            : (int)((time() - strtotime($a['admission_date'])) / 86400);
      ?>
      <tr>
        <td><strong><?= htmlspecialchars($a['patient_name']) ?></strong><br><span style="font-size:12px;color:var(--text-muted)"><?=$a['pid']?> · <?=$a['phone']?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars($a['doctor_name'] ?: '—') ?></td>
        <td><?= htmlspecialchars($a['ward_name'] ?: '—') ?><br><span style="font-size:12px;color:var(--text-muted)"><?=$a['bed_number']?></span></td>
        <td style="font-size:13px"><?= date('d M Y', strtotime($a['admission_date'])) ?><br><span style="font-size:12px;color:var(--text-muted)"><?=$days?> days</span></td>
        <td style="font-size:13px"><?= $a['discharge_date'] ? date('d M Y', strtotime($a['discharge_date'])) : '—' ?></td>
        <td style="font-size:13px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($a['diagnosis'] ?: '—') ?></td>
        <td><span class="badge <?=$sc?>"><?=$a['status']?></span></td>
        <td>
          <?php if ($a['status'] === 'Admitted'): ?>
          <form method="POST" onsubmit="return confirm('Discharge this patient?')">
            <input type="hidden" name="action" value="discharge"><input type="hidden" name="id" value="<?=$a['id']?>">
            <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-sign-out-alt"></i> Discharge</button>
          </form>
          <?php else: ?><span class="text-muted text-sm">Discharged</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
