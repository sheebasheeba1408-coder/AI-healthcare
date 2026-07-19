<?php
$page_title = 'Medical Records';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $db->prepare("INSERT INTO medical_records (patient_id,doctor_id,appointment_id,symptoms,diagnosis,treatment,notes,follow_up_date) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([(int)$_POST['patient_id'],(int)$_POST['doctor_id'],$_POST['appointment_id']?:(null),sanitize($_POST['symptoms']),sanitize($_POST['diagnosis']),sanitize($_POST['treatment']),sanitize($_POST['notes']),$_POST['follow_up_date']?:null]);
        $rid = $db->lastInsertId();
        // Prescriptions
        $meds = $_POST['med_id'] ?? [];
        foreach ($meds as $i => $mid) {
            if (!$mid) continue;
            $db->prepare("INSERT INTO prescriptions (record_id,medicine_id,dosage,frequency,duration,instructions) VALUES (?,?,?,?,?,?)")
               ->execute([$rid,(int)$mid,sanitize($_POST['dosage'][$i]),sanitize($_POST['frequency'][$i]),sanitize($_POST['duration'][$i]),sanitize($_POST['instructions'][$i])]);
        }
        $msg = 'Medical record created!';
    }
}

$patients  = $db->query("SELECT id,patient_id,full_name FROM patients ORDER BY full_name")->fetchAll();
$doctors   = $db->query("SELECT d.id,u.full_name FROM doctors d JOIN users u ON d.user_id=u.id ORDER BY u.full_name")->fetchAll();
$medicines = $db->query("SELECT id,name,unit FROM medicines ORDER BY name")->fetchAll();

$search = sanitize($_GET['search'] ?? '');
$where  = $search ? "WHERE p.full_name LIKE ? OR p.patient_id LIKE ?" : "";
$params = $search ? ["%$search%","%$search%"] : [];

$records = $db->prepare("SELECT mr.*,p.full_name as patient_name,p.patient_id as pid,u.full_name as doctor_name
    FROM medical_records mr JOIN patients p ON mr.patient_id=p.id JOIN doctors d ON mr.doctor_id=d.id JOIN users u ON d.user_id=u.id
    $where ORDER BY mr.visit_date DESC LIMIT 60");
$records->execute($params);
$records = $records->fetchAll();

$view_record = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT mr.*,p.full_name as patient_name,p.patient_id as pid,p.phone,p.dob,p.blood_group,u.full_name as doctor_name
        FROM medical_records mr JOIN patients p ON mr.patient_id=p.id JOIN doctors d ON mr.doctor_id=d.id JOIN users u ON d.user_id=u.id WHERE mr.id=?");
    $stmt->execute([(int)$_GET['view']]);
    $view_record = $stmt->fetch();
    $rxs = $db->prepare("SELECT pr.*,m.name as med_name,m.unit FROM prescriptions pr JOIN medicines m ON pr.medicine_id=m.id WHERE pr.record_id=?");
    $rxs->execute([$view_record['id']]);
    $prescriptions = $rxs->fetchAll();
}

$action_mode = $_GET['action'] ?? '';
?>
<?php if ($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>

<?php if ($action_mode === 'add'): ?>
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/medical_records.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700">New Medical Record</h2>
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
    <div class="form-group"><label class="form-label">Doctor *</label>
      <select name="doctor_id" class="form-select" required>
        <option value="">-- Select Doctor --</option>
        <?php foreach($doctors as $d): ?><option value="<?=$d['id']?>"><?= htmlspecialchars($d['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-grid-2">
    <div class="form-group"><label class="form-label">Symptoms</label><textarea name="symptoms" class="form-control" rows="3" placeholder="Patient's symptoms..."></textarea></div>
    <div class="form-group"><label class="form-label">Diagnosis</label><textarea name="diagnosis" class="form-control" rows="3" placeholder="Medical diagnosis..."></textarea></div>
    <div class="form-group"><label class="form-label">Treatment Plan</label><textarea name="treatment" class="form-control" rows="3" placeholder="Treatment plan..."></textarea></div>
    <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea></div>
  </div>
  <div class="form-group" style="max-width:280px"><label class="form-label">Follow-up Date</label><input type="date" name="follow_up_date" class="form-control"></div>

  <hr class="divider">
  <div style="font-weight:700;font-size:14px;margin-bottom:12px">💊 Prescription</div>
  <div id="rx_list"></div>
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 2fr auto;gap:8px;margin-bottom:8px;font-size:12px;font-weight:600;color:var(--text-muted)">
    <span>Medicine</span><span>Dosage</span><span>Frequency</span><span>Duration</span><span>Instructions</span><span></span>
  </div>
  <div id="rx_rows"></div>
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 2fr auto;gap:8px;margin-bottom:16px" id="rx_input_row">
    <select id="rx_med" class="form-select">
      <option value="">-- Medicine --</option>
      <?php foreach($medicines as $m): ?><option value="<?=$m['id']?>" data-name="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['name']) ?> (<?=$m['unit']?>)</option><?php endforeach; ?>
    </select>
    <input type="text" id="rx_dose" class="form-control" placeholder="e.g. 1 tab">
    <input type="text" id="rx_freq" class="form-control" placeholder="e.g. TDS">
    <input type="text" id="rx_dur" class="form-control" placeholder="e.g. 5 days">
    <input type="text" id="rx_inst" class="form-control" placeholder="After meals...">
    <button type="button" onclick="addRx()" class="btn btn-success"><i class="fa fa-plus"></i></button>
  </div>
  <div id="rx_fields"></div>

  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Record</button>
    <a href="/medical_records.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
</div></div>
<script>
let rxItems = [];
function addRx() {
  const med_id = document.getElementById('rx_med').value;
  const med_name = document.getElementById('rx_med').selectedOptions[0]?.dataset.name;
  const dose = document.getElementById('rx_dose').value.trim();
  const freq = document.getElementById('rx_freq').value.trim();
  const dur  = document.getElementById('rx_dur').value.trim();
  const inst = document.getElementById('rx_inst').value.trim();
  if (!med_id) return alert('Select a medicine');
  rxItems.push({med_id, med_name, dose, freq, dur, inst});
  renderRx();
  document.getElementById('rx_med').value=''; document.getElementById('rx_dose').value='';
  document.getElementById('rx_freq').value=''; document.getElementById('rx_dur').value=''; document.getElementById('rx_inst').value='';
}
function removeRx(i) { rxItems.splice(i,1); renderRx(); }
function renderRx() {
  const c = document.getElementById('rx_rows'); c.innerHTML='';
  const f = document.getElementById('rx_fields'); f.innerHTML='';
  rxItems.forEach((r,i)=>{
    const row = document.createElement('div');
    row.style='display:grid;grid-template-columns:2fr 1fr 1fr 1fr 2fr auto;gap:8px;margin-bottom:6px;background:#f0fdf4;padding:8px;border-radius:6px;align-items:center;font-size:13px';
    row.innerHTML=`<span><strong>${r.med_name}</strong></span><span>${r.dose}</span><span>${r.freq}</span><span>${r.dur}</span><span>${r.inst}</span><button type="button" onclick="removeRx(${i})" class="btn btn-danger btn-sm btn-icon"><i class="fa fa-times"></i></button>`;
    c.appendChild(row);
    f.innerHTML+=`<input type="hidden" name="med_id[]" value="${r.med_id}"><input type="hidden" name="dosage[]" value="${r.dose}"><input type="hidden" name="frequency[]" value="${r.freq}"><input type="hidden" name="duration[]" value="${r.dur}"><input type="hidden" name="instructions[]" value="${r.inst}">`;
  });
}
</script>

<?php elseif ($view_record): ?>
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/medical_records.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700">Medical Record</h2>
    <span style="color:var(--text-muted);font-size:13px"><?= date('d M Y', strtotime($view_record['visit_date'])) ?></span>
  </div>
  <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
</div>
<div class="grid-2" style="gap:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">Patient & Visit Info</span></div>
    <div class="card-body">
      <table style="font-size:14px;width:100%">
        <tr><td style="color:var(--text-muted);padding:5px 0;width:38%">Patient</td><td><strong><?= htmlspecialchars($view_record['patient_name']) ?></strong></td></tr>
        <tr><td style="color:var(--text-muted);padding:5px 0">Patient ID</td><td><?= $view_record['pid'] ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:5px 0">Blood Group</td><td><span class="badge badge-danger"><?= $view_record['blood_group'] ?></span></td></tr>
        <tr><td style="color:var(--text-muted);padding:5px 0">Doctor</td><td><?= htmlspecialchars($view_record['doctor_name']) ?></td></tr>
        <tr><td style="color:var(--text-muted);padding:5px 0">Visit Date</td><td><?= date('d M Y h:i A', strtotime($view_record['visit_date'])) ?></td></tr>
        <?php if ($view_record['follow_up_date']): ?>
        <tr><td style="color:var(--text-muted);padding:5px 0">Follow-up</td><td style="color:var(--warning);font-weight:600"><?= date('d M Y', strtotime($view_record['follow_up_date'])) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Clinical Details</span></div>
    <div class="card-body">
      <?php foreach(['symptoms'=>'Symptoms','diagnosis'=>'Diagnosis','treatment'=>'Treatment','notes'=>'Notes'] as $field=>$label): ?>
      <?php if ($view_record[$field]): ?>
      <div style="margin-bottom:12px"><strong style="font-size:13px;color:var(--text-muted)"><?=$label?></strong><p style="font-size:14px;margin-top:4px"><?= htmlspecialchars($view_record[$field]) ?></p></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php if (!empty($prescriptions)): ?>
<div class="card mt-4">
  <div class="card-header"><span class="card-title">💊 Prescription</span></div>
  <div class="card-body" style="padding:0">
    <table><thead><tr><th>#</th><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead>
    <tbody>
    <?php foreach($prescriptions as $i=>$rx): ?>
    <tr>
      <td style="color:var(--text-muted)"><?=$i+1?></td>
      <td><strong><?= htmlspecialchars($rx['med_name']) ?></strong></td>
      <td><?= htmlspecialchars($rx['dosage']) ?></td>
      <td><span class="badge badge-info"><?= htmlspecialchars($rx['frequency']) ?></span></td>
      <td><?= htmlspecialchars($rx['duration']) ?></td>
      <td style="color:var(--text-muted);font-size:13px"><?= htmlspecialchars($rx['instructions']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="section-header">
  <div class="search-bar">
    <span class="search-icon"><i class="fa fa-search"></i></span>
    <form method="GET"><input type="text" name="search" class="form-control" placeholder="Search by patient..." value="<?= htmlspecialchars($search) ?>"></form>
  </div>
  <a href="/medical_records.php?action=add" class="btn btn-primary"><i class="fa fa-file-medical"></i> New Record</a>
</div>
<div class="card"><div class="card-body" style="padding:0">
  <div class="table-container">
    <table>
      <thead><tr><th>Patient</th><th>Doctor</th><th>Diagnosis</th><th>Treatment</th><th>Visit Date</th><th>Follow-up</th><th>Rx</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($records)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">No medical records found</td></tr>
      <?php else: foreach($records as $r): ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['patient_name']) ?></strong><br><span style="font-size:12px;color:var(--text-muted)"><?=$r['pid']?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars($r['doctor_name']) ?></td>
        <td style="font-size:13px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['diagnosis'] ?: '—') ?></td>
        <td style="font-size:13px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['treatment'] ?: '—') ?></td>
        <td style="font-size:13px"><?= date('d M Y', strtotime($r['visit_date'])) ?></td>
        <td style="font-size:13px;<?= $r['follow_up_date'] ? 'color:var(--warning);font-weight:600' : 'color:var(--text-muted)' ?>"><?= $r['follow_up_date'] ? date('d M Y', strtotime($r['follow_up_date'])) : '—' ?></td>
        <td><?php $rxc=$db->prepare("SELECT COUNT(*) FROM prescriptions WHERE record_id=?"); $rxc->execute([$r['id']]); $cnt=$rxc->fetchColumn(); ?><span class="badge <?=$cnt?'badge-success':'badge-secondary'?>"><?=$cnt?></span></td>
        <td><a href="/medical_records.php?view=<?=$r['id']?>" class="btn btn-secondary btn-sm"><i class="fa fa-eye"></i></a></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
