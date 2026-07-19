<?php
$page_title = 'User Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');
$db = getDB();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
        try {
            $db->prepare("INSERT INTO users (username,password,full_name,email,role,phone) VALUES (?,?,?,?,?,?)")
               ->execute([sanitize($_POST['username']),$pass,sanitize($_POST['full_name']),sanitize($_POST['email']),$_POST['role'],sanitize($_POST['phone'])]);
            $msg = 'User created!';
        } catch (Exception $e) { $msg = 'Error: '.$e->getMessage(); $msg_type='danger'; }
    } elseif ($action === 'toggle') {
        $u = $db->prepare("SELECT is_active FROM users WHERE id=?"); $u->execute([(int)$_POST['id']]); $cur = $u->fetchColumn();
        $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$cur?0:1,(int)$_POST['id']]);
        $msg = 'User status toggled.';
    } elseif ($action === 'reset_pass') {
        $pass = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$pass,(int)$_POST['id']]);
        $msg = 'Password reset.';
    } elseif ($action === 'delete') {
        if ((int)$_POST['id'] !== $_SESSION['user_id']) {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_POST['id']]);
            $msg = 'User deleted.'; $msg_type='warning';
        } else { $msg = 'Cannot delete yourself.'; $msg_type='danger'; }
    }
}

$users = $db->query("SELECT * FROM users ORDER BY role,full_name")->fetchAll();
$role_colors = ['admin'=>'badge-danger','doctor'=>'badge-primary','nurse'=>'badge-success','receptionist'=>'badge-info','pharmacist'=>'badge-warning','lab_tech'=>'badge-cyan'];
?>
<?php if ($msg): ?><div class="alert alert-<?=$msg_type?>"><?=$msg?></div><?php endif; ?>

<div class="section-header">
  <span class="section-title"><?= count($users) ?> System Users</span>
  <button onclick="document.getElementById('addUserModal').classList.add('active')" class="btn btn-primary"><i class="fa fa-user-plus"></i> Add User</button>
</div>

<div class="card"><div class="card-body" style="padding:0">
  <div class="table-container">
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($users as $u): ?>
      <tr style="<?= $u['id']==$_SESSION['user_id']?'background:#eff6ff':'' ?>">
        <td>
          <div class="flex flex-center gap-2">
            <div class="avatar" style="width:32px;height:32px;font-size:12px;background:<?= $u['role']==='admin'?'var(--danger)':'var(--primary)' ?>"><?= strtoupper(substr($u['full_name'],0,2)) ?></div>
            <strong><?= htmlspecialchars($u['full_name']) ?><?= $u['id']==$_SESSION['user_id']?' (You)':'' ?></strong>
          </div>
        </td>
        <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($u['username']) ?></td>
        <td><span class="badge <?= $role_colors[$u['role']] ?? 'badge-secondary' ?>"><?= str_replace('_',' ', ucfirst($u['role'])) ?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
        <td style="font-size:13px"><?= $u['phone'] ?: '—' ?></td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$u['id']?>">
            <button type="submit" class="badge <?= $u['is_active']?'badge-success':'badge-danger' ?>" style="border:none;cursor:pointer"><?= $u['is_active']?'Active':'Inactive' ?></button>
          </form>
        </td>
        <td>
          <div class="flex gap-2">
            <button onclick="showResetModal(<?=$u['id']?>,'<?= htmlspecialchars($u['full_name'],ENT_QUOTES) ?>')" class="btn btn-warning btn-sm btn-icon" title="Reset Password"><i class="fa fa-key"></i></button>
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete user?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="fa fa-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Add New User</span><button class="modal-close" onclick="document.getElementById('addUserModal').classList.remove('active')">×</button></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-grid-2">
          <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Role *</label>
            <select name="role" class="form-select" required>
              <?php foreach(['admin','doctor','nurse','receptionist','pharmacist','lab_tech'] as $r): ?><option value="<?=$r?>"><?= str_replace('_',' ', ucfirst($r)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="width:100%;justify-content:center"><i class="fa fa-save"></i> Create User</button>
      </form>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><span class="modal-title" id="reset_modal_title">Reset Password</span><button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('active')">×</button></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="reset_pass">
        <input type="hidden" name="id" id="reset_user_id">
        <div class="form-group"><label class="form-label">New Password *</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
        <button type="submit" class="btn btn-warning w-full" style="width:100%;justify-content:center"><i class="fa fa-key"></i> Reset Password</button>
      </form>
    </div>
  </div>
</div>
<script>
function showResetModal(id, name) {
  document.getElementById('reset_user_id').value = id;
  document.getElementById('reset_modal_title').textContent = 'Reset Password: ' + name;
  document.getElementById('resetModal').classList.add('active');
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
