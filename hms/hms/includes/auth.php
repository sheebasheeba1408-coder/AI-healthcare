<?php
require_once __DIR__ . '/../config/database.php';

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function hasRole($role) {
    startSession();
    if (!isset($_SESSION['role'])) return false;
    if (is_array($role)) return in_array($_SESSION['role'], $role);
    return $_SESSION['role'] === $role;
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: /dashboard.php?error=unauthorized');
        exit;
    }
}

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        logActivity($user['id'], 'LOGIN', 'users', $user['id'], 'User logged in');
        return true;
    }
    return false;
}

function logout() {
    startSession();
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], 'User logged out');
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}

function logActivity($userId, $action, $table = null, $recordId = null, $details = null) {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, details, ip_address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$userId, $action, $table, $recordId, $details, $ip]);
    } catch (Exception $e) {}
}

function generatePatientId() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM patients");
    $count = $stmt->fetch()['cnt'];
    return 'PAT-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

function generateBillNumber() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM bills");
    $count = $stmt->fetch()['cnt'];
    return 'BILL-' . date('Y') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getStats() {
    $db = getDB();
    $stats = [];
    $stats['total_patients'] = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $stats['today_appointments'] = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
    $stats['admitted_patients'] = $db->query("SELECT COUNT(*) FROM admissions WHERE status='Admitted'")->fetchColumn();
    $stats['available_beds'] = $db->query("SELECT SUM(available_beds) FROM wards")->fetchColumn();
    $stats['total_doctors'] = $db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
    $stats['pending_bills'] = $db->query("SELECT COUNT(*) FROM bills WHERE status='Pending'")->fetchColumn();
    $stats['monthly_revenue'] = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM bills WHERE MONTH(bill_date)=MONTH(CURDATE()) AND YEAR(bill_date)=YEAR(CURDATE())")->fetchColumn();
    $stats['low_stock'] = $db->query("SELECT COUNT(*) FROM medicines WHERE stock_quantity <= reorder_level")->fetchColumn();
    return $stats;
}
?>
