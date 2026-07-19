<?php
require_once __DIR__ . '/../includes/auth.php';
startSession();
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } elseif (login($username, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - MedCare HMS</title>
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon">🏥</div>
      <h2>MedCare HMS</h2>
      <p>Hospital Management System</p>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-circle-xmark"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="input-group">
          <span style="padding:9px 13px;background:#f8fafc;border:1px solid var(--border);border-right:none;border-radius:8px 0 0 8px;color:var(--text-muted)"><i class="fa fa-user"></i></span>
          <input type="text" name="username" class="form-control" placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required style="border-radius:0 8px 8px 0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-group">
          <span style="padding:9px 13px;background:#f8fafc;border:1px solid var(--border);border-right:none;border-radius:8px 0 0 8px;color:var(--text-muted)"><i class="fa fa-lock"></i></span>
          <input type="password" name="password" class="form-control" placeholder="Enter password" required style="border-radius:0 8px 8px 0">
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="width:100%;justify-content:center;padding:12px">
        <i class="fa fa-sign-in-alt"></i> Sign In
      </button>
    </form>
    <div style="margin-top:24px;padding:16px;background:#f8fafc;border-radius:10px;border:1px solid var(--border)">
      <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase">Demo Credentials</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
        <?php foreach([['admin','Admin'],['dr.smith','Doctor'],['nurse.anita','Nurse'],['receptionist1','Reception']] as [$u,$r]): ?>
        <div style="background:#fff;padding:8px 10px;border-radius:7px;border:1px solid var(--border);cursor:pointer;font-size:12px" onclick="document.querySelector('[name=username]').value='<?=$u?>';document.querySelector('[name=password]').value='password'">
          <strong><?=$r?></strong><br><span style="color:var(--text-muted)"><?=$u?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:8px;font-size:11px;color:var(--text-muted);text-align:center">Password: <strong>password</strong> (click any card to fill)</div>
    </div>
  </div>
</div>
</body>
</html>
