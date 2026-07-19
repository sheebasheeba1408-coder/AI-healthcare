<?php
$page_title = 'Pharmacy';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'name' => sanitize($_POST['name']),
            'generic_name' => sanitize($_POST['generic_name']),
            'category' => sanitize($_POST['category']),
            'manufacturer' => sanitize($_POST['manufacturer']),
            'unit' => sanitize($_POST['unit']),
            'unit_price' => floatval($_POST['unit_price']),
            'stock_quantity' => (int)$_POST['stock_quantity'],
            'reorder_level' => (int)$_POST['reorder_level'],
            'expiry_date' => $_POST['expiry_date'] ?: null,
            'description' => sanitize($_POST['description']),
        ];
        if ($action === 'add') {
            $cols = implode(',', array_keys($data));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO medicines ($cols) VALUES ($vals)")->execute(array_values($data));
            $msg = 'Medicine added!';
        } else {
            $id = (int)$_POST['id'];
            $sets = implode(',', array_map(fn($k)=>"$k=?", array_keys($data)));
            $db->prepare("UPDATE medicines SET $sets WHERE id=?")->execute([...array_values($data), $id]);
            $msg = 'Medicine updated.';
        }
    } elseif ($action === 'stock') {
        $id = (int)$_POST['id']; $qty = (int)$_POST['qty'];
        $db->prepare("UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id=?")->execute([$qty, $id]);
        $msg = "Stock updated by +$qty";
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM medicines WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Medicine deleted.'; $msg_type = 'warning';
    }
}

$search = sanitize($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (name LIKE ? OR generic_name LIKE ? OR category LIKE ?)"; $params = array_fill(0, 3, "%$search%"); }
if ($filter === 'low') { $where .= " AND stock_quantity <= reorder_level"; }
if ($filter === 'expired') { $where .= " AND expiry_date < CURDATE()"; }

$stmt = $db->prepare("SELECT * FROM medicines $where ORDER BY name");
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$total_value = array_sum(array_map(fn($m) => $m['stock_quantity'] * $m['unit_price'], $medicines));
$low_count = $db->query("SELECT COUNT(*) FROM medicines WHERE stock_quantity <= reorder_level")->fetchColumn();

$action_mode = $_GET['action'] ?? '';
$edit_med = null;
if (isset($_GET['edit'])) { $stmt = $db->prepare("SELECT * FROM medicines WHERE id=?"); $stmt->execute([(int)$_GET['edit']]); $edit_med = $stmt->fetch(); }
?>
<?php if ($msg): ?><div class="alert alert-<?=$msg_type?>"><?=$msg?></div><?php endif; ?>

<?php if ($action_mode === 'add' || $edit_med): ?>
<div class="section-header">
  <div class="flex flex-center gap-3"><a href="/pharmacy.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a><h2 style="font-size:17px;font-weight:700"><?= $edit_med ? 'Edit Medicine' : 'Add Medicine' ?></h2></div>
</div>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="action" value="<?= $edit_med ? 'edit' : 'add' ?>">
  <?php if ($edit_med): ?><input type="hidden" name="id" value="<?= $edit_med['id'] ?>"><?php endif; ?>
  <div class="form-grid-3">
    <div class="form-group"><label class="form-label">Medicine Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_med['name'] ?? '') ?>" required></div>
    <div class="form-group"><label class="form-label">Generic Name</label><input type="text" name="generic_name" class="form-control" value="<?= htmlspecialchars($edit_med['generic_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Category</label><input type="text" name="category" class="form-control" value="<?= htmlspecialchars($edit_med['category'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Manufacturer</label><input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars($edit_med['manufacturer'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Unit</label>
      <select name="unit" class="form-select">
        <?php foreach(['Strip','Tablet','Capsule','Bottle','Vial','Injection','Syrup','Cream','Ointment','Sachet','Other'] as $u): ?><option <?= ($edit_med['unit']??'Strip')===$u?'selected':'' ?>><?=$u?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Unit Price (₹) *</label><input type="number" name="unit_price" class="form-control" value="<?= $edit_med['unit_price'] ?? 0 ?>" min="0" step="0.01" required></div>
    <div class="form-group"><label class="form-label">Stock Quantity</label><input type="number" name="stock_quantity" class="form-control" value="<?= $edit_med['stock_quantity'] ?? 0 ?>" min="0"></div>
    <div class="form-group"><label class="form-label">Reorder Level</label><input type="number" name="reorder_level" class="form-control" value="<?= $edit_med['reorder_level'] ?? 10 ?>" min="0"></div>
    <div class="form-group"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control" value="<?= $edit_med['expiry_date'] ?? '' ?>"></div>
  </div>
  <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_med['description'] ?? '') ?></textarea></div>
  <div class="flex gap-2"><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button><a href="/pharmacy.php" class="btn btn-secondary">Cancel</a></div>
</form>
</div></div>

<?php else: ?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fa fa-pills"></i></div><div><div class="stat-value"><?= count($medicines) ?></div><div class="stat-label">Total Medicines</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fa fa-triangle-exclamation"></i></div><div><div class="stat-value"><?= $low_count ?></div><div class="stat-label">Low Stock</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa fa-rupee-sign"></i></div><div><div class="stat-value">₹<?= number_format($total_value) ?></div><div class="stat-label">Stock Value</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa fa-box"></i></div><div><div class="stat-value"><?= array_sum(array_column($medicines,'stock_quantity')) ?></div><div class="stat-label">Total Units</div></div></div>
</div>
<div class="section-header">
  <div class="flex flex-center gap-2">
    <form method="GET" class="flex gap-2">
      <div class="search-bar"><span class="search-icon"><i class="fa fa-search"></i></span><input type="text" name="search" class="form-control" placeholder="Search medicines..." value="<?= htmlspecialchars($search) ?>"></div>
      <select name="filter" class="form-select" style="width:150px" onchange="this.form.submit()">
        <option value="" <?= !$filter?'selected':'' ?>>All</option>
        <option value="low" <?= $filter==='low'?'selected':'' ?>>Low Stock</option>
        <option value="expired" <?= $filter==='expired'?'selected':'' ?>>Expired</option>
      </select>
      <button type="submit" class="btn btn-secondary"><i class="fa fa-filter"></i></button>
    </form>
  </div>
  <a href="/pharmacy.php?action=add" class="btn btn-primary"><i class="fa fa-plus"></i> Add Medicine</a>
</div>
<div class="card"><div class="card-body" style="padding:0">
  <div class="table-container">
    <table>
      <thead><tr><th>Name</th><th>Generic</th><th>Category</th><th>Unit</th><th>Price</th><th>Stock</th><th>Reorder</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($medicines as $m): 
        $is_low = $m['stock_quantity'] <= $m['reorder_level'];
        $is_expired = $m['expiry_date'] && $m['expiry_date'] < date('Y-m-d');
        $stock_pct = $m['reorder_level'] > 0 ? min(100, ($m['stock_quantity'] / ($m['reorder_level'] * 3)) * 100) : 100;
      ?>
      <tr>
        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
        <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($m['generic_name'] ?: '—') ?></td>
        <td><span class="badge badge-info"><?= htmlspecialchars($m['category'] ?: '—') ?></span></td>
        <td style="font-size:13px"><?= $m['unit'] ?></td>
        <td style="font-weight:600">₹<?= number_format($m['unit_price'],2) ?></td>
        <td>
          <div style="font-weight:700;<?= $is_low?'color:var(--danger)':'' ?>"><?= number_format($m['stock_quantity']) ?></div>
          <div class="progress" style="margin-top:4px;width:80px"><div class="progress-bar <?= $is_low?'red':($stock_pct<60?'orange':'green') ?>" style="width:<?= $stock_pct ?>%"></div></div>
        </td>
        <td style="font-size:13px;color:var(--text-muted)"><?= $m['reorder_level'] ?></td>
        <td style="font-size:13px;<?= $is_expired?'color:var(--danger);font-weight:600':'' ?>"><?= $m['expiry_date'] ? date('M Y', strtotime($m['expiry_date'])) : '—' ?></td>
        <td>
          <?php if ($is_expired): ?><span class="badge badge-danger">Expired</span>
          <?php elseif ($is_low): ?><span class="badge badge-warning">Low Stock</span>
          <?php else: ?><span class="badge badge-success">In Stock</span><?php endif; ?>
        </td>
        <td>
          <div class="flex gap-2">
            <button onclick="showRestock(<?=$m['id']?>,<?=$m['stock_quantity']?>)" class="btn btn-success btn-sm btn-icon" title="Restock"><i class="fa fa-plus"></i></button>
            <a href="/pharmacy.php?edit=<?=$m['id']?>" class="btn btn-primary btn-sm btn-icon"><i class="fa fa-pen"></i></a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="fa fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<!-- Restock Modal -->
<div class="modal-overlay" id="restockModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><span class="modal-title">Restock Medicine</span><button class="modal-close" onclick="closeModal()">×</button></div>
    <div class="modal-body">
      <form method="POST" id="restockForm">
        <input type="hidden" name="action" value="stock"><input type="hidden" name="id" id="restock_id">
        <p id="restock_info" style="color:var(--text-muted);font-size:14px;margin-bottom:16px"></p>
        <div class="form-group"><label class="form-label">Add Quantity</label><input type="number" name="qty" class="form-control" min="1" value="50" required></div>
        <div class="modal-footer" style="padding:0;margin-top:16px"><button type="submit" class="btn btn-success w-full" style="width:100%;justify-content:center"><i class="fa fa-plus"></i> Update Stock</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function showRestock(id, current) {
  document.getElementById('restock_id').value = id;
  document.getElementById('restock_info').textContent = 'Current stock: ' + current + ' units';
  document.getElementById('restockModal').classList.add('active');
}
function closeModal() { document.getElementById('restockModal').classList.remove('active'); }
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
