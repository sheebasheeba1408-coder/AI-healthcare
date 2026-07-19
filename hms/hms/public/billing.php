<?php
$page_title = 'Billing';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        try {
            $db->beginTransaction();
            $bill_number = generateBillNumber();
            $patient_id = (int)$_POST['patient_id'];
            $items = json_decode($_POST['items_json'], true);
            $discount = floatval($_POST['discount']);
            $payment_method = $_POST['payment_method'];
            $notes = sanitize($_POST['notes']);

            $subtotal = 0;
            foreach ($items as $item) $subtotal += $item['qty'] * $item['price'];
            $tax = round(($subtotal - $discount) * 0.05, 2);
            $total = $subtotal - $discount + $tax;
            $paid = floatval($_POST['paid_amount']);
            $status = $paid >= $total ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');

            $db->prepare("INSERT INTO bills (bill_number,patient_id,subtotal,discount,tax,total_amount,paid_amount,status,payment_method,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$bill_number,$patient_id,$subtotal,$discount,$tax,$total,$paid,$status,$payment_method,$notes]);
            $bid = $db->lastInsertId();

            foreach ($items as $item) {
                $db->prepare("INSERT INTO bill_items (bill_id,item_type,description,quantity,unit_price,total_price) VALUES (?,?,?,?,?,?)")
                   ->execute([$bid,$item['type'],$item['desc'],$item['qty'],$item['price'],$item['qty']*$item['price']]);
            }
            $db->commit();
            $msg = "Bill $bill_number created! Total: ₹" . number_format($total, 2);
            if (isset($_POST['print'])) { header("Location: /billing.php?view=$bid"); exit; }
        } catch (Exception $e) { $db->rollBack(); $msg = 'Error: '.$e->getMessage(); $msg_type='danger'; }
    } elseif ($action === 'payment') {
        $bid = (int)$_POST['id']; $paid = floatval($_POST['paid_amount']);
        $bill = $db->prepare("SELECT total_amount FROM bills WHERE id=?"); $bill->execute([$bid]); $b = $bill->fetch();
        $status = $paid >= $b['total_amount'] ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');
        $db->prepare("UPDATE bills SET paid_amount=?, status=?, payment_method=? WHERE id=?")->execute([$paid,$status,$_POST['method'],$bid]);
        $msg = 'Payment updated.';
    }
}

$patients = $db->query("SELECT id, patient_id, full_name FROM patients ORDER BY full_name")->fetchAll();
$search = sanitize($_GET['search'] ?? '');
$where = $search ? "WHERE b.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?" : "";
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$bills = $db->prepare("SELECT b.*, p.full_name as patient_name, p.patient_id as pid FROM bills b JOIN patients p ON b.patient_id=p.id $where ORDER BY b.bill_date DESC LIMIT 50");
$bills->execute($params);
$bills = $bills->fetchAll();

$view_bill = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT b.*, p.full_name as patient_name, p.patient_id as pid, p.phone, p.address FROM bills b JOIN patients p ON b.patient_id=p.id WHERE b.id=?");
    $stmt->execute([(int)$_GET['view']]);
    $view_bill = $stmt->fetch();
    if ($view_bill) {
        $items_stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id=?");
        $items_stmt->execute([$view_bill['id']]);
        $view_items = $items_stmt->fetchAll();
    }
}

$action_mode = $_GET['action'] ?? '';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msg_type?>"><?=$msg?></div><?php endif; ?>

<?php if ($view_bill): ?>
<!-- Invoice View -->
<div class="section-header">
  <div class="flex flex-center gap-3">
    <a href="/billing.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
    <h2 style="font-size:17px;font-weight:700">Invoice: <?= $view_bill['bill_number'] ?></h2>
    <span class="badge <?= match($view_bill['status']){'Paid'=>'badge-success','Partial'=>'badge-warning','Cancelled'=>'badge-danger',default=>'badge-secondary'} ?>"><?= $view_bill['status'] ?></span>
  </div>
  <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
</div>
<div class="card" id="invoice">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;margin-bottom:24px;padding-bottom:20px;border-bottom:2px solid var(--border)">
      <div><div style="font-size:24px;font-weight:800;color:var(--primary)">🏥 MedCare HMS</div><div style="font-size:13px;color:var(--text-muted)">Hospital Management System</div></div>
      <div style="text-align:right"><div style="font-size:20px;font-weight:800">INVOICE</div><div style="font-size:14px;color:var(--text-muted)"># <?= $view_bill['bill_number'] ?></div><div style="font-size:13px;color:var(--text-muted)"><?= date('d M Y', strtotime($view_bill['bill_date'])) ?></div></div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:24px">
      <div><div style="font-weight:700;margin-bottom:6px">Bill To:</div><div style="font-size:14px"><strong><?= htmlspecialchars($view_bill['patient_name']) ?></strong></div><div style="font-size:13px;color:var(--text-muted)"><?= $view_bill['pid'] ?></div><div style="font-size:13px;color:var(--text-muted)"><?= $view_bill['phone'] ?></div><div style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($view_bill['address'] ?: '') ?></div></div>
    </div>
    <table style="width:100%;margin-bottom:20px">
      <thead><tr style="background:#f8fafc"><th style="padding:10px;text-align:left;border-bottom:2px solid var(--border)">Description</th><th style="padding:10px;text-align:center;border-bottom:2px solid var(--border)">Type</th><th style="padding:10px;text-align:center;border-bottom:2px solid var(--border)">Qty</th><th style="padding:10px;text-align:right;border-bottom:2px solid var(--border)">Unit Price</th><th style="padding:10px;text-align:right;border-bottom:2px solid var(--border)">Total</th></tr></thead>
      <tbody>
      <?php foreach($view_items as $item): ?>
      <tr><td style="padding:10px;border-bottom:1px solid #f1f5f9"><?= htmlspecialchars($item['description']) ?></td><td style="padding:10px;text-align:center;border-bottom:1px solid #f1f5f9"><span class="badge badge-secondary"><?= $item['item_type'] ?></span></td><td style="padding:10px;text-align:center;border-bottom:1px solid #f1f5f9"><?= $item['quantity'] ?></td><td style="padding:10px;text-align:right;border-bottom:1px solid #f1f5f9">₹<?= number_format($item['unit_price'],2) ?></td><td style="padding:10px;text-align:right;border-bottom:1px solid #f1f5f9;font-weight:600">₹<?= number_format($item['total_price'],2) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="display:flex;justify-content:flex-end"><div style="width:280px">
      <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px"><span>Subtotal</span><span>₹<?= number_format($view_bill['subtotal'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:var(--success)"><span>Discount</span><span>-₹<?= number_format($view_bill['discount'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px"><span>Tax (5%)</span><span>₹<?= number_format($view_bill['tax'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:17px;font-weight:800;border-top:2px solid var(--border);margin-top:6px"><span>Total</span><span>₹<?= number_format($view_bill['total_amount'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:var(--success)"><span>Paid</span><span>₹<?= number_format($view_bill['paid_amount'],2) ?></span></div>
      <?php $due = $view_bill['total_amount'] - $view_bill['paid_amount']; if($due > 0): ?>
      <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:var(--danger);font-weight:700"><span>Due</span><span>₹<?= number_format($due,2) ?></span></div>
      <?php endif; ?>
    </div></div>
    <?php if($due > 0): ?>
    <hr class="divider">
    <form method="POST" class="flex gap-2" style="max-width:400px;margin:0 auto;justify-content:center">
      <input type="hidden" name="action" value="payment"><input type="hidden" name="id" value="<?= $view_bill['id'] ?>">
      <input type="number" name="paid_amount" class="form-control" value="<?= $view_bill['paid_amount'] ?>" step="0.01" placeholder="Paid amount">
      <select name="method" class="form-select" style="width:130px"><?php foreach(['Cash','Card','Insurance','Online','Cheque'] as $pm): ?><option><?=$pm?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Pay</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($action_mode === 'add'): ?>
<!-- Create Bill -->
<div class="section-header">
  <div class="flex flex-center gap-3"><a href="/billing.php" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i></a><h2 style="font-size:17px;font-weight:700">Create New Bill</h2></div>
</div>
<div class="card"><div class="card-body">
<form method="POST" id="billForm">
  <input type="hidden" name="action" value="add"><input type="hidden" name="items_json" id="items_json">
  <div class="form-grid-2">
    <div class="form-group"><label class="form-label">Patient *</label>
      <select name="patient_id" class="form-select" required>
        <option value="">-- Select Patient --</option>
        <?php foreach($patients as $p): ?><option value="<?=$p['id']?>"><?= htmlspecialchars($p['full_name']) ?> (<?= $p['patient_id'] ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Payment Method</label>
      <select name="payment_method" class="form-select"><?php foreach(['Cash','Card','Insurance','Online','Cheque'] as $pm): ?><option><?=$pm?></option><?php endforeach; ?></select>
    </div>
  </div>
  <hr class="divider">
  <div style="font-weight:700;font-size:14px;margin-bottom:12px">Bill Items</div>
  <div id="items_container"></div>
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;font-size:12px;font-weight:600;color:var(--text-muted);padding:0 4px">
    <span>Description</span><span>Type</span><span>Qty</span><span>Unit Price</span><span></span>
  </div>
  <div id="items_list"></div>
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:12px">
    <input type="text" id="item_desc" class="form-control" placeholder="Description">
    <select id="item_type" class="form-select"><?php foreach(['Consultation','Admission','Medicine','Lab Test','Procedure','Other'] as $t): ?><option><?=$t?></option><?php endforeach; ?></select>
    <input type="number" id="item_qty" class="form-control" value="1" min="1">
    <input type="number" id="item_price" class="form-control" placeholder="Price" step="0.01" min="0">
    <button type="button" onclick="addItem()" class="btn btn-success"><i class="fa fa-plus"></i></button>
  </div>
  <hr class="divider">
  <div class="form-grid-3">
    <div class="form-group"><label class="form-label">Discount (₹)</label><input type="number" name="discount" class="form-control" value="0" min="0" step="0.01" onchange="calcTotal()"></div>
    <div class="form-group"><label class="form-label">Amount Paid (₹)</label><input type="number" name="paid_amount" id="paid_amount" class="form-control" value="0" step="0.01"></div>
    <div class="form-group"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
  </div>
  <div style="background:#f8fafc;border-radius:10px;padding:16px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px"><span>Subtotal</span><span id="disp_subtotal">₹0.00</span></div>
    <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px;color:var(--success)"><span>Discount</span><span id="disp_discount">-₹0.00</span></div>
    <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px"><span>Tax (5%)</span><span id="disp_tax">₹0.00</span></div>
    <div style="display:flex;justify-content:space-between;font-size:17px;font-weight:800;border-top:2px solid var(--border);padding-top:10px"><span>TOTAL</span><span id="disp_total">₹0.00</span></div>
  </div>
  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary" onclick="submitBill()"><i class="fa fa-save"></i> Create Bill</button>
    <button type="submit" name="print" class="btn btn-success" onclick="submitBill()"><i class="fa fa-print"></i> Create & Print</button>
    <a href="/billing.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
</div></div>
<script>
let items = [];
function addItem() {
  const desc = document.getElementById('item_desc').value.trim();
  const type = document.getElementById('item_type').value;
  const qty = parseInt(document.getElementById('item_qty').value);
  const price = parseFloat(document.getElementById('item_price').value);
  if (!desc || !price) return alert('Fill description and price');
  items.push({desc, type, qty, price});
  renderItems(); calcTotal();
  document.getElementById('item_desc').value=''; document.getElementById('item_price').value=''; document.getElementById('item_qty').value=1;
}
function removeItem(i) { items.splice(i,1); renderItems(); calcTotal(); }
function renderItems() {
  const c = document.getElementById('items_list'); c.innerHTML = '';
  items.forEach((it,i) => {
    const row = document.createElement('div');
    row.style = 'display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:6px;background:#f8fafc;padding:8px;border-radius:6px;align-items:center;font-size:13px';
    row.innerHTML = `<span>${it.desc}</span><span><span class="badge badge-secondary">${it.type}</span></span><span>${it.qty}</span><span>₹${it.price.toFixed(2)}</span><button type="button" onclick="removeItem(${i})" class="btn btn-danger btn-sm btn-icon"><i class="fa fa-times"></i></button>`;
    c.appendChild(row);
  });
}
function calcTotal() {
  const sub = items.reduce((s,it)=>s+it.qty*it.price,0);
  const disc = parseFloat(document.querySelector('[name=discount]')?.value||0);
  const tax = (sub - disc) * 0.05;
  const total = sub - disc + tax;
  document.getElementById('disp_subtotal').textContent = '₹'+sub.toFixed(2);
  document.getElementById('disp_discount').textContent = '-₹'+disc.toFixed(2);
  document.getElementById('disp_tax').textContent = '₹'+tax.toFixed(2);
  document.getElementById('disp_total').textContent = '₹'+total.toFixed(2);
  document.getElementById('paid_amount').value = total.toFixed(2);
}
function submitBill() { document.getElementById('items_json').value = JSON.stringify(items); }
</script>

<?php else: ?>
<!-- Bills List -->
<div class="section-header">
  <form method="GET" class="search-bar"><span class="search-icon"><i class="fa fa-search"></i></span><input type="text" name="search" class="form-control" placeholder="Search bills..." value="<?= htmlspecialchars($search) ?>"></form>
  <a href="/billing.php?action=add" class="btn btn-primary"><i class="fa fa-plus"></i> Create Bill</a>
</div>
<div class="card"><div class="card-body" style="padding:0">
  <div class="table-container">
    <table>
      <thead><tr><th>Bill No.</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Due</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($bills as $b):
        $due = $b['total_amount'] - $b['paid_amount'];
        $sc = match($b['status']){'Paid'=>'badge-success','Partial'=>'badge-warning','Cancelled'=>'badge-danger',default=>'badge-secondary'};
      ?>
      <tr>
        <td><span class="badge badge-primary"><?= $b['bill_number'] ?></span></td>
        <td><strong><?= htmlspecialchars($b['patient_name']) ?></strong><br><span style="font-size:12px;color:var(--text-muted)"><?= $b['pid'] ?></span></td>
        <td style="font-size:13px"><?= date('d M Y', strtotime($b['bill_date'])) ?></td>
        <td style="font-weight:700">₹<?= number_format($b['total_amount'],2) ?></td>
        <td style="color:var(--success);font-weight:600">₹<?= number_format($b['paid_amount'],2) ?></td>
        <td style="color:<?= $due>0?'var(--danger)':'var(--text-muted)' ?>;font-weight:<?= $due>0?700:400 ?>">₹<?= number_format($due,2) ?></td>
        <td style="font-size:13px"><?= $b['payment_method'] ?></td>
        <td><span class="badge <?=$sc?>"><?= $b['status'] ?></span></td>
        <td><a href="/billing.php?view=<?= $b['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa fa-eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
