<?php
$page_title = 'Wards & Beds';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_ward') {
        $name = sanitize($_POST['ward_name']); $type = $_POST['ward_type'];
        $total = (int)$_POST['total_beds']; $price = floatval($_POST['price_per_day']); $floor = (int)$_POST['floor_number'];
        $db->prepare("INSERT INTO wards (ward_name,ward_type,total_beds,available_beds,price_per_day,floor_number) VALUES (?,?,?,?,?,?)")->execute([$name,$type,$total,$total,$price,$floor]);
        // Auto-create beds
        for ($i = 1; $i <= $total; $i++) {
            $bed_num = strtoupper(substr($name,0,2)) . '-' . str_pad($i,3,'0',STR_PAD_LEFT);
            $db->prepare("INSERT INTO beds (ward_id,bed_number,status) VALUES (?,?,'Available')")->execute([$db->lastInsertId(),$bed_num]);
        }
        $msg = "Ward $name created with $total beds.";
    } elseif ($action === 'bed_status') {
        $bid = (int)$_POST['bed_id']; $status = $_POST['status'];
        $db->prepare("UPDATE beds SET status=? WHERE id=?")->execute([$status,$bid]);
        // Update ward available count
        $wid = $db->prepare("SELECT ward_id FROM beds WHERE id=?"); $wid->execute([$bid]); $w = $wid->fetch();
        $avail = $db->prepare("SELECT COUNT(*) FROM beds WHERE ward_id=? AND status='Available'"); $avail->execute([$w['ward_id']]);
        $db->prepare("UPDATE wards SET available_beds=? WHERE id=?")->execute([$avail->fetchColumn(),$w['ward_id']]);
        $msg = 'Bed status updated.';
    }
}

$wards = $db->query("SELECT w.*, (SELECT COUNT(*) FROM beds WHERE ward_id=w.id AND status='Occupied') as occupied_count FROM wards w ORDER BY floor_number,ward_name")->fetchAll();
$selected_ward = isset($_GET['ward']) ? (int)$_GET['ward'] : ($wards[0]['id'] ?? null);
$beds = [];
if ($selected_ward) {
    $stmt = $db->prepare("SELECT b.*, (SELECT p.full_name FROM admissions a JOIN patients p ON a.patient_id=p.id WHERE a.bed_id=b.id AND a.status='Admitted' LIMIT 1) as patient_name FROM beds b WHERE b.ward_id=? ORDER BY b.bed_number");
    $stmt->execute([$selected_ward]);
    $beds = $stmt->fetchAll();
}
?>
<?php if ($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>

<div class="section-header">
  <span class="section-title">Ward Overview</span>
  <button onclick="document.getElementById('wardModal').classList.add('active')" class="btn btn-primary"><i class="fa fa-plus"></i> Add Ward</button>
</div>

<div class="grid-3" style="margin-bottom:24px">
<?php foreach ($wards as $w):
  $occ_pct = $w['total_beds'] > 0 ? round(($w['occupied_count']/$w['total_beds'])*100) : 0;
  $color = $occ_pct >= 90 ? 'red' : ($occ_pct >= 70 ? 'orange' : 'green');
  $active = $w['id'] == $selected_ward;
?>
<a href="/wards.php?ward=<?=$w['id']?>" style="text-decoration:none">
<div class="card" style="<?= $active?'border:2px solid var(--primary)':'' ?>;cursor:pointer">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div>
        <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($w['ward_name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted)"><?= $w['ward_type'] ?> · Floor <?= $w['floor_number'] ?></div>
      </div>
      <span class="badge <?= match($w['ward_type']){'ICU'=>'badge-danger','Emergency'=>'badge-warning','Private'=>'badge-info',default=>'badge-primary'} ?>"><?= $w['ward_type'] ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
      <span><?= $w['occupied_count'] ?> / <?= $w['total_beds'] ?> occupied</span>
      <span style="font-weight:600"><?= $occ_pct ?>%</span>
    </div>
    <div class="progress"><div class="progress-bar <?=$color?>" style="width:<?=$occ_pct?>%"></div></div>
    <div style="margin-top:10px;font-size:12px;color:var(--text-muted)">₹<?= number_format($w['price_per_day']) ?>/day · <?= $w['available_beds'] ?> available</div>
  </div>
</div>
</a>
<?php endforeach; ?>
</div>

<!-- Beds Grid -->
<?php if ($selected_ward && !empty($beds)):
  $ward_info = array_values(array_filter($wards, fn($w) => $w['id'] == $selected_ward))[0] ?? null;
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Beds — <?= htmlspecialchars($ward_info['ward_name'] ?? '') ?></span>
    <div class="flex gap-2">
      <span class="badge badge-success">● Available</span>
      <span class="badge badge-danger">● Occupied</span>
      <span class="badge badge-warning">● Maintenance</span>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">
    <?php foreach ($beds as $bed): 
      $color = match($bed['status']){'Available'=>'#ecfdf5','Occupied'=>'#fef2f2','Maintenance'=>'#fffbeb',default=>'#f8fafc'};
      $border = match($bed['status']){'Available'=>'#a7f3d0','Occupied'=>'#fca5a5','Maintenance'=>'#fde68a',default=>'#e2e8f0'};
      $icon = match($bed['status']){'Available'=>'🟢','Occupied'=>'🔴','Maintenance'=>'🟡',default=>'⚪'};
    ?>
    <div style="background:<?=$color?>;border:2px solid <?=$border?>;border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:20px;margin-bottom:6px"><?=$icon?></div>
      <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($bed['bed_number']) ?></div>
      <div style="font-size:11px;color:var(--text-muted);margin:4px 0"><?= $bed['status'] ?></div>
      <?php if ($bed['patient_name']): ?><div style="font-size:11px;font-weight:600;color:var(--text)"><?= htmlspecialchars($bed['patient_name']) ?></div><?php endif; ?>
      <form method="POST" style="margin-top:8px">
        <input type="hidden" name="action" value="bed_status"><input type="hidden" name="bed_id" value="<?=$bed['id']?>">
        <select name="status" class="form-select" style="font-size:11px;padding:3px;height:24px" onchange="this.form.submit()">
          <?php foreach(['Available','Occupied','Maintenance'] as $s): ?><option <?= $bed['status']===$s?'selected':'' ?>><?=$s?></option><?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Add Ward Modal -->
<div class="modal-overlay" id="wardModal">
  <div class="modal"><div class="modal-header"><span class="modal-title">Add New Ward</span><button class="modal-close" onclick="document.getElementById('wardModal').classList.remove('active')">×</button></div>
  <div class="modal-body">
    <form method="POST">
      <input type="hidden" name="action" value="add_ward">
      <div class="form-grid-2">
        <div class="form-group"><label class="form-label">Ward Name</label><input type="text" name="ward_name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Ward Type</label>
          <select name="ward_type" class="form-select"><?php foreach(['General','ICU','Private','Semi-Private','Emergency'] as $t): ?><option><?=$t?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label class="form-label">Total Beds</label><input type="number" name="total_beds" class="form-control" value="10" min="1" required></div>
        <div class="form-group"><label class="form-label">Price Per Day (₹)</label><input type="number" name="price_per_day" class="form-control" value="1000" min="0" step="0.01"></div>
        <div class="form-group"><label class="form-label">Floor Number</label><input type="number" name="floor_number" class="form-control" value="1" min="1"></div>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="width:100%;justify-content:center"><i class="fa fa-save"></i> Create Ward</button>
    </form>
  </div></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
