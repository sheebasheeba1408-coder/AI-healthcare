<?php
$page_title = 'Laboratory';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'order') {
        $db->beginTransaction();
        $db->prepare("INSERT INTO lab_orders (patient_id,doctor_id,notes) VALUES (?,?,?)")
           ->execute([(int)$_POST['patient_id'],(int)$_POST['doctor_id'],sanitize($_POST['notes'])]);
        $oid = $db->lastInsertId();
        foreach ($_POST['test_ids'] ?? [] as $tid) {
            $db->prepare("INSERT INTO lab_order_items (order_id,test_id) VALUES (?,?)")->execute([$oid,(int)$tid]);
        }
        $db->commit();
        $msg = 'Lab order created!';
    } elseif ($action === 'result') {
        $db->prepare("UPDATE lab_order_items SET result=?,result_date=NOW() WHERE id=?")
           ->execute([sanitize($_POST['result']),(int)$_POST['item_id']]);
        // Check if all items completed
        $oid = (int)$_POST['order_id'];
        $pending = $db->prepare("SELECT COUNT(*) FROM lab_order_items WHERE order_id=? AND result IS NULL");
        $pending->execute([$oid]);
        if ($pending->fetchColumn() == 0) {
            $db->prepare("UPDATE lab_orders SET status='Completed' WHERE id=?")->execute([$oid]);
        } else {
            $db->prepare("UPDATE lab_orders SET status='Processing' WHERE id=?")->execute([$oid]);
        }
        $msg = 'Result entered.';
    } elseif ($action === 'add_test') {
        $db->prepare("INSERT INTO lab_tests (name,description,price,normal_range,category) VALUES (?,?,?,?,?)")
           ->execute([sanitize($_POST['name']),sanitize($_POST['desc']),floatval($_POST['price']),sanitize($_POST['normal_range']),sanitize($_POST['category'])]);
        $msg = 'Test added!';
    }
}

$patients = $db->query("SELECT id,patient_id,full_name FROM patients ORDER BY full_name")->fetchAll();
$doctors  = $db->query("SELECT d.id,u.full_name FROM doctors d JOIN users u ON d.user_id=u.id ORDER BY u.full_name")->fetchAll();
$tests    = $db->query("SELECT * FROM lab_tests ORDER BY category,name")->fetchAll();

$orders = $db->query("SELECT lo.*,p.full_name as patient_name,p.patient_id as pid,u.full_name as doctor_name,
    COUNT(loi.id) as total_items, SUM(CASE WHEN loi.result IS NOT NULL THEN 1 ELSE 0 END) as done_items,
    SUM(lt.price) as total_price
    FROM lab_orders lo JOIN patients p ON lo.patient_id=p.id
    JOIN doctors d ON lo.doctor_id=d.id JOIN users u ON d.user_id=u.id
    JOIN lab_order_items loi ON lo.id=loi.order_id JOIN lab_tests lt ON loi.test_id=lt.id
    GROUP BY lo.id ORDER BY lo.order_date DESC LIMIT 50")->fetchAll();

$view_order = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT lo.*,p.full_name as patient_name,p.patient_id as pid,u.full_name as doctor_name FROM lab_orders lo JOIN patients p ON lo.patient_id=p.id JOIN doctors d ON lo.doctor_id=d.id JOIN users u ON d.user_id=u.id WHERE lo.id=?");
    $stmt->execute([(int)$_GET['view']]);
    $view_order = $stmt->fetch();
    $items_stmt = $db->prepare("SELECT loi.*,lt.name as test_name,lt.price,lt.normal_range,lt.category FROM lab_order_items loi JOIN lab_tests lt ON loi.test_id=lt.id WHERE loi.order_id=?");
    $items_stmt->execute([$view_order['id']]);
    $order_items = $items_stmt->fetchAll();
}
$action_mode = $_GET['action'] ?? '';
?>
<?php if ($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>

<?php if ($action_mode === 'order'): ?>
<div class="section-header">
  <div class="flex flex-center gap-3"><a href="/lab.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a><h2 style="font-size:17px;font-weight:700">New Lab Order</h2></div>
</div>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="action" value="order">
  <div class="form-grid-2">
    <div class="form-group"><label class="form-label">Patient *</label>
      <select name="patient_id" class="form-select" required>
        <option value="">-- Select Patient --</option>
        <?php foreach($patients as $p): ?><option value="<?=$p['id']?>"><?= htmlspecialchars($p['full_name']) ?> (<?=$p['patient_id']?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Ordered By *</label>
      <select name="doctor_id" class="form-select" required>
        <option value="">-- Select Doctor --</option>
        <?php foreach($doctors as $d): ?><option value="<?=$d['id']?>"><?= htmlspecialchars($d['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group"><label class="form-label">Select Tests *</label>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;padding:16px;background:#f8fafc;border-radius:10px;border:1px solid var(--border)">
      <?php foreach($tests as $t): ?>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:13px">
        <input type="checkbox" name="test_ids[]" value="<?=$t['id']?>" style="width:16px;height:16px;accent-color:var(--primary)">
        <span>
          <strong><?= htmlspecialchars($t['name']) ?></strong><br>
          <span style="color:var(--text-muted)"><?=$t['category']?> · ₹<?=number_format($t['price'],2)?></span>
        </span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
  <div class="flex gap-2"><button type="submit" class="btn btn-primary"><i class="fa fa-flask"></i> Create Order</button><a href="/lab.php" class="btn btn-secondary">Cancel</a></div>
</form>
</div></div>

<?php elseif ($view_order): ?>
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/lab.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700">Lab Order #<?=$view_order['id']?></h2>
    <span class="badge <?= match($view_order['status']){'Completed'=>'badge-success','Processing'=>'badge-warning',default=>'badge-secondary'} ?>"><?=$view_order['status']?></span>
  </div>
  <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Print</button>
</div>
<div class="card" style="margin-bottom:20px">
  <div class="card-body">
    <div style="display:flex;gap:40px;flex-wrap:wrap;font-size:14px">
      <div><span style="color:var(--text-muted)">Patient:</span> <strong><?= htmlspecialchars($view_order['patient_name']) ?></strong> (<?=$view_order['pid']?>)</div>
      <div><span style="color:var(--text-muted)">Doctor:</span> <strong><?= htmlspecialchars($view_order['doctor_name']) ?></strong></div>
      <div><span style="color:var(--text-muted)">Date:</span> <?= date('d M Y h:i A', strtotime($view_order['order_date'])) ?></div>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header"><span class="card-title">Test Results</span></div>
  <div class="card-body" style="padding:0">
    <table>
      <thead><tr><th>Test</th><th>Category</th><th>Normal Range</th><th>Result</th><th>Price</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($order_items as $item): ?>
      <tr>
        <td><strong><?= htmlspecialchars($item['test_name']) ?></strong></td>
        <td><span class="badge badge-info"><?=$item['category']?></span></td>
        <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($item['normal_range'] ?: '—') ?></td>
        <td>
          <?php if ($item['result']): ?>
            <span style="font-weight:600"><?= htmlspecialchars($item['result']) ?></span>
            <?php if ($item['result_date']): ?><br><span style="font-size:11px;color:var(--text-muted)"><?= date('d M Y H:i', strtotime($item['result_date'])) ?></span><?php endif; ?>
          <?php else: ?><span style="color:var(--text-muted)">Pending</span><?php endif; ?>
        </td>
        <td>₹<?= number_format($item['price'],2) ?></td>
        <td>
          <?php if (!$item['result']): ?>
          <button onclick="showResultModal(<?=$item['id']?>,<?=$view_order['id']?>,'<?= htmlspecialchars($item['test_name'],ENT_QUOTES) ?>')" class="btn btn-success btn-sm"><i class="fa fa-pen"></i> Enter Result</button>
          <?php else: ?><span class="badge badge-success">Done</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Result Entry Modal -->
<div class="modal-overlay" id="resultModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><span class="modal-title" id="result_modal_title">Enter Result</span><button class="modal-close" onclick="document.getElementById('resultModal').classList.remove('active')">×</button></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="result">
        <input type="hidden" name="item_id" id="result_item_id">
        <input type="hidden" name="order_id" value="<?=$view_order['id']?>">
        <div class="form-group"><label class="form-label">Result</label><textarea name="result" class="form-control" rows="4" placeholder="Enter test result value..." required></textarea></div>
        <button type="submit" class="btn btn-success w-full" style="width:100%;justify-content:center"><i class="fa fa-save"></i> Save Result</button>
      </form>
    </div>
  </div>
</div>
<script>
function showResultModal(itemId, orderId, testName) {
  document.getElementById('result_item_id').value = itemId;
  document.getElementById('result_modal_title').textContent = 'Result: ' + testName;
  document.getElementById('resultModal').classList.add('active');
}
</script>

<?php else: ?>
<div class="section-header">
  <div class="flex gap-2">
    <span style="color:var(--text-muted);font-size:13px"><?= count($orders) ?> orders</span>
  </div>
  <div class="flex gap-2">
    <button onclick="document.getElementById('addTestModal').classList.add('active')" class="btn btn-secondary"><i class="fa fa-plus"></i> Add Test</button>
    <a href="/lab.php?action=order" class="btn btn-primary"><i class="fa fa-flask"></i> New Order</a>
  </div>
</div>
<div class="card"><div class="card-body" style="padding:0">
  <div class="table-container">
    <table>
      <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Tests</th><th>Total Price</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($orders)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">No lab orders found</td></tr>
      <?php else: foreach($orders as $o):
        $sc = match($o['status']){'Completed'=>'badge-success','Processing'=>'badge-warning','Cancelled'=>'badge-danger',default=>'badge-secondary'};
      ?>
      <tr>
        <td style="color:var(--text-muted)">#<?=$o['id']?></td>
        <td><strong><?= htmlspecialchars($o['patient_name']) ?></strong><br><span style="font-size:12px;color:var(--text-muted)"><?=$o['pid']?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars($o['doctor_name']) ?></td>
        <td><span class="badge badge-primary"><?=$o['done_items']?>/<?=$o['total_items']?> done</span></td>
        <td style="font-weight:600">₹<?= number_format($o['total_price'],2) ?></td>
        <td style="font-size:13px"><?= date('d M Y', strtotime($o['order_date'])) ?></td>
        <td><span class="badge <?=$sc?>"><?=$o['status']?></span></td>
        <td><a href="/lab.php?view=<?=$o['id']?>" class="btn btn-secondary btn-sm"><i class="fa fa-eye"></i> View</a></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div></div>

<!-- Add Test Modal -->
<div class="modal-overlay" id="addTestModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header"><span class="modal-title">Add Lab Test</span><button class="modal-close" onclick="document.getElementById('addTestModal').classList.remove('active')">×</button></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_test">
        <div class="form-grid-2">
          <div class="form-group"><label class="form-label">Test Name *</label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Hematology"></div>
          <div class="form-group"><label class="form-label">Price (₹) *</label><input type="number" name="price" class="form-control" min="0" step="0.01" required></div>
          <div class="form-group"><label class="form-label">Normal Range</label><input type="text" name="normal_range" class="form-control" placeholder="e.g. 70-99 mg/dL"></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="desc" class="form-control" rows="2"></textarea></div>
        <button type="submit" class="btn btn-primary w-full" style="width:100%;justify-content:center"><i class="fa fa-save"></i> Add Test</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
