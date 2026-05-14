<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/csrf.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    $_SESSION['auth_message'] = 'Invalid security token. Please try again.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

function registerUserColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

foreach ([
    "status" => "VARCHAR(20) DEFAULT 'active'",
    "is_blocked" => "TINYINT(1) DEFAULT 0",
    "is_deleted" => "TINYINT(1) DEFAULT 0",
    "deleted_at" => "DATETIME NULL"
] as $column => $definition) {
    if (!registerUserColumnExists($conn, $column)) {
        $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
    }
}
$conn->query("ALTER TABLE users MODIFY status VARCHAR(20) DEFAULT 'active'");

if ($fullName === '' || $username === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
    $_SESSION['auth_message'] = 'Please fill all registration fields.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_message'] = 'Please enter a valid email address.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['auth_message'] = 'Passwords do not match.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

$check = $conn->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
$check->bind_param("ss", $username, $email);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    $_SESSION['auth_message'] = $existing['username'] === $username ? 'Username already taken.' : 'Email already exists.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$activeStatus = 'active';
$stmt = $conn->prepare("INSERT INTO users (full_name, username, email, phone, gender, password, status, is_blocked, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)");
$stmt->bind_param("sssssss", $fullName, $username, $email, $phone, $gender, $hashedPassword, $activeStatus);
$stmt->execute();

session_regenerate_id(true);
$_SESSION['user_id'] = $stmt->insert_id;
$_SESSION['full_name'] = $fullName;
$_SESSION['username'] = $username;
$_SESSION['email'] = $email;
$_SESSION['profile_message'] = 'Sign Up successful.';
header("Location: profile.php");
exit;
