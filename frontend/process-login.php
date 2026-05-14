<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/csrf.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    $_SESSION['auth_message'] = 'Invalid security token. Please try again.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

function loginUserColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_message'] = 'Please enter a valid email and password.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

$loginColumns = [
    "status" => "VARCHAR(20) DEFAULT 'active'",
    "is_blocked" => "TINYINT(1) DEFAULT 0",
    "is_deleted" => "TINYINT(1) DEFAULT 0",
    "deleted_at" => "DATETIME NULL",
    "last_login" => "DATETIME NULL"
];
foreach ($loginColumns as $column => $definition) {
    if (!loginUserColumnExists($conn, $column)) {
        $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
    }
}
$conn->query("ALTER TABLE users MODIFY status VARCHAR(20) DEFAULT 'active'");
$conn->query("UPDATE users SET status = 'active' WHERE (status IS NULL OR status = '' OR status = '0') AND COALESCE(is_blocked, 0) = 0 AND COALESCE(is_deleted, 0) = 0 AND deleted_at IS NULL");

$stmt = $conn->prepare("SELECT id, full_name, username, email, password, status, is_blocked, is_deleted, deleted_at FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['auth_message'] = 'Invalid email or password.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

if ((int) ($user['is_blocked'] ?? 0) === 1) {
    $_SESSION['auth_message'] = 'Your account has been temporarily blocked.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

if (isUserLoginBlocked($user)) {
    $_SESSION['auth_message'] = 'Your account is not active. Please contact support.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['profile_message'] = 'Sign In successful.';

if (!empty($_POST['remember_me'])) {
    createRememberCookie($conn, (int) $user['id'], 30);
} else {
    clearRememberCookie($conn);
}

$loginUpdate = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$loginUpdate->bind_param("i", $user['id']);
$loginUpdate->execute();

header("Location: profile.php");
exit;
